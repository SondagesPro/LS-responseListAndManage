<?php
/**
 * Responses List And Manage
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 0.13.2
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
            'default' => 'vanilla',
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
        $this->subscribe('newSurveySettings');

        /* API dependant */
        $this->subscribe('getPluginTwigPath');

        /* Need some evenbt in iframe survey */
        $this->subscribe('beforeSurveyPage');
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
        if(Yii::app()->getRequest()->getParam("saveall")) {
            App()->getClientScript()->registerScript("justsaved","autoclose();\n",CClientScript::POS_END);
        }
        if(Yii::app()->getRequest()->getParam("clearall")=="clearall" && Yii::app()->getRequest()->getParam("confirm-clearall")) {
            App()->getClientScript()->registerScript("justsaved","autoclose();\n",CClientScript::POS_END);
        }
        App()->getClientScript()->registerScriptFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/surveymanaging/surveymanaging.js'),CClientScript::POS_BEGIN);

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
        $oSurvey = Survey::model()->findByPk($iSurveyId);
        App()->getClientScript()->registerScriptFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/settings/responselistandmanage.js'),CClientScript::POS_BEGIN);

        $aQuestionList = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionListData($iSurveyId,App()->getLanguage());
        $accesUrl = Yii::app()->createUrl("plugins/direct", array('plugin' => get_class(),'sid'=>$iSurveyId));
        $this->getEvent()->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'link'=>array(
                    'type'=>'info',
                    'content'=> CHtml::link($this->_translate("Response alternate management"),$accesUrl),
                ),
                'tokenAttributes' => array(
                    'type'=>'select',
                    'label'=>$this->_translate('Token attributes to show in management'),
                    'options'=>$this->_getTokensAttributeList($iSurveyId,'tokens.'),
                    'htmlOptions'=>array(
                        'multiple'=>true,
                        'placeholder'=>gT("All"),
                        'unselectValue'=>"",
                    ),
                    'selectOptions'=>array(
                        'placeholder'=>gT("All"),
                    ),
                    'current'=>$this->get('tokenAttributes','Survey',$iSurveyId)
                ),
                'surveyAttributes' => array(
                    'type'=>'select',
                    'label'=>$this->_translate('Survey columns to be show in management'),
                    'options'=>$aQuestionList['data'],
                    'htmlOptions'=>array(
                        'multiple'=>true,
                        'placeholder'=>gT("All"),
                        'unselectValue'=>"",
                        'options'=>$aQuestionList['options'], // In dropdon, but not in select2
                    ),
                    'selectOptions'=>array(
                        'placeholder'=>gT("All"),
                        //~ 'templateResult'=>"formatQuestion",
                    ),
                    'controlOptions' => array(
                        'class'=>'select2-withover ',
                    ),
                    'current'=>$this->get('surveyAttributes','Survey',$iSurveyId)
                ),
                'tokenAttributeGroup' => array(
                    'type'=>'select',
                    'label'=>$this->_translate('Token attributes for group'),
                    'options'=>$this->_getTokensAttributeList($iSurveyId,''),
                    'htmlOptions'=>array(
                        'empty'=>$this->_translate("None"),
                    ),
                    'current'=>$this->get('tokenAttributeGroup','Survey',$iSurveyId)
                ),
                'tokenAttributeGroupManager' => array(
                    'type'=>'select',
                    'label'=>$this->_translate('Token attributes for group manager'),
                    'options'=>$this->_getTokensAttributeList($iSurveyId,''),
                    'htmlOptions'=>array(
                        'empty'=>$this->_translate("None"),
                    ),
                    'current'=>$this->get('tokenAttributeGroupManager','Survey',$iSurveyId)
                ),
                'tokenAttributeGroupWhole' => array(
                    'type'=>'boolean',
                    'label'=>$this->_translate('User of group can see and manage all group response'),
                    'help'=>$this->_translate('Else only group manager can manage other group response.'),
                    'current'=>$this->get('tokenAttributeGroupWhole','Survey',$iSurveyId,1)
                ),
                'allowDelete' => array(
                    'type'=>'select',
                    'label'=>$this->_translate('Allow deletion of response'),
                    'options'=>array(
                        'admin'=>gT("Only for administrator"),
                        'all'=>gT("All user in group"),
                    ),
                    'htmlOptions'=>array(
                        'empty'=>gT("No"),
                    ),
                    //~ 'help'=>$this->_translate('Else only group manager can manage other group response.'),
                    'current'=>$this->get('allowDelete','Survey',$iSurveyId,'admin')
                ),
                'allowAdd' => array(
                    'type'=>'select',
                    'label'=>$this->_translate('Allow add response'),
                    'options'=>array(
                        'admin'=>gT("Only for administrator"),
                        'all'=>gT("Yes"),
                    ),
                    'htmlOptions'=>array(
                        'empty'=>gT("No"),
                    ),
                    //~ 'help'=>$this->_translate('Else only group manager can manage other group response.'),
                    'current'=>$this->get('allowAdd','Survey',$iSurveyId,'admin')
                ),
            )
        ));
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
        if(!$this->_isUsable()) {
            return;
        }
        if($this->getEvent()->get('target') != get_class($this)) {
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
        if($surveyId) {
            $this->_doSurvey($surveyId);
            App()->end(); // Not needed but more clear
        }
        $this->_doListSurveys();
    }

    /**
     * Managing access to survey
     */
    private function _doSurvey($surveyId)
    {
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
        if(!$userHaveRight && $this->_allowTokenLink($oSurvey) && App()->getRequest()->getParam('token')) {
            $userHaveRight = true;
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
        $currentToken = App()->getRequest()->getParam('token');
        $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId);
        $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId);
        $tokenAttributeGroupWhole = $this->get('tokenAttributeGroupWhole','Survey',$surveyId,1);
        if($currentToken) {
            $aTokens = (array) App()->getRequest()->getParam('token');
            $oToken = Token::model($surveyId)->findByToken(App()->getRequest()->getParam('token'));
            $tokenGroup = ($tokenAttributeGroup && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : null;
            if($tokenGroup) {
                $isManager = !empty($oToken->$tokenAttributeGroupManager);
                if($tokenAttributeGroupWhole || $isManager) {
                    $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup."= :group",array(":group"=>$tokenGroup));
                    $aTokens = CHtml::listData($oTokenGroup,'token','token');
                }
            }
            $mResponse->setAttribute('token', $aTokens);
        }

        Yii::app()->user->setState('pageSize',intval(Yii::app()->request->getParam('pageSize',Yii::app()->user->getState('pageSize',10))));
        /* Add a new */
        $tokenList = null;
        if($oSurvey->getHasTokensTable()) {
            if(Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create')) {
                $tokenList = CHtml::listData(Token::model($surveyId)->findAll(),'token',function($oToken){
                    return CHtml::encode(trim($oToken->firstname.' '.$oToken->lastname.' ('.$oToken->token.')'));
                },
                $tokenAttributeGroup);

            }
            if($currentToken) {
                if(is_array($currentToken)) {
                    $currentToken = array_shift(array_values($currentToken));
                }
                $tokenList = array($currentToken=>$currentToken);
                if($isManager) {
                    $tokenList = CHtml::listData($oTokenGroup,'token',function($oToken){
                        return CHtml::encode(trim($oToken->firstname.' '.$oToken->lastname.' ('.$oToken->token.')'));
                    },
                    $tokenAttributeGroup);
                }
            }
        }
        if(!$tokenList) {
            $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                array("survey/index",'sid'=>$surveyId,'newtest'=>"Y"),
                array('class'=>'btn btn-default btn-xs  addnew')
            );
        }
        if($tokenList && count($tokenList) == 1) {
            $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                array("survey/index",'sid'=>$surveyId,'newtest'=>"Y",'srid'=>'new','token'=>array_values($tokenList)[0]),
                array('class'=>'btn btn-default btn-xs addnew')
            );
        }
        if($tokenList && count($tokenList) > 1) {
            $addNew  = CHtml::beginForm(array("survey/index"),'get',array('class'=>"form-inline"));
            $addNew .= CHtml::hiddenField('sid',$surveyId);
            $addNew .= CHtml::hiddenField('newtest',"Y");
            if(count($tokenList) == 1) {
                $addNew .= CHtml::hiddenField('token',array_shift(array_keys($tokenList)));
                $addNew .= CHtml::htmlButton("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                    array("type"=>'submit','name'=>'srid','value'=>'new','class'=>'btn btn-default btn-xs addnew')
                );
            }
            if(count($tokenList) > 1) {
                //~ $addNew .= '<div class="form-group"><div class="input-group">';
                $addNew .= CHtml::dropDownList('token',$currentToken,$tokenList,array('class'=>'form-control input-xs','empty'=>gT("Please choose...")));
                $addNew .= CHtml::htmlButton("<i class='fa fa-plus-circle' aria-hidden='true'></i>".$this->_translate("Create an new response"),
                    array("type"=>'button','name'=>'srid','value'=>'new','class'=>'btn btn-default btn-xs addnew')
                );
                //~ $addNew .= '</div></div>';
            }
            $addNew .= CHtml::endForm();
        }
        $this->aRenderData['addNew'] = $addNew;
        /* Contruct column */
        /* Put the button here, more easy */
        $updateButtonUrl = 'App()->createUrl("survey/index",array("sid"=>'.$surveyId.',"srid"=>$data["id"],"newtest"=>"Y"))';
        if($this->_allowTokenLink($oSurvey)) {
            $updateButtonUrl = 'App()->createUrl("survey/index",array("sid"=>'.$surveyId.',"token"=>$data["token"],"srid"=>$data["id"],"newtest"=>"Y"))';
        }
        
        $deleteButtonUrl = 'App()->createUrl("plugins/direct",array("plugin"=>"'.get_class().'","sid"=>'.$surveyId.',"delete"=>$data["id"]))';
        if($this->_allowTokenLink($oSurvey) && !$oSurvey->getIsAnonymized()) {
            $deleteButtonUrl = 'App()->createUrl("plugins/direct",array("plugin"=>"'.get_class().'","sid"=>'.$surveyId.',"token"=>$data["token"],"delete"=>$data["id"]))';
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
        $aColumns = array_merge($aColumns,$mResponse->getGridColumns());
        /* Get the selected columns only */
        $tokenAttributes = $this->get('tokenAttributes','Survey',$surveyId);
        $surveyAttributes = $this->get('surveyAttributes','Survey',$surveyId);

        $filteredArr = array();
        if(!empty($tokenAttributes) || !empty($surveyAttributes)) {
            $aRestrictedColumns = array();
            if(empty($tokenAttributes)) {
                $tokenAttributes = array_keys($this->_getTokensAttributeList($surveyId,'tokens.'));
            }
            if(empty($surveyAttributes)) {
                $surveyAttributes = \getQuestionInformation\helpers\surveyColumnsInformation::getAllQuestionListData($surveyId,App()->getLanguage());
                $surveyAttributes = array_keys($surveyAttributes['data']);
            }

            $forcedColumns = array('buttons','id');
            $aRestrictedColumns = array_merge($forcedColumns,$tokenAttributes,$surveyAttributes);
            $aColumns = array_intersect_key( $aColumns, array_flip( $aRestrictedColumns ) );
            
        }

        $this->aRenderData['allowAddUser'] = $isManager || Permission::model()->hasSurveyPermission($surveyId, 'token', 'create');
        $this->aRenderData['addUser'] = array();
        $this->aRenderData['addUserButton'] = '';
        if($this->aRenderData['allowAddUser']) {
            $this->aRenderData['addUserButton'] = CHtml::htmlButton("<i class='fa fa-user-plus ' aria-hidden='true'></i>".$this->_translate("Create an new user"),
                array("type"=>'button','name'=>'adduser','value'=>'new','class'=>'btn btn-default btn-xs addnewuser')
            );
            $this->aRenderData['addUser'] = $this->_addUserDataForm($surveyId,$currentToken);
        }
        if(empty($this->aRenderData['lang'])) {
            $this->aRenderData['lang'] = array();
        }
        $this->aRenderData['lang']['Close'] = gT("Close");
        //$this->aRenderData['lang']['Delete'] = $this->_translate("Delete");
        if($oSurvey->allowprev == "Y") {
            $this->aRenderData['lang']['Previous'] = $this->gT("Previous");
        }
        if($oSurvey->allowsave == "Y") { // We don't need to test token, we don't shown default save part …
            $this->aRenderData['lang']['Save'] = $this->gT("Save");
        }
        if($oSurvey->format!="A") {
            $this->aRenderData['lang']['Next'] = $this->gT("Next");
        }
        $this->aRenderData['lang']['Submit'] = $this->gT("Submit");
        $this->aRenderData['model'] = $mResponse;
        $this->aRenderData['columns'] = $aColumns;
        $this->_render('responses');
    }

    private function _deleteResponseSurvey($surveyId,$srid)
    {
        $oSurvey = Survey::model()->findByPk($surveyId);
        //echo "Work in progress";
        $allowed = false;
        if($this->get('allowDelete','Survey',$surveyId,'admin') && Permission::model()->hasSurveyPermission($surveyId, 'response', 'delete')) {
            $allowed = true;
        }
        if(!$allowed && $this->get('allowDelete','Survey',$surveyId,'admin') && $oSurvey->getHasTokenTable()) {
            $whoIsAllowed = $this->get('allowDelete','Survey',$surveyId,'admin');
            $token = App()->getRequest()->getParam('token');
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
        $oResponse = Response::model($surveyId)->findByPk($srid);
        if(!$oResponse) {
            throw new CHttpException(401, $this->_translate("Invalid response id."));
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
        if($token) {
            $tokenAttributeGroup = $this->get('tokenAttributeGroup','Survey',$surveyId,null);
            $tokenAttributeGroupManager = $this->get('tokenAttributeGroupManager','Survey',$surveyId,null);
            $tokenGroup = (!empty($tokenAttributeGroup) && !empty($oToken->$tokenAttributeGroup)) ? $oToken->$tokenAttributeGroup : null;
            $tokenAdmin = (!empty($tokenAttributeGroupManager) && !empty($oToken->$tokenAttributeGroupManager)) ? $oToken->$tokenAttributeGroupManager : null;
        }
        if(!Permission::model()->hasSurveyPermission($surveyId, 'token', 'create')) {
            if(empty($tokenAdmin)) {
                throw new CHttpException(403, $this->_translate('No right to create new token in this survey.'));
            }
        }
        $oToken = Token::create($surveyId);
        $oToken->setAttributes(Yii::app()->getRequest()->getParam('token'));
        if(!is_null($tokenGroup)) {
            $oToken->$tokenAttributeGroup = $tokenGroup;
            $oToken->$tokenAttributeGroupManager = null;
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
            $html = sprintf($this->gT("Token created but unable to send the email, token code is %s"),$oToken->token);
            $html.= CHtml::tag("pre",array(),$this->mailError);
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
        $iAdminId = Permission::getUserId();
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
        $oTemplates = TemplateConfiguration::model()->findAll(array(
            'condition'=>'sid IS NULL',
            'order'=>'template_name',
        ));
        $aTemplates = CHtml::listData($oTemplates,'template_name','template_name');
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
        Yii::import('application.controllers.admin.authentication',1);
        $aResult = Authentication::prepareLogin();
        $succeeded = isset($aResult[0]) && $aResult[0] == 'success';
        $failed = isset($aResult[0]) && $aResult[0] == 'failed';
        $this->aRenderData['error'] = null;
        if ($succeeded) {
            //die('succeeded = '.$succeeded);
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
        header("HTTP/1.1 401 Unauthorized");
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
                $addUser["attributeGroup"]['caption'] = ($aSurveyInfo['attributecaptions'][$tokenAttributeGroup] ? $aSurveyInfo['attributecaptions'][$tokenAttributeGroup] : ($aAllAttributes[$tokenAttributeGroup]['description'] ? $aAllAttributes[$tokenAttributeGroup]['description'] : $this->gT("Is a group manager")));
            }
            if($tokenAttributeGroupManager) {
                $addUser["tokenAttributeGroupManager"] = $aAllAttributes[$tokenAttributeGroup];
                $addUser["tokenAttributeGroupManager"]['attribute'] = $tokenAttributeGroup;
                $addUser["tokenAttributeGroupManager"]['caption'] = ($aSurveyInfo['attributecaptions'][$tokenAttributeGroupManager] ? $aSurveyInfo['attributecaptions'][$tokenAttributeGroupManager] : ($aAllAttributes[$tokenAttributeGroupManager]['description'] ? $aAllAttributes[$tokenAttributeGroupManager]['description'] : $this->gT("Is a group manager")));
            }
        }
        $emailType = 'register';
        $addUser['email'] = array(
            'subject' => $aSurveyInfo['email_'.$emailType.'_subj'],
            'body' => $aSurveyInfo['email_'.$emailType],
            'help' => sprintf($this->gT("You can use token information like %s or %s, %s was replaced by the url for managing response."),"{FIRSTNAME}","{ATTRIBUTE_1}","{SURVEYURL}"),
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
     * sned the email wit replacing SURVEYURL by the manage url
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
        Yii::app()->setConfig("emailsmtpdebug",2);
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
        if(floatval(Yii::app()->getConfig('versionnumber')> 3.10)) {
            $this->aRenderData['pluginName'] = $pluginName = get_class($this);
            $this->aRenderData['plugin'] = $this;
            $this->aRenderData['username'] = Permission::getUserId() ? Yii::app()->user->getName() : null;
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
            $renderTwig['aSurveyInfo']['content'] = $content;
            $renderTwig['aSurveyInfo']['include_content'] = 'content';
            $assetUrl = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/responselistandmanage');
            App()->getClientScript()->registerCssFile($assetUrl."/responselistandmanage.css");
            App()->getClientScript()->registerScriptFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/responselistandmanage/responselistandmanage.js'));
            //~ App()->getClientScript()->registerScriptFile($assetUrl."/responselistandmanage.js");
            Yii::app()->twigRenderer->renderTemplateFromFile('layout_global.twig', $renderTwig, false);

            Yii::app()->end();
        }
        
        //Yii::app()->bootstrap->init();
        
        $this->aRenderData['oTemplate'] = $oTemplate  = Template::model()->getInstance(App()->getConfig('defaulttheme'));
        Yii::app()->clientScript->registerPackage($oTemplate->sPackageName, LSYii_ClientScript::POS_BEGIN);
        
        
        $this->aRenderData['title'] = isset($this->aRenderData['title']) ? $this->aRenderData['title'] : App()->getConfig('sitename');
        
        Yii::setPathOfAlias($pluginName, dirname(__FILE__));
        //$oEvent=$this->event;
        Yii::app()->controller->layout='bare'; // bare don't have any HTML
        $this->aRenderData['assetUrl'] = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/responselistandmanage');
        $this->aRenderData['subview']="content.{$fileRender}";
        $this->aRenderData['showAdminSurvey'] = false; // @todo Permission::model()->hasSurveyPermission($this->iSurveyId,'surveysettings','update');
        $this->aRenderData['showAdmin'] = false; // What can be the best solution ?
        
        Yii::app()->controller->render($pluginName.".views.layout",$this->aRenderData);
        Yii::app()->end();
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
     * Find is survey allow token link
     * @param \Survey
     * @return boolean
     */
    private function _allowTokenLink($oSurvey)
    {
        return $oSurvey->getHasTokensTable() && $oSurvey->anonymized != "Y";
    }
    /**
     * Translation : use another function name for poedit, and set escape mode to needed one
     * @see parent::gT
     * @param string $sToTranslate The message that are being translated
     * @param string $sEscapeMode
     * @param string $sLanguage
     * @return string
     */
    private function _translate($string, $sEscapeMode = 'unescaped', $sLanguage = null) {

        return $this->gT($string, $sEscapeMode, $sLanguage);
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
}
