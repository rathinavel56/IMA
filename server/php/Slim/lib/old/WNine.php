<?php
namespace Models;
class WNine extends AppModel
{
    protected $table = 'w9';
    protected $fillable = array(
        'from_2_0',
		'from_2_1',
		'from_2_2',
		'from_2_3',
		'from_2_4',
		'from_2_5',
		'from_2_6',
		'from_2_7',
		'from_2_8',
		'from_2_9',
		'from_2_10',
		'from_2_11',
		'from_2_12',
		'from_2_13',
		'from_2_14',
		'from_2_15',
		'from_2_16',
		'from_2_17',
		'from_2_18',
		'from_2_19',
		'from_2_20',
		'from_2_21',
		'from_2_22',
		'from_2_23',
		'from_2_24',
		'from_2_25',
		'from_2_26',
		'from_2_27',
		'from_2_28',
		'from_2_29',
		'from_2_30',
		'from_2_31',
		'from_2_32',
		'from_2_33',
		'from_2_34'
    );
    public $rules = array(
        'name' => 'sometimes|required',
		'id' => 'sometimes|required',
		'user_id' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];                
            });
        }
    }
}
