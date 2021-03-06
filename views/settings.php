<div class="row">
    <div class="col-lg-12 content-right">
        <?php if($warningString) {
            echo CHtml::tag("h3",array(),$title);
        } ?>
        <?php if($warningString) {
            echo CHtml::tag("p",array('class'=>'alert alert-warning'),$warningString);
        } ?>
        <?php echo CHtml::beginForm();?>
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
              echo CHtml::htmlButton('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'save','class'=>'btn btn-primary'));
              echo " ";
              echo CHtml::htmlButton('<i class="fa fa-check-circle-o " aria-hidden="true"></i> '.gT('Save and close'),array('type'=>'submit','name'=>'save'.$pluginClass,'value'=>'redirect','class'=>'btn btn-default'));
              echo " ";
              echo CHtml::link(gT('Close'),Yii::app()->createUrl('admin/survey',array('sa'=>'view','surveyid'=>$surveyId)),array('class'=>'btn btn-danger'));
            ?>
            <div class='hidden' style='display:none'>
              <div data-moveto='surveybarid' class='pull-right hidden-xs hidden'>
              <?php
              echo CHtml::link('<i class="fa fa-check" aria-hidden="true"></i> '.gT('Save'),"#",array('class'=>'btn btn-primary','data-click-name'=>'save'.$pluginClass,'data-click-value'=>'save'));
              echo " ";
              echo CHtml::link('<i class="fa fa-check-circle-o" aria-hidden="true"></i> '.gT('Save and close'),"#",array('class'=>'btn btn-default','data-click-name'=>'save'.$pluginClass,'data-click-value'=>'redirect'));
              echo " ";
              echo CHtml::link(gT('Close'),Yii::app()->createUrl('admin/survey',array('sa'=>'view','surveyid'=>$surveyId)),array('class'=>'btn btn-danger'));
              ?>
              </div>
            </div>
          </div>
        </div>
        <?php echo CHtml::endForm();?>
    </div>
</div>
<?php
  Yii:app()->clientScript->registerScriptFile($assetUrl.'/responselistandmanage.js',CClientScript::POS_END);
?>
