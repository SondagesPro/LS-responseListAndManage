<?php
/**
 * Responses List And Manage
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 1.3.0
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
class responseListAndManage extends PluginBase {

    protected $storage = 'DbStorage';
    static protected $description = 'A different way to manage response for a survey';
    static protected $name = 'responseListAndManage';

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
            'default' => 'default',
            'content' => 'To be updated',
        ),
    );

    protected $mailError;
    protected $mailDebug;
    //public $iSurveyId;
    
    /**
     * var array aRenderData
     */
    private $aRenderData = array();

    public function init() {
        $this->subscribe('newDirectRequest');
        $this->subscribe('beforeSurveySettings');
        //~ $this->subscribe('newSurveySettings');
        $this->subscribe('beforeToolsMenuRender');
        /* API dependant */
        $this->subscribe('getPluginTwigPath');

        /* Need some event in iframe survey */
        $this->subscribe('beforeSurveyPage');
        /* Replace token by the valid one before beforeSurveyPage @todo */
        $this->subscribe('beforeControllerAction');

        /* Need for own language system */
        $this->subscribe('afterPluginLoad');
    }

    /**
     * @see event
     */
    public function beforeSurveyPage()
    {
        if(!$this->_isUsable()) {
            return;
        }
        if(Yii::app()->session['responseListAndManage'] != $this->getEvent()->get('surveyId')) {
            return;
        }
        $surveyId = $this->getEvent()->get('surveyId');
        $currentSrid = isset($_SESSION['survey_'.$surveyId]['srid']) ? $_SESSION['survey_'.$surveyId]['srid'] : null;
        App()->getClientScript()->registerScriptFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/surveymanaging/surveymanaging.js'),CClientScript::POS_BEGIN);

        if(Yii::app()->getRequest()->getParam("saveall")) {
            App()->getClientScript()->registerScript("justsaved","autoclose();\n",CClientScript::POS_END);
            if($currentSrid) {
                $oSurvey = Survey::model()->findByPk($surveyId);
                if($oSurvey->active == "Y") {
                    $step = isset($_SESSION['survey_'.$surveyId]['step']) ? $_SESSION['survey_'.$surveyId]['step'] : 0;
                    LimeExpressionManager::JumpTo($step, false);
                    $oResponse = SurveyDynamic::model($surveyId)->findByPk($currentSrid);
                    $oResponse->lastpage = $step; // Or restart at 1st page ?
                    // Save must force always to not submitted (draft)
                    $oResponse->submitdate = null;
                    $oResponse->save();
                }
                killSurveySession($surveyId);
                \reloadAnyResponse\models\surveySession::model()->deleteByPk(array('sid'=>$surveyId,'srid'=>$currentSrid));
                if(Yii::getPathOfAlias('renderMessage')) {
                    \renderMessage\messageHelper::renderAlert($this->_translate("Your responses was saved with success, you can close this windows."));
                }
            }
        }
        if(Yii::app()->getRequest()->getParam("clearall")=="clearall" && Yii::app()->getRequest()->getParam("confirm-clearall")) {
            App()->getClientScript()->registerScript("justsaved","autoclose();\n",CClientScript::POS_END);
            \reloadAnyResponse\models\surveySession::model()->deleteByPk(array('sid'=>$surveyId,'srid'=>$currentSrid));
        }

    }
    /**
     * Add some views for this and other plugin
     */
    public function getPluginTwigPath()
    {
        $viewPath = dirname(__FILE__)."/twig";
        $this->getEvent()->append('add', array($viewPath));
    }

    /** @inheritdoc **/
    public function beforeSurveySettings()
    {
        if(!$this->_isUsable()) {
            return;
        }
        /* @Todo move this to own page */
        $oEvent = $this->getEvent();
        $iSurveyId = $this->getEvent()->get('survey');
        $accesUrl = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid'=>$iSurveyId));
        $managementUrl = Yii::app()->createUrl('admin/pluginhelper',
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
                'link'=>array(
                    'type'=>'info',
                    'content'=> CHtml::link($this->_translate("Access to responses listing"),$accesUrl,array("target"=>'_blank','class'=>'btn btn-block btn-default btn-lg')),
                ),
                'linkManagement'=>array(
                    'type'=>'info',
                    'content'=> CHtml::link($this->_translate("Manage settings of responses listing"),$accesUrl,array("target"=>'_blank','class'=>'btn btn-block btn-default btn-lg')),
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
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        $aMenuItem = array(
            'label' => $this->_translate('Response listing settings'),
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

        $aMenuItem = array(
            'label' => $this->_translate('Response listing'),
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

    /**
     * Main function to replace surveySetting
     * @param int $surveyId Survey id
     *
     * @return string
     */
    public function actionSettings($surveyId)
    {
        $oSurvey=Survey::model()->findByPk($surveyId);
        if(!$oSurvey) {
            throw new CHttpException(404,gT("This survey does not seem to exist."));
        }
        if(!Permission::model()->hasSurveyPermission($surveyId,'surveysettings','update')){
            throw new CHttpException(403);
        }
        if(App()->getRequest()->getPost('save'.get_class($this))) {
            // Adding save part
            $settings = array(
                'tokenAttributes','surveyAttributes','surveyAttributesPrimary',
                'tokenAttributesHideToUser','surveyAttributesHideToUser',
                'tokenAttributeGroup', 'tokenAttributeGroupManager', 'tokenAttributeGroupWhole',
                'allowSee','allowEdit','allowDelete', 'allowAdd'
            );
            foreach($settings as $setting) {
                $this->set($setting, App()->getRequest()->getPost($setting), 'Survey', $surveyId);
            }
            if(App()->getRequest()->getPost('save'.get_class($this)=='redirect')) {
                Yii::app()->getController()->redirect(Yii::app()->getController()->createUrl('admin/survey',array('sa'=>'view','surveyid'=>$surveyId)));
            }
            $languageSettings = array('description');
            foreach($languageSettings as $setting) {
                $finalSettings = array();
                foreach($oSurvey->getAllLanguages() as $language) {
                    $finalSettings[$language] = App()->getRequest()->getPost($setting.'_'.$language);
                }
                $this->set($setting, $finalSettings, 'Survey', $surveyId);
            }
        }
        $stateInfo = "<ul class='list'>";
        if($this->_allowTokenLink($oSurvey)) {
            $stateInfo .= CHtml::tag("li",array("class"=>'text-success'),$this->_translate("Token link and creation work in managing."));
        } else {
            $stateInfo .= CHtml::tag("li",array("class"=>'text-warning'),$this->_translate("No Token link and creation can be done in managing. Survey is anonymous or token table didn‘t exist."));
        }
        if($this->_allowMultipleResponse($oSurvey)) {
            $stateInfo .= CHtml::tag("li",array("class"=>'text-success'),$this->_translate("You can create new response for all token."));
        } else {
            $stateInfo .= CHtml::tag("li",array("class"=>'text-warning'),$this->_translate("You can not create new response for all token. Only one response is available for each token (see participation setting panel)."));
        }
        $stateInfo .= "</ul>";
        $surveyColumnsInformation = new \getQuestionInformation\helpers\surveyColumnsInformation($surveyId,App()->getLanguage());
        $aQuestionList = $surveyColumnsInformation->allQuestionListData();
        $accesUrl = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid'=>$surveyId));
        $aSettings[$this->_translate('Response Management')] = array(
            'link'=>array(
                'type'=>'info',
                'content'=> CHtml::link($this->_translate("Link to response alternate management"),$accesUrl,array("target"=>'_blank','class'=>'btn btn-block btn-default btn-lg')),
            ),
            'infoList' => array(
                'type'=>'info',
                'content'=> CHtml::tag("div",array('class'=>'well well-sm'),$stateInfo),
            ),
        );
        $aSettings[$this->_translate('Response Management table')] = array(
            'tokenAttributes' => array(
                'type'=>'select',
                'label'=>$this->_translate('Token attributes to show in management'),
                'options'=>$this->_getTokensAttributeList($surveyId,'tokens.'),
                'htmlOptions'=>array(
                    'multiple'=>true,
                    'placeholder'=>gT("All"),
                    'unselectValue'=>"",
                ),
                'selectOptions'=>array(
                    'placeholder'=>gT("All"),
                ),
                'current'=>$this->get('tokenAttributes','Survey',$surveyId)
            ),
            'surveyAttributes' => array(
                'type'=>'select',
                'label'=>$this->_translate('Survey columns to be show in management'),
                'options'=>$aQuestionList['data'],
                'htmlOptions'=>array(
                    'multiple'=>true,
                    'placeholder'=>gT("All"),
                    'unselectValue'=>"",
                    'options'=>$aQuestionList['options'], // In dropdown, but not in select2
                ),
                'selectOptions'=>array(
                    'placeholder'=>gT("All"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class'=>'select2-withover ',
                ),
                'current'=>$this->get('surveyAttributes','Survey',$surveyId)
            ),
            'surveyAttributesPrimary' => array(
                'type'=>'select',
                'label'=> $this->_translate('Survey columns to be show at first'),
                'help' => $this->_translate('This question are shown at first, just after the id of the reponse.'),
                'options'=>$aQuestionList['data'],
                'htmlOptions'=>array(
                    'multiple'=>true,
                    'placeholder'=>gT("None"),
                    'unselectValue'=>"",
                    'options'=>$aQuestionList['options'], // In dropdown, but not in select2
                ),
                'selectOptions'=>array(
                    'placeholder'=>gT("None"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class'=>'select2-withover ',
                ),
                'current'=>$this->get('surveyAttributesPrimary','Survey',$surveyId)
            ),
            'tokenAttributesHideToUser' => array(
                'type'=>'select',
                'label'=> $this->_translate('Token columns to be hidden to user (include group administrator)'),
                'help' => $this->_translate('This column are shown only to LimeSurvey administrator.'),
                'options'=>$this->_getTokensAttributeList($surveyId,'tokens.'),
                'htmlOptions'=>array(
                    'multiple'=>true,
                    'placeholder'=>gT("None"),
                    'unselectValue'=>"",
                ),
                'selectOptions'=>array(
                    'placeholder'=>gT("None"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class'=>'select2-withover ',
                ),
                'current'=>$this->get('tokenAttributesHideToUser','Survey',$surveyId)
            ),
            'surveyAttributesHideToUser' => array(
                'type'=>'select',
                'label'=> $this->_translate('Survey columns to be hidden to user (include group administrator)'),
                'help' => $this->_translate('This column are shown only to LimeSurvey administrator.'),
                'options'=>$aQuestionList['data'],
                'htmlOptions'=>array(
                    'multiple'=>true,
                    'placeholder'=>gT("None"),
                    'unselectValue'=>"",
                    'options'=>$aQuestionList['options'], // In dropdown, but not in select2
                ),
                'selectOptions'=>array(
                    'placeholder'=>gT("None"),
                    //~ 'templateResult'=>"formatQuestion",
                ),
                'controlOptions' => array(
                    'class'=>'select2-withover ',
                ),
                'current'=>$this->get('surveyAttributesHideToUser','Survey',$surveyId)
            ),
        );
        $aDescription = array();
        $aDescriptionCurrent = $this->get('description','Survey',$surveyId);
        $languageData = getLanguageData(false,Yii::app()->getLanguage());
        foreach($oSurvey->getAllLanguages() as $language) {
            $aDescription['description_'.$language] = array(
                'type' => 'text',
                'label' => sprintf($this->_translate("In %s language (%s)"),$languageData[$language]['description'],$languageData[$language]['nativedescription']),
                'current' => (isset($aDescriptionCurrent[$language]) ? $aDescriptionCurrent[$language] : ""),
            );
        }
        $aSettings[$this->_translate('Description and helper for survey listing')] = $aDescription;
        $aSettings[$this->_translate('Response Management token attribute usage')] = array(
            'tokenAttributeGroup' => array(
                'type'=>'select',
                'label'=>$this->_translate('Token attributes for group'),
                'options'=>$this->_getTokensAttributeList($surveyId,''),
                'htmlOptions'=>array(
                    'empty'=>$this->_translate("None"),
                ),
                'current'=>$this->get('tokenAttributeGroup','Survey',$surveyId)
            ),
            'tokenAttributeGroupManager' => array(
                'type'=>'select',
                'label'=>$this->_translate('Token attributes for group manager'),
                'options'=>$this->_getTokensAttributeList($surveyId,''),
                'htmlOptions'=>array(
                    'empty'=>$this->_translate("None"),
                ),
                'current'=>$this->get('tokenAttributeGroupManager','Survey',$surveyId)
            ),
            //~ 'tokenAttributeGroupWhole' => array(
                //~ 'type'=>'boolean',
                //~ 'label'=>$this->_translate('User of group can see and manage all group response'),
                //~ 'help'=>$this->_translate('Else only group manager can manage other group response.'),
                //~ 'current'=>$this->get('tokenAttributeGroupWhole','Survey',$surveyId,1)
            //~ ),
        );
        $aSettings[$this->_translate('Response Management access and right')] = array(
            'infoRights' => array(
                'type'=>'info',
                'content'=>CHtml::tag("ul",array('class'=>'well well-sm list-unstyled'),
                    CHtml::tag("li",array(),$this->_translate("Currently, for LimeSurvey admin user : only response access was used. Token access are not tested.")) .
                    CHtml::tag("li",array(),$this->_translate("For user, except for No : they always have same rights than other, for example if you allow delete to admin user, an user with token can delete his response with token.")) .
                    CHtml::tag("li",array(),$this->_translate("To disable access for user with token you can set this settings to No or only for LimeSurvey admin.")) .
                    ""
                ),
            ),
            'allowSee' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow view response of group'),
                'options'=>array(
                    'limesurvey'=>gT("Only for LimeSurvey administrator (According to LimeSurvey Permission)"),
                    'admin'=>gT("For group administrator and LimeSurvey administrator"),
                    'all'=>gT("All (user with a valid token included)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('You can disable all access with token here.'),
                'current'=>$this->get('allowSee','Survey',$surveyId,'all')
            ),
            'allowEdit' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow edit response of group'),
                'options'=>array(
                    'limesurvey'=>gT("Only for LimeSurvey administrator (According to LimeSurvey Permission)"),
                    'admin'=>gT("For group administrator and LimeSurvey administrator"),
                    'all'=>gT("All (user with a valid token included)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Except with No, user with token can always edit his response.'),
                'current'=>$this->get('allowEdit','Survey',$surveyId,'admin')
            ),
            'allowDelete' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow deletion of response'),
                'options'=>array(
                    'limesurvey'=>gT("Only for LimeSurvey administrator (According to LimeSurvey Permission)"),
                    'admin'=>gT("For group administrator and LimeSurvey administrator"),
                    'all'=>gT("All (user with a valid token included)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Except with No, user with token can always delete his response.'),
                'current'=>$this->get('allowDelete','Survey',$surveyId,'admin')
            ),
            'allowAdd' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow add response'),
                'options'=>array(
                    'limesurvey'=>gT("Only for LimeSurvey administrator (According to LimeSurvey Permission)"),
                    'admin'=>gT("For group administrator and LimeSurvey administrator"),
                    'all'=>gT("All (user with a valid token included)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Related to all tokens in group for user. User can always add new response if survey settings allow him to create a new response.'),
                'current'=>$this->get('allowAdd','Survey',$surveyId,'admin')
            ),
        );

        $aData['pluginClass']=get_class($this);
        $aData['surveyId']=$surveyId;
        $aData['title'] = "";
        $aData['warningString'] = null;
        $aData['aSettings']=$aSettings;
        $aData['assetUrl']=Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/settings');
        $content = $this->renderPartial('settings', $aData, true);
        return $content;
    }
    /**
    * @see newSurveySettings
    */
    public function newSurveySettings()
    {
        if(!$this->_isUsable()) {
            return;
        }
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }

    /** @inheritdoc **/
    public function newDirectRequest()
    {
        if($this->getEvent()->get('target') != get_class($this)) {
            return;
        }
        if(!$this->_isUsable()) {
            // throw error ?
            return;
        }
        $this->_setConfig();
        $surveyId = App()->getRequest()->getQuery('sid');
        if($surveyId && App()->getRequest()->getQuery('delete') ) {
            $this->_deleteResponseSurvey($surveyId,App()->getRequest()->getQuery('delete'));
            App()->end(); // Not needed but more clear
        }
        if($surveyId && App()->getRequest()->getQuery('action')=='adduser' ) {
            $this->_addUserForSurvey($surveyId);
            App()->end(); // Not needed but more clear
        }
        if($surveyId && App()->getRequest()->getQuery('action')=='download' ) {
            $this->_downloadFile($surveyId);
            App()->end(); // Not needed but more clear
        }
        if($surveyId) {
            $this->_doSurvey($surveyId);
            App()->end(); // Not needed but more clear
        }
        $this->_doListSurveys();
    }

    /**
     * Download a file by manager
     */
    private function _downloadFile($surveyId) {
        $srid = Yii::app()->getRequest()->getParam('srid');
        $qid = Yii::app()->getRequest()->getParam('qid');
        $fileIndex = Yii::app()->getRequest()->getParam('fileindex');
        $oSurvey = Survey::model()->findByPk($surveyId);
        $oResponse = Response::model($surveyId)->findByPk($srid);
        if(!$oResponse) {
            throw new CHttpException(404);
        }
        if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
            if($this->_allowTokenLink($oSurvey)) {
                throw new CHttpException(403);
            }
            $currentToken = $this->_getCurrentToken($surveyId);
            if($currentToken) {
                throw new CHttpException(403);
            }
            if($currentToken != $oResponse->token) {
                throw new CHttpException(403);
            }
        }
        $aQuestionFiles = $oResponse->getFiles($qid);
        if (empty($aQuestionFiles[$fileIndex])) {
            throw new CHttpException(404,gT("Sorry, this file was not found."));
        }
        $aFile = $aQuestionFiles[$fileIndex];
        $sFileRealName = Yii::app()->getConfig('uploaddir')."/surveys/".$surveyId."/files/".$aFile['filename'];
        if(!file_exists($sFileRealName)) {
            throw new CHttpException(404,gT("Sorry, this file was not found."));
        }
        $mimeType = CFileHelper::getMimeType($sFileRealName, null, false);
        if (is_null($mimeType)) {
            $mimeType = "application/octet-stream";
        }
        @ob_clean();
        header('Content-Description: File Transfer');
        header('Content-Type: '.$mimeType);
        header('Content-Disposition: attachment; filename="'.sanitize_filename(rawurldecode($aFile['name'])).'"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: '.filesize($sFileRealName));
        readfile($sFileRealName);
        exit;
    }
    /**
     * Managing access to survey
     */
    private function _doSurvey($surveyId)
    {
        $this->aRenderData['surveyId'] = $surveyId;
        Yii::import('application.helpers.viewHelper');
        $oSurvey=Survey::model()->findByPk($surveyId);
        /* Must fix rights */
        $userHaveRight = false;
        if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
            if(App()->getRequest()->isPostRequest && App()->getRequest()->getPost('login_submit')) {
                /* redirect to avoid CRSF when reload */
                Yii::app()->getController()->redirect(array("plugins/direct", 'plugin' => get_class(),'sid'=>$surveyId));
            }
            $userHaveRight = true;
        }
        if(!$userHaveRight && $this->_allowTokenLink($oSurvey) && App()->getRequest()->getPost('token')) {
            $currentToken = App()->getRequest()->getPost('token');
            $oToken = Token::model($surveyId)->findByToken($currentToken);
            if($oToken) {
                $this->_setCurrentToken($surveyId,$currentToken);
                /* redirect to avoid CRSF when reload */
                Yii::app()->getController()->redirect(array("plugins/direct", 'plugin' => get_class(),'sid'=>$surveyId));
            } else {
                $this->aRenderData['error'] = $this->_translate("This code is invalid.");
            }
        }
        $currentToken = $this->_getCurrentToken($surveyId);
        if(!$userHaveRight && $this->_allowTokenLink($oSurvey) && $currentToken) {
            $userHaveRight = true;
            $this->_setCurrentToken($surveyId,$currentToken);
            Yii::app()->user->setState('disableTokenPermission',true);
        }
        
        if(!$userHaveRight) {
            if($this->_allowTokenLink($oSurvey) && !Yii::app()->getRequest()->getParam('admin')) {
                $this->_showTokenForm($surveyId);
            } else {
                $this->_doLogin();
                //throw new CHttpException(401,$this->_translate("Sorry, no access on this survey."));
            }
        }
        $oSurvey = Survey::model()->findByPk($surveyId);
        $language = App()->getlanguage();
        if(!in_array($language,$oSurvey->getAllLanguages())) {
            $language = $oSurvey->language;
        }
        App()->setLanguage($language);
        $this->aRenderData['aSurveyInfo'] = getSurveyInfo($surveyId, $language);

        Yii::app()->session['responseListAndManage'] = $surveyId;
        Yii::import(get_class($this).'.models.ResponseExtended');
        $mResponse = ResponseExtended::model($surveyId);
        $mResponse->setScenario('search');
        $filters = Yii::app()->request->getParam('ResponseExtended');
        if (!empty($filters)) {
            $mResponse->setAttributes($filters, false);
            if(!empty($filters['completed'])) {
                $mResponse->setAttribute('completed', $filters['completed']);
            }
        }
        $tokensFilter = Yii::app()->request->getParam('TokenDynamic');
        if (!empty($tokensFilter)) {
            $mResponse->setTokenAttributes($tokensFilter);
        }
        /* Access with token */
        $isManager = false;
        $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId);
        $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId);

        /* Get the final allowed according to current token) */
        $allowSee = $allowEdit = $allowDelete = $allowAdd = false;
        $settingAllowSee = $this->get('allowSee','Survey',$surveyId,'all');
        $settingAllowEdit = $this->get('allowEdit','Survey',$surveyId,'admin');
        $settingAllowDelete = $this->get('allowDelete','Survey',$surveyId,'admin');
        $settingAllowAdd = $this->get('allowAdd','Survey',$surveyId,'admin');
        $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId,null);
        $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId,null);
        $tokenGroup = null;
        $tokenAdmin = null;
        $isManager = false;
        /* Set the default according to Permission */
        if($this->_isLsAdmin()) { /* When testing is done move this after */
            $allowSee = $settingAllowSee && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read');
            $allowEdit = $settingAllowEdit && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'update');
            $allowDelete = $settingAllowDelete && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'delete');
            $allowAdd = $settingAllowAdd && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create');
        }
        if($currentToken) {
            $aTokens = (array) $currentToken;
            $oToken = Token::model($surveyId)->findByToken($currentToken);
            $tokenGroup = (!empty($tokenAttributeGroup) && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : null;
            $tokenAdmin = (!empty($tokenAttributeGroupManager) && !empty($oToken->$tokenAttributeGroupManager)) ? $oToken->$tokenAttributeGroupManager : null;
            $isManager = ((bool) $tokenAdmin) && trim($tokenAdmin) !== '0';
            $allowSee = ($settingAllowSee == 'all') || ($settingAllowSee == 'admin' && $isManager);
            $allowEdit = $allowSee && (($settingAllowEdit == 'all') || ($settingAllowEdit == 'admin' && $isManager));
            $allowDelete = ($settingAllowDelete == 'all') || ($settingAllowDelete == 'admin' && $isManager);
            $allowAdd = ($settingAllowAdd == 'all') || ($settingAllowAdd == 'admin' && $isManager); // all add with any token (show token list)
            $oTokenGroup = Token::model($surveyId)->findAll("token = :token",array(":token"=>$currentToken));
            if($tokenGroup) {
                $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup."= :group",array(":group"=>$tokenGroup));
                if($allowSee) {
                    $aTokens = CHtml::listData($oTokenGroup,'token','token');
                }
            }
            $mResponse->setAttribute('token', $aTokens);

            if(!$allowSee && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
                throw new CHttpException(403, $this->_translate('You are not allowed to use reponse management with this token.'));
            }
        }
        Yii::app()->user->setState('pageSize',intval(Yii::app()->request->getParam('pageSize',Yii::app()->user->getState('pageSize',50))));
        /* Add a new */
        $tokenList = null;
        $singleToken = null;
        if($this->_allowTokenLink($oSurvey)) {
            if(Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create')) {
                if($this->_allowMultipleResponse($oSurvey)) {
                    $oToken = Token::model($surveyId)->findAll("token is not null and token <> ''");
                } else {
                    $oToken = Token::model($surveyId)->with('responses')->findAll("t.token is not null and t.token <> '' and responses.id is null");
                }
                if(count($oToken) == 1) {
                    $singleToken = $oToken[0]->token;
                }
                $tokenList = CHtml::listData($oToken,'token',function($oToken){
                    return CHtml::encode(trim($oToken->firstname.' '.$oToken->lastname.' ('.$oToken->token.')'));
                },
                $tokenAttributeGroup);
            }
            if($currentToken) {
                if(is_array($currentToken)) {
                    $currentToken = array_shift(array_values($currentToken));
                }
                $tokenList = array($currentToken=>$currentToken);
                if($allowAdd) { /* Adding for all group */
                    $criteria = new CDbCriteria();
                    if($this->_allowMultipleResponse($oSurvey)) {
                        $criteria->condition = "t.token is not null and t.token <> ''";
                    } else {
                        $criteria->condition = "t.token is not null and t.token <> '' and responses.id is null";
                    }
                    $criteria->addInCondition('t.token',$aTokens);
                    $oToken = Token::model($surveyId)->with('responses')->findAll($criteria);
                    if(count($oToken) == 1) {
                        $singleToken = $oToken[0]->token;
                    }
                    $tokenList = CHtml::listData($oTokenGroup,'token',function($oToken){
                        return CHtml::encode(trim($oToken->firstname.' '.$oToken->lastname.' ('.$oToken->token.')'));
                    },
                    $tokenAttributeGroup);
                }
            }
            if(!empty($tokenList) && !empty($tokenList[''])) {
                $emptyTokenGroup = $tokenList[''];
                unset($tokenList['']);
                $tokenList = array_merge($emptyTokenGroup,$tokenList);
            }
        }
        
        $addNew ='';
        if(Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create') && !$this->_surveyHasTokens($oSurvey)) {
            $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                array("survey/index",'sid'=>$surveyId,'newtest'=>"Y"),
                array('class'=>'btn btn-default btn-sm  addnew')
            );
        }
        if($allowAdd && $singleToken) {
            if($this->_allowMultipleResponse($oSurvey)) {
                $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                    array("survey/index",'sid'=>$surveyId,'newtest'=>"Y",'srid'=>'new','token'=>$singleToken),
                    array('class'=>'btn btn-default btn-sm addnew')
                );
            }
        }
        if(!$allowAdd && $currentToken) {
            if($this->_allowMultipleResponse($oSurvey)) {
                $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                    array("survey/index",'sid'=>$surveyId,'newtest'=>"Y",'srid'=>'new','token'=>$singleToken),
                    array('class'=>'btn btn-default btn-sm addnew')
                );
            }
        }
        if($allowAdd && !empty($tokenList) && !$singleToken) {
            $addNew  = CHtml::beginForm(array("survey/index"),'get',array('class'=>"form-inline"));
            $addNew .= CHtml::hiddenField('sid',$surveyId);
            $addNew .= CHtml::hiddenField('newtest',"Y");
            //~ $addNew .= '<div class="form-group"><div class="input-group">';
            $addNew .= CHtml::dropDownList('token',$currentToken,$tokenList,array('class'=>'form-control input-sm','empty'=>gT("Please choose...")));
            $addNew .= CHtml::htmlButton("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                array("type"=>'button','name'=>'srid','value'=>'new','class'=>'btn btn-default btn-sm addnew')
            );
            //~ $addNew .= '</div></div>';

            $addNew .= CHtml::endForm();
        }

        $this->aRenderData['addNew'] = $addNew;
        /* Contruct column */
        /* Put the button here, more easy */
        $updateButtonUrl = "";
        if(Permission::model()->hasSurveyPermission($surveyId, 'responses', 'update')) {
            $updateButtonUrl = 'App()->createUrl("survey/index",array("sid"=>'.$surveyId.',"srid"=>$data["id"],"newtest"=>"Y"))';
        }
        if($this->_allowTokenLink($oSurvey)) {
            $updateButtonUrl = 'App()->createUrl("survey/index",array("sid"=>'.$surveyId.',"srid"=>$data["id"],"newtest"=>"Y"))';
            if($currentToken) {
                if( $allowEdit ) {
                    $updateButtonUrl = 'App()->createUrl("survey/index",array("sid"=>'.$surveyId.',"token"=>"'.$currentToken.'","srid"=>$data["id"],"newtest"=>"Y"))';
                } elseif($settingAllowEdit) {
                    $updateButtonUrl = '("'.$currentToken.'" == $data["token"]) ? App()->createUrl("survey/index",array("sid"=>'.$surveyId.',"token"=>"'.$currentToken.'","srid"=>$data["id"],"newtest"=>"Y")) : null';
                } else {
                    $updateButtonUrl = '';
                }
            }
        }
        $deleteButtonUrl = "";
        if(Permission::model()->hasSurveyPermission($surveyId, 'responses', 'delete')) {
            $deleteButtonUrl = 'App()->createUrl("plugins/direct",array("plugin"=>"'.get_class().'","sid"=>'.$surveyId.',"delete"=>$data["id"]))';
        }
        if($this->_allowTokenLink($oSurvey) && $oSurvey->anonymized != 'Y') {
            $deleteButtonUrl = 'App()->createUrl("plugins/direct",array("plugin"=>"'.get_class().'","sid"=>'.$surveyId.',"token"=>$data["token"],"delete"=>$data["id"]))';
            if($currentToken) {
                if( $allowDelete ) {
                    $deleteButtonUrl = 'App()->createUrl("plugins/direct",array("plugin"=>"'.get_class().'","sid"=>'.$surveyId.',"token"=>$data["token"],"delete"=>$data["id"]))';
                } elseif($settingAllowDelete) {
                    $deleteButtonUrl = '("'.$currentToken.'" == $data["token"]) ? App()->createUrl("plugins/direct",array("plugin"=>"'.get_class().'","sid"=>'.$surveyId.',"token"=>$data["token"],"delete"=>$data["id"])) : null';
                } else {
                    $deleteButtonUrl = "";
                }
            }
        }
        $aColumns= array(
            'buttons'=>array(
                'htmlOptions' => array('nowrap'=>'nowrap'),
                'class'=>'bootstrap.widgets.TbButtonColumn',
                'template'=>'{update}{delete}',
                'updateButtonUrl'=>$updateButtonUrl,
                'deleteButtonUrl'=>$deleteButtonUrl,
            ),
        );
        $disableTokenPermission = (bool) $currentToken;
        
        $aColumns = array_merge($aColumns,$mResponse->getGridColumns($disableTokenPermission));
        /* Get the selected columns only */
        $tokenAttributes = $this->get('tokenAttributes','Survey',$surveyId);
        $surveyAttributes = $this->get('surveyAttributes','Survey',$surveyId);
        $surveyAttributesPrimary = $this->get('surveyAttributesPrimary','Survey',$surveyId);
        $filteredArr = array();
        $aRestrictedColumns = array();
        if(empty($tokenAttributes)) {
            $tokenAttributes = array_keys($this->_getTokensAttributeList($surveyId,'tokens.'));
        }
        /* remove tokens.token if user didn't have right to edit */
        if($currentToken && !$allowEdit) {
            /* unset by value */
            $tokenAttributes = array_values(array_diff($tokenAttributes, array('tokens.token')));
        }
        if(empty($surveyAttributes)) {
            $surveyAttributes = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionListData($surveyId,App()->getLanguage());
            $surveyAttributes = array_keys($surveyAttributes['data']);
        }
        if(is_string($surveyAttributes)) {
            $surveyAttributes = array($surveyAttributes);
        }
        if(empty($surveyAttributesPrimary)) {
            $surveyAttributesPrimary = array();
        }
        if(is_string($surveyAttributesPrimary)) {
            $surveyAttributesPrimary = array($surveyAttributesPrimary);
        }

        $forcedColumns = array('buttons','id');
        $aRestrictedColumns = array_merge($forcedColumns,$tokenAttributes,$surveyAttributes,$surveyAttributesPrimary);
        $mResponse->setRestrictedColumns($aRestrictedColumns);
        if($currentToken) {
            $tokenAttributesHideToUser = $this->get('tokenAttributesHideToUser','Survey',$surveyId);
            if(!empty($tokenAttributesHideToUser)) {
                $aRestrictedColumns = array_diff($aRestrictedColumns,$tokenAttributesHideToUser);
            }
            $surveyAttributesHideToUser = $this->get('surveyAttributesHideToUser','Survey',$surveyId);
            if(!empty($surveyAttributesHideToUser)) {
                $aRestrictedColumns = array_diff($aRestrictedColumns,$surveyAttributesHideToUser);
                $surveyAttributesPrimary = array_diff($surveyAttributesPrimary,$surveyAttributesHideToUser);
            }
        }
        $aColumns = array_intersect_key( $aColumns, array_flip( $aRestrictedColumns ) );
        if(!empty($surveyAttributesPrimary)) {
            $surveyAttributesPrimary=array_reverse($surveyAttributesPrimary);// We add at inverse, the last must be first
            $surveyAttributesPrimary=array_merge($surveyAttributesPrimary,array_reverse($forcedColumns)); // And need buttons and id at start
            foreach($surveyAttributesPrimary as $columnKey) {
                if(isset($aColumns[$columnKey])) {
                    $aColumnPrimary = $aColumns[$columnKey];
                    unset($aColumns[$columnKey]); // Is this needed ?
                    array_unshift($aColumns,$aColumnPrimary);
                }
            }
        }

        $this->aRenderData['allowAddUser'] = $this->_allowTokenLink($oSurvey) && ($isManager || Permission::model()->hasSurveyPermission($surveyId, 'token', 'create'));
        $this->aRenderData['addUser'] = array();
        $this->aRenderData['addUserButton'] = '';
        if($this->aRenderData['allowAddUser']) {
            $this->aRenderData['addUserButton'] = CHtml::htmlButton("<i class='fa fa-user-plus ' aria-hidden='true'></i>".$this->_translate("Create an new user"),
                array("type"=>'button','name'=>'adduser','value'=>'new','class'=>'btn btn-default btn-sm addnewuser')
            );
            $this->aRenderData['addUser'] = $this->_addUserDataForm($surveyId,$currentToken);
        }
        if(empty($this->aRenderData['lang'])) {
            $this->aRenderData['lang'] = array();
        }
        $this->aRenderData['lang']['Close'] = gT("Close");
        //$this->aRenderData['lang']['Delete'] = $this->_translate("Delete");
        if($oSurvey->allowprev == "Y") {
            $this->aRenderData['lang']['Previous'] = gT("Previous");
        }
        if($oSurvey->allowsave == "Y") { // We don't need to test token, we don't shown default save part …
            $this->aRenderData['lang']['Save'] = gT("Save");
        }
        if($oSurvey->format!="A") {
            $this->aRenderData['lang']['Next'] = gT("Next");
        }
        $this->aRenderData['lang']['Submit'] = gT("Submit");
        $this->aRenderData['model'] = $mResponse;
        // Add comment block
        $aDescriptionCurrent = $this->get('description','Survey',$surveyId);
        $this->aRenderData['description'] = isset($aDescriptionCurrent[Yii::app()->getLanguage()]) ? $aDescriptionCurrent[Yii::app()->getLanguage()] : "";
        $this->aRenderData['columns'] = $aColumns;
        $this->_render('responses');
    }

    private function _deleteResponseSurvey($surveyId,$srid)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        if(!$oSurvey) {
            throw new CHttpException(404, $this->_translate("Invalid survey id."));
        }
        $oResponse = Response::model($surveyId)->findByPk($srid);
        if(!$oResponse) {
            throw new CHttpException(404, $this->_translate("Invalid response id."));
        }
        //echo "Work in progress";
        $allowed = false;
        /* Is an admin */
        if($this->get('allowDelete','Survey',$surveyId,'admin') && Permission::model()->hasSurveyPermission($surveyId, 'response', 'delete')) {
            $allowed = true;
        }
        /* Is not an admin */
        if(!$allowed && $this->get('allowDelete','Survey',$surveyId,'admin') && $this->_surveyHasTokens($oSurvey)) {
            $whoIsAllowed = $this->get('allowDelete','Survey',$surveyId,'admin');
            $token = App()->getRequest()->getQuery('token');
            $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId,null);
            $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId,null);
            $tokenGroup = null;
            $tokenAdmin = null;
            if($token && $this->_allowTokenLink($oSurvey)) {
                $oToken =  Token::model($surveyId)->findByToken($token);
                $tokenGroup = (!empty($tokenAttributeGroup) && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : null;
                $tokenAdmin = (!empty($tokenAttributeGroupManager) && !empty($oToken->$tokenAttributeGroupManager)) ? $oToken->$tokenAttributeGroupManager : null;
            }
            $oResponse = Response::model($surveyId)->findByPk($srid);
            $responseToken = !(empty($oResponse->token)) ? $oResponse->token : null;
            if($responseToken == $token) { /* Always allow same token */
                $allowed = true;
            }
            if(!$allowed && !empty($tokenGroup)) {
                $oResponseToken =  Token::model($surveyId)->findByToken($responseToken);
                $oResponseTokenGroup = !(empty($oResponseToken->$tokenAttributeGroup)) ? $oResponseToken->$tokenAttributeGroup : null;
                if($this->get('allowDelete','Survey',$surveyId,'admin') == 'all' && $oResponseTokenGroup  == $tokenGroup) {
                    $allowed = true;
                }
                if($this->get('allowDelete','Survey',$surveyId,'admin') == 'admin' && $oResponseTokenGroup  == $tokenGroup && $tokenAdmin) {
                    $allowed = true;
                }
            }
        }
        if(!$allowed) {
            throw new CHttpException(401, $this->_translate('No right to delete this reponse.'));
        }
        if(!Response::model($surveyId)->deleteByPk($srid)) {
            throw new CHttpException(500, CHtml::errorSummary(Response::model($surveyId)));
        }
        return;
    }

    /**
     * Adding a token user to a survey
     */
    private function _addUserForSurvey($surveyId) {
        $oSurvey=Survey::model()->findByPk($surveyId);
        $aResult = array(
            'status' => null,
        );
        if(!$oSurvey) {
            throw new CHttpException(404, $this->_translate('Invalid survey id.'));
        }
        if(!$this->_allowTokenLink($oSurvey)) {
            throw new CHttpException(403, $this->_translate('Token creation is disable for this survey.'));
        }
        $token = App()->getRequest()->getParam('currenttoken');
        $tokenGroup = null;
        $tokenAdmin = null;
        $allowAdd = false;
        if($token) {
            $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId,null);
            $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId,null);
            $tokenGroup = (!empty($tokenAttributeGroup) && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : null;
            $tokenAdmin = (!empty($tokenAttributeGroupManager) && !empty($oToken->$tokenAttributeGroupManager)) ? $oToken->$tokenAttributeGroupManager : null;
            $isAdmin == ((bool) $tokenAdmin) && trim($tokenAdmin) !== '' && trim($tokenAdmin) !== '0';
            $settingAllowAdd = $this->get('allowAdd','Survey',$surveyId,'admin');
            $allowAdd = ($settingAllowAdd == 'all' || ($settingAllowAdd == 'admin' && $isAdmin));
        }
        
        if(!Permission::model()->hasSurveyPermission($surveyId, 'token', 'create')) {
            if(!$allowAdd) {
                throw new CHttpException(403, $this->_translate('No right to create new token in this survey.'));
            }
        }
        $oToken = Token::create($surveyId);
        $oToken->setAttributes(Yii::app()->getRequest()->getParam('tokenattribute'));
        if(!is_null($tokenGroup)) {
            $oToken->$tokenAttributeGroup = $tokenGroup;
            $oToken->$tokenAttributeGroupManager = '';
        }
        $resultToken = $oToken->save();
        if(!$resultToken) {
            $this->_returnJson(array(
                'status'=>'error',
                'html' => CHtml::errorSummary($oToken),
            ));
        }
        $oToken->generateToken();
        $oToken->save();

        if(!$this->_sendMail($surveyId,$oToken,App()->getRequest()->getParam('emailsubject'),App()->getRequest()->getParam('emailbody'))) {
            $html = sprintf($this->_translate("Token created but unable to send the email, token code is %s"),CHtml::tag('code',array(),$oToken->token));
            $html.= CHtml::tag("hr");
            $html.= CHtml::tag("strong",array('class'=>'block'),$this->_translate('Error return by mailer:'));
            $html.= CHtml::tag("div",array(),$this->mailError);
            $this->_returnJson(array(
                'status'=>'warning',
                'html' => $html,
            ));
        }
        $this->_returnJson(array('status'=>'success'));
    }

    /**
     * Managing list of Surveys
     */
    private function _doListSurveys()
    {
        $iAdminId = $this->_isLsAdmin();
        if($iAdminId) {
            $this->_showSurveyList();
        }
        $this->_doLogin();
    }

    /** @inheritdoc **/
    public function getPluginSettings($getValues=true)
    {
        if(Yii::app() instanceof CConsoleApplication) {
            return;
        }
        if(!$this->_isUsable()){
            $warningMessage = "";
            $haveGetQuestionInformation = Yii::getPathOfAlias('getQuestionInformation');
            if(!$haveGetQuestionInformation) {
                $warningMessage .= CHtml::tag("p",array(),sprintf($this->_translate("Unable to use this plugin, you need %s plugin."),CHtml::link("getQuestionInformation","https://gitlab.com/SondagesPro/coreAndTools/getQuestionInformation")));
            }
            $haveReloadAnyResponse = Yii::getPathOfAlias('reloadAnyResponse');
            if(!$haveReloadAnyResponse) {
                $warningMessage .= CHtml::tag("p",array(),sprintf($this->_translate("Unable to use this plugin, you need %s plugin."),CHtml::link("getQuestionInformation","https://gitlab.com/SondagesPro/coreAndTools/getQuestionInformation")));
            }
            $this->settings = array(
                'unable'=> array(
                    'type' => 'info',
                    'content' => CHtml::tag("div",
                        array('class'=>'alert alert-warning'),
                        $warningMessage
                    ),
                ),
            );
            return $this->settings;
        }
        $this->settings['template']['default'] = App()->getConfig('defaulttheme');
        $pluginSettings= parent::getPluginSettings($getValues);
        /* @todo : return if not needed */
        $accesUrl = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class()));
        $accesHtmlUrl = CHtml::link($accesUrl,$accesUrl);
        $pluginSettings['information']['content'] = sprintf($this->_translate("Access link for survey listing : %s."),$accesHtmlUrl);
        if(version_compare(Yii::app()->getConfig('versionnumber'),"3",">=")) {
            $oTemplates = TemplateConfiguration::model()->findAll(array(
                'condition'=>'sid IS NULL',
                'order'=>'template_name',
            ));
            $aTemplates = CHtml::listData($oTemplates,'template_name','template_name');
        }
        if(version_compare(Yii::app()->getConfig('versionnumber'),"3","<")) {
          $aTemplates = array_keys(Template::getTemplateList());
        }
        $pluginSettings['template'] = array_merge($pluginSettings['template'],array(
            'type' => 'select',
            'options'=>$aTemplates,
            'label'=> $this->_translate('Template to be used'),
        ));
        return $pluginSettings;
    }
    
    /**
     * Call plugin log and show login form if needed
     */
    private function _doLogin($surveyid=null)
    {
        $lang = App()->getRequest()->getParam('lang');
        if (empty($lang)) {
            $lang = App()->getConfig('defaultlang');
        }
        App()->setLanguage($lang);
        if(version_compare(Yii::app()->getConfig('versionnumber'),"3","<")) {
            App()->user->setReturnUrl(App()->request->requestUri);
            App()->getController()->redirect(array('/admin/authentication/sa/login'));
        }
        Yii::import('application.controllers.admin.authentication',1);
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
        if(empty($aResult['defaultAuth'])) {
            $aResult['defaultAuth'] = 'Authdb';
        }

        $aLangList = getLanguageDataRestricted(true);
        foreach ( $aLangList as $sLangKey => $aLanguage) {
            $languageData[$sLangKey] =  html_entity_decode($aLanguage['nativedescription'], ENT_NOQUOTES, 'UTF-8') . " - " . $aLanguage['description'];
        }
        $this->aRenderData['languageData'] = $languageData;
        $this->aRenderData['lang'] = $lang;
        $this->aRenderData['authPlugins'] = $aResult;
        if(App()->getRequest()->isPostRequest && App()->getRequest()->getPost('login_submit')) {
            //~ echo "<pre>AFTER";
            //~ print_r($aResult);
            //~ die("</pre>");
        }
        $pluginsContent = $aResult['pluginContent'];
        $this->aRenderData['authSelectPlugin'] = $aResult['defaultAuth'];
        if(Yii::app()->getRequest()->isPostRequest) {
            $this->aRenderData['authSelectPlugin'] = Yii::app()->getRequest()->getParam('authMethod');
        }
        $possibleAuthMethods = array();
        $pluginNames = array_keys($pluginsContent);
        foreach($pluginNames as $plugin) {
            $info = App()->getPluginManager()->getPluginInfo($plugin);
            $possibleAuthMethods[$plugin] = $info['pluginName'];
        }
        $this->aRenderData['authPluginsList'] = $possibleAuthMethods;
        $pluginContent = "";
        if(isset($pluginsContent[$this->aRenderData['authSelectPlugin']])) {
            $pluginBlockData = $pluginsContent[$this->aRenderData['authSelectPlugin']];
            $pluginContent = $pluginBlockData->getContent();
        }
        $this->aRenderData['pluginContent'] = $pluginContent;
        $this->aRenderData['summary'] = $aResult['summary'];
        $this->aRenderData['subtitle'] = gT('Log in');
        /* Bad hack … */
        //~ header("HTTP/1.1 401 Unauthorized");
        $this->_render('login');
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
        $oSurvey=Survey::model()->findByPk($surveyid);
        $this->aRenderData['languageData']=$oSurvey->getAllLanguages();
        if(!in_array($lang,$this->aRenderData['languageData'])) {
            $lang = $oSurvey->language;
        }
        App()->setLanguage($lang);
        $this->aRenderData['subtitle'] = gT("If you have been issued a token, please enter it in the box below and click continue.");
        $this->aRenderData['adminLoginUrl'] = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid'=>$surveyid,'admin'=>1));
        $this->_render('token');
    }
    /**
     * Show the survey listing
     */
    private function _showSurveyList()
    {
        $surveyModel = new \responseListAndManage\models\SurveyExtended('search');
        $this->aRenderData['surveyModel'] = $surveyModel;
        $dataProvider=new CActiveDataProvider($surveyModel, array(
            'criteria'=>array(
                'condition'=>"active='Y'",
                'with'=>'correct_relation_defaultlanguage',
            ),
        ));
        $this->aRenderData['dataProvider'] = $dataProvider;
        $this->_render('surveys');
    }

    /**
     * get the data form for adding an user
     */
    private function _addUserDataForm($surveyId,$currentToken) {
        $forcedGroup = null;
        $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId);
        $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId);
        if(!Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create')) {
            $oToken = Token::model($surveyId)->findByToken($currentToken);
            $forcedGroup = ($tokenAttributeGroup && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : "";
        }
        $addUser = array();
        $addUser['action'] = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid'=>$surveyId,'action'=>'adduser'));
        if(!Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create')) {
            $addUser['action'] = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid'=>$surveyId,'currenttoken'=>$currentToken,'action'=>'adduser'));
        }
        $oSurvey = Survey::model()->findByPk($surveyId);
        $aSurveyInfo = getSurveyInfo($surveyId, Yii::app()->getLanguage());
        $aAllAttributes = $aRegisterAttributes = $aSurveyInfo['attributedescriptions'];
        foreach ($aRegisterAttributes as $key=>$aRegisterAttribute) {
            if ($aRegisterAttribute['show_register'] != 'Y') {
                unset($aRegisterAttributes[$key]);
            } else {
                $aRegisterAttributes[$key]['caption'] = ($aSurveyInfo['attributecaptions'][$key] ? $aSurveyInfo['attributecaptions'][$key] : ($aRegisterAttribute['description'] ? $aRegisterAttribute['description'] : $key));
            }
        }
        unset($aRegisterAttributes[$tokenAttributeGroup]);
        unset($aRegisterAttributes[$tokenAttributeGroupManager]);
        $addUser['attributes'] = $aRegisterAttributes;
        $addUser["attributeGroup"] = null;
        $addUser["tokenAttributeGroupManager"] = null;
        if(is_null($forcedGroup)) {
            if($tokenAttributeGroup) {
                $addUser["attributeGroup"] = $aAllAttributes[$tokenAttributeGroup];
                $addUser["attributeGroup"]['attribute'] = $tokenAttributeGroup;
                $addUser["attributeGroup"]['caption'] = ($aSurveyInfo['attributecaptions'][$tokenAttributeGroup] ? $aSurveyInfo['attributecaptions'][$tokenAttributeGroup] : ($aAllAttributes[$tokenAttributeGroup]['description'] ? $aAllAttributes[$tokenAttributeGroup]['description'] : $this->_translate("Is a group manager")));
            }
            if($tokenAttributeGroupManager) {
                $addUser["tokenAttributeGroupManager"] = $aAllAttributes[$tokenAttributeGroup];
                $addUser["tokenAttributeGroupManager"]['attribute'] = $tokenAttributeGroup;
                $addUser["tokenAttributeGroupManager"]['caption'] = ($aSurveyInfo['attributecaptions'][$tokenAttributeGroupManager] ? $aSurveyInfo['attributecaptions'][$tokenAttributeGroupManager] : ($aAllAttributes[$tokenAttributeGroupManager]['description'] ? $aAllAttributes[$tokenAttributeGroupManager]['description'] : $this->_translate("Is a group manager")));
            }
        }
        $emailType = 'register';
        $addUser['email'] = array(
            'subject' => $aSurveyInfo['email_'.$emailType.'_subj'],
            'body' => str_replace("<br />","<br>",$aSurveyInfo['email_'.$emailType]),
            'help' => sprintf($this->_translate("You can use token information like %s or %s, %s was replaced by the url for managing response."),"{FIRSTNAME}","{ATTRIBUTE_1}","{SURVEYURL}"),
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
    private function _sendMail($surveyId,$oToken,$sSubject="",$sMessage="")
    {
        $emailType = 'register';
        $sLanguage = App()->language;
        $aSurveyInfo = getSurveyInfo($surveyId, $sLanguage);

        $aMail = array();
        $aMail['subject'] = $sSubject;
        if(trim($sSubject) == "") {
            $aMail['subject'] = $aSurveyInfo['email_'.$emailType.'_subj'];
        }
        $aMail['message'] = $sMessage;
        if(trim($sMessage) == "") {
            $aMail['message'] = $aSurveyInfo['email_'.$emailType];
        }
        $aReplacementFields = array();
        $aReplacementFields["{ADMINNAME}"] = $aSurveyInfo['adminname'];
        $aReplacementFields["{ADMINEMAIL}"] = empty($aSurveyInfo['adminemail']) ? App()->getConfig('siteadminemail') : $aSurveyInfo['adminemail'];
        $aReplacementFields["{SURVEYNAME}"] = $aSurveyInfo['name'];
        $aReplacementFields["{SURVEYDESCRIPTION}"] = $aSurveyInfo['description'];
        $aReplacementFields["{EXPIRY}"] = $aSurveyInfo["expiry"];
        foreach ($oToken->attributes as $attribute=>$value) {
            $aReplacementFields["{".strtoupper($surveyId)."}"] = $value;
        }
        $sToken = $oToken->token;
        $useHtmlEmail = (getEmailFormat($surveyId) == 'html');
        $aMail['subject'] = preg_replace("/{TOKEN:([A-Z0-9_]+)}/", "{"."$1"."}", $aMail['subject']);
        $aMail['message'] = preg_replace("/{TOKEN:([A-Z0-9_]+)}/", "{"."$1"."}", $aMail['message']);
        $aReplacementFields["{SURVEYURL}"] = Yii::app()->getController()->createAbsoluteUrl("plugins/direct", array('plugin' => get_class(),'sid'=>$surveyId,'token'=>$sToken));
        $aReplacementFields["{OPTOUTURL}"] = "";
        $aReplacementFields["{OPTINURL}"] = "";
        foreach (array('OPTOUT', 'OPTIN', 'SURVEY') as $key) {
            $url = $aReplacementFields["{{$key}URL}"];
            if ($useHtmlEmail) {
                $aReplacementFields["{{$key}URL}"] = "<a href='{$url}'>".htmlspecialchars($url).'</a>';
            }
            $aMail['subject'] = str_replace("@@{$key}URL@@", $url, $aMail['subject']);
            $aMail['message'] = str_replace("@@{$key}URL@@", $url, $aMail['message']);
        }
        // Replace the fields
        $aMail['subject'] = ReplaceFields($aMail['subject'], $aReplacementFields);
        $aMail['message'] = ReplaceFields($aMail['message'], $aReplacementFields);

        $sFrom = $aReplacementFields["{ADMINEMAIL}"];
        if(!empty($aReplacementFields["{ADMINNAME}"])) {
            $sFrom = $aReplacementFields["{ADMINNAME}"]."<".$aReplacementFields["{ADMINEMAIL}"].">";
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
        Yii::app()->setConfig("emailsmtpdebug",0);
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
        $versionNumber = Yii::app()->getConfig('versionnumber');
        $aVersion = array(0,0,0)+explode(".",$versionNumber);
        if(version_compare(Yii::app()->getConfig('versionnumber'),"3.10",">=")) {
            /* Fix it to use renderMessage ! */
            $this->aRenderData['pluginName'] = $pluginName = get_class($this);
            $this->aRenderData['plugin'] = $this;
            $this->aRenderData['username'] = $this->_isLsAdmin() ? Yii::app()->user->getName() : null;
            $content = Yii::app()->getController()->renderPartial(get_class($this).".views.content.".$fileRender,$this->aRenderData,true);
            $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttheme')));
            Template::model()->getInstance($templateName, null);
            Template::model()->getInstance($templateName, null)->oOptions->ajaxmode = 'off';
            if(!empty($this->aRenderData['aSurveyInfo'])) {
                Template::model()->getInstance($templateName, null)->oOptions->container = 'off';
            }
            //~ tracevar(Template::model()->getInstance($templateName, null));
            if(empty($this->aRenderData['aSurveyInfo'])) {
                $renderTwig['aSurveyInfo'] = array(
                    'surveyls_title' => App()->getConfig('sitename'),
                    'name' => App()->getConfig('sitename'),
                );
            }
            $renderTwig['aSurveyInfo']['active'] = 'Y'; // Didn't show the default warning
            $renderTwig['aSurveyInfo']['include_content'] = 'content';
            $renderTwig['htmlcontent'] = $content;
            $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/responselistandmanage');
            App()->getClientScript()->registerCssFile($assetUrl."/responselistandmanage.css");
            App()->getClientScript()->registerScriptFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/responselistandmanage/responselistandmanage.js'));
            //~ App()->getClientScript()->registerScriptFile($assetUrl."/responselistandmanage.js");
            Yii::app()->twigRenderer->renderTemplateFromFile('layout_global.twig', $renderTwig, false);
            Yii::app()->end();
        }
        
        //Yii::app()->bootstrap->init();
        if(version_compare(Yii::app()->getConfig('versionnumber'),"3",">=")) {
            /* Fix it to use renderMessage ! */
            $this->aRenderData['oTemplate'] = $oTemplate  = Template::model()->getInstance(App()->getConfig('defaulttheme'));
            Yii::app()->clientScript->registerPackage($oTemplate->sPackageName, LSYii_ClientScript::POS_BEGIN);
            
            $this->aRenderData['title'] = isset($this->aRenderData['title']) ? $this->aRenderData['title'] : App()->getConfig('sitename');
            $pluginName = get_class($this);
            Yii::setPathOfAlias($pluginName, dirname(__FILE__));
            //$oEvent=$this->event;
            Yii::app()->controller->layout='bare'; // bare don't have any HTML
            $this->aRenderData['assetUrl'] = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/responselistandmanage');
            $this->aRenderData['subview']="content.{$fileRender}";
            $this->aRenderData['showAdminSurvey'] = false; // @todo Permission::model()->hasSurveyPermission($this->iSurveyId,'surveysettings','update');
            $this->aRenderData['showAdmin'] = false; // What can be the best solution ?
            $this->aRenderData['pluginName'] = $pluginName;
            $this->aRenderData['username'] = false;
            
            Yii::app()->controller->render($pluginName.".views.layout",$this->aRenderData);
            Yii::app()->end();
        }
        /* Finally 2.5X version */
        if(!empty($this->aRenderData['surveyId'])) {
            Yii::app()->setConfig('surveyID',$this->aRenderData['surveyId']);
        }
        $this->aRenderData['title'] = isset($this->aRenderData['title']) ? $this->aRenderData['title'] : App()->getConfig('sitename');
        $pluginName = get_class($this);
        $this->aRenderData['assetUrl'] = $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/responselistandmanage');
        $this->aRenderData['subview']="content.{$fileRender}";
        $this->aRenderData['showAdminSurvey'] = false; // @todo Permission::model()->hasSurveyPermission($this->iSurveyId,'surveysettings','update');
        $this->aRenderData['showAdmin'] = false; // What can be the best solution ?
        $this->aRenderData['pluginName'] = $pluginName;
        $this->aRenderData['username'] = false;
        App()->getClientScript()->registerPackage("bootstrap");
        Yii::app()->getClientScript()->registerMetaTag('width=device-width, initial-scale=1.0', 'viewport');
        App()->bootstrap->registerAllScripts();
        App()->getClientScript()->registerCssFile($assetUrl."/responselistandmanage.css");
        App()->getClientScript()->registerScriptFile($assetUrl."/responselistandmanage.js");
        $message = Yii::app()->controller->renderPartial($pluginName.".views.content.".$fileRender,$this->aRenderData,true);
        $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttheme')));
        $messageHelper = new \renderMessage\messageHelper($this->aRenderData['surveyId'],$templateName);
        $messageHelper->render($message);
    }

    /**
     * get the token attribute list
     * @param integer $surveyId
     * @param string $prefix
     * @return array
     */
    private function _getTokensAttributeList($surveyId,$prefix="",$register=false) {
        $oSurvey = Survey::model()->findByPk($surveyId);
        $aTokens = array(
            $prefix.'firstname'=>gT("First name"),
            $prefix.'lastname'=>gT("Last name"),
            $prefix.'token'=>gT("Token"),
            $prefix.'email'=>gT("Email"),
        );
        foreach($oSurvey->getTokenAttributes() as $attribute=>$information) {
            $aTokens[$prefix.$attribute] = empty($information['description']) ? $attribute : $information['description'];
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
        return $this->_allowTokenLink($oSurvey) && $oSurvey->alloweditaftercompletion == "Y" && $oSurvey->tokenanswerspersistence != "Y";
    }

    /**
     * Check if getQuestionInformation plugin is here and activated
     * Log as error if not
     * @return boolean
     */
    private function _isUsable()
    {
        $haveGetQuestionInformation = Yii::getPathOfAlias('getQuestionInformation');
        if(!$haveGetQuestionInformation) {
            $this->log("You need getQuestionInformation plugin",'error');
        }
        $haveReloadAnyResponse = Yii::getPathOfAlias('reloadAnyResponse');
        if(!$haveReloadAnyResponse) {
            $this->log("You need reloadAnyResponse plugin",'error');
        }
        return $haveGetQuestionInformation && $haveReloadAnyResponse;
    }

    /**
     * get the current token
     * Order is : session, getQuery
     * @param integer $surveyId
     * @return string|null
     */
    private function _getCurrentToken($surveyId) {
        if(Yii::app()->getRequest()->getQuery('token') && is_string(Yii::app()->getRequest()->getQuery('token')) ) {
            return Yii::app()->getRequest()->getQuery('token');
        }
        $sessionToken = Yii::app()->session['responseListAndManageTokens'];
        if(!empty($sessionToken[$surveyId])) {
            return $sessionToken[$surveyId];
        }
    }

    /**
     * set the current token for this survey
     * @param integer $surveyId
     * @param string $token
     * @return string|null
     */
    private function _setCurrentToken($surveyId,$token) {
        $sessionTokens = Yii::app()->session['responseListAndManageTokens'];
        if(empty($sessionTokens)) {
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
        if(is_callable("parent::log")) {
            //parent::log($message, $level);
        }
        Yii::log("[".get_class($this)."] ".$message, $level, 'vardump');
    }

    /**
     * get translation
     * @param string
     * @return string
     */
    private function _translate($string){
        return Yii::t('',$string,array(),get_class($this));
    }

    /**
     * Add this translation just after loaded all plugins
     * @see event afterPluginLoad
     */
    public function afterPluginLoad(){
        // messageSource for this plugin:
        $messageSource=array(
            'class' => 'CGettextMessageSource',
            'cacheID' => get_class($this).'Lang',
            'cachingDuration'=>3600,
            'forceTranslation' => true,
            'useMoFile' => true,
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR.'locale',
            'catalog'=>'messages',// default from Yii
        );
        Yii::app()->setComponent(get_class($this),$messageSource);
    }

    /**
     * Return if user is connected to LS admin
     */
    private function _isLsAdmin() {
        return Yii::app()->session['loginID'];
    }

    /**
     * return if survey has token table
     * @param \Survey
     * @return boolean
     */
    private function _surveyHasTokens($oSurvey) {
        if(version_compare(Yii::app()->getConfig('versionnumber'),'3',">=")) {
            return $oSurvey->getHasTokensTable();
        }
        Yii::import('application.helpers.common_helper', true);
        return tableExists("{{tokens_".$oSurvey->sid."}}");
    }

    public function beforeControllerAction() {
      if(!$this->getEvent()->get("controller") == "survey") {
        return;
      }
      $token = Yii::app()->getRequest()->getQuery("token");
      if(!$token) {
        return;
      }
      $surveyId = Yii::app()->getRequest()->getParam("sid");
      $responseid = Yii::app()->getRequest()->getQuery("srid");
      if(!$responseid || $responseid=="new") {
        return;
      }
      $oSurvey = Survey::model()->findByPk($surveyId);
      if(!$this->_surveyHasTokens($oSurvey)) {
        return;
      }
      if($oSurvey->active != "Y") {
        return;
      }
      $oToken =  Token::model($surveyId)->find("token = :token",array(":token"=>$token));
      if(!$oToken) {
        return;
      }

      $oResponseToken = Response::model($surveyId)->findByPk($responseid);
      if(!$oResponseToken || empty($oResponseToken->token)) {
        return;
      }
      $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId);
      $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId);
      $tokenGroup = (!empty($oToken->$tokenAttributeGroup) && trim($oToken->$tokenAttributeGroup) != "") ? $oToken->$tokenAttributeGroup : null;
      $tokenGroupManager = (!empty($oToken->$tokenAttributeGroupManager) && trim($oToken->$tokenAttributeGroupManager) != "") ? $oToken->$tokenAttributeGroupManager : null;
      $isManager = ((bool) $tokenGroupManager) && trim($tokenGroupManager) !== '0';
      $settingAllowSee = $this->get('allowSee','Survey',$surveyId,'admin');
      $allowSee = ($settingAllowSee == 'all') || ($settingAllowSee == 'admin' && $isManager);
      $settingAllowEdit = $this->get('allowEdit','Survey',$surveyId,'admin');
      $allowEdit = $allowSee && (($settingAllowEdit == 'all') || ($settingAllowEdit == 'admin' && $isManager));
      if(!$allowEdit) {
        return;
      }
      /* OK get it */
      $aTokens = $this->_getTokensList($surveyId,$token);
      if($oResponseToken->token == $token) {
        return;
      }
      if(in_array($token,$aTokens)) {
        $oResponseToken->token = $token;
        $oResponseToken->save();
      }

    }
    private function _getTokensList($surveyId,$token)
    {
      $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId);
      $oTokenGroup = Token::model($surveyId)->find("token = :token",array(":token"=>$token));
      $tokenGroup = (isset($oTokenGroup->$tokenAttributeGroup) && trim($oTokenGroup->$tokenAttributeGroup)!='') ? $oTokenGroup->$tokenAttributeGroup : null;
      if(empty($tokenGroup)) {
        return array($token=>$token);
      }
      $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup."= :group",array(":group"=>$tokenGroup));
      return CHtml::listData($oTokenGroup,'token','token');
    }
}
