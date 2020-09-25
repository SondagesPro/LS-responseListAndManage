<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @see SurveyDynamic
 * @version 1.1.4
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

    /** @var  object */
    protected $restrictedColumns;

    /** @var boolean */
    public $showFooter;

    /** @var boolean */
    public $filterOnDate = true;
    /** @var integer */
    public $filterSubmitDate = 0;
    /** @var integer */
    public $filterStartdate = 0;
        /** @var integer */
    public $filterDatestamp = 0;

    /* @var */
    protected $sum;

    /**
     * @inheritdoc
     * @return SurveyDynamic
     */
    public static function model($sid = null) {
        $survey = Survey::model()->findByPk($sid);
        if ($survey) {
            self::sid($survey->sid);
            self::$survey = $survey;
        }

        /** @var self $model */
        $model = parent::model(__CLASS__);
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
        $pageSize = Yii::app()->user->getState('responseListAndManagePageSize', Yii::app()->params['defaultPageSize']);
        $this->setScenario('search');
        $criteria = new CDbCriteria;

        // Join the survey participants table and filter tokens if needed
        if ($this->haveToken && $this->survey->anonymized != 'Y') {
            $criteria->with = 'tokens';
        }
        $sort = $this->getSort();
        if(!empty($this->restrictedColumns)) {
            $criteria->select = $this->restrictedColumns;
        }
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
        if(self::$survey->anonymized != "Y" && $this->token) {
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
                        if(is_array($this->$c1)) {
                            $date = $this->$c1;
                            $dateFormat = empty($date['format']) ? 'Y-m-d' : $date['format'];
                            if(!empty($date['min'])) {
                                $minDate = DateTime::createFromFormat('!' . $dateFormat, trim($date['min']));
                                if ($minDate === false) {
                                    throw new CHttpException(500, "Invalid format for $c1 : $dateFormat");
                                }
                                $minDate = $minDate->format("Y-m-d H:i");
                                //~ $criteria->compare(Yii::app()->db->quoteColumnName($c1), ">=".$minDate);
                                $criteria->addCondition(Yii::app()->db->quoteColumnName($c1).' >= '.Yii::app()->db->quoteValue($minDate));
                            }
                            if(!empty($date['max'])) {
                                $maxDate = DateTime::createFromFormat('!' . $dateFormat, trim($date['max']));
                                if ($maxDate === false) {
                                    throw new CHttpException(500, "Invalid format for $c1 : $dateFormat");
                                }
                                $maxDate = $maxDate->format("Y-m-d H:i");
                                $criteria->addCondition(Yii::app()->db->quoteColumnName($c1).' < '.Yii::app()->db->quoteValue($maxDate));
                            }
                            continue;
                        }
                        $s = DateTime::createFromFormat("Y-m-d", $this->$c1);
                        if ($s === false) {
                            // This happens when date is in wrong format
                            continue;
                        }
                        $s2 = $s->format('Y-m-d');
                        $criteria->addCondition('cast('.Yii::app()->db->quoteColumnName($c1).' as date) = '.Yii::app()->db->quoteValue($s2));
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
        if($this->showFooter) {
            $aFooter = $this->getFooters();
        }
        $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation(self::$sid,App()->getLanguage());
        $surveyColumnsInformation->model = $this;
        $surveyColumnsInformation->downloadUrl = array(
            'route' => "plugins/direct",
            'params' => array(
                'plugin' => "responseListAndManage",
                'action' => 'download',
            )
        );
        $aColumns = array();
        /* Basic columns */
        $aColumns['id']=array(
            'header' => '<strong>[id]</strong><small>'.gT('Identifier').'</small>',
            'name' => 'id',
            'htmlOptions' => array('class' => 'data-column column-id'),
            'filterInputOptions' => array('class'=>'form-control input-sm filter-id'),
            'footer' => ($this->showFooter && isset($aFooter['id'])) ? $aFooter['id'] : null,
        );
        $aColumns['completed']=array(
            'header' => '<strong>[completed]</strong><small>'.gT('Completed'),
            'name' => 'completed',
            'htmlOptions' => array('class' => 'data-column column-completed'),
            'type'=>'raw',
            'value' => 'ResponseExtended::getCompletedGrid($data)',
            'filter'=> array('Y'=>gT('Yes'),'N'=>gT('No')),
            'filterInputOptions' => array('class'=>'form-control input-sm filter-completed'),
            'footer' => ($this->showFooter && isset($aFooter['completed'])) ? $aFooter['completed'] : null,
        );
        if(self::$survey->datestamp =="Y") {
            $allowDateFilter = method_exists($surveyColumnsInformation,'getDateFilter');
            $dateFormatData = getDateFormatData(\SurveyLanguageSetting::model()->getDateFormat(self::$sid,Yii::app()->getLanguage()));
            $dateFormat = $dateFormatData['phpdate'];
            $aColumns['startdate']=array(
                'header' => '<strong>[startdate]</strong><small>'.gT('Start date').'</small>',
                'name' => 'startdate',
                'htmlOptions' => array('class' => 'data-column column-startdate'),
                'value' => 'ResponseExtended::getDateValue($data,"startdate","'.$dateFormat.($this->filterStartdate > 1 ? " H:m:i": "").'")',
                'filter' => $this->getDateFilter("startdate",null,$this->filterStartdate),
                'filterInputOptions' => array('class'=>'form-control input-sm filter-startdate'),
                'footer' => ($this->showFooter && isset($aFooter['startdate'])) ? $aFooter['startdate'] : null,
            );
            $aColumns['submitdate']=array(
                'header' => '<strong>[submitdate]</strong><small>'.gT('Submit date').'</small>',
                'name' => 'submitdate',
                'htmlOptions' => array('class' => 'data-column column-submitdate'),
                'value' => 'ResponseExtended::getDateValue($data,"submitdate","'.$dateFormat.($this->filterSubmitDate > 1 ? " H:m:i": "").'")',
                'filter' => $this->getDateFilter("submitdate",null,$this->filterSubmitDate),
                'filterInputOptions' => array('class'=>'form-control input-sm filter-submitdate'),
                'footer' => ($this->showFooter && isset($aFooter['submitdate'])) ? $aFooter['submitdate'] : null,
            );
            $aColumns['datestamp']=array(
                'header' => '<strong>[datestamp]</strong><small>'.gT('Date stamp').'</small>',
                'name' => 'datestamp',
                'htmlOptions' => array('class' => 'data-column column-datestamp'),
                'value' => 'ResponseExtended::getDateValue($data,"datestamp","'.$dateFormat.($this->filterDatestamp > 1 ? " H:m:i": "").'")',
                'filter' => $this->getDateFilter("datestamp",null,$this->filterDatestamp),
                'filterInputOptions' => array('class'=>'form-control input-sm filter-datestamp'),
                'footer' => ($this->showFooter && isset($aFooter['datestamp'])) ? $aFooter['datestamp'] : null,
            );
        }
        if($this->getHaveToken()) {
            $aColumns = array_merge($aColumns,$this->getTokensColumns());
        }
        $allQuestionsColumns = $surveyColumnsInformation->allQuestionsColumns();
        if(!empty($aFooter)) {
            $allQuestionsColumns = array_map(function ($questionsColumn) use ($aFooter) {
                $questionsColumn['footer'] = (isset($aFooter[$questionsColumn['name']])) ? $aFooter[$questionsColumn['name']] : null;
                return $questionsColumn;
                },
            $allQuestionsColumns);
        }
        
        $aColumns = array_merge($aColumns,$allQuestionsColumns);
        return $aColumns;
    }

    public static function getDateValue($data,$name,$dateFormat="Y-m-d") {
        if(empty($data->$name)) {
            return "";
        }
        $datetimeobj = \DateTime::createFromFormat('!Y-m-d H:i:s', $data->$name);
        if ($datetimeobj) {
            return $datetimeobj->format($dateFormat);
        }
        return $data->$name;
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
            'value' => 'empty($data->tokens) ? "" : "<div class=\'tokenattribute-value\'>".$data->tokens->firstname."</div>";',
            'htmlOptions' => array('class' => 'data-column column-token-firstname'),
            'filter' => CHtml::activeTextField($this->tokenRelated,"firstname",array('class'=>'form-control input-sm filter-token-firstname')),
        );
        $aColumns['tokens.lastname']=array(
            'header' => '<strong>[lastname]</strong><small>'.gT('Last name').'</small>',
            'name' => 'tokens.lastname',
            'value' => 'empty($data->tokens) ? "" : "<div class=\'tokenattribute-value\'>".CHtml::encode($data->tokens->lastname)."</div>";',
            'type' => 'raw',
            'htmlOptions' => array('class' => 'data-column column-token-lastname'),
            'filter' => CHtml::activeTextField($this->tokenRelated,"lastname",array('class'=>'form-control input-sm filter-token-lastname')),
        );
        $tokenAttributes = self::$survey->getTokenAttributes();
        foreach($tokenAttributes as $attribute=>$aDescrition) {
            $aColumns['tokens.'.$attribute]=array(
                'header' => '<strong>['.$attribute.']</strong><small>'.$aDescrition['description'].'</small>',
                'name' => 'tokens.'.$attribute,
                'value' => 'empty($data->tokens->'.$attribute.') ? "" : "<div class=\'tokenattribute-value\'>".$data->tokens->'.$attribute.'."</div>";',
                'type' => 'raw',
                //~ 'value' => 'empty($data->tokens) ? "" : $data->tokens->'.$attribute.';',
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

    /**
     * Set array to retricted columns
     * @param string[]
     */
    public function setRestrictedColumns($columns)
    {
        $restrictedColumns = array(
            'id',
            'submitdate',
        );
        $attributes = $this->getAttributes();
        foreach($columns as $column) {
            if(array_key_exists($column,$attributes) && !in_array($column,$restrictedColumns)) {
                $restrictedColumns[] = $column;
            }
        }
        $this->restrictedColumns = $restrictedColumns;
    }

    /**
     *
     */
    public function getFooters()
    {
        if(is_callable(array("\getQuestionInformation\helpers\surveyColumnsInformation","allQuestionsType")) ) {
            $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation(self::$sid,App()->getLanguage());
            $allQuestionsType = $surveyColumnsInformation->allQuestionsType();
        } else {
            $this->log("Footer without sum, update getQuestionInformation plugin to 1.4.0 and up",'error',"ReponseExtended.getFooters");
            $surveyColumnsInformation = null;
        }
        $aFooters = array();
        $baseCriteria = $this->search()->getCriteria();
        /* Always add submitdate */
        $cloneCriteria = clone $baseCriteria;
        $cloneCriteria->addCondition("submitdate != '' and submitdate IS NOT null");
        $aFooters['completed'] = self::model()->count($baseCriteria);
        /* All DB columns */
        $aDbColumns = self::model()->getTableSchema()->columns;
        foreach($aDbColumns as $column=>$data) {
            $footer = null;
            /* Specific one */
            if($column == 'id') {
                $aFooters['id'] = self::model()->count($baseCriteria);
                continue;
            }
            if(!empty($this->restrictedColumns) && !in_array($column,$this->restrictedColumns)) {
                continue;
            }
            $quoteColumn = Yii::app()->db->quoteColumnName($column);
            $cloneCriteria = clone $baseCriteria;
            $cloneCriteria->addCondition("$quoteColumn != '' and $quoteColumn IS NOT null");
            $footer = self::model()->count($cloneCriteria);
            if(isset($allQuestionsType[$column])) {
                if(in_array($allQuestionsType[$column],array('decimal','float','integer','number')) ) {
                    $sum = array_sum(Chtml::listData($this->search()->getData(),'id',$column));
                    $footer .= " / ".$sum;
                }
            }
            $aFooters[$column] = $footer;
        }
        return $aFooters;
    }

  /**
   * @inheritdoc adding string, by default current event
   * @param string
   * @param string \CLogger const
   * @param string $logDetail, default to global
   */
  public function log($message, $level = \CLogger::LEVEL_TRACE,$logDetail = "global")
  {
    Yii::log($message, $level,'plugins.responseListAndManage.ResponseExtended.'.$logDetail);
    Yii::log('[plugins.responseListAndManage.ResponseExtended.'.$logDetail.'] '.$message, $level,'vardump');
  }

  /**
   * get specific filter for date
   * @param string $column
   * @param integer|null $iQid
   * @param integer $filterType : only used without iQid : show time or not
   */
  public function getDateFilter($column,$iQid =null ,$filterType = 1) {
        /* Validate version for date time picker ? */
        $dateFormatMoment = $dateFormatPHP = null;
        if(is_null($iQid) && $filterType === 0) {
            return false;
        }
        if($iQid) {
            $oAttributeDateFormat = QuestionAttribute::model()->find("qid = :qid AND attribute = :attribute",array(":qid"=>$iQid,":attribute"=>'date_format'));
            if($oAttributeDateFormat && trim($oAttributeDateFormat->value)) {
                $dateFormat = trim($oAttributeDateFormat->value);
                $dateFormatMoment = getJSDateFromDateFormat($dateFormat);
                $dateFormatPHP = getPHPDateFromDateFormat($dateFormat);
            }
        }
        if(empty($dateFormatMoment)) {
            $dateFormatData = getDateFormatData(\SurveyLanguageSetting::model()->getDateFormat(self::$sid,Yii::app()->getLanguage()));
            $dateFormatMoment = $dateFormatData['jsdate'];
            $dateFormatPHP = $dateFormatData['phpdate'];
            if(is_null($iQid) && $filterType > 1) {
                $dateFormatMoment.= " HH:mm:ss";
                $dateFormatPHP.= " H:i:s";
            }
        }
        $attributes = $this->attributes;
        $minValue = !empty($attributes[$column]['min']) ? $attributes[$column]['min'] : null;
        $maxValue = !empty($attributes[$column]['max']) ? $attributes[$column]['max'] : null;
        $dateFilter = '<div class="input-group input-group-date input-group-date-min"><div class="input-group-addon">&gt;=</div>';
        $dateFilter.= CHtml::textField(get_class($this)."[".$column."]"."[min]",$minValue,array("class"=>'form-control input-sm filter-date','data-format'=>$dateFormatMoment));
        $dateFilter.= "</div>";
        $dateFilter .= '<div class="input-group input-group-date input-group-date-max"><div class="input-group-addon">&lt;</div>';
        $dateFilter.= CHtml::textField(get_class($this)."[".$column."]"."[max]",$maxValue,array("class"=>'form-control input-sm filter-date','data-format'=>$dateFormatMoment));
        $dateFilter.= "</div>";
        $dateFilter.= CHtml::hiddenField(get_class($this)."[".$column."]"."[format]",$dateFormatPHP);
        return $dateFilter;
  }

    public function getSort() {
        $sort     = new CSort;
        $sort->defaultOrder = Yii::app()->db->quoteColumnName($this->tableAlias.'.id').' ASC';
        $sort->multiSort = true;
        $sort->attributes = array();
        // Token sort
        if($this->getHaveToken()) {
            $sort->attributes = array_merge($sort->attributes,$this->getTokensSort());
        }
        $sort->attributes = array_merge($sort->attributes,
            array(
                'completed' =>
                    array(
                    'asc'=>Yii::app()->db->quoteColumnName($this->tableAlias.'.submitdate').' ASC',
                    'desc'=>Yii::app()->db->quoteColumnName($this->tableAlias.'.submitdate').' DESC',
                )
            )
        );
        /* Find numeric columns */
        if(!defined("\getQuestionInformation\helpers\surveyColumnsInformation::apiversion")) {
            $this->log("You need getQuestionInformation\helpers\surveyColumnsInformation with api version 1 at minimum for numbers_only question",'warning','getSort');
        }else {
            $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation(self::$sid,App()->getLanguage());
            $aQuestionsTypes = $surveyColumnsInformation->allQuestionsType();
            $aQuestionsAsNumber = array_filter($aQuestionsTypes, function($type) {
                return $type == 'number';
            });
            if(!empty($aQuestionsAsNumber)) {
                foreach($aQuestionsAsNumber as $column => $type) {
                    /* cast as decimal : then allow int and non int */
                    /* and casting as decimal didn't need specific db part test */
                    /* Not tested with separator as `,` since it's broken in 3.14.9 */
                    $sort->attributes = array_merge($sort->attributes,
                        array(
                            $column => array(
                                'asc'=>'CAST('.Yii::app()->db->quoteColumnName($column).' AS decimal(30,10)) ASC',
                                'desc'=>'CAST('.Yii::app()->db->quoteColumnName($column).' AS decimal(30,10)) DESC',
                            ),
                        )
                    );
                }
            }
        }
        // Finally all not set order 
        $sort->attributes = array_merge($sort->attributes,array("*"));
        return $sort;
    }

}
