<?php
/**
 * UserLogin
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * UserLogin
*/
class UserLogin extends AppModel
{
    protected $table = 'user_logins';
    public $rules = array();
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
    public function ip()
    {
        return $this->belongsTo('Models\Ip', 'ip_id', 'id');
    }
    protected static function boot()
    {
        parent::boot();
        self::created(function ($log_user) {
            User::where('id', $log_user->user_id)->increment('user_login_count', 1);
        });
    }
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['ip_id'])) {
            $query->where('ip_id', $params['ip_id']);
        }
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];
                $q1->orWhereHas('user', function ($q) use ($search) {
                    $q->where('users.username', 'ilike', "%$search%");
                });
            });
        }
    }
}
