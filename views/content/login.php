<!-- <h1></h1> -->
<div class="row">
<div class="col-md-6 col-md-offset-3">
<div class="panel panel-default">
    <div class="panel-heading">
      <h1 class="h3 panel-title"><?php echo $subtitle ?></h3>
    </div>
    <div class="panel-body">
        <?php
            if($error) {
                echo CHtml::tag("div",array('class'=>'alert alert-danger'),$error);
            }
            echo CHtml::beginForm();
            if (count($authPluginsList) > 1) {
                echo CHtml::tag("div",array('class'=>"form-group"),
                    CHtml::dropDownList('authMethod',$authSelectPlugin,$authPluginsList,array("class"=>'form-control'))
                );
            }
            if (count($authPluginsList) ==1) { 
                echo CHtml::hiddenField('authMethod',$authSelectPlugin);
            }
            echo CHtml::tag("div",array('class'=>"form-group"),$pluginContent);
            echo CHtml::tag("div",array('class'=>"form-group"),
                CHtml::dropDownList('lang',$lang,$languageData,array("class"=>'form-control'))
            );
            echo CHtml::htmlButton(gT("Log in"),array("type"=>'submit',"name"=>'login_submit',"value"=>"login",'class'=>"btn btn-primary btn-block"));
            echo CHtml::endForm();
        ?>
    </div>
</div>

</div>
</div>

