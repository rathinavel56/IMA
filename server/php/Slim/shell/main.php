<?php
/**
 * Sample cron file
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 */
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../lib/database.php';
$now = date('Y-m-d h:i:s');
Models\OauthAccessToken::where('expires', '<=', $now)->delete();
Models\OauthRefreshToken::where('expires', '<=', $now)->delete();
