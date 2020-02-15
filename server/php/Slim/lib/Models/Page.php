<?php
/**
 * Page
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class Page extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pages';
    protected $fillable = array(
        'title',
        'content',
        'meta_keywords',
        'description_meta_tag',
        'is_default'
    );
    public $rules = array(
        'title' => 'sometimes|required',
        'content' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'title'
    );
}
