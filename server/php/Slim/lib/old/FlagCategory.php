<?php
/**
 * FlagCategory
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Instagram API
 * @subpackage Model
 */
namespace Models;

/*
 * FlagCategory
*/
class FlagCategory extends AppModel
{
    protected $table = 'flag_categories';
    protected $fillable = array(
        'name',
        'class',
        'is_active'
    );
    public $rules = array(
        'name' => 'sometimes|required',
    );
    public $qSearchFields = array(
        'name'
    );
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['class'])) {
            $query->where('class', $params['class']);
        }
    }
}
