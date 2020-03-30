<?php
/**
 * ProductSize
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class ProductSize extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'product_sizes';
	public $hidden = array(
        'created_at',
        'updated_at',
		'product_detail_id',
		'is_active',
		'size_id',
		'discount_percentage',
		'coupon_code'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'product_detail_id',
		'size_id',
		'is_active',
		'price',
		'quantity',
		'discount_percentage',
		'coupon_code'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'product_detail_id' => 'sometimes|required',
		'size_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function size()
    {
        return $this->belongsTo('Models\Size', 'size_id', 'id');
    }
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
