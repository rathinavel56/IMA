<?php
/**
 * Product
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class UserCategory extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user_categories';
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
		'user_id',
		'created_at',
		'updated_at',
		'category_id',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'category_id' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'user_id')->where('class', 'UserAvatar');
    }
	public function category()
    {
        return $this->belongsTo('Models\Category', 'category_id', 'id');
    }
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
		if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];                
            });
        }
    }
}
