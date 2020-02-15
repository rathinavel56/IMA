<?php
/**
 * JobStatus
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * JobStatus
*/
class JobStatus extends AppModel
{
    protected $table = 'job_statuses';
    public $rules = array();
    public $qSearchFields = array(
        'name'
    );
}
