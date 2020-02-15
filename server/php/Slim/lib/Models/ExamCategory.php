<?php
/**
 * ExamCategory
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * ExamCategory
*/
class ExamCategory extends AppModel
{
    protected $table = 'exam_categories';
    protected $fillable = array(
        'name'
    );
    public $rules = array(
        'name' => 'sometimes|required',
        'exam_count' => 'sometimes|required',
    );
    public function exam()
    {
        return $this->hasMany('Models\Exam', 'id', 'exam_category_id ')->with('attachment', 'exam_level');
    }
}
