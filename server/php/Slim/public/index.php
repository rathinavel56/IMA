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
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://mysite')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});
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
    if (!empty($response) && $response['access_token'] != '') {
		return renderWithJson($response);
	} else {
		return renderWithJson(array(), 'Session Expired.', '', 1);
	}
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
    $validationErrorFields = $user->validate($args);
    if (!empty($validationErrorFields)) {
        $validationErrorFields = $validationErrorFields->toArray();
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
        try {
            $user->is_email_confirmed = (USER_IS_EMAIL_VERIFICATION_FOR_REGISTER == 1) ? 0 : 1;
            $user->is_active = (USER_IS_ADMIN_ACTIVATE_AFTER_REGISTER == 1) ? 0 : 1;
            if (USER_IS_AUTO_LOGIN_AFTER_REGISTER == 1) {
                $user->is_email_confirmed = 1;
                $user->is_active = 1;
            }
            $user->role_id = \Constants\ConstUserTypes::User;
            $user->save();
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
				if($user->role_id == \Constants\ConstUserTypes::Admin) {
					$scopes = 'canAdmin';
				} else if($user->role_id == \Constants\ConstUserTypes::Employer) {
					$scopes = 'canContestantUser';
				} else {
					$scopes = 'canUser';	
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
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
                return renderWithJson($result, 'Success','', 0);
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
    if (isset($body['role_id']) && $body['role_id'] != '') {
		$result = array();
		$user = new Models\User;
		$enabledIncludes = array(
			'attachment',
			'role'
		);		
		if (USER_USING_TO_LOGIN == 'username') {
			$log_user = $user->where('username', $body['username'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->where('role_id', $body['role_id'])->first();
		} else {
			$log_user = $user->where('email', $body['email'])->with($enabledIncludes)->where('is_active', 1)->where('is_email_confirmed', 1)->where('role_id', $body['role_id'])->first();
		}
		$password = crypt($body['password'], $log_user['password']);
		$validationErrorFields = $user->validate($body);
		$validationErrorFields = array();
		if (empty($validationErrorFields) && !empty($log_user) && ($password == $log_user['password'])) {
			$scopes = '';
			if($log_user['role']['id'] == \Constants\ConstUserTypes::Admin) {
				$scopes = 'canAdmin';
			} else if($log_user['role']['id'] == \Constants\ConstUserTypes::Employer) {
				$scopes = 'canContestantUser';
			} else {
				$scopes = 'canUser';	
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
	} else {
		return renderWithJson(array(), 'Role Id is required.', '', 1);
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
                return renderWithJson($result, 'Success','', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'User Password could not be updated. Please, try again', '', 1);
            }
        } else {
            return renderWithJson($result, 'Password is invalid . Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'User Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User Password could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'User Password could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));;
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson(array(), 'Please verify in your token', '', 1);
        }
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/contestants', function ($request, $response, $args) {    
    $queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
        $count = PAGE_LIMIT;
        if (!empty($queryParams['limit'])) {
            $count = $queryParams['limit'];
        }
        $queryParams['role_id'] = \Constants\ConstUserTypes::Employer;
		$queryParams['is_email_confirmed'] = true;
		$queryParams['is_active'] = true;
		$enabledIncludes = array(
            'attachment',
			'company'
        );
		if (!empty($queryParams['contest_id'])) {
			$enabledIncludes = array_merge($enabledIncludes,array('contest'));
        }
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
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/contestants/highest_votes', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();    
    global $authUser;
    $result = array();
    try {
		$data = array();
        $enabledIncludes = array(
            'attachment'
		);
		$third_highest_votes = Models\User::select('votes')->where('is_email_confirmed', true)->where('is_active', true)->where('role_id', \Constants\ConstUserTypes::Employer)->where('votes','<>', 0)->orderBy('votes', 'DESC')->limit(1)->skip(2)->get()->toArray();
		$highest_votes = array();
		if (!empty($third_highest_votes)) {
			$highest_votes = Models\User::with($enabledIncludes)->where('is_email_confirmed', true)->where('is_active', true)->where('role_id', \Constants\ConstUserTypes::Employer)->where('votes','<>', 0)->where('votes','>=', $third_highest_votes[0]['votes'])->orderBy('votes', 'DESC')->get()->toArray();
		}
		$highest_votes_list = array();
		if ((!empty($highest_votes))) {
			$highest_votes_list['title'] = "Top Female Influencer of the Year";
			$highest_votes_list['data'] = (!empty($highest_votes)) ? $highest_votes : array();
		} else {
			$highest_votes_list = array();
		}	
		$sql = "select * from(
				SELECT user_categories.user_id,user_categories.category_id, users.votes,users.first_name,categories.name,
				rank() over(partition by user_categories.category_id order by users.votes desc) as rank_vote
				FROM user_categories,users,categories
				where user_categories.user_id = users.id
				and user_categories.category_id = categories.id
				and users.votes <> 0
				and users.role_id = ".\Constants\ConstUserTypes::Employer."
				and users.is_email_confirmed = 1
				and users.is_active = 1
				order by user_categories.category_id,users.first_name) user_data
				where rank_vote = 1";
		$category_highest_votes = Capsule::select($sql);
		if(!empty($category_highest_votes)) {
			$category_highest_votes = json_decode(json_encode($category_highest_votes), true);
			$user_ids = array_column($category_highest_votes, 'user_id');
			$category_highest_votes_users = Models\User::with($enabledIncludes)->whereIn('id', $user_ids)->get()->toArray();
			$users = array();
			foreach ($category_highest_votes as $category_highest_vote) {
				$user_id = $category_highest_vote['user_id'];
				$user_data = array_filter($category_highest_votes_users , function ($elem) use($user_id) {
															  return $elem['id'] == $user_id;
															});
				$category_data = array();
				$category_data = current($user_data);
				$category_data['category_name'] = $category_highest_vote['name'];
				$users[] = $category_data;
			}
		}
		$data['highest_votes'] = $highest_votes_list;
		$data['category_highest_votes'] = (!empty($users)) ? $users : array();
        $result = array(
            'data' => $data
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $e->getMessage(), $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
/**
 * POST UserPost
 * Summary: Create New user by admin
 * Notes: Create New user by admin
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/users', function ($request, $response, $args) {
	global $authUser;
	$args = $request->getParsedBody();
    $result = array();
    $user = new Models\User($args);
    $validationErrorFields = $user->validate($args);
    $validationErrorFields['unique'] = array();
    $validationErrorFields['required'] = array();
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
            unset($user->image);
            unset($user->cover_photo);       
            $user->save();
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'User could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
        'role'
    );
    $user = Models\User::with($enabledIncludes)->where('id', $request->getAttribute('userId'))->first();
    if (!empty($authUser['id']) && $authUser->role_id == '1') {
        $user_model = new Models\User;
        $user->makeVisible($user_model->hidden);
    }
    if (!empty($user)) {
        $result['data'] = $user;
        $isAllowToViewBadge = false;
        if (!empty($_GET['type']) && $_GET['type'] == 'view' && (empty($authUser) || (!empty($authUser) && $authUser['id'] != $request->getAttribute('userId')))) {
            insertViews($request->getAttribute('userId'), 'User');
        }
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1, 404);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
        'role'
    );
    $user = Models\User::with($enabledIncludes)->where('id', $authUser->id)->first();
    $user_model = new Models\User;
    $user->makeVisible($user_model->hidden);
    if (!empty($user)) {
        $result['data'] = $user;
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
    $validation = true;
	if (!empty($args['address'])) {
		$user->address = $args['address'];
	}
    if (!empty($user)) {
		if ($authUser['role_id'] != \Constants\ConstUserTypes::Admin) {
			unset($args['username']);
			unset($args['is_paypal_connect']);
			unset($args['is_stripe_connect']);
			unset($args['subscription_end_date']);
			unset($args['votes']);
			unset($args['rank']);
		}
        if ($validation) {
            $user->fill($args);
            unset($user->image);
            unset($user->cover_photo);
            try {
                $user->save();
                if (!empty($args['image'])) {
                    saveImage('UserAvatar', $args['image'], $user->id);
                }
                if (!empty($args['cover_photo'])) {
                    saveImage('CoverPhoto', $args['cover_photo'], $user->id);
                }
                $enabledIncludes = array(
                    'attachment'
                );
                $user = Models\User::with($enabledIncludes)->find($user->id);
                $result['data'] = $user->toArray();
                return renderWithJson($result, 'Success','', 0);
            } catch (Exception $e) {
                return renderWithJson($result, 'User could not be updated. Please, try again.', '', 1);
            }
        } else {
            return renderWithJson($result, 'Country is required', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid user Details, try again.', '', 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'User could not be deleted. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Invalid User details.', '', 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
        return renderWithJson($result, 'Success','', 0);
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
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
});
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Provider could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'Provider could not be updated. Please, try again', $validationErrorFields, 1);
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
        $result = Models\Contest::processOrder($args);
        if (!empty($result)) {
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, $message = 'Order could not added. No record found', '', $isError = 1);
        }
    } else {
        $validationErrorFields['class'] = 'class required';
        $validationErrorFields['foreign_id'] = 'foreign_id required';
        return renderWithJson($result, $message = 'Order could not added', $validationErrorFields, $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
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
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
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
            'payment_gateway'
        );
        $transactions = Models\Transaction::with($enabledIncludes)->Filter($queryParams);
        $transactions = $transactions->paginate($count);
        $transactionsNew = $transactions->toArray();
        $result = array(
            'data' => $transactions->toArray(),
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
            'payment_gateway'
        );
        $transactions = Models\Transaction::with($enabledIncludes);
        if (!empty($authUser['id'])) {
            $user_id = $authUser['id'];
            $transactions->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('to_user_id', $user_id);
            });
        }
        $transactions = $transactions->Filter($queryParams)->paginate($count);
        $data = $transactions->toArray();
        $result = array(
            'data' => $data,
            '_metadata' => $transactionsNew
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
/**
 * GET paymentGatewayGet
 * Summary: Filter  payment gateway
 * Notes: Filter payment gateway.
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/payment_gateways', function ($request, $response, $args) {
	global $authUser;
    $queryParams = $request->getQueryParams();
    $result = array();
    try {
        $paymentGateways = Models\PaymentGateway::with('attachment')->where('is_active', true)->Filter($queryParams)->get()->toArray();
		$payGateway = array();
		$addCard = array();
		if (!empty($paymentGateways)) {
			foreach($paymentGateways as $paymentGateway) {
				//if ($paymentGateway['name'] != 'Add Card') {
				//	$payGateway[] = $paymentGateway;
				//} else {
					$addCard[] = $paymentGateway;
				// }
			}
		}
        // $cards = Models\Card::select('id', 'card_display_number', 'expiry_date', 'name')->where('user_id', $authUser->id)->get()->toArray();
        $result = array(
            'data' => $addCard // array_merge(array_merge($payGateway, $cards), $addCard)
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->PUT('/api/v1/payment_gateway/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$paymentGateway = Models\PaymentGateway::find($request->getAttribute('id'));
	$result = array();
	try {
		if (!empty($args['image']) && $paymentGateway->id) {
			saveImage('PaymentGateway', $args['image'], $paymentGateway->id);
		}
		$result = $paymentGateway->toArray();
		return renderWithJson($result, 'Success','', 0);		
	} catch (Exception $e) {
		return renderWithJson($result, 'PaymentGateway could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
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
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page user could not be added. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be added. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
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
            return renderWithJson($result, 'Success','', 0);
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Page could not be updated. Please, try again.', '', 1);
        }
    } else {
        return renderWithJson($result, 'Page could not be updated. Please, try again.', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
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
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Page could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canAdmin'));
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
        $settingCategories = $settingCategories->paginate($count)->toArray();
        $data = $settingCategories['data'];
        unset($settingCategories['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $settingCategories
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
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
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
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
        return renderWithJson($result, 'Success','', 0);
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
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
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
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Setting could not be updated. Please, try again.', '', 1);
    }
})->add(new ACL('canAdmin'));
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
        $emailTemplates = $emailTemplates->paginate($count)->toArray();
        $data = $emailTemplates['data'];
        unset($emailTemplates['data']);
        $result = array(
            'data' => $data,
            '_metadata' => $emailTemplates
        );
        return renderWithJson($result, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($result, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
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
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
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
            return renderWithJson($result, 'Success','', 0);
        } catch (Exception $e) {
            return renderWithJson($result, 'Email template could not be updated. Please, try again', '', 1);
        }
    } else {
        return renderWithJson($result, 'Email template could not be updated. Please, try again', $validationErrorFields, 1);
    }
})->add(new ACL('canAdmin'));
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
})->add(new ACL('canAdmin canUser canContestantUser'));
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
            'timezone'
        );
        $ips = Models\Ip::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $ips['data'];
        unset($ips['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $ips
        );
        return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
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
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'Ip could not be deleted. Please, try again.', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Ip could not be deleted. Please, try again.', '', 1);
    }
})->add(new ACL('canAdmin'));
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
        'timezone'
    );
    $ip = Models\Ip::with($enabledIncludes)->find($request->getAttribute('ipId'));
    if (!empty($ip)) {
        $result['data'] = $ip;
        return renderWithJson($result, 'Success','', 0);
    } else {
        return renderWithJson($result, 'No record found', '', 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/cron', function ($request, $response, $args) use ($app)
{
	//Token clean up 
	$now = date('Y-m-d h:i:s');
	Models\OauthAccessToken::where('expires', '<=', $now)->delete();
	Models\OauthRefreshToken::where('expires', '<=', $now)->delete();
});
$app->GET('/api/v1/companies', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$companies = Models\Company::Filter($queryParams)->paginate($count)->toArray();
		$data = $companies['data'];
		unset($companies['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $companies
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/company/{id}', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
        $company = Models\Company::find($request->getAttribute('id'));
        if (!empty($company)) {
            $result['data'] = $company;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/company', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
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
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Company could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Company could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Company could not be added. ssPlease, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/company/{id}', function ($request, $response, $args) {
    global $authUser;
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
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'Company could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Company could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->DELETE('/api/v1/company/{id}', function ($request, $response, $args) {
    global $authUser;
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
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/advertisements', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$advertisements = Models\Advertisement::with('user', 'attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $advertisements['data'];
		unset($advertisements['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $advertisements
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
	
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
        $advertisement = Models\Advertisement::find($request->getAttribute('id'));
        if (!empty($advertisement)) {
            $result['data'] = $advertisement;
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson($result, 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin'));
$app->POST('/api/v1/advertisement', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
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
					return renderWithJson($result, 'Success','', 0);
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
})->add(new ACL('canAdmin'));
$app->PUT('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
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
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Advertisement could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin'));
$app->DELETE('/api/v1/advertisement/{id}', function ($request, $response, $args) {
    global $authUser;
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
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/products', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$queryParams['is_active'] = true;
		if (!empty($queryParams['filter_by']) && $queryParams['filter_by'] == 'me') {
			$queryParams['user_id'] = $authUser->id;
		} else {
			$queryParams['inactive'] = false;
		}
		$products = Models\Product::with('attachment', 'product_sizes', 'product_colors')->Filter($queryParams)->paginate($count)->toArray();
		$data = $products['data'];
		unset($products['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $products
        );
		return renderWithJson($results, 'Success', '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/product/{id}', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$product = Models\Product::with('attachment', 'product_sizes', 'product_colors')->where('id', $request->getAttribute('id'))->get()->toArray();
        if (!empty($product)) {
            $result['data'] = $product[0];
            return renderWithJson($result, 'Success','', 0);
        } else {
            return renderWithJson(array(), 'No record found', '', 1);
        }
    } catch (Exception $e) {
        return renderWithJson(array(), $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->POST('/api/v1/product', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $product = new Models\Product($args);
    try {
        $validationErrorFields = $product->validate($args);
        if (empty($validationErrorFields)) {
            $product->is_active = 1;
            $product->user_id = $authUser->id;
            if ($product->save()) {
				if ($product->id) {
					if (!empty($args['image'])) {
						foreach($args['image'] as $image) {
							saveImage('Product', $image['file'], $product->id);
						}
					}
					if (!empty($args['product_sizes'])) {
						foreach($args['product_sizes'] as $product_size) {
							$productSize = new Models\ProductSize;
							$productSize->product_id = $product->id;
							$productSize->size_id = $product_size['size_id'];
							$productSize->save();
						}
					}
					if (!empty($args['product_colors'])) {
						foreach($args['product_colors'] as $product_color) {
							$productColor = new Models\ProductColor;
							$productColor->product_id = $product->id;
							$productColor->color = $product_color['color'];
							$productColor->save();
						}
					}
					$result['data'] = $product->toArray();
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Product could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Product could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Product could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canContestantUser'));
$app->PUT('/api/v1/product/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	$product = Models\Product::find($request->getAttribute('id'));
	if ($authUser->id != $product->user_id) {
		return renderWithJson(array(), 'Invalid Request', '', 1);
	}
	$product->fill($args);
	$result = array();
	try {
		$validationErrorFields = $product->validate($args);
		if (empty($validationErrorFields)) {
			$product->save();
			if (!empty($args['image'])) {
				if (!empty($args['image'])) {
					foreach($args['image'] as $image) {
						saveImage('Product', $image['file'], $product->id);
					}
				}
			}
			if (!empty($args['product_sizes'])) {
				Models\ProductSize::where('product_id', $product->id)->update(array(
					'is_active' => false
				));
				foreach($args['product_sizes'] as $product_size) {
					$productSize = new Models\ProductSize;
					$productSize->product_id = $product->id;
					$productSize->size_id = $product_size['size_id'];
					$productSize->save();
				}
			}
			if (!empty($args['product_colors'])) {
				Models\ProductColor::where('product_id', $product->id)->update(array(
					'is_active' => false
				));
				foreach($args['product_colors'] as $product_color) {
					$productColor = new Models\ProductColor;
					$productColor->product_id = $product->id;
					$productColor->color = $product_color['color'];
					$productColor->save();
				}
			}
			$result = $product->toArray();
			return renderWithJson($result, 'Success','', 0);
		} else {
			return renderWithJson($result, 'Product could not be updated. Please, try again.', $validationErrorFields, 1);
		}
	} catch (Exception $e) {
		return renderWithJson($result, 'Product could not be updated. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canContestantUser'));
$app->DELETE('/api/v1/product/{id}', function ($request, $response, $args) {
    global $authUser;
	$args = array();
	$args['is_active'] = false;
	$product = Models\Product::find($request->getAttribute('id'));
	if ($authUser->id != $product->user_id) {
		return renderWithJson(array(), 'Invalid Request', '', 1);
	}
	$product->fill($args);
	$result = array();
	try {
		$product->save();
		return renderWithJson(array(), 'Product delete sucessfully','', 0);
	} catch (Exception $e) {
		return renderWithJson($result, 'Product could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canContestantUser'));
$app->GET('/api/v1/catagories', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$categories = Models\Category::Filter($queryParams)->paginate($count)->toArray();
		$data = $categories['data'];
		unset($categories['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $categories
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/user_catagories', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$user_categories = Models\UserCategory::with('user', 'attachment')->Filter($queryParams)->paginate($count)->toArray();
		$data = $user_categories['data'];
		unset($user_categories['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $user_categories
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser'));
$app->POST('/api/v1/user_catagory', function ($request, $response, $args) {
    global $authUser;
    $result = array();
    $args = $request->getParsedBody();
    $product = new Models\Product($args);
    try {
        $validationErrorFields = $product->validate($args);
        if (empty($validationErrorFields)) {
            $product->is_active = 1;
            $product->user_id = $authUser->id;
            if ($product->save()) {
				if ($product->id) {
					if (!empty($args['image'])) {
						saveImage('Product', $args['image'], $product->id);
					}
					$result['data'] = $product->toArray();
					return renderWithJson($result, 'Success','', 0);
				}
            } else {
				return renderWithJson($result, 'Product could not be added. Please, try again.', '', 1);
			}
        } else {
            return renderWithJson($result, 'Product could not be added. Please, try again.', $validationErrorFields, 1);
        }
    } catch (Exception $e) {
        return renderWithJson($result, 'Product could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin'));
$app->GET('/api/v1/cart', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$enabledIncludes = array(
                    'attachment',
					'product',
					'product_sizes',
					'product_colors'
                );
		$is_purchase = false;
		if (isset($queryParams['is_purchase']) && $queryParams['is_purchase'] == 'true') {
			$is_purchase = true;
		}			
        $carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->where('is_purchase', $is_purchase)->get()->toArray();
		$total_amount = 0;
		if (!empty($carts)) {
			foreach ($carts as $cart) {
				$total_amount = $total_amount + ($cart['product']['price']*$cart['quantity']);
			}
		}
        $results = array(
            'data' => $carts,
			'total_amount' => $total_amount
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->PUT('/api/v1/cart', function ($request, $response, $args) {
    global $authUser;
	$result = array();
	$queryParams = $request->getQueryParams();
    $args = $request->getParsedBody();
    try {
		if (!empty($args)) {
			$datas = array();
			if (!empty($args) && empty($args[0])) {
				$datas[] = $args;
			} else {
				$datas = $args;
			}
			foreach ($datas as $arg) {
				$cart = Models\Cart::where('user_id', $authUser->id)->where('product_id', $arg['product_id'])->where('product_size_id', $arg['product_size_id'])->where('product_color_id', $arg['product_color_id'])->where('is_purchase', false)->get()->toArray();
				if (!empty($cart)) {
					Models\Cart::where('user_id', $authUser->id)->where('product_id', $arg['product_id'])->where('product_size_id', $arg['product_size_id'])->where('product_color_id', $arg['product_color_id'])->where('is_purchase', false)->update(array(
							'quantity' => $arg['quantity']
						));
				} else {
					$cart = new Models\Cart;
					$cart->is_active = 1;
					$cart->user_id = $authUser->id;
					$cart->product_id = $arg['product_id'];
					$cart->quantity = $arg['quantity'];
					$cart->product_size_id = $arg['product_size_id'];
					$cart->product_color_id = $arg['product_color_id'];
					$cart->save();
				}
			}
			if (isset($queryParams['product_id'])) {
				$products = Models\Product::with('attachment', 'cart', 'product_sizes', 'product_colors')->where('id', $queryParams['product_id'])->first()->toArray();
				$results = array(
					'data' => $products
				);
				return renderWithJson($results, 'Success','', 0);
			} else {
				$enabledIncludes = array(
							'attachment',
							'product'
						);
				$carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->get()->toArray();
				$total_amount = 0;
				if (!empty($carts)) {
					foreach ($carts as $cart) {
						$total_amount = $total_amount + $cart['product']['price'];
					}
				}
			}
			$results = array(
				'data' => $carts,
				'total_amount' => $total_amount
			);
			return renderWithJson($results, 'Success','', 0);
		}
    } catch (Exception $e) {
        return renderWithJson($result, 'Cart could not be added. Please, try again.'.$e->getMessage(), '', 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->DELETE('/api/v1/cart/{id}', function ($request, $response, $args) {
    global $authUser;
	try {
		Models\Cart::where('id', $request->getAttribute('id'))->where('user_id', $authUser->id)->delete();
		return renderWithJson(array(), 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'Cart could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/vote_packages', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$votes = Models\VotePackage::where('is_active', true)->get()->toArray();
		if (!empty($votes)) {
			$response = array(
                            'data' => $votes
                        ); 
			return renderWithJson($response, 'Success','', 0);
		} 
		return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/contest', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
		$contests = Models\Contest::where('is_active', true)->get()->toArray();
		if (!empty($contests)) {
			$response = array(
                            'data' => $contests
                        ); 
			return renderWithJson($response, 'Success','', 0);
		} 
		return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/user_contests/{contest_id}', function ($request, $response, $args) {
	global $authUser;
	if ($request->getAttribute('contest_id') != '') {
		$queryParams = $request->getQueryParams();
		$results = array();
		try {
			$enabledIncludes = array(
						'attachment',
						'user'
					);
			$contests = Models\UserContest::where('contest_id', $request->getAttribute('contest_id'))->with($enabledIncludes)->get()->toArray();
			if (!empty($contests)) {
				$response = array(
								'data' => $contests
							); 
				return renderWithJson($response, 'Success','', 0);
			} 
			return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
		} catch (Exception $e) {
			return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), 'contest_id is required', $fields = '', 0);
	}
});
$app->PUT('/api/v1/vote/{contestant_id}', function ($request, $response, $args) {
    global $authUser;
	try {
		$user_details = Models\User::find($authUser->id);
		if ($user_details->total_votes > 0) {
			$contestant_details = Models\User::find($request->getAttribute('contestant_id'));
			if (!empty($contestant_details)) {
				$user_details->total_votes = $user_details->total_votes - 1;
				$user_details->save();
				$contestant_details->votes = $contestant_details->votes + 1;
				$contestant_details->save();
				return renderWithJson(array(), 'Vote added successfully','', 0);
			} else {
				return renderWithJson(array(), 'User not found','', 1);
			}
		}
		return renderWithJson(array(), 'Purchase Vote','', 1);
	} catch (Exception $e) {
		return renderWithJson(array(), 'Vote could not be add. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/subscription', function ($request, $response, $args) {
    global $authUser;
    $queryParams = $request->getQueryParams();
    try {
		$subscription = Models\Subscription::where('is_active', true)->get()->toArray();
		if (!empty($subscription)) {
			$response = array(
                            'data' => $subscription
                        ); 
			return renderWithJson($response, 'Success','', 0);
		} 
		return renderWithJson(array(), $message = 'No record found', $fields = '', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/purchase/contest/{packageId}', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if (!empty($queryParams['contestant_id']) && $queryParams['contestant_id'] != '') { 
		$vote_package = Models\VotePackage::where('id', $request->getAttribute('packageId'))->first();
		if (!empty($vote_package)) {
			$paymentGateway = Models\PaymentGateway::select('id', 'name', 'is_active')->where('id', $queryParams['payment_gateway_id'])->where('is_active', true)->get()->toArray();
			if (!empty($paymentGateway)) {
				try {
					$paymentGateway = current($paymentGateway);
					if ($paymentGateway['name'] == 'PayPal') {
						$post = array(
							'actionType' => 'PAY',
							'currencyCode' => 'USD',
							'receiverList' => array(
								'receiver'=> array(
									array(
										'email' => 'freehidehide@gmail.com',
										'amount'=> $vote_package->price
									)
								)
							),
							'requestEnvelope' => array(
								'errorLanguage' => 'en_US'
							),
							'returnUrl' => $_server_domain_url.'/api/v1/purchase/contest/verify/'. $authUser->id.'/'.$queryParams['contestant_id'].'?success=0',
							'cancelUrl' => $_server_domain_url.'/api/v1/purchase/contest/verify/'. $authUser->id.'/'.$queryParams['contestant_id'].'?success=1'
						);
						$method = 'AdaptivePayments/Pay';
						$response = paypal_pay($post, $method);
						if (!empty($response) && $response['ack'] == 'success') {
							$user = Models\User::find($authUser->id);
							$user->instant_vote_pay_key = $response['payKey'];
							$user->instant_vote_to_purchase = $vote_package->vote;
							$user->save();
							$data['payUrl'] = $response['payUrl'];
							return renderWithJson($data, 'Success','', 0);
						} else {	
							return renderWithJson(array(),'Please check with Administrator', '', 1);
						}
					} else if ($paymentGateway['name'] == 'Stripe') {
						//
					} else if ($paymentGateway['name'] == 'Add Card') {
						$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
						if (!empty($card) && $queryParams['ccv']) {
							echo '<pre>';print_r($card);exit;
						} else {
							return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
						}
					} else {
						return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
					}
				} catch (Exception $e) {
					return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
				}
			} else {
				return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Package is empty', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'contestant is required', $fields = '', $isError = 1);
	}	
})->add(new ACL('canAdmin canUser canContestantUser'));	
$app->GET('/api/v1/purchase/contest/verify/{user_id}/{contestant_id}', function ($request, $response, $args) {
	$user = Models\User::find($request->getAttribute('user_id'));
	if (!empty($user) && $user->instant_vote_pay_key != '') {
		$post = array(
				'payKey' => $user->instant_vote_pay_key,
				'requestEnvelope' => array(
					'errorLanguage' => 'en_US'
				)
			);
		$method = 'AdaptivePayments/PaymentDetails';
		$response = paypal_pay($post, $method);
		if (!empty($response) && $response['ack'] == 'success' && !empty($response['response']) && strtolower($response['response']['status']) == 'completed') {
			$contestant_details = Models\UserContest::where('user_id', $request->getAttribute('contestant_id'))->first();
			$contestant_details->instant_votes = $contestant_details->instant_votes + $user->instant_vote_to_purchase;
			$contestant_details->save();
			$user->instant_vote_pay_key = '';
			$user->save();
			return renderWithJson(array(), 'Success','', 0);
		}
	}
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/purchase/vote_package/{packageId}', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if (!empty($queryParams['contestant_id']) && $queryParams['contestant_id'] != '') { 
		$vote_package = Models\VotePackage::where('id', $request->getAttribute('packageId'))->first();
		if (!empty($vote_package)) {
			$paymentGateway = Models\PaymentGateway::select('id', 'name', 'is_active')->where('id', $queryParams['payment_gateway_id'])->where('is_active', true)->get()->toArray();
			if (!empty($paymentGateway)) {
				try {
					$paymentGateway = current($paymentGateway);
					if ($paymentGateway['name'] == 'PayPal') {
						$post = array(
							'actionType' => 'PAY',
							'currencyCode' => 'USD',
							'receiverList' => array(
								'receiver'=> array(
									array(
										'email' => 'freehidehide@gmail.com',
										'amount'=> $vote_package->price
									)
								)
							),
							'requestEnvelope' => array(
								'errorLanguage' => 'en_US'
							),
							'returnUrl' => $_server_domain_url.'/api/v1/purchase/vote_package/verify/'. $authUser->id.'/'.$queryParams['contestant_id'].'?success=0',
							'cancelUrl' => $_server_domain_url.'/api/v1/purchase/vote_package/verify/'. $authUser->id.'/'.$queryParams['contestant_id'].'?success=1'
						);
						$method = 'AdaptivePayments/Pay';
						$response = paypal_pay($post, $method);
						if (!empty($response) && $response['ack'] == 'success') {
							$user = Models\User::find($authUser->id);
							$user->vote_pay_key = $response['payKey'];
							$user->vote_to_purchase = $vote_package->vote;
							$user->save();
							$data['payUrl'] = $response['payUrl'];
							return renderWithJson($data, 'Success','', 0);
						} else {	
							return renderWithJson(array(),'Please check with Administrator', '', 1);
						}
					} else if ($paymentGateway['name'] == 'Stripe') {
						//
					} else if ($paymentGateway['name'] == 'Add Card') {
						$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
						if (!empty($card) && $queryParams['ccv']) {
							echo '<pre>';print_r($card);exit;
						} else {
							return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
						}
					} else {
						return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
					}
				} catch (Exception $e) {
					return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
				}
			} else {
				return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Package is empty', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'contestant is required', $fields = '', $isError = 1);
	}	
})->add(new ACL('canAdmin canUser canContestantUser'));	
$app->GET('/api/v1/purchase/vote_package/verify/{user_id}/{contestant_id}', function ($request, $response, $args) {
	$user = Models\User::find($request->getAttribute('user_id'));
	if (!empty($user) && $user->vote_pay_key != '') {
		$post = array(
				'payKey' => $user->vote_pay_key,
				'requestEnvelope' => array(
					'errorLanguage' => 'en_US'
				)
			);
		$method = 'AdaptivePayments/PaymentDetails';
		$response = paypal_pay($post, $method);
		if (!empty($response) && $response['ack'] == 'success' && !empty($response['response']) && strtolower($response['response']['status']) == 'completed') {
			// $user->total_votes = $user->total_votes + $user->vote_to_purchase;
			$contestant_details = Models\User::find($request->getAttribute('contestant_id'));
			$contestant_details->votes = $contestant_details->votes + $user->vote_to_purchase;
			$contestant_details->save();
			$user->vote_pay_key = '';
			$user->save();
			return renderWithJson(array(), 'Success','', 0);
		}
	}
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/purchase/cart', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	$enabledIncludes = array(
				'product'
			);
	$carts = Models\Cart::with($enabledIncludes)->where('user_id', $authUser->id)->get()->toArray();
	if (!empty($carts)) {
		$total_amount = 0;
		if (!empty($carts)) {
			foreach ($carts as $cart) {
				$total_amount = $total_amount + ($cart['product']['price']*$cart['quantity']);
			}
		}
		$paymentGateway = Models\PaymentGateway::select('id', 'name', 'is_active')->where('id', $queryParams['payment_gateway_id'])->where('is_active', true)->get()->toArray();
		if (!empty($paymentGateway)) {
			try {
				$paymentGateway = current($paymentGateway);
				if ($paymentGateway['name'] == 'PayPal') {
					$post = array(
						'actionType' => 'PAY',
						'currencyCode' => 'USD',
						'receiverList' => array(
							'receiver'=> array(
								array(
									'email' => 'freehidehide@gmail.com',
									'amount'=> $total_amount
								)
							)
						),
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						),
						'returnUrl' => $_server_domain_url.'/api/v1/purchase/cart/verify/'. $authUser->id.'?success=0',
						'cancelUrl' => $_server_domain_url.'/api/v1/purchase/cart/verify/'. $authUser->id.'?success=1'
					);
					$method = 'AdaptivePayments/Pay';
					$response = paypal_pay($post, $method);
					if (!empty($response) && $response['ack'] == 'success') {
						Models\Cart::where('user_id', $authUser->id)->update(array(
							'pay_key' => $response['payKey']
						));
						$data['payUrl'] = $response['payUrl'];
						return renderWithJson($data, 'Success','', 0);
					} else {	
						return renderWithJson(array(),'Please check with Administrator', '', 1);
					}
				} else if ($paymentGateway['name'] == 'Stripe') {
					//
				} else if ($paymentGateway['name'] == 'Add Card') {
					$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
					if (!empty($card) && $queryParams['ccv']) {
						echo '<pre>';print_r($card);exit;
					} else {
						return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
					}
				} else {
					return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
				}
			} catch (Exception $e) {
				return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Invalid Package is empty', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser'));	
$app->GET('/api/v1/purchase/cart/verify/{user_id}', function ($request, $response, $args) {
	$cart = Models\Cart::where('user_id', $request->getAttribute('user_id'))->first();
	if (!empty($cart) && $cart->pay_key != '') {
		$post = array(
				'payKey' => $cart->pay_key,
				'requestEnvelope' => array(
					'errorLanguage' => 'en_US'
				)
			);
		$method = 'AdaptivePayments/PaymentDetails';
		$response = paypal_pay($post, $method);
		if (!empty($response) && $response['ack'] == 'success' && !empty($response['response']) && strtolower($response['response']['status']) == 'completed') {
			Models\Cart::where('user_id', $request->getAttribute('user_id'))->where('pay_key', $cart->pay_key)->update(array(
								'pay_key' => '',
								'is_purchase' => true
							));
			return renderWithJson(array(), 'Success','', 0);
		}
	}
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/purchase/subscription/{packageId}', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	$subscription = Models\Subscription::where('id', $request->getAttribute('packageId'))->first();
    if (!empty($subscription)) {
		$paymentGateway = Models\PaymentGateway::select('id', 'name', 'is_active')->where('id', $queryParams['payment_gateway_id'])->where('is_active', true)->get()->toArray();
		if (!empty($paymentGateway)) {
			try {
				$paymentGateway = current($paymentGateway);
				if ($paymentGateway['name'] == 'PayPal') {
					$post = array(
						'actionType' => 'PAY',
						'currencyCode' => 'USD',
						'receiverList' => array(
							'receiver'=> array(
								array(
									'email' => 'freehidehide@gmail.com',
									'amount'=> $subscription->price
								)
							)
						),
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						),
						'returnUrl' => $_server_domain_url.'/api/v1/purchase/subscription/verify/'. $authUser->id.'?success=0',
						'cancelUrl' => $_server_domain_url.'/api/v1/purchase/subscription/verify/'. $authUser->id.'?success=1'
					);
					$method = 'AdaptivePayments/Pay';
					$response = paypal_pay($post, $method);
					if (!empty($response) && $response['ack'] == 'success') {
						$user = Models\User::find($authUser->id);
						$user->subscription_pay_key = $response['payKey'];
						$user->subscription_id = $request->getAttribute('packageId');
						$user->save();
						$data['payUrl'] = $response['payUrl'];
						return renderWithJson($data, 'Success','', 0);
					} else {	
						return renderWithJson(array(),'Please check with Administrator', '', 1);
					}
				} else if ($paymentGateway['name'] == 'Stripe') {
					//
				} else if ($paymentGateway['name'] == 'Add Card') {
					$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
					if (!empty($card) && $queryParams['ccv']) {
						echo '<pre>';print_r($card);exit;
					} else {
						return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
					}
				} else {
					return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
				}
			} catch (Exception $e) {
				return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Invalid Package', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/purchase/subscription/verify/{user_id}', function ($request, $response, $args) {
	$user = Models\User::find($request->getAttribute('user_id'));
	if (!empty($user) && $user->subscription_pay_key != '') {
		$post = array(
					'payKey' => $user->subscription_pay_key,
					'requestEnvelope' => array(
						'errorLanguage' => 'en_US'
					)
				);
		$method = 'AdaptivePayments/PaymentDetails';
		$response = paypal_pay($post, $method);
		if (!empty($response) && $response['ack'] == 'success' && !empty($response['response']) && strtolower($response['response']['status']) == 'completed') {
			$subscription = Models\Subscription::where('id', $user->subscription_id)->first();
			Models\User::where('id', $request->getAttribute('user_id'))->update(array(
					'subscription_end_date' => date('Y-m-d', strtotime('+'.$subscription->days.' days')),
					'subscription_pay_key' => '',
					'subscription_id' => null
			));
			return renderWithJson(array(), 'Success','', 0);
		}
	}
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/fund', function ($request, $response, $args) {
    global $authUser;
	global $_server_domain_url;
	$queryParams = $request->getQueryParams();
	if (!empty($queryParams) && isset($queryParams['amount'])) {
		$paymentGateway = Models\PaymentGateway::select('id', 'name', 'is_active')->where('id', $queryParams['payment_gateway_id'])->where('is_active', true)->get()->toArray();
		if (!empty($paymentGateway)) {
			try {
				$paymentGateway = current($paymentGateway);
				if ($paymentGateway['name'] == 'PayPal') {
					$post = array(
						'actionType' => 'PAY',
						'currencyCode' => 'USD',
						'receiverList' => array(
							'receiver'=> array(
								array(
									'email' => 'freehidehide@gmail.com',
									'amount'=> $queryParams['amount']
								)
							)
						),
						'requestEnvelope' => array(
							'errorLanguage' => 'en_US'
						),
						'returnUrl' => $_server_domain_url.'/api/v1/fund/verify/'. $authUser->id.'?success=0',
						'cancelUrl' => $_server_domain_url.'/api/v1/fund/verify/'. $authUser->id.'?success=1'
					);
					$method = 'AdaptivePayments/Pay';
					$response = paypal_pay($post, $method);
					if (!empty($response) && $response['ack'] == 'success') {
						$user = Models\User::find($authUser->id);
						$user->fund_pay_key = $response['payKey'];
						$user->save();
						$data['payUrl'] = $response['payUrl'];
						return renderWithJson($data, 'Success','', 0);
					} else {	
						return renderWithJson(array(),'Please check with Administrator', '', 1);
					}
				} else if ($paymentGateway['name'] == 'Stripe') {
					//
				} else if ($paymentGateway['name'] == 'Add Card') {
					$card = Models\Card::where('id', $queryParams['card_id'])->where('user_id', $authUser->id)->get()->toArray();
					if (!empty($card) && $queryParams['ccv']) {
						echo '<pre>';print_r($card);exit;
					} else {
						return renderWithJson(array(), $message = 'Invalid card or ccv', $fields = '', $isError = 1);
					}
				} else {
					return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
				}
			} catch (Exception $e) {
				return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
			}
		} else {
			return renderWithJson(array(), $message = 'Invalid Payment Gateway', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Invalid Amount', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->GET('/api/v1/fund/verify/{user_id}', function ($request, $response, $args) {
	$user = Models\User::find($request->getAttribute('user_id'));
	if (!empty($user) && $user->fund_pay_key != '') {
		$post = array(
				'payKey' => $user->fund_pay_key,
				'requestEnvelope' => array(
					'errorLanguage' => 'en_US'
				)
			);
		$method = 'AdaptivePayments/PaymentDetails';
		$response = paypal_pay($post, $method);
		if (!empty($response) && $response['ack'] == 'success' && !empty($response['response']) && strtolower($response['response']['status']) == 'completed') {
			$user->donated = $user->donated + $response['response']['paymentInfoList']['paymentInfo'][0]['receiver']['amount'];
			$user->fund_pay_key = '';
			$user->save();
			return renderWithJson(array(), 'Success','', 0);
		}
	}
	return renderWithJson(array(),'Please check with Administrator', '', 1);
});
$app->GET('/api/v1/cards', function ($request, $response, $args) {
    global $authUser;
	$queryParams = $request->getQueryParams();
    $results = array();
    try {
		$count = PAGE_LIMIT;
		if (!empty($queryParams['limit'])) {
			$count = $queryParams['limit'];
		}
		$cards = Models\Card::with('user')->Filter($queryParams)->paginate($count)->toArray();
		$data = $cards['data'];
		unset($cards['data']);
		$results = array(
            'data' => $data,
            '_metadata' => $cards
        );
		return renderWithJson($results, 'Success','', 0);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->POST('/api/v1/card', function ($request, $response, $args) {
    global $authUser, $_server_domain_url;
	$result = array();
    $args = $request->getParsedBody();
	if ((isset($args['card_number']) && $args['card_number'] != '') && (isset($args['ccv']) && $args['ccv'] != '') && (isset($args['expiry_date']) && $args['expiry_date'] != '')) {  
		$card = new Models\Card($args);
		$card->card_number = crypt($args['card_number'], $args['ccv']);
		$card->card_display_number = str_repeat('*', strlen($args['card_number']) - 3) . substr($args['card_number'], -3);;
		try {
			$validationErrorFields = $card->validate($args);
			if (empty($validationErrorFields)) {
				$card->is_active = 1;
				$card->user_id = $authUser->id;
				if ($card->save()) {
					$result['data'] = $card->toArray();
					return renderWithJson($result, 'Success','', 0);
				} else {
					return renderWithJson(array(), 'Card could not be added. Please, try again.', '', 1);
				}
			} else {
				return renderWithJson(array(), 'Card could not be added. Please, try again.', $validationErrorFields, 1);
			}
		} catch (Exception $e) {
			return renderWithJson(array(), 'Card could not be added. Please, try again.'.$e->getMessage(), '', 1);
		}
	}
	return renderWithJson(array(), 'Card could not be added. Please, try again.', '', 1);
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->POST('/api/v1/paypal_connect', function ($request, $response, $args) {
    global $authUser;
	$args = $request->getParsedBody();
	if (!empty($args) && isset($args['email'])) {
		$isLive = false;
		$url = $isLive ? 'https://svcs.paypal.com/' : 'https://svcs.sandbox.paypal.com/';
		$tokenUrl = $url.'AdaptiveAccounts/GetVerifiedStatus';	
		try {
			$post = array(
				'actionType' => 'PAY',
				'currencyCode' => 'USD',
				'requestEnvelope' => array(
					'errorLanguage' => 'en_US'
				),
				'matchCriteria' => 'NONE',
				'emailAddress' => $args['email']
			);
			$post_string = json_encode($post);
			$header = array(
					'X-PAYPAL-SECURITY-USERID: freehidehide_api1.gmail.com',
					'X-PAYPAL-SECURITY-PASSWORD: AC3BTDPQW5DWV52W',
					'X-PAYPAL-SECURITY-SIGNATURE: AYS.KyRPCh0NqN2ORLAMv8z1H9kWAS3rJdqYkIt.XoOnKgTHdSlTxCrx',
					'X-PAYPAL-REQUEST-DATA-FORMAT: JSON',
					'X-PAYPAL-RESPONSE-DATA-FORMAT: JSON',
					'X-PAYPAL-APPLICATION-ID: APP-80W284485P519543T'
				);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $tokenUrl);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$result = curl_exec($ch);
			if ($result) {
				$resultArray = json_decode($result, true);
				$user = Models\User::find($authUser->id);
				if (!empty($resultArray) && !empty($resultArray['responseEnvelope']) && strtolower($resultArray['responseEnvelope']['ack']) == 'success') {
					$user->is_paypal_connect = true;
					$user->paypal_email = $args['email'];
					$user->save();
					$data = array(
						'is_paypal_connect' => $user->is_paypal_connect
					);
					return renderWithJson($data, 'Success','', 0);
				} else { 
					$user->is_paypal_connect = false;
					$user->paypal_email = '';
					$user->save();
					$data = array(
						'is_paypal_connect' => $user->is_paypal_connect
					);					
					return renderWithJson($data, 'Invalid','', 0);
				}
			}
			return renderWithJson(array(),'Please check with Administrator', '', 1);
		} catch (Exception $e) {
			return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
		}
	} else {
		return renderWithJson(array(), $message = 'Email is empty', $fields = '', $isError = 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->DELETE('/api/v1/card/{id}', function ($request, $response, $args) {
    global $authUser;
	try {
		Models\Card::where('id', $request->getAttribute('id'))->where('user_id', $authUser->id)->delete();
		return renderWithJson(array(), 'Success','', 0);
	} catch (Exception $e) {
		return renderWithJson(array(), 'Card could not be delete. Please, try again.', $e->getMessage(), 1);
	}
})->add(new ACL('canAdmin canUser canContestantUser'));
$app->POST('/api/v1/mail_test', function ($request, $response, $args) use ($app)
{
	$result = array();
	$args = $request->getParsedBody();
	$result['status'] = 'Failed';
	if ($args && $args['email']) {
		$result['status'] = mail($args['email'],"Mail Test","Testing Mail");
	}
	return renderWithJson($result, 'Success','', 0);
});
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function($req, $res) {
    $handler = $this->notFoundHandler; // handle using the default Slim page not found handler
    return $handler($req, $res);
});
$app->run();
