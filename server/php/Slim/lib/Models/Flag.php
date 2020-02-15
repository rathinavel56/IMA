<?php
/**
 * Flag
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Instagram API
 * @subpackage Model
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

/*
 * Flag
*/
class Flag extends AppModel
{
    protected $table = 'flags';
    protected $fillable = array(
        'foreign_id',
        'class',
        'flag_category_id',
        'message'
    );
    public $rules = array(
        'flag_category_id' => 'sometimes|required'
    );
    public function portfolio()
    {
        return $this->belongsTo('Models\Portfolio', 'portfolio_id', 'id')->with('user', 'attachment');
    }
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
    public function flag_category()
    {
        return $this->belongsTo('Models\FlagCategory', 'flag_category_id', 'id');
    }
    public function ip()
    {
        return $this->belongsTo('Models\Ip', 'ip_id', 'id');
    }
    public function attachment()
    {
        return $this->hasMany('Models\Attachment', 'foreign_id', 'photo_id');
    }
    public function foreign_flag()
    {
        return $this->morphTo(null, 'class', 'foreign_id');
    }
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->orWhereHas('user', function ($q) use ($params) {
                $q->orWhere('username', 'like', '%' . $params['q'] . '%');
            });
        }
        if (!empty($params['filter'])) {
            $query->where('type', $params['filter']);
        }
        if (!empty($params['class'])) {
            $query->where('class', $params['class']);
        }
        if (!empty($params['foreign_id'])) {
            $query->where('foreign_id', $params['foreign_id']);
        }
    }
    protected static function boot()
    {
        Relation::morphMap(['ContestUser' => ContestUser::class , 'Contest' => Contest::class , 'Job' => Job::class , 'User' => User::class , 'Portfolio' => Portfolio::class , 'QuoteService' => QuoteService::class , 'Project' => Project::class , ]);
    }
}
