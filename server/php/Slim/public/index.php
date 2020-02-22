<?php
/**
 * Base API
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 */
require_once '../lib/bootstrap.php';
use Illuminate\Database\Capsule\Manager as Capsule;
use Mpdf\Mpdf;
/**
 * GET oauthGet
 * Summary: Get site token
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/oauth/token', function ($request, $response, $args) {
    $post_val = array(
        'grant_type' => 'client_credentials',
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET
    );
    $response = getToken($post_val);
    return renderWithJson($response);
});
/**
 * GET oauthRefreshTokenGet
 * Summary: Get site refresh token
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/oauth/refresh_token', function ($request, $response, $args) {
    $post_val = array(
        'grant_type' => 'refresh_token',
        'refresh_token' => $_GET['token'],
        'client_id' => OAUTH_CLIENT_ID,
        'client_secret' => OAUTH_CLIENT_SECRET
    );
    $response = getToken($post_val);
    return renderWithJson($response);
});
/**
 * POST usersRegisterPost
 * Summary: new user
 * Notes: Post new user.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/register', function ($request, $response, $args) {
    global $_server_domain_url;
    $args = $request->getParsedBody();
    $result = array();
    $user = new Models\User;
    unset($user->location);
    unset($user->city_name);
    unset($user->state_name);
    unset($user->country);
    $validationErrorFields = $user->validate($args);
    if (!empty($validationErrorFields)) {
        $validationErrorFields = $validationErrorFields->toArray();
    }
    //get country, state and city ids
    if (!empty($args['full_address']) && empty($validationErrorFields)) {
        if (!empty($args['country']['iso_alpha2'])) {
            $user->country_id = findCountryIdFromIso2($args['country']['iso_alpha2']);
            if (!empty($args['state']['name'])) {
                $user->state_id = findOrSaveAndGetStateId($args['state']['name'], $user->country_id);
            }
            if (!empty($args['city']['name'])) {
                $user->city_id = findOrSaveAndGetCityId($args['city']['name'], $user->country_id, $user->state_id);
            }
        } else {
            $validationErrorFields['required'] = array();
            array_push($validationErrorFields['required'], 'Country');
        }
    }
    if (checkAlreadyUsernameExists($args['username']) && empty($validationErrorFields)) {
        $validationErrorFields['unique'] = array();
        array_push($validationErrorFields['unique'], 'username');
    }
    if (checkAlreadyEmailExists($args['email']) && empty($validationErrorFields)) {
        $validationErrorFields['unique'] = array();
        array_push($validationErrorFields['unique'], 'email');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields['required'])) {
        unset($validationErrorFields['required']);
    }
    if (empty($validationErrorFields)) {
        foreach ($args as $key => $arg) {
            if ($key == 'password') {
                $user->{$key} = getCryptHash($arg);
            } else {
                $user->{$key} = $arg;
            }
        }
        unset($user->country);
        unset($user->state);
        unset($user->city);
        unset($user->image);
        unset($user->cover_photo);
        try {
            $user->is_email_confirmed = (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1) ? 0 : 1;
            $user->is_active = (USER_IS_ADMIN_ACTIVATE_AFTER_REGISTER == 1) ? 0 : 1;
            if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                $user->is_email_confirmed = 1;
                $user->is_active = 1;
            }
            if (!empty($args['register']) && $args['register'] == 'company') {
                $user->role_id = \Constants\ConstUserTypes::Employer;
            } elseif (!empty($args['register']) && $args['register'] == 'constestant') {
                $user->role_id = \Constants\ConstUserTypes::Freelancer;
            } else {
                $user->role_id = \Constants\ConstUserTypes::User;
            }
			
            unset($user->is_employer);
            unset($user->is_freelancer);
            $user->save();
            if (isPluginEnabled('Common/Subscription')) {
                $quoteCreditPurchasePlan = Models\CreditPurchasePlan::where('is_welcome_plan', 1)->where('is_active', 1)->first();
                if (!empty($quoteCreditPurchasePlan)) {
                    $quoteCreditPurchaseLogs = new Models\CreditPurchaseLog;
                    $quoteCreditPurchaseLogs->user_id = $user->id;
                    $quoteCreditPurchaseLogs->price = $quoteCreditPurchasePlan->price;
                    $quoteCreditPurchaseLogs->credit_count = !empty($quoteCreditPurchasePlan->no_of_credits) ? $quoteCreditPurchasePlan->no_of_credits : 0;
                    $quoteCreditPurchaseLogs->discount_percentage = !empty($quoteCreditPurchasePlan->discount_percentage) ? $quoteCreditPurchasePlan->discount_percentage : 0;
                    $quoteCreditPurchaseLogs->original_price = !empty($quoteCreditPurchasePlan->original_price) ? $quoteCreditPurchasePlan->original_price : 0;
                    $quoteCreditPurchaseLogs->is_active = 1;
                    $quoteCreditPurchaseLogs->credit_purchase_plan_id = $quoteCreditPurchasePlan->id;
                    $quoteCreditPurchaseLogs->is_payment_completed = 1;
                    if (!empty($quoteCreditPurchasePlan->day_limit)) {
                        $quoteCreditPurchaseLogs->expiry_date = date('Y-m-d h:i:s', strtotime("+" . $quoteCreditPurchasePlan->day_limit . " days"));
                    }
                    $quoteCreditPurchaseLogs->save();
                    $quoteUser = Models\User::find($quoteCreditPurchaseLogs->user_id);
                    $quoteUser = $quoteUser->makeVisible(array(
                        'available_credit_count',
                    ));
                    $quoteUser->available_credit_count = $quoteCreditPurchaseLogs->credit_count;
                    $quoteUser->update();
                }
            }
            if (!empty($args['image'])) {
                saveImage('UserAvatar', $args['image'], $user->id);
            }
            if (!empty($args['cover_photo'])) {
                saveImage('CoverPhoto', $args['cover_photo'], $user->id);
            }
            // send to admin mail if USER_IS_ADMIN_MAIL_AFTER_REGISTER is true
            if (USER_IS_ADMIN_MAIL_AFTER_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##USEREMAIL##' => $user->email,
                    '##SUPPORT_EMAIL##' => SUPPORT_EMAIL
                );
                sendMail('newuserjoin', $emailFindReplace, SITE_CONTACT_EMAIL);
            }
            if (USER_IS_WELCOME_MAIL_AFTER_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##SUPPORT_EMAIL##' => SUPPORT_EMAIL
                );
                // send welcome mail to user if USER_IS_WELCOME_MAIL_AFTER_REGISTER is true
                sendMail('welcomemail', $emailFindReplace, $user->email);
            }
            if (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1) {
                $emailFindReplace = array(
                    '##USERNAME##' => $user->username,
                    '##ACTIVATION_URL##' => $_server_domain_url . '/activation/' . $user->id . '/' . md5($user->username)
                );
                sendMail('activationrequest', $emailFindReplace, $user->email);
            }
            if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                $scopes = '';
                if (isset($user->role_id) && $user->role_id == \Constants\ConstUserTypes::User) {
                    $scopes = implode(' ', $user['user_scopes']);
                } else {
                    $scopes = '';
                }
                $post_val = array(
                    'grant_type' => 'password',
                    'username' => $user->username,
                    'password' => $user->password,
                    'client_id' => OAUTH_CLIENT_ID,
                    'client_secret' => OAUTH_CLIENT_SECRET,
                    'scope' => $scopes
                );
                $response = getToken($post_val);
                $result = $response + $user->toArray();
            } else {
                $enabledIncludes = array(
                    'attachment',
                    'cover_photo'
                );
                $user = Models\User::with($enabledIncludes)->find($user->id);
                $result = $user->toArray();
            }
            return renderWithJson($result);
        } catch (Exception $e) {
			// $e->getMessage()
            return renderWithJson($result, 'User could not be added. Please, try again.', '', 1);
        }
    } else {
		if (!empty($validationErrorFields)) {
			foreach ($validationErrorFields as $key=>$value) {
				if ($key == 'unique') {
					return renderWithJson($result, ucfirst($value[0]).' already exists. Please, try again login.', '', 1);
				} else if (!empty($value[0]) && !empty($value[0]['numeric'])) {
					return renderWithJson($result, $value[0]['numeric'], '', 1);
				} else {
					return renderWithJson($result, $value[0], '', 1);
				}
				break;
			}
		} else {
			return renderWithJson($result, 'User could not be added. Please, try again.', $validationErrorFields, 1);
		}
    }
});
/**
 * PUT usersUserIdActivationHashPut
 * Summary: User activation
 * Notes: Send activation hash code to user for activation. \n
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users/activation/{userId}/{hash}', function ($request, $response, $args) {
    $result = array();
    $user = Models\User::where('id', $request->getAttribute('userId'))->first();
    if (!empty($user)) {
        if($user->is_email_confirmed != 1) {
            if (md5($user['username']) == $request->getAttribute('hash')) {
                $user->is_email_confirmed = 1;
                $user->is_active = (USER_IS_ADMIN_ACTIVATE_AFTER_REGISTER == 0 || USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) ? 1 : 0;
                $user->save();
                if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                    $scopes = '';
                    if (isset($user->role_id) && $user->role_id == \Constants\ConstUserTypes::User) {
                        $scopes = implode(' ', $user['user_scopes']);
                    } else {
                        $scopes = '';
                    }
                    $post_val = array(
                        'grant_type' => 'password',
                        'username' => $user->username,
                        'password' => $user->password,
                        'client_id' => OAUTH_CLIENT_ID,
                        'client_secret' => OAUTH_CLIENT_SECRET,
                        'scope' => $scopes
                    );
                    $response = getToken($post_val);
                    $result['data'] = $response + $user->toArray();
                } else {
                    $result['data'] = $user->toArray();
                }
                return renderWithJson($result);
            } else {
                return renderWithJson($result, 'Invalid user deatails.', '', 1);
            }
        } else {
            return renderWithJson($result, 'Invalid Request', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid user deatails.', '', 1);
    }
});
/**
 * POST usersLoginPost
 * Summary: User login
 * Notes: User login information post
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/login', function ($request, $response, $args) {
    $body = $request->getParsedBody();
    $result = array();
    $user = new Models\User;
    $enabledIncludes = array(
        'attachment'
    );
    if (USER_USING_TO_LOGIN == 'username') {
        $log_user = $user->where('username', $body['username'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->first();
    } else {
        $log_user = $user->where('email', $body['email'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->first();
    }
    if (!empty($log_user)) {
        // $log_user->makeVisible($user->hidden);
    }
	$password = crypt($body['password'], $log_user['password']);
    $validationErrorFields = $user->validate($body);
	$validationErrorFields = array();
    if (empty($validationErrorFields) && !empty($log_user) && ($password == $log_user['password'])) {
        $scopes = '';
        if (!empty($log_user['scopes_' . $log_user['role_id']])) {
            $scopes = implode(' ', $log_user['scopes_' . $log_user['role_id']]);
        }
        $post_val = array(
            'grant_type' => 'password',
            'username' => $log_user['username'],
            'password' => $password,
            'client_id' => OAUTH_CLIENT_ID,
            'client_secret' => OAUTH_CLIENT_SECRET,
            'scope' => $scopes
        );
        $response = getToken($post_val);
        if (!empty($response['refresh_token'])) {
            $result = $response + $log_user->toArray();
            $userLogin = new Models\UserLogin;
            $userLogin->user_id = $log_user->id;
            $userLogin->ip_id = saveIp();
            $userLogin->user_agent = $_SERVER['HTTP_USER_AGENT'];
            $userLogin->save();
            return renderWithJson($result, 'LoggedIn Successfully');
        } else {
            return renderWithJson($result, 'Your login credentials are invalid.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Your login credentials are invalid.', $validationErrorFields, 1);
    }
});
/**
 * Get userSocialLoginGet
 * Summary: Social Login for twitter
 * Notes: Social Login for twitter
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/social_login', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    if (!empty($queryParams['type'])) {
        $response = social_auth_login($queryParams['type']);
		return renderWithJson($response);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * POST userSocialLoginPost
 * Summary: User Social Login
 * Notes:  Social Login
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/social_login', function ($request, $response, $args) {
    $body = $request->getParsedBody();
	try {
		$result = array();
		if (!empty($_GET['type'])) {
			$response = social_auth_login($_GET['type'], $body);
			// return (($response && $response['error'] && $response['error']['code'] == 1) ? renderWithJson($response) : renderWithJson($result, 'Unable to fetch details', '', 1));
			return renderWithJson($response, 'LoggedIn Successfully');
		} else {
			return renderWithJson($result, 'Please choose one provider.', '', 1);
		}
	} catch(Exception $e) {
		return renderWithJson($result, $e->getMessage(), '', 1);
	}
});
/**
 * POST usersForgotPasswordPost
 * Summary: User forgot password
 * Notes: User forgot password
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/forgot_password', function ($request, $response, $args) {
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::where('email', $args['email'])->first();
    if (!empty($user)) {
        $validationErrorFields = $user->validate($args);
        if (empty($validationErrorFields) && !empty($user)) {
            $password = uniqid();
            $user->password = getCryptHash($password);
            try {
                $user->save();
                $emailFindReplace = array(
                    '##USERNAME##' => $user['username'],
                    '##PASSWORD##' => $password,
                );
                sendMail('forgotpassword', $emailFindReplace, $user['email']);
                return renderWithJson($result, 'An email has been sent with your new password', '', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'Email Not found', '', 1);
            }
        } else {
            return renderWithJson($result, 'Process could not be found', $validationErrorFields, 1);
        }
    } else {
        return renderWithJson($result, 'No data found', '', 1);
    }
});
/**
 * PUT UsersuserIdChangePasswordPut .
 * Summary: update change password
 * Notes: update change password
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users/change_password', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::find($authUser->id);
    $validationErrorFields = $user->validate($args);
    $password = crypt($args['password'], $user['password']);
    if (empty($validationErrorFields)) {
        if ($password == $user['password']) {
            $change_password = $args['new_password'];
            $user->password = getCryptHash($change_password);
            try {
                $user->save();
                $emailFindReplace = array(
                    '##PASSWORD##' => $args['new_password'],
                    '##USERNAME##' => $user['username']
                );
                if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin) {
                    sendMail('adminchangepassword', $emailFindReplace, $user->email);
                } else {
                    sendMail('changepassword', $emailFindReplace, $user['email']);
                }
                $result['data'] = $user->toArray();
                return renderWithJson($result);
            } catch (Exception $e) {
                return renderWithJson($result, 'User Password could not be updated. Please, try again', '', 1);
            }
        } else {
            return renderWithJson($result, 'Password is invalid . Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'User Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateUser'));
/**
 * POST AdminChangePasswordToUser .
 * Summary: update change password
 * Notes: update change password
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users/change_password', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $user = Models\User::find($args['user_id']);
    $validationErrorFields = $user->validate($args);
    $validationErrorFields['unique'] = array();
    if (!empty($args['new_password']) && !empty($args['new_confirm_password']) && $args['new_password'] != $args['new_confirm_password']) {
        array_push($validationErrorFields['unique'], 'Password and confirm password should be same');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields)) {
        $change_password = $args['new_password'];
        $user->password = getCryptHash($change_password);
        try {
            $user->save();
            $emailFindReplace = array(
                '##PASSWORD##' => $args['new_password'],
                '##USERNAME##' => $user['username']
            );
            sendMail('adminchangepassword', $emailFindReplace, $user->email);
            $result['data'] = $user->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'User Password could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'User Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdminChangePasswordToUser'));
/**
 * GET usersLogoutGet
 * Summary: User Logout
 * Notes: oauth
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/logout', function ($request, $response, $args) {
    if (!empty($_GET['token'])) {
        try {
            $oauth = Models\OauthAccessToken::where('access_token', $_GET['token'])->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson(array(), 'Please verify in your token', '', 1);
        }
    }
});
$app->GET('/api/v1/contestants', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $queryParams['role_id'] = \Constants\ConstUserTypes::User;
		$queryParams['is_email_confirmed'] = true;
		$queryParams['is_active'] = true;
		$enabledIncludes = array(
            'attachment'
        );
        $users = Models\User::with($enabledIncludes);
        $users = $users->Filter($queryParams)->paginate($count);
        if (!empty($authUser) && $authUser->role_id == '1') {
            $user_model = new Models\User;
            $users->makeVisible($user_model->hidden);
        }
        $users = $users->toArray();
        $data = $users['data'];
		unset($users['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $users
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * GET UsersGet
 * Summary: Filter  users
 * Notes: Filter users.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
		if (!empty($queryParams['skills'])) {
			$skills = explode(",",$queryParams['skills']);
			foreach ($skills as $skill) {
				$keyword = new Models\Keyword;
				$keyword->created_at = date('Y-m-d h:i:s');
				$keyword->updated_at = date('Y-m-d h:i:s');
				$keyword->skill_id = $skill;
				$keyword->save();
			}
		}
        $enabledIncludes = array(
            'attachment',
            'city',
            'state',
            'country',
            'role',
            'skill_users'
        );
        /*(isPluginEnabled('Bidding/Exam')) ? $enabledIncludes[] = 'exams_users' : '';
        $users = Models\User::with($enabledIncludes);
        if (isPluginEnabled('Common/UserFlag')) {
            $users = $users->with(array(
                'flags' => function ($q) use ($authUser) {
                    $q->where('user_id', $authUser->id);
                }
            ));
        }
        if (isPluginEnabled('Common/UserFollow')) {
            $users = $users->with(array(
                'follower' => function ($q) use ($authUser) {
                    $q->where('user_id', $authUser->id);
                }
            ));
        }*/
		$users = new Models\User;
        $users = $users->Filter($queryParams)->paginate($count);
        if (!empty($authUser) && $authUser->role_id == '1') {
            $user_model = new Models\User;
            $users->makeVisible($user_model->hidden);
        }
        $users = $users->toArray();
        $data = $users['data'];
		unset($users['data']);
        if ((isPluginEnabled('Bidding/Exam'))) {
            foreach ($data as $key => $user) {
                $examsUser = Models\ExamsUser::with('exam')->where('user_id', $user['id'])->where('exams_users.exam_status_id', \Constants\ExamStatus::Passed);
                $examsUser = $examsUser->get();
                if (!empty($examsUser)) {
                    $data[$key]['exams_users'] = $examsUser->toArray();
                }
            }
        }
        $result = array(
            'data' => $data,
            '_metadata' => $users
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/usershome', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
        $count = 6;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'attachment',
            'skill_users'
        );
        $users = Models\User::with($enabledIncludes);
        $users = $users->Filter($queryParams)->paginate($count);
        $users = $users->toArray();
        $data = $users['data'];
        unset($users['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $users
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST UserPost
 * Summary: Create New user by admin
 * Notes: Create New user by admin
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users', function ($request, $response, $args) {
	global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
    $args = $request->getParsedBody();
    $result = array();
    $user = new Models\User($args);
    unset($user->location);
    unset($user->city_name);
    unset($user->state_name);
    unset($user->country);
    $validationErrorFields = $user->validate($args);
    $validationErrorFields['unique'] = array();
    $validationErrorFields['required'] = array();
    //get country, state and city ids
    if (!empty($args['full_address'])) {
        if (!empty($args['country']['iso_alpha2'])) {
            $user->country_id = findCountryIdFromIso2($args['country']['iso_alpha2']);
            if (!empty($args['state']['name'])) {
                $user->state_id = findOrSaveAndGetStateId($args['state']['name'], $user->country_id);
            }
            if (!empty($args['city']['name'])) {
                $user->city_id = findOrSaveAndGetCityId($args['city']['name'], $user->country_id, $user->state_id);
            }
        } else {
            array_push($validationErrorFields['required'], 'Country');
        }
    }
    unset($user->country);
    unset($user->state);
    unset($user->city);
    if (checkAlreadyUsernameExists($args['username'])) {
        array_push($validationErrorFields['unique'], 'username');
    }
    if (checkAlreadyEmailExists($args['email'])) {
        array_push($validationErrorFields['unique'], 'email');
    }
    if (empty($validationErrorFields['unique'])) {
        unset($validationErrorFields['unique']);
    }
    if (empty($validationErrorFields['required'])) {
        unset($validationErrorFields['required']);
    }
    if (!empty($args['is_active'])) {
        $user->is_active = $args['is_active'];
     }
     if (!empty($args['is_email_confirmed'])) {
        $user->is_email_confirmed = $args['is_email_confirmed'];
     } 
    if (empty($validationErrorFields)) {
        $user->password = getCryptHash($args['password']);
        $user->role_id = $args['role_id'];  
        try {
            unset($user->location);
            unset($user->state);
            unset($user->city);
            unset($user->country);
            unset($user->image);
            unset($user->cover_photo);       
            $user->save();
            if (isPluginEnabled('Common/Subscription')) {
                $quoteCreditPurchasePlan = Models\CreditPurchasePlan::where('is_welcome_plan', 1)->where('is_active', 1)->first();
                if (!empty($quoteCreditPurchasePlan)) {
                    $quoteCreditPurchaseLogs = new Models\CreditPurchaseLog;
                    $quoteCreditPurchaseLogs->user_id = $user->id;
                    $quoteCreditPurchaseLogs->price = $quoteCreditPurchasePlan->price;
                    $quoteCreditPurchaseLogs->credit_count = !empty($quoteCreditPurchasePlan->no_of_credits) ? $quoteCreditPurchasePlan->no_of_credits : 0;
                    $quoteCreditPurchaseLogs->discount_percentage = !empty($quoteCreditPurchasePlan->discount_percentage) ? $quoteCreditPurchasePlan->discount_percentage : 0;
                    $quoteCreditPurchaseLogs->original_price = !empty($quoteCreditPurchasePlan->original_price) ? $quoteCreditPurchasePlan->original_price : 0;
                    $quoteCreditPurchaseLogs->is_active = 1;
                    $quoteCreditPurchaseLogs->credit_purchase_plan_id = $quoteCreditPurchasePlan->id;
                    $quoteCreditPurchaseLogs->is_payment_completed = 1;
                    if (!empty($quoteCreditPurchasePlan->day_limit)) {
                        $quoteCreditPurchaseLogs->expiry_date = date('Y-m-d h:i:s', strtotime("+" . $quoteCreditPurchasePlan->day_limit . " days"));
                    }
                    $quoteCreditPurchaseLogs->save();
                    $quoteUser = Models\User::find($quoteCreditPurchaseLogs->user_id);
                    $quoteUser = $quoteUser->makeVisible(array(
                        'available_credit_count',
                    ));
                    $quoteUser->available_credit_count = $quoteCreditPurchaseLogs->credit_count;
                    $quoteUser->update();
                }
            }
            if (!empty($args['image'])) {
                saveImage('UserAvatar', $args['image'], $user->id);
            }
            if (!empty($args['cover_photo'])) {
                saveImage('CoverPhoto', $args['cover_photo'], $user->id);
            }
            $emailFindReplace_user = array(
                '##USERNAME##' => $user->username,
                '##LOGINLABEL##' => (USER_USING_TO_LOGIN == 'username') ? 'Username' : 'Email',
                '##USEDTOLOGIN##' => (USER_USING_TO_LOGIN == 'username') ? $user->username : $user->email,
                '##PASSWORD##' => $args['password']
            );
            sendMail('adminuseradd', $emailFindReplace_user, $user->email);
            $enabledIncludes = array(
                'attachment',
                'cover_photo'
            );
            $result = Models\User::with($enabledIncludes)->find($user->id)->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'User could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'User could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canCreateUser'));
/**
 * GET UseruserIdGet
 * Summary: Get particular user details
 * Notes: Get particular user details
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/{userId}', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'attachment',
        'city',
        'state',
        'country',
        'role'
    );
    // (isPluginEnabled('Common/UserFollow')) ? $enabledIncludes[] = 'follower' : '';
    $user = Models\User::with($enabledIncludes)->where('id', $request->getAttribute('userId'))->first();
    if (!empty($authUser['id']) && $authUser->role_id == '1') {
        $user_model = new Models\User;
        $user->makeVisible($user_model->hidden);
    }
    if (!empty($user)) {
        $result['data'] = $user;
        $isAllowToViewBadge = false;
        if (!empty($authUser['id'])) {
            $enabledIncludes = array();
            (isPluginEnabled('Job/Job')) ? $enabledIncludes = 'job' : '';
            $jobApply = Models\JobApply::where('user_id', $request->getAttribute('userId'))->with([$enabledIncludes => function ($q) use ($authUser) {
                return $q->where('user_id', $authUser->id);
            }
            ])->first();
            if (!empty($jobApply['job'])) {
                $isAllowToViewBadge = true;
            }
        } else {
            $isAllowToViewBadge = true;
        }
        if ($isAllowToViewBadge) {
            $examsUser = Models\ExamsUser::where('user_id', $request->getAttribute('userId'))->where('exams_users.exam_status_id', \Constants\ExamStatus::Passed)->get();
            if (!empty($examsUser)) {
                $result['data']['ExamsUser'] = $examsUser->toArray();
            }
        }
        if (!empty($_GET['type']) && $_GET['type'] == 'view' && (empty($authUser) || (!empty($authUser) && $authUser['id'] != $request->getAttribute('userId')))) {
            insertViews($request->getAttribute('userId'), 'User');
        }
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1, 404);
    }
})->add(new ACL('canViewUser'));
/**
 * GET AuthUserID
 * Summary: Get particular user details
 * Notes: Get particular user details
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/me', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'attachment',
        'city',
        'state',
        'country',
        'role',
        'skill_users'
    );
    $user = Models\User::with($enabledIncludes)->where('id', $authUser->id)->first();
    $user_model = new Models\User;
    $user->makeVisible($user_model->hidden);
    if (!empty($user)) {
        $result['data'] = $user;
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canGetMe'));
/**
 * PUT UsersuserIdPut
 * Summary: Update user
 * Notes: Update user
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/users', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();	
    $result = array();
    $user = Models\User::find($authUser->id);
    unset($user->location);
    unset($user->city_name);
    unset($user->state_name);
    unset($user->country);
    $validation = true;
    //get country, state and city ids
    if (!empty($args['full_address'])) {
        if (!empty($args['country']['iso_alpha2'])) {
            $user->country_id = findCountryIdFromIso2($args['country']['iso_alpha2']);
            if (!empty($args['state']['name'])) {
                $user->state_id = findOrSaveAndGetStateId($args['state']['name'], $user->country_id);
            }
            if (!empty($args['city']['name'])) {
                $user->city_id = findOrSaveAndGetCityId($args['city']['name'], $user->country_id, $user->state_id);
            }
            elseif(isset($args['city']['name'])){
                $user->city_id = null;
            }
        } else {
            $validation = false;
        }
    }
	if (!empty($args['address'])) {
		$user->address = $args['address'];
	}
    unset($user->country);
    unset($user->state);
    unset($user->city);
    if (!empty($user)) {
        if ($validation) {
            $user->fill($args);
            unset($user->location);
            unset($user->city);
            unset($user->state);
            unset($user->country);
            unset($user->image);
            unset($user->cover_photo);
            unset($user->add_fund);
            unset($user->deduct_fund);
            unset($user->skills);
            //get country, state and city ids
            if (!empty($user->location)) {
                $user->country_id = findCountryIdFromIso2($args['country']['iso_alpha2']);
            if (!empty($args['state']['name'])) {
                $user->state_id = findOrSaveAndGetStateId($args['state']['name'], $user->country_id);
            }
            if (!empty($args['city']['name'])) {
                $user->city_id = findOrSaveAndGetCityId($args['city']['name'], $user->country_id, $user->state_id);
            }
            elseif(isset($args['city']['name'])){
                $user->city_id = null;
            }
            }
            try {
                $user->save();
                if (!empty($args['image'])) {
                    saveImage('UserAvatar', $args['image'], $user->id);
                }
                if (!empty($args['cover_photo'])) {
                    saveImage('CoverPhoto', $args['cover_photo'], $user->id);
                }
                if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin) {
                    if (!empty($args['add_fund'])) {
                        Models\User::where('id', $user->id)->update(array(
                            'available_wallet_amount' => $user->available_wallet_amount + $args['add_fund']
                        ));
                        insertTransaction($authUser['id'], $user->id, 'User', \Constants\TransactionType::AdminAddedAmountToUserWallet, \Constants\PaymentGateways::Wallet, $args['add_fund'], 0, 0, 0, 0, $user->id, 0);
                    }
                    if (!empty($args['deduct_fund'])) {
                        Models\User::where('id', $user->id)->update(array(
                            'available_wallet_amount' => $user->available_wallet_amount - $args['deduct_fund']
                        ));
                        insertTransaction($authUser['id'], $user->id, $user->id, 'User', \Constants\TransactionType::AdminDeductedAmountToUserWallet, \Constants\PaymentGateways::Wallet, $args['deduct_fund'], 0, 0, 0, 0, $user->id, 0);
                    }
                }
                if (!empty($args['skills']) && $user->id) {
                    Models\SkillsUser::where('user_id', $user->id)->get()->each(function ($skillsUsers) {
                        $skillsUsers->delete();
                    });
                    foreach ($args['skills'] as $skill) {
                        $skillsUser = new Models\SkillsUser;
                        $skillsUser->skill_id = $skill['skill_id'];
                        $skillsUser->user_id = $user->id;
                        $skillsUser->save();
                    }
                }
                $enabledIncludes = array(
                    'attachment'
                );
                $user = Models\User::with($enabledIncludes)->find($user->id);
                $result['data'] = $user->toArray();
                return renderWithJson($result);
            } catch (Exception $e) {
                return renderWithJson($result, 'User could not be updated. Please, try again.', '', 1);
            }
        } else {
            return renderWithJson($result, 'Country is required', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid user Details, try again.', '', 1);
    }
})->add(new ACL('canUpdateUser'));
/**
 * DELETE UseruserId Delete
 * Summary: DELETE user by admin
 * Notes: DELETE user by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/users/{userId}', function ($request, $response, $args) {
    $result = array();
    $user = Models\User::find($request->getAttribute('userId'));
    $data = $user;
    if (!empty($user)) {
        try {
            $user->delete();
            $emailFindReplace = array(
                '##USERNAME##' => $data['username']
            );
            sendMail('adminuserdelete', $emailFindReplace, $data['email']);
            $result = array(
                'status' => 'success',
            );
            Models\UserLogin::where('user_id', $request->getAttribute('userId'))->delete();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'User could not be deleted. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid User details.', '', 1);
    }
})->add(new ACL('canDeleteUser'));
/**
 * GET ProvidersGet
 * Summary: all providers lists
 * Notes: all providers lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/providers', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $providers = Models\Provider::Filter($queryParams)->paginate($count)->toArray();
        $data = $providers['data'];
        unset($providers['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $providers
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * GET  ProvidersProviderIdGet
 * Summary: Get  particular provider details
 * Notes: GEt particular provider details.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/providers/{providerId}', function ($request, $response, $args) {
    $result = array();
    $provider = Models\Provider::find($request->getAttribute('providerId'));
    if (!empty($provider)) {
        $result['data'] = $provider->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewProvider'));
/**
 * PUT ProvidersProviderIdPut
 * Summary: Update provider details
 * Notes: Update provider details.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/providers/{providerId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $provider = Models\Provider::find($request->getAttribute('providerId'));
    $validationErrorFields = $provider->validate($args);
    if (empty($validationErrorFields)) {
        $provider->fill($args);
        try {
            $provider->save();
            $result['data'] = $provider->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Provider could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'Provider could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateProvider'));
/**
 * GET FormFieldGet
 * Summary: all FormField lists
 * Notes: all FormField lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/form_fields', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        if (!empty($queryParams['class']) && !empty($queryParams['foreign_id'])) {
            $enabledIncludes = array(
                'form_fields'
            );
            $FormFieldGroup = Models\FormFieldGroup::with($enabledIncludes)->whereHas('form_fields', function ($q) use ($authUser) {
                if (!isset($authUser) || (isset($authUser) && ($authUser['role_id'] != \Constants\ConstUserTypes::Admin))) {
                    return $q->where('is_active', 1);
                }
            })->Filter($queryParams)->paginate($count)->toArray();
        } else {
            if (!isset($authUser) || (isset($authUser) && ($authUser['role_id'] != \Constants\ConstUserTypes::Admin))) {
                $queryParams['filter'] = 'active';
            }
            $enabledIncludes = array(
                'input_types'
            );
            $FormFieldGroup = Models\FormField::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        }
        if (!empty($FormFieldGroup)) {
            $data = $FormFieldGroup['data'];
            unset($FormFieldGroup['data']);
            $result = array(
                'data' => $data,
                '_metadata' => $FormFieldGroup
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST QuoteFormField POST
 * Summary:Post QuoteFormField
 * Notes:  Post QuoteFormField
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/form_fields', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $queryParams = $request->getQueryParams();
    $FormFields = new Models\FormField($args);
    $FormFields->class = $queryParams['class'];
    $result = array();
    try {
        $validationErrorFields = $FormFields->validate($args);
        if (empty($validationErrorFields)) {
            $FormFields->save();
            $result['data'] = $FormFields->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Form Field could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, ' Form Field could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreateFormField'));
/**
 * DELETE QuoteFormField QuoteFormFieldIdDelete
 * Summary: Delete QuoteFormFielde
 * Notes: Deletes a single  QuoteFormField based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/form_fields/{FormFieldId}', function ($request, $response, $args) {
    $FormFields = Models\FormField::find($request->getAttribute('FormFieldId'));
    $result = array();
    try {
        if (!empty($FormFields)) {
            $FormFields->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Form Field could not be deleted. Please, try again.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Form Field could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteFormField'));
/**
 * GET QuoteFormField QuoteFormFieldId get
 * Summary: Fetch a QuoteFormField based on QuoteFormField Id
 * Notes: Returns a QuoteFormField from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/form_fields/{FormFieldId}', function ($request, $response, $args) {
    $enabledIncludes = array(
        'input_types'
    );
    (isPluginEnabled('Quote/Quote')) ? $enabledIncludes[] = 'quote_category' : '';
    $FormFields = Models\FormField::with($enabledIncludes)->find($request->getAttribute('FormFieldId'));
    $result = array();
    try {
        if (!empty($FormFields)) {
            $result['data'] = $FormFields->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'No record found.', '', 1);
    }
})->add(new ACL('canViewFormField'));
/**
 * PUT QuoteFormField QuoteFormFieldIdPut
 * Summary: Update QuoteFormField details
 * Notes: Update QuoteFormField details.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/form_fields/{FormFieldId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $FormFields = Models\FormField::find($request->getAttribute('FormFieldId'));
    $oldForeignId = $FormFields->foreign_id;
    if (!empty($FormFields)) {
        $validationErrorFields = $FormFields->validate($args);
        if (empty($validationErrorFields)) {
            $FormFields->fill($args);
            try {
                $FormFields->save();
                $result['data'] = $FormFields->toArray();
                return renderWithJson($result);
            } catch (Exception $e) {
                return renderWithJson($result, 'Form Field could not be updated. Please, try again', '', 1);
            }
        } else {
            return renderWithJson($result, 'Form Field could not be updated. Please, try again', $validationErrorFields, 1);
        }
    } else {
        return renderWithJson($result, 'Form Field could not be updated. Please, try again', '', 1);
    }
})->add(new ACL('canUpdateFormField'));
/**
 * POST QuoteFormFieldGroup POST
 * Summary:Post QuoteFormFieldGroup
 * Notes:  Post QuoteFormFieldGroup
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/form_field_groups', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $FormFieldGroups = new Models\FormFieldGroup($args);
    $result = array();
    try {
        $validationErrorFields = $FormFieldGroups->validate($args);
        if (empty($validationErrorFields)) {
            $FormFieldGroups->save();
            $result['data'] = $FormFieldGroups->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Form Field Group could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, ' Form Field Group could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreateFormFieldGroup'));
/**
 * PUT FormFieldGroupId FormFieldGroupIdPut
 * Summary: Update FormFieldGroup details
 * Notes: Update FormFieldGroup details.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/form_field_groups/{FormFieldGroupId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $FormFieldGroups = Models\FormFieldGroup::find($request->getAttribute('FormFieldGroupId'));
    if (!empty($FormFieldGroups)) {
        $validationErrorFields = $FormFieldGroups->validate($args);
        if (empty($validationErrorFields)) {
            $FormFieldGroups->fill($args);
            try {
                $FormFieldGroups->save();
                $result['data'] = $FormFieldGroups->toArray();
                return renderWithJson($result);
            } catch (Exception $e) {
                return renderWithJson($result, 'Form Field Group could not be updated. Please, try again', '', 1);
            }
        } else {
            return renderWithJson($result, 'Form Field Group could not be updated. Please, try again', $validationErrorFields, 1);
        }
    } else {
        return renderWithJson($result, 'Form Field Group could not be updated. Please, try again', '', 1);
    }
})->add(new ACL('canUpdateFormFieldGroup'));
/**
 * GET inputTypesGet
 * Summary: Fetch all input types
 * Notes: Returns all input types from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/input_types', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $inputTypes = Models\InputType::Filter($queryParams)->paginate(PAGE_LIMIT)->toArray();
        $data = $inputTypes['data'];
        unset($inputTypes['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $inputTypes
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST orderPost
 * Summary: Creates a new page
 * Notes: Creates a new page
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/order', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
    $args = $request->getParsedBody();
    $result = array();
    if (!empty($args['class']) && !empty($args['foreign_id'])) {
        $args['user_id'] = isset($args['user_id']) ? $args['user_id'] : $authUser->id;
        switch ($args['class']) {
            case 'Job':
                if ((isPluginEnabled('Job/Job'))) {
                    $result = Models\Job::processOrder($args);
                }
                break;

            case 'Project':
                if ((isPluginEnabled('Bidding/Bidding'))) {
                    $result = Models\Project::processOrder($args);
                }
                break;

            case 'QuoteBid':
                if ((isPluginEnabled('Quote/Quote'))) {
                    $result = Models\QuoteBid::processOrder($args);
                }
                break;

            case 'Milestone':
                if ((isPluginEnabled('Bidding/Milestone'))) {
                    $result = Models\Milestone::processOrder($args);
                }
                break;

            case 'ProjectBidInvoice':
                if ((isPluginEnabled('Bidding/Invoice'))) {
                    $result = Models\ProjectBidInvoice::processOrder($args);
                }
                break;

            case 'Contest':
                if ((isPluginEnabled('Contest/Contest'))) {
                    $result = Models\Contest::processOrder($args);
                }
                break;

            case 'ExamsUser':
                if ((isPluginEnabled('Bidding/Exam'))) {
                    $result = Models\ExamsUser::processOrder($args);
                }
                break;
        }
        if (!empty($result)) {
            return renderWithJson($result);
        } else {
            return renderWithJson($result, $message = 'Order could not added. No record found', '', $isError = 1);
        }
    } else {
        $validationErrorFields['class'] = 'class required';
        $validationErrorFields['foreign_id'] = 'foreign_id required';
        return renderWithJson($result, $message = 'Order could not added', $validationErrorFields, $isError = 1);
    }
})->add(new ACL('canCreateOrder'));
/**
 * GET workProfilesGet
 * Summary: Fetch all work profiles
 * Notes: Returns all work profiles from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/work_profiles', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $enabledIncludes = array(
            'user'
        );
        $workProfiles = Models\WorkProfile::with($enabledIncludes)->Filter($queryParams)->paginate(PAGE_LIMIT)->toArray();
        $data = $workProfiles['data'];
        unset($workProfiles['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $workProfiles
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST workProfilesPost
 * Summary: Creates a new work profile
 * Notes: Creates a new work profile
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/work_profiles', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $workProfile = new Models\WorkProfile($args);
    $result = array();
    try {
        $validationErrorFields = $workProfile->validate($args);
        if (empty($validationErrorFields)) {
            $workProfile->user_id = $authUser->id;
            $workProfile->save();
            $result = $workProfile->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Work profile could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Work profile could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreateWorkProfile'));
/**
 * DELETE workProfilesWorkProfileIdDelete
 * Summary: Delete work profile
 * Notes: Deletes a single work profile based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/work_profiles/{workProfileId}', function ($request, $response, $args) {
    $workProfile = Models\WorkProfile::find($request->getAttribute('workProfileId'));
    try {
        $workProfile->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'Work profile could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteWorkProfile'));
/**
 * GET workProfilesWorkProfileIdGet
 * Summary: Fetch work profile
 * Notes: Returns a work profile based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/work_profiles/{workProfileId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'user'
    );
    $workProfile = Models\WorkProfile::with($enabledIncludes)->find($request->getAttribute('workProfileId'));
    if (!empty($workProfile)) {
        $result['data'] = $workProfile;
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * PUT workProfilesWorkProfileIdPut
 * Summary: Update work profile by its id
 * Notes: Update work profile by its id
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/work_profiles/{workProfileId}', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $workProfile = Models\WorkProfile::find($request->getAttribute('workProfileId'));
    $workProfile->fill($args);
    $result = array();
    try {
        $validationErrorFields = $workProfile->validate($args);
        if (empty($validationErrorFields)) {
            $workProfile->user_id = $authUser->id;
            $workProfile->save();
            $result = $workProfile->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Work profile could not be updated. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Work profile could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canUpdateWorkProfile'));
/**
 * GET publicationsGet
 * Summary: Fetch all publications
 * Notes: Returns all publications from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/publications', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $enabledIncludes = array(
            'user'
        );
        $publications = Models\Publication::with($enabledIncludes)->Filter($queryParams)->paginate(PAGE_LIMIT)->toArray();
        $data = $publications['data'];
        unset($publications['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $publications
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST publicationsPost
 * Summary: Creates a new publication
 * Notes: Creates a new publication
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/publications', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $publication = new Models\Publication($args);
    $result = array();
    try {
        $validationErrorFields = $publication->validate($args);
        if (empty($validationErrorFields)) {
            $publication->user_id = $authUser->id;
            $publication->save();
            $result = $publication->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Publication could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Publication could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreatePublication'));
/**
 * DELETE publicationsPublicationIdDelete
 * Summary: Delete publication
 * Notes: Deletes a single publication based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/publications/{publicationId}', function ($request, $response, $args) {
    $publication = Models\Publication::find($request->getAttribute('publicationId'));
    try {
        $publication->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'Publication could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeletePublication'));
/**
 * GET publicationsPublicationIdGet
 * Summary: Fetch publication
 * Notes: Returns a publication based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/publications/{publicationId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'user'
    );
    $publication = Models\Publication::with($enabledIncludes)->find($request->getAttribute('publicationId'));
    if (!empty($publication)) {
        $result['data'] = $publication;
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * PUT publicationsPublicationIdPut
 * Summary: Update publication by its id
 * Notes: Update publication by its id
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/publications/{publicationId}', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $publication = Models\Publication::find($request->getAttribute('publicationId'));
    $publication->fill($args);
    $result = array();
    try {
        $validationErrorFields = $publication->validate($args);
        if (empty($validationErrorFields)) {
            $publication->user_id = $authUser->id;
            $publication->save();
            $result = $publication->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Publication could not be updated. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Publication could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canUpdatePublication'));
/**
 * GET RoleGet
 * Summary: Get roles lists
 * Notes: Get roles lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/roles', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $roles = Models\Role::Filter($queryParams)->paginate($count)->toArray();
        $data = $roles['data'];
        unset($roles['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $roles
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * GET RolesIdGet
 * Summary: Get paticular email templates
 * Notes: Get paticular email templates
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/roles/{roleId}', function ($request, $response, $args) {
    $result = array();
    $role = Models\Role::find($request->getAttribute('roleId'));
    if (!empty($role)) {
        $result = $role->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * GET ContactsGet
 * Summary: Get  contact lists
 * Notes: Get contact lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/contacts', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $contacts = Models\Contact::leftJoin('ips', 'contacts.ip_id', '=', 'ips.id');
        $contacts = $contacts->select('contacts.*', 'ips.ip as ip_ip');
        $enabledIncludes = array(
            'ip'
        );
        $contacts = $contacts->with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $contacts['data'];
        unset($contacts['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $contacts
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canListContact'));
/**
 * POST contactPost
 * Summary: add contact
 * Notes: add contact
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/contacts', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $contact = new Models\Contact($args);
    $contact->ip_id = saveIp();
    $validationErrorFields = $contact->validate($args);
    if (empty($validationErrorFields)) {
        try {
            $contact->save();
            $contact_list = Models\Contact::where('id', $contact->id)->first();
            $emailFindReplace = array(
                '##FIRST_NAME##' => $contact_list['first_name'],
                '##LAST_NAME##' => $contact_list['last_name'],
                '##FROM_EMAIL##' => $contact_list['email'],
                '##IP##' => $contact_list['ip']['ip'],
                '##TELEPHONE##' => $contact_list['phone'],
                '##MESSAGE##' => $contact_list['message'],
                '##SUBJECT##' => $contact_list['subject'],
                '##SITE_CONTACT_EMAIL##' => SITE_CONTACT_EMAIL
            );
            sendMail('contactus', $emailFindReplace, SITE_CONTACT_EMAIL);
            sendMail('contactusreplymail', $emailFindReplace, $contact->email);
            $result = $contact->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Contact could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Contact could not be added. Please, try again.', $validationErrorFields, 1);
    }
});
/**
 * GET ContactscontactIdGet
 * Summary: get particular contact details
 * Notes: get particular contact details
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/contacts/{contactId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'ip'
    );
    $contact = Models\Contact::with($enabledIncludes)->find($request->getAttribute('contactId'));
    if (!empty($contact)) {
        $result['data'] = $contact->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewContact'));
/**
 * DELETE ContactsContactIdDelete
 * Summary: DELETE contact Id by admin
 * Notes: DELETE contact Id by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/contacts/{contactId}', function ($request, $response, $args) {
    $result = array();
    $contact = Models\Contact::find($request->getAttribute('contactId'));
    try {
        if (!empty($contact)) {
            $contact->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Contact could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteContact'));
/**
 * GET TransactionGet
 * Summary: Get all transactions list.
 * Notes: Get all transactions list.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/transactions', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'user',
            'other_user',
            'foreign_transaction',
            'payment_gateway',
            'zazpay_gateway'
        );
        $transactions = Models\Transaction::with($enabledIncludes)->Filter($queryParams);
        if (!isPluginEnabled('Bidding/Bidding')) {
            $transactions = $transactions->whereNotIn('class', ['Project', 'Milestone', 'ProjectBidInvoice']);
        }
        if (!isPluginEnabled('Contest/Contest')) {
            $transactions = $transactions->whereNotIn('class', ['Contest']);
        }
        if (!isPluginEnabled('Job/Job')) {
            $transactions = $transactions->whereNotIn('class', ['Job']);
        }
        if (!isPluginEnabled('Quote/Quote')) {
            $transactions = $transactions->whereNotIn('class', ['QuoteService', 'QuoteBid']);
        }
        if (!isPluginEnabled('Bidding/Exam')) {
            $transactions = $transactions->whereNotIn('class', ['ExamsUser']);
        }
        if (!isPluginEnabled('Common/Subscription')) {
            $transactions = $transactions->whereNotIn('class', ['CreditPurchaseLog']);
        }
        $transactions = $transactions->paginate($count);
        $transactionsNew = $transactions;
        $transactions = $transactions->map(function ($transaction) {
            if ($transaction->class == 'Milestone' || $transaction->class == 'ProjectBidInvoice') {
                $project = Models\Project::select('id', 'name', 'slug')->where('id', $transaction->foreign_transaction->project_id)->first();
                if (!empty($project)) {
                    $transaction->project = $project->toArray();
                }
            }
            if ($transaction->class == 'ExamsUser') {
                $exam = Models\Exam::select('id', 'title', 'slug')->where('id', $transaction->foreign_transaction->exam_id)->first();
                if (!empty($exam)) {
                    $transaction->exam = $exam->toArray();
                }
            }
            if ($transaction->class == 'CreditPurchaseLog') {
                $creditPurchasePlan = Models\CreditPurchasePlan::select('id', 'name')->where('id', $transaction->foreign_transaction->credit_purchase_plan_id)->first();
                if (!empty($creditPurchasePlan)) {
                    $transaction->creditPurchasePlan = $creditPurchasePlan->toArray();
                }
            }
            $transactionsNew = $transaction;
            return $transaction;
        });
        $transactionsNew = $transactionsNew->toArray();
        $data = $transactionsNew['data'];
        unset($transactionsNew['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListAllTransactions'));
/**
 * GET UsersUserIdTransactionsGet
 * Summary: Get user transactions list.
 * Notes: Get user transactions list.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/users/{userId}/transactions', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'user',
            'other_user',
            'foreign_transaction',
            'payment_gateway',
            'zazpay_gateway'
        );
        $transactions = Models\Transaction::with($enabledIncludes);
        if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
        if (!isPluginEnabled('Bidding/Bidding')) {
            $transactions = $transactions->whereNotIn('class', ['Project', 'Milestone', 'ProjectBidInvoice']);
        }
        if (!isPluginEnabled('Contest/Contest')) {
            $transactions = $transactions->whereNotIn('class', ['Contest']);
        }
        if (!isPluginEnabled('Job/Job')) {
            $transactions = $transactions->whereNotIn('class', ['Job']);
        }
        if (!isPluginEnabled('Quote/Quote')) {
            $transactions = $transactions->whereNotIn('class', ['QuoteService', 'QuoteBid']);
        }
        if (!isPluginEnabled('Bidding/Exam')) {
            $transactions = $transactions->whereNotIn('class', ['ExamsUser']);
        }
        if (!isPluginEnabled('Common/Subscription')) {
            $transactions = $transactions->whereNotIn('class', ['CreditPurchaseLog']);
        }
        $transactions = $transactions->Filter($queryParams)->paginate($count);
        $transactionsNew = $transactions;
        $transactions = $transactions->map(function ($transaction) {
            if ($transaction->class == 'Milestone' || $transaction->class == 'ProjectBidInvoice') {
                $project = Models\Project::select('id', 'name', 'slug')->where('id', $transaction->foreign_transaction->project_id)->first();
                if (!empty($project)) {
                    $transaction->project = $project->toArray();
                }
            }
            if ($transaction->class == 'ExamsUser') {
                $exam = Models\Exam::select('id', 'title', 'slug')->where('id', $transaction->foreign_transaction->exam_id)->first();
                if (!empty($exam)) {
                    $transaction->exam = $exam->toArray();
                }
            }
            if ($transaction->class == 'CreditPurchaseLog') {
                $creditPurchasePlan = Models\CreditPurchasePlan::select('id', 'name')->where('id', $transaction->foreign_transaction->credit_purchase_plan_id)->first();
                if (!empty($creditPurchasePlan)) {
                    $transaction->creditPurchasePlan = $creditPurchasePlan->toArray();
                }
            }
            $transactionsNew = $transaction;
            return $transaction;
        });
        $transactionsNew = $transactionsNew->toArray();
        $data = $transactionsNew['data'];
        unset($transactionsNew['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListUserTransactions'));
/**
 * GET paymentGatewayGet
 * Summary: Filter  payment gateway
 * Notes: Filter payment gateway.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/payment_gateways', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $paymentGateways = Models\PaymentGateway::Filter($queryParams)->paginate($count)->toArray();
        $data = $paymentGateways['data'];
        unset($paymentGateways['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $paymentGateways
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * GET Paymentgateways list.
 * Summary: Paymentgateway list.
 * Notes: Paymentgateways list.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/payment_gateways/list', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $data = array();
    try {
        $zazpay = array();
        if (isPluginEnabled('Common/ZazPay')) {
            $data['zazpay'] = Models\ZazpayPaymentGateway::zazpayCallGetGateways($zazpay);
        }
        if (isPluginEnabled('Common/Wallet')) {
            $data['wallet'] = array(
                'wallet_enabled' => true
            );
        }
		if (isPluginEnabled('Common/PayTabs')) {
			$showTab = true;
            $data['PayTabs'] = array(
                'paytabs_enabled' => true
            );
        }
        if (isPluginEnabled('Common/PaypalREST')) {
            $data['PayPalREST'] = array(
                'paypalrest_enabled' => true
            );
        }
		
		
        return renderWithJson($data);
    } catch (Exception $e) {
        return renderWithJson($result, 'No Paymentgateway found.Please try again.', '', 1);
    }
});
/**
 * GET paymentGatewayGet
 * Summary: Get  payment gateways
 * Notes: Filter payment gateway.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/payment_gateways/{paymentGatewayId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'payment_settings'
    );
    $paymentGateway = Models\PaymentGateway::with($enabledIncludes)->find($request->getAttribute('paymentGatewayId'));
    if (!empty($paymentGateway)) {
        $result['data'] = $paymentGateway->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
$app->PUT('/api/v1/payment_gateway_settings/{paymentGatewayId}', function ($request, $response, $args) {
    $body = $request->getParsedBody();
    $result = $ZazPayResponse = array();
    $ZazPay_credential_for_live = '';
    $ZazPay_credential_for_test = '';
    $is_test = 1;
    $is_live = 0;
    if (!empty($body['is_live_mode'])) {
        $ZazPay_credential_for_live = $body['live_mode_value'];
        $uploaded_to_zazpay = $body['live_mode_value'];
    } else {
        $ZazPay_credential_for_test = $body['test_mode_value'];
        $uploaded_to_zazpay = $body['test_mode_value'];
    }
    if (isset($body['is_live_mode']) && $body['is_live_mode'] == true) {
        $is_test = 0;
        $is_live = 1;
    }
    Models\PaymentGateway::where('name', 'ZazPay')->update(array(
         "is_test_mode" => $is_test
    ));
    $payment_gateway_settings = Models\PaymentGatewaySetting::where('name', 'payment_gateway_all_credentials')->update(array(
        "test_mode_value" => serialize($ZazPay_credential_for_test) ,
        "live_mode_value" => serialize($ZazPay_credential_for_live)
    )); 
    $result['data'] = $payment_gateway_settings;
    return renderWithJson($result);
});
/**
 * PUT paymentGatewayspaymentGatewayIdPut
 * Summary: Update Payment gateway by its id
 * Notes: Update Payment gateway.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/payment_gateways/{paymentGatewayId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $paymentGateway = Models\PaymentGateway::find($request->getAttribute('paymentGatewayId'));
    foreach ($args as $key => $arg) {
        $paymentGateway->{$key} = $arg;
    }
    try {
        $paymentGateway->save();
        $result['data'] = $paymentGateway->toArray();
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'Payment gateway could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canUpdatePaymentGateway'));
/**
 * GET PagesGet
 * Summary: Filter  pages
 * Notes: Filter pages.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/pages', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $pages = Models\Page::Filter($queryParams)->paginate($count)->toArray();
        $data = $pages['data'];
        unset($pages['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $pages
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST pagePost
 * Summary: Create New page
 * Notes: Create page.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/pages', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $page = new Models\Page($args);
    $validationErrorFields = $page->validate($args);
    if (empty($validationErrorFields)) {
        $page->slug = getSlug($page->title);
        try {
            $page->save();
            $result = $page->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page user could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canCreatePage'));
/**
 * GET PagePageIdGet.
 * Summary: Get page.
 * Notes: Get page.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/pages/{pageId}', function ($request, $response, $args) {
    $result = array();
    $queryParams = $request->getQueryParams();
    try {
        if (!empty($queryParams['type']) && $queryParams['type'] == 'slug') {
            $page = Models\Page::where('slug', $request->getAttribute('pageId'))->first();
        } else {
            $page = Models\Page::where('id', $request->getAttribute('pageId'))->first();
        }
        if (!empty($page)) {
            $result['data'] = $page->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found.', '', 1, 404);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'No record found.', '', 1, 404);
    }
});
/**
 * PUT PagepageIdPut
 * Summary: Update page by admin
 * Notes: Update page by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/pages/{pageId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $page = Models\Page::find($request->getAttribute('pageId'));
    $validationErrorFields = $page->validate($args);
    if (empty($validationErrorFields)) {
        $oldPageTitle = $page->title;
        $page->fill($args);
        if ($page->title != $oldPageTitle) {
            $page->slug = $page->slug = getSlug($page->title);
        }
        try {
            $page->save();
            $result['data'] = $page->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdatePage'));
/**
 * DELETE PagepageIdDelete
 * Summary: DELETE page by admin
 * Notes: DELETE page by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/pages/{pageId}', function ($request, $response, $args) {
    $result = array();
    $page = Models\Page::find($request->getAttribute('pageId'));
    try {
        if (!empty($page)) {
            $page->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Page could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeletePage'));
/**
 * GET SettingcategoriesGet
 * Summary: Filter  Setting categories
 * Notes: Filter Setting categories.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/setting_categories', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        if (empty($queryParams['sortby'])) {
            $queryParams['sortby'] = 'ASC';
        }
        $settingCategories = Models\SettingCategory::Filter($queryParams);
        // We are not implement Widget now, So we doen't return Widget data
        $settingCategories = $settingCategories->where('id', '!=', '8');
        $plugins = explode(',', SITE_ENABLED_PLUGINS);
        if (!in_array('Contest/Contest', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '9');
        }
        if (!in_array('Job/Job', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '10');
        }
        if (!in_array('Quote/Quote', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '11');
        }
        if (!in_array('Bidding/Exam', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '128');
        }
        if (!in_array('Bidding/Bidding', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '129');
        }
        if (!in_array('Bidding/Dispute', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '131');
        }
        if (!in_array('Contest/ImageResource', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '133');
        }
        if (!in_array('Portfolio/Portfolio', $plugins)) {
            $settingCategories = $settingCategories->where('id', '!=', '132');
        }
        $settingCategories = $settingCategories->paginate($count)->toArray();
        $data = $settingCategories['data'];
        unset($settingCategories['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $settingCategories
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListSettingCategory'));
/**
 * GET SettingcategoriesSettingCategoryIdGet
 * Summary: Get setting categories.
 * Notes: GEt setting categories.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/setting_categories/{settingCategoryId}', function ($request, $response, $args) {
    $result = array();
    $settingCategory = Models\SettingCategory::find($request->getAttribute('settingCategoryId'));
    if (!empty($settingCategory)) {
        $result['data'] = $settingCategory->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canListSettingCategory'));
/**
 * GET SettingGet .
 * Summary: Get settings.
 * Notes: GEt settings.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/settings', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        if (empty($queryParams['sortby'])) {
            $queryParams['sortby'] = 'ASC';
        }
        $plugins = explode(',', SITE_ENABLED_PLUGINS);
        $settings = Models\Setting::Filter($queryParams);
        if (!in_array('Contest/Contest', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '9');
        }
        if (!in_array('Job/Job', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '10');
        }
        if (!in_array('Quote/Quote', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '11');
            $settings = $settings->whereNotIn('name', array('IS_ALLOW_PROVIDER_TO_SEND_MESSAGE_BEFORE_PAY_CREDIT', 'IS_ALLOW_PROVIDER_TO_VIEW_ADDRESS_BEFORE_PAY_CREDIT', 'CREDIT_POINT_FOR_SENDING_QUOTE_FOR_REQUEST'));
        }
        if (!in_array('Bidding/Exam', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '128');
        }
        if (!in_array('Portfolio/Portfolio', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '132');
        }
        if (!in_array('Bidding/Bidding', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '129');
             $settings = $settings->whereNotIn('name', array('CREDIT_POINT_FOR_BIDDING_A_PROJECT'));
        } else {
            if (!in_array('Bidding/Milestone', $plugins)) {
                $settings = $settings->where('name', '!=', 'PROJECT_COMMISSION_FROM_EMPLOYER_FOR_MILESTONE');
                $settings = $settings->where('name', '!=', 'PROJECT_COMMISSION_FROM_FREELANCER_FOR_MILESTONE');
            }
            if (!in_array('Bidding/Invoice', $plugins)) {
                $settings = $settings->where('name', '!=', 'PROJECT_COMMISSION_FROM_FREELANCER_FOR_INVOICE');
                $settings = $settings->where('name', '!=', 'PROJECT_COMMISSION_FROM_EMPLOYER_FOR_INVOICE');
            }
        }
        if (!in_array('Bidding/Dispute', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '131');
        }
        if (in_array('Common/Subscription', $plugins)) {
            if (!in_array('Bidding/Bidding', $plugins)) {
                $settings = $settings->where('name', '!=', 'CREDIT_POINT_FOR_BIDDING_A_PROJECT');
            }
            if (!in_array('Quote/Quote', $plugins)) {
                $settings = $settings->where('name', '!=', 'CREDIT_POINT_FOR_SENDING_QUOTE_FOR_REQUEST');
            }
        }
        if (!in_array('Contest/ImageResource', $plugins)) {
            $settings = $settings->where('setting_category_id', '!=', '133');
        }
        if (in_array('Quote/Quote', $plugins)) {
            $settingRequestType = Models\Setting::where('name', 'SENDING_QUOTE_REQUEST_FLOW_TYPE')->select('value')->first();
            if (!empty($settingRequestType)) {
                if ($settingRequestType->value == 'Send to All Relevant Service Provider') {
                    $hiddenValue = array(
                        'QUOTE_VISIBLE_LIMIT',
                        'QUOTE_REQUEST_SENDING_RATING_UPTO',
                        'TIME_LIMIT_AFTER_OTHER_PROVIDERS_QUOTE_VISIBLE_TO_REQUESTOR',
                        'TIME_LIMIT_AFTER_OTHER_PROVIDER_GET_QUOTE_REQUEST'
                    );
                    $settings = $settings->whereNotIn('name', $hiddenValue);
                } elseif ($settingRequestType->value == 'Limited Quote Per Limited Period') {
                    $hiddenValue = array(
                        'QUOTE_REQUEST_SENDING_RATING_UPTO',
                        'TIME_LIMIT_AFTER_OTHER_PROVIDER_GET_QUOTE_REQUEST'
                    );
                    $settings = $settings->whereNotIn('name', $hiddenValue);
                } elseif ($settingRequestType->value == 'Rating Basis') {
                    $hiddenValue = array(
                        'QUOTE_VISIBLE_LIMIT',
                        'TIME_LIMIT_AFTER_OTHER_PROVIDERS_QUOTE_VISIBLE_TO_REQUESTOR'
                    );
                    $settings = $settings->whereNotIn('name', $hiddenValue);
                }
            }
        }
        if (!empty($queryParams['limit']) && $queryParams['limit'] == 'all') {
            $settings = $settings->get()->toArray();
            $result['data'] = $settings;
        } else {
            $settings = $settings->paginate($count)->toArray();
            $data = $settings['data'];
            unset($settings['data']);
            $result = array(
                'data' => $data,
                '_metadata' => $settings
            );
        }
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * GET settingssettingIdGet
 * Summary: GET particular Setting.
 * Notes: Get setting.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/settings/{settingId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'setting_category'
    );
    $setting = Models\Setting::with($enabledIncludes)->find($request->getAttribute('settingId'));
    if (!empty($setting)) {
        $result['data'] = $setting->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewSetting'));
$app->GET('/api/v1/site_languages', function ($request, $response, $args) {
    $result = array();
	$lan = array(
				array(
				"iso2" => "en",
				"name" => "English"
				),
				array(
				"iso2" => "ar",
				"name" => "Arabic"
				),
			);
    $result['data'] = $lan;
    return renderWithJson($result);
});
/**
 * PUT SettingsSettingIdPut
 * Summary: Update setting by admin
 * Notes: Update setting by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/settings/{settingId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $setting = Models\Setting::find($request->getAttribute('settingId'));
    $setting->fill($args);
    try {
        if (!empty($setting)) {
            if ($setting->name == 'ALLOWED_SERVICE_LOCATIONS') {
                $country_list = array();
                $city_list = array();
                $allowed_locations = array();
                if (!empty(!empty($args['allowed_countries']))) {
                    foreach ($args['allowed_countries'] as $key => $country) {
                        $country_list[$key]['id'] = $country['id'];
                        $country_list[$key]['name'] = $country['name'];
                        $country_list[$key]['iso_alpha2'] = '';
                        $country_details = Models\Country::select('iso_alpha2')->where('id', $country['id'])->first();
                        if (!empty($country_details)) {
                            $country_list[$key]['iso_alpha2'] = $country_details->iso_alpha2;
                        }
                    }
                    $allowed_locations['allowed_countries'] = $country_list;
                }
                if (!empty(!empty($args['allowed_cities']))) {
                    foreach ($args['allowed_cities'] as $key => $city) {
                        $city_list[$key]['id'] = $city['id'];
                        $city_list[$key]['name'] = $city['name'];
                    }
                    $allowed_locations['allowed_cities'] = $city_list;
                }
                $setting->value = json_encode($allowed_locations);
            }
            $setting->save();
            // Handle watermark image uploaad in settings
            if ($setting->name == 'WATERMARK_IMAGE' && !empty($args['image'])) {
                saveImage('WaterMark', $args['image'], $setting->id);
            }
            $result['data'] = $setting->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Setting could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canUpdateSetting'));
/**
 * GET EmailTemplateGet
 * Summary: Get email templates lists
 * Notes: Get email templates lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/email_templates', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $templates = array(
            'Admin Add Fund',
            'Admin Deduct Fund'
        ); 
        $emailTemplates = Models\EmailTemplate::whereNotIn('name', $templates)->Filter($queryParams);
        if (!isPluginEnabled('Bidding/Bidding')) {
            $biddingTemplate = array(
                'New Bid Notification',
                'New Bid Notification',
                'Bid Notification',
                'New project opened for bidding',
                'Project Cancelled Alert',
                'Admin Project Status Alert',
                'New MileStone Notification',
                'Bidding Expired Alert',
                'Admin Dispute Alert',
                'Dispute Alert',
                'Mutual Cancel Alert',
                'Accept Mutual Cancel',
                'Reject Mutual Cancel',
                'Project Closed Alert',
                'Update Bid Notification',
                'Update Project Notification',
                'Bidder Winner Acceptance Notification',
                'Work Completed Alert For Project Owner',
                'projectopenstatus',                
                'Project Favorite Added',
                'Start Skill Test Notification',
                'Project Feedback Received Notification',
                'Winner Reject Notification',
                'Winner Acceptance Notification',
                'Bid Withdraw Notification'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $biddingTemplate);
        }
        if (!isPluginEnabled('Contest/Contest')) {
            $contestTemplate = array(
                'contestactivityalert',
                'entrystatuschangealert',
                'conteststatuschangealert',
                'newcontestforparticipants',
                'newcontest',
                'newcontestentry',
                'contestpaymentpendingalert'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $contestTemplate);
        }
        if (!isPluginEnabled('Bidding/Invoice')) {
            $invoiceTemplate = array(
                'New Invoice Received Notification',
                'Invoice Paid Notification'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $invoiceTemplate);
        }
        if (!isPluginEnabled('Bidding/Milestone')) {
            $milestoneTemplate = array(
                'Milestone Cancelled Notification',
                'Milestone - Escrow Released Notification',
                'Milestone - Requested Escrow Amount Release Notification',
                'Milestone - Milestone Completed Notification',
                'Milestone - Escrow Amount Paid Notification',
                'Milestone - Escrow Requested Notification',
                'New Milestone Requested Notification',
                'New Milestone Notification',
                'Milestone Approved Notification'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $milestoneTemplate);
        }
        if (!isPluginEnabled('Job/Job')) {
            $jobTemplate = array(
                'New job opened',
                'Admin Job Status Alert',
                'Job Cancelled Alert',
                'Job Expired Alert',
                'New Resume Notification'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $jobTemplate);
        }
        if (!isPluginEnabled('Portfolio/Portfolio')) {
            $portfolioTemplate = array(
                'New portfolio opened',
                'Portfolio Review Added',
                'Portfolio Favorite Added'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $portfolioTemplate);
        }
        if (!isPluginEnabled('Quote/Quote')) {
            $quoteTemplate = array(
                'Work Completed Notification',
                'Work Closed Notification',
                'Quote Updated Notification',
                'Quote Received Notification',
                'New Quote Request Received Notification',
                'Hired Notification',
                'Quote - Feedback Updated Notification',
                'Quote - Feedback Received Notification'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $quoteTemplate);
        }
        if (!isPluginEnabled('Common/Subscription')) {
            $subscriptionTemplate = array(
                'Credit plan expired'
            );
            $emailTemplates = $emailTemplates->whereNotIn('name', $subscriptionTemplate);
        }
        $emailTemplates = $emailTemplates->paginate($count)->toArray();
        $data = $emailTemplates['data'];
        unset($emailTemplates['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $emailTemplates
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListEmailTemplate'));
/**
 * GET EmailTemplateemailTemplateIdGet
 * Summary: Get paticular email templates
 * Notes: Get paticular email templates
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/email_templates/{emailTemplateId}', function ($request, $response, $args) {
    $result = array();
    $emailTemplate = Models\EmailTemplate::find($request->getAttribute('emailTemplateId'));
    if (!empty($emailTemplate)) {
        $result['data'] = $emailTemplate->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewEmailTemplate'));
/**
 * PUT EmailTemplateemailTemplateIdPut
 * Summary: Put paticular email templates
 * Notes: Put paticular email templates
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/email_templates/{emailTemplateId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $emailTemplate = Models\EmailTemplate::find($request->getAttribute('emailTemplateId'));
    $validationErrorFields = $emailTemplate->validate($args);
    if (empty($validationErrorFields)) {
        $emailTemplate->fill($args);
        try {
            $emailTemplate->save();
            $result['data'] = $emailTemplate->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Email template could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'Email template could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateEmailTemplate'));
/**
 * GET activitiesGet
 * Summary: Fetch all activities
 * Notes: Returns all activities from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/activities', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $activities = Models\Activity::Filter($queryParams);
        if (!isPluginEnabled('Bidding/Bidding')) {
            $activities = $activities->whereNotIn('class', ['Project', 'Milestone', 'Bid', 'ProjectBidInvoice']);
        }
        if (!isPluginEnabled('Contest/Contest')) {
            $activities = $activities->whereNotIn('class', ['Contest', 'ContestUser']);
        }
        if (!isPluginEnabled('Job/Job')) {
            $activities = $activities->whereNotIn('class', ['Job', 'JobApply']);
        }
        if (!isPluginEnabled('Quote/Quote')) {
            $activities = $activities->whereNotIn('class', ['QuoteService', 'QuoteBid', 'QuoteRequest']);
        }
        if (!isPluginEnabled('Portfolio/Portfolio')) {
            $activities = $activities->whereNotIn('class', ['Portfolio']);
        }
        $enabledIncludes = array(
            'user',
            'other_user',
            'foreign'
        );
        $activities = $activities->with($enabledIncludes)->paginate($count)->toArray();
        $data = $activities['data'];
        unset($activities['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $activities
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListActivity'));
/**
 * GET activitiesGet
 * Summary: Fetch all activities
 * Notes: Returns all activities from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/me/activities', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $activities = Models\Activity::Filter($queryParams);
        if (!isPluginEnabled('Bidding/Bidding')) {
            $activities = $activities->whereNotIn('class', ['Project', 'Milestone', 'Bid', 'ProjectBidInvoice']);
        }
        if (!isPluginEnabled('Contest/Contest')) {
            $activities = $activities->whereNotIn('class', ['Contest', 'ContestUser']);
        }
        if (!isPluginEnabled('Job/Job')) {
            $activities = $activities->whereNotIn('class', ['Job', 'JobApply']);
        }
        if (!isPluginEnabled('Quote/Quote')) {
            $activities = $activities->whereNotIn('class', ['QuoteService', 'QuoteBid', 'QuoteRequest']);
        }
        if (!isPluginEnabled('Portfolio/Portfolio')) {
            $activities = $activities->whereNotIn('class', ['Portfolio']);
        }
         $enabledIncludes = array(
            'user',
            'other_user',
            'foreign'
        );
        $followers = Models\Follower::select('foreign_id as id', 'class')->where('user_id', $authUser->id)->get();
        $activities = $activities->where(function ($mainQuery) use ($authUser, $followers) {           
            $mainQuery->orWhere(function ($query) use ($authUser, $followers) {
                $query->where('other_user_id', $authUser['id']);
            });         
            $mainQuery->orWhere(function ($query) use ($authUser, $followers) {                
                $contestFollower = $projectFollower = $quoteServicesFollower = $portfoliosFollower =  $contestUsersFollower = $usersFollower = array();
                if (!empty($followers)) {
                    foreach ($followers as $follower) {
                        if ($follower->class == 'Contest') {
                            $contestFollower[]['id'] = $follower->id;
                        }
                        if ($follower->class == 'Project') {
                            $projectFollower[]['id'] = $follower->id;
                        }
                        if ($follower->class == 'QuoteService') {
                            $quoteServicesFollower[]['id'] = $follower->id;
                        }
                        if ($follower->class == 'Portfolio') {
                            $portfoliosFollower[]['id'] = $follower->id;
                        }
                        if ($follower->class == 'ContestUser') {
                            $contestUsersFollower[]['id'] = $follower->id;
                        }
                        if ($follower->class == 'User') {
                            $usersFollower[]['id'] = $follower->id;
                        }
                    }
                }                    
               if (isPluginEnabled('Contest/Contest')) {
                    $contests = Models\Contest::where('user_id', $authUser->id)->OrWhereIn('user_id', $usersFollower)->select('id')->get();
                    if (!empty($contests)) {
                        $contests = array_unique(array_merge($contests->toArray(), $contestFollower), SORT_REGULAR);
                        $query->orWhere(function ($query1) use ($contests, $authUser, $followers) {
                            $query1->where('model_class', 'Contest')->whereIn('model_id', $contests);
                            $query1->Where(function ($queryContest) use ($authUser, $followers) {
                                $queryContest->Where(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', '!=',  $authUser['id']);
                                    $squery->where('other_user_id', 0);
                                });
                                /*$queryContest->orWhere(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', $authUser['id']);
                                    $squery->where('activity_type', '!=', \Constants\ActivityType::Notification);
                                });*/
                            });
                        });
                    }
                }
                if (isPluginEnabled('Bidding/Bidding')) { 
                    $projects = Models\Project::where('user_id', $authUser->id)->OrWhereIn('user_id', $usersFollower)->select('id')->get();                         
                    if (!empty($projects)) {
                        $projects = array_unique(array_merge($projects->toArray(), $projectFollower), SORT_REGULAR);
                        $query->orWhere(function ($query2) use ($projects, $authUser, $followers) {
                            $query2->where('model_class', 'Project')->whereIn('model_id', $projects);
                            $query2->Where(function ($queryproject) use ($authUser, $followers) {
                                $queryproject->Where(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', '!=',  $authUser['id']);
                                    $squery->where('other_user_id', 0);
                                });
                                /*$queryproject->orWhere(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', $authUser['id']);
                                    $squery->where('activity_type', '!=', \Constants\ActivityType::Notification);
                                });*/
                            });
                        });
                    }
                }
                if (isPluginEnabled('Quote/Quote')) {
                    $quoteServices = Models\QuoteService::where('user_id', $authUser->id)->OrWhereIn('user_id', $usersFollower)->select('id')->get();
                    if (!empty($quoteServices)) {
                        $quoteServices = array_unique(array_merge($quoteServices->toArray(), $quoteServicesFollower), SORT_REGULAR);
                        $query->orWhere(function ($query3) use ($quoteServices, $authUser, $followers) {
                            $query3->where('model_class', 'QuoteBid')->whereIn('model_id', $quoteServices);
                            $query3->Where(function ($queryquote) use ($authUser, $followers) {
                                $queryquote->Where(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', '!=',  $authUser['id']);
                                    $squery->where('other_user_id', 0);
                                });
                                /*$queryquote->orWhere(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', $authUser['id']);
                                    $squery->where('activity_type', '!=', \Constants\ActivityType::Notification);
                                });*/
                            });
                        });
                    }
                }
                if (isPluginEnabled('Portfolio/Portfolio')) {
                    $portfolios = Models\Portfolio::where('user_id', $authUser->id)->OrWhereIn('user_id', $usersFollower)->select('id')->get();
                    if (!empty($portfolios)) {
                        $portfolios = array_unique(array_merge($portfolios->toArray(), $portfoliosFollower), SORT_REGULAR);
                        $query->orWhere(function ($query4) use ($portfolios, $authUser, $followers) {
                            $query4->where('model_class', 'Portfolio')->whereIn('model_id', $portfolios);
                            $query4->Where(function ($queryproject) use ($authUser, $followers) {
                                $queryproject->Where(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', '!=',  $authUser['id']);
                                    $squery->where('other_user_id', 0);
                                });
                                /*$queryproject->orWhere(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', $authUser['id']);
                                    $squery->where('activity_type', '!=', \Constants\ActivityType::Notification);
                                });*/
                            });
                        });
                    }
                }
                if (isPluginEnabled('Contest/Contest')) {
                    $contestUsers = Models\ContestUser::where('user_id', $authUser->id)->OrWhereIn('user_id', $usersFollower)->select('id')->get();
                    if (!empty($contestUsers)) {
                        $contestUsers = array_unique(array_merge($contestUsers->toArray(), $contestUsersFollower), SORT_REGULAR);
                        $query->orWhere(function ($query5) use ($contestUsers, $authUser, $followers) {
                            $query5->where('model_class', 'Contest')->whereIn('model_id', $contestUsers);
                            $query5->Where(function ($queryproject) use ($authUser, $followers) {
                                $queryproject->Where(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', '!=',  $authUser['id']);
                                    $squery->where('other_user_id', 0);
                                });
                                /*$queryproject->orWhere(function ($squery) use ($authUser, $followers) {
                                    $squery->where('user_id', $authUser['id']);
                                    $squery->where('activity_type', '!=', \Constants\ActivityType::Notification);
                                });*/
                            });
                        });
                    }
                }
                
            });
        });
        $activities = $activities->with($enabledIncludes)->paginate($count)->toArray();        
        $data = $activities['data'];
        unset($activities['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $activities
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListMeActivity'));
/**
 * GET salaryTypesGet
 * Summary: Fetch all salary types
 * Notes: Returns all salary types from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/salary_types', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $salaryTypes = Models\SalaryType::Filter($queryParams)->paginate($count)->toArray();
        $data = $salaryTypes['data'];
        unset($salaryTypes['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $salaryTypes
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST salaryTypesPost
 * Summary: Creates a new salary type
 * Notes: Creates a new salary type
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/salary_types', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $salaryType = new Models\SalaryType($args);
    $result = array();
    try {
        $validationErrorFields = $salaryType->validate($args);
        if (empty($validationErrorFields)) {
            $salaryType->save();
            $result = $salaryType->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Salary type could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Salary type could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreateSalaryType'));
/**
 * DELETE salaryTypesSalaryTypeIdDelete
 * Summary: Delete salary type
 * Notes: Deletes a single salary type based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/salary_types/{salaryTypeId}', function ($request, $response, $args) {
    $salaryType = Models\SalaryType::find($request->getAttribute('salaryTypeId'));
    try {
        $salaryType->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'Salary type could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteSalaryType'));
/**
 * GET salaryTypesSalaryTypeIdGet
 * Summary: Fetch salary type
 * Notes: Returns a salary type based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/salary_types/{salaryTypeId}', function ($request, $response, $args) {
    $result = array();
    $salaryType = Models\SalaryType::find($request->getAttribute('salaryTypeId'))->first();
    if (!empty($salaryType)) {
        $result['data'] = $salaryType;
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewSalaryType'));
/**
 * PUT salaryTypesSalaryTypeIdPut
 * Summary: Update salary type by its id
 * Notes: Update salary type by its id
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/salary_types/{salaryTypeId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $salaryType = Models\SalaryType::find($request->getAttribute('salaryTypeId'));
    $salaryType->fill($args);
    $result = array();
    try {
        $validationErrorFields = $salaryType->validate($args);
        if (empty($validationErrorFields)) {
            $salaryType->save();
            $result = $salaryType->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Salary type could not be updated. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Salary type could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canUpdateSalaryType'));
/**
 * POST Skill
 * Summary: Skill post
 * Notes:  Skill post
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/skills', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $skill = new Models\Skill($args);
    $result = array();
    try {
        $validationErrorFields = $skill->validate($args);
        if (empty($validationErrorFields)) {
            $skill->slug = Inflector::slug(strtolower($skill->name), '-');
            $skill->save();
            $result['data'] = $skill->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, ' Skill could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Skill could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreateSkill'));
/**
 * GET Skill SkillId get
 * Summary: Fetch a Skill based on a Skill Id
 * Notes: Returns a Skill from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/skills/{skillId}', function ($request, $response, $args) {
    $result = array();
    try {
        $skill = Models\Skill::find($request->getAttribute('skillId'));
        if (!empty($skill)) {
            $result['data'] = $skill->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewSkill'));
/**
 * GET SkillGet
 * Summary: Fetch all Skill
 * Notes: Returns all Skill from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/skills', function ($request, $response, $args) {
	header("Access-Control-Allow-Origin: *");
    $queryParams = $request->getQueryParams();
    $count = PAGE_LIMIT;
    if (!empty($queryParams['limit'])) {
        $count = $queryParams['limit'];
    }
    $results = array();
    try {
        $skill = Models\Skill::Filter($queryParams);
        if (empty($queryParams['filter']) || !empty($queryParams['filter']) && $queryParams['filter'] != 'all') {
            $skill = $skill->where('is_active', 1);
            if(!empty($queryParams['project_id'])) {
                $skillIds = array();
                $projectSkills = Models\SkillsProjects::select('skill_id')->where('project_id', $queryParams['project_id'])->get()->toArray();
                foreach ($projectSkills as $skillId) {
                    $skillIds[] = $skillId['skill_id'];
                }
                if(!empty($skillIds)) {
                    $skill->orWhere(function ($q1) use ($skillIds) {
                        $q1->whereIn('id', $skillIds);
                    });
                    $skill = $skill->whereIn('id', $skillIds);
                }
            }
            if(!empty($queryParams['job_id'])) {
                $skillIds = array();
                $jobSkills = Models\JobsSkill::select('skill_id')->where('job_id', $queryParams['job_id'])->get()->toArray();
                foreach ($jobSkills as $skillId) {
                    $skillIds[] = $skillId['skill_id'];
                }
                if(!empty($skillIds)) {
                    $skill->orWhere(function ($q1) use ($skillIds) {
                        $q1->whereIn('id', $skillIds);
                    });
                    $skill = $skill->whereIn('id', $skillIds);
                }
            }
            if(!empty($queryParams['portfolio_id'])) {
                $skillIds = array();
                $portfolioSkills = Models\SkillsPortfolios::select('skill_id')->where('portfolio_id', $queryParams['portfolio_id'])->get()->toArray();
                foreach ($portfolioSkills as $skillId) {
                    $skillIds[] = $skillId['skill_id'];
                }
                if(!empty($skillIds)) {
                    $skill->orWhere(function ($q1) use ($skillIds) {
                        $q1->whereIn('id', $skillIds);
                    });
                    $skill = $skill->whereIn('id', $skillIds);
                }
            }
        }
        if (!empty($queryParams['limit']) && $queryParams['limit'] == 'all') {
            $skill = $skill->get();
            $results['data'] = $skill;
        } else {
            $skill = $skill->paginate($count)->toArray();
            $data = $skill['data'];
            unset($skill['data']);
            $results = array(
                'data' => $data,
                '_metadata' => $skill
            );
        }
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->OPTIONS('/api/v1/skills', function ($request, $response, $args) {
	header("Access-Control-Allow-Origin: *");
    $queryParams = $request->getQueryParams();
    $count = PAGE_LIMIT;
    if (!empty($queryParams['limit'])) {
        $count = $queryParams['limit'];
    }
    $results = array();
    try {
        $skill = Models\Skill::Filter($queryParams);
        if (empty($queryParams['filter']) || !empty($queryParams['filter']) && $queryParams['filter'] != 'all') {
            $skill = $skill->where('is_active', 1);
            if(!empty($queryParams['project_id'])) {
                $skillIds = array();
                $projectSkills = Models\SkillsProjects::select('skill_id')->where('project_id', $queryParams['project_id'])->get()->toArray();
                foreach ($projectSkills as $skillId) {
                    $skillIds[] = $skillId['skill_id'];
                }
                if(!empty($skillIds)) {
                    $skill->orWhere(function ($q1) use ($skillIds) {
                        $q1->whereIn('id', $skillIds);
                    });
                    $skill = $skill->whereIn('id', $skillIds);
                }
            }
            if(!empty($queryParams['job_id'])) {
                $skillIds = array();
                $jobSkills = Models\JobsSkill::select('skill_id')->where('job_id', $queryParams['job_id'])->get()->toArray();
                foreach ($jobSkills as $skillId) {
                    $skillIds[] = $skillId['skill_id'];
                }
                if(!empty($skillIds)) {
                    $skill->orWhere(function ($q1) use ($skillIds) {
                        $q1->whereIn('id', $skillIds);
                    });
                    $skill = $skill->whereIn('id', $skillIds);
                }
            }
            if(!empty($queryParams['portfolio_id'])) {
                $skillIds = array();
                $portfolioSkills = Models\SkillsPortfolios::select('skill_id')->where('portfolio_id', $queryParams['portfolio_id'])->get()->toArray();
                foreach ($portfolioSkills as $skillId) {
                    $skillIds[] = $skillId['skill_id'];
                }
                if(!empty($skillIds)) {
                    $skill->orWhere(function ($q1) use ($skillIds) {
                        $q1->whereIn('id', $skillIds);
                    });
                    $skill = $skill->whereIn('id', $skillIds);
                }
            }
        }
        if (!empty($queryParams['limit']) && $queryParams['limit'] == 'all') {
            $skill = $skill->get();
            $results['data'] = $skill;
        } else {
            $skill = $skill->paginate($count)->toArray();
            $data = $skill['data'];
            unset($skill['data']);
            $results = array(
                'data' => $data,
                '_metadata' => $skill
            );
        }
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * DELETE Skill SkillIdDelete
 * Summary: Delete Skill Skill
 * Notes: Deletes a single  Skill based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/skills/{skillId}', function ($request, $response, $args) {
    $result = array();
    $skill = Models\Skill::find($request->getAttribute('skillId'));
    if (empty($skill)) {
        return renderWithJson($result, 'No record found.', '', 1);
    }
    try {
        $skill->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'Skill could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteSkill'));
/**
 * PUT skill SkillIdPut
 * Summary: Update Skill
 * Notes: Update Skill
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/skills/{skillId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $skill = Models\Skill::find($request->getAttribute('skillId'));
    if (empty($skill)) {
        return renderWithJson($result, 'No record found.', '', 1);
    }
    $validationErrorFields = $skill->validate($args);
    if (empty($validationErrorFields)) {
        $skill->fill($args);
        try {
            $skill->slug = Inflector::slug(strtolower($skill->name), '-');
            $skill->save();
            $result['data'] = $skill->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Skill could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Skill could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateSkill'));
/**
 * GET viewsGet
 * Summary: Fetch all views
 * Notes: Returns all views from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/views', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $enabledIncludes = array(
            'user',
            'ip',
            'foreign_view'
        );
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $views = Models\View::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $views['data'];
        unset($views['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $views
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListView'));
/**
 * POST viewsPost
 * Summary: Creates a new view
 * Notes: Creates a new view
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/views', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $view = new Models\View($args);
    $result = array();
    try {
        $view->user_id = $authUser['id'];
        $view->ip_id = saveIp();
        $validationErrorFields = $view->validate($args);
        if (empty($validationErrorFields)) {
            $view->save();
            $result = $view->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'View could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'View could not be added. Please, try again.', '', 1);
    }
});
/**
 * DELETE ViewIdDelete
 * Summary: Delete view
 * Notes: Deletes a single view based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/views/{viewId}', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $views = Models\View::find($request->getAttribute('viewId'));
    try {
        if (!empty($views)) {
            $views->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'View could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteView'));
/**
 * GET viewId get
 * Summary: Fetch a view based on Review Id
 * Notes: Returns a view from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/views/{viewId}', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'user',
        'ip',
        'foreign_view'
    );
    $views = Models\View::with($enabledIncludes)->find($request->getAttribute('viewId'));
    if (!empty($views)) {
        $result['data'] = $views->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found.', '', 1);
    }
})->add(new ACL('canViewSingleView'));
/**
 * GET userLoginsGet
 * Summary: Fetch all user logins
 * Notes: Returns all user logins from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/user_logins', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $enabledIncludes = array(
            'user',
            'ip'
        );
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $userLogins = Models\UserLogin::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $userLogins['data'];
        unset($userLogins['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $userLogins
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListUserLogin'));
/**
 * DELETE userLoginIdDelete
 * Summary: Delete user_logins
 * Notes: Deletes a single  user_logins based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/user_logins/{userLoginId}', function ($request, $response, $args) {
    $result = array();
    $UserLogin = Models\UserLogin::find($request->getAttribute('userLoginId'));
    if (empty($UserLogin)) {
        return renderWithJson($result, 'No record found.', '', 1);
    }
    try {
        $UserLogin->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'UserLogin could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteUserLogin'));
/**
 * GET CitieuserLoginIdsGet
 * Summary: Get  particular userLogin
 * Notes: Get  particular userLogin
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/user_logins/{userLoginId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'user',
        'ip'
    );
    $UserLogin = Models\UserLogin::with($enabledIncludes)->find($request->getAttribute('userLoginId'));
    if (!empty($UserLogin)) {
        $result['data'] = $UserLogin->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canviewUserLogin'));
/**
 * GET CitiesGet
 * Summary: Filter  cities
 * Notes: Filter cities.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/cities', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        if (!empty($queryParams['limit']) && $queryParams['limit'] == 'all') {
            $result['data'] = Models\City::where('is_active', 1)->get();
        } else {
            $count = PAGE_LIMIT;
            if (!empty($queryParams['limit'])) {
                $count = $queryParams['limit'];
            }
            $cities = Models\City::leftJoin('states', 'cities.state_id', '=', 'states.id');
            $cities = $cities->leftJoin('countries', 'cities.country_id', '=', 'countries.id');
            $cities = $cities->select('cities.*', 'states.name as state_name', 'countries.name as country_name');
            $enabledIncludes = array(
                'country',
                'state'
            );
            $cities = $cities->with($enabledIncludes)->Filter($queryParams);
            $cities = $cities->paginate($count)->toArray();
            $data = $cities['data'];
            unset($cities['data']);
            $result = array(
                'data' => $data,
                '_metadata' => $cities
            );
        }
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST citiesPost
 * Summary: create new city
 * Notes: create new city
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/cities', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $city = new Models\City($args);
    $validationErrorFields = $city->validate($args);
    if (empty($validationErrorFields)) {
        $city->slug = Inflector::slug(strtolower($city->name), '-');
        try {
            $city->save();
            $result = $city->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'City could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'city could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canCreateCity'));
/**
 * GET CitiesGet
 * Summary: Get  particular city
 * Notes: Get  particular city
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/cities/{cityId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'country',
        'state'
    );
    $city = Models\City::with($enabledIncludes)->find($request->getAttribute('cityId'));
    if (!empty($city)) {
        $result['data'] = $city->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * PUT CitiesCityIdPut
 * Summary: Update city by admin
 * Notes: Update city by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/cities/{cityId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $city = Models\City::find($request->getAttribute('cityId'));
    $validationErrorFields = $city->validate($args);
    if (empty($validationErrorFields)) {
        $city->fill($args);
        $city->slug = Inflector::slug(strtolower($city->name), '-');
        try {
            $city->save();
            $result['data'] = $city->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'City could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'City could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateCity'));
/**
 * DELETE CitiesCityIdDelete
 * Summary: DELETE city by admin
 * Notes: DELETE city by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/cities/{cityId}', function ($request, $response, $args) {
    $result = array();
    $city = Models\City::find($request->getAttribute('cityId'));
    try {
        $city->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'City could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteCity'));
/**
 * GET StatesGet
 * Summary: Filter  states
 * Notes: Filter states.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/states', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'country'
        );
        if (!empty($queryParams['limit']) && $queryParams['limit'] == 'all') {
            $result['data'] = Models\State::with($enabledIncludes)->where('is_active', 1)->get();
        } else {
            $states = Models\State::leftJoin('countries', 'states.country_id', '=', 'countries.id');
            $states = $states->select('states.*', 'countries.name as country_name');
            $states = $states->with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
            $data = $states['data'];
            unset($states['data']);
            $result = array(
                'data' => $data,
                '_metadata' => $states
            );
        }
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST StatesPost
 * Summary: Create New states
 * Notes: Create states.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/states', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $state = new Models\State($args);
    $validationErrorFields = $state->validate($args);
    if (empty($validationErrorFields)) {
        try {
            $state->save();
            $result = $state->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'State could not be added. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'State could not be added. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canCreateState'));
/**
 * GET StatesstateIdGet
 * Summary: Get  particular state
 * Notes: Get  particular state
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/states/{stateId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'country'
    );
    $state = Models\State::with($enabledIncludes)->find($request->getAttribute('stateId'));
    if (!empty($state)) {
        $result['data'] = $state->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewState'));
/**
 * PUT StatesStateIdPut
 * Summary: Update states by admin
 * Notes: Update states.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/states/{stateId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $state = Models\State::find($request->getAttribute('stateId'));
    $validationErrorFields = $state->validate($args);
    if (empty($validationErrorFields)) {
        $state->fill($args);
        try {
            $state->save();
            $result['data'] = $state->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'State could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'State could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateState'));
/**
 * DELETE StatesStateIdDelete
 * Summary: DELETE states by admin
 * Notes: DELETE states by admin
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/states/{stateId}', function ($request, $response, $args) {
    $result = array();
    $state = Models\State::find($request->getAttribute('stateId'));
    try {
        if (!empty($state)) {
            $state->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'State could not be added. Please, try again', '', 1);
    }
})->add(new ACL('canDeleteState'));
/**
 * GET countriesGet
 * Summary: Filter  countries
 * Notes: Filter countries.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/countries', function ($request, $response, $args) use ($app) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        if (!empty($queryParams['limit']) && $queryParams['limit'] == 'all') {
            $result['data'] = Models\Country::get();
        } else {
            $countries = Models\Country::Filter($queryParams)->paginate($count)->toArray();
            $data = $countries['data'];
            unset($countries['data']);
            $result = array(
                'data' => $data,
                '_metadata' => $countries
            );
        }
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST countriesPost
 * Summary: Create New countries
 * Notes: Create countries.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/countries', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $country = new Models\Country($args);
    $validationErrorFields = $country->validate($args);
    if (empty($validationErrorFields)) {
        try {
            $country->save();
            $result = $country->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Country could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Country could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canCreateCountry'));
/**
 * GET countriescountryIdGet
 * Summary: Get countries
 * Notes: Get countries.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/countries/{countryId}', function ($request, $response, $args) {
    $result = array();
    $country = Models\Country::find($request->getAttribute('countryId'));
    if (!empty($country)) {
        $result['data'] = $country->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewCountry'));
/**
 * PUT countriesCountryIdPut
 * Summary: Update countries by admin
 * Notes: Update countries.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/countries/{countryId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $country = Models\Country::find($request->getAttribute('countryId'));
    $validationErrorFields = $country->validate($args);
    if (empty($validationErrorFields)) {
        $country->fill($args);
        try {
            $country->save();
            $result['data'] = $country->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Country could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Country could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateCountry'));
/**
 * DELETE countrycountryIdDelete
 * Summary: DELETE country by admin
 * Notes: DELETE country.
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/countries/{countryId}', function ($request, $response, $args) {
    $result = array();
    $country = Models\Country::find($request->getAttribute('countryId'));
    try {
        if (!empty($country)) {
            $country->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Country could not be deleted. Please, try again.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Country could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteCountry'));
/**
 * GET LanguageGet
 * Summary: Filter  language
 * Notes: Filter language.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/languages', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        if (!empty($queryParams['limit']) && $queryParams['limit'] == 'all') {
            $result['data'] = Models\Language::Filter($queryParams)->get();
        } else {
            $count = PAGE_LIMIT;
            if (!empty($queryParams['limit'])) {
                $count = $queryParams['limit'];
            }
            $languages = Models\Language::Filter($queryParams)->paginate($count)->toArray();
            $data = $languages['data'];
            unset($languages['data']);
            $result = array(
                'data' => $data,
                '_metadata' => $languages
            );
        }
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST LanguagePost
 * Summary: add language
 * Notes: add language
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/languages', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $language = new Models\Language($args);
    $validationErrorFields = $language->validate($args);
    if (checkAlreadyLanguageExists($args['name'])) {
        $validationErrorFields['name'] = 'Already this language exists.';
    }
    if (empty($validationErrorFields)) {
        try {
            $language->save();
            $result = $language->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Language user could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Language could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canCreateLanguage'));
/**
 * GET LanguagelanguageIdGet
 * Summary: Get particular language
 * Notes: Get particular language.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/languages/{languageId}', function ($request, $response, $args) {
    $result = array();
    $language = Models\Language::find($request->getAttribute('languageId'));
    if (!empty($language)) {
        $result['data'] = $language->toArray();
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'Language not found', '', 1);
    }
})->add(new ACL('canViewLanguage'));
/**
 * PUT LanguagelanguageIdPut
 * Summary: Update language by admin
 * Notes: Update language by admin
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/languages/{languageId}', function ($request, $response, $args) {
    $args = $request->getParsedBody();
    $result = array();
    $language = Models\Language::find($request->getAttribute('languageId'));
    $validationErrorFields = $language->validate($args);
    if (empty($validationErrorFields)) {
        $language->fill($args);
        try {
            $language->save();
            $result['data'] = $language->toArray();
            return renderWithJson($result);
        } catch (Exception $e) {
            return renderWithJson($result, 'Language could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Language could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canUpdateLanguage'));
/**
 * DELETE LanguageLanguageIdDelete
 * Summary: DELETE language by its id
 * Notes: DELETE language.
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/languages/{languageId}', function ($request, $response, $args) {
    $result = array();
    $language = Models\Language::find($request->getAttribute('languageId'));
    try {
        if (!empty($language)) {
            $language->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Language could not be deleted. Please, try again.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Language could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteLanguage'));
/**
 * GET StatsGet
 * Summary: Get site stats lists
 * Notes: Get site stats lists
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/stats', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $result = Models\QuoteService::stats();
    $result['project_count'] = Models\Project::where('is_active', 1)->count();
    $result['contest_count'] = Models\Contest::count();
    $result['job_count'] = Models\Job::count();
    $result['portfolio_count'] = Models\Portfolio::count();
    $result['total_transaction_amount'] = Models\Transaction::sum('amount');
    $result['site_revenue_from_freelancer'] = Models\Transaction::sum('site_revenue_from_freelancer');
    $result['site_revenue_from_employer'] = Models\Transaction::sum('site_revenue_from_employer');
    $result['total_revenue'] = $result['site_revenue_from_freelancer'] + $result['site_revenue_from_employer'];
    $result['customers'] = Models\User::where('is_active', 1)->where('is_email_confirmed', 1)->count();
	$includeModel = array('money_transfer_account');
	//$result['payout_payoneer'] = Models\UserCashWithdrawal::with($includeModel)->where('money_transfer_account.account', 'payoneer')->where('withdrawal_status_id', 1)->get()->toArray();
	$queryParams = array();
	$queryParams['account'] = 'payoneer';
	$queryParams['withdrawal_status_id'] = 1;
	$payout_payoneer = Models\UserCashWithdrawal::with($includeModel)->Filter($queryParams)->selectRaw('sum(amount) as amount')->first()->toArray();
	$result['payout_payoneer'] = (!empty($payout_payoneer["amount"])) ? $payout_payoneer["amount"]:0;
	unset($queryParams['account']);
	$payoutOthers = Models\UserCashWithdrawal::with($includeModel)->Filter($queryParams)->selectRaw('sum(amount) as amount')->first()->toArray();
    $payoutOthers  = (!empty($payoutOthers["amount"])) ? $payoutOthers["amount"]:0;
	$result['payout_other'] = abs($payoutOthers-$result['payout_payoneer']);
	return renderWithJson($result);
})->add(new ACL('canViewStats'));
/**
 * POST AttachmentPost
 * Summary: Add attachment
 * Notes: Add attachment.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/tax_form', function ($request, $response, $args) {
	global $authUser;
	$result = array();
	$args = $request->getParsedBody();
	if (isset($args) && !empty($args)) {
		try {
				if ($args['taxform'] == 'fw9') {
					$taxForm = Models\WNine::where('user_id', $authUser->id)->first();	
					if(empty($taxForm)){
						$taxForm = new Models\WNine($args['w9']);	
					}
					$validationErrorFields = $taxForm->validate($args);
					if (empty($validationErrorFields)) {	
						$taxForm->user_id = $authUser->id;
						$taxForm->updated_at = date("Y-m-d h:i:s");
						$taxForm->save();

						Models\User::where('id', $taxForm->user_id)->update(array(
							'tax_id' => 'fw9'
						));
						/*if (!empty($args['image']) && $taxForm->id) {
							saveImage('TaxForm', $args['image'], $taxForm->id);
						}*/
						$result = $taxForm->toArray();
						$result['download'] = true;	
						return renderWithJson($result);
					} else {
						return renderWithJson($result, 'Tax Form could not be added. Please, try again.', $validationErrorFields, 1);
					}
				} else if ($args['taxform'] == 'fw8bene') {
					$taxForm = Models\WEighth::where('user_id', $authUser->id)->first();	
					if(empty($taxForm)){
						$taxForm = new Models\WEighth($args['form3']);	
					}
					$validationErrorFields = $taxForm->validate($args);
					if (empty($validationErrorFields)) {	
						$taxForm->user_id = $authUser->id;
						$taxForm->updated_at = date("Y-m-d h:i:s");
						$taxForm->save();
						Models\User::where('id', $taxForm->user_id)->update(array(
							'tax_id' => 'fw8bene'
						));
						/*
						if (!empty($args['image']) && $taxForm->id) {
							saveImage('TaxForm', $args['image'], $taxForm->id);
						}*/
						$result = $taxForm->toArray();
						$result['download'] = true;	
						return renderWithJson($result);
					} else {
						return renderWithJson($result, 'Tax Form could not be added. Please, try again.', $validationErrorFields, 1);
					}
				} else {
					$taxForm = Models\TaxForm::where('user_id', $authUser->id)->first();	
					if(empty($taxForm)){
						$taxForm = new Models\TaxForm($args);	
					}
					$validationErrorFields = $taxForm->validate($args);
					if (empty($validationErrorFields)) {	
						$taxForm->user_id = $authUser->id;
						$taxForm->updated_at = date("Y-m-d h:i:s");
						$taxForm->save();

						Models\User::where('id', $taxForm->user_id)->update(array(
							'tax_id' => 'w8ben'
						));
						/*if (!empty($args['image']) && $taxForm->id) {
							saveImage('TaxForm', $args['image'], $taxForm->id);
						}*/
						$result = $taxForm->toArray();
						$result['download'] = true;	
						return renderWithJson($result);
					} else {
						return renderWithJson($result, 'Tax Form could not be added. Please, try again.', $validationErrorFields, 1);
					}
				}
			} catch (Exception $e) {
				return renderWithJson($result, 'Tax Form could not be added. Please, try again.'.$e->getMessage(), '', 1);
			}
	} else {
		$user = Models\User::select('tax_id')->where('id', $authUser->id)->first();
		if ($user->tax_id != "") {
			if ($user->tax_id == 'fw9') {
				$taxForm = Models\WNine::where('user_id', $authUser->id)->first();
				if (!empty($taxForm)) {
					$result['w9'] = $taxForm->toArray();
					$result['w9']['download'] = true;
					$result['w9']["updated_at"] = date("Y-m-d h:i:s");
				} else {
					$result['download'] = false;
					$result["updated_at"] = date("Y-m-d h:i:s");
				}
				$result['taxform'] = 'fw9';
			} else if ($user->tax_id == 'fw8bene') {
				$taxForm = Models\WEighth::where('user_id', $authUser->id)->first();
				if (!empty($taxForm)) {
					$result['form3'] = $taxForm->toArray();
					$result['download'] = true;
					$result['form3']["updated_at"] = date("Y-m-d h:i:s");
				} else {
					$result['download'] = false;
					$result["updated_at"] = date("Y-m-d h:i:s");
				}
				$result['taxform'] = 'fw8bene';
			} else {
				$taxForm = Models\TaxForm::where('user_id', $authUser->id)->first();
				if (!empty($taxForm)) {
					$result = $taxForm->toArray();
					$result['download'] = true;
					$result["updated_at"] = date("Y-m-d h:i:s");
				} else {
					$result['download'] = false;
					$result["updated_at"] = date("Y-m-d h:i:s");
				}
				$result['taxform'] = 'w8ben';
			}
			return renderWithJson($result);
		} else {
			$result['download'] = false;
			$result["updated_at"] = date("Y-m-d h:i:s");
		}
	}
});
$app->GET('/api/v1/tax_form/download', function ($request, $response, $args) {
	global $authUser;
    $args = $request->getParsedBody();
	//require_once  '..' .DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'mpdf'.DIRECTORY_SEPARATOR.'mpdf'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
	require_once  '..' .DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
	$mpdf = new \Mpdf\Mpdf();
	$taxForm = Models\TaxForm::where('user_id', $authUser->id)->first();
	if (!empty($taxForm)) {
		$html = '<style>
				table td{
					font-size:10px;
					font-family: "Segoe UI", Arial, sans-serif;
					line-height:16px;
				}
				</style>
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
				<tr>
					<td style="border-bottom:2px solid #000000;" colspan="3">
						<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
						<tr>
				<td style="width:20%; border-right:2px solid #000000; " align="left">
					<table>
					<tr>
					<td style="padding:0px 10px 10px 0; line-height:16px; font-size:9px;">
					
						Form <strong style="font-size:14px">W-8BEN</strong>
						<br>(Rev. July 2017)
						<br>Department of the Treasury Internal Revenue Service
					
					</td>
					</tr>
					</table>
				</td>
				<td style="width:65%; border-right:2px solid #000000; " align="center">
					<table>
					<tr>
					<td style="padding:0px 10px 10px 10px; line-height:16px;">
					<b>Certificate of Foreign Status of Beneficial Owner for United
					States Tax Withholding and Reporting (Individuals)</b>
						<br>&#x25B6;&nbsp;For use by individuals. Entities must use Form W-8BEN-E.
						<br>&#x25B6;&nbsp;Go to www.irs.gov/FormW8BEN for instructions and the latest information.
						<br>&#x25B6;&nbsp;Give this form to the withholding agent or payer. Do not send to the IRS.
					</ul>
					</td>
					</tr>
					</table>
				</td>
				<td style="width:15%; " align="left">
					<table>
					<tr>
						<td style="padding:0px 0px 10px 10px; line-height:16px; font-size:9px;">
				OMB No. 1545-1621
				</td>
					</tr>
					</table>
				</td>
				</tr>
				</table>
				</td>
				</tr>
				<tr>
					<td colspan="2">Do NOT use this form if:</td>
					<td colspan="1" align="right"><strong>Instead, use Form:</strong></td>
				</tr>
				<tr>
					<td colspan="3">
						<ul>
							<li>You are NOT an individual . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . W-8BEN-E</li>
							<li>You are a U.S. citizen or other U.S. person, including a resident alien individual . . . . . . . . . . . . . . . . . . . W-9</li>
							<li>You are a beneficial owner claiming that income is effectively connected with the conduct of trade or business within the U.S.</li>
							<li>(other than personal services) . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . W-8ECI</li>
							<li>You are a beneficial owner who is receiving compensation for personal services performed in the United States . . . . . . . 8233 or W-4</li>
							<li>You are a person acting as an intermediary . . . . . . . . . . . . . . . . . . . . . . . . . . . . . W-8IMY</li>
						</ul>
					</td>
				</tr>
				<tr>
					<td colspan="3">
						<b>Note:</b> If you are resident in a FATCA partner jurisdiction (i.e., a Model 1 IGA jurisdiction with reciprocity), certain tax account information may be provided to your jurisdiction of residence.
					</td>
				</tr>
				<tr>
				<td style="border-top:1px solid #000000; " colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >

				<tr>
					<td style="border-bottom:1px solid #000000; background:#000000; color:#ffffff; font-weight:bold; text-align:center;">Part I</td>
					<td style="border-bottom:1px solid #000000;"><b>&nbsp; &nbsp;Identification of Beneficial Owner (see instructions)</b></td>
				</tr>
				</table>
				</td>
				</tr>

				<tr>
				<td colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
				<tr>
					<td  style="border-right:1px solid #000000;">1 Name of individual who is the beneficial owner</td>
					<td>&nbsp; &nbsp;2 Country of citizenship</td>
				</tr>
				<tr>
					<td style="background:#f1f4ff; border-bottom:1px solid #000000; border-right:1px solid #000000;">'.$taxForm->beneficial_owner.'</td>
					<td style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->citizenship.'</td>
				</tr>

				</table>
				</td>
				</tr>
				<tr>
				<td colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
				<tr>
					<td>Permanent residence address (street, apt. or suite no., or rural route).<b>Do not use a P.O. box or in-care-of address.</b></td>
				</tr>
				<tr>
				<td style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->permanent_add.'</td>
				</tr>
				</table>
				</td>
				</tr>
				<tr>
				<td colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
				<tr>
					<td style="border-right:1px solid #000000;">City or town, state or province. Include postal code where appropriate.</td>
					<td>&nbsp;&nbsp;Country</td>
				</tr>
				<tr>
					<td style="background:#f1f4ff; border-bottom:1px solid #000000; border-right:1px solid #000000;">'.$taxForm->city1.'</td>
					<td style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->country1.'</td>
				</tr>
				</table>
				</td>
				</tr>
				<tr>
				<td colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
				<tr>
					<td>4 Mailing address (if different from above)</td>
				</tr>
				<tr>
				<td style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->mailing_address.'</td>
				</tr>
				</table>
				</td>
				</tr>
				<tr>
				<td colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center">
				<tr>
					<td style="border-right:1px solid #000000;">City or town, state or province. Include postal code where appropriate.##CITY2##</td>
					<td>Country</td>
				</tr>
				<tr>
					<td style="background:#f1f4ff; border-bottom:1px solid #000000; border-right:1px solid #000000;">'.$taxForm->city2.'</td>
					<td style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->country2.'</td>
				</tr>
				</table>
				</td>
				</tr>
				<tr>
				<td colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
				<tr>
					<td colspan="2" style="border-right:1px solid #000000;">5 U.S. taxpayer identification number (SSN or ITIN), if required (see instructions)</td>
					<td colspan="1">&nbsp; &nbsp;6 Foreign tax identifying number (see instructions)</td>
				</tr>
				<tr>
					<td  colspan="2" style="background:#f1f4ff; border-bottom:1px solid #000000; border-right:1px solid #000000;">'.$taxForm->taxpayer.'</td>
					<td colspan="1" style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->foreign_tax.'</td>
				</tr>
				</table>
				</td></tr>
				<tr>
				<td colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
				<tr>
					<td colspan="2" style="border-right:1px solid #000000;">7 Reference number(s) (see instructions)</td>
					<td colspan="1">&nbsp; &nbsp;8 Date of birth (MM-DD-YYYY) (see instructions)</td>
				</tr>
				<tr>
					<td  colspan="2" style="background:#f1f4ff; border-right:1px solid #000000;">'.$taxForm->reference_number.'</td>
					<td colspan="1" style="background:#f1f4ff;">'.$taxForm->dob.'</td>
				</tr>
				</table>
				</td></tr>
				<tr>
				<td style="border-top:1px solid #000000; " colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
				<tr>
					<td style="border-bottom:1px solid #000000; background:#000000; color:#ffffff; font-weight:bold; text-align:center;">Part II</td>
					<td style="border-bottom:1px solid #000000;"><b>&nbsp; &nbsp;Claim of Tax Treaty Benefits (for chapter 3 purposes only) (see instructions)</b></td>
				</tr>
				</table>
				</td>
				</tr>
				<tr>
					<td>9 I certify that the beneficial owner is a resident of
					<span style="background:#f1f4ff; display:inline-block;">'.$taxForm->resident.'</span>
					within the meaning of the income tax treaty between the United States and that country. </td>
				</tr>
				<tr>
					<td colspan="3">
						<b>Special rates and conditions</b> (if applicable�see instructions): The beneficial owner is claiming the provisions of Article and paragraph <span style="background:#f1f4ff; display:inline-block;">'.$taxForm->paragraph.'</span> 
						of the treaty identified on line 9 above to claim a <span style="background:#f1f4ff; display:inline-block;">'.$taxForm->percentage.'</span> rate of withholding on (specify type of income):<td>
						<tr>
						<td colspan="3" style="background:#f1f4ff;">'.$taxForm->income.'</td>
						</tr>
						<tr>
						<td colspan="3">Explain the additional conditions in the Article and paragraph the beneficial owner meets to be eligible for the rate of withholding:</td></tr>
						<tr>
						<td colspan="3" style="background:#f1f4ff;">'.$taxForm->additional_conditions.'</td>
					
				</tr>
				<tr>
				<td style="border-top:1px solid #000000;" colspan="3">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
				<tr>
					<td style="width:50px; border-bottom:1px solid #000000; background:#000000; color:#ffffff; font-weight:bold; text-align:center;">Part III</td>
					<td style=" border-bottom:1px solid #000000;"><b>&nbsp; &nbsp;Certification</b></td>
				</tr>
				</table>
				</td></tr>
				<tr>
					<td colspan="3">
						Under penalties of perjury, I declare that I have examined the information on this form and to the best of my knowledge and belief it is true, correct, and complete. I further certify under penalties of perjury that:
						<ul>
							<li>I am the individual that is the beneficial owner (or am authorized to sign for the individual that is the beneficial owner) of all the income to which this form relates or am using this form to document myself for chapter 4 purposes,</li>
							<li>The person named on line 1 of this form is not a U.S. person,</li>
							<li>
								The income to which this form relates is:
								<ol>
									<li>not effectively connected with the conduct of a trade or business in the United States</li>
									<li>effectively connected but is not subject to tax under an applicable income tax treaty, or</li>
									<li>the partner\'s share of a partnership\'s effectively connected income,</li>
								</ol>
							</li>
							<li>The person named on line 1 of this form is a resident of the treaty country listed on line 9 of the form (if any) within the meaning of the income tax treaty between the United States and that country, and</li>
							<li>
								For broker transactions or barter exchanges, the beneficial owner is an exempt foreign person as defined in the instructions
								<p>Furthermore, I authorize this form to be provided to any withholding agent that has control, receipt, or custody of the income of which I am the beneficial owner or any withholding agent that can disburse or make payments of the income of which I am the beneficial owner. <b>I agree that I will submit a new form within 30 days if any certification made on this form becomes incorrect.</b></p>
							</li>
					</td>
				</tr>
				<tr>
					
					<td colspan="3">
						<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
							<tr>
							<td style="font-size:16px; font-weight:bold; line-height:24px;">Sign Here &#x25B6;</td>
								<td style="border-bottom:1px solid #000000">##SIGNATURE##</td>
								<td style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->updated_at.'</td>
							</tr>
							<tr>
							<td>&nbsp;</td>
								<td>Signature of beneficial owner (or individual authorized to sign for beneficial owner)</td>
								<td style="">Date (MM-DD-YYYY)</td>
							</tr>
							</table>
					</td>
				</tr>
				<tr>
				<td colspan="3">
						<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
							<tr>
							<td colspan="2" style="background:#f1f4ff; border-bottom:1px solid #000000;">'.$taxForm->print_name.'</td>
							<td colspan="1" style="background:#f1f4ff; border-bottom:1px solid #000000; width:50%;">'.$taxForm->capacity_acting.'</td>
							
							</tr>
							<tr>
								<td colspan="2" > Print name of signer </td>
								<td colspan="1" align="right" style="width:50%;">Capacity in which acting (if form is not signed by beneficial owner)</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
				<td colspan="3" style="border-top:1px solid #000000;">
						<table width="100%" border="0" cellspacing="0" cellpadding="0" align="center" >
				<tr>
					<td><b>For Paperwork Reduction Act Notice, see separate instructions.</b></td>
					<td>Cat. No. 25047Z </td>
					<td align="right">Form <strong style="font-size:15px;">W-8BEN</strong> (Rev. 7-2017)</td>
				</tr>
				</table>
				</td>
				</tr>
				</table>';
				$html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
				$mpdf->SetDisplayMode('fullpage');
				$mpdf->list_indent_first_level = 0;
				$mpdf->WriteHTML($html);
				$mpdf->Output("tax.pdf",'I');
				header('Content-type: application/pdf');
				header("Content-Disposition: attachment; filename=tax.pdf");
				exit;
	}
});
$app->POST('/api/v1/attachments', function ($request, $response, $args) {
    global $configuration;
	global $authUser;
    $args = $request->getQueryParams();
    $file = $request->getUploadedFiles();
	
    if(!empty($file)) {
        $newfile = $file['file'];
        $type = pathinfo($newfile->getClientFilename(), PATHINFO_EXTENSION);
        $fileName = str_replace('.'.$type,"",$newfile->getClientFilename()).'_'.time().'.'.$type;
        $name = md5(time());
        $class = $args['class'];
        $attachment_settings = getAttachmentSettings($class);
        $file = $_FILES['file'];
        
        $file_formats = explode(",", $attachment_settings['allowed_file_formats']);
        $file_formats = array_map('trim', $file_formats);
        $max_file_size = $attachment_settings['allowed_file_size'];
        $kilobyte = 1024;
        $megabyte = $kilobyte * 1024;
        $file["type"] = get_mime($file['tmp_name']);  
        
        $current_file_size = round($file["size"] / $megabyte, 2);
        if (in_array($file["type"], $file_formats) || empty($attachment_settings['allowed_file_formats'])) {
            if (empty($max_file_size) || (!empty($max_file_size) && $current_file_size <= $max_file_size)) {
                if ($class == "UsersMyFile") {
                    $filePath = APP_PATH.DIRECTORY_SEPARATOR.'client'.APP_PATH.DIRECTORY_SEPARATOR.'images'.APP_PATH.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'UsersMyFile'.DIRECTORY_SEPARATOR.$authUser->id.DIRECTORY_SEPARATOR;
                    if (!file_exists($filePath)) {
                        mkdir($filePath,0777,true);
                    }
                    if ($type == 'php') {
                        $type = 'txt';
                    }
                
                    if (move_uploaded_file($newfile->file, $filePath.$fileName) === true) {
                        $attachment = new Models\Attachment;
                        $info = getimagesize( $filePath.$file['name']);
                        $width = $info[0];
                        $height = $info[1];
                        $attachment->filename = $fileName;
                        $attachment->width = $width;
                        $attachment->height = $height;
                        $attachment->dir = $class .DIRECTORY_SEPARATOR . $authUser->id;
                        $attachment->foreign_id = $authUser->id;
                        $attachment->class = $class;
                        $attachment->mimetype = $info['mime'];
                        $attachment->save();
                        $userFiles = Models\Attachment::where('foreign_id', $authUser->id)->where('class', $class)->get()->toArray();
                        $response = array(
                            'data' => $userFiles,
                            'error' => array(
                                'code' => 0,
                                'message' => ''
                            )
                        );
                    } else {
                        $response = array(
                            'error' => array(
                                'code' => 1,
                                'message' => 'Attachment could not be added.',
                                'fields' => ''
                            )
                        );
                    }
                } else {
                    if (!file_exists(APP_PATH . '/media/tmp/')) {
                        mkdir(APP_PATH . '/media/tmp/',0777,true);
                    }
                    if ($type == 'php') {
                        $type = 'txt';
                    }
                    if (move_uploaded_file($newfile->file, APP_PATH . '/media/tmp/' . $name . '.' . $type) === true) {
                        $filename = $name . '.' . $type;
                        $response = array(
                            'attachment' => $filename,
                            'error' => array(
                                'code' => 0,
                                'message' => ''
                            )
                        );
                    } else {
                        $response = array(
                            'error' => array(
                                'code' => 1,
                                'message' => 'Attachment could not be added.',
                                'fields' => ''
                            )
                        );
                    }
                }
            } else {
                $response = array(
                    'error' => array(
                        'code' => 1,
                        'message' => "The uploaded file size exceeds the allowed " . $attachment_settings['allowed_file_size'] . "MB",
                        'fields' => ''
                    )
                );
            }
        } else {
            $response = array(
                'error' => array(
                    'code' => 1,
                    'message' => "File couldn't be uploaded. Allowed extensions: " . $attachment_settings['allowed_file_extensions'],
                    'fields' => ''
                )
            );
        }
    } else {
        $userFiles = Models\Attachment::where('foreign_id', $authUser->id)->where('class', 'UsersMyFile')->get()->toArray();
        $response = array(
            'data' => $userFiles,
            'error' => array(
                'code' => 0,
                'message' => ''
            )
        );
    }
    return renderWithJson($response);
});
/**
 * GET pluginsGet
 * Summary: Fetch all plugins
 * Notes: Returns all plugins from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/plugins', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $path = APP_PATH . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'plugins';
    if (!is_dir($path)) {
        $path = APP_PATH . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'plugins';
    }
    $directories = array();
    $directories = glob($path . '/*', GLOB_ONLYDIR);
    $available_plugin = array();
    $available_plugin_details = array();
    $pluginArray = array();
    $pluginArray['Bidding'] = array();
    $pluginArray['Common'] = array();
    $pluginArray['Contest'] = array();
    $pluginArray['Job'] = array();
    $pluginArray['Portfolio'] = array();
    $pluginArray['Quote'] = array();
    $biddingRelatedPlugins = array();
    $contestRelatedPlugins = array();
    $jobRelatedPlugins = array();
    $portfolioRelatedPlugins = array();
    $quoteRelatedPlugins = array();
    $plugin_name = array();
    $otherlugins = array();
    $hide_plugins = array();
    foreach ($directories as $key => $val) {
        $name = explode('/', $val);
        $sub_directories = glob($val . '/*', GLOB_ONLYDIR);
        if (!empty($sub_directories)) {
            foreach ($sub_directories as $sub_directory) {
                $json = file_get_contents($sub_directory . DIRECTORY_SEPARATOR . 'plugin.json');
                $data = json_decode($json, true);
                if (!in_array($data['name'], $hide_plugins)) {
                    if (!empty($data['dependencies'])) {
                        $pluginArray[$data['dependencies']][$data['name']] = $data;
                    } elseif (!in_array($data['name'], $pluginArray)) {
                        if (empty($pluginArray[$data['name']])) {
                            $pluginArray[] = $data;
                        }
                    }
                }
            }
        }
    }
    if (empty($pluginArray['Bidding'])) {
        unset($pluginArray['Bidding']);
    } else {
        $biddingPlugins = $pluginArray['Bidding'];
        unset($pluginArray['Bidding']);
        foreach ($biddingPlugins as $biddingPlugin) {
            if ($biddingPlugin['name'] != 'Bidding') {
                $biddingRelatedPlugins['sub_plugins'][] = $biddingPlugin;
            } else {
                $biddingRelatedPlugins['main_plugins'][] = $biddingPlugin;
            }
        }
    }
    if (empty($pluginArray['Contest'])) {
        unset($pluginArray['Contest']);
    } else {
        $contestPlugins = $pluginArray['Contest'];
        unset($pluginArray['Contest']);
        foreach ($contestPlugins as $contestPlugin) {
            if ($contestPlugin['name'] != 'Contest') {
                $contestRelatedPlugins['sub_plugins'][] = $contestPlugin;
            } else {
                $contestRelatedPlugins['main_plugins'][] = $contestPlugin;
            }
        }
    }
    if (empty($pluginArray['Job'])) {
        unset($pluginArray['Job']);
    } else {
        $jobPlugins = $pluginArray['Job'];
        unset($pluginArray['Job']);
        foreach ($jobPlugins as $jobPlugin) {
            if ($jobPlugin['name'] != 'Job') {
                $jobRelatedPlugins['sub_plugins'][] = $jobPlugin;
            } else {
                $jobRelatedPlugins['main_plugins'][] = $jobPlugin;
            }
        }
    }
    if (empty($pluginArray['Portfolio'])) {
        unset($pluginArray['Portfolio']);
    } else {
        $portfolioPlugins = $pluginArray['Portfolio'];
        unset($pluginArray['Portfolio']);
        foreach ($portfolioPlugins as $portfolioPlugin) {
            if ($portfolioPlugin['name'] != 'Portfolio') {
                $portfolioRelatedPlugins['sub_plugins'][] = $portfolioPlugin;
            } else {
                $portfolioRelatedPlugins['main_plugins'][] = $portfolioPlugin;
            }
        }
    }
    if (empty($pluginArray['Quote'])) {
        unset($pluginArray['Quote']);
    } else {
        $quotePlugins = $pluginArray['Quote'];
        unset($pluginArray['Quote']);
        foreach ($quotePlugins as $quotePlugin) {
            if ($quotePlugin['name'] != 'Quote') {
                $quoteRelatedPlugins['sub_plugins'][] = $quotePlugin;
            } else {
                $quoteRelatedPlugins['main_plugins'][] = $quotePlugin;
            }
        }
    }
    foreach ($pluginArray as $plugin) {
        $otherlugins[] = $plugin;
    }
    $enabled_plugins = explode(',', SITE_ENABLED_PLUGINS);
    foreach ($enabled_plugins as $key => $enabled_plugin) {
        $name = explode('/', $enabled_plugin);
        $plugin_name[] = end($name);
    }
    $enabled_plugin = array_map('trim', $plugin_name);
    $result['data']['bidding_plugin'] = $biddingRelatedPlugins;
    $result['data']['contest_plugin'] = $contestRelatedPlugins;
    $result['data']['job_plugin'] = $jobRelatedPlugins;
    $result['data']['portfolio_plugin'] = $portfolioRelatedPlugins;
    $result['data']['quote_plugin'] = $quoteRelatedPlugins;
    $result['data']['other_plugin'] = $otherlugins;
    $result['data']['enabled_plugin'] = $enabled_plugin;
    return renderWithJson($result);
})->add(new ACL('canListPlugins'));
/**
 * PUT pluginPut
 * Summary: Update plugins ny plugin name
 * Notes: Update plugins ny plugin name
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/plugins', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $result = array();
    $site_enable_plugin = Models\Setting::where('name', 'SITE_ENABLED_PLUGINS')->first();
    $enabled_plugins = explode(',', SITE_ENABLED_PLUGINS);
    if ($args['is_enabled'] === 1) {
        if (!in_array($args['plugin'], $enabled_plugins)) {
            $enabled_plugins[] = $args['plugin'];
        }
        $pluginStr = implode(',', $enabled_plugins);
        Models\Setting::where('name', 'SITE_ENABLED_PLUGINS')->update(array(
            'value' => $pluginStr
        ));
        return renderWithJson($result, 'Plugin enabled', '', 0);
    } elseif ($args['is_enabled'] === 0) {
        $key = array_search($args['plugin'], $enabled_plugins);
        if ($key !== false) {
            unset($enabled_plugins[$key]);
        }
        $main_pugins = array('Bidding/Bidding', 'Contest/Contest', 'Job/Job', 'Portfolio/Portfolio', 'Quote/Quote');
        if (in_array($args['plugin'], $main_pugins)) {
            $main_folder = explode("/",$args['plugin']);
            $path = APP_PATH . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'plugins'. DIRECTORY_SEPARATOR . $main_folder[0];
            if (!is_dir($path)) {
                $path = APP_PATH . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'plugins'. DIRECTORY_SEPARATOR . $main_folder[0];
            }
            $pluginArray = $hide_plugins = array();
            $sub_plugins = '';
            $directories = glob($path . '/*', GLOB_ONLYDIR);    
            foreach ($directories as $sub_directory) {
                $json = file_get_contents($sub_directory . DIRECTORY_SEPARATOR . 'plugin.json');
                $data = json_decode($json, true);
                $sub_plugins[] = $data['plugin_name'];
            }   
            $enabled_plugins = array_diff($enabled_plugins, $sub_plugins); 
        }
        $pluginStr = implode(',', $enabled_plugins);
        Models\Setting::where('name', 'SITE_ENABLED_PLUGINS')->update(array(
            'value' => $pluginStr
        ));
        $scripts_path = APP_PATH . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'scripts';
        if (!is_dir($scripts_path)) {
            $scripts_path = APP_PATH . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'scripts';
        }
        $list = glob($scripts_path . '/plugins*.js');
        if ($list) {
            unlink($list[0]);
        }
        return renderWithJson($result, 'Plugin disabled', '', 0);
    } else {
        return renderWithJson($result, 'Invalid request.', '', 1);
    }
})->add(new ACL('canUpdatePlugin'));
$app->GET('/api/v1/admin-config', function ($request, $response, $args) {
    $plugins = explode(',', SITE_ENABLED_PLUGINS);
    $compiledMenus = $compiledTables = $mainJson = '';
    $file = __DIR__ . '/admin-config.php';
    $list_mode = true;
    $create_mode = true;
    $edit_mode = true;
    $delete_mode = true;
    $show_mode = true;
    $resultSet = array();
    if (file_exists($file)) {
        require_once $file;
        if (!empty($menus)) {
            $resultSet['menus'] = $menus;
        }
        if (!empty($dashboard)) {
            if (!empty($resultSet['dashboard'])) {
                $resultSet['dashboard'] = array_merge($resultSet['dashboard'], $dashboard);
            } else {
                $resultSet['dashboard'] = $dashboard;
            }
        }
        if (!empty($tables)) {
            $resultSet['tables'] = $tables;
            $tableName = current(array_keys($resultSet['tables']));
            if ($list_mode === false) {
                unset($resultSet['tables'][$tableName]['listview']);
            } else {
                if ($create_mode === false) {
                    unset($resultSet['tables'][$tableName]['listview']['actions'][2]);
                }
                if ($edit_mode === false) {
                    unset($resultSet['tables'][$tableName]['listview']['listActions'][0]);
                }
                if ($show_mode === false) {
                    unset($resultSet['tables'][$tableName]['listview']['actions'][1]);
                }
                if ($delete_mode === false) {
                    unset($resultSet['tables'][$tableName]['listview']['actions'][2]);
                }
            }
            if ($create_mode === false) {
                unset($resultSet['tables'][$tableName]['creationview']);
            }
            if ($edit_mode === false) {
                unset($resultSet['tables'][$tableName]['editionview']);
            }
            if ($delete_mode === false) {
                unset($resultSet['tables'][$tableName]['showview']);
            }
        }
    }
    if (!empty($plugins)) {
        foreach ($plugins as $plugin) {
            $file = __DIR__ . '/../plugins/' . $plugin . '/admin-config.php';
            if (file_exists($file)) {
                require_once $file;
                if (!empty($resultSet['menus'])) {
                    foreach ($menus as $key => $menu) {
                        if (isset($resultSet['menus'][$key])) {
                            $resultSet['menus'][$key]['child_sub_menu'] = array_merge($resultSet['menus'][$key]['child_sub_menu'], $menu['child_sub_menu']);
                        } else {
                            $resultSet['menus'][$key] = $menu;
                        }
                    }
                } elseif (!empty($menus)) {
                    $resultSet['menus'] = $menus;
                }
                if (!empty($dashboard)) {
                    if (!empty($resultSet['dashboard'])) {
                        $resultSet['dashboard'] = array_merge($resultSet['dashboard'], $dashboard);
                    } else {
                        $resultSet['dashboard'] = $dashboard;
                    }
                }
                if (!empty($tables)) {
                    $tableName = current(array_keys($tables));
                    if ($list_mode === false) {
                        unset($tables[$tableName]['listview']);
                    } else {
                        if ($create_mode === false) {
                            unset($tables[$tableName]['listview']['actions'][2]);
                        }
                        if ($edit_mode === false) {
                            unset($tables[$tableName]['listview']['listActions'][0]);
                        }
                        if ($show_mode === false) {
                            unset($tables[$tableName]['listview']['actions'][1]);
                        }
                        if ($delete_mode === false) {
                            unset($tables[$tableName]['listview']['actions'][2]);
                        }
                    }
                    if ($create_mode === false) {
                        unset($tables['tables'][$tableName]['creationview']);
                    }
                    if ($edit_mode === false) {
                        unset($tables[$tableName]['editionview']);
                    }
                    if ($delete_mode === false) {
                        unset($tables[$tableName]['showview']);
                    }
                    if (!empty($resultSet['tables'])) {
                        $resultSet['tables'] = array_merge($resultSet['tables'], $tables);
                    } else {
                        $resultSet['tables'] = $tables;
                    }
                }
            }
        }
        usort($resultSet['menus'], function ($a, $b) {
            return $a['order'] - $b['order'];
        });
        foreach ($resultSet['menus'] as $key => $value) {
            $resultSet['menus'][$key]['child_sub_menu'] = menu_sub_array_sorting($resultSet['menus'][$key]['child_sub_menu'], 'suborder', SORT_ASC);
        }
        foreach ($resultSet['tables'] as $key => $table) {
            if($key == 'user_cash_withdrawals') {
                foreach ($table as $view_key => $view) {
                    $fields = menu_sub_array_sorting($resultSet['tables'][$key][$view_key]['fields'], 'suborder', SORT_ASC); 
                    if(count($fields) > 0) {
                        foreach ($fields as $field) {
                            $field_list[] = $field;
                        }
                        $resultSet['tables'][$key][$view_key]['fields'] = $field_list;
                        $field_list = array();
                    }
                }
            }
        }
    }
    echo json_encode($resultSet);
    exit;
});
/**
 * DELETE certificationsCertificationIdDelete
 * Summary: Delete certification
 * Notes: Deletes a single certification based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/certifications/{certificationId}', function ($request, $response, $args) {
    $certification = Models\Certification::find($request->getAttribute('certificationId'));
    try {
        $certification->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'Certification could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteCertification'));
/**
 * GET certificationsCertificationIdGet
 * Summary: Fetch certification
 * Notes: Returns a certification based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/certifications/{certificationId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'user'
    );
    $certification = Models\Certification::with($enabledIncludes)->find($request->getAttribute('certificationId'));
    if (!empty($certification)) {
        $result['data'] = $certification;
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * PUT certificationsCertificationIdPut
 * Summary: Update certification by its id
 * Notes: Update certification by its id
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/certifications/{certificationId}', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $certification = Models\Certification::find($request->getAttribute('certificationId'));
    $certification->fill($args);
    $result = array();
    try {
        $validationErrorFields = $certification->validate($args);
        if (empty($validationErrorFields)) {
            $certification->user_id = $authUser->id;
            $certification->save();
            $result = $certification->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Certification could not be updated. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Certification could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canUpdateCertification'));
/**
 * GET certificationsGet
 * Summary: Fetch all certifications
 * Notes: Returns all certifications from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/certifications', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $enabledIncludes = array(
            'user'
        );
        $certifications = Models\Certification::with($enabledIncludes)->Filter($queryParams)->paginate(PAGE_LIMIT)->toArray();
        $data = $certifications['data'];
        unset($certifications['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $certifications
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST certificationsPost
 * Summary: Creates a new certification
 * Notes: Creates a new certification
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/certifications', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $certification = new Models\Certification($args);
    $result = array();
    try {
        $validationErrorFields = $certification->validate($args);
        if (empty($validationErrorFields)) {
            $certification->user_id = $authUser->id;
            $certification->save();
            $result = $certification->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Certification could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Certification could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreateCertification'));
/**
 * DELETE educationsEducationIdDelete
 * Summary: Delete education
 * Notes: Deletes a single education based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/educations/{educationId}', function ($request, $response, $args) {
    $education = Models\Education::find($request->getAttribute('educationId'));
    try {
        $education->delete();
        $result = array(
            'status' => 'success',
        );
        return renderWithJson($result);
    } catch (Exception $e) {
        return renderWithJson($result, 'Education could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteEducation'));
/**
 * GET educationsEducationIdGet
 * Summary: Fetch education
 * Notes: Returns a education based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/educations/{educationId}', function ($request, $response, $args) {
    $result = array();
    $enabledIncludes = array(
        'user',
        'country'
    );
    $education = Models\Education::with($enabledIncludes)->find($request->getAttribute('educationId'));
    if (!empty($education)) {
        $result['data'] = $education;
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
/**
 * PUT educationsEducationIdPut
 * Summary: Update education by its id
 * Notes: Update education by its id
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/educations/{educationId}', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $education = Models\Education::find($request->getAttribute('educationId'));
    $education->fill($args);
    $result = array();
    try {
        $validationErrorFields = $education->validate($args);
        if (empty($validationErrorFields)) {
            $education->user_id = $authUser->id;
            $education->save();
            $result = $education->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Education could not be updated. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Education could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canUpdateEducation'));
/**
 * GET educationsGet
 * Summary: Fetch all educations
 * Notes: Returns all educations from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/educations', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'user',
            'country'
        );
        $educations = Models\Education::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $educations['data'];
        unset($educations['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $educations
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
/**
 * POST educationsPost
 * Summary: Creates a new education
 * Notes: Creates a new education
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/educations', function ($request, $response, $args) {
    global $authUser;
    $args = $request->getParsedBody();
    $education = new Models\Education($args);
    $result = array();
    try {
        $validationErrorFields = $education->validate($args);
        if (empty($validationErrorFields)) {
            $education->user_id = $authUser->id;
            $education->save();
            $result = $education->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Education could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Education could not be added. Please, try again.', '', 1);
    }
})->add(new ACL('canCreateEducation'));
/**
 * GET ipsGet
 * Summary: Fetch all ips
 * Notes: Returns all ips from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/ips', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $enabledIncludes = array(
            'country',
            'state',
            'city',
            'timezone'
        );
        $ips = Models\Ip::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $ips['data'];
        unset($ips['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $ips
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListIp'));
/**
 * DELETE IpsIdDelete
 * Summary: Delete ip
 * Notes: Deletes a single ip based on the ID supplied
 * Output-Formats: [application/json]
 */
$app->DELETE('/api/v1/ips/{ipId}', function ($request, $response, $args) {
    global $authUser;
    $ip = Models\Ip::find($request->getAttribute('ipId'));
    $result = array();
    try {
        if (!empty($ip)) {
            $ip->delete();
            $result = array(
                'status' => 'success',
            );
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Ip could not be deleted. Please, try again.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Ip could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canDeleteIp'));
/**
 * GET ipIdGet
 * Summary: Fetch ip
 * Notes: Returns a ip based on a single ID
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/ips/{ipId}', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $enabledIncludes = array(
        'country',
        'state',
        'city',
        'timezone'
    );
    $ip = Models\Ip::with($enabledIncludes)->find($request->getAttribute('ipId'));
    if (!empty($ip)) {
        $result['data'] = $ip;
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canViewIp'));
/**
 * GET translations
 * Summary: Filter  translations
 * Notes: Filter translations.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/translations', function ($request, $response, $args) use ($app)
{
    $result = array();
    try {
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['lang_code'])) {
            $lang_name = $queryParams['lang_code'];
        } else {
            $lang_name = 'en';
        }
        if (!empty($queryParams['filter']) && $queryParams['filter'] == 'filelist') {
            $dir = APP_PATH . '/client/scripts/l10n/*.json';
            $whitelist = array(
                '127.0.0.1',
                '::1'
            );
            if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
                $dir = APP_PATH . '/client/scripts/l10n/*.json';
            }
            $data = array();                        
            foreach(glob($dir) as $file) 
            {
                 $file = basename($file);
                 $filename = basename($file, ".json");
                 $data[$filename]=$file;
            }
            return renderWithJson($result);
        }         
        $lang_file_path = APP_PATH . '/client/scripts/l10n/' . $lang_name . '.json';
        if (file_exists($lang_file_path)) {
            $content = file_get_contents(APP_PATH . '/client/scripts/l10n/' . $lang_name . '.json');
            $app = json_decode($content, true);
            $data = array();
            foreach($app as $Key => $value){
                $data[] = array('label' => $Key, 'lang_text' => $value);
            }
            $result['data'] = $data;
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No translation found. Please create new translation', '', 1);
        }
    }
    catch(Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListTranslations'));
/**
 * GET translations
 * Summary: Filter  translations
 * Notes: Filter translations.
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/translations', function ($request, $response, $args) use ($app)
{
    $result = array();
    $args = $request->getParsedBody();
    if (!empty($args['lang_code'])) {
		$lang_file_path = APP_PATH .DIRECTORY_SEPARATOR. 'client'.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'l10n'.DIRECTORY_SEPARATOR.'en.json';
        $content = file_get_contents($lang_file_path);
		$app = json_decode($content, true);
        $mediadir = APP_PATH . '/client/scripts/l10n/';
        $whitelist = array(
            '127.0.0.1',
            '::1'
        );
        if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
            $mediadir = APP_PATH . 'client/scripts/l10n/';
        }
        $file_path = $mediadir . $args['lang_code'] . '.json';
        $fh = fopen($file_path, 'w');
        fwrite($fh, json_encode($app, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fclose($fh);
        $result['data'] = $app;
        return renderWithJson($app);
    } else {
        return renderWithJson($result, $message = 'Please provide language code', $fields = '', $isError = 1);
    }
})->add(new ACL('canCreateTranslation'));
/**
 * GET translations
 * Summary: Filter  translations
 * Notes: Filter translations.
 * Output-Formats: [application/json]
 */
$app->PUT('/api/v1/translations/{langCode}', function ($request, $response, $args) use ($app)
{
    $result = array();
    $args = $request->getParsedBody();
    $langCode = $request->getAttribute('langCode');
    if (!empty($langCode)) {
        $lang_file_path = APP_PATH . '/client/scripts/l10n/' . $langCode . '.json';
        if (file_exists($lang_file_path)) {
            $content = file_get_contents($lang_file_path);
            $app = json_decode($content, true);
            if (!empty($args['keyword'])) {
                foreach ($args['keyword'] as $arg) {
                   // foreach ($arg as $key => $value) {
                        $app[$arg['label']] = $arg['lang_text'];
                   // }
                }
            }
            $fh = fopen($lang_file_path, 'w');
            fwrite($fh, json_encode($app, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fclose($fh);
            $result['data'] = $app;
            return renderWithJson($app);
        } else {
            $lang_file_path = APP_PATH . '/client/scripts/l10n/en.json';
            $content = file_get_contents($lang_file_path);
            $app = json_decode($content, true);
            if (!empty($args['keyword'])) {
				foreach ($args['keyword'] as $arg) {
                    foreach ($arg as $key => $value) {
                        $app[$key] = $value;
                    }
                }
            }
            $mediadir = APP_PATH . '/client/scripts/l10n/';
            $whitelist = array(
                '127.0.0.1',
                '::1'
            );
            if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
                $mediadir = APP_PATH . '/client/scripts/l10n/';
            }
            $file_path = $mediadir . $langCode . '.json';
            $fh = fopen($file_path, 'w');
            fwrite($fh, json_encode($app, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fclose($fh);
            $result['data'] = $app;
            return renderWithJson($app);
        }
    } else {
        return renderWithJson($result, $message = 'No translaton found', $fields = '', $isError = 1);
    }
})->add(new ACL('canUpdateTranslation'));
$app->GET('/api/v1/getApplicantIdToken', function ($request, $response, $args) use ($app)
{
	global $authUser;
	global $_server_domain_url;
	$result = array();
	$onfidoToken = (ONFIDO_MODE) ? ONFIDO_LIVE : ONFIDO_SANDBOX;
	$userDetails = Models\User::find($authUser['id']);
	try {
		$result['status'] = $userDetails->onfido_status;
		if ($userDetails->onfido_status == "") {
			$headers = array();
			$headers[] = 'Authorization: Token token='.$onfidoToken;
			if ($userDetails->onfido_applicant_id != "") {
				$result['appid'] = $userDetails->onfido_applicant_id;
			} else {
				$post_fields = http_build_query(array("first_name" => $userDetails->first_name, "last_name"=> $userDetails->last_name));
				$url = "https://api.onfido.com/v2/applicants";
				$response = onfidoExecute($url, $post_fields, $headers);
				$appid = json_decode($response, true);
				$result['appid'] = $appid['id'];
				$userDetails->onfido_applicant_id = $appid['id'];
				$userDetails->update();
			}
			$post_fields = http_build_query(array('applicant_id' => $userDetails->onfido_applicant_id, "referrer"=> $_server_domain_url."/users/cash_withdrawals"));
			$url = "https://api.onfido.com/v2/sdk_token";
			$tokenJson = onfidoExecute($url, $post_fields, $headers);
			$token = array();
			$token = json_decode($tokenJson, true);
			$result['token'] = $token['token'];			
			if (isset($result['appid']) && isset($result['token']) && $result['appid'] != "" && $result['token'] != "") {
				return renderWithJson($result);
			} else {
				return renderWithJson($result, 'Onfido could not be invoked. Please, try again.', '', 1);
			}
		} else {
			return renderWithJson($result);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Onfido could not be invoked. Please, try again.', '', 1);
	}
});
$app->POST('/api/v1/adminStats', function ($request, $response, $args) use ($app)
{
	$getParsedBody = $request->getParsedBody();
	$year = $month = false;
	if (isset($getParsedBody['from_date']) && $getParsedBody['from_date'] != "" && isset($getParsedBody['to_date']) && $getParsedBody['to_date'] != "") {
		$from_date = explode("T",$getParsedBody['from_date']);
		$getParsedBody['from_date'] = date('Y-m-d', strtotime($from_date[0].' +1 day')).' 00:00:00';
		$to_date = explode("T",$getParsedBody['to_date']);
		$getParsedBody['to_date'] = date('Y-m-d', strtotime($to_date[0].' +1 day')).' 23:59:59';
	} else {
		$getParsedBody['from_date'] = date("Y-m-d", strtotime("-6 months")).' 00:00:00';
		$getParsedBody['to_date'] = date("Y-m-d").' 23:59:59';
	}
	$start = strtotime($getParsedBody['from_date']);
	$end = strtotime($getParsedBody['to_date']);
	$days_between = ceil(abs($end - $start) / 86400);
	if($days_between >= 365) {
		$year = true;
	} else if($days_between >= 30) {
		$month = true;
	}
	$theme = '{"colors":["#2b908f","#90ee7e","#f45b5b","#7798BF","#aaeeee","#ff0066","#eeaaee","#55BF3B","#DF5353","#7798BF","#aaeeee"],"chart":{"backgroundColor":{"linearGradient":{"x1":0,"y1":0,"x2":1,"y2":1},"stops":[[0,"#2a2a2b"],[1,"#3e3e40"]]},"style":{"fontFamily":"\'Unica One\', sans-serif"},"plotBorderColor":"#606063"},"title":{"style":{"color":"#E0E0E3","textTransform":"uppercase","fontSize":"20px"}},"subtitle":{"style":{"color":"#E0E0E3","textTransform":"uppercase"}},"xAxis":{"gridLineColor":"#707073","labels":{"style":{"color":"#E0E0E3"}},"lineColor":"#707073","minorGridLineColor":"#505053","tickColor":"#707073","title":{"style":{"color":"#A0A0A3"}}},"yAxis":{"gridLineColor":"#707073","labels":{"style":{"color":"#E0E0E3"}},"lineColor":"#707073","minorGridLineColor":"#505053","tickColor":"#707073","tickWidth":1,"title":{"style":{"color":"#A0A0A3"}}},"tooltip":{"backgroundColor":"rgba(0, 0, 0, 0.85)","style":{"color":"#F0F0F0"}},"plotOptions":{"series":{"dataLabels":{"color":"#B0B0B3"},"marker":{"lineColor":"#333"}},"boxplot":{"fillColor":"#505053"},"candlestick":{"lineColor":"white"},"errorbar":{"color":"white"}},"legend":{"itemStyle":{"color":"#E0E0E3"},"itemHoverStyle":{"color":"#FFF"},"itemHiddenStyle":{"color":"#606063"}},"credits":{"style":{"color":"#666"}},"labels":{"style":{"color":"#707073"}},"drilldown":{"activeAxisLabelStyle":{"color":"#F0F0F3"},"activeDataLabelStyle":{"color":"#F0F0F3"}},"navigation":{"buttonOptions":{"symbolStroke":"#DDDDDD","theme":{"fill":"#505053"}}},"rangeSelector":{"buttonTheme":{"fill":"#505053","stroke":"#000000","style":{"color":"#CCC"},"states":{"hover":{"fill":"#707073","stroke":"#000000","style":{"color":"white"}},"select":{"fill":"#000003","stroke":"#000000","style":{"color":"white"}}}},"inputBoxBorderColor":"#505053","inputStyle":{"backgroundColor":"#333","color":"silver"},"labelStyle":{"color":"silver"}},"navigator":{"handles":{"backgroundColor":"#666","borderColor":"#AAA"},"outlineColor":"#CCC","maskFill":"rgba(255,255,255,0.1)","series":{"color":"#7798BF","lineColor":"#A6C7ED"},"xAxis":{"gridLineColor":"#505053"}},"scrollbar":{"barBackgroundColor":"#808083","barBorderColor":"#808083","buttonArrowColor":"#CCC","buttonBackgroundColor":"#606063","buttonBorderColor":"#606063","rifleColor":"#FFF","trackBackgroundColor":"#404043","trackBorderColor":"#404043"},"legendBackgroundColor":"rgba(0, 0, 0, 0.5)","background2":"#505053","dataLabelsColor":"#B0B0B3","textColor":"#C0C0C0","contrastTextColor":"#F0F0F3","maskColor":"rgba(255,255,255,0.3)"}';
	$highChart = array();
	$highChart["highChart"]["theme"] = json_decode($theme,true);
	$monthYear = "";
	if ($year === true) { 
		$selectSql = "SELECT date_part('year', closed_date) as xaxis";
	} else if ($month === true) {
		$selectSql = "SELECT TO_CHAR(closed_date,'MON') xaxis,date_part('year', closed_date) as yearstat";
		$monthYear = "yearstat,";
	} else {
		$selectSql = "SELECT TO_CHAR(closed_date, 'DD-MM-YYYY') as xaxis";
	}
	$sql = $selectSql.",COUNT(*) as total
				FROM project_bids
				where closed_date >= '".$getParsedBody['from_date']."'
				and closed_date <= '".$getParsedBody['to_date']."'
				and closed_date IS NOT NULL
				GROUP BY ".$monthYear."xaxis";
	$barChartResultSet = Capsule::select($sql);
	if(!empty($barChartResultSet)) {
		$barChartResultSet = json_decode(json_encode($barChartResultSet), true);
		$tempArray = array();
		if ($year === true) {
			foreach ($barChartResultSet as $key => $value) {
				$temp = array();
				$temp["xaxis"] = $value["xaxis"];
				$temp["total"] = (int)$value["total"];
				$tempArray[$value['xaxis']] = $temp;
			}
		} else if ($month === true) {
			foreach ($barChartResultSet as $key => $value) {
				$temp = array();
				$temp["xaxis"] = date('M', strtotime($value["xaxis"]))."-".$value['yearstat'];
				$temp["total"] = (int)$value["total"];
				$tempArray[$value['yearstat'].date('m', strtotime($value['xaxis']))] = $temp;
			}
		} else {
			foreach ($barChartResultSet as $key => $value) {
				$temp = array();
				$temp["xaxis"] = date('d-m-y', strtotime($value["xaxis"])).' ('.date('D', strtotime($value["xaxis"])).')';
				$temp["total"] = (int)$value["total"];
				$tempArray[date('d', strtotime($value['xaxis']))] = $temp;
			}
		}
		ksort($tempArray);
		$barChartResultSet = array_values($tempArray);
		$barChart["chart"]["type"] = "column";
		$barChart["legend"]["enabled"] = false;
		$barChart["chart"]["options3d"]["enabled"] = true;
		$barChart["chart"]["options3d"]["alpha"] = 10;
		$barChart["chart"]["options3d"]["beta"] = 25;
		$barChart["chart"]["options3d"]["depth"] = 70;
		$barChart["title"]["text"] = "Jobs Hired";
		$barChart["plotOptions"]["column"]["depth"] = 25;
		$barChart["xAxis"]["categories"] = array_column($barChartResultSet, 'xaxis');
		$barChart["xAxis"]["labels"]["skew3d"] = true;
		$barChart["xAxis"]["labels"]["style"]["fontSize"] = "16px";
		$data = array();
		$data["name"] = "FreeLancer";
		$data["data"] = array_column($barChartResultSet, 'total');
		$barChart["series"][] = $data;
		$highChart["highChart"]["barChart"] = $barChart;			
	}
	if ($year === true) { 
		$selectSql = "SELECT date_part('year', created_at) as xaxis";
	} else if ($month === true) {
		$selectSql = "SELECT TO_CHAR(created_at,'MON') xaxis,date_part('year', created_at) as yearstat";
		$monthYear = "yearstat,";
	} else {
		$selectSql = "SELECT TO_CHAR(created_at, 'DD-MM-YYYY') as xaxis";
	}
	$sql = $selectSql.",COUNT(*) as total
				FROM users
				where created_at >= '".$getParsedBody['from_date']."'
				and created_at <= '".$getParsedBody['to_date']."'
				and role_id in ('4', '2')
				GROUP BY ".$monthYear."xaxis";
	$lineChartResultSet = Capsule::select($sql);
	if(!empty($lineChartResultSet)) {
		$lineChartResultSet = json_decode(json_encode($lineChartResultSet), true);
		$tempArray = array();
		if ($year === true) {
			foreach ($lineChartResultSet as $key => $value) {
				$temp = array();
				$temp["xaxis"] = $value["xaxis"];
				$temp["total"] = (int)$value["total"];
				$tempArray[$value['xaxis']] = $temp;
			}
		} else if ($month === true) {
			foreach ($lineChartResultSet as $key => $value) {
				$temp = array();
				$temp["xaxis"] = date('M', strtotime($value["xaxis"]))."-".$value['yearstat'];
				$temp["total"] = (int)$value["total"];
				$tempArray[$value['yearstat'].date('m', strtotime($value['xaxis']))] = $temp;
			}		
		} else {
			foreach ($lineChartResultSet as $key => $value) {
				$temp = array();
				$temp["xaxis"] = date('d-m-y', strtotime($value["xaxis"])).' ('.date('D', strtotime($value["xaxis"])).')';
				$temp["total"] = (int)$value["total"];
				$tempArray[date('d', strtotime($value['xaxis']))] = $temp;
			}
		}
		ksort($tempArray);
		$lineChartResultSet = array_values($tempArray);
		$lineChart = array();
		$lineChart["title"]["text"] = "Freelancers In The System";
		$lineChart["legend"]["enabled"] = false;
		$lineChart["legend"]["layout"] = "vertical";
		$lineChart["legend"]["align"] = "right";
		$lineChart["legend"]["verticalAlign"] = "middle";
		$lineChart["plotOptions"]["series"]["label"]["connectorAllowed"] = 25;
		$lineChart["xAxis"]["categories"] = array_column($lineChartResultSet, 'xaxis');
		$lineChart["xAxis"]["labels"]["skew3d"] = true;
		$lineChart["xAxis"]["labels"]["style"]["fontSize"] = "16px";
		$rules = array();
		$rules["condition"]["maxWidth"] = 500;
		$rules["condition"]["chartOptions"]["legend"]["layout"] = "horizontal";
		$rules["condition"]["chartOptions"]["legend"]["align"] = "center";
		$rules["condition"]["chartOptions"]["legend"]["verticalAlign"] = "bottom";		
		$lineChart["responsive"]["rules"][] = $rules;
		$data = array();
		$data["name"] = "";
		$data["data"] = array_column($lineChartResultSet, 'total');
		$lineChart["series"][] = $data;
		$highChart["highChart"]["lineChart"] = $lineChart;			
	}
	$sql = "SELECT skills.name as name,COUNT(skills_projects.skill_id) AS y FROM skills_projects
			inner join skills on skills.id = skills_projects.skill_id
			where skills_projects.created_at >= '".$getParsedBody['from_date']."'
			and skills_projects.created_at <= '".$getParsedBody['to_date']."'
			GROUP BY skills_projects.skill_id,name
			HAVING COUNT(skills_projects.skill_id) > 1
			limit 10";
	$pieChartResultSet = Capsule::select($sql);
	if(!empty($pieChartResultSet)) {
		$pieChartResultSet = json_decode(json_encode($pieChartResultSet), true);
		$pieChart = array();
		$pieChart["chart"]["type"] = "pie";
		$pieChart["chart"]["options3d"]["enabled"] = true;
		$pieChart["chart"]["options3d"]["alpha"] = 45;
		$pieChart["chart"]["options3d"]["beta"] = 0;
		$pieChart["title"]["text"] = "Top Skills Hired";
		$pieChart["tooltip"]["pointFormat"] = "{series.name}: <b>{point.percentage:.1f}%</b>";
		$pieChart["plotOptions"]["pie"]["allowPointSelect"] = true;
		$pieChart["plotOptions"]["pie"]["cursor"] = "pointer";
		$pieChart["plotOptions"]["pie"]["depth"] = 35;
		$pieChart["plotOptions"]["pie"]["dataLabels"]["enabled"] = true;
		$pieChart["plotOptions"]["pie"]["dataLabels"]["format"] = "{point.name}";
		$data = array();
		$data["type"] = "pie";
		$data["name"] = "Skills Hired";
		$tempArray = array();
		foreach ($pieChartResultSet as $key => $value) {
			$temp = array();
			$temp["name"] = $value["name"];
			$temp["y"] = (int)$value["y"];
			$tempArray[] = $temp;
		}
		$data["data"] = $tempArray;
		$pieChart["series"][] = $data;
		$highChart["highChart"]["pieChart"] = $pieChart;
	}
	$Sql = "SELECT skills.name, COUNT(*) as value
			FROM keywords
			inner join skills on keywords.skill_id = skills.id
			where keywords.created_at >= '".$getParsedBody['from_date']."'
			and keywords.created_at <= '".$getParsedBody['to_date']."'
			GROUP BY keywords.skill_id,skills.name
			limit 10";
	$bubbleChartResultSet = Capsule::select($Sql);
	if(!empty($bubbleChartResultSet)) {
		$bubbleChartResultSet = json_decode(json_encode($bubbleChartResultSet), true);
		$bubbleChart["chart"]["type"] = "packedbubble";
		$bubbleChart["title"]["text"] = "Top Skills Searched";
		$bubbleChart["tooltip"]["useHTML"] = true;
		$bubbleChart["tooltip"]["pointFormat"] = "<b>{point.name}:</b> {point.y}";
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["enabled"] = true;
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["format"] = "{point.name}";
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["filter"]["property"] = "y";
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["filter"]["operator"] = ">";
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["filter"]["value"] = 250;
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["style"]["color"] = "black";
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["style"]["textOutline"] = "none";
		$bubbleChart["plotOptions"]["packedbubble"]["dataLabels"]["style"]["fontWeight"] = "none";
		$bubbleChart["plotOptions"]["packedbubble"]["minPointSize"] = 5;
		$rules = array();
		$rules["condition"]["maxWidth"] = 310;
		$rules["chartOptions"]["legend"]["align"] = "align";
		$rules["chartOptions"]["legend"]["verticalAlign"] = "middle";
		$rules["chartOptions"]["legend"]["layout"] = "align";		
		$bubbleChart["responsive"]["rules"][] = $rules;
		$data = array();
		foreach ($bubbleChartResultSet as $resultSet) {
			$subArr = array();
			$subArr["name"] = $resultSet["name"];
			$subData = array();
			$subData["name"] = $resultSet["value"];
			$subData["value"] = (int)$resultSet["value"];
			$subArr["data"][] = $subData;
			$data[] = $subArr;
		}
		$bubbleChart["series"] = $data;
		$highChart["highChart"]["bubbleChart"] = $bubbleChart;			
	}
	$highChart["highChart"]["from_date"] = date("Y-m-d",strtotime($getParsedBody['from_date']));
	$highChart["highChart"]["to_date"] = date("Y-m-d",strtotime($getParsedBody['to_date']));
	$result = array(
                "data" => $highChart,
				);
    return renderWithJson($result);
});
$app->GET('/api/v1/checkApplicantId/{applicantId}', function ($request, $response, $args) use ($app)
{
	$onfidoToken = (ONFIDO_MODE) ? ONFIDO_LIVE : ONFIDO_SANDBOX;
	global $authUser;
	$userDetails = Models\User::find($authUser['id']);
	try {
		$applicantId = $request->getAttribute('applicantId');
		$headers = $result = array();
		$headers[] = 'Authorization: Token token='.$onfidoToken;
		$post_fields = http_build_query(array('async' => true,"type"=>"express","reports[][name]"=>"document"));
		$url = "https://api.onfido.com/v2/applicants/".$applicantId."/checks";
		$applicantStatus = onfidoExecute($url, $post_fields, $headers);
		if($applicantStatus != "") {
			$applicantStatusDetails = json_decode($applicantStatus, true);
			$userDetails->onfido_check_id = $applicantStatusDetails['id'];
			$userDetails->onfido_is_sanbox = $applicantStatusDetails['sandbox'];
			$userDetails->onfido_status = $applicantStatusDetails['status'];
			$userDetails->update();
			$result = array(
                'status' => $applicantStatusDetails['status'],
            );
            return renderWithJson($result);
		} else {
			return renderWithJson($result, 'Onfido could not be invoked. Please, try again.', '', 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Onfido could not be invoked. Please, try again.', '', 1);
	}
});
$app->GET('/api/v1/cron', function ($request, $response, $args) use ($app)
{
	$countKeyWord = Models\Keyword::select('id')->count();
	if ($countKeyWord != "" && $countKeyWord > 5000) {
		$sql = "DELETE FROM keywords WHERE id IN (SELECT id FROM keywords ORDER BY id asc LIMIT ".($countKeyWord-5000).")";
		Capsule::select($sql);
	}
});
$app->GET('/api/v1/companies', function ($request, $response, $args) {
    global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$companies = Models\Company::with('user', 'attachment')->Filter($queryParams)->paginate($count)->toArray();
		$results = array(
			'data' => $companies
		);
		return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/company/{id}', function ($request, $response, $args) {
    global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $company = Models\Company::find($request->getAttribute('id'));
        if (!empty($company)) {
            $result['data'] = $company;
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canUpdateUser'));
$app->POST('/api/v1/company', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
    $result = array();
    $args = $request->getParsedBody();
    $company = new Models\Company($args);
    try {
        $validationErrorFields = $company->validate($args);
        if (empty($validationErrorFields)) {
            $company->is_active = 1;
            $company->user_id = $authUser->id;
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin && !empty($args['user_id'])) {
                $company->user_id = $args['user_id'];
            }
            if ($company->save()) {
				if ($company->id) {
					if (!empty($args['image'])) {
						saveImage('Company', $args['image'], $company->id);
					}
					$result['data'] = $company->toArray();
					return renderWithJson($result);
				}
            } else {
				return renderWithJson($result, 'Company could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Company could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Company could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canUpdateUser'));
$app->PUT('/api/v1/company/{id}', function ($request, $response, $args) {
    global $authUser;
	
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
	$args = $request->getParsedBody();
	$company = Models\Company::find($request->getAttribute('id'));
	$company->fill($args);
	$result = array();
	try {
		$validationErrorFields = $company->validate($args);
		if (empty($validationErrorFields)) {
			$company->save();
			if (!empty($args['image']) && $company->id) {
				saveImage('Company', $args['image'], $request->getAttribute('id'));
			}
			$result = $company->toArray();
			return renderWithJson($result);
		} else {
			return renderWithJson($result, 'Company could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Company could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canUpdateUser'));
$app->DELETE('/api/v1/company/{id}', function ($request, $response, $args) {
    global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
	$args = array();
	$args['is_active'] = false;
	$company = Models\Company::find($request->getAttribute('id'));
	$company->fill($args);
	$result = array();
	try {
		$validationErrorFields = $company->validate($args);
		if (empty($validationErrorFields)) {
			$company->save();
			return renderWithJson(array(), 'Company delete sucessfully','', 0);
		} else {
			return renderWithJson($result, 'Company could not be delete. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Company could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canUpdateUser'));
$app->GET('/api/v1/advertisements', function ($request, $response, $args) {
    global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$advertisements = Models\Advertisement::with('user', 'attachment')->Filter($queryParams)->paginate($count)->toArray();
		$results = array(
			'data' => $advertisements
		);
		return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
});
$app->GET('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $advertisement = Models\Advertisement::find($request->getAttribute('id'));
        if (!empty($advertisement)) {
            $result['data'] = $advertisement;
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canUpdateUser'));
$app->POST('/api/v1/advertisement', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
    $result = array();
    $args = $request->getParsedBody();
    $advertisement = new Models\Advertisement($args);
    try {
        $validationErrorFields = $advertisement->validate($args);
        if (empty($validationErrorFields)) {
            $advertisement->is_active = 1;
            $advertisement->user_id = $authUser->id;
            if ($authUser['role_id'] == \Constants\ConstUserTypes::Admin && !empty($args['user_id'])) {
                $advertisement->user_id = $args['user_id'];
            }
            if ($advertisement->save()) {
				if ($advertisement->id) {
					if (!empty($args['image'])) {
						saveImage('Advertisement', $args['image'], $advertisement->id);
					}
					$result['data'] = $advertisement->toArray();
					return renderWithJson($result);
				}
            } else {
				return renderWithJson($result, 'Advertisement could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Advertisement could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Advertisement could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canUpdateUser'));
$app->PUT('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
	$args = $request->getParsedBody();
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$validationErrorFields = $advertisement->validate($args);
		if (empty($validationErrorFields)) {
			$advertisement->save();
			if (!empty($args['image']) && $advertisement->id) {
				saveImage('Advertisement', $args['image'], $request->getAttribute('id'));
			}
			$result = $advertisement->toArray();
			return renderWithJson($result);
		} else {
			return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canUpdateUser'));
$app->DELETE('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	if (!empty($authUser['id']) && $authUser->role_id != '1') {
		return renderWithJson($result, $message = 'Invalid Request', $fields = '', $isError = 1);
	}
	$args = array();
	$args['is_active'] = false;
	$advertisement = Models\Advertisement::find($request->getAttribute('id'));
	$advertisement->fill($args);
	$result = array();
	try {
		$advertisement->save();
		return renderWithJson(array(), 'Advertisement delete sucessfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canUpdateUser'));
$app->POST('/api/v1/mail_test', function ($request, $response, $args) use ($app)
{
	$result = array();
	$args = $request->getParsedBody();
	$result['status'] = 'Failed';
	if ($args && $args['email']) {
		$result['status'] = mail($args['email'],"Mail Test","Testing Mail");
	}
	return renderWithJson($result);
});
$app->run();
