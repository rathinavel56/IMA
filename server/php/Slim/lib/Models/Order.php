<?php
/**
 * Product
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Order extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'orders';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'user_id',
		'product_id',
		'quantity',
		'price',
		'status',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'product_id' => 'sometimes|required',
		'quantity' => 'sometimes|required',
		'price' => 'sometimes|required',
		'status' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Product');
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
