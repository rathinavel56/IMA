<?php
/**
 * API Endpoints
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer
 * @subpackage Core
 */
require_once __DIR__ . '/../../config.inc.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'vendors/Inflector.php';
require_once 'vendors/OAuth2/Autoloader.php';
require_once 'database.php';
require_once 'core.php';
require_once 'constants.php';
require_once 'settings.php';
require_once 'acl.php';
require_once 'auth.php';
use Illuminate\Pagination\Paginator;

Paginator::currentPageResolver(function ($pageName) {
    return empty($_GET[$pageName]) ? 1 : $_GET[$pageName];
});
$config = ['settings' => ['displayErrorDetails' => R_DEBUG]];
global $app;
$app = new Slim\App($config);
$app->add(new \pavlakis\cli\CliRequest());
$app->add(new Auth());
