<?php
/**
 * Publication
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * Publication
*/
class Publication extends AppModel
{
    protected $table = 'publications';
    protected $fillable = array(
        'title',
        'publisher',
        'description'
    );
    public $rules = array(
        'title' => 'sometimes|required',
        'publisher' => 'sometimes|required',
        'description' => 'sometimes|required',
    );
    public $qSearchFields = array(
        'title'
    );
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id')->with('attachment');
    }
    public function scopeFilter($query, $params = array())
    {
        $authUser = array();
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
    }
    protected static function boot()
    {
        global $authUser;
        self::saving(function ($data) use ($authUser) {
            if (($authUser['role_id'] == \Constants\ConstUserTypes::Admin) || ($authUser['id'] == $data->user_id)) {
                return true;
            }
            return false;
        });
        self::deleting(function ($data) use ($authUser) {
            if (($authUser['role_id'] == \Constants\ConstUserTypes::Admin) || ($authUser['id'] == $data->user_id)) {
                return true;
            }
            return false;
        });
        self::created(function ($publication) {
            User::where('id', $publication->user_id)->increment('publication_count');
        });
        self::deleted(function ($publication) {
            User::where('id', $publication->user_id)->decrement('publication_count');
        });
    }
}
