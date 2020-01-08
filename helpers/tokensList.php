<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @version 0.1.0
 */
namespace responseListAndManage\helpers;

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
        $oPluginResponseListAndManage = Plugin::model()->find("name = :name", array(":name"=>'responseListAndManage'));
        if (empty($oPluginResponseListAndManage)) {
            return $tokensList;
        }
        $tokenAttributeGroup = trim(json_decode($oPluginResponseListAndManage->value));
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
