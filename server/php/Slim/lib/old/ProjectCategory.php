<?php
/**
 * ProjectCategory
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * ProjectCategory
*/
class ProjectCategory extends AppModel
{
    protected $table = 'project_categories';
    protected $fillable = array(
        'name',
        'is_active',
        'icon_class'
    );
    public $rules = array(
        'name' => 'sometimes|required',
        'is_active' => 'sometimes|required|boolean',
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];
                $q1->orwhere('project_categories.name', 'like', "%$search%");
            });
        }
    }
}
