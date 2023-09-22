<?php

/**
 * Responses List And Manage
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018-2022 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 2.9.6
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

class responseListAndManage extends PluginBase
{
    protected $storage = 'DbStorage';
    protected static $description = 'A different way to manage response for a survey';
    protected static $name = 'responseListAndManage';

    /* @var integer|null surveyId get track of current sureyId between action */
    private $surveyId;

    /* @var integer : the version of settings whan saved */
    const SettingsVersion = 2;

    /**
     * @var array[] the settings
     */
    protected $settings = array(
        'information' => array(
            'type' => 'info',
            'content' => 'Access link is …',
        ),
        'template' => array(
            'type' => 'info',
            'default' => '',
            'label' => 'Template to be used',
        ),
        'showLogOut' => array(
            'type' => 'boolean',
            'default' => false,
            'label' => 'Show log out',
        ),
        'showAdminLink' => array(
            'type' => 'boolean',
            'default' => 1,
            'label' => 'Show administration link',
        ),
        'afterSaveAll' => array(
            'type' => 'select',
            'label' => 'Action to do after save',
            'options' => array(
                'replace' => 'Replace totally the content and close dialog box, this can disable other plugin system.',
                'js' => 'Only close dialog box',
                'none' => 'Return to survey',
            ),
            'default' => 'js',
        ),
        'forceDownloadImage' => array(
            'type' => 'boolean',
            'default' => 1,
            'label' => 'Force download of image',
            'help' => 'When click on an image (png,jpg,gif or pdf) : image is directly open in browser (if able). You need a javascript solution if you want to open it in new tab',
        ),
    );

    protected $mailError;
    protected $mailDebug;
    //public $iSurveyId;

    /**
     * var array aRenderData
     */
    private $aRenderData = array();

    public function init()
    {
        Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
        App()->setConfig('responseListAndManageAPI', \responseListAndManage\Utilities::API);
        $this->subscribe('afterPluginLoad');

        /* Primary system: show the list of surey or response */
        $this->subscribe('newDirectRequest');

        $this->subscribe('beforeSurveySettings');
        $this->subscribe('beforeToolsMenuRender');

        /* Need some event in iframe survey */
        $this->subscribe('beforeSurveyPage');

        /* Register when need close */
        $this->subscribe('afterSurveyComplete');
    }

    /**
     * Check if we can use this plugin
     * @return boolean
     */
    private function getIsUsable()
    {
        return version_compare(Yii::app()->getConfig('versionnumber'), "3.10", ">=")
            && (defined('\reloadAnyResponse\Utilities::API') && \reloadAnyResponse\Utilities::API >= 3.2)
            && Yii::getPathOfAlias('getQuestionInformation');
    }

    /**
     * Add an error for admin user if activated but can not used
     * Subscribed in afterPluginLoad
     */
    public function beforeControllerAction()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $aFlashMessage = App()->session['aFlashMessage'];
        if (!empty($aFlashMessage['responseListAndManage'])) {
            return;
        }

        if (\responseListAndManage\Utilities::getCurrentUserId()) {
            $controller = $this->getEvent()->get('controller');
            $action = $this->getEvent()->get('action');
            $subaction = $this->getEvent()->get('subaction');
            if ($controller == 'admin' && $action != "pluginmanager") {
                $aFlashMessage['responseListAndManage'] = array(
                    'message' => sprintf(
                        $this->translate("%s can not be used due to a lack of functionnality on your instance"),
                        CHtml::link(
                            'responseListAndManage',
                            array( "admin/pluginmanager/sa/configure",
                            'id' => $this->id
                            )
                        )
                    ),
                    'type' => 'danger'
                );
                App()->session['aFlashMessage'] = $aFlashMessage;
            }
        }
        return;
    }

    /**
     * Checkj if can be activated , show a message if not
     * @return boolean
     */
    public function beforeActivate()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        if (!$this->getIsUsable()) {
            $this->getEvent()->set(
                'success',
                false
            );
            $this->getEvent()->set(
                'message',
                sprintf($this->translate("%s can not be used due to a lack of functionnality on your instance. Please check setting to have the list of errors."), 'responseListAndManage')
            );
        }
    }

    /**
     * Get the error string for availability
     * @todo : replace by installed in 4.X ?
     * @return null[string[]
     */
    private function getErrorsUnUsable()
    {
        $errors = array();
        if (version_compare(Yii::app()->getConfig('versionnumber'), "3.10", "<")) {
            $errors[] = sprintf($this->translate("%s plugin need LimeSurvey version 3.10 and up"), 'responseListAndManage');
        }
        if (!Yii::getPathOfAlias('reloadAnyResponse')) {
            $errors[] = sprintf($this->translate("%s plugin need %s version 3.2 and up"), 'responseListAndManage', 'reloadAnyResponse');
        } elseif (!defined('\reloadAnyResponse\Utilities::API')) {
            $errors[] = sprintf($this->translate("%s plugin need %s version 3.2 and up"), 'responseListAndManage', 'reloadAnyResponse');
        } elseif (\reloadAnyResponse\Utilities::API < 3.2) {
            $errors[] = sprintf($this->translate("%s plugin need %s version 3.2 and up"), 'responseListAndManage', 'reloadAnyResponse');
        }
        if (!Yii::getPathOfAlias('getQuestionInformation')) {
            $errors[] = sprintf($this->translate("%s plugin need %s"), 'responseListAndManage', 'getQuestionInformation');
        }
        return $errors;
    }

    /**
     * @see event
     */
    public function beforeSurveyPage()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $surveyId = $this->event->get('surveyId');
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (empty($oSurvey)) {
            return;
        }
        if (empty(Yii::app()->session['responseListAndManage'][$surveyId])) {
            return;
        }
        $this->surveyId = $surveyId;
        App()->getClientScript()->registerScriptFile(
            Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/surveymanaging/surveymanaging.js'),
            CClientScript::POS_BEGIN
        );
        if (App()->getrequest()->getPost('saveall') && App()->getrequest()->getPost('autosaveandquit')) {
            $script = "responseListAndManage.autoclose();";
            Yii::app()->getClientScript()->registerScript("responseListAndManageSaveAllQuit", $script, CClientScript::POS_END);
            return;
        }

        if (App()->getrequest()->getPost('saveall') && $oSurvey->allowsave == "Y") {
            $isSaveandQuit = false;
            if (Yii::getPathOfAlias('autoSaveAndQuit')) {
                $isSaveandQuit = \autoSaveAndQuit\Utilities::isSaveAndQuit($surveyId) && !\autoSaveAndQuit\Utilities::isDisableSaveAndQuit($surveyId);
            }
            /* Quit if is save and quit except for automatic one */
            if ($isSaveandQuit && !App()->getRequest()->getPost("saveandquit-autosave")) {
                $script = "responseListAndManage.autoclose();";
                Yii::app()->getClientScript()->registerScript("responseListAndManageSaveAll", $script, CClientScript::POS_END);
            }
            return;
        }
        if (App()->getrequest()->getPost('clearall') == "clearall" && App()->getrequest()->getPost('confirm-clearall')) {
            $script = "responseListAndManage.autoclose();";
            Yii::app()->getClientScript()->registerScript("responseListAndManageClearAll", $script, CClientScript::POS_END);
        }
    }

    /**
     * Add the script after survey is completed
     * Add the content information
     */
    public function afterSurveyComplete()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $afterSurveyCompleteEvent = $this->getEvent(); // because update in twig renderPartial
        $surveyId = $afterSurveyCompleteEvent->get('surveyId');
        if (empty(Yii::app()->session['responseListAndManage'][$surveyId])) {
            return;
        }
        if (empty($afterSurveyCompleteEvent->get('responseId'))) {
            return;
        }
        /* Todo : check if surey are open by this plugin */
        $script = "responseListAndManage.autoclose();";
        Yii::app()->getClientScript()->registerScript("responseListAndManageComplete", $script, CClientScript::POS_END);
        $renderData = array(
            'language' => array(
                "Your responses was saved as complete, you can close this windows." => $this->translate("Your responses was saved as complete, you can close this windows."),
            ),
            'aSurveyInfo' => getSurveyInfo($surveyId, App()->getLanguage()),
        );
        $this->subscribe('getPluginTwigPath');
        $extraContent = Yii::app()->twigRenderer->renderPartial('/subviews/messages/responseListAndManage_submitted.twig', $renderData);
        if ($extraContent) {
            $afterSurveyCompleteEvent->getContent($this)
                ->addContent($extraContent);
        }
    }

    /**
     * Add some views for this and other plugin
     */
    public function getPluginTwigPath()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $viewPath = dirname(__FILE__) . "/twig";
        $this->getEvent()->append('add', array($viewPath));
    }

    /** @inheritdoc **/
    public function beforeSurveySettings()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        /* @Todo move this to own page */
        $oEvent = $this->getEvent();
        $iSurveyId = $this->getEvent()->get('survey');
        $accesUrl = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid' => $iSurveyId));
        $managementUrl = Yii::app()->createUrl(
            'admin/pluginhelper',
            array(
                'sa' => 'sidebody',
                'plugin' => get_class($this),
                'method' => 'actionSettings',
                'surveyId' => $iSurveyId
            )
        );

        $this->getEvent()->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'link' => array(
                    'type' => 'info',
                    'content' => CHtml::link($this->translate("Access to responses listing"), $accesUrl, array("target" => '_blank','class' => 'btn btn-block btn-default btn-lg')),
                ),
                'linkManagement' => array(
                    'type' => 'info',
                    'content' => CHtml::link($this->translate("Manage settings of responses listing"), $managementUrl, array("target" => '_blank','class' => 'btn btn-block btn-default btn-lg')),
                ),
            )
        ));
    }

    /**
     * see beforeToolsMenuRender event
     *
     * @return void
     */
    public function beforeToolsMenuRender()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'update')) {
            $aMenuItem = array(
                'label' => $this->translate('Response listing settings'),
                'iconClass' => 'fa fa-list-alt ',
                'href' => Yii::app()->createUrl(
                    'admin/pluginhelper',
                    array(
                        'sa' => 'sidebody',
                        'plugin' => get_class($this),
                        'method' => 'actionSettings',
                        'surveyId' => $surveyId
                    )
                ),
            );
            if (class_exists("\LimeSurvey\Menu\MenuItem")) {
                $menuItem = new \LimeSurvey\Menu\MenuItem($aMenuItem);
            } else {
                $menuItem = new \ls\menu\MenuItem($aMenuItem);
            }
            $event->append('menuItems', array($menuItem));
        }
        if ($oSurvey->active == "Y" && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read') && $this->get('allowAccess', 'Survey', $surveyId, 'all')) {
            $aMenuItem = array(
                'label' => $this->translate('Response listing'),
                'iconClass' => 'fa fa-list-alt ',
                'href' => Yii::app()->createUrl(
                    'plugins/direct',
                    array(
                        'plugin' => get_class($this),
                        'sid' => $surveyId
                    )
                ),
            );
            if (class_exists("\LimeSurvey\Menu\MenuItem")) {
                $menuItem = new \LimeSurvey\Menu\MenuItem($aMenuItem);
            } else {
                $menuItem = new \ls\menu\MenuItem($aMenuItem);
            }

            $event->append('menuItems', array($menuItem));
        }
    }

    /**
     * Main function to replace surveySetting
     * @param int $surveyId Survey id
     *
     * @return string
     */
    public function actionSettings($surveyid)
    {
        $surveyId = $surveyid;
        $oSurvey = Survey::model()->findByPk($surveyId);
        /* @var float API of TokenUsersListAndManagePlugin */
        $TokenUsersListAndManagePluginApi = 0;
        if (defined('\TokenUsersListAndManagePlugin\Utilities::API')) {
            $TokenUsersListAndManagePluginApi = \TokenUsersListAndManagePlugin\Utilities::API;
        }
        /* @var boolean : mange here TokenAttributeGroup */
        $manageTokenAttributeGroup = version_compare($TokenUsersListAndManagePluginApi, "0.5", "<");
        if (!$oSurvey) {
            throw new CHttpException(404, gT("This survey does not seem to exist."));
        }
        if (!Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'read')) {
            throw new CHttpException(403);
        }

        $this->checkAndFixVersion($surveyId);
        if (App()->getRequest()->getPost('save' . get_class($this))) {
            if (!Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'update')) {
                throw new CHttpException(403);
            }
            // Adding save part
            $settings = array(
                'showId','showCompleted','showSubmitdate','showStartdate','showDatestamp',
                'tokenAttributes','surveyAttributes','surveyAttributesPrimary',
                'tokenColumnOrder','tokenAttributesNone',
                'surveyNeededValues',
                'tokenAttributesHideToUser','surveyAttributesHideToUser',
                'allowAccess','allowSee','allowEdit','allowDelete', 'allowAdd','allowAddSelf','allowAddUser',
                'template',
                'showFooter',
                'filterOnDate','filterSubmitdate','filterStartdate','filterDatestamp',
                'showLogOut','showSurveyAdminpageLink',
                'showExportLink','exportType','exportHeadexports','exportAnswers',
                'afterSaveAll','saveAllAsDraft',
            );
            foreach ($settings as $setting) {
                $this->set($setting, App()->getRequest()->getPost($setting), 'Survey', $surveyId);
            }
            if ($manageTokenAttributeGroup) {
                $this->set('tokenAttributeGroup', App()->getRequest()->getPost('tokenAttributeGroup'), 'Survey', $surveyId);
                $this->set('tokenAttributeGroupManager', App()->getRequest()->getPost('tokenAttributeGroupManager'), 'Survey', $surveyId);
            }
            $languageSettings = array('description');
            foreach ($languageSettings as $setting) {
                $finalSettings = array();
                foreach ($oSurvey->getAllLanguages() as $language) {
                    $finalSettings[$language] = App()->getRequest()->getPost($setting . '_' . $language);
                }
                $this->set($setting, $finalSettings, 'Survey', $surveyId);
            }
            /* Set the version of current settings */
            $this->set('SettingsVersion', self::SettingsVersion, 'Survey', $surveyId);
            if (App()->getRequest()->getPost('save' . get_class($this)) == 'redirect') {
                $redirectUrl = Yii::app()->createUrl('surveyAdministration/view', array('surveyid' => $surveyId));
                if (intval(App()->getConfig('versionnumber')) < 4) {
                    $redirectUrl = Yii::app()->createUrl('admin/survey', array('sa' => 'view', 'surveyid' => $surveyId));
                }
                Yii::app()->getController()->redirect($redirectUrl);
            }
        }
        $stateInfo = "<ul class='list'>";
        if ($this->_allowTokenLink($oSurvey)) {
            $stateInfo .= CHtml::tag("li", array("class" => 'text-success'), $this->translate("Token link and creation work in managing."));
            if ($this->_allowMultipleResponse($oSurvey)) {
                $stateInfo .= CHtml::tag("li", array("class" => 'text-success'), $this->translate("You can create new response for all token."));
            } else {
                $stateInfo .= CHtml::tag("li", array("class" => 'text-warning'), $this->translate("You can not create new response for all token, this part was unsure due to specific survey settings."));
            }
        } else {
            $stateInfo .= CHtml::tag("li", array("class" => 'text-warning'), $this->translate("No Token link and creation can be done in managing. Survey is anonymous or token table didn‘t exist."));
            if ($oSurvey->alloweditaftercompletion == "Y") {
                $stateInfo .= CHtml::tag("li", array("class" => 'text-success'), $this->translate("You can edit submitted response."));
            } else {
                $stateInfo .= CHtml::tag("li", array("class" => 'text-warning'), $this->translate("When editing submitted reponse : you reset the submit date (see allow edit after completion settings)."));
            }
        }

        $stateInfo .= "</ul>";
        $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation($surveyId, App()->getLanguage());
        $surveyColumnsInformation->ByEmCode = true;
        $aQuestionList = $surveyColumnsInformation->allQuestionListData();
        $accesUrl = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid' => $surveyId));
        $linkManagement = CHtml::link(
            $this->translate("Link to response alternate management"),
            $accesUrl,
            array("target" => '_blank','class' => 'btn btn-block btn-default btn-lg')
        );
        if ($oSurvey->active != "Y") {
            $linkManagement = CHtml::htmlButton(
                $this->translate("Link to response alternate management"),
                array('class' => 'btn btn-block btn-default btn-lg','disabled' => 'disabled','title' => $this->translate("Survey is not activated"))
            );
        }
        $aSettings[$this->translate('Management')] = array(
            'link' => array(
                'type' => 'info',
                'content' => $linkManagement,
            ),
            'infoList' => array(
                'type' => 'info',
                'content' => CHtml::tag("div", array('class' => 'well well-sm'), $stateInfo),
            ),
        );
        $aSettings[$this->translate('Response Management table')] = array(
            'showId' => array(
                'type' => 'boolean',
                'label' => $this->translate('Show id of response.'),
                'current' => $this->get('showId', 'Survey', $surveyId, 1)
            ),
            'showCompleted' => array(
                'type' => 'boolean',
                'label' => $this->translate('Show completed state.'),
                'current' => $this->get('showCompleted', 'Survey', $surveyId, 1)
            ),
            'tokenAttributes' => array(
                'type' => 'select',
                'label' => $this->translate('Token attributes to show in management'),
                'options' => $this->getTokensAttributeList($surveyId, 'tokens.', true),
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => gT("All"),
                    'unselectValue' => "",
                ),
                'selectOptions' => array(
                    'placeholder' => gT("All"),
                ),
                'current' => $this->get('tokenAttributes', 'Survey', $surveyId)
            ),
            'tokenAttributesNone' => array(
                'type' => 'boolean',
                'label' => $this->translate('Hide all token attributes.'),
                'current' => $this->get('tokenAttributesNone', 'Survey', $surveyId, 0)
            ),
            'tokenColumnOrder' => array(
                'type' => 'select',
                'label' => $this->translate('Token attributes order'),
                'options' => array(
                    'start' => $this->translate("At start (before primary columns)"),
                    'default' => $this->translate("Between primary columns and secondary columns (default)"),
                    'end' => $this->translate("At end (after all other columns)"),
                ),
                'current' => $this->get('tokenColumnOrder', 'Survey', $surveyId, 'default')
            ),
            'surveyAttributes' => array(
                'type' => 'select',
                'label' => $this->translate('Survey columns to be show in management'),
                'options' => $aQuestionList['data'],
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => gT("All"),
                    'unselectValue' => "",
                    'options' => $aQuestionList['options'], // In dropdown, but not in select2
                ),
                'selectOptions' => array(
                    'placeholder' => gT("All"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class' => 'select2-withover ',
                ),
                'current' => $this->get('surveyAttributes', 'Survey', $surveyId)
            ),
            'surveyAttributesPrimary' => array(
                'type' => 'select',
                'label' => $this->translate('Survey columns to be show at first'),
                'help' => $this->translate('This question are shown at first, just after the id of the reponse.'),
                'options' => $aQuestionList['data'],
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => gT("None"),
                    'unselectValue' => "",
                    'options' => $aQuestionList['options'], // In dropdown, but not in select2
                ),
                'selectOptions' => array(
                    'placeholder' => gT("None"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class' => 'select2-withover ',
                ),
                'current' => $this->get('surveyAttributesPrimary', 'Survey', $surveyId)
            ),
            'tokenAttributesHideToUser' => array(
                'type' => 'select',
                'label' => $this->translate('Token columns to be hidden to user (include group administrator)'),
                'help' => $this->translate('This column are shown only to LimeSurvey administrator.'),
                'options' => $this->getTokensAttributeList($surveyId, 'tokens.', true),
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => gT("None"),
                    'unselectValue' => "",
                ),
                'selectOptions' => array(
                    'placeholder' => gT("None"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class' => 'select2-withover ',
                ),
                'current' => $this->get('tokenAttributesHideToUser', 'Survey', $surveyId)
            ),
            'surveyAttributesHideToUser' => array(
                'type' => 'select',
                'label' => $this->translate('Survey columns to be hidden to user (include group administrator)'),
                'help' => $this->translate('This column are shown only to LimeSurvey administrator.'),
                'options' => $aQuestionList['data'],
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => gT("None"),
                    'unselectValue' => "",
                    'options' => $aQuestionList['options'], // In dropdown, but not in select2
                ),
                'selectOptions' => array(
                    'placeholder' => gT("None"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class' => 'select2-withover ',
                ),
                'current' => $this->get('surveyAttributesHideToUser', 'Survey', $surveyId)
            ),
            'surveyNeededValues' => array(
                'type' => 'select',
                'label' => $this->translate('Hide answer without value on : '),
                'help' => '',
                'options' => $aQuestionList['data'],
                'htmlOptions' => array(
                    'multiple' => true,
                    'placeholder' => gT("None"),
                    'unselectValue' => "",
                    'options' => $aQuestionList['options'], // In dropdown, but not in select2
                ),
                'selectOptions' => array(
                    'placeholder' => gT("None"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class' => 'select2-withover ',
                ),
                'current' => $this->get('surveyNeededValues', 'Survey', $surveyId)
            ),
            'showFooter' => array(
                'type' => 'boolean',
                'label' => $this->translate('Show footer with count and sum.'),
                'current' => $this->get('showFooter', 'Survey', $surveyId, 0)
            ),
        );
        $aSettings[$this->translate('Date/time response management')] = array(
            'filterOnDate' => array(
                'type' => 'boolean',
                'label' => $this->translate('Show filter on date question.'),
                'current' => $this->get('filterOnDate', 'Survey', $surveyId, 1)
            ),
            'showDateInfo' => array(
                'type' => 'info',
                'content' => CHtml::tag("div", array("class" => "well"), $this->translate("All this settings after need date stamped survey.")),
            ),
            'showStartdate' => array(
                'type' => 'boolean',
                'label' => $this->translate('Show start date.'),

                'current' => $this->get('showStartdate', 'Survey', $surveyId, 0)
            ),
            'filterStartdate' => array(
                'type' => 'select',
                'options' => array(
                    0 => gT("No"),
                    1 => gT("Yes"),
                    2 => $this->translate("Yes with time"),
                ),
                'label' => $this->translate('Show filter on start date.'),
                'current' => $this->get('filterStartdate', 'Survey', $surveyId, 0)
            ),
            'showSubmitdate' => array(
                'type' => 'boolean',
                'label' => $this->translate('Show submit date.'),
                'current' => $this->get('showSubmitdate', 'Survey', $surveyId, 0)
            ),
            'filterSubmitdate' => array(
                'type' => 'select',
                'options' => array(
                    0 => gT("No"),
                    1 => gT("Yes"),
                    2 => $this->translate("Yes with time"),
                ),
                'label' => $this->translate('Show filter on submit date.'),
                'current' => $this->get('filterSubmitdate', 'Survey', $surveyId, 0)
            ),
            'showDatestamp' => array(
                'type' => 'boolean',
                'label' => $this->translate('Show last action date.'),
                'current' => $this->get('showDatestamp', 'Survey', $surveyId, 0)
            ),
            'filterDatestamp' => array(
                'type' => 'select',
                'options' => array(
                    0 => gT("No"),
                    1 => gT("Yes"),
                    2 => $this->translate("Yes with time"),
                ),
                'label' => $this->translate('Show filter on start date.'),
                'current' => $this->get('filterDatestamp', 'Survey', $surveyId, 0)
            ),
        );
        /* Descrition by lang */
        $aDescription = array();
        $aDescriptionCurrent = $this->get('description', 'Survey', $surveyId);
        $languageData = getLanguageData(false, Yii::app()->getLanguage());
        foreach ($oSurvey->getAllLanguages() as $language) {
            $aDescription['description_' . $language] = array(
                'type' => 'text',
                'label' => sprintf($this->translate("In %s language (%s)"), $languageData[$language]['description'], $languageData[$language]['nativedescription']),
                'current' => (isset($aDescriptionCurrent[$language]) ? $aDescriptionCurrent[$language] : ""),
            );
        }
        $aSettings[$this->translate('Description and helper for responses listing')] = $aDescription;
        /* Template to be used */
        if (version_compare(Yii::app()->getConfig('versionnumber'), "3", ">=")) {
            $oTemplates = TemplateConfiguration::model()->findAll(array(
                'condition' => 'sid IS NULL',
            ));
            $aTemplates = CHtml::listData($oTemplates, 'template_name', 'template_name');
        } else {
            $aTemplates = array_keys(Template::getTemplateList());
        }
        $default = $this->get('template', null, null, App()->getConfig('defaulttheme', App()->getConfig('defaulttemplate')));
        $aSettings[$this->translate('Template to be used')] = array(
            'template' => array(
                'type' => 'select',
                'label' => $this->translate('Template to be used'),
                'options' => $aTemplates,
                'htmlOptions' => array(
                    'empty' => sprintf($this->translate("Leave default (%s)"), $default),
                ),
                'current' => $this->get('template', 'Survey', $surveyId),
            )
        );
        /* Token attribute usage */
        if ($manageTokenAttributeGroup) {
            $tokenAttributeGroup = $this->get('tokenAttributeGroup', 'Survey', $surveyId);
            $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager', 'Survey', $surveyId);
        } else {
            $tokenAttributeGroup = \TokenUsersListAndManagePlugin\Utilities::getTokenAttributeGroup($surveyId);
            $tokenAttributeGroupManager = \TokenUsersListAndManagePlugin\Utilities::getTokenAttributeGroupManager($surveyId);
        }
        $aSettings[$this->translate('Response Management token attribute usage')] = array(
            'managedInTokenAndmanage' => array(
                'type' => 'info',
                'content' => CHtml::link(
                    sprintf($this->translate("Managed in Token Users List And Manage (%s) plugin"), 'TokenUsersListAndManage'),
                    array(
                        'admin/pluginhelper',
                        'sa' => 'sidebody',
                        'plugin' => 'TokenUsersListAndManage',
                        'method' => 'actionSettings',
                        'surveyId' => $surveyId
                    ),
                    array('class' => 'btn btn-block btn-link')
                ),
                'class' => 'h4'
            ),
            'tokenAttributeGroup' => array(
                'type' => 'select',
                'label' => $this->translate('Token attributes for group'),
                'options' => $this->getTokensAttributeList($surveyId),
                'htmlOptions' => array(
                    'empty' => $this->translate("None"),
                    'disabled' => !$manageTokenAttributeGroup,
                ),
                'current' => $tokenAttributeGroup
            ),
            'tokenAttributeGroupManager' => array(
                'type' => 'select',
                'label' => $this->translate('Token attributes for group manager'),
                'help' => $this->translate('Any value except 0 and empty string set this to true.'),
                'options' => $this->getTokensAttributeList($surveyId),
                'htmlOptions' => array(
                    'empty' => $this->translate("None"),
                    'disabled' => !$manageTokenAttributeGroup,
                ),
                'current' => $tokenAttributeGroupManager
            ),
        );
        if ($manageTokenAttributeGroup) {
            unset($aSettings[$this->translate('Response Management token attribute usage')]['managedInTokenAndmanage']);
        }
        /* @todo : get settings of reloadAnyResponse and set warning + readonly on some settings */

        $aSettings[$this->translate('Response Management access and right')] = array(
            'infoRights' => array(
                'type' => 'info',
                'content' => CHtml::tag(
                    "ul",
                    array('class' => 'well well-sm list-unstyled'),
                    CHtml::tag("li", array(), $this->translate("Currently, for LimeSurvey admin user, for survey with token, need token read right.")) .
                    CHtml::tag("li", array(), $this->translate("For user, except for No : they always have same rights than other, for example if you allow delete to admin user, an user with token can delete his response with token.")) .
                    CHtml::tag("li", array(), $this->translate("To disable access for user with token you can set this settings to No or only for LimeSurvey admin.")) .
                    CHtml::tag("li", array('class' => 'text-warning'), sprintf("<strong>%s</strong>%s", $this->translate("Warning"), $this->translate(": you need to update reloadAnyResponse settings and right. This was not fixed here."))) .
                    ""
                ),
            ),
            'allowAccess' => array(
                'type' => 'select',
                'label' => $this->translate('Allow to access of tool.'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator (According to LimeSurvey Permission)"),
                    'all' => $this->translate("All (need a valid token)"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("None"),
                ),
                'help' => $this->translate('You can disable all access with token here. If user can access to the tool, he have same right than LimeSurvey admin on his responses.'),
                'current' => $this->get('allowAccess', 'Survey', $surveyId, 'all')
            ),
            'allowSee' => array(
                'type' => 'select',
                'label' => $this->translate('Allow view response of group'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator"),
                    'admin' => $this->translate("For group administrator and LimeSurvey administrator"),
                    'all' => $this->translate("All (with a valid token)"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'help' => $this->translate('Only related to Group responses not to single user responses with token.'),
                'current' => $this->get('allowSee', 'Survey', $surveyId, 'all')
            ),
            'allowEdit' => array(
                'type' => 'select',
                'label' => $this->translate('Allow edit response of group'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator"),
                    'admin' => $this->translate("For group administrator and LimeSurvey administrator"),
                    'all' => $this->translate("All (with a valid token)"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'help' => $this->translate('Need view rights.'),
                'current' => $this->get('allowEdit', 'Survey', $surveyId, 'admin')
            ),
            'allowDelete' => array(
                'type' => 'select',
                'label' => $this->translate('Allow deletion of response'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator"),
                    'admin' => $this->translate("For group administrator and LimeSurvey administrator"),
                    'all' => $this->translate("All (with a valid token)"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'help' => $this->translate('Need view rights.'),
                'current' => $this->get('allowDelete', 'Survey', $surveyId, 'admin')
            ),
            'allowAdd' => array(
                'type' => 'select',
                'label' => $this->translate('Allow add response'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator"),
                    'admin' => $this->translate("For group administrator and LimeSurvey administrator"),
                    'all' => $this->translate("All (with a valid token)"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'help' => $this->translate('Need view rights, for administrator, allow to choose token for creation.'),
                'current' => $this->get('allowAdd', 'Survey', $surveyId, 'admin')
            ),
            'allowAddSelf' => array(
                'type' => 'boolean',
                'label' => $this->translate('Allow add response with existing token according to survey setting'),
                'help' => $this->translate('If survey settings allow user to create new response, add a button to create a new response.'),
                'current' => $this->get('allowAddSelf', 'Survey', $surveyId, true)
            ),
            'allowAddUser' => array(
                'type' => 'select',
                'label' => $this->translate('Allow add token user'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator"),
                    'admin' => $this->translate("For group administrator and LimeSurvey administrator"),
                    'all' => $this->translate("All (with a valid token)"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'help' => $this->translate('Need add response right.'),
                'current' => $this->get('allowAddUser', 'Survey', $surveyId, 'admin')
            ),
        );

        $exportList = $this->_getExportList();
        $aSettings[$this->translate('User tools')] = array(
            'showLogOut' => array(
                'type' => 'select',
                'label' => $this->translate('Show log out button'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator"),
                    'all' => gT("Yes"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'current' => $this->get('showLogOut', 'Survey', $surveyId, $this->get('showLogOut', null, null, $this->settings['showLogOut']['default']) ? 'admin' : null)
            ),
            'showSurveyAdminpageLink' => array(
                'type' => 'select',
                'label' => $this->translate('Show survey admin page link'),
                'options' => array(
                    'limesurvey' => $this->translate("All LimeSurvey administrator"),
                    'admin' => $this->translate("LimeSurvey administrator with survey settings right"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'help' => $this->translate('Need add response right.'),
                'current' => $this->get('showSurveyAdminpageLink', 'Survey', $surveyId, $this->get('showAdminLink', null, null, $this->settings['showAdminLink']['default']) ? 'admin' : null)
            ),
            'showExportLink' => array(
                'type' => 'select',
                'label' => $this->translate('Show export for checked response'),
                'options' => array(
                    'limesurvey' => $this->translate("Only for LimeSurvey administrator"),
                    'all' => gT("Yes"),
                ),
                'current' => $this->get('showExportLink', 'Survey', $surveyId, $this->get('showExportLink', null, null, 'limesurvey')),
            ),
            'exportType' => array(
                'type' => 'select',
                'label' => $this->translate('Export type'),
                'options' => $exportList,
                'htmlOptions' => array(
                    'empty' => gT("None"),
                ),
                'current' => $this->get('exportType', 'Survey', $surveyId),
            ),
            'exportHeadexports' => array(
                'type' => 'select',
                'label' => $this->translate('Export questions as'),
                'options' => array(
                    'code' => gT("Question code"),
                    'abbreviated' => gT("Abbreviated question text"),
                    'full' => gT("Full question text"),
                    'codetext' => gT("Question code & question text"),
                ),
                'current' => $this->get('exportHeadexports', 'Survey', $surveyId, 'full'),
            ),
            'exportAnswers' => array(
                'type' => 'select',
                'label' => $this->translate('Export responses as'),
                'options' => array(
                    'short' => gT("Answer codes"),
                    'long' => gT("Full answers"),
                ),
                'current' => $this->get('exportAnswers', 'Survey', $surveyId, 'long'),
            ),
        );

        $aSettings[$this->translate('Survey behaviour')] = array(
            'afterSaveAll' => array(
                'type' => 'select',
                'label' => $this->translate('Action to do after save'),
                'options' => array(
                    'replace' => $this->translate('Replace totally the content and close dialog box, this can disable other plugin system.'),
                    'js' => $this->translate('Only close dialog box'),
                    'none' => $this->translate('Return to survey'),
                ),
                'htmlOptions' => array(
                    'empty' => sprintf($this->translate("Leave default (%s)"), $this->get('afterSaveAll', null, null, 'replace')),
                ),
                'current' => $this->get('afterSaveAll', 'Survey', $surveyId, ''),
            ),
            'saveAllAsDraft' => array(
                'type' => 'select',
                'label' => $this->translate('Save all reset submitdate (set as draft)'),
                'options' => array(
                    1 => gT("Yes"),
                ),
                'htmlOptions' => array(
                    'empty' => gT("No"),
                ),
                'current' => $this->get('saveAllAsDraft', 'Survey', $surveyId, 1),
                'help' => ($oSurvey->alloweditaftercompletion != "Y") ? "<div class='text-danger'>" . $this->translate("Survey participant settings, allow multiple responses is off : survey is set as draft when opened") . "</div>" : "",
            ),
        );
        $aData['pluginClass'] = get_class($this);
        $aData['surveyId'] = $surveyId;
        $aData['form'] = array(
            'action' => Yii::app()->createUrl('admin/pluginhelper/sa/sidebody', array('plugin' => get_class($this),'method' => 'actionSettings','surveyid' => $surveyId)),
            'reset' => Yii::app()->createUrl('admin/pluginhelper/sa/sidebody', array('plugin' => get_class($this),'method' => 'actionSettings','surveyid' => $surveyId)),
            'close' => Yii::app()->createUrl('surveyAdministration/view', array('surveyid' => $surveyId)),
        );
        if (intval(App()->getConfig('versionnumber')) < 4) {
            $aData['form']['close'] = Yii::app()->createUrl('admin/survey', array('sa' => 'view', 'surveyid' => $surveyId));
        }
        $aData['title'] = $this->translate("Response management settings");
        $aData['warningString'] = null;
        $aData['aSettings'] = $aSettings;
        $aData['assetUrl'] = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/settings');
        $content = $this->renderPartial('settings', $aData, true);
        return $content;
    }

    /**
     * Export response
     * @param int $surveyId Survey id
     * @param string|null $currenttoken token to be used for export : seems not sent currently (see LS issue)
     * @return mixed
     */
    private function _doExport($surveyId)
    {
        $currenttoken = Yii::app()->getRequest()->getParam('currenttoken');
        $userHaveRight = false;
        $settingAllowAccess = $this->get('showExportLink', 'Survey', $surveyId, 'limesurvey');
        if (empty($settingAllowAccess)) {
            throw new CHttpException(403, $this->translate("This action was not allowed"));
        }
        $exportType = $this->get('exportType', 'Survey', $surveyId);

        if (empty($exportType)) {
            throw new CHttpException(403, $this->translate("This action was not allowed"));
        }
        if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'export')) {
            $userHaveRight = true;
        }
        if (!$userHaveRight && $settingAllowAccess == 'limesurvey') {
            throw new CHttpException(401, $this->translate("This action was not allowed with your current rights."));
        }
        $oSurvey = \Survey::model()->findByPk($surveyId);
        if (!$userHaveRight && !$this->_allowTokenLink($oSurvey)) {
            throw new CHttpException(403, $this->translate("This action was not allowed"));
        }
        if (!$userHaveRight && !$currenttoken) {
            throw new CHttpException(401, $this->translate("This action was not allowed without a valid token."));
        }
        $aFilters = array();
        $checkeds = Yii::app()->getRequest()->getParam("checkeds", "");
        if (!empty($checkeds)) {
            $aChecked = explode(",", $checkeds);
            $aChecked = array_filter($aChecked, function ($id) {
                return ctype_digit($id) || is_int($id);
            });
            if (!empty($aChecked)) {
                $aFilters[] = " id IN (" . implode(",", $aChecked) . ")";
            }
        } else {
            if (Yii::app()->getRequest()->getParam("complete")) {
                $aFilters[] = " submitdate IS NOT NULL ";
            }
        }
        if ($currenttoken) {
            $aTokens = $this->_getTokensList($surveyId, $currenttoken);
            $aTokens = array_map(function ($token) {
                return Yii::app()->getDb()->quoteValue($token);
            }, $aTokens);
        }
        if (!empty($aTokens)) {
            $aFilters[] = ' {{survey_' . $surveyId . '}}.token IN (' . implode(",", $aTokens) . ')';
        }
        $sFilter = implode(" AND ", $aFilters);

        /* language … */
        $language = Survey::model()->findByPk($surveyId)->language;
        /* columns : add an option */
        $survey = \Survey::model()->findByPk($surveyId);
        if (intval(Yii::app()->getConfig('versionnumber')) < 3) {
            $survey = $surveyId;
        }
        $aFields = array_keys(createFieldMap($survey, 'full', true, false, $language));

        Yii::app()->loadHelper('admin/exportresults');
        Yii::import('application.helpers.viewHelper');
        \viewHelper::disableHtmlLogging();
        $oExport = new \ExportSurveyResultsService();
        $exports = $oExport->getExports();
        $oFormattingOptions = new \FormattingOptions();
        $oFormattingOptions->responseMinRecord = 1;
        $oFormattingOptions->responseMaxRecord = SurveyDynamic::model($surveyId)->getMaxId();
        $oFormattingOptions->selectedColumns = $aFields;
        $oFormattingOptions->responseCompletionState = 'all';
        $oFormattingOptions->headingFormat = $this->get('exportHeadexports', 'Survey', $surveyId, 'full');
        $oFormattingOptions->answerFormat = $this->get('exportAnswers', 'Survey', $surveyId, 'long');
        $oFormattingOptions->output = 'display';
        /* Hack action id to set to remotecontrol */
        $action = new stdClass();
        $action->id = 'remotecontrol';
        Yii::app()->controller->__set('action', $action);
        /* Export as display */
        if (version_compare(Yii::app()->getConfig('versionnumber'), "3.17.14", ">=")) {
            $oExport->exportResponses($surveyId, $language, $exportType, $oFormattingOptions, $sFilter);
        } else {
            $oExport->exportSurvey($surveyId, $language, $exportType, $oFormattingOptions, $sFilter);
        }
        Yii::app()->end();
    }

    /**
    * @see newSurveySettings
    */
    public function newSurveySettings()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }

    /** @inheritdoc **/
    public function newDirectRequest()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        if ($this->getEvent()->get('target') != get_class($this)) {
            return;
        }
        $this->_setConfig();
        $surveyId = App()->getRequest()->getQuery('sid', App()->getRequest()->getQuery('surveyid'));
        if (App()->getRequest()->getQuery('logout')) {
            $this->_doLogout($surveyId);
            App()->end(); // Not needed but more clear
        }
        if ($surveyId && App()->getRequest()->getQuery('delete')) {
            if (!app()->getRequest()->isPostRequest) {
                throw new CHttpException(405, gT("Method Not Allowed"));
            }
            $this->_deleteResponseSurvey($surveyId, App()->getRequest()->getQuery('delete'));
            App()->end(); // Not needed but more clear
        }
        if ($surveyId && App()->getRequest()->getQuery('action') == 'adduser') {
            $this->_addUserForSurvey($surveyId);
            App()->end(); // Not needed but more clear
        }
        if ($surveyId && App()->getRequest()->getQuery('action') == 'download') {
            $this->_downloadFile($surveyId);
            App()->end(); // Not needed but more clear
        }
        if ($surveyId && App()->getRequest()->getQuery('action') == 'export') {
            $this->_doExport($surveyId);
            App()->end(); // Not needed but more clear
        }
        if ($surveyId) {
            $this->_doSurvey($surveyId);
            App()->end(); // Not needed but more clear
        }
        $this->_doListSurveys();
    }

    /**
     * Download a file by manager
     */
    private function _downloadFile($surveyId)
    {
        $srid = Yii::app()->getRequest()->getParam('srid');
        $qid = Yii::app()->getRequest()->getParam('qid');
        $fileIndex = Yii::app()->getRequest()->getParam('fileindex');
        $oSurvey = Survey::model()->findByPk($surveyId);
        $oResponse = Response::model($surveyId)->findByPk($srid);
        if (!$oResponse) {
            throw new CHttpException(404);
        }
        if (!Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
            if (!$this->_allowTokenLink($oSurvey)) {
                throw new CHttpException(403);
            }
            $currentToken = $this->_getCurrentToken($surveyId);
            if (empty($currentToken)) {
                throw new CHttpException(401);
            }
            if ($currentToken != $oResponse->token) {
                throw new CHttpException(403);
            }
        }

        $aQuestionFiles = $oResponse->getFiles($qid);
        if (empty($aQuestionFiles[$fileIndex])) {
            throw new CHttpException(404, gT("Sorry, this file was not found."));
        }
        $aFile = $aQuestionFiles[$fileIndex];
        $sFileRealName = Yii::app()->getConfig('uploaddir') . "/surveys/" . $surveyId . "/files/" . $aFile['filename'];
        if (!file_exists($sFileRealName)) {
            throw new CHttpException(404, gT("Sorry, this file was not found."));
        }
        $mimeType = CFileHelper::getMimeType($sFileRealName, null, false);
        if (is_null($mimeType)) {
            $mimeType = "application/octet-stream";
        }
        $shownInline = false;
        if (!$this->get('forceDownloadImage', null, null, $this->settings['forceDownloadImage']['default']) && in_array($mimeType, array("image/gif","image/jpeg","image/pjpeg","image/png","application/pdf"))) {
            $shownInline = true;
        }
        @ob_clean();
        if (!$shownInline) {
            header('Content-Description: File Transfer');
        }
        header('Content-Type: ' . $mimeType);
        if (!$shownInline) {
            header('Content-Disposition: attachment; filename="' . sanitize_filename(rawurldecode($aFile['name'])) . '"');
        } else {
            header('Content-Disposition: inline; filename="' . sanitize_filename(rawurldecode($aFile['name'])) . '"');
        }
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($sFileRealName));
        readfile($sFileRealName);
        Yii::app()->end();
    }

    /**
     * Managing access to survey
     */
    private function _doSurvey($surveyId)
    {
        $this->aRenderData['surveyId'] = $surveyId;
        Yii::import('application.helpers.viewHelper');
        $oSurvey = Survey::model()->findByPk($surveyId);
        /* Must fix rights */
        $userHaveRight = false;
        $settingAllowAccess = $this->get('allowAccess', 'Survey', $surveyId, 'all');
        if (empty($settingAllowAccess)) {
            throw new CHttpException(403);
        }
        if ($oSurvey->active != 'Y') {
            if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
                throw new CHttpException(400, $this->translate("Survey is not activated"));
            }
            throw new CHttpException(403);
        }
        if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
            if (App()->getRequest()->isPostRequest && App()->getRequest()->getPost('login_submit')) {
                /* redirect to avoid CRSF when reload */
                Yii::app()->getController()->redirect(array("plugins/direct", 'plugin' => get_class(),'sid' => $surveyId));
            }
            $userHaveRight = true;
        }
        if (!$userHaveRight && ($settingAllowAccess == 'limesurvey' || !$this->_allowTokenLink($oSurvey))) {
            if (\responseListAndManage\Utilities::getCurrentUserId()) {
                throw new CHttpException(403);
            }
            $this->_doLogin();
        }
        if (!$userHaveRight && App()->getRequest()->getPost('token')) {
            $currentToken = App()->getRequest()->getPost('token');
            $oToken = Token::model($surveyId)->findByToken($currentToken);
            if ($oToken) {
                $this->_setCurrentToken($surveyId, $currentToken);
                /* redirect to avoid CRSF when reload */
                Yii::app()->getController()->redirect(array("plugins/direct", 'plugin' => get_class(),'sid' => $surveyId));
            } else {
                $this->aRenderData['error'] = $this->translate("This code is invalid.");
            }
        }
        /* Get the token by URI or session */
        $currentToken = $this->_getCurrentToken($surveyId);
        if (!$userHaveRight && $currentToken) {
            $userHaveRight = true;
            $this->_setCurrentToken($surveyId, $currentToken);
            Yii::app()->user->setState('disableTokenPermission', true);
        }

        if (!$userHaveRight) {
            if ($this->_allowTokenLink($oSurvey) && !Yii::app()->getRequest()->getParam('admin')) {
                $this->_showTokenForm($surveyId);
            } else {
                $this->_doLogin();
                //throw new CHttpException(401,$this->translate("Sorry, no access on this survey."));
            }
        }
        $oSurvey = Survey::model()->findByPk($surveyId);
        $language = App()->getlanguage();
        if (!in_array($language, $oSurvey->getAllLanguages())) {
            $language = $oSurvey->language;
            App()->setLanguage($language);
        }
        $this->aRenderData['aSurveyInfo'] = getSurveyInfo($surveyId, $language);

        $aResponseListAndManage = (array) Yii::app()->session['responseListAndManage'];
        $aResponseListAndManage[$surveyId] = $surveyId;
        Yii::app()->session['responseListAndManage'] = $aResponseListAndManage;
        Yii::import(get_class($this) . '.models.ResponseExtended');

        $mResponse = ResponseExtended::model($surveyId);
        $mResponse->setScenario('search');
        $mResponse->showFooter = $this->get('showFooter', 'Survey', $surveyId, false);
        $mResponse->filterOnDate = (bool) $this->get('filterOnDate', 'Survey', $surveyId, true);
        $mResponse->filterSubmitDate = (int) $this->get('filterSubmitDate', 'Survey', $surveyId, 0);
        $mResponse->filterStartdate = (int) $this->get('filterStartdate', 'Survey', $surveyId, 0);
        $mResponse->filterDatestamp = (int) $this->get('filterDatestamp', 'Survey', $surveyId, 0);

        $surveyNeededValues = $this->getAttributesColumn($surveyId, 'NeededValues');
        if (empty($surveyNeededValues)) {
            $surveyNeededValues = array();
        }
        if (is_string($surveyNeededValues)) {
            $surveyNeededValues = array($surveyNeededValues);
        }
        if (!empty($surveyNeededValues)) {
            $criteria = new CDbCriteria();
            foreach ($surveyNeededValues as $surveyNeededValue) {
                $surveyNeededValue = Yii::app()->getDb()->quoteColumnName($surveyNeededValue);
                $criteria->addCondition("$surveyNeededValue <>'' AND $surveyNeededValue IS NOT NULL");
            }
            $mResponse->searchCriteria = $criteria;
        }

        $filters = Yii::app()->request->getParam('ResponseExtended');
        if (!empty($filters)) {
            $mResponse->setAttributes($filters, false);
            if (!empty($filters['completed'])) {
                $mResponse->setAttribute('completed', $filters['completed']);
            }
        }
        $tokensFilter = Yii::app()->request->getParam('TokenDynamic');
        if (!empty($tokensFilter)) {
            $mResponse->setTokenAttributes($tokensFilter);
        }
        /* Access with token */
        $isManager = false;
        $tokenAttributeGroup = $this->get('tokenAttributeGroup', 'Survey', $surveyId);
        $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager', 'Survey', $surveyId);

        /* Get the final allowed according to current token) */
        $allowAccess = $allowSee = $allowEdit = $allowDelete = $allowAdd = false;
        $settingAllowAccess = $this->get('allowAccess', 'Survey', $surveyId, 'all');
        $settingAllowSee = $this->get('allowSee', 'Survey', $surveyId, 'all');
        $settingAllowEdit = $this->get('allowEdit', 'Survey', $surveyId, 'admin');
        $settingAllowDelete = $this->get('allowDelete', 'Survey', $surveyId, 'admin');
        $settingAllowAdd = $this->get('allowAdd', 'Survey', $surveyId, 'admin');
        $settingAllowAddUser = $this->get('allowAddUser', 'Survey', $surveyId, 'admin');
        /* Set forced allow accedd */
        $tokenAttributeGroup = $this->get('tokenAttributeGroup', 'Survey', $surveyId, null);
        $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager', 'Survey', $surveyId, null);
        $tokenGroup = null;
        $tokenAdmin = null;
        $isManager = false;
        /* Set the default according to Permission */
        if (empty($currentToken) && $this->_isLsAdmin()) {
            $allowAccess = Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read');
            if ($this->_surveyHasTokens($oSurvey)) {
                $allowAccess = $settingAllowAccess && Permission::model()->hasSurveyPermission($surveyId, 'token', 'read');
            }
            $allowSee = $allowAccess && $settingAllowSee && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read');
            $allowEdit = $allowSee && $settingAllowEdit && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'update');
            $allowDelete = $allowSee && $settingAllowDelete && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'delete');
            $allowAdd = $settingAllowAdd && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create');
            $allowAddUser = $this->_allowTokenLink($oSurvey) && $settingAllowAddUser && Permission::model()->hasSurveyPermission($surveyId, 'token', 'create');
            if ($allowEdit) {
                \reloadAnyResponse\Utilities::setForcedAllowedSettings($surveyId, 'allowAdminUser');
            }
        }
        if ($currentToken) {
            $mResponse->currentToken = $currentToken;
            $aTokens = (array) $currentToken;
            $oToken = Token::model($surveyId)->findByToken($currentToken);
            $tokenGroup = (!empty($tokenAttributeGroup) && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : null;
            $tokenAdmin = (!empty($tokenAttributeGroupManager) && !empty($oToken->$tokenAttributeGroupManager)) ? $oToken->$tokenAttributeGroupManager : null;
            $isManager = ((bool) $tokenAdmin) && trim($tokenAdmin) !== '0';
            $allowAccess = ($settingAllowAccess == 'all') || ($settingAllowAccess == 'admin' && $isManager);
            $allowSee = ($settingAllowSee == 'all') || ($settingAllowSee == 'admin' && $isManager);
            $allowEdit = $allowSee && (($settingAllowEdit == 'all') || ($settingAllowEdit == 'admin' && $isManager));
            $allowDelete = ($settingAllowDelete == 'all') || ($settingAllowDelete == 'admin' && $isManager);
            $allowAdd = ($settingAllowAdd == 'all') || ($settingAllowAdd == 'admin' && $isManager); // all add with any token (show token list)
            $allowAddUser = $this->_allowTokenLink($oSurvey) && ( ($settingAllowAddUser == 'all') || ($settingAllowAddUser == 'admin' && $isManager));
            $oTokenGroup = Token::model($surveyId)->findAll("token = :token", array(":token" => $currentToken));
            if ($tokenGroup) {
                $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup . "= :group", array(":group" => $tokenGroup));
                if ($allowSee) {
                    $aTokens = CHtml::listData($oTokenGroup, 'token', 'token');
                }
            }
            $mResponse->setAttribute('token', $aTokens);
            if (!$allowAccess && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
                throw new CHttpException(403, $this->translate('You are not allowed to use reponse management with this token.'));
            }
            if ($allowEdit) {
                \reloadAnyResponse\Utilities::setForcedAllowedSettings($surveyId, 'allowAdminUser');
                \reloadAnyResponse\Utilities::setForcedAllowedSettings($surveyId, 'allowTokenUser');
                if ($isManager || ($settingAllowSee  == 'all' && $settingAllowEdit == 'all')) {
                    \reloadAnyResponse\Utilities::setForcedAllowedSettings($surveyId, 'allowTokenGroupUser');
                }
            }
        }
        $mResponse->showEdit = $allowEdit;
        $mResponse->showDelete = $allowDelete;
        Yii::app()->user->setState('responseListAndManagePageSize', intval(Yii::app()->request->getParam('pageSize', Yii::app()->user->getState('responseListAndManagePageSize', 50))));
        // Check if allow check
        $selectableRows = 0;
        if ($this->get('exportType', 'Survey', $surveyId) && $this->get('showExportLink', 'Survey', $surveyId, $this->get('showExportLink', null, null, 'limesurvey'))) {
            if (Permission::model()->hasSurveyPermission($surveyId, 'response', 'export')) {
                $selectableRows = 2;
            }
            if ($selectableRows == 0 && $currentToken && $this->get('showExportLink', 'Survey', $surveyId) == 'all') {
                $selectableRows = 2;
            }
        }

        /* Add a new */
        $tokenList = null;
        $singleToken = null;
        if ($this->_allowTokenLink($oSurvey)) {
            if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create')) {
                if ($this->_allowMultipleResponse($oSurvey)) {
                    $oToken = Token::model($surveyId)->findAll("token is not null and token <> ''");
                } else {
                    $oToken = Token::model($surveyId)->with('responses')->findAll("t.token is not null and t.token <> '' and responses.id is null");
                }
                if (count($oToken) == 1) {
                    $singleToken = $oToken[0]->token;
                }
                $tokenList = CHtml::listData($oToken, 'token', function ($oToken) {
                    return CHtml::encode(trim($oToken->firstname . ' ' . $oToken->lastname . ' (' . $oToken->token . ')'));
                },
                $tokenAttributeGroup);
            }
            if ($currentToken) {
                if (is_array($currentToken)) {
                    $currentToken = array_shift(array_values($currentToken));
                }
                $tokenList = array($currentToken => $currentToken);
                if ($allowAdd) { /* Adding for all group */
                    $criteria = new CDbCriteria();
                    if ($this->_allowMultipleResponse($oSurvey)) {
                        $criteria->condition = "t.token is not null and t.token <> ''";
                    } else {
                        $criteria->condition = "t.token is not null and t.token <> '' and responses.id is null";
                    }
                    $criteria->addInCondition('t.token', $aTokens);
                    $oToken = Token::model($surveyId)->with('responses')->findAll($criteria);
                    if (count($oToken) == 1) {
                        $singleToken = $oToken[0]->token;
                    }
                    $tokenList = CHtml::listData($oTokenGroup, 'token', function ($oToken) {
                        return CHtml::encode(trim($oToken->firstname . ' ' . $oToken->lastname . ' (' . $oToken->token . ')'));
                    },
                    $tokenAttributeGroup);
                }
            }
            if (!empty($tokenList) && !empty($tokenList[''])) {
                $emptyTokenGroup = $tokenList[''];
                unset($tokenList['']);
                $tokenList = array_merge($emptyTokenGroup, $tokenList);
            }
        }

        $adminAction = $this->_getAdminMenu($surveyId);
        $this->aRenderData['adminAction'] = empty($adminAction) ? "" : $adminAction . " ";
        ;

        $addNew = '';
        if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create') && !$this->_surveyHasTokens($oSurvey)) {
            $addNew = CHtml::link(
                "<i class='fa fa-plus-circle' aria-hidden='true'></i> " . $this->translate("Create an new response"),
                array("survey/index",'sid' => $surveyId,'newtest' => "Y",'srid' => 'new','plugin' => get_class($this)),
                array('class' => 'btn btn-default btn-sm  addnew')
            );
        }
        if ($allowAdd && $singleToken) {
            if ($this->_allowMultipleResponse($oSurvey)) {
                $addNew = CHtml::link(
                    "<i class='fa fa-plus-circle' aria-hidden='true'></i> " . $this->translate("Create an new response"),
                    array("survey/index",'sid' => $surveyId,'newtest' => "Y",'srid' => 'new','token' => $singleToken,'plugin' => get_class($this)),
                    array('class' => 'btn btn-default btn-sm addnew')
                );
            }
        }
        if (!$allowAdd && $currentToken) {
            if ($this->get('allowAddSelf', 'Survey', $surveyId, true) && $this->_allowMultipleResponse($oSurvey)) {
                $addNew = CHtml::link(
                    "<i class='fa fa-plus-circle' aria-hidden='true'></i> " . $this->translate("Create an new response"),
                    array("survey/index",'sid' => $surveyId,'newtest' => "Y",'srid' => 'new','token' => $currentToken,'plugin' => get_class($this)),
                    array('class' => 'btn btn-default btn-sm addnew')
                );
            }
        }
        if ($allowAdd && !empty($tokenList) && !$singleToken) {
            $addNew  = CHtml::beginForm(array("survey/index",'sid' => $surveyId), 'get', array('class' => "form-inline"));
            $addNew .= CHtml::hiddenField('srid', 'new');
            $addNew .= CHtml::hiddenField('newtest', "Y");
            $addNew .= CHtml::hiddenField('plugin', get_class($this));
            //~ $addNew .= '<div class="form-group"><div class="input-group">';
            $addNew .= CHtml::dropDownList('token', $currentToken, $tokenList, array('class' => 'form-control input-sm','empty' => gT("Please choose...")));
            $addNew .= CHtml::htmlButton(
                "<i class='fa fa-plus-circle' aria-hidden='true'></i> " . $this->translate("Create an new response"),
                array("type" => 'submit','name' => 'addnew','value' => 'new','class' => 'btn btn-default btn-sm addnew')
            );
            //~ $addNew .= '</div></div>';

            $addNew .= CHtml::endForm();
        }

        $this->aRenderData['addNew'] = empty($addNew) ? "" : $addNew . " ";
        ;
        $aColumns = array();
        $disableTokenPermission = (bool) $currentToken;
        /* Get the selected columns only */
        $tokenAttributes = $this->get('tokenAttributes', 'Survey', $surveyId);
        $surveyAttributes = $this->getAttributesColumn($surveyId);
        $surveyAttributesPrimary = $this->getAttributesColumn($surveyId, 'Primary');
        $filteredArr = array();
        $aRestrictedColumns = array();
        if (empty($tokenAttributes)) {
            if ($this->get('tokenAttributesNone', 'Survey', $surveyId, 0)) {
                $tokenAttributes = array();
            } else {
                $tokenAttributes = array_keys($this->getTokensAttributeList($surveyId, 'tokens.', true));
            }
        }
        /* remove tokens.token if user didn't have right to edit */
        if ($currentToken && !$allowEdit) {
            /* unset by value */
            $tokenAttributes = array_values(array_diff($tokenAttributes, array('tokens.token')));
        }
        if (empty($surveyAttributes)) {
            $surveyAttributes = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionListData($surveyId, App()->getLanguage());
            $surveyAttributes = array_keys($surveyAttributes['data']);
        }
        if (is_string($surveyAttributes)) {
            $surveyAttributes = array($surveyAttributes);
        }
        if (empty($surveyAttributesPrimary)) {
            $surveyAttributesPrimary = array();
        }
        if (is_string($surveyAttributesPrimary)) {
            $surveyAttributesPrimary = array($surveyAttributesPrimary);
        }
        if (!empty($surveyAttributesPrimary)) {
            $surveyAttributes = array_diff($surveyAttributes, $surveyAttributesPrimary);
        }
        $baseColumns = array();
        if ($this->get('showId', 'Survey', $surveyId, 1)) {
            $baseColumns[] = 'id';
        }
        if ($allowEdit || $allowDelete) {
            $baseColumns[] = 'button';
        }
        if ($this->get('showCompleted', 'Survey', $surveyId, 1)) {
            $baseColumns[] = 'completed';
        }
        if ($oSurvey->datestamp && $this->get('showStartdate', 'Survey', $surveyId, 0)) {
            $baseColumns[] = 'startdate';
        }
        if ($oSurvey->datestamp && $this->get('showSubmitDate', 'Survey', $surveyId, 0)) {
            $baseColumns[] = 'submitdate';
        }
        if ($oSurvey->datestamp && $this->get('showDatestamp', 'Survey', $surveyId, 0)) {
            $baseColumns[] = 'datestamp';
        }
        $aRestrictedColumns = array_merge($baseColumns, $tokenAttributes, $surveyAttributes, $surveyAttributesPrimary);
        switch ($this->get('tokenColumnOrder', 'Survey', $surveyId, 'default')) {
            case "start":
                $aRestrictedColumns = array_merge($baseColumns, $tokenAttributes, $surveyAttributesPrimary, $surveyAttributes);
                break;
            case "end":
                $aRestrictedColumns = array_merge($baseColumns, $surveyAttributesPrimary, $surveyAttributes, $tokenAttributes);
                break;
            case "default":
            default:
                $aRestrictedColumns = array_merge($baseColumns, $surveyAttributesPrimary, $tokenAttributes, $surveyAttributes);
        }
        if ($currentToken) {
            $tokenAttributesHideToUser = $this->get('tokenAttributesHideToUser', 'Survey', $surveyId);
            if (!empty($tokenAttributesHideToUser)) {
                $aRestrictedColumns = array_diff($aRestrictedColumns, $tokenAttributesHideToUser);
            }
            $surveyAttributesHideToUser = $this->getAttributesColumn($surveyId, 'HideToUser');
            if (!empty($surveyAttributesHideToUser)) {
                $aRestrictedColumns = array_diff($aRestrictedColumns, $surveyAttributesHideToUser);
            }
        }
        $mResponse->setRestrictedColumns($aRestrictedColumns);
        $aColumns = $mResponse->getGridColumns($disableTokenPermission);
        /* Get columns by order now … */
        $aOrderedColumn = array();
        if ($selectableRows) {
            $aOrderedColumn['selected'] = array(
                'class' => 'CCheckBoxColumn',
            );
        }
        foreach ($aRestrictedColumns as $key) {
            if (isset($aColumns[$key])) {
                $aOrderedColumn[$key] = $aColumns[$key];
            }
        }
        $this->aRenderData['allowAddUser'] = $allowAddUser;
        $this->aRenderData['addUser'] = array();
        $this->aRenderData['addUserButton'] = '';
        if ($allowAddUser) {
            $this->aRenderData['addUserButton'] = CHtml::htmlButton(
                "<i class='fa fa-user-plus ' aria-hidden='true'></i>" . $this->translate("Create an new user"),
                array("type" => 'button','name' => 'adduser','value' => 'new','class' => 'btn btn-default btn-sm addnewuser')
            );
            $this->aRenderData['addUser'] = $this->_addUserDataForm($surveyId, $currentToken);
        }
        /* whole translations */
        $this->aRenderData['lang'] = $this->getTranslations();

        /* The modal valid buttons */
        $allowsaveall = false;
        if (Yii::getPathOfAlias('autoSaveAndQuit')) {
            $autoSaveAndQuitActive = \autoSaveAndQuit\Utilities::getSetting($surveyId, 'autoSaveAndQuitActive');
            $autoSaveAndQuitRestrict = \autoSaveAndQuit\Utilities::getSetting($surveyId, 'autoSaveAndQuitRestrict');
            if ($autoSaveAndQuitActive != 'always' || $autoSaveAndQuitRestrict == 'ondemand') {
                $allowsaveall = true;
            }
        }

        $modalButtons = array(
            'close' => 'close',
        );
        $clearAllAction = \reloadAnyResponse\Utilities::getSetting($surveyId, 'clearAllAction');
        if ($clearAllAction == 'all') {
            $modalButtons['delete'] = "delete";
        }
        if ($oSurvey->format != "A" && $oSurvey->allowprev == "Y") {
            $modalButtons['moveprev'] = "moveprev";
        }
        if ($oSurvey->allowsave == "Y") {
            if ($allowsaveall) {
                $modalButtons['saveall'] = "saveall";
            }
            $modalButtons['saveallquit'] = "saveallquit";
        }
        if ($oSurvey->format != "A") {
            $modalButtons['movenext'] = "movenext";
        }
        $modalButtons['movesubmit'] = "movesubmit";
        $this->aRenderData['modalButtons'] = $modalButtons;
        /* model */
        $this->aRenderData['model'] = $mResponse;
        // Add comment block
        $aDescriptionCurrent = $this->get('description', 'Survey', $surveyId);
        $sDescriptionCurrent = isset($aDescriptionCurrent[Yii::app()->getLanguage()]) ? $aDescriptionCurrent[Yii::app()->getLanguage()] : "";
        $aReplacement = array(
            'SID' => $surveyId,
            'TOKEN' => $currentToken,
        );
        if (version_compare(App()->getConfig("versionnumber"), "3", "<")) {
            $sDescriptionCurrent = LimeExpressionManager::ProcessString($sDescriptionCurrent, null, $aReplacement);
        } else {
            $sDescriptionCurrent = LimeExpressionManager::ProcessString($sDescriptionCurrent, null, $aReplacement);
        }

        $this->aRenderData['selectableRows'] = $selectableRows;
        $this->aRenderData['description'] = $sDescriptionCurrent;
        $this->aRenderData['columns'] = $aOrderedColumn;
        $this->_render('responses');
    }

    private function _deleteResponseSurvey($surveyId, $srid)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            throw new CHttpException(404, $this->translate("Invalid survey id."));
        }
        $oResponse = Response::model($surveyId)->findByPk($srid);
        if (!$oResponse) {
            throw new CHttpException(404, $this->translate("Invalid response id."));
        }
        $allowed = false;
        /* Is an admin */
        if ($this->get('allowDelete', 'Survey', $surveyId, 'admin') && Permission::model()->hasSurveyPermission($surveyId, 'response', 'delete')) {
            $allowed = true;
        }
        /* Is not an admin */
        if (!$allowed && $this->get('allowDelete', 'Survey', $surveyId, 'admin') && $this->_surveyHasTokens($oSurvey)) {
            $whoIsAllowed = $this->get('allowDelete', 'Survey', $surveyId, 'admin');
            $token = App()->getRequest()->getQuery('token');
            $tokenAttributeGroup = $this->get('tokenAttributeGroup', 'Survey', $surveyId, null);
            $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager', 'Survey', $surveyId, null);
            $tokenGroup = null;
            $tokenAdmin = null;
            if ($token && $this->_allowTokenLink($oSurvey)) {
                $oToken =  Token::model($surveyId)->findByToken($token);
                $tokenGroup = (!empty($tokenAttributeGroup) && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : null;
                $tokenAdmin = (!empty($tokenAttributeGroupManager) && !empty($oToken->$tokenAttributeGroupManager)) ? $oToken->$tokenAttributeGroupManager : null;
            }
            $oResponse = Response::model($surveyId)->findByPk($srid);
            $responseToken = !(empty($oResponse->token)) ? $oResponse->token : null;
            if ($responseToken == $token) { /* Always allow same token */
                $allowed = true;
            }
            if (!$allowed && !empty($tokenGroup)) {
                $oResponseToken =  Token::model($surveyId)->findByToken($responseToken);
                $oResponseTokenGroup = !(empty($oResponseToken->$tokenAttributeGroup)) ? $oResponseToken->$tokenAttributeGroup : null;
                if ($this->get('allowDelete', 'Survey', $surveyId, 'admin') == 'all' && $oResponseTokenGroup  == $tokenGroup) {
                    $allowed = true;
                }
                if ($this->get('allowDelete', 'Survey', $surveyId, 'admin') == 'admin' && $oResponseTokenGroup  == $tokenGroup && $tokenAdmin) {
                    $allowed = true;
                }
            }
        }
        if (!$allowed) {
            throw new CHttpException(401, $this->translate('No right to delete this reponse.'));
        }
        if (!Response::model($surveyId)->deleteByPk($srid)) {
            throw new CHttpException(500, CHtml::errorSummary(Response::model($surveyId)));
        }
        return;
    }

    /**
     * Adding a token user to a survey
     */
    private function _addUserForSurvey($surveyId)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        $aResult = array(
            'status' => null,
        );
        if (!$oSurvey) {
            throw new CHttpException(404, $this->translate('Invalid survey id.'));
        }
        if (!$this->_allowTokenLink($oSurvey)) {
            throw new CHttpException(403, $this->translate('Token creation is disable for this survey.'));
        }
        $allowAddSetting = $this->get('allowAdd', 'Survey', $surveyId, 'admin');
        $allowAddUserSetting =  $this->get('allowAddUser', 'Survey', $surveyId, 'admin');
        $currenttoken = App()->getRequest()->getParam('currenttoken');
        $tokenGroup = null;
        $tokenAdmin = null;
        $allowAdd = false;
        if ($currenttoken) {
            $oCurrentToken =  Token::model($surveyId)->findByToken($currenttoken);
            $tokenAttributeGroup = $this->get('tokenAttributeGroup', 'Survey', $surveyId, null);
            $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager', 'Survey', $surveyId, null);
            $tokenGroup = (!empty($tokenAttributeGroup) && !empty($oCurrentToken->$tokenAttributeGroup)) ? $oCurrentToken->$tokenAttributeGroup : null;
            $tokenAdmin = (!empty($tokenAttributeGroupManager) && !empty($oCurrentToken->$tokenAttributeGroupManager)) ? $oCurrentToken->$tokenAttributeGroupManager : null;
            $isAdmin = ((bool) $tokenAdmin) && trim($tokenAdmin) !== '' && trim($tokenAdmin) !== '0';
            $allowAddUser = ($allowAddUserSetting == 'all' || ($allowAddUserSetting == 'admin' && $isAdmin));
        }

        if (!Permission::model()->hasSurveyPermission($surveyId, 'token', 'create')) {
            if (!$allowAddUser) {
                throw new CHttpException(403, $this->translate('No right to create new token in this survey.'));
            }
        }
        $oToken = Token::create($surveyId);
        $oToken->setAttributes(Yii::app()->getRequest()->getParam('tokenattribute'));
        if (!is_null($tokenGroup)) {
            $oToken->$tokenAttributeGroup = $tokenGroup;
            if (!empty($tokenAttributeGroupManager)) {
                $oToken->$tokenAttributeGroupManager = '';
            }
        }
        $resultToken = $oToken->save();
        if (!$resultToken) {
            $this->_returnJson(array(
                'status' => 'error',
                'html' => CHtml::errorSummary($oToken),
            ));
        }
        $oToken->generateToken();
        $oToken->save();

        if (!$this->_sendMail($surveyId, $oToken, App()->getRequest()->getParam('emailsubject'), App()->getRequest()->getParam('emailbody'))) {
            $html = sprintf($this->translate("Token created but unable to send the email, token code is %s"), CHtml::tag('code', array(), $oToken->token));
            $html .= CHtml::tag("hr");
            $html .= CHtml::tag("strong", array('class' => 'block'), $this->translate('Error return by mailer:'));
            $html .= CHtml::tag("div", array(), $this->mailError);
            $this->_returnJson(array(
                'status' => 'warning',
                'html' => $html,
            ));
        }
        $this->_returnJson(array('status' => 'success'));
    }

    /**
     * Managing list of Surveys
     */
    private function _doListSurveys()
    {
        $iAdminId = $this->_isLsAdmin();
        if ($iAdminId) {
            $this->_showSurveyList();
        }
        $this->_doLogin();
    }

    /** @inheritdoc **/
    public function getPluginSettings($getValues = true)
    {
        if (!Permission::model()->hasGlobalPermission('settings', 'read')) {
            throw new CHttpException(403);
        }
        if (Yii::app() instanceof CConsoleApplication) {
            return;
        }
        if (!$this->getIsUsable()) {
            $warningMessages = $this->getErrorsUnUsable();
            $this->settings = array(
                'unable' => array(
                    'type' => 'info',
                    'content' => CHtml::tag(
                        "ul",
                        array('class' => 'alert alert-warning'),
                        "<li>" . implode("</li><li>", $warningMessages) . "</li>"
                    ),
                ),
            );
            return $this->settings;
        }
        $this->settings['template']['default'] = App()->getConfig('defaulttheme', App()->getConfig('defaulttemplate'));
        $pluginSettings = parent::getPluginSettings($getValues);
        /* @todo : return if not needed */
        $accesUrl = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class()));
        $accesHtmlUrl = CHtml::link($accesUrl, $accesUrl);
        $pluginSettings['information']['content'] = sprintf($this->translate("Access link for survey listing : %s."), $accesHtmlUrl);
        $oTemplates = TemplateConfiguration::model()->findAll(array(
            'condition' => 'sid IS NULL',
        ));
        $aTemplates = CHtml::listData($oTemplates, 'template_name', 'template_name');
        $pluginSettings['template'] = array_merge($pluginSettings['template'], array(
            'type' => 'select',
            'options' => $aTemplates,
            'label' => $this->translate('Template to be used.'),
        ));
        $pluginSettings['showLogOut'] = array_merge($pluginSettings['showLogOut'], array(
            'label' => $this->translate('Show log out.'),
            'help' => $this->translate('On survey list and by default for admin'),
        ));
        $pluginSettings['showAdminLink'] =  array_merge($pluginSettings['showAdminLink'], array(
            'label' => $this->translate('Show LimeSurvey admininstration link.'),
            'help' => $this->translate('On survey list and by default for admin'),
        ));
        /* Find if menu already exist */
        $oSurveymenuEntries = SurveymenuEntries::model()->find("name = :name", array(":name" => 'reponseListAndManage'));
        $state = !empty($oSurveymenuEntries);
        $help = $state ? $this->translate('Menu exist, to delete : uncheck box and validate.') : $this->translate("Menu didn‘t exist, to create check box and validate.");
        $pluginSettings['createSurveyMenu'] = array(
            'type' => 'checkbox',
            'label' => $this->translate('Add a menu to responses management in surveys.'),
            'default' => false,
            'help' => $help,
            'current' => $state,
        );
        return $pluginSettings;
    }


    /**
     * @inheritdoc
     * and set menu if needed
    **/
    public function saveSettings($settings)
    {
        if (!Permission::model()->hasGlobalPermission('settings', 'update')) {
            throw new CHttpException(403);
        }
        parent::saveSettings($settings);
        if (version_compare(App()->getConfig("versionnumber"), "3", "<")) {
            return;
        }
        $oSurveymenuEntries = SurveymenuEntries::model()->find("name = :name", array(":name" => 'reponseListAndManage'));
        $createSurveyMenu = App()->getRequest()->getPost('createSurveyMenu');
        if (empty($oSurveymenuEntries) && App()->getRequest()->getPost('createSurveyMenu')) {
            $parentMenu = 1;
            $order = 3;
            /* Find response menu */
            $oResponseSurveymenuEntries = SurveymenuEntries::model()->find("name = :name", array(":name" => 'responses'));
            if ($oResponseSurveymenuEntries) {
                $parentMenu = $oResponseSurveymenuEntries->menu_id;
                $order = $oResponseSurveymenuEntries->ordering;
            }
            /* Unable to translate it currently … */
            $aNewMenu = array(
                'name' => 'responseListAndManage', // staticAddMenuEntry didn't use it but parse title
                'language' => 'en-GB',
                'title' => "Responses list",
                'menu_title' => "Responses list",
                'menu_description' => "Responses list and manage",
                'menu_icon' => 'list-alt',
                'menu_icon_type' => 'fontawesome',
                'menu_link' => 'plugins/direct', // 'plugins/direct/plugin/responseListAndManage'
                'manualParams' => array(
                    'plugin' => 'responseListAndManage',
                ),
                'permission' => 'responses',
                'permission_grade' => 'read',
                'hideOnSurveyState' => 'active', // This must be named as showOnSurveyState …
                'pjaxed' => false,
                'addSurveyId' => true,
                'addQuestionGroupId' => false,
                'addQuestionId' => false,
                'linkExternal' => false,
                'ordering' => $order,
            );
            $iMenu = SurveymenuEntries::staticAddMenuEntry($parentMenu, $aNewMenu);
            $oSurveymenuEntries = SurveymenuEntries::model()->findByPk($iMenu);
            if ($oSurveymenuEntries) {
                $oSurveymenuEntries->ordering = $order;
                $oSurveymenuEntries->name = 'reponseListAndManage'; // SurveymenuEntries::staticAddMenuEntry cut name, then reset
                $oSurveymenuEntries->save();
                SurveymenuEntries::reorderMenu($parentMenu);
            }
        }
        if (!empty($oSurveymenuEntries) && empty(App()->getRequest()->getPost('createSurveyMenu'))) {
            SurveymenuEntries::model()->deleteAll("name = :name", array(":name" => 'reponseListAndManage'));
        }
    }

    /**
     * Call plugin log and show login form if needed
     */
    private function _doLogin($surveyid = null)
    {
        $lang = App()->getRequest()->getParam('lang');
        if (empty($lang)) {
            $lang = App()->getConfig('defaultlang');
        }
        App()->setLanguage($lang);
        if (version_compare(App()->getConfig('versionnumber'), '5.1.6', ">")) {
            Yii::import('application.controllers.admin.Authentication', 1);
        } else {
            Yii::import('application.controllers.admin.authentication', 1);
        }
        $aResult = Authentication::prepareLogin();
        $succeeded = isset($aResult[0]) && $aResult[0] == 'success';
        $failed = isset($aResult[0]) && $aResult[0] == 'failed';
        $this->aRenderData['error'] = null;
        if ($succeeded) {
            $this->newDirectRequest();
        } elseif ($failed) {
            $message = $aResult[1];
            $this->aRenderData['error'] = $message;
            // Hack : remove POST value
            $_POST['login_submit'] = null;
            $aResult = Authentication::prepareLogin();
        }
        if (empty($aResult['defaultAuth'])) {
            $aResult['defaultAuth'] = 'Authdb';
        }

        $aLangList = getLanguageDataRestricted(true);
        foreach ($aLangList as $sLangKey => $aLanguage) {
            $languageData[$sLangKey] =  html_entity_decode($aLanguage['nativedescription'], ENT_NOQUOTES, 'UTF-8') . " - " . $aLanguage['description'];
        }
        $this->aRenderData['languageData'] = $languageData;
        $this->aRenderData['lang'] = $lang;
        $this->aRenderData['authPlugins'] = $aResult;

        $pluginsContent = $aResult['pluginContent'];
        $this->aRenderData['authSelectPlugin'] = $aResult['defaultAuth'];
        if (Yii::app()->getRequest()->isPostRequest) {
            $this->aRenderData['authSelectPlugin'] = Yii::app()->getRequest()->getParam('authMethod');
        }
        $possibleAuthMethods = array();
        $pluginNames = array_keys($pluginsContent);
        foreach ($pluginNames as $plugin) {
            $info = App()->getPluginManager()->getPluginInfo($plugin);
            $possibleAuthMethods[$plugin] = $info['pluginName'];
        }
        $this->aRenderData['authPluginsList'] = $possibleAuthMethods;
        $pluginContent = "";
        if (isset($pluginsContent[$this->aRenderData['authSelectPlugin']])) {
            $pluginBlockData = $pluginsContent[$this->aRenderData['authSelectPlugin']];
            $pluginContent = $pluginBlockData->getContent();
        }
        $this->aRenderData['pluginContent'] = $pluginContent;
        $this->aRenderData['summary'] = $aResult['summary'];
        $this->aRenderData['subtitle'] = gT('Log in');
        /* Bad hack … or not ?*/
        //~ header("HTTP/1.1 401 Unauthorized");
        $this->_render('login');
    }

    private function _doLogout($surveyId = null)
    {
        if ($surveyId && $this->_getCurrentToken($surveyId)) {
            /* admin can come from survey with token */
            $this->_setCurrentToken($surveyId, null);
        }
        $userId = \responseListAndManage\Utilities::getCurrentUserId();
        if ($userId) {
            $beforeLogout = new PluginEvent('beforeLogout');
            App()->getPluginManager()->dispatchEvent($beforeLogout);
            regenerateCSRFToken();
            App()->user->logout();
            /* Adding afterLogout event */
            $event = new PluginEvent('afterLogout');
            App()->getPluginManager()->dispatchEvent($event);
        }
        if ($surveyId) {
            App()->getController()->redirect(
                array('plugins/direct',
                    'plugin' => get_class($this),
                    'sid' => $surveyId
                )
            );
        }
        App()->getController()->redirect(
            array('plugins/direct',
                'plugin' => get_class($this),
            )
        );
    }

    /**
     * Show a token form
     */
    private function _showTokenForm($surveyid)
    {
        $lang = App()->getRequest()->getParam('lang');
        if (empty($lang)) {
            $lang = App()->getConfig('defaultlang');
        }
        $oSurvey = Survey::model()->findByPk($surveyid);
        $this->aRenderData['languageData'] = $oSurvey->getAllLanguages();
        if (!in_array($lang, $this->aRenderData['languageData'])) {
            $lang = $oSurvey->language;
        }
        App()->setLanguage($lang);
        $this->aRenderData['subtitle'] = gT("If you have been issued a token, please enter it in the box below and click continue.");
        $this->aRenderData['adminLoginUrl'] = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid' => $surveyid,'admin' => 1));
        $this->_render('token');
    }

    /**
     * Show the survey listing
     */
    private function _showSurveyList()
    {
        Yii::app()->user->setState('pageSize', intval(Yii::app()->request->getParam('pageSize', Yii::app()->user->getState('pageSize', Yii::app()->params['defaultPageSize']))));
        Yii::import(get_class($this) . '.models.SurveyExtended');
        $surveyModel = new SurveyExtended();
        $surveyModel->setScenario('search');
        $this->aRenderData['surveyModel'] = $surveyModel;
        /* @todo : filter by settings … */
        $filter = Yii::app()->request->getParam('SurveyExtended');
        $surveyModel->title = empty($filter['title']) ? null : $filter['title'];
        $dataProvider = $surveyModel->search();
        //$accessSettings = PluginSettings::model()->findAll …
        $this->aRenderData['adminMenu'] = $this->_getAdminMenu();
        $this->aRenderData['dataProvider'] = $dataProvider;
        $this->_render('surveys');
    }

    /**
     * get the data form for adding an user
     */
    private function _addUserDataForm($surveyId, $currentToken)
    {
        $forcedGroup = null;
        $tokenAttributeGroup = $this->get('tokenAttributeGroup', 'Survey', $surveyId);
        $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager', 'Survey', $surveyId);
        if (!Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create')) {
            $oToken = Token::model($surveyId)->findByToken($currentToken);
            $forcedGroup = ($tokenAttributeGroup && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : "";
        }
        $addUser = array();
        $addUser['action'] = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid' => $surveyId,'action' => 'adduser'));
        if (!Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create')) {
            $addUser['action'] = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid' => $surveyId,'currenttoken' => $currentToken,'action' => 'adduser'));
        }
        $oSurvey = Survey::model()->findByPk($surveyId);
        $aSurveyInfo = getSurveyInfo($surveyId, Yii::app()->getLanguage());
        $aAllAttributes = $aRegisterAttributes = $aSurveyInfo['attributedescriptions'];
        foreach ($aRegisterAttributes as $key => $aRegisterAttribute) {
            if ($aRegisterAttribute['show_register'] != 'Y') {
                unset($aRegisterAttributes[$key]);
            } else {
                $aRegisterAttributes[$key]['caption'] = (!empty($aSurveyInfo['attributecaptions'][$key]) ? $aSurveyInfo['attributecaptions'][$key] : ($aRegisterAttribute['description'] ? $aRegisterAttribute['description'] : $key));
            }
        }
        unset($aRegisterAttributes[$tokenAttributeGroup]);
        unset($aRegisterAttributes[$tokenAttributeGroupManager]);
        $addUser['attributes'] = $aRegisterAttributes;
        $addUser["attributeGroup"] = null;
        $addUser["tokenAttributeGroupManager"] = null;
        if (is_null($forcedGroup)) {
            if ($tokenAttributeGroup) {
                $addUser["attributeGroup"] = $aAllAttributes[$tokenAttributeGroup];
                $addUser["attributeGroup"]['attribute'] = $tokenAttributeGroup;
                $addUser["attributeGroup"]['caption'] = ($aSurveyInfo['attributecaptions'][$tokenAttributeGroup] ? $aSurveyInfo['attributecaptions'][$tokenAttributeGroup] : ($aAllAttributes[$tokenAttributeGroup]['description'] ? $aAllAttributes[$tokenAttributeGroup]['description'] : $this->translate("Is a group manager")));
            }
            if ($tokenAttributeGroupManager) {
                $addUser["tokenAttributeGroupManager"] = $aAllAttributes[$tokenAttributeGroup];
                $addUser["tokenAttributeGroupManager"]['attribute'] = $tokenAttributeGroup;
                $addUser["tokenAttributeGroupManager"]['caption'] = ($aSurveyInfo['attributecaptions'][$tokenAttributeGroupManager] ? $aSurveyInfo['attributecaptions'][$tokenAttributeGroupManager] : ($aAllAttributes[$tokenAttributeGroupManager]['description'] ? $aAllAttributes[$tokenAttributeGroupManager]['description'] : $this->translate("Is a group manager")));
            }
        }
        $emailType = 'register';
        $addUser['email'] = array(
            'subject' => $aSurveyInfo['email_' . $emailType . '_subj'],
            'body' => str_replace("<br />", "<br>", $aSurveyInfo['email_' . $emailType]),
            'help' => sprintf($this->translate("You can use token information like %s or %s, %s was replaced by the url for managing response."), "&#123;FIRSTNAME&#125;", "&#123;ATTRIBUTE_1&#125;", "&#123;SURVEYURL&#125;"),
            'html' => $oSurvey->htmlemail == "Y",
        );

        return $addUser;
    }

    /**
    * Add needed alias and put it in autoloader
    * @return void
    */
    private function _setConfig()
    {
        Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
    }

    /**
     * send the email with replacing SURVEYURL by the manage url
     */
    private function _sendMail($surveyId, $oToken, $sSubject = "", $sMessage = "")
    {
        $emailType = 'register';
        $sLanguage = App()->language;
        $aSurveyInfo = getSurveyInfo($surveyId, $sLanguage);

        $aMail = array();
        $aMail['subject'] = $sSubject;
        if (trim($sSubject) == "") {
            $aMail['subject'] = $aSurveyInfo['email_' . $emailType . '_subj'];
        }
        $aMail['message'] = $sMessage;
        if (trim($sMessage) == "") {
            $aMail['message'] = $aSurveyInfo['email_' . $emailType];
        }
        $aReplacementFields = array();
        $aReplacementFields["{ADMINNAME}"] = $aSurveyInfo['adminname'];
        $aReplacementFields["{ADMINEMAIL}"] = empty($aSurveyInfo['adminemail']) ? App()->getConfig('siteadminemail') : $aSurveyInfo['adminemail'];
        $aReplacementFields["{SURVEYNAME}"] = $aSurveyInfo['name'];
        $aReplacementFields["{SURVEYDESCRIPTION}"] = $aSurveyInfo['description'];
        $aReplacementFields["{EXPIRY}"] = $aSurveyInfo["expiry"];
        foreach ($oToken->attributes as $attribute => $value) {
            $aReplacementFields["{" . strtoupper($surveyId) . "}"] = $value;
        }
        $sToken = $oToken->token;
        $useHtmlEmail = (getEmailFormat($surveyId) == 'html');
        $aMail['subject'] = preg_replace("/{TOKEN:([A-Z0-9_]+)}/", "{" . "$1" . "}", $aMail['subject']);
        $aMail['message'] = preg_replace("/{TOKEN:([A-Z0-9_]+)}/", "{" . "$1" . "}", $aMail['message']);
        $aReplacementFields["{SURVEYURL}"] = Yii::app()->getController()->createAbsoluteUrl("plugins/direct", array('plugin' => get_class(),'sid' => $surveyId,'token' => $sToken));
        $aReplacementFields["{OPTOUTURL}"] = "";
        $aReplacementFields["{OPTINURL}"] = "";
        foreach (array('OPTOUT', 'OPTIN', 'SURVEY') as $key) {
            $url = $aReplacementFields["{{$key}URL}"];
            if ($useHtmlEmail) {
                $aReplacementFields["{{$key}URL}"] = "<a href='{$url}'>" . htmlspecialchars($url) . '</a>';
            }
            $aMail['subject'] = str_replace("@@{$key}URL@@", $url, $aMail['subject']);
            $aMail['message'] = str_replace("@@{$key}URL@@", $url, $aMail['message']);
        }
        // Replace the fields
        $aMail['subject'] = ReplaceFields($aMail['subject'], $aReplacementFields);
        $aMail['message'] = ReplaceFields($aMail['message'], $aReplacementFields);

        $sFrom = $aReplacementFields["{ADMINEMAIL}"];
        if (!empty($aReplacementFields["{ADMINNAME}"])) {
            $sFrom = $aReplacementFields["{ADMINNAME}"] . "<" . $aReplacementFields["{ADMINEMAIL}"] . ">";
        }

        $sBounce = getBounceEmail($surveyId);
        $sTo = $oToken->email;
        $sitename = Yii::app()->getConfig('sitename');
        // Plugin event for email handling (Same than admin token but with register type)
        //~ $event = new PluginEvent('beforeTokenEmail');
        //~ $event->set('survey', $surveyId);
        //~ $event->set('type', 'register');
        //~ $event->set('model', 'register');
        //~ $event->set('subject', $aMail['subject']);
        //~ $event->set('to', $sTo);
        //~ $event->set('body', $aMail['message']);
        //~ $event->set('from', $sFrom);
        //~ $event->set('bounce', $sBounce);
        //~ $event->set('token', $oToken->attributes);
        //~ App()->getPluginManager()->dispatchEvent($event);
        //~ $aMail['subject'] = $event->get('subject');
        //~ $aMail['message'] = $event->get('body');
        //~ $sTo = $event->get('to');
        //~ $sFrom = $event->get('from');
        //~ $sBounce = $event->get('bounce');

        $aRelevantAttachments = array();
        if (isset($aSurveyInfo['attachments'])) {
            $aAttachments = unserialize($aSurveyInfo['attachments']);
            if (!empty($aAttachments)) {
                if (isset($aAttachments['registration'])) {
                    LimeExpressionManager::singleton()->loadTokenInformation($aSurveyInfo['sid'], $sToken);

                    foreach ($aAttachments['registration'] as $aAttachment) {
                        if (LimeExpressionManager::singleton()->ProcessRelevance($aAttachment['relevance'])) {
                            $aRelevantAttachments[] = $aAttachment['url'];
                        }
                    }
                }
            }
        }
        global $maildebug, $maildebugbody;
        Yii::app()->setConfig("emailsmtpdebug", 0);
        if (false) { /* for event */
            $this->sMessage = $event->get('message', $this->sMailMessage); // event can send is own message
            if ($event->get('error') == null) {
                $today = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig('timeadjust'));
                $oToken->sent = $today;
                $oToken->save();
                return true;
            }
        } elseif (SendEmailMessage($aMail['message'], $aMail['subject'], $sTo, $sFrom, $sitename, $useHtmlEmail, $sBounce, $aRelevantAttachments)) {
            $today = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig('timeadjust'));
            $oToken->sent = $today;
            $oToken->save();
            return true;
        }
        /* todo : add error of email */
        $this->mailError = $maildebug;
        $this->mailDebug = $sFrom;
        return false;
    }

    /**
     * rendering a file in plugin view
     */
    private function _render($fileRender)
    {
        $surveyId = empty($this->aRenderData['surveyId']) ? null : $this->aRenderData['surveyId'];

        if (empty($this->aRenderData['aSurveyInfo'])) {
            $this->aRenderData['aSurveyInfo'] = array(
                'surveyls_title' => App()->getConfig('sitename'),
                'name' => App()->getConfig('sitename'),
            );
        }
        /* Get the template name */
        $templateName = Template::templateNameFilter($this->get('template', null, null, Yii::app()->getConfig('defaulttheme')));
        if ($surveyId) {
            if ($this->get('template', 'Survey', $surveyId)) {
                $templateName = Template::templateNameFilter($this->get('template', 'Survey', $surveyId));
                if ($templateName == Yii::app()->getConfig('defaulttheme')) {
                    $templateName = Template::templateNameFilter($this->get('template', null, null, Yii::app()->getConfig('defaulttheme')));
                }
            }
        }
        /* reset to get last option : plugin can update theme option*/
        Template::resetInstance();
        /* Let construct the page now */
        Template::getInstance($templateName, $surveyId);
        Template::getInstance($templateName, $surveyId)->oOptions->ajaxmode = 'off';

        if (empty($this->aRenderData['aSurveyInfo'])) {
            $this->aRenderData['aSurveyInfo'] = array(
                'surveyls_title' => App()->getConfig('sitename'),
                'name' => App()->getConfig('sitename'),
            );
        } else {
            Template::getInstance($templateName, $surveyId)->oOptions->container = 'off';
        }
        /* Specific event */
        $event = new PluginEvent('beforeRenderResponseListAndManage');
        $event->set('surveyId', $surveyId);
        $event->set('token', $this->_getCurrentToken($surveyId));
        App()->getPluginManager()->dispatchEvent($event);
        $this->aRenderData['pluginHtml'] = (string) $event->get('html');
        $this->aRenderData['pluginName'] = $pluginName = get_class($this);
        $this->aRenderData['plugin'] = $this;
        $this->aRenderData['username'] = $this->_isLsAdmin() ? Yii::app()->user->getName() : null;
        $this->subscribe('getPluginTwigPath');
        $this->aRenderData['responseListAndManage'] = $this->aRenderData;
        $responselist = Yii::app()->getController()->renderPartial(
            get_class($this) . ".views.content." . $fileRender,
            $this->aRenderData,
            true
        );

        App()->getClientScript()->registerPackage("bootstrap-datetimepicker");
        App()->clientScript->registerScriptFile(App()->getConfig("generalscripts") . 'nojs.js', CClientScript::POS_HEAD);

        Yii::setPathOfAlias(get_class($this), dirname(__FILE__));
        Yii::app()->clientScript->addPackage('responselistandmanage', array(
            'basePath'    => get_class($this) . '.assets.responselistandmanage',
            'js'          => array('responselistandmanage.js'),
            'css'          => array('responselistandmanage.css'),
            'depends'      => array('jquery'),
        ));
        Yii::app()->getClientScript()->registerPackage('responselistandmanage');
        $renderTwig = array(
            'responseListAndManage' => $this->aRenderData,
            'aSurveyInfo' => $this->aRenderData['aSurveyInfo'],
        );
        $renderTwig['aSurveyInfo']['name'] = sprintf($this->translate("Reponses of %s survey"), $renderTwig['aSurveyInfo']['name']);
        $renderTwig['aSurveyInfo']['active'] = 'Y'; // Didn't show the default warning
        $renderTwig['aSurveyInfo']['showprogress'] = 'N'; // Didn't show progress bar
        $renderTwig['aSurveyInfo']['include_content'] = 'responselistandmanage';
        $renderTwig['responseListAndManage']['responselist'] = $responselist;
        Yii::app()->twigRenderer->renderTemplateFromFile('layout_global.twig', $renderTwig, false);
        Yii::app()->end();
    }

    /**
     * get the token attribute list
     * @param integer $surveyId
     * @param string $prefix
     * @return array
     */
    private function getTokensAttributeList($surveyId, $prefix = "", $default = false)
    {
        if (Yii::getPathOfAlias('TokenUsersListAndManagePlugin')) {
            return \TokenUsersListAndManagePlugin\Utilities::getTokensAttributeList($surveyId, $prefix, $default);
        }
        $aTokens = array();
        if ($default) {
            $aTokens = array(
                $prefix . 'firstname' => gT("First name"),
                $prefix . 'lastname' => gT("Last name"),
                $prefix . 'token' => gT("Token"),
                $prefix . 'email' => gT("Email"),
            );
        }
        $oSurvey = Survey::model()->findByPk($surveyId);
        foreach ($oSurvey->getTokenAttributes() as $attribute => $information) {
            $aTokens[$prefix . $attribute] = $attribute;
            if (!empty($information['description'])) {
                $aTokens[$prefix . $attribute] = $information['description'];
            }
        }
        return $aTokens;
    }

    /**
     * Find if survey allow token link
     * @param \Survey
     * @return boolean
     */
    private function _allowTokenLink($oSurvey)
    {
        return $this->_surveyHasTokens($oSurvey) && $oSurvey->anonymized != "Y";
    }

    /**
     * Find if survey allow multiple response for token
     * @param \Survey
     * @return boolean
     */
    private function _allowMultipleResponse($oSurvey)
    {
        return $this->_allowTokenLink($oSurvey) && ($oSurvey->tokenanswerspersistence != "Y" || $oSurvey->showwelcome != 'Y' || $oSurvey->format == 'A');
    }

    /**
     * Get the administration menu
     * @param $surveyId
     * @return string html
     */
    private function _getAdminMenu($surveyId = null)
    {
        $adminAction = "";
        $showLogOut = $this->get('showLogOut', null, null, $this->settings['showLogOut']['default']);
        $showAdminSurveyLink = false;
        $showAdminLink = $this->get('showAdminLink', null, null, $this->settings['showAdminLink']['default']);
        $showExportLink = false;
        if ($surveyId) {
            $showLogOut = $this->get('showLogOut', 'Survey', $surveyId, $this->get('showLogOut', null, null, $this->settings['showLogOut']['default']) ? 'admin' : null);
            $showAdminSurveyLink = $this->get('showSurveyAdminpageLink', 'Survey', $surveyId, $this->get('showAdminLink', null, null, $this->settings['showAdminLink']['default']) ? 'admin' : null);
            $showAdminLink = $showAdminSurveyLink && $this->get('showAdminLink', null, null, $this->settings['showAdminLink']['default']);
            $showExportLink = $this->get('showExportLink', 'Survey', $surveyId, $this->get('showExportLink', null, null, 'limesurvey'));
        }
        $userId = \responseListAndManage\Utilities::getCurrentUserId();
        $actionLinks = array();
        $currentToken = null;
        if (!$userId) {
            $currentToken = $this->_getCurrentToken($surveyId);
            if ($surveyId && $showLogOut == 'all') {
                $actionLinks[] = array(
                    'text' => "<i class='fa fa-sign-out' aria-hidden='true'></i> " . $this->translate("Log out"),
                    'link' => array("plugins/direct",'plugin' => get_class(),'sid' => $surveyId,'logout' => "logout"),
                );
            }
        }
        if ($userId && $showLogOut) {
            $actionLinks[] = array(
                'text' => "<i class='fa fa-sign-out' aria-hidden='true'></i> " . $this->translate("Log out"),
                'link' => array("plugins/direct",'plugin' => get_class(),'sid' => $surveyId,'logout' => "logout"),
            );
        }
        if ($userId && $showAdminLink) {
            $actionLinks[] = array(
                'text' => "<i class='fa fa-cogs' aria-hidden='true'></i> " . $this->translate("LimeSurvey administration"),
                'link' => array("admin/index"),
            );
        }
        if ($userId && $surveyId && (Permission::model()->hasSurveyPermission($surveyId, 'survey', 'read') || $showAdminSurveyLink == 'limesurvey') && $showAdminSurveyLink) {
            $actionLinks[] = array(
                'text' => "<i class='fa fa-cog' aria-hidden='true'></i> " . $this->translate("Survey administration"),
                'link' => array("admin/survey/sa/view",'surveyid' => $surveyId),
            );
            if (Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'update')) {
                $actionLinks[] = array(
                    'text' => "<i class='fa fa-cog' aria-hidden='true'></i> " . $this->translate("Manage responses listing"),
                    'link' => array('admin/pluginhelper',
                        'sa' => 'sidebody',
                        'plugin' => get_class($this),
                        'method' => 'actionSettings',
                        'surveyId' => $surveyId
                    ),
                );
            }
        }

        if ($showExportLink && $this->get('exportType', 'Survey', $surveyId)) {
            $actionExportLink = array(
                'text' => "<i class='fa fa-download' aria-hidden='true'></i> " . $this->translate("Export (checked) response"),
                'link' => array('plugins/direct',
                    'plugin' => get_class($this),
                    'action' => 'export',
                    'sid' => $surveyId,
                    'currenttoken' => $currentToken,
                ),
                'htmlOptions' => array('data-export-checked' => true,'download' => 1),
            );
            if (!$userId && $showExportLink == 'all') {
                $actionLinks[] = $actionExportLink;
            }
            if (Permission::model()->hasSurveyPermission($surveyId, 'response', 'export')) {
                unset($actionExportLink['link']['currenttoken']);
                $actionLinks[] = $actionExportLink;
            }
        }
        if (count($actionLinks) == 1) {
            $actionLink = array_merge(array('htmlOptions' => array('class' => 'btn btn-default btn-sm btn-admin')), $actionLinks[0]);
            $adminAction = CHtml::link(
                $actionLink['text'],
                $actionLink['link'],
                $actionLink['htmlOptions']
            );
        }
        if (count($actionLinks) > 1) {
            $btnLabel = $this->translate("Tools");
            if ($userId) {
                $oUser = User::model()->findByPk($userId);
                $btnLabel = \CHtml::encode($oUser->full_name);
            }

            $adminAction = '<div class="dropup">' .
                           '<button class="btn btn-default btn-sm dropdown-toggle" type="button" id="dropdownAdminAction" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' .
                           $btnLabel .
                           '<span class="caret"></span>' .
                           '</button>' .
                           '<ul class="dropdown-menu" aria-labelledby="dropdownAdminAction">';
            $adminAction .= implode('', array_map(function ($link) {
                $link = array_merge_recursive(array('htmlOptions' => array('class' => 'btn btn-default btn-sm btn-admin')), $link);
                return CHtml::tag('li', array(), CHtml::link($link['text'], $link['link'], $link['htmlOptions']));
            }, $actionLinks));
            $adminAction .= '</ul>';
            $adminAction .= '</div>';
        }
        return $adminAction;
    }

    /**
     * Get the current token by URI or session
     * @param integer $surveyId
     * @return string|null
     */
    private function _getCurrentToken($surveyId)
    {
        $tokenQuery = Yii::app()->getRequest()->getQuery('token');
        if ($tokenQuery && is_string($tokenQuery)) {
            $this->_setCurrentToken($surveyId, $tokenQuery);
            return Yii::app()->getRequest()->getQuery('token');
        }
        $sessionTokens = Yii::app()->session['responseListAndManageTokens'];
        if (!empty($sessionTokens[$surveyId])) {
            return $sessionTokens[$surveyId];
        }
    }

    /**
     * set the current token for this survey
     * @param integer $surveyId
     * @param string $token
     * @return string|null
     */
    private function _setCurrentToken($surveyId, $token)
    {
        $sessionTokens = Yii::app()->session['responseListAndManageTokens'];
        if (empty($sessionTokens)) {
            $sessionTokens = array();
        }
        $sessionTokens[$surveyId] = $token;
        Yii::app()->session['responseListAndManageTokens'] = $sessionTokens;
    }

    /**
     * Just a quickest and cleaner way to return json
     */
    private function _returnJson($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        Yii::app()->end();
    }

    /**
     * Log message
     * @return void
     */
    public function log($message, $level = \CLogger::LEVEL_TRACE)
    {
        if (is_callable("parent::log")) {
            //parent::log($message, $level);
        }
        Yii::log("[" . get_class($this) . "] " . $message, $level, 'vardump');
    }

    /**
     * get translation
     * @param string
     * @return string
     */
    private function translate($string)
    {
        return Yii::t('', $string, array(), get_class($this) . 'Messages');
    }

    /**
     * register to needed event according to usability
     * @see event afterPluginLoad
     */
    public function afterPluginLoad()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        if (!$this->getIsUsable()) {
            $this->subscribe('beforeActivate');
            $this->subscribe('beforeControllerAction');
            $this->unsubscribe('newDirectRequest');
            $this->unsubscribe('beforeSurveySettings');
            $this->unsubscribe('beforeToolsMenuRender');
            $this->unsubscribe('beforeSurveyPage');
            $this->unsubscribe('afterSurveyComplete');
            return;
        }
        // messageSource for this plugin:
        $messageSource = array(
            'class' => 'CGettextMessageSource',
            'cacheID' => 'ResponseListAndManageLang',
            'cachingDuration' => 3600,
            'forceTranslation' => true,
            'useMoFile' => true,
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR . 'locale',
            'catalog' => 'messages',// default from Yii
        );
        Yii::app()->setComponent(get_class($this) . 'Messages', $messageSource);
    }

    /**
     * Return if user is connected to LS admin
     */
    private function _isLsAdmin()
    {
        return Yii::app()->session['loginID'];
    }

    /**
     * return if survey has token table
     * @param \Survey
     * @return boolean
     */
    private function _surveyHasTokens($oSurvey)
    {
        if (version_compare(Yii::app()->getConfig('versionnumber'), '3', ">=")) {
            return $oSurvey->getHasTokensTable();
        }
        Yii::import('application.helpers.common_helper', true);
        return tableExists("{{tokens_" . $oSurvey->sid . "}}");
    }

    private function _getTokensList($surveyId, $token)
    {
        $tokenAttributeGroup = $this->get('tokenAttributeGroup', 'Survey', $surveyId);
        $oTokenGroup = Token::model($surveyId)->find("token = :token", array(":token" => $token));
        $tokenGroup = (isset($oTokenGroup->$tokenAttributeGroup) && trim($oTokenGroup->$tokenAttributeGroup) != '') ? $oTokenGroup->$tokenAttributeGroup : null;
        if (empty($tokenGroup)) {
            return array($token => $token);
        }
        $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup . "= :group", array(":group" => $tokenGroup));
        return CHtml::listData($oTokenGroup, 'token', 'token');
    }

    /**
     * Get the attributes selected by column name
     * @param specific attribute type
     * @return array
     */
    private function getAttributesColumn($surveyId, $type = "")
    {
        switch ($type) {
            case 'Primary':
                $attributes = $this->get('surveyAttributesPrimary', 'Survey', $surveyId);
                break;
            case 'HideToUser':
                $attributes = $this->get('surveyAttributesHideToUser', 'Survey', $surveyId);
                break;
            case 'NeededValues':
                $attributes = $this->get('surveyNeededValues', 'Survey', $surveyId);
                break;
            case '':
            default:
                $attributes = $this->get('surveyAttributes', 'Survey', $surveyId);
        }
        if (empty($attributes)) {
            return array();
        }
        if (is_string($attributes)) {
            $attributes = array($attributes);
        }
        // $availableColumns = SurveyDynamic::model($surveyId)->getAttributes();
        if ($this->get('SettingsVersion', 'Survey', $surveyId, 0) < 2) {
            return $attributes;
        }
        /* We get the columln with intersect with surveyCodeHelper */
        $intersect = array_intersect(getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($surveyId), $attributes);
        return array_flip($intersect);
    }

    /**
     * get available export type
     */
    private function _getExportList()
    {
        Yii::app()->loadHelper("admin/exportresults");
        $resultsService = new ExportSurveyResultsService();
        $exports = $resultsService->getExports();
        $exports = array_filter($exports);
        $exportData = array();
        foreach ($exports as $key => $plugin) {
            $event = new PluginEvent('listExportOptions');
            $event->set('type', $key);
            $oPluginManager = App()->getPluginManager();
            $oPluginManager->dispatchEvent($event, $plugin);
            $exportData[$key] = $event->get('label');
        }
        return array_filter($exportData);
    }

    private function getTranslations()
    {
        return array(
            'Close' => gT('Close'),
            'Delete' => $this->translate("Delete"),
            'Previous' => gT('Previous'),
            'Save' => gT('Save'),
            'Save as draft' => $this->translate('Save as draft'),
            'Save and quit' => $this->translate('Save and quit'),
            'Save as draft and quit' => $this->translate('Save as draft and quit'),
            'Next' => gT('Next'),
            'Submit' => gT('Submit'),
            'Save as complete' => gT('Save as complete'),
        );
    }

    /**
     * Check the current version of survey settings, fix it oif needed
     * @param integer $surveyId
     * @return void
     */
    private function checkAndFixVersion($surveyId)
    {
        if ($this->get('SettingsVersion', 'Survey', $surveyId, 0) >= self::SettingsVersion) {
            return;
        }
        $SGQtoColumn = getQuestionInformation\helpers\surveyCodeHelper::getAllQuestions($surveyId);
        $surveyAttributeSettings = array(
            'surveyAttributes',
            'surveyAttributesPrimary',
            'surveyNeededValues',
            'surveyAttributesHideToUser'
        );
        foreach ($surveyAttributeSettings as $surveyAttributeSetting) {
            $currentSetting = $this->get($surveyAttributeSetting, 'Survey', $surveyId);
            if (empty($currentSetting)) {
                continue;
            }
            if (!settype($currentSetting, 'array')) {
                // WTF ? can not fix it
                continue;
            }
            $newSetting = array_intersect_key($SGQtoColumn, array_flip($currentSetting));
            $this->set($surveyAttributeSetting, $newSetting, 'Survey', $surveyId);
        }
        $this->set('SettingsVersion', self::SettingsVersion, 'Survey', $surveyId);
    }
}
