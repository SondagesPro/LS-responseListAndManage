<?php
$this->widget('bootstrap.widgets.TbGridView', array(
    'dataProvider'=>$dataProvider,
    'id'            => 'surveys-grid',
    'ajaxUpdate'    => 'surveys-grid',
    //~ 'ajaxType'      => 'POST',
    'template'      => "{items}\n<div class='row'><div class='col-sm-4 tools-form'>{$adminMenu}</div><div class='col-sm-4'>{pager}</div><div class='col-sm-4'>{summary}</div></div>",
    'summaryText'   => gT('Displaying {start}-{end} of {count} result(s).').' '.
        sprintf(gT('%s rows per page'),
            CHtml::dropDownList(
                'pageSize',
                Yii::app()->user->getState('pageSize'),
                Yii::app()->params['pageSizeOptions'],
                array(
                    'class'=>'changePageSize form-control input-sm',
                    'style'=>'display: inline; width: auto',
                    'onchange'=>"$.fn.yiiGridView.update('surveys-grid',{ data:{ pageSize: $(this).val() }})",
                ))
        ),
    'columns'=>array(
        array(
            'class'=>"CLinkColumn",
            'header'=>gT("Id"),
            'labelExpression'=>'$data["sid"]',
            'urlExpression' => 'Yii::app()->createUrl("plugins/direct", array("plugin" =>"'.$pluginName.'","sid"=>$data["sid"]))',
        ),
        array(
            'name'=>'correct_relation_defaultlanguage.surveyls_title',
            'header'=>gT("Title"),
            //~ 'filterInputOptions' => array('class'=>'form-control input-sm filter-id'),
            'filter'=>CHtml::textField("SurveyExtended[title]", $surveyModel->title, array('id'=>false)),
        ),
        array(
            'name'=>'datecreated',
            'header'=>gT("Date created"),
            //~ 'filterInputOptions' => array('class'=>'form-control input-sm filter-id'),
            'filter'=>false,
        ),
    ),
    'filter'=>$surveyModel,
    
));
?>
