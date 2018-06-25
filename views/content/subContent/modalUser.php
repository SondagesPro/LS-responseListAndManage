<div class="modal fade" tabindex="-1" role="dialog" id="modal-create-token">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Create a new user and send an invite</h4>
      </div>
      <div class="modal-body">
        <?php echo CHtml::beginForm($addUser['action'],'post',array('class'=>'form-horizontal')); ?>
            <fieldset>
                <legend>User attribute</legend>
                <ul class='list-unstyled'>
                    <li class="form-group"><?php echo CHtml::label(gT("First name"),'token_firstname',array('class'=>"col-sm-4 control-label")) . CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::textField('token[firstname]','',array('class'=>'form-control'))); ?></li>
                    <li class="form-group"><?php echo CHtml::label(gT("Last name"),'token_lastname',array('class'=>"col-sm-4 control-label")) . CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::textField('token[lastname]','',array('class'=>'form-control'))); ?></li>
                    <li class="form-group"><?php echo CHtml::label(gT("Email"),'token_email',array('class'=>"col-sm-4 control-label")) . CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::textField('token[email]','',array('class'=>'form-control'))); ?></li>
                    <li>Todo : add attribute</li>
                </ul>
            </fieldset>
            <fieldset>
                <legend>Email to send</legend>
                <ul class='list-unstyled'>
                    <li class="form-group"><?php echo CHtml::label(gT("Subject"),'email_subject',array('class'=>"col-sm-4 control-label")) . CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::textField('email[subject]','',array('class'=>'form-control'))); ?></li>
                    <li class="form-group">
                        <?php echo CHtml::label(gT("Body"),'email_body',array('class'=>"col-sm-4 control-label")) .
                            CHtml::tag("div",array('class'=>"col-sm-7"),CHtml::textArea('email[body]','',array('class'=>'form-control')) . "<p class='help-block'>Some help about {URL} etc ….</p>");
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
