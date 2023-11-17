<?php

/**
 * This file is part of reloadAnyResponse plugin
 * Minimal system for parent
 * @since 2.10.0
 * @since 2.11.0 : parent link
 */
//~ namespace responseListAndManage\models;
//~ use Yii;

class ResponseParent extends LSActiveRecord
{
    /** @var int $sid */
    protected static $sid = 0;
    /** @var Survey $survey */
    protected static $survey;

    /* @var string|null */
    public $currentToken = "";

    /**
     * @inheritdoc
     * @return self
     */
    public static function model($sid = null)
    {
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
    public function primaryKey()
    {
        return 'id';
    }

    /** @inheritdoc */
    public function relations()
    {
        $relations = array(
            'survey' => array(self::HAS_ONE, 'Survey', array(), 'condition' => ('sid = ' . self::$sid)),
        );
        return $relations;
    }

    /**
     * @inheritdoc adding string, by default current event
     * @param string
     * @param string \CLogger const
     * @param string $logDetail, default to global
     */
    public function log($message, $level = \CLogger::LEVEL_TRACE, $logDetail = "global")
    {
        Yii::log($message, $level, 'plugins.responseListAndManage.ResponseParent.' . $logDetail);
        Yii::log('[plugins.responseListAndManage.ResponseParent.' . $logDetail . '] ' . $message, $level, 'vardump');
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
            return '</span><span class="link-parent-id">' . $this->id .'</span> <span class="fa fa-pencil text-muted" aria-hidden="true">';
        }
        return '<a class="update btn btn-link" href="' . $updateUrl . '"><span class="link-parent-id">' . $this->id .'</span> <span class="fa fa-pencil" aria-hidden="true"> </span></a>';
    }
}
