<?php
$app_path = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;

$app->POST('/api/v1/paypala/vaults', function ($request, $response, $args) {
    global $authUser;
    $vault_data = $request->getParsedBody();
    $result = array();
    $vault = new Models\Vault($args);
    try {
        $apiContext = getApiContext();
        $card = new CreditCard();
        $card->setType($vault_data['credit_card_type'])->setNumber($vault_data['credit_card_number'])->setExpireMonth($vault_data['expire_month'])->setExpireYear($vault_data['expire_year'])->setCvv2($vault_data['cvv2'])->setFirstName($vault_data['first_name'])->setLastName($vault_data['last_name']);
        $card->create($apiContext);
        $vault->user_id = $authUser['id'];
        $vault->credit_card_type = $card->getType();
        $vault->vault_key = $card->getId();
        $vault->masked_cc = $card->getNumber();
        $vault->expire_month = $card->getExpireMonth();
        $vault->expire_year = $card->getExpireYear();
        $vault->cvv2 = $card->getCvv2();
        $vault->first_name = $card->getFirstName();
        $vault->last_name = $card->getLastName();
        $vault->payment_type = \Constants\PaymentGateways::PayPalREST;
        if ($vault->save()) {
            $result['data'] = $vault->toArray();
            return renderWithJson($result);
        } else {
            return renderWithJson($result, 'Card details could not be saved', '', 1);
        }
    } catch (PayPal\Exception\PayPalConnectionException $ex) {
        $data = json_decode($ex->getData());
        return renderWithJson($data->details, 'Card details could not be saved', '', 1);
    } catch (Exception $ex) {
        return renderWithJson($result, "Card details could not be saved" . $ex->getMessage(), '', 1);
    }
    return renderWithJson($results);
})->add(new ACL('canCreateValut'));