<?php
/**
 * This file is part of reloadAnyResponse plugin
 * @version 0.3.1
 * @deprecated since 0.3.1
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
     * Didn't check right on edition
     * @deprecated since 0.3.1
     * @param integer $surveyId
     * @param string $token
     * @return string[]
     */
    public static function getTokensList($surveyId, $token)
    {
        return \responseListAndManage\Utilities::getTokensList($surveyId, $token, false);
    }
}
