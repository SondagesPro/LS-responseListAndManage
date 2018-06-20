<?php
/**
 * Description
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license AGPL v3
 * @version 0.0.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
namespace getQuestionInformation\helpers;
use Yii;
use PDO;
use Survey;
use Question;
use QuestionAttribute;
use Answer;

Class surveyColumnsInformation
{
    /**
    /* Get an array with DB column name key and EM code for value or columns information for 
     * @param integer $iSurvey
     * @param string $language
     * @param string $language
     * @return null|array
     */
    public static function getAllQuestions($iSurvey,$language=null,$columns=false)
    {
        // First get all question
        $oSurvey = Survey::model()->findByPk($iSurvey);
        if(!$oSurvey) {
          return null;
        }
        if(!$language || !in_array($language,$oSurvey->getAllLanguages()) {
            $language = $oSurvey->language;
        }
        $questionTable = Question::model()->tableName();
        $command = Yii::app()->db->createCommand()
            ->select("qid")
            ->from($questionTable)
            ->where("($questionTable.sid = :sid AND {{questions}}.language = :language AND $questionTable.parent_qid = 0)")
            ->join('{{groups}}', "{{groups}}.gid = {{questions}}.gid  AND {{questions}}.language = {{groups}}.language")
            ->bindParam(":sid", $iSurvey, PDO::PARAM_INT)
            ->bindParam(":language", $language, PDO::PARAM_STR)
            ->order("{{groups}}.group_order asc, {{questions}}.question_order asc");
        $allQuestions = $command->query()->readAll();
        $aColumnsToCode = array();
        foreach ($allQuestions as $aQuestion) {
            $aColumnsToCode = array_merge($aColumnsToCode,self::getQuestionInformations($aQuestion['qid'],$columns));
        }
        return $aColumnsToCode;
    }

    /**
     * return array  with DB column name key and EM code for value for one question
     * @param integer $qid
     * @throw Exception if debug
     * @return array|null
     */
    private static function getQuestionColumns($qid,$language) {
        Yii::import('application.helpers.viewHelper');
        $oQuestion = Question::model()->find("qid=:qid AND language=:language",array(":qid"=>$qid,":language"=>$language));
        if(!$oQuestion) {
            if(defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function.');
            }
            return null;
        }
        if($oQuestion->parent_qid) {
            if(defined('YII_DEBUG') && YII_DEBUG) {
                throw new Exception('Invalid question iQid in getQuestionColumnToCode function. This function must be call only for parent question.');
            }
            return null;
        }
        $language = $oQuestion->language;
        $aColumnsInfo = array();
        switch(self::getTypeFromType($oQuestion->type)) {
            case 'single':
                $aColumnsInfo[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid]= array(
                    'id'=>$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid,
                    'name'>"<strong>[{$oQuestion->title}]</strong> <small>".viewHelper::flatEllipsizeText($oQuestion->text,true,40,'…',0.6)."<small>",
                );
                    //'value' => 'ResponseExtended::getAnswerValue($data)',
                break;
            case 'dual':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#0"]=$oQuestion->title."_".$oSubQuestion->title."_0";
                        $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title."#1"]=$oQuestion->title."_".$oSubQuestion->title."_1";
                    }
                }
                break;
            case 'sub':
                $oSubQuestions = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestions) {
                    foreach($oSubQuestions as $oSubQuestion) {
                        $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestion->title]=$oQuestion->title."_".$oSubQuestion->title;
                    }
                }
                break;
            case 'ranking':
                $oQuestionAttribute = QuestionAttribute::model()->find(
                    "qid = :qid AND attribute = 'max_subquestions'",
                    array(':qid' => $oQuestion->qid)
                );
                if($oQuestionAttribute) {
                    $maxAnswers = intval($oQuestionAttribute->value);
                }
                if(empty($maxAnswers)) {
                    $maxAnswers = intval(Answer::model()->count(
                        "qid=:qid and language=:language",
                        array(":qid"=>$oQuestion->qid,":language"=>$oQuestion->language)
                    ));
                }
                for ($count = 1; $count <= $maxAnswers; $count++) {
                    $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$count]=$oQuestion->title."_".$count;
                }
                break;
            case 'upload':
                $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid]=$oQuestion->title;
                $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."_filecount"]=$oQuestion->title."_filecount";
                break;
            case 'double':
                $oSubQuestionsY = Question::model()->findAll(array(
                    'select'=>'title',
                    'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=0",
                    'order'=>'question_order asc',
                    'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                ));
                if($oSubQuestionsY) {
                    foreach($oSubQuestionsY as $oSubQuestionY) {
                        $oSubQuestionsX = Question::model()->findAll(array(
                            'select'=>'title',
                            'condition'=>"sid=:sid and language=:language and parent_qid=:qid and scale_id=1",
                            'order'=>'question_order asc',
                            'params'=>array(":sid"=>$oQuestion->sid,":language"=>$language,":qid"=>$oQuestion->qid),
                        ));
                        if($oSubQuestionsX) {
                            foreach($oSubQuestionsX as $oSubQuestionX) {
                                $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid.$oSubQuestionY->title."_".$oSubQuestionX->title]=$oQuestion->title."_".$oSubQuestionY->title."_".$oSubQuestionX->title;
                            }
                        }
                    }
                }
                break;
            case 'none':
                // Nothing needed
                break;
            default:
                if(defined('YII_DEBUG') && YII_DEBUG) {
                    throw new Exception(sprintf('Unknow question type %s.',$oQuestion->type));
                }  
                // NUll
        }
        if(self::allowOther($oQuestion->type) and $oQuestion->other=="Y") {
            $aColumnsToCode[$oQuestion->sid."X".$oQuestion->gid.'X'.$oQuestion->qid."other"]=$oQuestion->title."_other";
        }
        if(self::haveComment($oQuestion->type)) {
            $aCommentColumns=array();
            foreach($aColumnsToCode as $column => $code) {
                $aCommentColumns[$column."comment"] = $code."comment";
            }
            $aColumnsToCode = array_merge($aColumnsToCode,$aCommentColumns);
        }
        return $aColumnsInfo;
    }
    /**
     * retunr the global type by question type
     * @param string $type
     * @return string|null
     */
    private static function getTypeFromType($type) {
        $questionTypeByType = array(
            "1" => 'dual',
            "5" => 'single',
            "A" => 'sub',
            "B" => 'sub',
            "C" => 'sub',
            "D" => 'single',
            "E" => 'sub',
            "F" => 'sub',
            "G" => 'single',
            "H" => 'sub',
            "I" => 'single',
            "K" => 'single',
            "L" => 'single',
            "M" => 'sub',
            "N" => 'single',
            "O" => 'single',
            "P" => 'sub',
            "Q" => 'sub',
            "R" => 'ranking',
            "S" => 'single',
            "T" => 'single',
            "U" => 'single',
            "X" => 'single',
            "Y" => 'single',
            "!" => 'single',
            ":" => 'double',
            ";" => 'double',
            "|" => 'upload',
            "*" => 'single',
            "X" => 'none',
        );
        if(!isset($questionTypeByType[$type])) {
            return null;
        }
        return $questionTypeByType[$type];
    }
    /**
     * @param \Question
     * return array|null
     */
    private static getAnswers($oQuestion) {
        switch ($oQuestion->type) {

        }
    }
    /**
     * @param string $type
     * return boolean
     */
    private static function haveComment($type){
        $haveComment = array('O','P');
        return in_array($type,$haveComment);
    }
    /**
     * @param string $type
     * return boolean
     */
    private static function allowOther($type){
        $allowOther = array("L","!","P","M");
        return in_array($type,$allowOther);
    }

    /**
     * get the filter for this column
     * @param Question
     * @param $scale
     * return array|null
     */
    public static function getFilter($oQuestion,$scale=null)
    {
        switch ($oQuestion->type) {
            case "L":
            case "!":
            case "O":
            case "^":
            case "I":
            case "R":

                break:

            default:
                return null;
        }
    }

}
