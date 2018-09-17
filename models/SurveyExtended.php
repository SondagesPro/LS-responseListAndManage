<?php
/**
 * This file is part of reloadAnyResponse plugin
 */


class SurveyExtended extends Survey
{

    public $title;

    /** @inheritdoc */
    public static function model($className=__CLASS__) {
        return parent::model($className);
    }

    /**
     * @return CActiveDataProvider
     */
    public function search()
    {
        $pageSize = Yii::app()->user->getState('pageSize', Yii::app()->params['defaultPageSize']);
        $sort = new CSort();
        $sort->attributes = array(
            'correct_relation_defaultlanguage.surveyls_title',
            'datecreated',
        );
        $sort->defaultOrder = array('datecreated' => CSort::SORT_DESC); 

        $criteria = new CDbCriteria;
        $criteria->condition = "active='Y'";
        $criteria->with = array('correct_relation_defaultlanguage');

        // Permission
        // Note: reflect Permission::hasPermission
        if (!Permission::model()->hasGlobalPermission("surveys", 'read')) {
            $criteriaPerm = new CDbCriteria;
            // Multiple ON conditions with string values such as 'survey'
            $criteriaPerm->mergeWith(array(
                'join'=>"LEFT JOIN {{permissions}} AS permissions ON (permissions.entity_id = t.sid AND permissions.permission='survey' AND permissions.entity='survey' AND permissions.uid='".Yii::app()->user->id."') ",
            ));
            $criteriaPerm->compare('t.owner_id', Yii::app()->user->id, false);
            $criteriaPerm->compare('permissions.read_p', '1', false, 'OR');
            $criteria->mergeWith($criteriaPerm, 'AND');
        }
        // Search filter
        $criteria->compare('correct_relation_defaultlanguage.surveyls_title', $this->title,true);
    
        $dataProvider = new CActiveDataProvider('SurveyExtended', array(
            'sort'=>$sort,
            'criteria'=>$criteria,
            'pagination'=>array(
                'pageSize'=>$pageSize,
            ),
        ));
        return $dataProvider;
    }

}
