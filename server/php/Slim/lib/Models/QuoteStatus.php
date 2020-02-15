<?php
/**
 * QuoteStatus
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * QuoteStatus
*/
class QuoteStatus extends AppModel
{
    protected $table = 'quote_statuses';
    private $rules = array();
    public function getQuoteStatusSlugNameById($id)
    {
        $name = '';
        $quoteStatus = QuoteStatus::select('name')->where('id', $id)->first();
        if (!empty($quoteStatus)) {
            $name = \Inflector::slug(strtolower($quoteStatus->name), '-');
        }
        return $name;
    }
}
