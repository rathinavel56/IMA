<?php
/**
 * Role
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class Role extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'roles';
	public $hidden = array(
						'created_at',
						'updated_at',
						'is_active'
					);
}
