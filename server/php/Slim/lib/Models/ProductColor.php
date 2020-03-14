<?php
/**
 * ProductColor
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class ProductColor extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'product_colors';
	public $hidden = array(
        'created_at',
        'updated_at',
		'product_id',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'product_id',
		'color',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'product_id' => 'sometimes|required',
		'color' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
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
