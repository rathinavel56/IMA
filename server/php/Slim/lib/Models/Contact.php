<?php
/**
 * Contact
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class Contact extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'contacts';
    protected $fillable = array(
        'first_name',
        'last_name',
        'email',
        'phone',
        'subject',
        'message'
    );
    public $rules = array(
        'first_name' => 'sometimes|required',
        'last_name' => 'sometimes|required',
        'email' => 'sometimes|required|email',
        'phone' => 'sometimes|required',
        'subject' => 'sometimes|required',
        'message' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'first_name',
        'last_name',
        'email'
    );
    public function ip()
    {
        return $this->belongsTo('Models\Ip', 'ip_id', 'id');
    }
    public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->orWhereHas('ip', function ($q) use ($params) {
                $q->where('ip', 'ilike', '%' . $params['q'] . '%');
            });
        }
    }
}
