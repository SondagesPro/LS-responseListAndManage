<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @version 0.3.0
 */
namespace responseListAndManage\helpers;
use Yii;
use Survey;
use Plugin;
use PluginSetting;
use Token;
use CHtml;

class tokensList
{
    /**
     * Return the list of token related by responseListAndManage
     * @param integer $surveyId
     * @param string $token
     * @return string[]
     */
    public static function getTokensList($surveyId, $token)
    {
        $tokensList = array($token=>$token);
        if (!Survey::model()->findByPk($surveyId)->hasTokensTable) {
            return $tokensList;
        }
        $oPluginResponseListAndManage = Plugin::model()->find("name = :name",array(":name"=>'responseListAndManage'));
        if(empty($oPluginResponseListAndManage) || !$oPluginResponseListAndManage->active) {
            return $tokensList;
        }
        if(App()->getConfig('TokenUsersListAndManageAPI')) {
            $tokenAttributeGroup = \TokenUsersListAndManagePlugin\Utilities::getTokenAttributeGroup($surveyId);
        } else {
            $oTokenAttributeGroup = PluginSetting::model()->find(
                "plugin_id = :plugin_id AND model = :model AND model_id = :model_id AND ".Yii::app()->db->quoteColumnName('key')." = :setting",
                array(":plugin_id"=>$oPluginResponseListAndManage->id,':model'=>"Survey",':model_id'=>$surveyId,':setting'=>"tokenAttributeGroup")
            );
            if(empty($oTokenAttributeGroup)) {
                return $tokensList;
            }
            $tokenAttributeGroup = trim(json_decode($oTokenAttributeGroup->value));
        }
        if (empty($tokenAttributeGroup)) {
            return $tokensList;
        }
        if (!is_string($tokenAttributeGroup)) {
            return $tokensList;
        }
        $oTokenGroup = Token::model($surveyId)->find("token = :token", array(":token"=>$token));
        $tokenGroup = null;
        if (isset($oTokenGroup->$tokenAttributeGroup)) {
            $tokenGroup = trim($oTokenGroup->$tokenAttributeGroup);
        }
        if (empty($tokenGroup)) {
            return $tokensList;
        }
        $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup."= :group", array(":group"=>$tokenGroup));
        return CHtml::listData($oTokenGroup, 'token', 'token');
    }
}
