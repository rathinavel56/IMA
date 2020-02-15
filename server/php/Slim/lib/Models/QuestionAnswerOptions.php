<?php
/**
 * QuestionCategory
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * QuestionCategory
*/
class QuestionAnswerOptions extends AppModel
{
    protected $table = 'question_answer_options';
    public $rules = array(
        'option' => 'sometimes|required',
    );
}
