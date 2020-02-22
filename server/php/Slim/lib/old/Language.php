<?php
/**
 * Language
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class Language extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'languages';
    protected $fillable = array(
        'name',
        'iso2',
        'iso3',
        'is_active'
    );
    public $rules = array(
        'name' => 'sometimes|required',
        'iso2' => 'sometimes|required|max:2',
        'iso3' => 'sometimes|required|max:3'
    );
    public $qSearchFields = array(
        'name',
        'iso2',
        'iso3'
    );
}
