<?php
/**
 * ProductDetail
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class ProductDetail extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'product_details';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'product_id',
		'product_color_id',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'product_id' => 'sometimes|required',
		'product_color_id' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function sizes()
    {
        return $this->hasMany('Models\ProductSize', 'product_detail_id', 'id')->select('id', 'product_detail_id', 'size_id')->with('size')->where('quantity', '<>', 0)->where('size_id', '<>', 0);
    }
	public function amount_detail()
    {
        return $this->hasOne('Models\ProductSize', 'product_detail_id', 'id')->where('quantity', '<>', 0);
    }
	public function attachments()
    {
        return $this->hasMany('Models\Attachment', 'foreign_id', 'product_id')->where('class', 'Product');
    }
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'product_detail_id', 'id')->where('class', 'Product');
    }
	public function product()
    {
        return $this->belongsTo('Models\Product', 'product_id', 'id')->with('user');
    }
	public function product_detail()
    {
        return $this->belongsTo('Models\Product', 'product_id', 'id')->select('id', 'name');
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
