<?php
/**
 * DiscountType
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * DiscountType
*/
class DiscountType extends AppModel
{
    protected $table = 'discount_types';
    protected $fillable = array(
        'id',
        'created_at',
        'updated_at',
        'name',
    );
    public $rules = array();
}
