<?php
namespace Models;
class Keyword extends AppModel
{
    protected $table = 'keywords';
    protected $fillable = array(
        'created_at',
		'updated_at',
		'skill_id',
        'is_active'
    );
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
    }
}
