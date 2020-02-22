<?php
/**
 * ZazPaymentGateway
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

class ZazpayPaymentGateway extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'zazpay_payment_gateways';
    public function zazpay_group()
    {
        return $this->belongsTo('Models\ZazpayPaymentGroup', 'zazpay_payment_group_id', 'id');
    }
    public function zazpayCallGetGateways($zazpay)
    {
        $s = getZazPayObject();
        return $s->callGetGateways();
    }
}
