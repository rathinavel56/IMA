<?php
/**
 * Constants configurations
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 */
namespace Constants;

class ConstUserTypes
{
    const Admin = 1;
    const User = 2;
    const Employer = 3;
    const Freelancer = 4;
}
class UserCashWithdrawStatus
{
    const Pending = 1;
    const UnderProcess = 2;
    const Approved = 3;
    const Rejected = 4;
}
class SocialLogins
{
    const Twitter = 1;
    const Facebook = 2;
    const GooglePlus = 3;
}
class PaymentGateways
{
    const Wallet = 1;
    const ZazPay = 2;
    const PayPalREST = 3;
	const PayTabREST = 4;
}
class TransactionType
{
    const Contest = 1;
	const VotePackage = 2;
	const Product = 3;
	const InstantPackage = 4;
	const SubscriptionPackage = 5;
	const Fund = 6;
}
class TransactionClass
{
    const Contest = 'Contest';
	const VotePackage = 'VotePackage';
	const Product = 'Product';
	const InstantPackage = 'InstantPackage';
	const SubscriptionPackage = 'SubscriptionPackage';
	const Fund = 'Fund';
}