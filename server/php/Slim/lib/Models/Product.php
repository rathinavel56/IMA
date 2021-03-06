<?php
/**
 * Product
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Product extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'products';
	public $hidden = array(
        'created_at',
        'updated_at',
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
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required',
		'description' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id')->with('attachment');
    }
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Product');
    }
	public function attachments()
    {
        return $this->hasMany('Models\Attachment', 'foreign_id', 'id')->where('class', 'Product');
    }
	public function colors()
    {
        return $this->hasMany('Models\ProductColor', 'product_id', 'id')->where('is_active', true);
    }
	public function details()
    {
        return $this->hasMany('Models\ProductDetail', 'product_id', 'id')->where('is_active', true)->with('attachments', 'sizes', 'amount_detail');
    }
	public function cart()
    {
		global $authUser;
		return $this->hasOne('Models\Cart', 'product_id', 'id')->with('product_sizes', 'product_colors')->where('is_purchase', false)->where('user_id', $authUser->id);
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
		if (!empty($params['id'])) {
            $query->Where('id', $params['id']);
        }
		if (!empty($params['user_id'])) {
            $query->Where('user_id', $params['user_id']);
        }
    }
}
