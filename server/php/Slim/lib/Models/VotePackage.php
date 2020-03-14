<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class VotePackage extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'vote_packages';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'vote',
		'price',
		'description',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'vote' => 'sometimes|required',
		'price' => 'sometimes|required',
		'description' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'description'
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
