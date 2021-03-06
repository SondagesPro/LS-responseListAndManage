<?php
if(!empty($description)) {
    echo CHtml::tag("div",array("class"=>"clearfix"),$description);
}
$this->widget('bootstrap.widgets.TbGridView', array(
    'dataProvider'=>$model->search(),
    'columns'=>$columns,
    'filter'=>$model,
    'itemsCssClass'=>'table-condensed',
    'id'            => 'responses-grid',
    'ajaxUpdate'    => 'responses-grid',
    //'ajaxUpdate' => false,
    //~ 'htmlOptions'   => array('class'=>'grid-view table-responsive'),
    'ajaxType'      => 'POST',
    'template'      => "{items}\n<div class='row'><div class='col-sm-4 tools-form'>{$adminAction}{$addNew}{$addUserButton}</div><div class='col-sm-4'>{pager}</div><div class='col-sm-4'>{summary}</div></div>",
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
