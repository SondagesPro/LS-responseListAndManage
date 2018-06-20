<?php
$this->widget('bootstrap.widgets.TbGridView', array(
    'dataProvider'=>$dataProvider,
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
        ),
    ),
));
?>
