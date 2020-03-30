<?php
/**
 * Attachment
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class Attachment extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'attachments';
	public $hidden = array(
        'created_at',
        'updated_at',
		'description',
		'filesize',
		'is_admin_approval',
		'is_primary'
    );
	public function thumb()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'UserProfileVideoImage');
    }
}
