<?php
/**
 * Responses List And Manage
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 1.9.0
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
            'label' => 'Template to be used',
        ),
        'showLogOut' => array(
            'type' => 'boolean',
            'default' => false,
            'label' => 'Show log out',
        ),
        'showAdminLink' => array(
            'type' => 'boolean',
            'default' => true,
            'label' => 'Show administration link',
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
        if(Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'update')) {
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
        }
        if(Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read') && $this->get('allowAccess','Survey',$surveyId,'all') ) {
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
                'showId','showCompleted','showSubmitdate','showStartdate','showDatestamp',
                'tokenAttributes','surveyAttributes','surveyAttributesPrimary',
                'tokenColumnOrder',
                'tokenAttributesHideToUser','surveyAttributesHideToUser',
                'tokenAttributeGroup', 'tokenAttributeGroupManager', 'tokenAttributeGroupWhole',
                'allowAccess','allowSee','allowEdit','allowDelete', 'allowAdd',
                'template',
                'showFooter',
                'filterOnDate','filterSubmitdate','filterStartdate','filterDatestamp',
                'showLogOut','showSurveyAdminpageLink',
            );
            foreach($settings as $setting) {
                $this->set($setting, App()->getRequest()->getPost($setting), 'Survey', $surveyId);
            }

            $languageSettings = array('description');
            foreach($languageSettings as $setting) {
                $finalSettings = array();
                foreach($oSurvey->getAllLanguages() as $language) {
                    $finalSettings[$language] = App()->getRequest()->getPost($setting.'_'.$language);
                }
                $this->set($setting, $finalSettings, 'Survey', $surveyId);
            }

            if(App()->getRequest()->getPost('save'.get_class($this)=='redirect')) {
                Yii::app()->getController()->redirect(Yii::app()->getController()->createUrl('admin/survey',array('sa'=>'view','surveyid'=>$surveyId)));
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
            'showId' => array(
                'type'=>'boolean',
                'label'=>$this->_translate('Show id of response.'),
                'current'=>$this->get('showId','Survey',$surveyId,1)
            ),
            'showCompleted' => array(
                'type'=>'boolean',
                'label'=>$this->_translate('Show completed state.'),
                'current'=>$this->get('showCompleted','Survey',$surveyId,1)
            ),
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
            'tokenColumnOrder' => array(
                'type'=>'select',
                'label'=>$this->_translate('Token attributes order'),
                'options'=>array(
                    'start' => $this->_translate("At start (before primary columns)"),
                    'default' => $this->_translate("Between primary columns and secondary columns (default)"),
                    'end' => $this->_translate("At end (after all other columns)"),
                ),
                'current'=>$this->get('tokenColumnOrder','Survey',$surveyId,'default')
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
            'showFooter' => array(
                'type'=>'boolean',
                'label'=>$this->_translate('Show footer with count and sum.'),
                'current'=>$this->get('showFooter','Survey',$surveyId,0)
            ),
        );
        $aSettings[$this->_translate('Date/time response management')] = array(
            'filterOnDate' => array(
                'type'=>'boolean',
                'label'=>$this->_translate('Show filter on date question.'),
                'current'=>$this->get('filterOnDate','Survey',$surveyId,1)
            ),
            'showDateInfo' => array(
                'type' => 'info',
                'content' => CHtml::tag("div",array("class"=>"well"),$this->_translate("All this settings after need date stamped survey.")),
            ),
            'showStartdate' => array(
                'type'=>'boolean',
                'label'=>$this->_translate('Show start date.'),
                
                'current'=>$this->get('showStartdate','Survey',$surveyId,0)
            ),
            'filterStartdate' => array(
                'type'=>'select',
                'options' => array(
                    0 => gT("No"),
                    1 => gT("Yes"),
                    2 => $this->_translate("Yes with time"),
                ),
                'label'=>$this->_translate('Show filter on start date.'),
                'current'=>$this->get('filterStartdate','Survey',$surveyId,0)
            ),
            'showSubmitdate' => array(
                'type'=>'boolean',
                'label'=>$this->_translate('Show submit date.'),
                'current'=>$this->get('showSubmitdate','Survey',$surveyId,0)
            ),
            'filterSubmitdate' => array(
                'type'=>'select',
                'options' => array(
                    0 => gT("No"),
                    1 => gT("Yes"),
                    2 => $this->_translate("Yes with time"),
                ),
                'label'=>$this->_translate('Show filter on submit date.'),
                'current'=>$this->get('filterSubmitdate','Survey',$surveyId,0)
            ),
            'showDatestamp' => array(
                'type'=>'boolean',
                'label'=>$this->_translate('Show last action date.'),
                'current'=>$this->get('showDatestamp','Survey',$surveyId,0)
            ),
            'filterDatestamp' => array(
                'type'=>'select',
                'options' => array(
                    0 => gT("No"),
                    1 => gT("Yes"),
                    2 => $this->_translate("Yes with time"),
                ),
                'label'=>$this->_translate('Show filter on start date.'),
                'current'=>$this->get('filterDatestamp','Survey',$surveyId,0)
            ),
        );
        /* Descrition by lang */
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
        $aSettings[$this->_translate('Description and helper for responses listing')] = $aDescription;
        /* Template to be used */
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
        $default = $this->get('template',null,null,App()->getConfig('defaulttheme',App()->getConfig('defaulttemplate')));
        $aSettings[$this->_translate('Template to be used')] = array(
            'template' => array(
                'type' => 'select',
                'label'=> $this->_translate('Template to be used'),
                'options'=>$aTemplates,
                'htmlOptions' => array(
                    'empty'=> sprintf($this->_translate("Leave default (%s)"),$default),
                ),
                'current' => $this->get('template','Survey',$surveyId),
            )
        );
        /* Token attribute usage */
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
        /* @todo : get settings of reloadAnyResponse and set warning + readonly on some settings */
        
        $aSettings[$this->_translate('Response Management access and right')] = array(
            'infoRights' => array(
                'type'=>'info',
                'content'=>CHtml::tag("ul",array('class'=>'well well-sm list-unstyled'),
                    CHtml::tag("li",array(),$this->_translate("Currently, for LimeSurvey admin user, for survey with token, need token read right.")) .
                    CHtml::tag("li",array(),$this->_translate("For user, except for No : they always have same rights than other, for example if you allow delete to admin user, an user with token can delete his response with token.")) .
                    CHtml::tag("li",array(),$this->_translate("To disable access for user with token you can set this settings to No or only for LimeSurvey admin.")) .
                    CHtml::tag("li",array('class'=>'text-warning'),sprintf("<strong>%s</strong>%s",$this->_translate("Warning"),$this->_translate(": you need to update reloadAnyResponse settings and right. This was not fixed here."))) .
                    ""
                ),
            ),
            'allowAccess' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow to access of tool.'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("Only for LimeSurvey administrator (According to LimeSurvey Permission)"),
                    'all'=>$this->_translate("All (need a valid token)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("None"),
                ),
                'help'=>$this->_translate('You can disable all access with token here. If user can access to the tool, he have same right than LimeSurvey admin on his responses.'),
                'current'=>$this->get('allowAccess','Survey',$surveyId,'all')
            ),
            'allowSee' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow view response of group'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("Only for LimeSurvey administrator"),
                    'admin'=>$this->_translate("For group administrator and LimeSurvey administrator"),
                    'all'=>$this->_translate("All (with a valid token)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Only related to Group responses not to single user responses with token.'),
                'current'=>$this->get('allowSee','Survey',$surveyId,'all')
            ),
            'allowEdit' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow edit response of group'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("Only for LimeSurvey administrator"),
                    'admin'=>$this->_translate("For group administrator and LimeSurvey administrator"),
                    'all'=>$this->_translate("All (with a valid token)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Need view rights.'),
                'current'=>$this->get('allowEdit','Survey',$surveyId,'admin')
            ),
            'allowDelete' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow deletion of response'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("Only for LimeSurvey administrator"),
                    'admin'=>$this->_translate("For group administrator and LimeSurvey administrator"),
                    'all'=>$this->_translate("All (with a valid token)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Need view rights.'),
                'current'=>$this->get('allowDelete','Survey',$surveyId,'admin')
            ),
            'allowAdd' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow add response'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("Only for LimeSurvey administrator"),
                    'admin'=>$this->_translate("For group administrator and LimeSurvey administrator"),
                    'all'=>$this->_translate("All (with a valid token)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Need view rights.'),
                'current'=>$this->get('allowAdd','Survey',$surveyId,'admin')
            ),
            'allowAddUser' => array(
                'type'=>'select',
                'label'=>$this->_translate('Allow add token user'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("Only for LimeSurvey administrator"),
                    'admin'=>$this->_translate("For group administrator and LimeSurvey administrator"),
                    'all'=>$this->_translate("All (with a valid token)"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Need add response right.'),
                'current'=>$this->get('allowAddUser','Survey',$surveyId,'admin')
            ),
            'showLogOut' => array(
                'type'=>'select',
                'label'=>$this->_translate('Show log out button'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("Only for LimeSurvey administrator"),
                    'all'=>gT("Yes"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'current'=>$this->get('showLogOut','Survey',$surveyId,$this->get('showLogOut',null,null,$this->settings['showLogOut']['default']) ? 'admin': null)
            ),
            'showSurveyAdminpageLink' => array(
                'type'=>'select',
                'label'=>$this->_translate('Show survey admin page link'),
                'options'=>array(
                    'limesurvey'=>$this->_translate("All LimeSurvey administrator"),
                    'admin'=>$this->_translate("LimeSurvey administrator with survey settings right"),
                ),
                'htmlOptions'=>array(
                    'empty'=>gT("No"),
                ),
                'help'=>$this->_translate('Need add response right.'),
                'current'=>$this->get('showSurveyAdminpageLink','Survey',$surveyId,$this->get('showAdminLink',null,null,$this->settings['showAdminLink']['default']) ? 'admin': null)
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
        $surveyId = App()->getRequest()->getQuery('sid',App()->getRequest()->getQuery('surveyid'));
        if(App()->getRequest()->getQuery('logout') ) {
            $this->_doLogout($surveyId);
            App()->end(); // Not needed but more clear
        }
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
        if (!Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
            if(!$this->_allowTokenLink($oSurvey)) {
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
        $settingAllowAccess = $this->get('allowAccess','Survey',$surveyId,'all');
        if(empty($settingAllowAccess)) {
            throw new CHttpException(403);
        }
        if (Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
            if(App()->getRequest()->isPostRequest && App()->getRequest()->getPost('login_submit')) {
                /* redirect to avoid CRSF when reload */
                Yii::app()->getController()->redirect(array("plugins/direct", 'plugin' => get_class(),'sid'=>$surveyId));
            }
            $userHaveRight = true;
        }
        if (!$userHaveRight && $settingAllowAccess == 'limesurvey') {
            $this->_doLogin();
        }
        if (!$userHaveRight && !$this->_allowTokenLink($oSurvey)) {
            $this->_doLogin();
        }
        if(!$userHaveRight && App()->getRequest()->getPost('token')) {
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
        if(!$userHaveRight && $currentToken) {
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
            App()->setLanguage($language);
        }
        $this->aRenderData['aSurveyInfo'] = getSurveyInfo($surveyId, $language);
        /* See https://github.com/LimeSurvey/LimeSurvey/commit/0ffc127bfceac4aa7658595b624ac18a2dcce2aa */
        if(Yii::app()->getConfig('debug') && version_compare(Yii::app()->getConfig('versionnumber'),"3.14.8","<=") && version_compare(Yii::app()->getConfig('versionnumber'),"3.0.0",">=")) {
            $sessionSurvey = array(
                "s_lang" => $language
            );
            Yii::app()->session['survey_'.$surveyId] = $sessionSurvey;
        }
        Yii::app()->session['responseListAndManage'] = $surveyId;
        Yii::import(get_class($this).'.models.ResponseExtended');
        $mResponse = ResponseExtended::model($surveyId);
        $mResponse->setScenario('search');
        $mResponse->showFooter = $this->get('showFooter','Survey',$surveyId,false);
        $mResponse->filterOnDate = (bool) $this->get('filterOnDate','Survey',$surveyId,true);
        $mResponse->filterSubmitDate = (int) $this->get('filterSubmitDate','Survey',$surveyId,0);
        $mResponse->filterStartdate = (int) $this->get('filterStartdate','Survey',$surveyId,0);
        $mResponse->filterDatestamp = (int) $this->get('filterDatestamp','Survey',$surveyId,0);

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
        $allowAccess = $allowSee = $allowEdit = $allowDelete = $allowAdd = false;
        $settingAllowAccess = $this->get('allowAccess','Survey',$surveyId,'all');
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
            $allowAccess = Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read');
            if($this->_surveyHasTokens($oSurvey)) {
                $allowAccess = $settingAllowAccess && Permission::model()->hasSurveyPermission($surveyId, 'token', 'read');
            }
            $allowSee = $allowAccess && $settingAllowSee && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read');
            $allowEdit = $allowSee && $settingAllowEdit && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'update');
            $allowDelete = $allowSee && $settingAllowDelete && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'delete');
            $allowAdd = $settingAllowAdd && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create');
        }
        if($currentToken) {
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
            $oTokenGroup = Token::model($surveyId)->findAll("token = :token",array(":token"=>$currentToken));
            if($tokenGroup) {
                $oTokenGroup = Token::model($surveyId)->findAll($tokenAttributeGroup."= :group",array(":group"=>$tokenGroup));
                if($allowSee) {
                    $aTokens = CHtml::listData($oTokenGroup,'token','token');
                }
            }
            $mResponse->setAttribute('token', $aTokens);

            if(!$allowAccess && Permission::model()->hasSurveyPermission($surveyId, 'responses', 'read')) {
                throw new CHttpException(403, $this->_translate('You are not allowed to use reponse management with this token.'));
            }
        }
        Yii::app()->user->setState('responseListAndManagePageSize',intval(Yii::app()->request->getParam('pageSize',Yii::app()->user->getState('responseListAndManagePageSize',50))));
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

        $adminAction = $this->_getAdminMenu($surveyId);
        $this->aRenderData['adminAction'] = empty($adminAction) ? "" : $adminAction." ";;

        $addNew ='';
        if(Permission::model()->hasSurveyPermission($surveyId, 'responses', 'create') && !$this->_surveyHasTokens($oSurvey)) {
            $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i> ".$this->_translate("Create an new response"),
                array("survey/index",'sid'=>$surveyId,'newtest'=>"Y"),
                array('class'=>'btn btn-default btn-sm  addnew')
            );
        }
        if($allowAdd && $singleToken) {
            if($this->_allowMultipleResponse($oSurvey)) {
                $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i> ".$this->_translate("Create an new response"),
                    array("survey/index",'sid'=>$surveyId,'newtest'=>"Y",'srid'=>'new','token'=>$singleToken),
                    array('class'=>'btn btn-default btn-sm addnew')
                );
            }
        }
        if(!$allowAdd && $currentToken) {
            if($this->_allowMultipleResponse($oSurvey)) {
                $addNew = CHtml::link("<i class='fa fa-plus-circle' aria-hidden='true'></i> ".$this->_translate("Create an new response"),
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
            $addNew .= CHtml::htmlButton("<i class='fa fa-plus-circle' aria-hidden='true'></i> ".$this->_translate("Create an new response"),
                array("type"=>'button','name'=>'srid','value'=>'new','class'=>'btn btn-default btn-sm addnew')
            );
            //~ $addNew .= '</div></div>';

            $addNew .= CHtml::endForm();
        }

        $this->aRenderData['addNew'] = empty($addNew) ? "" : $addNew." ";;
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

        $aColumns = array();
        $disableTokenPermission = (bool) $currentToken;
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
        if(!empty($surveyAttributesPrimary)) {
            $surveyAttributes = array_diff ($surveyAttributes,$surveyAttributesPrimary);
        }
        $baseColumns = array();
        if($this->get('showId','Survey',$surveyId,1)) {
            $baseColumns[] = 'id';
        }
        if($this->get('showCompleted','Survey',$surveyId,1)) {
            $baseColumns[] = 'completed';
        }
        if($oSurvey->datestamp && $this->get('showStartdate','Survey',$surveyId,0)) {
            $baseColumns[] = 'startdate';
        }
        if($oSurvey->datestamp && $this->get('showSubmitDate','Survey',$surveyId,0)) {
            $baseColumns[] = 'submitdate';
        }
        if($oSurvey->datestamp && $this->get('showDatestamp','Survey',$surveyId,0)) {
            $baseColumns[] = 'datestamp';
        }
        $aRestrictedColumns = array_merge($baseColumns,$tokenAttributes,$surveyAttributes,$surveyAttributesPrimary);
        switch ($this->get('tokenColumnOrder','Survey',$surveyId,'default')) {
            case "start":
                $aRestrictedColumns = array_merge($baseColumns,$tokenAttributes,$surveyAttributesPrimary,$surveyAttributes);
                break;
            case "end":
                $aRestrictedColumns = array_merge($baseColumns,$surveyAttributesPrimary,$surveyAttributes,$tokenAttributes);
                break;
            case "default":
            default:
                $aRestrictedColumns = array_merge($baseColumns,$surveyAttributesPrimary,$tokenAttributes,$surveyAttributes);
        }
        if($currentToken) {
            $tokenAttributesHideToUser = $this->get('tokenAttributesHideToUser','Survey',$surveyId);
            if(!empty($tokenAttributesHideToUser)) {
                $aRestrictedColumns = array_diff($aRestrictedColumns,$tokenAttributesHideToUser);
            }
            $surveyAttributesHideToUser = $this->get('surveyAttributesHideToUser','Survey',$surveyId);
            if(!empty($surveyAttributesHideToUser)) {
                $aRestrictedColumns = array_diff($aRestrictedColumns,$surveyAttributesHideToUser);
            }
        }
        $mResponse->setRestrictedColumns($aRestrictedColumns);
        $aColumns = $mResponse->getGridColumns($disableTokenPermission);
        /* Get columns by order now … */
        $aOrderedColumn = array(
            'button' => array(
                'htmlOptions' => array('nowrap'=>'nowrap'),
                'class'=>'bootstrap.widgets.TbButtonColumn',
                'template'=>'{update}{delete}',
                'updateButtonUrl'=>$updateButtonUrl,
                'deleteButtonUrl'=>$deleteButtonUrl,
                'footer' => ($this->get('showFooter','Survey',$surveyId,false) ? $this->_translate("Answered count and sum") : null),
            )
        );
        foreach($aRestrictedColumns as $key) {
            if(isset($aColumns[$key])) {
                $aOrderedColumn[$key] = $aColumns[$key];
            }
        }

        $this->aRenderData['allowAddUser'] = $this->_allowTokenLink($oSurvey) && $this->get('allowAddUser','Survey',$surveyId,'admin') == 'all';
        if(!$this->aRenderData['allowAddUser'] && $isManager) {
            $this->aRenderData['allowAddUser'] = $this->_allowTokenLink($oSurvey) && $this->get('allowAddUser','Survey',$surveyId,'admin') == 'admin';
        }
        if(!$this->aRenderData['allowAddUser'] && Permission::model()->hasSurveyPermission($surveyId, 'token', 'create')) {
            $this->aRenderData['allowAddUser'] = $this->_allowTokenLink($oSurvey) && $this->get('allowAddUser','Survey',$surveyId,'admin') == 'limesurvey';
        }
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
        $this->aRenderData['columns'] = $aOrderedColumn;
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
        $allowAddSetting = $this->get('allowAdd','Survey',$surveyId,'admin');
        $allowAddUserSetting =  $this->get('allowAddUser','Survey',$surveyId,'admin');
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
            $allowAddUser = ($allowAddUserSetting == 'all' || ($allowAddUserSetting == 'admin' && $isAdmin));
        }
        
        if(!Permission::model()->hasSurveyPermission($surveyId, 'token', 'create')) {
            if(!$allowAddUser) {
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
        //~ if(!$getValues) {
            //~ return;
        //~ }
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
        $this->settings['template']['default'] = App()->getConfig('defaulttheme',App()->getConfig('defaulttemplate'));
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
            'label'=> $this->_translate('Template to be used.'),
        ));
        $pluginSettings['showLogOut'] = array(
            'type' => 'boolean',
            'label'=> $this->_translate('Show log out.'),
            'default' => true,
            'help' => $this->_translate('On survey list and by default for admin'),
        );
        $pluginSettings['showAdminLink'] = array(
            'type' => 'boolean',
            'label'=> $this->_translate('Show LimeSurvey admininstration link.'),
            'default' => true,
            'help' => $this->_translate('On survey list and by default for admin'),
        );
        /* Validate version_number more than 3.0 ?*/
        if(version_compare(App()->getConfig("versionnumber"),"3",">=") ) {
            /* Find if menu already exist */
            $oSurveymenuEntries = SurveymenuEntries::model()->find("name = :name",array(":name"=>'responseslist'));
            $state = !empty($oSurveymenuEntries);
            $help = $state ? $this->_translate('Menu exist, to delete : uncheck box and validate.') : $this->_translate("Menu didn‘t exist, to create check box and validate." );
            $pluginSettings['createSurveyMenu'] = array(
                'type' => 'checkbox',
                'label'=> $this->_translate('Add a menu to responses management in surveys.'),
                'default' => false,
                'help' => $help,
                'current' => $state,
            );
        }
        return $pluginSettings;
    }


    /**
     * @inheritdoc
     * and set menu if needed
    **/

    public function saveSettings($settings)
    {
        parent::saveSettings($settings);
        if(version_compare(App()->getConfig("versionnumber"),"3","<") ) {
            return;
        }
        $oSurveymenuEntries = SurveymenuEntries::model()->find("name = :name",array(":name"=>'responseslist'));
        $createSurveyMenu = App()->getRequest()->getPost('createSurveyMenu');
        if(empty($oSurveymenuEntries) && App()->getRequest()->getPost('createSurveyMenu')) {
            $parentMenu = 1;
            $order = 3;
            /* Find response menu */
            $oResponseSurveymenuEntries = SurveymenuEntries::model()->find("name = :name",array(":name"=>'responses'));
            if($oResponseSurveymenuEntries) {
                $parentMenu = $oResponseSurveymenuEntries->menu_id;
                $order = $oResponseSurveymenuEntries->ordering;
            }
            /* Unable to translate it currently … */
            //~ $oSurveymenuEntries = new SurveymenuEntries();
            //~ $oSurveymenuEntries->name = 'responselistandmanage';
            //~ $oSurveymenuEntries->language = 'en-GB';
            //~ $oSurveymenuEntries->title = "Responses list";
            //~ $oSurveymenuEntries->menu_title = "Responses list";
            //~ $oSurveymenuEntries->menu_description = "Responses list";
            //~ $oSurveymenuEntries->menu_icon ='list-alt',
            //~ $oSurveymenuEntries->title =
            //~ $oSurveymenuEntries->title =
            //~ $oSurveymenuEntries->title =
            $aNewMenu = array(
                'name' => 'responseslist', // Why this was cutted ????? 
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
            $iMenu = SurveymenuEntries::staticAddMenuEntry($parentMenu,$aNewMenu);
            $oSurveymenuEntries = SurveymenuEntries::model()->find("name = :name",array(":name"=>'responseslist'));
            $oSurveymenuEntries->ordering = $order;
            $oSurveymenuEntries->save();
            SurveymenuEntries::reorderMenu($parentMenu);
        }
        if(!empty($oSurveymenuEntries) && empty(App()->getRequest()->getPost('createSurveyMenu'))) {
            SurveymenuEntries::model()->deleteAll("name = :name",array(":name"=>'responseslist'));
        }
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
        /* Bad hack … or not ?*/
        //~ header("HTTP/1.1 401 Unauthorized");
        $this->_render('login');
    }

    private function _doLogout($surveyId = null)
    {
        if($surveyId && $this->_getCurrentToken($surveyId)) {
            /* admin can come from survey with token */
            $this->_setCurrentToken($surveyId,null);
        }
        if(Permission::getUserId()) {
            $beforeLogout = new PluginEvent('beforeLogout');
            App()->getPluginManager()->dispatchEvent($beforeLogout);
            regenerateCSRFToken();
            App()->user->logout();
            /* Adding afterLogout event */
            $event = new PluginEvent('afterLogout');
            App()->getPluginManager()->dispatchEvent($event);
        }
        if($surveyId) {
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
        Yii::app()->user->setState('pageSize',intval(Yii::app()->request->getParam('pageSize',Yii::app()->user->getState('pageSize',Yii::app()->params['defaultPageSize']))));
        Yii::import(get_class($this).'.models.SurveyExtended');
        $surveyModel = new SurveyExtended();
        $surveyModel->setScenario('search');
        $this->aRenderData['surveyModel'] = $surveyModel;
        /* @todo : filter by settings … */
        $filter = Yii::app()->request->getParam('SurveyExtended');
        $surveyModel->title = empty($filter['title']) ? null : $filter['title'];
        $dataProvider=$surveyModel->search();
        //$accessSettings = PluginSettings::model()->findAll …
        $this->aRenderData['adminMenu'] = $this->_getAdminMenu();
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
        $surveyId = empty($this->aRenderData['surveyId']) ? null : $this->aRenderData['surveyId'];
        if(version_compare(Yii::app()->getConfig('versionnumber'),"3.10",">=")) {
            /* Fix it to use renderMessage ! */
            $this->aRenderData['pluginName'] = $pluginName = get_class($this);
            $this->aRenderData['plugin'] = $this;
            $this->aRenderData['username'] = $this->_isLsAdmin() ? Yii::app()->user->getName() : null;
            /* @todo move it to twig if able */
            $responselist = Yii::app()->getController()->renderPartial(get_class($this).".views.content.".$fileRender,$this->aRenderData,true);
            $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttheme')));
            if($surveyId) {
                if($this->get('template','Survey',$surveyId)) {
                    $templateName = Template::templateNameFilter($this->get('template','Survey',$surveyId));
                    if($templateName == Yii::app()->getConfig('defaulttheme')) {
                        $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttheme')));
                    }
                }
            }
            Template::model()->getInstance($templateName, null);
            Template::model()->getInstance($templateName, null)->oOptions->ajaxmode = 'off';
            //~ tracevar(Template::model()->getInstance($templateName, null));
            if(empty($this->aRenderData['aSurveyInfo'])) {
                $this->aRenderData['aSurveyInfo'] = array(
                    'surveyls_title' => App()->getConfig('sitename'),
                    'name' => App()->getConfig('sitename'),
                );
            }
            $renderTwig['aSurveyInfo'] = $this->aRenderData['aSurveyInfo'];
            $renderTwig['aSurveyInfo']['name'] = sprintf($this->_translate("Reponses of %s survey"),$renderTwig['aSurveyInfo']['name']);
            $renderTwig['aSurveyInfo']['active'] = 'Y'; // Didn't show the default warning
            $renderTwig['aSurveyInfo']['showprogress'] = 'N'; // Didn't show progress bar
            $renderTwig['aSurveyInfo']['include_content'] = 'responselistandmanage';
            $renderTwig['responseListAndManage']['responselist'] = $responselist;
            App()->getClientScript()->registerPackage("bootstrap-datetimepicker");
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
            $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttheme')));
            if($surveyId) {
                if($this->get('template','Survey',$surveyId)) {
                    $templateName = Template::templateNameFilter($this->get('template','Survey',$surveyId));
                    if($templateName == Yii::app()->getConfig('defaulttheme')) {
                        $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttheme')));
                    }
                }
            }
            $this->aRenderData['oTemplate'] = $oTemplate  = Template::model()->getInstance($templateName);
            Yii::app()->clientScript->registerPackage($oTemplate->sPackageName, LSYii_ClientScript::POS_BEGIN);
            App()->getClientScript()->registerPackage("bootstrap-datetimepicker");
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
        if($surveyId) {
            Yii::app()->setConfig('surveyID',$surveyId);
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
        App()->getClientScript()->registerPackage("fontawesome");
        App()->getClientScript()->registerPackage("bootstrap-datetimepicker");
        Yii::app()->getClientScript()->registerMetaTag('width=device-width, initial-scale=1.0', 'viewport');
        App()->bootstrap->registerAllScripts();
        App()->getClientScript()->registerCssFile($assetUrl."/responselistandmanage.css");
        App()->getClientScript()->registerScriptFile($assetUrl."/responselistandmanage.js");
        $message = Yii::app()->controller->renderPartial($pluginName.".views.content.".$fileRender,$this->aRenderData,true);
        $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttemplate')));
        if($surveyId) {
            if($this->get('template','Survey',$surveyId)) {
                $templateName = Template::templateNameFilter($this->get('template','Survey',$surveyId));
                if($templateName == Yii::app()->getConfig('defaulttheme')) {
                    $templateName = Template::templateNameFilter($this->get('template',null,null,Yii::app()->getConfig('defaulttemplate')));
                }
            }
        }
        $messageHelper = new \renderMessage\messageHelper($surveyId,$templateName);
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
     * Get the administration menu
     * @param $surveyId
     * @return string html
     */
    private function _getAdminMenu($surveyId = null)
    {
        $adminAction = "";
        $showLogOut = $this->get('showLogOut',null,null,$this->settings['showLogOut']['default']);
        $showAdminSurveyLink = false;
        $showAdminLink = $this->get('showAdminLink',null,null,$this->settings['showAdminLink']['default']);
        if($surveyId) {
            $showLogOut = $this->get('showLogOut','Survey',$surveyId,$this->get('showLogOut',null,null,$this->settings['showLogOut']['default']) ? 'admin': null);
            $showAdminSurveyLink = $this->get('showSurveyAdminpageLink','Survey',$surveyId,$this->get('showAdminLink',null,null,$this->settings['showAdminLink']['default']) ? 'admin': null);
            $showAdminLink = $showAdminSurveyLink && $this->get('showAdminLink',null,null,$this->settings['showAdminLink']['default']);
        } 
        if(!Permission::getUserId()) {
            if($surveyId && $showLogOut == 'all') {
                $adminAction = CHtml::link("<i class='fa fa-sign-out' aria-hidden='true'></i> ".$this->_translate("Log out"),
                    array("plugins/direct",'plugin' => get_class(),'sid'=>$surveyId,'logout'=>"logout"),
                    array('class'=>'btn btn-default btn-sm btn-logout')
                );
            }
            return $adminAction;
        }

        if(Permission::getUserId()) {
            $actionLinks = array();
            if($showLogOut) {
                $actionLinks[] = array(
                    'text'=>"<i class='fa fa-sign-out' aria-hidden='true'></i> ".$this->_translate("Log out"),
                    'link'=> array("plugins/direct",'plugin' => get_class(),'sid'=>$surveyId,'logout'=>"logout"),
                );
            }
            if($showAdminLink) {
                $actionLinks[] = array(
                    'text'=>"<i class='fa fa-cogs' aria-hidden='true'></i> ".$this->_translate("Administration"),
                    'link'=> array("admin/index"),
                );
            }
            if($surveyId && (Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'read') || $showAdminSurveyLink == 'limesurvey') && $showAdminSurveyLink) {
                $actionLinks[] = array(
                    'text'=>"<i class='fa fa-cog' aria-hidden='true'></i> ".$this->_translate("Survey settings"),
                    'link'=>array("admin/survey/sa/view",'surveyid'=>$surveyId),
                );
            }
            if(count($actionLinks) == 1) {
                $adminAction = CHtml::link($actionLinks[0]['text'],
                        $actionLinks[0]['link'],
                        array('class'=>'btn btn-default btn-sm btn-admin')
                    );;
            }
            if(count($actionLinks) > 1) {
                $oUser = User::model()->findByPk(Permission::getUserId());
                $adminAction = '<div class="dropup">'.
                               '<button class="btn btn-default btn-sm dropdown-toggle" type="button" id="dropdownAdminAction" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.
                               $oUser->full_name.
                               '<span class="caret"></span>'.
                               '</button>'.
                               '<ul class="dropdown-menu" aria-labelledby="dropdownAdminAction">';
                $adminAction.= implode('',array_map(function($link){
                    return CHtml::tag('li',array(),CHtml::link($link['text'],$link['link']));
                },$actionLinks));
                $adminAction.= '</ul>';
                $adminAction.= '</div>';
            }
            return $adminAction;
        }

        return $adminAction; // Never happen currently
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
      $settingAllowAccess = $this->get('allowAccess','Survey',$surveyId,'all');
      $allowAccess = ($settingAllowAccess == 'all') || ($settingAllowAccess == 'admin' && $isManager);
      if(!$allowAccess) {
        return; // Leave limesurvey do
      }
      $settingAllowSee = $this->get('allowSee','Survey',$surveyId,'all');
      $allowSee = $allowAccess && (($settingAllowSee == 'all') || ($settingAllowSee == 'admin' && $isManager));
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

    /**
     * @todo : get the array of current rights
     * @param $surveyid
     * @param $token
     * @return boolean[]
     */
    private function _getCurrentRights($surveyid,$token=null)
    {

    }
}
