<?php
/**
 * ContestStatus
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * ContestStatus
*/
class ContestStatus extends AppModel
{
    protected $table = 'contest_statuses';
    protected $fillable = array(
        'message'
    );
    public $rules = array(
        'message' => 'sometimes|required',
    );
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $search = $params['q'];
            $query->orWhere('name', 'ilike', "%$search%");
            $query->orWhere('message', 'ilike', "%$search%");
        }
    }
}
