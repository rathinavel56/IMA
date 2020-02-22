<?php
/**
 * User
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class User extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';
    protected $fillable = array(
        'username',
        'email',
        'password',
        'is_agree_terms_conditions',
        'is_active',
        'role_id',
        'gender_id',
        'is_email_confirmed',
        'first_name',
        'last_name',
        'add_fund',
        'deduct_fund',
        'zip_code',
        'hourly_rate',
        'designation',
        'about_me',
        'full_address',
        'is_have_unreaded_activity',
		'instagram_url',
		'tiktok_url',
		'youtube_url',
		'twitter_url',
		'facebook_url'
    );
	
    public $qSearchFields = array(
        'first_name',
        'last_name',
        'username',
        'email',
    );
    public $hidden = array(
        'role_id',
        'password',
        'email',
        'available_wallet_amount',
        'ip_id',
        'last_login_ip_id',
        'last_logged_in_time',
        'is_agree_terms_conditions',
        'is_active',
        'total_amount_withdrawn',
        'available_credit_count',
        'total_credit_bought',
		'scope',
		'is_email_confirmed',
		'display_name',
		'gender_id',
		'contest_user_count',
		'view_count',
		'follower_count',
		'flag_count',
		'total_rating_as_employer',
		'review_count_as_employer',
		'address1',
		'city_id',
		'state_id',
		'country_id',
		'zip_code',
		'latitude',
		'longitude',
		'full_address',
		'expired_balance_credit_points',
		'is_made_deposite',
		'hourly_rate',
		'total_spend_amount_as_employer',
		'about_me',
		'blocked_amount',
		'is_have_unreaded_activity',
		'user_login_count'
    );
    public $rules = array(
       'username' => [
                'sometimes',
                'required',
                'min:3',
                'max:15',
                'regex:/^[A-Za-z][A-Za-z0-9]*(?:_[A-Za-z0-9]+)*$/',
            ],
        'email' => 'sometimes|required|email',
        'password' => [
                'sometimes',
                'required',
                'min:3',
                'max:15'
            ]
    );
    protected $scopes_1 = array();
    // User scope
    protected $scopes_2 = array(
        'canUser',
        'canAdmin'
    );
    /**
     * To check if username already exist in user table, if so generate new username with append number
     *
     * @param string $username User name which want to check if already exsist
     *
     * @return mixed
     */
    public function checkUserName($username)
    {
        $userExist = User::where('username', $username)->first();
        if (count($userExist) > 0) {
            $org_username = $username;
            $i = 1;
            do {
                $username = $org_username . $i;
                $userExist = User::where('username', $username)->first();
                if (count($userExist) < 0) {
                    break;
                }
                $i++;
            } while ($i < 1000);
        }
        return $username;
    }
    public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'UserAvatar');
    }
    public function foreign_attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->select('id', 'filename', 'class', 'foreign_id')->where('class', 'UserAvatar');
    }
    public function cover_photo()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'CoverPhoto');
    }
    public function role()
    {
        return $this->belongsTo('Models\Role', 'role_id', 'id');
    }
    public function foreign()
    {
        return $this->morphTo(null, 'class', 'foreign_id');
    }
    public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['is_email_confirmed'])) {
            $query->where('is_email_confirmed', $params['is_email_confirmed']);
        }
        if (!empty($params['role_id'])) {
            $query->Where('role_id', $params['role_id']);
        }
		if (!empty($params['search'])) {
			$search = $params['search'];
			$query->where('username', 'like', "%$search%");
        }
        if (!empty($authUser) && !empty($authUser['role_id'])) {
            if ($authUser['role_id'] != \Constants\ConstUserTypes::Admin) {
                $query->where('role_id', '!=', \Constants\ConstUserTypes::Admin);
            }
            if (!empty($params['role']) && $params['role'] == 'freelancer') {
                $query->whereIn('role_id', array(
                    \Constants\ConstUserTypes::User,
                    \Constants\ConstUserTypes::Freelancer
                ));
            } elseif (!empty($params['role']) && $params['role'] == 'employer') {
                $query->whereIn('role_id', array(
                    \Constants\ConstUserTypes::User,
                    \Constants\ConstUserTypes::Employer
                ));
            } elseif (!empty($params['role']) && $params['role'] == 'admin') {
                if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin) {
                    $query->where('role_id', \Constants\ConstUserTypes::Admin);
                }
            }
        } else {
            $query->where('role_id', '!=', \Constants\ConstUserTypes::Admin);
        }
    }
}
