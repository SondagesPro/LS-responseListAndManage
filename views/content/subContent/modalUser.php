<div class="modal fade" tabindex="-1" role="dialog" id="modal-create-token">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"></h4>
      </div>
      <div class="modal-body">
        <?php echo CHtml::beginForm($addUserAction,'post'); ?>
            <ul>
                <li><?php echo CHtml::label(gT("First name"),'firstname',array()) . CHtml::textField('firstname','',array()); ?>
                <li><?php echo CHtml::label(gT("Last name"),'lastname',array()) . CHtml::textField('lastname','',array()); ?>
                <li><?php echo CHtml::label(gT("Email"),'email',array()) . CHtml::textField('lastname','',array()); ?>
            </ul>
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
