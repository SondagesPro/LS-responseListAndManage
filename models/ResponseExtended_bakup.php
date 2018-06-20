<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @see SurveyDynamic
 */
//~ namespace responseListAndManage\models;
//~ use Yii;

class ResponseExtended extends LSActiveRecord
{

    /** @var int $sid */
    protected static $sid = 0;

    /** @var Survey $survey */
    protected static $survey;

    /** @var  boolean $bHaveToken */
    protected $haveToken;

    /** @var string $completed_filter */
    public $completed;
    /** @var integer $lastpage */
    public $lastpage;

    /**
     * @inheritdoc
     * @return SurveyDynamic
     */
    public static function model($sid = null) {
        $refresh = false;
        $survey = Survey::model()->findByPk($sid);
        if ($survey) {
            //~ if(self::sid != $survey->sid) {
                //~ $refresh = true;
            //~ }
            self::sid($survey->sid);
            self::$survey = $survey;
        }

        /** @var self $model */
        $model = parent::model(__CLASS__);

        //We need to refresh if we changed sid
        if ($refresh) {
            $model->refreshMetaData();
        }

        return $model;
    }

    /**
     * Set defaults
     * @inheritdoc
     */
    public function init()
    {
        /** @inheritdoc */
        $this->attachEventHandler("onAfterFind", array($this, 'afterFind'));
    }

    public function afterFind()
    {
        $this->completed = $this->getCompleted();
    }
    /**
     * Sets the survey ID for the next model
     *
     * @static
     * @access public
     * @param int $sid
     * @return void
     */
    public static function sid($sid)
    {
        self::$sid = (int) $sid;
    }

    /** @inheritdoc */
    public function tableName()
    {
        return '{{survey_'.self::$sid.'}}';
    }

    /** @inheritdoc */
    public function relations()
    {
        $relations = array(
            'survey'   => array(self::HAS_ONE, 'Survey', array(), 'condition'=>('sid = '.self::$sid)),
        );
        if ($this->getHaveToken()) {
            TokenDynamic::sid(self::$sid);
            $relations['tokens'] = array(self::BELONGS_TO, 'TokenDynamic', array('token' => 'token'));
        }
        return $relations;
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return 'id';
    }

    /**
     * @return bool
     */
    private function getHaveToken()
    {
        return false;
        if (!isset($this->haveToken)) {
            $this->haveToken = (self::$survey->anonymized != "Y") && tableExists('tokens_'.self::$sid) && Permission::model()->hasSurveyPermission(self::$sid, 'tokens', 'read'); // Boolean : show (or not) the token;
        }
        return $this->haveToken;
    }

    /**
     * Get the list of default columns for surveys
     * @return string[]
     */
    public function getDefaultColumns()
    {
        return array('id', 'token', 'submitdate', 'lastpage', 'startlanguage', 'completed', 'seed');
    }

    /**
     * @return CActiveDataProvider
     */
    public function search()
    {
        $pageSize = Yii::app()->user->getState('pageSize', Yii::app()->params['defaultPageSize']);
        $criteria = new CDbCriteria;
        // Join the survey participants table and filter tokens if needed
        if ($this->haveToken && $this->survey->anonymized != 'Y') {
            $criteria->with = 'tokens';
        }
        $sort     = new CSort;
        $sort->defaultOrder = 'id ASC';

        // Make all the model's columns sortable (default behaviour)
        $sort->attributes = array(
            '*',
        );
        // Completed filters
        if ($this->completed == "Y") {
            $criteria->addCondition('t.submitdate IS NOT NULL');
        }
        if ($this->completed == "N") {
            $criteria->addCondition('t.submitdate IS NULL');
        }
        // Token filters
        $aTokenFilter = App()->getRequest()->getParam('TokenDynamic');
        if(!empty($aTokenFilter)) {
            $aTokenFilter = App()->getRequest()->getParam('TokenDynamic');
            $aTokenFilter['email'] = isset($aTokenFilter['email']) ? $aTokenFilter['email'] : null;
            $criteria->compare('tokens.email',$aTokenFilter['email'],true);
        }
        $this->filterColumns($criteria);
        $dataProvider = new CActiveDataProvider('ResponseExtended', array(
            'sort'=>$sort,
            'criteria'=>$criteria,
            'pagination'=>array(
                'pageSize'=>$pageSize,
            ),
        ));
        return $dataProvider;
    }

    /**
     * Loop through columns and add filter if any value is given for this column
     * Used in responses grid
     * @param CdbCriteria $criteria
     * @return void
     */
    protected function filterColumns(CDbCriteria $criteria)
    {
        $dateFormatDetails = getDateFormatData(Yii::app()->session['dateformat']);

        // Filters for responses
        foreach ($this->metaData->columns as $column) {
            if (!in_array($column->name, $this->defaultColumns)) {
                $c1 = (string) $column->name;
                $columnHasValue = !empty($this->$c1);
                if ($columnHasValue) {
                    $isDatetime = strpos($column->dbType, 'timestamp') !== false || strpos($column->dbType, 'datetime') !== false;
                    if ($column->dbType == 'decimal') {
                        $this->$c1 = (float) $this->$c1;
                        $criteria->compare(Yii::app()->db->quoteColumnName($c1), $this->$c1, false);
                    } elseif ($isDatetime) {
                        $s = DateTime::createFromFormat($dateFormatDetails['phpdate'], $this->$c1);
                        if ($s === false) {
                            // This happens when date is in wrong format
                            continue;
                        }
                        $s2 = $s->format('Y-m-d');
                        $criteria->addCondition('cast('.Yii::app()->db->quoteColumnName($c1).' as date) = \''.$s2.'\'');
                    } else {
                        $criteria->compare(Yii::app()->db->quoteColumnName($c1), $this->$c1, true);
                    }
                }
            }
        }
    }

    /**
     * get columns for grid
     * @param null|array columns to be shown
     * @return array
     */
    public function getGridColumns($showColumns=null)
    {
        $aColumns = array();
        $aColumns['buttons']=array(
            'htmlOptions' => array('nowrap'=>'nowrap'),
            'class'=>'bootstrap.widgets.TbButtonColumn',
            'template'=>'{view}',
            'viewButtonUrl'=>'App()->createUrl("survey/index",array("sid"=>'.self::$sid.',"srid"=>$data["id"]))',
        );
        /* Basic columns */
        $aColumns['id']=array(
            'header' => '<strong>[id]</strong><small>'.gT('Identifier').'</small>',
            'name' => 'id',
            'filterInputOptions' => array('class'=>'form-control input-sm filter-id'),
        );
        $aColumns['completed']=array(
            'header' => gT('Completed'),
            'name' => 'completed',
            'type'=>'raw',
            'value' => 'ResponseExtended::getCompletedGrid($data)',
            'filter'=> array(''=>gT('All'),'Y'=>gT('Yes'),'N'=>gT('No')),
            'filterInputOptions' => array('class'=>'form-control input-sm filter-completed'),
        );
        if(self::$survey->datestamp =="Y") {
            $aColumns['submitdate']=array(
                'header' => '<strong>[submitdate]</strong><small>'.gT('Submit date').'</small>',
                'name' => 'submitdate',
                'value' => 'ResponseExtended::getSubmitdateValue($data)',
                'filter' => null,
                'filterInputOptions' => array('class'=>'form-control input-sm filter-submitdate'),
            );
        }

        if($this->getHaveToken()) {
            $aTokenFilter = App()->getRequest()->getParam('TokenDynamic');
            $aTokenFilter['email'] = isset($aTokenFilter['email']) ? $aTokenFilter['email'] : '';
            $aColumns['tokens.email']=array(
                'header' => '<strong>[token]</strong><small>'.gT('Token').'</small>',
                'name' => 'tokens.email',
                'value' => '$data->tokens->email',
                'filter' => '<input class="form-control input-sm filter-tokens-email" name="TokenDynamic[email]" type="text" value="'.$aTokenFilter['email'].'">',
                //'filterInputOptions' => array('class'=>'form-control input-sm filter-tokens-email'),
            );
        }
        $aColumns = array_merge($aColumns,\getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestions(self::$sid));
        if($showColumns) {
            // @TODO
        }
        
        return $aColumns;
    }

    public static function getSubmitdateValue($data) {
        return $data->submitdate;
    }

    public static function getAnswerValue($data,$name,$type,$iQid) {
        return $data->$name;

    }
    public function getCompleted() {
        return (bool)$this->submitdate;
    }
    public static function getCompletedGrid($data) {
        if($data->submitdate) {
            if(self::$survey->datestamp =="Y") {
                return "<span class='text-success fa fa-check' title='{$data->submitdate}'></span>";
            }
            return "<span class='text-success fa fa-check'></span>";
        }
        return "<span class='text-warning fa fa-times'></span>";
    }
    /**
     * @param CDbCriteria $criteria
     * @param CSort $sort
     * @return void
     */
    //~ protected function joinWithToken(CDbCriteria $criteria, CSort $sort)
    //~ {
        //~ $criteria->compare('t.token', $this->token, true);
        //~ $criteria->join = "LEFT JOIN {{tokens_".self::$sid."}} as tokens ON t.token = tokens.token";
        //~ $criteria->compare('tokens.firstname', $this->firstname_filter, true);
        //~ $criteria->compare('tokens.lastname', $this->lastname_filter, true);
        //~ $criteria->compare('tokens.email', $this->email_filter, true);

        //~ // Add the related token model's columns sortable
        //~ $aSortVirtualAttributes = array(
            //~ 'tokens.firstname'=>array(
                //~ 'asc'=>'tokens.firstname ASC',
                //~ 'desc'=>'tokens.firstname DESC',
            //~ ),
            //~ 'tokens.lastname' => array(
                //~ 'asc'=>'lastname ASC',
                //~ 'desc'=>'lastname DESC'
            //~ ),
            //~ 'tokens.email' => array(
                //~ 'asc'=>'email ASC',
                //~ 'desc'=>'email DESC'
            //~ ),
        //~ );

        //~ $sort->attributes = array_merge($sort->attributes, $aSortVirtualAttributes);
    //~ }

}
