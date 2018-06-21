<?php
$this->widget('bootstrap.widgets.TbGridView', array(
    'dataProvider'=>$model->search(),
    'columns'=>$columns,
    'filter'=>$model,
    'itemsCssClass'=>'table-condensed',
    'id'            => 'responses-grid',
    'ajaxUpdate'    => 'responses-grid',
    //~ 'ajaxUpdate' => false,
    //~ 'htmlOptions'   => array('class'=>'grid-view table-responsive'),
    'ajaxType'      => 'POST',
    'template'      => "{items}\n<div class='row'><div class='col-sm-4'>{$addNew}</div><div class='col-sm-4'>{pager}</div><div class='col-sm-4'>{summary}</div></div>",
    'summaryText'   => gT('Displaying {start}-{end} of {count} result(s).').' '.
        sprintf(gT('%s rows per page'),
            CHtml::dropDownList(
                'pageSize',
                Yii::app()->user->getState('pageSize'),
                Yii::app()->params['pageSizeOptions'],
                array(
                    'class'=>'changePageSize form-control',
                    'style'=>'display: inline; width: auto',
                    'onchange'=>"$.fn.yiiGridView.update('responses-grid',{ data:{pageSize: $(this).val() }})",
                ))
        ),
    //~ 'afterAjaxUpdate' => "console.log($('#responses-grid > .row').height())",
    //~ 'selectableRows'=>2,
));
?>
<div class="modal fade" tabindex="-1" role="dialog" id="modal-survey-update">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title"></h4>
      </div>
      <div class="modal-body">
        <iframe id="survey-update" name="survey-update" src="" frameborder="0"></iframe>
      </div>
      <div class="modal-footer">
        <?php
          echo CHtml::htmlButton($lang['Close'],array('type'=>'button','class'=>"btn btn-warning",'data-dismiss'=>"modal"));
          if(!empty($lang['Delete'])) {
            echo CHtml::htmlButton($lang['Delete'],array('type'=>'button','class'=>"btn btn-danger",'data-action'=>"clearall",'disabled'=>true));
          }
          if(!empty($lang['Previous'])) {
            echo CHtml::htmlButton($lang['Previous'],array('type'=>'button','class'=>"btn btn-default",'data-action'=>"moveprevious",'disabled'=>true));
          }
          if(!empty($lang['Save'])) {
            echo CHtml::htmlButton($lang['Save'],array('type'=>'button','class'=>"btn btn-info",'data-action'=>"saveall",'disabled'=>true));
          }
          if(!empty($lang['Next'])) {
            echo CHtml::htmlButton($lang['Next'],array('type'=>'button','class'=>"btn btn-primary",'data-action'=>"movenext",'disabled'=>true));
          }
          if(!empty($lang['Submit'])) {
            echo CHtml::htmlButton($lang['Submit'],array('type'=>'button','class'=>"btn btn-success",'data-action'=>"movesubmit",'disabled'=>true));
          }
        ?>
      </div>
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
