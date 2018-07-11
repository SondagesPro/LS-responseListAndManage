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

    /** @var  object */
    protected $tokenRelated;

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
     * @inheritdoc
     */
    public function setScenario($scenario) {
        parent::setScenario($scenario);
        if($scenario == 'search') {
            if ($this->getHaveToken()) {
                $oToken = TokenDynamic::model(self::$sid);
                $this->tokenRelated = $oToken;
            }
        }
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
     * return if token column can be returned
     * @return bool
     */
    private function getHaveToken()
    {
        if (!isset($this->haveToken)) {
            $this->haveToken = self::$survey->anonymized != "Y" && tableExists('tokens_'.self::$sid) && (Permission::model()->hasSurveyPermission(self::$sid, 'token', 'read') || Yii::app()->user->getState('disableTokenPermission')); // Boolean : show (or not) the token;
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
        $this->setScenario('search');
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
        // Token sort
        if($this->getHaveToken()) {
            $sort->attributes = array_merge($sort->attributes,$this->getTokensSort());
        }
        $sort->attributes = array_merge($sort->attributes,array('completed' => array(
                'asc'=>'submitdate ASC',
                'desc'=>'submitdate DESC',
        )));
        // Completed filters
        if ($this->completed == "Y") {
            $criteria->addCondition('t.submitdate IS NOT NULL');
        }
        if ($this->completed == "N") {
            $criteria->addCondition('t.submitdate IS NULL');
        }
        if($this->id) {
            $criteria->compare('id',$this->id,true);
        }
        if($this->token) {
            if(is_array($this->token)) {
                $criteria->addInCondition('t.token',$this->token);
            } else {
                $criteria->compare('t.token',$this->token,false);
            }
        }
        // Token filters
        if($this->getHaveToken()) {
            $this->filterTokenColumns($criteria);
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

    protected function filterTokenColumns(CDbCriteria $criteria)
    {
        $tokensAttributes = $this->tokenRelated->getAttributes();
        //~ unset($tokensAttributes['token']);
        foreach($tokensAttributes as $attribute=>$value) {
            $criteria->compare('tokens.'.$attribute, $value, true);
        }
    }
    /**
     * get columns for grid
     * @return array
     */
    public function getGridColumns()
    {
        $aColumns = array();
        /* Basic columns */
        $aColumns['id']=array(
            'header' => '<strong>[id]</strong><small>'.gT('Identifier').'</small>',
            'name' => 'id',
            'htmlOptions' => array('class' => 'data-column column-id'),
            'filterInputOptions' => array('class'=>'form-control input-sm filter-id'),
        );
        $aColumns['completed']=array(
            'header' => '<strong>[completed]</strong><small>'.gT('Completed'),
            'name' => 'completed',
            'htmlOptions' => array('class' => 'data-column column-completed'),
            'type'=>'raw',
            'value' => 'ResponseExtended::getCompletedGrid($data)',
            'filter'=> array('Y'=>gT('Yes'),'N'=>gT('No')),
            'filterInputOptions' => array('class'=>'form-control input-sm filter-completed'),
        );
        if(self::$survey->datestamp =="Y") {
            $aColumns['submitdate']=array(
                'header' => '<strong>[submitdate]</strong><small>'.gT('Submit date').'</small>',
                'name' => 'submitdate',
                'htmlOptions' => array('class' => 'data-column column-submitdate'),
                'value' => 'ResponseExtended::getSubmitdateValue($data)',
                'filter' => null,
                'filterInputOptions' => array('class'=>'form-control input-sm filter-submitdate'),
            );
        }
        if($this->getHaveToken()) {
            $aColumns = array_merge($aColumns,$this->getTokensColumns());
        }
        $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation(self::$sid,App()->getLanguage());
        $surveyColumnsInformation->downloadUrl = array(
            'route' => "plugins/direct",
            'params' => array(
                'plugin' => "responseListAndManage",
                'action' => 'download',
            )
        );
        $aColumns = array_merge($aColumns,$surveyColumnsInformation->allQuestionsColumns());

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

    public function setTokenAttributes($tokens=array()) {
        if(!$this->getHaveToken()) {
            return;
        }
        $this->tokenRelated->setAttributes($tokens,false);
    }

    public function getTokensColumns()
    {
        $aColumns = array();
        $aColumns['tokens.token']=array(
            'header' => '<strong>[token]</strong><small>'.gT('Token').'</small>',
            'name' => 'tokens.token',
            'value' => 'empty($data->tokens) ? "" : $data->tokens->token;',
            'htmlOptions' => array('class' => 'data-column column-token-token'),
            'filter' => CHtml::activeTextField($this->tokenRelated,"token",array('class'=>'form-control input-sm filter-token-token')),
        );
        $aColumns['tokens.email']=array(
            'header' => '<strong>[email]</strong><small>'.gT('Email').'</small>',
            'name' => 'tokens.email',
            'value' => 'empty($data->tokens) ? "" : $data->tokens->email;',
            'htmlOptions' => array('class' => 'data-column column-token-email'),
            'filter' => CHtml::activeTextField($this->tokenRelated,"email",array('class'=>'form-control input-sm filter-token-email')),
        );
        $aColumns['tokens.firstname']=array(
            'header' => '<strong>[firstname]</strong><small>'.gT('First name').'</small>',
            'name' => 'tokens.firstname',
            'type' => 'raw',
            'value' => 'empty($data->tokens) ? "" : $data->tokens->firstname;',
            'htmlOptions' => array('class' => 'data-column column-token-firstname'),
            'filter' => CHtml::activeTextField($this->tokenRelated,"firstname",array('class'=>'form-control input-sm filter-token-firstname')),
        );
        $aColumns['tokens.lastname']=array(
            'header' => '<strong>[lastname]</strong><small>'.gT('Last name').'</small>',
            'name' => 'tokens.lastname',
            'value' => 'empty($data->tokens) ? "" : $data->tokens->lastname;',
            'htmlOptions' => array('class' => 'data-column column-token-lastname'),
            'filter' => CHtml::activeTextField($this->tokenRelated,"lastname",array('class'=>'form-control input-sm filter-token-lastname')),
        );
        $tokenAttributes = self::$survey->getTokenAttributes();
        foreach($tokenAttributes as $attribute=>$aDescrition) {
            $aColumns['tokens.'.$attribute]=array(
                'header' => '<strong>['.$attribute.']</strong><small>'.$aDescrition['description'].'</small>',
                'name' => 'tokens.'.$attribute,
                'value' => 'empty($data->tokens) ? "" : $data->tokens->'.$attribute.';',
                'htmlOptions' => array('class' => 'data-column column-token-attribute'),
                'filter' => CHtml::activeTextField($this->tokenRelated,$attribute,array('class'=>'form-control input-sm filter-token-attribute')),
            );
        }
        return $aColumns;
    }
    public function getTokensSort()
    {
        $aSort = array(
            'tokens.token',
            'tokens.email',
            'tokens.firstname',
            'tokens.lastname',
        );
        $tokenAttributes = self::$survey->getTokenAttributes();
        foreach($tokenAttributes as $attribute=>$aDescrition) {
            $aSort[] = 'tokens.'.$attribute;
        }
        return $aSort;
    }
}
