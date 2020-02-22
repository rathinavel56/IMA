<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Company extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'companies';
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
    protected $fillable = array(
        'id',
		'user_id',
		'created_by',
		'created_at',
		'updated_at',
		'name',
		'url',
		'description',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
        'created_by' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required',
		'url' => 'sometimes|required',
		'description' => 'sometimes|required',
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
