<div class="modal fade" tabindex="-1" role="dialog" id="modal-create-token">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Create a new user and send an invite</h4>
      </div>
      <div class="modal-body">
        <?php echo CHtml::beginForm($addUser['action'],'post',array('class'=>'form-horizontal')); ?>
            <div id="create-token-errors"></div>
            <fieldset>
                <legend>User attribute</legend>
                <ul class='list-unstyled'>
                    <li class="form-group"><?php echo CHtml::label(gT("First name"),'tokenattribute_firstname',array('class'=>"col-sm-4 control-label")) . CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::textField('tokenattribute[firstname]','',array('class'=>'form-control','data-default'=>''))); ?></li>
                    <li class="form-group"><?php echo CHtml::label(gT("Last name"),'tokenattribute_lastname',array('class'=>"col-sm-4 control-label")) . CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::textField('tokenattribute[lastname]','',array('class'=>'form-control','data-default'=>''))); ?></li>
                    <li class="form-group"><?php echo CHtml::label(gT("Email"),'tokenattribute_email',array('class'=>"col-sm-4 control-label")) . CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::emailField('tokenattribute[email]','',array('class'=>'form-control','data-default'=>'','required'=>'required'))); ?></li>
                    <?php foreach($addUser['attributes'] as $attribute=>$aAttribute) {
                        echo CHtml::tag("li",array('class'=>"form-group"),
                            CHtml::label($aAttribute['caption'],'tokenattribute_'.$attribute,array('class'=>"col-sm-4 control-label"))
                            . CHtml::tag("div",array('class'=>"col-sm-7"),
                                CHtml::textField('tokenattribute['.$attribute.']','',array('class'=>'form-control','data-default'=>'','required'=>($aAttribute['mandatory'] == 'Y')))
                            )
                        );
                    }?>
                    <?php if($addUser["attributeGroup"]) {
                        $label = CHtml::label($addUser["attributeGroup"]["caption"],'tokenattribute_'.$addUser["attributeGroup"]["attribute"],array('class'=>"col-sm-4 control-label"));
                        $field = CHtml::textField('tokenattribute['.$addUser["attributeGroup"]["attribute"].']','',array('class'=>'form-control','data-default'=>'','required'=>($addUser["attributeGroup"]['mandatory'] == 'Y')));
                        echo CHtml::tag("hr");
                        echo CHtml::tag("li",array('class'=>"form-group"),
                            $label
                            . CHtml::tag("div",array('class'=>"col-sm-7"),
                                $field
                            )
                        );
                    }?>
                    <?php if($addUser["tokenAttributeGroupManager"]) {
                        //$field = CHtml::checkBox('tokenattribute['.$addUser["tokenAttributeGroupManager"]["attribute"].']',false,array('class'=>''));
                        $label = CHtml::label($addUser["tokenAttributeGroupManager"]["caption"],'tokenattribute_',array('class'=>"col-sm-4 control-label"));
                        $field = CHtml::textField('tokenattribute['.$addUser["tokenAttributeGroupManager"]["attribute"].']','',array('class'=>'form-control','data-default'=>''));
                        echo CHtml::tag("li",array('class'=>"form-group"),
                            $label
                            . CHtml::tag("div",array('class'=>"col-sm-7"),
                                $field
                            )
                        );
                    }?>
                </ul>
            </fieldset>
            <fieldset>
                <legend>Email to send</legend>
                <ul class='list-unstyled'>
                    <li class="form-group"><?php
                        echo CHtml::label(gT("Subject"),'emailsubject',array('class'=>"col-sm-4 control-label"))
                            . CHtml::tag("div",
                                array('class'=>"col-sm-7"),
                                CHtml::textField('emailsubject',$addUser['email']['subject'],array('class'=>'form-control','data-default'=>$addUser['email']['subject']))
                            );
                    ?></li>
                    <li class="form-group">
                        <?php
                            $textArea = Chtml::textArea('email[body]',$addUser['email']['body'],array('rows'=>20,'class'=> 'form-control','data-default'=>$addUser['email']['body']));
                            if($addUser['email']['html']) {
                                $textArea = Yii::app()->getController()->widget('yiiwheels.widgets.html5editor.WhHtml5Editor', array(
                                    'name' => 'emailbody',
                                    'value' => $addUser['email']['body'],
                                    'pluginOptions' => array(
                                        'html' => true,
                                        'lists' => false,
                                        'image'=>false,
                                        'link'=>false,
                                        'useLineBreaks'=>false,// False broke reading …
                                        'autoLink'=>true,
                                        'parserRules'=>'js:wysihtml5ParserRules'
                                    ),
                                    'htmlOptions' => array(
                                        'class'=> 'form-control',
                                        'data-default'=>$addUser['email']['body'],
                                    ),
                                ),true);
                            }
                            echo CHtml::label(gT("Body"),'emailbody',array('class'=>"col-sm-4 control-label")) .
                            CHtml::tag("div",array('class'=>"col-sm-7"),
                            $textArea . "<p class='help-block'>".$addUser['email']['help']."</p>");
                        ?>
                    </li>

                </ul>
            </fieldset>
            <div class="form-group">
                <div class="col-sm-offset-4 col-sm-8">
                    <?php echo CHtml::htmlButton("Create and send",array('type'=>'submit','class'=>"btn btn-primary")); ?>
                </div>
            </div>
        <?php echo CHtml::endForm(); ?>
        </div>
      <div class="modal-footer">
        <?php
          echo CHtml::htmlButton($lang['Close'],array('type'=>'button','class'=>"btn btn-warning",'data-dismiss'=>"modal"));
        ?>
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
