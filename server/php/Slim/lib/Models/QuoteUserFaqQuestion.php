<?php
/**
 * QuoteUserFaqQuestion
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * QuoteUserFaqQuestion
*/
class QuoteUserFaqQuestion extends AppModel
{
    protected $table = 'quote_user_faq_questions';
    protected $fillable = array(
        'user_id',
        'question'
    );
    public $rules = array(
        //'user_id' => 'sometimes|required',
        
    );
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $search = $params['q'];
            $query->WhereHas('user', function ($q) use ($search) {
                $q->where('username', 'ilike', "%$search%");
            });
            $query->orWhere('question', 'ilike', "%$search%");
        }
    }
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
}
