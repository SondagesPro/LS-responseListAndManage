<?php

/**
 * This file is part of reloadAnyResponse plugin
 * @see SurveyDynamic
 * @version 2.14.5
 */
//~ namespace responseListAndManage\models;
//~ use Yii;

class ResponseExtended extends LSActiveRecord
{
    /** @var int $sid */
    protected static $sid = 0;

    /** @var Survey $survey */
    protected static $survey;

    /** @var  boolean $haveToken */
    protected $haveToken;

    /** @var  string $surveyPrefix */
    public $surveyPrefix = "";

    /** @var  string $tokenPrefix */
    public $tokenPrefix = "";

    /** @var string $htmlPrefix */
    private $htmlPrefix = "";

    /** @var  null|boolean $haveParent */
    protected $haveParent;

    /** @var  null|integer $haveParent */
    protected $parentId;

    /** @var  string $parentPrefix */
    public $parentPrefix = "Parent";

    /** @var null|string[] $relationWithParent : column of this survey related to column of parent survey */
    protected $relationWithParent;

    /** @var  object */
    protected $tokenRelated;

    /** @var  object */
    protected $parentRelated;

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

    /** @var \CDbCriteria|null filter to be applied before other criteria**/
    public $searchCriteria = array();

    /* @var */
    protected $sum;

    /* Construction link */
    /* @var string|null */
    public $currentToken = "";
    /* @var boolean */
    public $showEdit = false;
    /* @var boolean show id as link */
    public $idAsLink = false;
    /* @var boolean */
    public $showDelete = false;
    /* @var boolean : replace id of parent by a link */
    public $parentLinkUpdate = false;

    /**
     * @inheritdoc
     * @return self
     */
    public static function model($sid = null)
    {
        if (self::$sid !== $sid) {
            $survey = Survey::model()->findByPk($sid);
            if ($survey) {
                self::sid($survey->sid);
                self::$survey = $survey;
            } else {
                throw new Exception('Survey not found');
            }
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
    public function setScenario($scenario)
    {
        parent::setScenario($scenario);
        if ($scenario == 'search') {
            if ($this->getHaveToken()) {
                $oToken = TokenDynamic::model(self::$sid);
                $this->tokenRelated = $oToken;
            }
            if ($this->getHaveParent()) {
                $oParent = ResponseParent::model($this->parentId);
                $oParent->currentToken = $this->currentToken;
                $this->parentRelated = $oParent;
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
        return '{{survey_' . self::$sid . '}}';
    }

    /** @inheritdoc */
    public function relations()
    {
        $relations = array(
            'survey' => array(self::HAS_ONE, 'Survey', array(), 'condition' => ('sid = ' . self::$sid)),
        );
        if ($this->getHaveToken()) {
            TokenDynamic::sid(self::$sid);
            $relations['tokens'] = array(self::BELONGS_TO, 'TokenDynamic', array('token' => 'token'));
        }
        if ($this->getHaveParent()) {
            /* Add relation here too */
            ResponseParent::sid($this->parentId);
            // Unsure we have a BELONGS_TO relation, but keep it
            $relations['parent'] = array(self::BELONGS_TO, 'ResponseParent', $this->relationWithParent);
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
    public function getHaveToken()
    {
        if (!isset($this->haveToken)) {
            $this->haveToken = self::$survey->anonymized != "Y" && tableExists('tokens_' . self::$sid) && (Permission::model()->hasSurveyPermission(self::$sid, 'tokens', 'read') || Yii::app()->user->getState('disableTokenPermission')); // Boolean : show (or not) the token;
        }
        return $this->haveToken;
    }

    /**
     * return if parent column can be returned
     * @return bool
     */
    public function getHaveParent()
    {
        if (!isset($this->haveParent)) {
            $this->haveParent = false;
            if (
                version_compare(App()->getConfig('RelatedSurveyManagementApiVersion'), "0.11.0", ">=")
                && version_compare(App()->getConfig('getQuestionInformationAPI'), "3.1.0", ">=")
            ) {
                $RelatedSurveyManagementSettings = \RelatedSurveyManagement\Settings::getInstance();
                $parentId = $RelatedSurveyManagementSettings->getParentId(self::$sid);
                if ($parentId) {
                    $this->haveParent = true;
                    $this->parentId = $parentId;
                    $this->relationWithParent = $RelatedSurveyManagementSettings->getParentRelation(self::$sid);
                }
            }
        }
        return $this->haveParent;
    }

    public function getRelationWithParent()
    {
        if ($this->getHaveParent()) {
            return $this->relationWithParent;
        }
        return null;
    }

    /**
     * @return CActiveDataProvider
     */
    public function search()
    {
        $pageSize = Yii::app()->user->getState('responseListAndManagePageSize', Yii::app()->params['defaultPageSize']);
        $this->setScenario('search');
        if ($this->searchCriteria) {
            $criteria = $this->searchCriteria;
        } else {
            $criteria = new CDbCriteria();
        }

        // Join the survey participants table and filter tokens if needed
        if ($this->haveToken && $this->survey->anonymized != 'Y') {
            $criteria->with[] = 'tokens';
        }
        if ($this->getHaveParent()) {
            $criteria->with[] = 'parent';
        }
        $sort = $this->getSort();
        $criteria->select = $this->restrictedColumns;

        if (self::$survey->anonymized != "Y" && $this->token) {
            if (is_array($this->token)) {
                $criteria->addInCondition('t.token', $this->token);
            } else {
                $criteria->compare('t.token', $this->token, false);
            }
        }
        // Token filters
        if ($this->getHaveToken()) {
            $this->filterTokenColumns($criteria);
        }
        // Parent filters
        if ($this->getHaveParent()) {
            $this->filterParentColumns($criteria);
        }
        $this->filterColumns($criteria);
        $dataProvider = new CActiveDataProvider('ResponseExtended', array(
            'sort' => $sort,
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $pageSize,
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
        // Completed filters
        if ($this->completed == "Y") {
            $criteria->addCondition('t.submitdate IS NOT NULL');
        }
        if ($this->completed == "N") {
            $criteria->addCondition('t.submitdate IS NULL');
        }
        // Filters for responses
        foreach ($this->metaData->columns as $column) {
            $columnName = (string) $column->name;
            if ($columnName == 'token') {
                continue;
            }
            if (!empty($this->$columnName)) {
                $dbType = $column->dbType;
                $precision = $column->precision;
                $isDatetime = strpos($dbType, 'timestamp') !== false || strpos($dbType, 'datetime') !== false;
                if ($isDatetime) {
                    if (is_array($this->$columnName)) {
                        $date = $this->$columnName;
                        $dateFormat = empty($date['format']) ? 'Y-m-d' : $date['format'];
                        if (!empty($date['min'])) {
                            $minDate = DateTime::createFromFormat('!' . $dateFormat, trim($date['min']));
                            if ($minDate === false) {
                                continue;
                            }
                            $minDate = $minDate->format("Y-m-d H:i");
                            $criteria->addCondition(App()->db->quoteColumnName('t.' . $columnName) . ' >= ' . App()->db->quoteValue($minDate));
                        }
                        if (!empty($date['max'])) {
                            $maxDate = DateTime::createFromFormat('!' . $dateFormat, trim($date['max']));
                            if ($maxDate === false) {
                                continue;
                            }
                            $maxDate = $maxDate->format("Y-m-d H:i");
                            $criteria->addCondition(App()->db->quoteColumnName('t.' . $columnName) . ' < ' . App()->db->quoteValue($maxDate));
                        }
                        continue;
                    }
                    /* Else : single date : use as day */
                    $s = DateTime::createFromFormat("Y-m-d", $this->$columnName);
                    if ($s === false) {
                        // This happens when date is in wrong format
                        continue;
                    }
                    $value = $s->format('Y-m-d');
                    $criteria->addCondition('cast(' . App()->db->quoteColumnName('t.' . $columnName) . ' as date) = ' . Yii::app()->db->quoteValue($value));
                    continue;
                }
                /* Is dropdown : precison is set to 5 (choice) or 20 (language) **/
                if ($precision == 5 || $precision == 20) {
                    $criteria->compare(App()->db->quoteColumnName('t.' . $columnName), $this->$columnName, false);
                    continue;
                }
                /* Default compare */
                $criteria->compare(App()->db->quoteColumnName('t.' . $columnName), $this->$columnName, true);
            }
        }
    }

    /**
     * Add the filter to the token columns
     * @param CDbCriteria $criteria
     * @return @void
     */
    protected function filterTokenColumns(CDbCriteria $criteria)
    {
        $tokensAttributes = $this->tokenRelated->getAttributes();
        foreach ($tokensAttributes as $attribute => $value) {
            $criteria->compare('tokens.' . $attribute, $value, true);
        }
    }

    /**
     * Add the filter to the parent columns
     * @param CDbCriteria $criteria
     * @return @void
     */
    protected function filterParentColumns(CDbCriteria $criteria)
    {
        // Completed filters
        if ($this->parentRelated->completed == "Y") {
            $criteria->addCondition('parent.submitdate IS NOT NULL');
        }
        if ($this->parentRelated->completed == "N") {
            $criteria->addCondition('parent.submitdate IS NULL');
        }
        foreach ($this->parentRelated->metaData->columns as $column) {
            $columnName = (string) $column->name;
            if ($columnName == 'token') {
                continue;
            }
            if (!empty($this->parentRelated->$columnName)) {
                $dbType = $column->dbType;
                $precision = $column->precision;
                /* Is dropdown : precison is set to 5 (choice) or 20 (language) **/
                if ($precision == 5 || $precision == 20) {
                    $criteria->compare(App()->db->quoteColumnName('parent.' .  $columnName), $this->parentRelated->$columnName, false);
                    continue;
                }
                /* Default compare */
                $criteria->compare(App()->db->quoteColumnName('parent.' .  $columnName), $this->parentRelated->$columnName, true);
            }
        }
    }


    /**
     * get columns for grid
     * @return array
     */
    public function getGridColumns()
    {
        static $count = 0;
        if ($this->showFooter) {
            $aFooter = $this->getFooters();
        }
        $aColumns = $this->getSurveyColumns();
        if ($this->getHaveToken()) {
            $aColumns = array_merge($aColumns, $this->getTokensColumns());
        }
        if ($this->getHaveParent()) {
            $aColumns = array_merge($aColumns, $this->getParentColumns());
        }
        return $aColumns;
    }

    /**
     * get the update url for the current response
     * @param string|null token to be used
     */
    public function getUdateUrl($token = null)
    {
        if (empty($token)) {
            $token = $this->currentToken;
        }
        $startUrl = new \reloadAnyResponse\StartUrl(
            self::$sid,
            $token
        );
        return strval($startUrl->getUrl($this->id, array("newtest" => "Y")));
    }

    /**
     * get the update url for the current response
     * @param string|null token to be used
     */
    public function getIdButtonUrl($token = null)
    {
        $updateUrl = $this->getUdateUrl($token);
        if ($updateUrl === "") {
            return '<span class="btn btn-link disabled"><span class="link-id">' . $this->id . '</span> <span class="fa fa-pencil text-muted" aria-hidden="true"> </span></span>';
        }
        return '<a class="update btn btn-link" href="' . $updateUrl . '"><span class="link-id">' . $this->id . '</span> <span class="fa fa-pencil" aria-hidden="true"> </span></a>';
    }

    /**
     * get the delete url for the current response
     * @param string|null token to be used
     */
    public function getDeleteUrl($token = null)
    {
        if (empty($token)) {
            $token = $this->currentToken;
        }
        if ($token) {
            return App()->createUrl(
                "plugins/direct",
                array(
                    "plugin" => "responseListAndManage",
                    "sid" => self::$sid,
                    "token" => $token,
                    "delete" => $this->id
                )
            );
        }
        return App()->createUrl(
            "plugins/direct",
            array(
                "plugin" => "responseListAndManage",
                "sid" => self::$sid,
                "delete" => $this->id
            )
        );
    }

    public static function getDateValue($data, $name, $dateFormat = "Y-m-d")
    {
        if (empty($data->$name)) {
            return "";
        }
        $datetimeobj = \DateTime::createFromFormat('!Y-m-d H:i:s', $data->$name);
        if ($datetimeobj) {
            return $datetimeobj->format($dateFormat);
        }
        return $data->$name;
    }

    public static function getAnswerValue($data, $name, $type, $iQid)
    {
        return $data->$name;
    }

    public function getCompleted()
    {
        return (bool) $this->submitdate;
    }

    public static function getCompletedGrid($data)
    {
        if ($data->submitdate) {
            if (self::$survey->datestamp == "Y") {
                return "<span class='text-success fa fa-check' title='{$data->submitdate}'></span>";
            }
            return "<span class='text-success fa fa-check'></span>";
        }
        return "<span class='text-warning fa fa-times'></span>";
    }

    public function setTokenAttributes($tokens = array())
    {
        if (!$this->getHaveToken()) {
            return;
        }
        $this->tokenRelated->setAttributes($tokens, false);
    }

    public function setParentAttributes($attributes = array())
    {
        if (!$this->getHaveParent()) {
            return;
        }
        $this->parentRelated->setAttributes($attributes, false);
    }

    public function setParentAttribute($attribute, $value)
    {
        $this->parentRelated->setAttribute($attribute, $value);
    }
    /**
    * Get the survey columns for grid
    * @return [][]
    */
    public function getSurveyColumns()
    {
        $aFooter = [];
        if ($this->showFooter) {
            $aFooter = $this->getFooters();
        }
        $htmlPrefix = "";
        if (!empty($this->surveyPrefix)) {
            $this->htmlPrefix = $htmlPrefix = "<em class='responselist-prefix survey-prefix'>" . viewHelper::purified($this->surveyPrefix) . "</em> ";
        }
        $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation(self::$sid, App()->getLanguage());
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
        $aColumns['id'] = array(
            'header' => $htmlPrefix . '<strong>[id]</strong><small>' . gT('Identifier') . '</small>',
            'name' => 'id',
            'type' => 'raw',
            'filter' => CHtml::activeTextField($this, "id", array('class' => 'form-control input-sm filter-parent-id', 'size' => 6)),
            'htmlOptions' => array('class' => 'data-column column-id'),
            'footer' => ($this->showFooter && isset($aFooter['id'])) ? $aFooter['id'] : null,
        );
        if ($this->idAsLink) {
            $aColumns['id']['value'] = '$data->getIdButtonUrl("' . $this->currentToken . '");';
        }
        if ($this->showEdit || $this->showDelete) {
            $template = '';
            if ($this->showEdit) {
                $template .= '{update}';
            }
            if ($this->showDelete) {
                $template .= '{delete}';
            }
            $aColumns['button'] = array(
                'htmlOptions' => array('class' => 'data-column action-column'),
                'class' => 'bootstrap.widgets.TbButtonColumn',
                'template' => $template,
                //'buttons'=> $this->getGridButtons(),
                'updateButtonUrl' => '$data->getUdateUrl("' . $this->currentToken . '")',
                'deleteButtonUrl' => '$data->getDeleteUrl("' . $this->currentToken . '")',
                'footer' => $this->showFooter ? \responseListAndManage\Utilities::translate("Answered count and sum") : null,
            );
        }
        $aColumns['completed'] = array(
            'header' => $htmlPrefix . '<strong>[completed]</strong><small>' . gT('Completed'),
            'name' => 'completed',
            'htmlOptions' => array('class' => 'data-column column-completed'),
            'type' => 'raw',
            'value' => 'ResponseExtended::getCompletedGrid($data)',
            'filter' => array('Y' => gT('Yes'), 'N' => gT('No')),
            'filterInputOptions' => array('class' => 'form-control input-sm filter-completed'),
            'footer' => ($this->showFooter && isset($aFooter['completed'])) ? $aFooter['completed'] : null,
        );
        if (self::$survey->datestamp == "Y") {
            $allowDateFilter = method_exists($surveyColumnsInformation, 'getDateFilter');
            $dateFormatData = getDateFormatData(\SurveyLanguageSetting::model()->getDateFormat(self::$sid, Yii::app()->getLanguage()));
            $dateFormat = $dateFormatData['phpdate'];
            $aColumns['startdate'] = array(
                'header' => $htmlPrefix . '<strong>[startdate]</strong><small>' . gT('Start date') . '</small>',
                'name' => 'startdate',
                'htmlOptions' => array('class' => 'data-column column-startdate'),
                'value' => 'ResponseExtended::getDateValue($data,"startdate","' . $dateFormat . ($this->filterStartdate > 1 ? " H:m:i" : "") . '")',
                'filter' => $this->getDateFilter("startdate", null, $this->filterStartdate),
                'filterInputOptions' => array('class' => 'form-control input-sm filter-startdate'),
                'footer' => ($this->showFooter && isset($aFooter['startdate'])) ? $aFooter['startdate'] : null,
            );
            $aColumns['submitdate'] = array(
                'header' => $htmlPrefix . '<strong>[submitdate]</strong><small>' . gT('Submit date') . '</small>',
                'name' => 'submitdate',
                'htmlOptions' => array('class' => 'data-column column-submitdate'),
                'value' => 'ResponseExtended::getDateValue($data,"submitdate","' . $dateFormat . ($this->filterSubmitDate > 1 ? " H:m:i" : "") . '")',
                'filter' => $this->getDateFilter("submitdate", null, $this->filterSubmitDate),
                'filterInputOptions' => array('class' => 'form-control input-sm filter-submitdate'),
                'footer' => ($this->showFooter && isset($aFooter['submitdate'])) ? $aFooter['submitdate'] : null,
            );
            $aColumns['datestamp'] = array(
                'header' => $htmlPrefix . '<strong>[datestamp]</strong><small>' . gT('Date stamp') . '</small>',
                'name' => 'datestamp',
                'htmlOptions' => array('class' => 'data-column column-datestamp'),
                'value' => 'ResponseExtended::getDateValue($data,"datestamp","' . $dateFormat . ($this->filterDatestamp > 1 ? " H:m:i" : "") . '")',
                'filter' => $this->getDateFilter("datestamp", null, $this->filterDatestamp),
                'filterInputOptions' => array('class' => 'form-control input-sm filter-datestamp'),
                'footer' => ($this->showFooter && isset($aFooter['datestamp'])) ? $aFooter['datestamp'] : null,
            );
        }
        $allQuestionsColumns = $surveyColumnsInformation->allQuestionsColumns();
        if (!empty($htmlPrefix)) {
            $allQuestionsColumns = array_map(
                function ($questionsColumn) use ($htmlPrefix) {
                    $questionsColumn['header'] = $htmlPrefix . $questionsColumn['header'];
                    return $questionsColumn;
                },
                $allQuestionsColumns
            );
        }
        if (!empty($aFooter)) {
            $allQuestionsColumns = array_map(
                function ($questionsColumn) use ($aFooter) {
                    $questionsColumn['footer'] = (isset($aFooter[$questionsColumn['name']])) ? $aFooter[$questionsColumn['name']] : null;
                    return $questionsColumn;
                },
                $allQuestionsColumns
            );
        }
        $aColumns = array_merge($aColumns, $allQuestionsColumns);
        return $aColumns;
    }
    /**
    * Get the parent columns for grid
    * @return [][]
    */
    public function getParentColumns()
    {
        if (!$this->getHaveParent()) {
            return [];
        }

        $htmlParentPrefix = "";
        if (!empty($this->parentPrefix)) {
            $htmlParentPrefix = "<em class='responselist-prefix parent-prefix'>" . viewHelper::purified($this->parentPrefix) . "</em> ";
        }
        $aColumns = array();
        $aColumns['parent.id'] = array(
            'header' => $htmlParentPrefix . '<strong>[id]</strong> <small>' . gT('Identifier') . '</small>',
            'name' => 'parent.id',
            'type' => 'raw',
            'value' => 'empty($data->parent) ? "" : $data->parent->id;',
            'htmlOptions' => array('class' => 'data-column column-parent-id'),
            'filter' => CHtml::activeTextField($this->parentRelated, "id", array('class' => 'form-control input-sm filter-parent-id', 'size' => 6)),
        );
        $aColumns['parent.completed'] = array(
            'header' => $htmlParentPrefix . '<strong>[completed]</strong><small>' . gT('Completed') . '</small>',
            'name' => 'parent.completed',
            'htmlOptions' => array('class' => 'data-column column-parent-completed'),
            'type' => 'raw',
            'value' => 'empty($data->parent) ? "" : $data->parent->getCompletedGrid();',
            'filter' => CHtml::activeDropDownList(
                $this->parentRelated,
                'completed',
                ['Y' => gT('Yes'), 'N' => gT('No')],
                ['class' => 'form-control input-sm filter-completed', 'empty' => '']
            )
        );
        if ($this->parentLinkUpdate) {
            $aColumns['parent.id']['value'] = 'empty($data->parent) ? "" : $data->parent->getIdButtonUrl("' . $this->currentToken . '");';
        }
        $surveyParentColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation($this->parentId, App()->getLanguage());
        $surveyParentColumnsInformation->relation = 'parent';
        $surveyParentColumnsInformation->relatedObjectName = 'parentRelated';
        $surveyParentColumnsInformation->relatedDescrition = gT('Parent'); // @todo : allow set by user
        $allParentQuestionsColumns = $surveyParentColumnsInformation->allQuestionsColumns();
        $allFixedParentQuestionsColumns = [];
        foreach ($allParentQuestionsColumns as $column => $data) {
            /* @todo name it in plugin settings */
            $name = $data['name'];
            $data['header'] = $htmlParentPrefix . $data['header'];
            $data['name'] = 'parent.' .  $data['name'];
            $filterInputOptions = $data['filterInputOptions'];
            $filterInputOptions['class'] = $filterInputOptions['class'] . " filter-parent";
            if (isset($data['filter']) && is_array($data['filter'])) {
                $filterInputOptions['empty'] = "";
                $data['filter'] = CHtml::activeDropDownList(
                    $this->parentRelated,
                    $name,
                    $data['filter'],
                    $filterInputOptions
                );
            } else {
                $data['filter'] = CHtml::activeTextField($this->parentRelated, $name, $filterInputOptions);
            }
            $allFixedParentQuestionsColumns['parent.' . $column] = $data;
        }
        $aColumns = array_merge($aColumns, $allFixedParentQuestionsColumns);
        return $aColumns;
    }

    /**
    * Get the token columns for grid
    * @return [][]
    */
    public function getTokensColumns()
    {
        $tokenPrefix = "";
        if (!empty($this->tokenPrefix)) {
            $tokenPrefix = "<em class='responselist-prefix token-prefix'>" . viewHelper::purified($this->tokenPrefix) . "</em> ";
        }
        $aColumns = array();
        $aColumns['tokens.token'] = array(
            'header' => $tokenPrefix . '<strong>[token]</strong><small>' . gT('Token') . '</small>',
            'name' => 'tokens.token',
            'value' => 'empty($data->tokens) ? "" : $data->tokens->token;',
            'htmlOptions' => array('class' => 'data-column column-token-token'),
            'filter' => CHtml::activeTextField($this->tokenRelated, "token", array('class' => 'form-control input-sm filter-token-token')),
        );
        $aColumns['tokens.email'] = array(
            'header' => $tokenPrefix . '<strong>[email]</strong><small>' . gT('Email') . '</small>',
            'name' => 'tokens.email',
            'value' => 'empty($data->tokens) ? "" : $data->tokens->email;',
            'htmlOptions' => array('class' => 'data-column column-token-email'),
            'filter' => CHtml::activeTextField($this->tokenRelated, "email", array('class' => 'form-control input-sm filter-token-email')),
        );
        $aColumns['tokens.firstname'] = array(
            'header' => $tokenPrefix . '<strong>[firstname]</strong><small>' . gT('First name') . '</small>',
            'name' => 'tokens.firstname',
            'type' => 'raw',
            'value' => 'empty($data->tokens) ? "" : "<div class=\'tokenattribute-value\'>".$data->tokens->firstname."</div>";',
            'htmlOptions' => array('class' => 'data-column column-token-firstname'),
            'filter' => CHtml::activeTextField($this->tokenRelated, "firstname", array('class' => 'form-control input-sm filter-token-firstname')),
        );
        $aColumns['tokens.lastname'] = array(
            'header' => $tokenPrefix . '<strong>[lastname]</strong><small>' . gT('Last name') . '</small>',
            'name' => 'tokens.lastname',
            'value' => 'empty($data->tokens) ? "" : "<div class=\'tokenattribute-value\'>".CHtml::encode($data->tokens->lastname)."</div>";',
            'type' => 'raw',
            'htmlOptions' => array('class' => 'data-column column-token-lastname'),
            'filter' => CHtml::activeTextField($this->tokenRelated, "lastname", array('class' => 'form-control input-sm filter-token-lastname')),
        );
        $tokenAttributes = self::$survey->getTokenAttributes();
        foreach ($tokenAttributes as $attribute => $aDescrition) {
            $aColumns['tokens.' . $attribute] = array(
                'header' => $tokenPrefix . '<strong>[' . $attribute . ']</strong><small>' . $aDescrition['description'] . '</small>',
                'name' => 'tokens.' . $attribute,
                'value' => 'empty($data->tokens->' . $attribute . ') ? "" : "<div class=\'tokenattribute-value\'>".$data->tokens->' . $attribute . '."</div>";',
                'type' => 'raw',
                //~ 'value' => 'empty($data->tokens) ? "" : $data->tokens->'.$attribute.';',
                'htmlOptions' => array('class' => 'data-column column-token-attribute'),
                'filter' => CHtml::activeTextField($this->tokenRelated, $attribute, array('class' => 'form-control input-sm filter-token-attribute')),
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
        foreach ($tokenAttributes as $attribute => $aDescrition) {
            $aSort[] = 'tokens.' . $attribute;
        }
        return $aSort;
    }

    public function getParentSort()
    {
        if (!$this->getHaveParent()) {
            return array();
        }
        $parentAttributes = $this->parentRelated->getAttributes();
        $aSort = [];
        foreach ($parentAttributes as $columns => $value) {
            $aSort[] = 'parent.' . $columns;
        }
        $aSort = array_merge(
            $aSort,
            array(
                'parent.completed' =>
                array(
                    'asc' => Yii::app()->db->quoteColumnName('parent.submitdate') . ' ASC',
                    'desc' => Yii::app()->db->quoteColumnName('parent.submitdate') . ' DESC',
                )
            )
        );
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
        $ismssql = in_array(App()->db->driverName, ['sqlsrv', 'dblib', 'mssql']);
        foreach ($columns as $column) {
            if (array_key_exists($column, $attributes) && !in_array($column, $restrictedColumns)) {
                if ($ismssql) {
                    $restrictedColumns[] = $column;
                } else {
                    /* broke with mssql , needed for pgsql, needed for dual scale with mariadb */
                    $restrictedColumns[] = App()->db->quoteColumnName($column);
                }
            }
        }
        $this->restrictedColumns = $restrictedColumns;
    }

    /**
     *
     */
    public function getFooters()
    {
        if (is_callable(array("\getQuestionInformation\helpers\surveyColumnsInformation", "allQuestionsType"))) {
            $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation(self::$sid, App()->getLanguage());
            $allQuestionsType = $surveyColumnsInformation->allQuestionsType();
        } else {
            $this->log("Footer without sum, update getQuestionInformation plugin to 1.4.0 and up", 'error', "ReponseExtended.getFooters");
            $surveyColumnsInformation = null;
        }
        $aFooters = array();
        $baseCriteria = $this->search()->getCriteria();
        /* Always add submitdate */
        $cloneCriteria = clone $baseCriteria;
        $cloneCriteria->addCondition("submitdate != '' and submitdate IS NOT null");
        $aFooters['completed'] = self::model(self::$sid)->count($baseCriteria);
        /* All DB columns */
        $aDbColumns = self::model(self::$sid)->getTableSchema()->columns;
        foreach ($aDbColumns as $column => $data) {
            $footer = null;
            /* Specific one */
            if ($column == 'id') {
                $aFooters['id'] = self::model(self::$sid)->count($baseCriteria);
                continue;
            }
            $quoteColumn = Yii::app()->db->quoteColumnName($column);
            if (!empty($this->restrictedColumns) && (!in_array($column, $this->restrictedColumns) && !in_array($quoteColumn, $this->restrictedColumns))) {
                continue;
            }
            $cloneCriteria = clone $baseCriteria;
            $cloneCriteria->addCondition("$quoteColumn != '' and $quoteColumn IS NOT null");
            $footer = self::model(self::$sid)->count($cloneCriteria);
            /* broken : $this->search()->getData() take page, not only filter */
            if (isset($allQuestionsType[$column])) {
                if (in_array($allQuestionsType[$column], array('decimal', 'float', 'integer', 'number'))) {
                    $countCriteria = $this->search()->getCountCriteria();
                    $countCriteria->select = ['id', $column];
                    $sum = array_sum(Chtml::listData(self::model(self::$sid)->findAll($countCriteria), 'id', $column));
                    $footer .= " / " . $sum;
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
    public function log($message, $level = \CLogger::LEVEL_TRACE, $logDetail = "global")
    {
        Yii::log($message, $level, 'plugins.responseListAndManage.ResponseExtended.' . $logDetail);
        Yii::log('[plugins.responseListAndManage.ResponseExtended.' . $logDetail . '] ' . $message, $level, 'vardump');
    }

    /**
     * get specific filter for date
     * @param string $column
     * @param integer|null $iQid
     * @param integer $filterType : only used without iQid : show time or not
     */
    public function getDateFilter($column, $iQid = null, $filterType = 1)
    {
        /* Validate version for date time picker ? */
        $dateFormatMoment = $dateFormatPHP = null;
        if (is_null($iQid) && $filterType === 0) {
            return false;
        }
        if ($iQid) {
            $oAttributeDateFormat = QuestionAttribute::model()->find("qid = :qid AND attribute = :attribute", array(":qid" => $iQid, ":attribute" => 'date_format'));
            if ($oAttributeDateFormat && trim($oAttributeDateFormat->value)) {
                $dateFormat = trim($oAttributeDateFormat->value);
                $dateFormatMoment = getJSDateFromDateFormat($dateFormat);
                $dateFormatPHP = getPHPDateFromDateFormat($dateFormat);
            }
        }
        if (empty($dateFormatMoment)) {
            $dateFormatData = getDateFormatData(\SurveyLanguageSetting::model()->getDateFormat(self::$sid, Yii::app()->getLanguage()));
            $dateFormatMoment = $dateFormatData['jsdate'];
            $dateFormatPHP = $dateFormatData['phpdate'];
            if (is_null($iQid) && $filterType > 1) {
                $dateFormatMoment .= " HH:mm:ss";
                $dateFormatPHP .= " H:i:s";
            }
        }
        $attributes = $this->attributes;
        $minValue = !empty($attributes[$column]['min']) ? $attributes[$column]['min'] : null;
        $maxValue = !empty($attributes[$column]['max']) ? $attributes[$column]['max'] : null;
        $dateFilter = '<div class="input-group input-group-date input-group-date-min"><div class="input-group-addon input-sm">&gt;=</div>';
        $dateFilter .= CHtml::textField(get_class($this) . "[" . $column . "]" . "[min]", $minValue, array("class" => 'form-control input-sm filter-date', 'data-format' => $dateFormatMoment));
        $dateFilter .= "</div>";
        $dateFilter .= '<div class="input-group input-group-date input-group-date-max"><div class="input-group-addon input-sm">&lt;</div>';
        $dateFilter .= CHtml::textField(get_class($this) . "[" . $column . "]" . "[max]", $maxValue, array("class" => 'form-control input-sm filter-date', 'data-format' => $dateFormatMoment));
        $dateFilter .= "</div>";
        $dateFilter .= CHtml::hiddenField(get_class($this) . "[" . $column . "]" . "[format]", $dateFormatPHP);
        return $dateFilter;
    }

    public function getSort()
    {
        $sort = new CSort();
        $sort->defaultOrder = Yii::app()->db->quoteColumnName($this->tableAlias . '.id') . ' ASC';
        $sort->multiSort = true;
        $sort->attributes = array();
        // Token sort
        if ($this->getHaveToken()) {
            $sort->attributes = array_merge($sort->attributes, $this->getTokensSort());
        }
        if ($this->getHaveParent()) {
            $sort->attributes = array_merge($sort->attributes, $this->getParentSort());
        }
        $sort->attributes = array_merge(
            $sort->attributes,
            array(
                'completed' =>
                array(
                    'asc' => Yii::app()->db->quoteColumnName($this->tableAlias . '.submitdate') . ' ASC',
                    'desc' => Yii::app()->db->quoteColumnName($this->tableAlias . '.submitdate') . ' DESC',
                )
            )
        );
        /* Find numeric columns */
        if (!defined("\getQuestionInformation\helpers\surveyColumnsInformation::apiversion")) {
            $this->log("You need getQuestionInformation\helpers\surveyColumnsInformation with api version 1 at minimum for numbers_only question", 'warning', 'getSort');
        } else {
            $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation(self::$sid, App()->getLanguage());
            $aQuestionsTypes = $surveyColumnsInformation->allQuestionsType();
            $aQuestionsAsNumber = array_filter($aQuestionsTypes, function ($type) {
                return $type == 'number';
            });
            if (!empty($aQuestionsAsNumber)) {
                foreach ($aQuestionsAsNumber as $column => $type) {
                    /* cast as decimal : then allow int and non int */
                    /* and casting as decimal didn't need specific db part test */
                    /* Not tested with separator as `,` since it's broken in 3.14.9 */
                    $sort->attributes = array_merge(
                        $sort->attributes,
                        array(
                            $column => array(
                                'asc' => 'CAST(' . Yii::app()->db->quoteColumnName($column) . ' AS decimal(30,10)) ASC',
                                'desc' => 'CAST(' . Yii::app()->db->quoteColumnName($column) . ' AS decimal(30,10)) DESC',
                            ),
                        )
                    );
                }
            }
        }
        // Finally all not set order
        $sort->attributes = array_merge($sort->attributes, array("*"));
        return $sort;
    }
}
