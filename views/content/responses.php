<?php
if(!empty($description)) {
    echo CHtml::tag("div",array("class"=>"clearfix"),$description);
}
$ajaxUpdate = 'responses-grid';
$ajaxType = 'POST';
if(App()->getConfig('debug') > 1){
    $ajaxUpdate = false;
    $ajaxType = 'GET';
}
tracevar(array_keys($columns));
$this->widget('bootstrap.widgets.TbGridView', array(
    'dataProvider'=>$model->search(),
    'columns'=>$columns,
    'filter'=>$model,
    'itemsCssClass' => 'table table-condensed table-hover table-striped',
    'id'            => 'responses-grid',
    'ajaxUpdate'    => $ajaxUpdate,
    'ajaxType'      => $ajaxType,
    'template'      => "{items}\n<div class='row'><div class='col-sm-4 tools-form text-left'>{$adminAction}{$addNew}{$addUserButton}</div><div class='col-sm-4 text-center'>{pager}</div><div class='col-sm-4 text-right'>{summary}</div></div>",
    'summaryText'   => gT('Displaying {start}-{end} of {count} result(s).').' '.
        sprintf(gT('%s rows per page'),
            CHtml::dropDownList(
                'pageSize',
                Yii::app()->user->getState('responseListAndManagePageSize'),
                Yii::app()->params['pageSizeOptions'],
                array(
                    'class'=>'changePageSize form-control input-sm',
                    'style'=>'display: inline; width: auto',
                    'onchange'=>"$.fn.yiiGridView.update('responses-grid',{ data:{ pageSize: $(this).val() }})",
                ))
        ),
    'afterAjaxUpdate' => "js:function(id,data){ $('#'+id).trigger('ajaxUpdated'); }",
    'selectableRows' => $selectableRows,
));
echo App()->twigRenderer->renderPartial('/subviews/navigation/responseListAndManage_modalSurvey.twig', $responseListAndManage);

if($allowAddUser) {
    Yii::app()->getController()->renderPartial("responseListAndManage.views.content.subContent.modalUser",array('lang'=>$lang,'addUser'=>$addUser));
}
echo $pluginHtml;
