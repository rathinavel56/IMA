<?php
/**
 * SalaryType
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * SalaryType
*/
class SalaryType extends AppModel
{
    protected $table = 'salary_types';
    protected $fillable = array(
        'name',
        'is_active'
    );
    public $rules = array(
        'name' => 'sometimes|required',
    );
    public $qSearchFields = array(
        'name'
    );
}
