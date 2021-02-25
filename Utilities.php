<?php
/**
 * Some Utilities
 * 
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2020-2021 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
namespace responseListAndManage;

use App;
use Yii;
use Token;

class Utilities
{
    /* var string[] settings with global part and default value */
    const DefaultSettings = array(
        'template' => '',
        'showLogOut'=> false,
        'showAdminLink' => 1,
        'afterSaveAll' => 'js',
        'forceDownloadImage' => 1,
    );

    /**
     * Translate a string
     * @param string $string The message that are being translated
     * @param string $language
     * @return string
     */
    public static function translate($string, $language = null)
    {
        return Yii::t('', $string, array(), 'ResponseListAndManageMessages', $language);
    }

    /**
     * Return the list of token related
     * @param integer $surveyId
     * @param string $token
     * @param boolean $checkRight on this settings for this survey
     * @return integer[]
     */
    public static function getTokensList($surveyId, $token, $checkRight = true)
    {
        if(!Yii::getPathOfAlias('reloadAnyResponse')) {
            return null;
        }
        if (!class_exists('\reloadAnyResponse\Utilities')) {
            return null;
        }
        if ($checkRight && !\reloadAnyResponse\Utilities::getReloadAnyResponseSetting($surveyId, 'allowTokenUser')) {
            return null;
        }
        $tokensList = array(
            $token => $token
        );
        if ($checkRight && !\reloadAnyResponse\Utilities::getReloadAnyResponseSetting($surveyId, 'allowTokenGroupUser')) {
            return $tokensList;
        }
        $tokenAttributeGroup = self::getSetting($surveyId, 'tokenAttributeGroup');
        if (empty($tokenAttributeGroup)) {
            return $tokensList;
        }
        $oToken = Token::model($surveyId)->find("token = :token", array(":token"=>$token));
        $tokenGroup = (isset($oToken->$tokenAttributeGroup) && trim($oToken->$tokenAttributeGroup) != '') ? $oToken->$tokenAttributeGroup : null;
        if (empty($tokenGroup)) {
            return $tokensList;
        }
        $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup."= :group", array(":group"=>$tokenGroup));
        return \CHtml::listData($oTokenGroup, 'token', 'token');
    }

    /**
     * Get a DB setting from a plugin
     * @param integer survey id
     * @param string setting name
     * @return mixed
     */
    public static function getSetting($surveyId, $sSetting) {
        $oPlugin = \Plugin::model()->find(
            "name = :name",
            array(":name" => 'responseListAndManage')
        );
        if(!$oPlugin || !$oPlugin->active) {
            return null;
        }
        $oSetting = \PluginSetting::model()->find(
            'plugin_id = :pluginid AND '.App()->getDb()->quoteColumnName('key').' = :key AND model = :model AND model_id = :surveyid',
            array(
                ':pluginid' => $oPlugin->id,
                ':key' => $sSetting,
                ':model' => 'Survey',
                ':surveyid' => $surveyId,
            )
        );
        if(!empty($oSetting)) {
            $value = json_decode($oSetting->value);
            if($value !== '') {
                return $value;
            }
        }
        if(!array_key_exists($sSetting, self::DefaultSettings)) {
            return null;
        }
        $oSetting = \PluginSetting::model()->find(
            'plugin_id = :pluginid AND '.App()->getDb()->quoteColumnName('key').' = :key AND model = :model AND model_id = :surveyid',
            array(
                ':pluginid' => $oPlugin->id,
                ':key' => $sSetting,
                ':model' => null,
                ':surveyid' => null,
            )
        );
        if(!empty($oSetting)) {
            $value = json_decode($oSetting->value);
            if($value !== '') {
                return $value;
            }
        }
        return self::DefaulSettings[$sSetting];
    }

}
