<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Contest extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'contests';
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
		'end_date',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required',
		'end_date' => 'sometimes|required',
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
