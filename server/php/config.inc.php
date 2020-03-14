<?php
/**
 * Core configurations
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 */
define('R_DEBUG', false);
ini_set('display_errors', R_DEBUG);
define('R_API_VERSION', 1);
define('DS', DIRECTORY_SEPARATOR);
define('APP_PATH', dirname(dirname(dirname(__FILE__))));
define('MEDIA_PATH', APP_PATH . DS . 'media');
define('TMP_PATH', APP_PATH . DS . 'tmp');
define('CACHE_PATH', TMP_PATH . DS . 'cache');
$default_timezone = 'Europe/Berlin';
if (ini_get('date.timezone')) {
    $default_timezone = ini_get('date.timezone');
}
date_default_timezone_set($default_timezone);
define('R_DB_DRIVER', 'mysql');
define('R_DB_HOST', 'localhost');
define('R_DB_NAME', 'contest');
define('R_DB_USER', 'root');
define('R_DB_PASSWORD', 'Weblogic@123');
define('R_DB_PORT', 3306);
define('SITE_LICENSE_KEY', 'YOUR LICENCE HERE');
define('SECURITY_SALT', 'e9a556134534545ab47c6c81c14f06c0b8sdfsdf');
define('OAUTH_CLIENT_ID', '2212711849319225');
define('OAUTH_CLIENT_SECRET', '14uumnygq6xyorsry8l382o3myr852hb');
define('PAGE_LIMIT', 20);
$_server_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http';
if (!defined('STDIN')) {
    $_server_domain_url = $_server_protocol . '://' . $_SERVER['HTTP_HOST']; // http://localhost
}
if (!defined('STDIN') && !file_exists(APP_PATH . '/tmp/cache/site_url_for_shell.php') && !empty($_server_domain_url)) {
    $fh = fopen(APP_PATH . '/tmp/cache/site_url_for_shell.php', 'a');
    fwrite($fh, '<?php' . "\n");
    fwrite($fh, '$_server_domain_url = \'' . $_server_domain_url . '\';');
    fclose($fh);
}
global $configuration;
$configuration['Photo']['file'] = array(
    'allowedExt' => array(
        'jpg',
        'jpeg',
        'gif',
        'png'
    )
);
$configuration['UserAvatar']['file'] = array(
    'allowedExt' => array(
        'jpg',
        'jpeg',
        'gif',
        'png'
    )
);$configuration['ContestUser']['file'] = array(
    'allowedExt' => '*'
);
$configuration['CoverPhoto']['file'] = array(
    'allowedExt' => array(
        'jpg',
        'jpeg',
        'gif',
        'png'
    )
);
const THUMB_SIZES = array(
    'micro_thumb' => '16x16',
    'small_thumb' => '32x32',
    'normal_thumb' => '64x64',
    'medium_thumb' => '153x153',
    'profile_thumb' => '300x300',
    'big_thumb' => '225x225'
);
