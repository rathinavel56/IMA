<?php
/**
 * Provider
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class Provider extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'providers';
    protected $fillable = array(
        'name',
        'secret_key',
        'api_key',
        'is_active'
    );
    public $rules = array(
        'name' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (empty($params['filter'])) {
            $query->where('is_active', 1);
        }
    }
}
