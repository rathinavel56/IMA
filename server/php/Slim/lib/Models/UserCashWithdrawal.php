<?php
/**
 * UserCashWithdrawals
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class UserCashWithdrawal extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user_cash_withdrawals';
    protected $fillable = array(
        'user_id',
        'amount',
        'remark',
        'status',
        'money_transfer_account_id',
        'withdrawal_status_id'
    );
    public $rules = array(
        'user_id' => 'sometimes|required|integer',
        'money_transfer_account_id' => 'sometimes|required|integer',
        'amount' => 'sometimes|required',
        'status' => 'sometimes'
    );
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id')->select('id', 'username', 'email');
    }
    public function money_transfer_account()
    {
        return $this->belongsTo('Models\MoneyTransferAccount', 'money_transfer_account_id', 'id');
    }
    public function activity()
    {
        return $this->belongsTo('Models\UserCashWithdrawal', 'id', 'id')->select('id', 'user_id', 'money_transfer_account_id')->with('foreign_user', 'money_transfer_account');
    }
    public function foreign_user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id')->select('id', 'username')->with('foreign_attachment');
    }
    protected static function boot()
    {
        global $authUser;
        parent::boot();
        static ::addGlobalScope('user', function (\Illuminate\Database\Eloquent\Builder $builder) use ($authUser) {
            if ($authUser['role_id'] != \Constants\ConstUserTypes::Admin) {
                $builder->where('user_id', $authUser['id']);
            }
        });
        self::saving(function ($data) use ($authUser) {
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin || $authUser['id'] == $data->user_id) {
                return true;
            }
            return false;
        });
        self::deleting(function ($data) use ($authUser) {
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin || $authUser['id'] == $data->user_id) {
                return true;
            }
            return false;
        });
    }
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }
        if (!empty($params['withdrawal_status_id'])) {
            $query->where('withdrawal_status_id', $params['withdrawal_status_id']);
        }
		if (!empty($params['account'])) {
            $query->where(function ($q2) use ($params) {
                $search = $params['account'];
				if($search == "payoneer") {
					$q2->orWhereHas('money_transfer_account', function ($q) use ($search) {
						$q->where('money_transfer_accounts.account', "$search");
					});
				} else {
					$search = "payoneer";
					$q2->orWhereHas('money_transfer_account', function ($q) use ($search) {
						$q->where('money_transfer_accounts.account','!=', "$search");
					});
				}
            });
        }
        if (!empty($params['money_transfer_account_id'])) {
            $query->where('money_transfer_account_id', $params['money_transfer_account_id']);
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
