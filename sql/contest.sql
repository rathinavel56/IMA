-- Adminer 4.2.5 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `advertisements`;
CREATE TABLE `advertisements` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` bigint(20) NOT NULL,
  `name` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(510) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `page_number` varchar(510) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `price` varchar(510) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `description` longtext COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `is_approved` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `class` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `foreign_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `product_detail_id` bigint(20) DEFAULT NULL,
  `filename` longtext COLLATE utf8_unicode_ci NOT NULL,
  `dir` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `mimetype` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `filesize` bigint(20) DEFAULT NULL,
  `height` bigint(20) DEFAULT NULL,
  `width` bigint(20) DEFAULT NULL,
  `thumb` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `location` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `caption` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_admin_approval` int(2) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


SET NAMES utf8mb4;

DROP TABLE IF EXISTS `cards`;
CREATE TABLE `cards` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `name` longtext NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `card_number` longtext NOT NULL,
  `card_display_number` longtext NOT NULL,
  `expiry_date` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `parent_id` bigint(20) DEFAULT 0,
  `user_address_id` bigint(20) DEFAULT NULL,
  `product_detail_id` bigint(20) NOT NULL,
  `product_size_id` bigint(20) NOT NULL,
  `coupon_id` bigint(20) DEFAULT NULL,
  `price` double DEFAULT 0,
  `quantity` int(11) NOT NULL,
  `is_purchase` tinyint(1) DEFAULT 0,
  `pay_key` longtext DEFAULT NULL,
  `pay_status` longtext DEFAULT NULL,
  `addressline1` longtext DEFAULT NULL,
  `addressline2` longtext DEFAULT NULL,
  `city` longtext DEFAULT NULL,
  `state` longtext DEFAULT NULL,
  `country` longtext DEFAULT NULL,
  `zipcode` longtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `product_detail_id` (`product_detail_id`),
  KEY `user_address_id` (`user_address_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `name` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `url` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `first_name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(510) COLLATE utf8_unicode_ci DEFAULT NULL,
  `message` longtext COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contacts_ip_id` (`ip_id`),
  KEY `contacts_user_id` (`user_id`),
  CONSTRAINT `contacts_ip_id_fkey` FOREIGN KEY (`ip_id`) REFERENCES `ips` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `contacts_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `contests`;
CREATE TABLE `contests` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `from` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `reply_to` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `text_email_content` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `html_email_content` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `notification_content` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `email_variables` varchar(2000) COLLATE utf8_unicode_ci NOT NULL,
  `is_html` tinyint(4) NOT NULL,
  `is_notify` tinyint(4) DEFAULT NULL,
  `display_name` varchar(300) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_templates_name` (`name`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `ips`;
CREATE TABLE `ips` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `ip` varchar(510) COLLATE utf8_unicode_ci DEFAULT NULL,
  `host` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `city_id` bigint(20) DEFAULT NULL,
  `state_id` bigint(20) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `timezone_id` bigint(20) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ips_city_id` (`city_id`),
  KEY `ips_country_id` (`country_id`),
  KEY `ips_state_id` (`state_id`),
  KEY `ips_timezone_id` (`timezone_id`),
  CONSTRAINT `ips_city_id_fkey` FOREIGN KEY (`city_id`) REFERENCES `__cities` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `ips_country_id_fkey` FOREIGN KEY (`country_id`) REFERENCES `__countries` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `ips_state_id_fkey` FOREIGN KEY (`state_id`) REFERENCES `__states` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `ips_timezone_id_fkey` FOREIGN KEY (`timezone_id`) REFERENCES `timezones` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `oauth_access_tokens`;
CREATE TABLE `oauth_access_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `access_token` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `scope` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_client_id` (`client_id`),
  KEY `oauth_access_tokens_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `oauth_authorization_codes`;
CREATE TABLE `oauth_authorization_codes` (
  `authorization_code` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `redirect_uri` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `scope` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  KEY `oauth_authorization_codes_client_id` (`client_id`),
  KEY `oauth_authorization_codes_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `oauth_clients`;
CREATE TABLE `oauth_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `client_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `client_secret` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `redirect_uri` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `grant_types` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `scope` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `client_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `logo_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tos_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `policy_url` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_client_id` (`client_id`),
  KEY `oauth_clients_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `oauth_refresh_tokens`;
CREATE TABLE `oauth_refresh_tokens` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `refresh_token` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `client_id` varchar(80) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `scope` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_client_id` (`client_id`),
  KEY `oauth_refresh_tokens_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `oauth_scopes`;
CREATE TABLE `oauth_scopes` (
  `scope` longtext COLLATE utf8_unicode_ci NOT NULL,
  `is_default` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `title` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(510) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description_meta_tag` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `url` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `slug` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pages_title` (`title`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE `payment_gateways` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `name` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `slug` varchar(510) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `is_test_mode` tinyint(4) NOT NULL,
  `is_active` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_gateways_name` (`name`(255)),
  KEY `payment_gateways_slug` (`slug`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `payment_gateway_settings`;
CREATE TABLE `payment_gateway_settings` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `payment_gateway_id` int(11) NOT NULL,
  `name` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `options` longtext COLLATE utf8_unicode_ci NOT NULL,
  `test_mode_value` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `live_mode_value` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `label` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_gateway_settings_payment_gateway_id` (`payment_gateway_id`),
  CONSTRAINT `payment_gateway_settings_payment_gateway_id_fkey` FOREIGN KEY (`payment_gateway_id`) REFERENCES `payment_gateways` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(1000) NOT NULL DEFAULT '',
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `product_colors`;
CREATE TABLE `product_colors` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` bigint(20) NOT NULL,
  `color` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `product_details`;
CREATE TABLE `product_details` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` bigint(20) NOT NULL,
  `product_color_id` bigint(20) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `product_sizes`;
CREATE TABLE `product_sizes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_detail_id` bigint(20) NOT NULL,
  `size_id` bigint(20) NOT NULL,
  `quantity` bigint(20) NOT NULL,
  `price` double NOT NULL,
  `discount_percentage` bigint(20) DEFAULT NULL,
  `coupon_code` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `providers`;
CREATE TABLE `providers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `slug` varchar(265) COLLATE utf8_unicode_ci NOT NULL,
  `secret_key` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `api_key` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `icon_class` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `button_class` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `position` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `providers_name` (`name`),
  KEY `providers_slug` (`slug`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `provider_users`;
CREATE TABLE `provider_users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `provider_id` bigint(20) NOT NULL,
  `foreign_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `access_token` longtext COLLATE utf8_unicode_ci NOT NULL,
  `access_token_secret` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_connected` tinyint(4) NOT NULL DEFAULT 1,
  `profile_picture_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `provider_users_foreign_id` (`foreign_id`),
  KEY `provider_users_provider_id` (`provider_id`),
  KEY `provider_users_user_id` (`user_id`),
  CONSTRAINT `provider_users_provider_id_fkey` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `provider_users_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `setting_category_id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` varchar(8) COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `position` int(11) NOT NULL,
  `option_values` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_web` tinyint(1) NOT NULL DEFAULT 1,
  `is_mobile` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `settings_setting_category_id` (`setting_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `setting_categories`;
CREATE TABLE `setting_categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `name` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `setting_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `sizes`;
CREATE TABLE `sizes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `price` varchar(510) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `days` int(11) NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `timezones`;
CREATE TABLE `timezones` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `code` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `gmt_offset` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `dst_offset` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `raw_offset` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `hasdst` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `timezones_name` (`name`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `to_user_id` bigint(20) DEFAULT NULL,
  `foreign_id` bigint(20) DEFAULT NULL,
  `class` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `transaction_type` bigint(20) NOT NULL,
  `payment_gateway_id` bigint(20) DEFAULT NULL,
  `amount` double NOT NULL,
  `site_revenue_from_freelancer` double DEFAULT 0,
  `coupon_id` smallint(6) DEFAULT NULL,
  `site_revenue_from_employer` double NOT NULL DEFAULT 0,
  `is_sanbox` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `transactions_payment_gateway_id` (`payment_gateway_id`),
  KEY `transactions_to_user_id` (`to_user_id`),
  KEY `transactions_user_id` (`user_id`),
  CONSTRAINT `transactions_to_user_id_fkey` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `transactions_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `upload_service_settings`;
CREATE TABLE `upload_service_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `upload_service_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `upload_service_settings_upload_service_id_idx` (`upload_service_id`),
  CONSTRAINT `upload_service_settings_upload_service_id_fkey` FOREIGN KEY (`upload_service_id`) REFERENCES `__upload_services` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `company_id` bigint(20) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `username` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `mobile` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_login_count` bigint(20) NOT NULL DEFAULT 0,
  `available_wallet_amount` double DEFAULT 0,
  `ip_id` bigint(20) DEFAULT NULL,
  `last_login_ip_id` bigint(20) DEFAULT NULL,
  `last_logged_in_time` datetime DEFAULT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 0,
  `first_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_email_confirmed` tinyint(4) NOT NULL DEFAULT 0,
  `view_count` bigint(20) NOT NULL DEFAULT 0,
  `flag_count` bigint(20) NOT NULL DEFAULT 0,
  `total_votes` tinyint(4) NOT NULL DEFAULT 0,
  `votes` bigint(20) DEFAULT 0,
  `instagram_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tiktok_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `youtube_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `twitter_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `facebook_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `available_credit_count` bigint(20) DEFAULT NULL,
  `instant_vote_pay_key` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `vote_pay_key` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `vote_to_purchase` int(11) DEFAULT 0,
  `instant_vote_to_purchase` int(11) DEFAULT NULL,
  `subscription_pay_key` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `fund_pay_key` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `donated` double DEFAULT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `paypal_email` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_paypal_connect` tinyint(1) DEFAULT 0,
  `is_stripe_connect` tinyint(1) DEFAULT 0,
  `subscription_end_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `users_email` (`email`(255)),
  KEY `users_ip_id` (`ip_id`),
  KEY `users_role_id` (`role_id`),
  KEY `users_username` (`username`(255)),
  KEY `users_last_login_ip_id_fkey` (`last_login_ip_id`),
  CONSTRAINT `users_ip_id_fkey` FOREIGN KEY (`ip_id`) REFERENCES `ips` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `users_last_login_ip_id_fkey` FOREIGN KEY (`last_login_ip_id`) REFERENCES `ips` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `user_address`;
CREATE TABLE `user_address` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` bigint(20) NOT NULL,
  `name` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `addressline1` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `addressline2` varchar(510) COLLATE utf8_unicode_ci DEFAULT NULL,
  `city` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `state` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `country` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `zipcode` varchar(510) COLLATE utf8_unicode_ci NOT NULL,
  `is_default` int(1) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `user_cash_withdrawals`;
CREATE TABLE `user_cash_withdrawals` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `withdrawal_status_id` bigint(20) NOT NULL DEFAULT 1,
  `amount` double NOT NULL,
  `remark` longtext COLLATE utf8_unicode_ci DEFAULT NULL,
  `money_transfer_account_id` bigint(20) DEFAULT NULL,
  `withdrawal_fee` double NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_cash_withdrawals_money_transfer_account_id` (`money_transfer_account_id`),
  KEY `user_cash_withdrawals_user_id` (`user_id`),
  KEY `user_cash_withdrawals_withdrawal_status_id` (`withdrawal_status_id`),
  CONSTRAINT `user_cash_withdrawals_money_transfer_account_id_fkey` FOREIGN KEY (`money_transfer_account_id`) REFERENCES `__money_transfer_accounts` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `user_cash_withdrawals_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `user_categories`;
CREATE TABLE `user_categories` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `category_id` bigint(20) NOT NULL,
  `votes` bigint(20) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;


DROP TABLE IF EXISTS `user_contests`;
CREATE TABLE `user_contests` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contest_id` bigint(20) NOT NULL,
  `instant_votes` bigint(20) DEFAULT 0,
  `user_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `user_logins`;
CREATE TABLE `user_logins` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `ip_id` bigint(20) DEFAULT NULL,
  `user_agent` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_logins_ip_id` (`ip_id`),
  KEY `user_logins_user_id` (`user_id`),
  CONSTRAINT `user_logins_ip_id_fkey` FOREIGN KEY (`ip_id`) REFERENCES `ips` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION,
  CONSTRAINT `user_logins_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


DROP TABLE IF EXISTS `vote_packages`;
CREATE TABLE `vote_packages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `name` longtext COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `price` double NOT NULL,
  `vote` int(11) NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `vote_packages` (`id`, `created_at`, `updated_at`, `name`, `price`, `vote`, `description`, `is_active`) VALUES
(1,	'2019-03-21 03:01:05',	'2019-03-21 03:01:05',	'Purchase 10 votes',	30,	30,	'Purchase 30 votes',	1),
(2,	'2019-03-21 03:01:05',	'2017-05-16 11:29:32',	'Platinum',	20,	20,	'Purchase 20 votes',	1),
(3,	'2019-03-21 03:01:05',	'2017-05-16 11:29:32',	'Sliver',	3,	3,	'Purchase 3 votes',	1);

-- 2020-03-30 19:48:35
