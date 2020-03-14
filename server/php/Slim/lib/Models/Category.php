<?php
/**
 * Category
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Category extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'categories';
	public $hidden = array(
        'created_at',
        'updated_at',
		'description',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'user_id',
		'created_at',
		'updated_at',
		'name',
		'description',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required',
		'description' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];                
            });
        }
    }
}
