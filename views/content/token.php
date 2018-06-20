<!-- <h1></h1> -->
<div class="row">
<div class="col-md-6 col-md-offset-3">
<div class="panel panel-default">
    <div class="panel-heading">
      <h1 class="h3 panel-title"><?php echo $subtitle ?></h3>
    </div>
    <div class="panel-body">
        <?php
            echo CHtml::beginForm('','get');
            echo CHtml::tag("div",array('class'=>"form-group"),
                CHtml::textField('token','',array("class"=>'form-control','required'=>true))
            );
            if(count($languageData) > 1) {
                echo CHtml::tag("div",array('class'=>"form-group"),
                    CHtml::dropDownList('lang',$lang,$languageData,array("class"=>'form-control'))
                );
            }
            echo CHtml::htmlButton(gT("Log in"),array("type"=>'submit',"name"=>'login_submit',"value"=>"login",'class'=>"btn btn-primary btn-block"));
            echo CHtml::endForm();
            if($adminLoginUrl) {
                echo CHtml::link(gT("Admin log in"),$adminLoginUrl,array('class'=>"btn btn-sm btn-link btn-block"));
            }
        ?>
    </div>
</div>

</div>
</div>

