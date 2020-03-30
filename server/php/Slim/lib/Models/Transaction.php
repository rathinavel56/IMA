<?php
/**
 * Transaction
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Transaction extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'transactions';
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id')->select('id', 'first_name', 'last_name')->with('attachment');
    }
    public function other_user()
    {
        return $this->belongsTo('Models\User', 'to_user_id', 'id')->select('id', 'first_name', 'last_name')->with('attachment');
    }
	public function detail()
    {
        return $this->belongsTo('Models\ProductDetail', 'foreign_id', 'id')->select('id','product_id')->with('attachment', 'product_detail');
    }
	public function package()
    {
        return $this->belongsTo('Models\VotePackage', 'foreign_id', 'id');
    }
	public function subscription()
    {
        return $this->belongsTo('Models\Subscription', 'foreign_id', 'id');
    }
    public function foreign_transaction()
    {
        return $this->morphTo(null, 'class', 'foreign_id');
    }
    public function ip()
    {
        return $this->belongsTo('Models\Ip', 'ip_id', 'id');
    }
    public function payment_gateway()
    {
        return $this->belongsTo('Models\PaymentGateway', 'payment_gateway_id', 'id');
    }
    public function zazpay_gateway()
    {
        return $this->belongsTo('Models\ZazpayPaymentGateway', 'zazpay_gateway_id', 'id');
    }
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['class'])) {
            $query->where('class', $params['class']);
        }
        if (!empty($params['foreign_id'])) {
            $query->where('foreign_id', $params['foreign_id']);
        }
        if (!empty($params['to_user_id'])) {
            $query->where('to_user_id', $params['to_user_id']);
        }
        if (!empty($params['type'])) {
            $now = date('Y-m-d h:i:s');
            if ($params['type'] == 'today') {
                $today = date('Y-m-d 00:00:00');
                $query->whereDate('created_at', '>=', $today);
            } elseif ($params['type'] == 'this_week') {
                $this_week_first_day = date('Y-m-d 00:00:00', strtotime('last Sunday', strtotime($now)));
                $this_week_last_day = date('Y-m-d 23:59:59', strtotime('next Saturday', strtotime($now)));
                $query->whereDate('created_at', '>=', $this_week_first_day);
                $query->whereDate('created_at', '<=', $this_week_last_day);
            } elseif ($params['type'] == 'this_month') {
                $this_month_first_day = date('Y-m-d 00:00:00', strtotime('first day of this month', strtotime($now)));
                $this_month_last_day = date('Y-m-d 23:59:59', strtotime('last day of this month', strtotime($now)));
                $query->whereDate('created_at', '>=', $this_month_first_day);
                $query->whereDate('created_at', '<=', $this_month_last_day);
            } elseif ($params['type'] == 'custom') {
                if (!empty($params['from_date']) && !empty($params['to_date'])) {
                    $query->whereDate('created_at', '>=', $params['from_date']);
                    $query->whereDate('created_at', '<=', $params['to_date']);
                }
            }
        }
    }
    protected static function boot()
    {
        Relation::morphMap(['Contest' => Contest::class]);
    }
}
