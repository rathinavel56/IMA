<?php
/**
 *


 *
 */
/**
 * GET walletsGet
 * Summary: Fetch all wallets
 * Notes: Returns all wallets from the system
 * Output-Formats: [application/json]
 */
$app->GET('/api/v1/wallets', function ($request, $response, $args) {
    $queryParams = $request->getQueryParams();
    $results = array();
    try {
        $count = !empty($queryParams['limit']) ? $queryParams['limit'] : PAGE_LIMIT;
        $enabledIncludes = array(
            'user',
            'payment_gateway'
        );
        $wallets = Models\Wallet::with($enabledIncludes)->Filter($queryParams)->paginate($count)->toArray();
        $data = $wallets['data'];
        unset($wallets['data']);
        $results = array(
            'data' => $data,
            '_metadata' => $wallets
        );
        return renderWithJson($results);
    } catch (Exception $e) {
        return renderWithJson($results, $message = 'No record found', $fields = '', $isError = 1);
    }
})->add(new ACL('canListWallet'));
/**
 * POST walletsPost
 * Summary: Creates a new wallet
 * Notes: Creates a new wallet
 * Output-Formats: [application/json]
 */
$app->POST('/api/v1/wallets', function ($request, $response, $args) {
    global $authUser;
    global $_server_domain_url;
    $result = array();
    $args = $request->getParsedBody();
    $amount = $args['amount'];
    if ($amount > 0) {
        $wallet = new Models\Wallet;
        $wallet->user_id = $authUser['id'];
        $wallet->amount = $args['amount'];
        $wallet->payment_gateway_id = $args['payment_gateway_id'];
        $wallet->gateway_id = !empty($args['gateway_id']) ? $args['gateway_id'] : '0';
        $wallet->is_payment_completed = 0;
        $wallet->save();
        $payment = new Models\Payment;
        $args['user_id'] = $authUser['id'];
		$args['description'] = $args['name'] = "Amount add to " . SITE_NAME . " Wallet";
        $args['id'] = $wallet->id;
        //$args['success_url'] = $_server_domain_url . '/api/v1/wallets/'.$wallet->id.'/md5key' . $wallet->id . '/' . md5(SECURITY_SALT . $wallet->id . SITE_NAME);
		$args['success_url'] = 'http://localhost/api/v1/wallets/'.$wallet->id.'/' . md5(SECURITY_SALT . $wallet->id . SITE_NAME);
        $result = $payment->processPayment($wallet->id, $args, 'Wallet');
        return renderWithJson($result);
    } else {
        return renderWithJson($result, 'Amount should be greater than 0.', '', 1);
    }
})->add(new ACL('canCreateWallet'));
$app->GET('/api/v1/wallets/{walletId}/{hash}', function ($request, $response, $args) {
	if (md5(SECURITY_SALT . $request->getAttribute('walletId') . SITE_NAME) == $request->getAttribute('hash')) {
		$wallet = Models\Wallet::where('id', $request->getAttribute('walletId'))->where('is_payment_completed', false)->first();
        if (!empty($wallet)) {
			$paymentGatewaySettings = Models\PaymentGatewaySetting::with('payment_gateway')->where('payment_gateway_id', \Constants\PaymentGateways::PayTabREST)->get()->toArray();
			$post = array();
			if ($paymentGatewaySettings[0]['payment_gateway']['is_test_mode'] == "0") {
				$post['merchant_email'] = $paymentGatewaySettings[0]['live_mode_value'];
				$post['secret_key'] = $paymentGatewaySettings[1]['live_mode_value-'];
			} else {
				$post['merchant_email'] = $paymentGatewaySettings[0]['test_mode_value'];
				$post['secret_key'] = $paymentGatewaySettings[1]['test_mode_value'];
			}
			$post['payment_reference'] = $wallet->paytab_pay_key;
			$result = verify_payment($post);
			if ($result['response_code'] == 100) {
				$wallet->is_payment_completed = true;
				$wallet->paytab_result = $result['result'];
				$wallet->paytab_response_code = $result['response_code'];
				$wallet->paytab_pt_invoice_id = $result['pt_invoice_id'];
				$wallet->paytab_amount = $result['amount'];
				$wallet->paytab_currency = $result['currency'];
				$wallet->paytab_reference_no = $result['reference_no'];
				$wallet->paytab_transaction_id = $result['transaction_id'];
				$adminId = Models\User::select('id')->where('role_id', \Constants\ConstUserTypes::Admin)->first();
				insertTransaction($wallet->user_id, $adminId['id'], $request->getAttribute('walletId'), 'Wallet', \Constants\TransactionType::AmountAddedToWallet, $wallet->payment_gateway_id, $wallet->paytab_amount, 0, 0, 0, 0, $request->getAttribute('walletId'), $wallet->gateway_id);
				$wallet->update();
				$user = Models\User::find($wallet->user_id);
				$user->makeVisible(['available_wallet_amount']);
				$user->available_wallet_amount = $user->available_wallet_amount + $wallet->paytab_amount;
				$user->is_made_deposite = 1;
				$user->update();
				echo "<script>location.replace('http://localhost/client/app/wallets?error_code=0');</script>";
			} else {
				echo "<script>location.replace('http://localhost/client/app/wallets?error_code=512');</script>";
			}
        }
	} else {
		echo "<script>location.replace('http://localhost/client/app/');</script>";
	}
});