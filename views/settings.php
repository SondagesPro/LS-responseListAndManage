<?php echo CHtml::beginForm();?>
    <div class="clearfix h3 pagetitle"><?php echo $title; ?>
      <div class='pull-right'>
        <?php
          if(Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'update')) {
            echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
            echo " ";
            echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-default'));
            echo " ";
            echo CHtml::link(gT('Reset'),$form['reset'],array('class'=>'btn btn-danger'));
            echo " ";
            echo CHtml::link(gT('Close'),$form['close'],array('class'=>'btn btn-danger'));
          } else {
            echo CHtml::link(gT('Close'),$form['close'],array('class'=>'btn btn-default'));
          }
        ?>
      </div>
    </div>
        <?php if($warningString) {
            echo CHtml::tag("p",array('class'=>'alert alert-warning'),$warningString);
        } ?>
        <?php foreach($aSettings as $legend=>$settings) {
          $this->widget('ext.SettingsWidget.SettingsWidget', array(
                //'id'=>'summary',
                'title'=>$legend,
                //'prefix' => 'responseListAndManage', // Inalid label before 3.13.3
                'form' => false,
                'formHtmlOptions'=>array(
                    'class'=>'form-core',
                ),
                'labelWidth'=>6,
                'controlWidth'=>6,
                'settings' => $settings,
            ));
        } ?>
        <div class='row'>
          <div class='col-md-offset-6 submit-buttons'>
            <?php
              if(Permission::model()->hasSurveyPermission($surveyId, 'surveysettings', 'update')) {
                echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
                echo " ";
                echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-default'));
                echo " ";
                echo CHtml::link(gT('Reset'),$form['reset'],array('class'=>'btn btn-danger'));
                echo " ";
                echo CHtml::link(gT('Close'),$form['close'],array('class'=>'btn btn-danger'));
              } else {
                echo CHtml::link(gT('Close'),$form['close'],array('class'=>'btn btn-default'));
              }
            ?>
          </div>
        </div>
        <?php echo CHtml::endForm();?>
    </div>
</div>
<?php
  Yii:app()->clientScript->registerScriptFile($assetUrl.'/responselistandmanage.js',CClientScript::POS_END);
?>
