<?php
namespace Models;
class TaxForm extends AppModel
{
    protected $table = 'tax_form';
    protected $fillable = array(
        'id',
		'user_id',
		'beneficial_owner',
		'citizenship',
		'permanent_add',
		'city1',
		'country1',
		'mailing_address',
		'city2',
		'country2',
		'taxpayer',
		'foreign_tax',
		'reference_number',
		'dob',
		'resident',
		'paragraph',
		'percentage',
		'income',
		'additional_conditions',
		'print_name',
		'capacity_acting',
		'updated_at'
    );
    public $rules = array(
        'name' => 'sometimes|required',
		'id' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'beneficial_owner' => 'sometimes|required',
		'citizenship' => 'sometimes|required',
		'permanent_add' => 'sometimes|required',
		'city1' => 'sometimes|required',
		'country1' => 'sometimes|required',
		'city2' => 'sometimes|required',
		'country2' => 'sometimes|required',
		'taxpayer' => 'sometimes|required',
		'foreign_tax' => 'sometimes|required',
		'reference_number' => 'sometimes|required',
		'dob' => 'sometimes|required',
		'resident' => 'sometimes|required',
		'paragraph' => 'sometimes|required',
		'print_name' => 'sometimes|required',
		'capacity_acting' => 'sometimes|required',
		'updated_at' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];
                
            });
        }
    }
}
