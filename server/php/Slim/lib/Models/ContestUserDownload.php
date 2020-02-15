<?php
/**
 * ContestUserDownload
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * ContestUserDownload
*/
class ContestUserDownload extends AppModel
{
    protected $table = 'contest_user_downloads';
    public $rules = array();
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $search = $params['q'];
            $query->orWhereHas('user', function ($q) use ($search) {
                $q->where('username', 'ilike', "%$search%");
            });
            $query->orWhereHas('contest_user', function ($q) use ($search) {
                $q->where('description', 'ilike', "%$search%");
            });
        }
    }
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
    public function contest_user()
    {
        return $this->belongsTo('Models\ContestUser', 'contest_user_id', 'id');
    }
    public function ip()
    {
        return $this->belongsTo('Models\Ip', 'ip_id', 'id');
    }
}
