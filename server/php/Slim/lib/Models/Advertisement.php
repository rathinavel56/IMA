<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Advertisement extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'advertisements';
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
    protected $fillable = array(
        'id',
		'user_id',
		'created_at',
		'updated_at',
		'url',
		'page_number',
		'block_no',
		'price',
		'description',
		'is_approved'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'url' => 'sometimes|required',
		'page_number' => 'sometimes|required',
		'block_no' => 'sometimes|required',
		'price' => 'sometimes|required',
		'description' => 'sometimes|required',
		'is_approved' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Advertisement');
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
