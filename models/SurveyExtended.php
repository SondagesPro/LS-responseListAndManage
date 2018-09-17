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
            'survey_id'=>array(
                'asc'=>'t.sid asc',
                'desc'=>'t.sid desc',
            ),
            'title'=>array(
                'asc'=>'correct_relation_defaultlanguage.surveyls_title asc',
                'desc'=>'correct_relation_defaultlanguage.surveyls_title desc',
            ),
            'creation_date'=>array(
                'asc'=>'t.datecreated asc',
                'desc'=>'t.datecreated desc',
            ),

            'owner'=>array(
                'asc'=>'owner.users_name asc',
                'desc'=>'owner.users_name desc',
            ),
        );
        $sort->defaultOrder = array('creation_date' => CSort::SORT_DESC);

        $criteria = new LSDbCriteria;
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
        $dataProvider = new CActiveDataProvider('SurveyExtended', array(
            'sort'=>$sort,
            'criteria'=>$criteria,
            'pagination'=>array(
                'pageSize'=>$pageSize,
            ),
        ));
        // Search filter
        $criteria->compare('correct_relation_defaultlanguage.surveyls_title', $this->title,true);
        return $dataProvider;
    }

}
