<?php
/**
 * City
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class City extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cities';
    protected $fillable = array(
        'country_id',
        'state_id',
        'name',
        'code',
        'is_active'
    );
    public $rules = array(
        'name' => 'sometimes|required',
    );
    public function state()
    {
        return $this->belongsTo('Models\State', 'state_id', 'id')->select('id', 'name');
    }
    public function country()
    {
        return $this->belongsTo('Models\Country', 'country_id', 'id')->select('id', 'name', 'iso_alpha2');
    }
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->Where(function ($q1) use ($params) {
                $q1->orWhereHas('country', function ($q) use ($params) {
                    $q->where('countries.name', 'ilike', '%' . $params['q'] . '%');
                });
                $q1->orWhereHas('state', function ($q) use ($params) {
                    $q->Where('states.name', 'ilike', '%' . $params['q'] . '%');
                });
                $q1->orWhere('cities.name', 'ilike', '%' . $params['q'] . '%');
            });
        }
    }
}
