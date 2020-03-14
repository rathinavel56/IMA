<?php
/**
 * Product
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class UserContest extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user_contests';
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
		'contest_id',
		'user_id',
		'instant_votes',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'contest_id' => 'sometimes|required',
		'instant_votes' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function contest()
    {
        return $this->belongsTo('Models\Contest', 'contest_id', 'id');
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
