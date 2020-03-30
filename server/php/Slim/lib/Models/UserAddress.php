<?php
/**
 * UserAddress
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class UserAddress extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user_address';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'name',
		'addressline1',
		'addressline2',
		'city',
		'state',
		'country',
		'zipcode',
		'is_default',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required',
		'addressline1' => 'sometimes|required',
		'city' => 'sometimes|required',
		'state' => 'sometimes|required',
		'country' => 'sometimes|required',
		'zipcode' => 'sometimes|required',
		'is_default' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function product()
    {
        return $this->hasMany('Models\User', 'user_id', 'id');
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
