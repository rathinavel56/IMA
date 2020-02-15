<?php
/**
 * ContestTypesPricingPackage
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * ContestTypesPricingPackage
*/
class ContestTypesPricingPackage extends AppModel
{
    protected $table = 'contest_types_pricing_packages';
    protected $fillable = array(
        'contest_type_id',
        'pricing_package_id',
        'price',
        'participant_commision',
        'maximum_entry_allowed'
    );
    public $rules = array(
        'contest_type_id' => 'sometimes|required',
        'pricing_package_id' => 'sometimes|required',
    );
    public function contest_type()
    {
        return $this->belongsTo('Models\ContestType', 'contest_type_id', 'id');
    }
    public function pricing_package()
    {
        return $this->belongsTo('Models\PricingPackage', 'pricing_package_id', 'id');
    }
}
