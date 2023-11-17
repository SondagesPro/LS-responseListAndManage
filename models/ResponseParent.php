<?php

/**
 * This file is part of reloadAnyResponse plugin
 * Minimal system for parent
 * @since 2.10.0
 */
//~ namespace responseListAndManage\models;
//~ use Yii;

class ResponseParent extends LSActiveRecord
{
    /** @var int $sid */
    protected static $sid = 0;
    /** @var Survey $survey */
    protected static $survey;

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
}
