<?php
/**
 * ProjectBidInvoiceItems
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * ProjectBidInvoiceItems
*/
class ProjectBidInvoiceItems extends AppModel
{
    protected $table = 'project_bid_invoice_items';
    protected $fillable = array(
        'amount',
        'description'
    );
    public $rules = array();
}
