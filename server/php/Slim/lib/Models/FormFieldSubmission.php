<?php
/**
 * FormFieldSubmission
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * FormFieldSubmission
*/
class FormFieldSubmission extends AppModel
{
    protected $table = 'form_field_submissions';
    protected $fillable = array(
        'field'
    );
    public $rules = array();
    public function form_field()
    {
        return $this->hasMany('Models\FormField', 'id', 'form_field_id')->with('input_types');
    }
}
