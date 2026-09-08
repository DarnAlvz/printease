-- PrintEase database schema (structure only)
-- Extracted from: backend/database/ujvepwwv_PRINTEASE_DB.sql
-- Data dumps and AUTO_INCREMENT counters removed; structure + indexes + constraints kept.

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `customer_favorite_shops` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `geocode_cache` (
  `id` int(11) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` datetime DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `message` text NOT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `metadata_json` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `order_code` varchar(30) DEFAULT NULL,
  `submit_token` varchar(32) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `paper_size` varchar(50) DEFAULT NULL,
  `paper_type` varchar(100) DEFAULT NULL,
  `print_type` varchar(50) DEFAULT NULL,
  `copies` int(11) DEFAULT 1,
  `customer_instruction` text DEFAULT NULL,
  `pickup_datetime` datetime DEFAULT NULL,
  `pickup_reminder_sent` tinyint(1) DEFAULT 0,
  `order_status` enum('pending','processing','ready_for_pickup','completed','cancelled') DEFAULT 'pending',
  `customer_deleted_at` timestamp NULL DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `page_count` int(11) DEFAULT 1,
  `service_category` varchar(50) DEFAULT 'document_printing' COMMENT 'document_printing or custom_service',
  `custom_service_id` int(11) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `active_lock_order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('gcash_direct','gcash_merchant_link') NOT NULL DEFAULT 'gcash_merchant_link',
  `reference_number` varchar(100) DEFAULT NULL,
  `ocr_reference_number` varchar(100) DEFAULT NULL,
  `ocr_payment_date` date DEFAULT NULL,
  `payment_reference_match` enum('unchecked','matched','not_matched','not_detected','detected','partial') DEFAULT 'unchecked',
  `proof_of_payment_file` varchar(255) NOT NULL,
  `payment_status` enum('pending','paid','unpaid') DEFAULT 'pending',
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `print_shops` (
  `shop_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `shop_address` text NOT NULL,
  `display_address` varchar(150) DEFAULT NULL,
  `landmark` varchar(150) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `business_permit_file` varchar(255) NOT NULL,
  `shop_logo` varchar(255) DEFAULT NULL,
  `shop_status` enum('available','busy','not_accepting') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contact_number` varchar(20) DEFAULT NULL,
  `gcash_name` varchar(150) DEFAULT NULL,
  `gcash_number` varchar(30) DEFAULT NULL,
  `gcash_qr_file` varchar(255) DEFAULT NULL,
  `merchant_payment_link` varchar(500) DEFAULT NULL,
  `merchant_payment_label` varchar(100) DEFAULT 'GCash Merchant Link',
  `permit_status` enum('pending','verified','rejected','disabled') NOT NULL DEFAULT 'pending',
  `weekday_open_time` time DEFAULT NULL,
  `weekday_close_time` time DEFAULT NULL,
  `weekend_open_time` time DEFAULT NULL,
  `weekend_close_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rate_limit_events` (
  `id` int(11) NOT NULL,
  `action` varchar(80) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `last_attempt_at` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `shop_custom_services` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `unit_label` varchar(50) DEFAULT 'per piece',
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `shop_payment_accounts` (
  `account_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `gcash_name` varchar(100) NOT NULL,
  `gcash_number` varchar(20) NOT NULL,
  `gcash_qr_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `shop_payment_channels` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `channel` enum('gcash_qr','gcash_merchant_link') NOT NULL,
  `gcash_account_name` varchar(150) DEFAULT NULL,
  `gcash_number` varchar(30) DEFAULT NULL,
  `gcash_qr_code` varchar(255) DEFAULT NULL,
  `merchant_link` varchar(500) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_reason` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shop_payment_settings` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `payment_method` enum('gcash') NOT NULL DEFAULT 'gcash',
  `merchant_link` varchar(500) DEFAULT NULL,
  `gcash_account_name` varchar(150) NOT NULL,
  `gcash_number` varchar(30) NOT NULL,
  `gcash_qr_code` varchar(255) DEFAULT NULL,
  `instructions` text NOT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `shop_price_records` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `size_name` varchar(150) NOT NULL,
  `variant` varchar(150) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `dimension_unit` varchar(20) DEFAULT NULL,
  `pricing_basis` varchar(50) NOT NULL,
  `min_quantity` int(11) DEFAULT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `legacy_source` enum('shop_services','shop_service_pricing') NOT NULL,
  `legacy_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `shop_services` (
  `service_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `paper_size` varchar(50) NOT NULL,
  `paper_type` varchar(100) NOT NULL,
  `print_type` varchar(50) NOT NULL,
  `price_per_page` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `shop_service_pricing` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `option_size` varchar(50) DEFAULT NULL,
  `option_label` varchar(150) NOT NULL,
  `variant` varchar(150) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `shop_service_types` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `service_offered` tinyint(1) NOT NULL DEFAULT 1,
  `online_available` tinyint(1) NOT NULL DEFAULT 1,
  `customer_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `uploaded_files` (
  `file_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cloudinary_public_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `role` enum('customer','shop_owner','super_admin') NOT NULL,
  `valid_id_file` varchar(255) DEFAULT NULL,
  `valid_id_front_file` varchar(255) DEFAULT NULL,
  `valid_id_back_file` varchar(255) DEFAULT NULL,
  `account_status` enum('incomplete','pending','verified','rejected','inactive') NOT NULL DEFAULT 'incomplete',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `auth_version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_remember_tokens` (
  `remember_token_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` varchar(64) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `auth_provider` varchar(20) NOT NULL DEFAULT 'password',
  `remember_duration_days` int(11) NOT NULL DEFAULT 7,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_social_accounts` (
  `social_account_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider` varchar(30) NOT NULL,
  `provider_user_id` varchar(191) NOT NULL,
  `provider_email` varchar(191) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes and constraints
--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_activity_logs_target` (`target_type`,`target_id`),
  ADD KEY `idx_activity_logs_module_created` (`module`,`created_at`),
  ADD KEY `idx_activity_logs_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `customer_favorite_shops`
--
ALTER TABLE `customer_favorite_shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_shop` (`customer_id`,`shop_id`),
  ADD KEY `idx_customer_favorites_customer` (`customer_id`),
  ADD KEY `idx_customer_favorites_shop` (`shop_id`);

--
-- Indexes for table `geocode_cache`
--
ALTER TABLE `geocode_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_coord` (`lat`,`lng`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user_unread_created` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_notifications_user_read_created` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_notifications_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `uq_orders_order_code` (`order_code`),
  ADD UNIQUE KEY `uq_orders_submit_token` (`submit_token`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `fk_orders_service` (`service_id`),
  ADD KEY `idx_orders_shop_created` (`shop_id`,`created_at`,`order_id`),
  ADD KEY `idx_orders_shop_status_created` (`shop_id`,`order_status`,`created_at`),
  ADD KEY `idx_orders_customer_created` (`customer_id`,`created_at`,`order_id`),
  ADD KEY `idx_orders_customer_status_created` (`customer_id`,`order_status`,`created_at`),
  ADD KEY `idx_orders_category` (`service_category`),
  ADD KEY `idx_orders_custom_service` (`custom_service_id`),
  ADD KEY `idx_orders_customer_privacy_status_created` (`customer_id`,`customer_deleted_at`,`order_status`,`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `uq_payments_active_lock_order_id` (`active_lock_order_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_payments_order_created` (`order_id`,`created_at`,`payment_id`),
  ADD KEY `idx_payments_customer_status` (`customer_id`,`payment_status`,`verification_status`),
  ADD KEY `idx_payments_status_created` (`verification_status`,`created_at`);

--
-- Indexes for table `print_shops`
--
ALTER TABLE `print_shops`
  ADD PRIMARY KEY (`shop_id`),
  ADD UNIQUE KEY `unique_owner_shop` (`owner_id`),
  ADD KEY `idx_print_shops_owner` (`owner_id`),
  ADD KEY `idx_print_shops_permit_status` (`permit_status`,`created_at`);

--
-- Indexes for table `rate_limit_events`
--
ALTER TABLE `rate_limit_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rate_limit_unique` (`action`,`identifier`,`ip_address`),
  ADD KEY `rate_limit_action_identifier` (`action`,`identifier`),
  ADD KEY `rate_limit_ip` (`ip_address`),
  ADD KEY `rate_limit_blocked_until` (`blocked_until`);

--
-- Indexes for table `shop_custom_services`
--
ALTER TABLE `shop_custom_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_custom_services_shop` (`shop_id`),
  ADD KEY `idx_custom_services_available` (`shop_id`,`is_available`);

--
-- Indexes for table `shop_payment_accounts`
--
ALTER TABLE `shop_payment_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `shop_payment_channels`
--
ALTER TABLE `shop_payment_channels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_payment_channel_shop_channel` (`shop_id`,`channel`),
  ADD KEY `idx_shop_payment_channels_approved_by` (`approved_by`);

--
-- Indexes for table `shop_payment_settings`
--
ALTER TABLE `shop_payment_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_payment_settings_shop` (`shop_id`);

--
-- Indexes for table `shop_price_records`
--
ALTER TABLE `shop_price_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_spr_legacy` (`shop_id`,`legacy_source`,`legacy_id`),
  ADD KEY `idx_spr_shop_service` (`shop_id`,`service_type`),
  ADD KEY `idx_spr_shop_available` (`shop_id`,`is_available`),
  ADD KEY `idx_spr_pricing_basis` (`pricing_basis`);

--
-- Indexes for table `shop_services`
--
ALTER TABLE `shop_services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `idx_shop_services_shop_available` (`shop_id`,`is_available`);

--
-- Indexes for table `shop_service_pricing`
--
ALTER TABLE `shop_service_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ssp_size_variant` (`shop_id`,`service_type`,`option_label`,`variant`),
  ADD KEY `idx_ssp_shop_type` (`shop_id`,`service_type`),
  ADD KEY `idx_ssp_shop_available` (`shop_id`,`is_available`);

--
-- Indexes for table `shop_service_types`
--
ALTER TABLE `shop_service_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_shop_service_type` (`shop_id`,`service_type`);

--
-- Indexes for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_uploaded_files_order_file` (`order_id`,`file_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  ADD PRIMARY KEY (`remember_token_id`),
  ADD UNIQUE KEY `unique_remember_selector` (`selector`),
  ADD KEY `remember_user_expiry` (`user_id`,`expires_at`);

--
-- Indexes for table `user_social_accounts`
--
ALTER TABLE `user_social_accounts`
  ADD PRIMARY KEY (`social_account_id`),
  ADD UNIQUE KEY `unique_provider_user` (`provider`,`provider_user_id`),
  ADD UNIQUE KEY `unique_user_provider` (`user_id`,`provider`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `shop_payment_channels`
--
ALTER TABLE `shop_payment_channels`
  ADD CONSTRAINT `fk_shop_payment_channels_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`) ON DELETE CASCADE;

