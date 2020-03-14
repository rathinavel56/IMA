<?php
/**
 * Size
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Size extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'sizes';
	public $hidden = array(
        'created_at',
        'updated_at'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'name'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'name' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function product()
    {
        return $this->belongsTo('Models\Product', 'product_id', 'id');
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
