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
$settings = Models\Setting::all();
foreach ($settings as $setting) {
    define($setting->name, $setting->value);
}
$upload_service_settings = Models\UploadServiceSetting::all();
foreach ($upload_service_settings as $upload_service_setting) {
    define($upload_service_setting->name, $upload_service_setting->value);
}
