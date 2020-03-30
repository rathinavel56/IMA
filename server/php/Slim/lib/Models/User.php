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
		'created_at',
		'updated_at',
		'company_id',
		'role_id',
		'username',
		'email',
		'mobile',
		'password',
		'user_login_count',
		'available_wallet_amount',
		'ip_id',
		'last_login_ip_id',
		'last_logged_in_time',
		'is_active',
		'first_name',
		'is_email_confirmed',
		'last_name',
		'view_count',
		'flag_count',
		'total_votes',
		'votes',
		'instagram_url',
		'tiktok_url',
		'youtube_url',
		'twitter_url',
		'facebook_url',
		'available_credit_count',
		'vote_pay_key',
		'vote_to_purchase',
		'subscription_pay_key',
		'fund_pay_key',
		'donated',
		'subscription_id',
		'paypal_email',
		'is_paypal_connect',
		'is_stripe_connect',
		'subscription_end_date',
		'device_details',
		'instant_vote_pay_key'
    );	
    public $qSearchFields = array(
        'first_name',
        'last_name',
        'username',
        'email',
    );
    public $hidden = array(
		'company_id',
		'created_at',
		'updated_at',
		'role_id',
		'username',
		'email',
		'mobile',
		'password',
		'user_login_count',
		'available_wallet_amount',
		'ip_id',
		'last_login_ip_id',
		'last_logged_in_time',
		'is_active',
		'is_email_confirmed',
		'view_count',
		'flag_count',
		'available_credit_count',
		'vote_pay_key',
		'vote_to_purchase',
		'subscription_pay_key',
		'fund_pay_key',
		'donated',
		'subscription_id',
		'paypal_email',
		'total_votes',
		'display_name',
		'instant_vote_pay_key',
		'instant_vote_to_purchase',
		'is_paypal_connect',
		'is_stripe_connect',
		//'subscription_end_date'
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
    protected $scopes_1 = array(
		'canAdmin',
		'canUser',
        'canContestantUser'
	);
    // User scope
    protected $scopes_2 = array(
        'canUser'
    );
	protected $scopes_3 = array(
        'canUser',
        'canContestantUser'
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
	public function company()
    {
        return $this->belongsTo('Models\Company', 'company_id', 'id');
    }
	public function address()
    {
        return $this->hasOne('Models\UserAddress', 'user_id', 'id')->where('is_default', true)->where('is_active', true);
    }
    public function foreign()
    {
        return $this->morphTo(null, 'class', 'foreign_id');
    }
	public function user_category()
    {
        return $this->hasOne('Models\UserCategory', 'user_id', 'id')->with('category');
    }
	public function category()
    {
		return $this->hasOne('Models\UserCategory', 'user_id', 'id')->with('category')->where('category_id', $_GET['category_id']);	
    }
	public function contest()
    {
		if (isset($_GET['contest_id']) && $_GET['contest_id'] != '') {
			return $this->hasOne('Models\UserContest', 'user_id', 'id')->where('contest_id', $_GET['contest_id']);
		} else {
			return $this->hasOne('Models\UserContest', 'user_id', 'id');
		}
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
		if (!empty($params['is_active'])) {
            $query->Where('is_active', $params['is_active']);
        }
		if (!empty($params['search'])) {
			$search = $params['search'];
			$query->where('username', 'like', "%$search%");
        }
		if (!empty($params['category_id'])) {
            $category_id = explode(',', $params['category_id']);
            $user_id = array();
            $user_categories = UserCategory::select('user_id')->whereIn('category_id', $category_id)->get()->toArray();
            foreach ($user_categories as $user_category) {
                $user_id[] = $user_category['user_id'];
            }
            $query->whereIn('id', $user_id);
        }
		if (!empty($params['contest_id'])) {
            $constests = explode(',', $params['contest_id']);
            $user_id = array();
			$user_constests = UserContest::whereIn('contest_id', $constests)->get()->toArray();
			foreach ($user_constests as $user_constest) {
                $user_id[] = $user_constest['user_id'];
            }
            $query->whereIn('id', $user_id);
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
