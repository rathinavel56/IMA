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
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
	public $hidden = array(
        'created_at',
        'updated_at',
		'pay_key',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'user_id',
		'created_at',
		'updated_at',
		'product_id',
		'quantity',
		'pay_key',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'product_id' => 'sometimes|required',
		'quantity' => 'sometimes|required',
		'is_active' => 'sometimes|required',
		'pay_key' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'product_id')->where('class', 'Product')->where('is_primary', true);
    }
	public function product()
    {
        return $this->belongsTo('Models\Product', 'product_id', 'id');
    }
	public function product_sizes()
    {
        return $this->belongsTo('Models\ProductSize', 'product_id', 'id')->with('size');
	}
	public function product_colors()
    {
        return $this->belongsTo('Models\ProductColor', 'product_id', 'id');
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
