<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Cart extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'carts';
    public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'user_id',
		'created_at',
		'updated_at',
		'product_detail_id',
		'quantity',
		'pay_key',
		'pay_status',
		'addressline1',
		'addressline2',
		'city',
		'state',
		'country',
		'zipcode',
		'price',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'product_detail_id' => 'sometimes|required',
		'quantity' => 'sometimes|required',
		'is_active' => 'sometimes|required',
		'pay_key' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'product_detail_id')->where('class', 'Product');
		// ->where('is_primary', true)
    }
	public function detail()
    {
        return $this->belongsTo('Models\ProductDetail', 'product_detail_id', 'id')->with('attachment', 'product');
    }
	public function size()
    {
        return $this->belongsTo('Models\ProductSize', 'product_size_id', 'id')->with('size');
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
