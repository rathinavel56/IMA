<?php
/**
 * QuoteCategoryQuoteService
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * QuoteCategoryQuoteService
*/
class QuoteCategoryQuoteService extends AppModel
{
    protected $table = 'quote_categories_quote_services';
    public function quote_categories()
    {
        return $this->belongsTo('Models\QuoteCategory', 'quote_category_id', 'id');
    }
}
