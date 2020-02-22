<?php
/**
 * ContestType
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * Vault
*/
class Vault extends AppModel
{
    protected $table = 'vaults';
    protected $fillable = array(
        'vault_key',
        'vault_id',
        'email',
        'address',
        'city',
        'state',
        'country',
        'zip_code',
        'phone',
        'is_primary',
        'credit_card_expire',
        'expire_month',
        'expire_year',
        'cvv2',
        'first_name',
        'last_name',
        'payment_type'
    );
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id')->with('attachment', 'country', 'state', 'city');
    }
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['user_id'])) {
            $query->where('user_id', '=', $params['user_id']);
        }
    }
}
