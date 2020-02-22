<?php
/**
 * ExamAnswer
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * ExamAttend
*/
class ExamAttend extends AppModel
{
    protected $table = 'exam_attends';
    public $rules = array(
        'user_id' => 'sometimes|required',
        'exams_user_id' => 'sometimes|required',
        'exam_id' => 'sometimes|required',
    );
}
