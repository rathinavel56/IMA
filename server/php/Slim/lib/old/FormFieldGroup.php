<?php
/**
 * FormFieldGroup
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * QuoteFormField
*/
class FormFieldGroup extends AppModel
{
    protected $table = 'form_field_groups';
    protected $fillable = array(
        'name',
        'foreign_id',
        'info'
    );
    public $rules = array(
        'name' => 'sometimes|required',
        'contest_type_id' => 'sometimes|required',
    );
    public function form_fields()
    {
        return $this->hasMany('Models\FormField', 'form_field_group_id')->with('input_types');
    }
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where('name', 'ilike', '%' . $params['q'] . '%');
        }
        if (!empty($params['class'])) {
            $query->where('class', $params['class']);
        }
        if (!empty($params['foreign_id'])) {
            $query->where('foreign_id', $params['foreign_id']);
        }
    }
}
