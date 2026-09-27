-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 25, 2026 at 01:40 PM
-- Server version: 10.11.19-MariaDB-cll-lve
-- PHP Version: 8.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ujvepwwv_PRINTEASE_DB`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

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

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `module`, `target_type`, `target_id`, `old_value`, `new_value`, `ip_address`, `user_agent`, `created_at`) VALUES
(743, 87, 'Updated customer profile', 'Customer Profile', 'user', 87, '{\"full_name\":\"John dens\",\"phone_number\":null,\"address\":null,\"account_status\":\"incomplete\",\"has_profile_picture\":false,\"has_valid_id_front\":false,\"has_valid_id_back\":false}', '{\"full_name\":\"John dens\",\"phone_number\":\"09286262722\",\"address\":\"Gandara city\",\"account_status\":\"incomplete\",\"changed_fields\":[\"phone_number\",\"address\"],\"profile_picture_updated\":false,\"valid_id_front_updated\":false,\"valid_id_back_updated\":false}', '120.28.199.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Mobile Safari/537.36', '2026-09-22 06:43:40'),
(744, 73, 'Updated user #87 to rejected', 'User Management', 'user', 87, '{\"account_status\":\"incomplete\"}', '{\"account_status\":\"rejected\",\"role\":\"customer\",\"email\":\"chandennis96@gmail.com\",\"full_name\":\"John dens\"}', '180.191.100.19', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-25 04:53:31'),
(745, 89, 'Saved shop profile (pending verification)', 'Shop Profile', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0', '2026-09-25 05:04:21'),
(746, 73, 'Updated user #88 to verified', 'User Management', 'user', 88, '{\"account_status\":\"incomplete\"}', '{\"account_status\":\"verified\",\"role\":\"customer\",\"email\":\"alvarezdareen776@gmail.com\",\"full_name\":\"Alvarez, Dareen C\"}', '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-25 05:15:43'),
(747, 73, 'Updated permit #27 (shop: TESTING) to verified', 'Permit Management', 'shop', 27, '{\"permit_status\":\"pending\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"TESTING\",\"owner_id\":89}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-09-25 05:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `customer_favorite_shops`
--

CREATE TABLE `customer_favorite_shops` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `geocode_cache`
--

CREATE TABLE `geocode_cache` (
  `id` int(11) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `geocode_cache`
--

INSERT INTO `geocode_cache` (`id`, `lat`, `lng`, `address`, `created_at`) VALUES
(11, 12.0660892, 124.5888727, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 04:54:46'),
(12, 12.0660964, 124.5888733, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 04:55:41'),
(13, 12.0660853, 124.5888801, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 04:57:14'),
(14, 12.0660882, 124.5888756, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 04:58:41'),
(15, 12.0660879, 124.5888731, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 04:59:12'),
(16, 12.0664803, 124.5890379, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 05:03:35'),
(17, 12.0664730, 124.5890680, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 05:05:47'),
(18, 12.0660850, 124.5888826, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 05:27:04'),
(19, 12.0660810, 124.5888817, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 05:28:11'),
(20, 12.0660845, 124.5888800, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-09-25 05:34:36');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` datetime DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

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

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `title`, `message`, `target_url`, `metadata_json`, `is_read`, `read_at`, `created_at`) VALUES
(783, 73, 'account_submitted', 'New customer registered: John dens', 'chandennis96@gmail.com has signed up as a customer. Review their account.', 'https://printease.org/frontend/user/superadmin/manage_users.php', '{\"user_id\":87,\"role\":\"customer\",\"stage\":\"registered\"}', 1, '2026-09-25 07:13:37', '2026-09-22 06:42:23'),
(784, 87, 'account_status', 'Account status updated', 'Your account has been set to Rejected.', 'https://printease.org/frontend/user/customer/profile.php', '{\"status\":\"rejected\",\"role\":\"customer\"}', 0, NULL, '2026-09-25 04:53:31'),
(785, 73, 'account_submitted', 'New customer registered: Alvarez, Dareen C', 'alvarezdareen776@gmail.com has signed up as a customer. Review their account.', 'https://printease.org/frontend/user/superadmin/manage_users.php', '{\"user_id\":88,\"role\":\"customer\",\"stage\":\"registered\"}', 1, '2026-09-25 13:11:24', '2026-09-25 04:54:28'),
(786, 73, 'permit_submitted', 'New shop owner registered: Alvarez', 'alvarezdareen667@gmail.com has signed up as a print shop owner. They still need to set up their shop.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php', '{\"user_id\":89,\"role\":\"shop_owner\",\"stage\":\"registered\"}', 1, '2026-09-25 13:11:24', '2026-09-25 05:03:03'),
(787, 73, 'permit_submitted', 'Permit verification submitted: TESTING', '\"TESTING\" submitted its business permit for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php?status=pending', '{\"shop_id\":27,\"owner_id\":89}', 1, '2026-09-25 13:11:03', '2026-09-25 05:04:21'),
(788, 73, 'payment_settings_submitted', 'Payment details updated: TESTING', 'GCash QR Code & GCash Merchant Link for \"TESTING\" is ready for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php#payment-settings-review', '{\"shop_id\":27,\"owner_id\":89,\"channels\":[\"GCash QR Code\",\"GCash Merchant Link\"]}', 1, '2026-09-25 13:11:24', '2026-09-25 05:04:21'),
(789, 88, 'account_status', 'Account status updated', 'Your account has been set to Active.', 'https://printease.org/frontend/user/customer/profile.php', '{\"status\":\"verified\",\"role\":\"customer\"}', 1, '2026-09-25 13:15:50', '2026-09-25 05:15:43'),
(790, 89, 'permit_status', 'Permit status updated', 'Your business permit for \"TESTING\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":27,\"status\":\"verified\"}', 1, '2026-09-25 13:38:36', '2026-09-25 05:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `print_shops`
--

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

--
-- Dumping data for table `print_shops`
--

INSERT INTO `print_shops` (`shop_id`, `owner_id`, `shop_name`, `shop_address`, `display_address`, `landmark`, `latitude`, `longitude`, `business_permit_file`, `shop_logo`, `shop_status`, `created_at`, `contact_number`, `gcash_name`, `gcash_number`, `gcash_qr_file`, `merchant_payment_link`, `merchant_payment_label`, `permit_status`, `weekday_open_time`, `weekday_close_time`, `weekend_open_time`, `weekend_close_time`) VALUES
(27, 89, 'TESTING', 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', 'Magsaysay Blvd', 'Near Christ The King', 12.06648030, 124.58903789, '1790312661_288c89be9fb62af44400d53514ed9e55.jpg', '1790312661_ef423b12c3d4b244b72cd189df30c894.jfif', 'available', '2026-09-25 05:04:21', NULL, '', '', '1790312661_48464b8a5f3799d7a24b6074341c3d89.png', NULL, 'GCash Merchant Link', 'verified', '15:04:00', '13:08:00', '18:04:00', '16:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `rate_limit_events`
--

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

--
-- Dumping data for table `rate_limit_events`
--

INSERT INTO `rate_limit_events` (`id`, `action`, `identifier`, `ip_address`, `attempt_count`, `window_started_at`, `last_attempt_at`, `blocked_until`) VALUES
(390, 'mark_notification_read', 'mark_notification_read:73', '180.191.100.19', 1, '2026-09-24 23:13:37', '2026-09-24 23:13:37', NULL),
(391, 'user_status', 'user_status:73', '180.191.100.19', 2, '2026-09-25 04:53:31', '2026-09-25 05:15:43', NULL),
(392, 'save_customer_profile', 'save_customer_profile:88', '222.127.50.180', 2, '2026-09-25 04:55:16', '2026-09-25 04:55:53', NULL),
(393, 'save_customer_profile', 'save_customer_profile:88', '180.191.100.19', 5, '2026-09-25 04:58:27', '2026-09-25 05:06:55', NULL),
(394, 'save_shop_profile', 'save_shop_profile:89', '180.190.200.100', 1, '2026-09-25 05:04:21', '2026-09-25 05:04:21', NULL),
(395, 'mark_notification_read', 'mark_notification_read:73', '222.127.50.180', 2, '2026-09-25 05:11:03', '2026-09-25 05:11:24', NULL),
(396, 'mark_notification_read', 'mark_notification_read:88', '180.190.200.100', 2, '2026-09-25 05:15:50', '2026-09-25 05:15:50', NULL),
(397, 'save_customer_profile', 'save_customer_profile:88', '58.69.228.103', 2, '2026-09-25 05:27:20', '2026-09-25 05:28:34', NULL),
(398, 'save_customer_profile', 'save_customer_profile:88', '180.190.200.100', 1, '2026-09-25 05:34:52', '2026-09-25 05:34:52', NULL),
(399, 'permit_status', 'permit_status:73', '180.190.200.100', 1, '2026-09-25 05:38:31', '2026-09-25 05:38:31', NULL),
(400, 'mark_notification_read', 'mark_notification_read:89', '180.190.200.100', 1, '2026-09-25 05:38:36', '2026-09-25 05:38:36', NULL),
(401, 'login_email_ip', 'bustergrave90@gmail.com', '131.226.108.213', 1, '2026-09-25 05:39:58', '2026-09-25 05:39:58', NULL),
(402, 'login_ip', 'all', '131.226.108.213', 1, '2026-09-25 05:39:58', '2026-09-25 05:39:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shop_custom_services`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `shop_payment_accounts`
--

CREATE TABLE `shop_payment_accounts` (
  `account_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `gcash_name` varchar(100) NOT NULL,
  `gcash_number` varchar(20) NOT NULL,
  `gcash_qr_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shop_payment_channels`
--

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

--
-- Dumping data for table `shop_payment_channels`
--

INSERT INTO `shop_payment_channels` (`id`, `shop_id`, `channel`, `gcash_account_name`, `gcash_number`, `gcash_qr_code`, `merchant_link`, `instructions`, `approval_status`, `approved_by`, `approved_at`, `rejected_reason`, `is_active`, `created_at`, `updated_at`) VALUES
(89, 27, 'gcash_qr', '', '', '1790312661_48464b8a5f3799d7a24b6074341c3d89.png', NULL, 'Pay the exact print request total using this GCash account, then upload your reference number and payment screenshot.', 'pending', NULL, NULL, NULL, 1, '2026-09-25 05:04:21', '2026-09-25 05:04:21'),
(90, 27, 'gcash_merchant_link', NULL, NULL, NULL, 'https://gcash.com', 'Pay the exact print request total using this GCash account, then upload your reference number and payment screenshot.', 'pending', NULL, NULL, NULL, 1, '2026-09-25 05:04:21', '2026-09-25 05:04:21');

-- --------------------------------------------------------

--
-- Table structure for table `shop_payment_settings`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `shop_price_records`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `shop_services`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `shop_service_pricing`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `shop_service_types`
--

CREATE TABLE `shop_service_types` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `service_offered` tinyint(1) NOT NULL DEFAULT 1,
  `online_available` tinyint(1) NOT NULL DEFAULT 1,
  `customer_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shop_service_types`
--

INSERT INTO `shop_service_types` (`id`, `shop_id`, `service_type`, `service_offered`, `online_available`, `customer_note`, `created_at`) VALUES
(631, 27, 'Lamination', 1, 1, NULL, '2026-09-25 05:04:21'),
(632, 27, 'Photo Printing', 1, 1, NULL, '2026-09-25 05:04:21'),
(633, 27, 'Tarpaulin Printing', 1, 1, NULL, '2026-09-25 05:04:21'),
(634, 27, 'ID Printing', 1, 1, NULL, '2026-09-25 05:04:21'),
(635, 27, 'Invitation / Card Printing', 1, 1, NULL, '2026-09-25 05:04:21'),
(636, 27, 'Photocopy', 1, 0, 'This service requires physical documents. Online request is unavailable.', '2026-09-25 05:04:21'),
(637, 27, 'Binding', 1, 0, 'Physical document submission is required. Please visit the shop.', '2026-09-25 05:04:21'),
(638, 27, 'Scanning', 1, 0, 'Original documents are required. Online request is unavailable.', '2026-09-25 05:04:21'),
(639, 27, 'Document Printing', 1, 1, NULL, '2026-09-25 05:04:21');

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `file_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cloudinary_public_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

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

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone_number`, `role`, `valid_id_file`, `valid_id_front_file`, `valid_id_back_file`, `account_status`, `created_at`, `address`, `profile_picture`, `latitude`, `longitude`, `auth_version`, `otp_code`, `otp_expires`) VALUES
(73, 'SUPERADMIN', 'superadmin@printease.com', '$2a$12$hauFZLHhZwaDIj364u0dY.XObYAHYPxfm11iUh1NAWRLJ9pu.1Zv.', NULL, 'super_admin', NULL, NULL, NULL, 'verified', '2026-08-16 06:14:43', NULL, NULL, NULL, NULL, 1, NULL, NULL),
(87, 'John dens', 'chandennis96@gmail.com', '$2y$10$6AEivwpbcMfur5rGKrKGfeGAxmHMm5vmgZ6Jv9HEepOC/f82rQdDm', '09286262722', 'customer', NULL, NULL, NULL, 'rejected', '2026-09-22 06:42:23', 'Gandara city', NULL, NULL, NULL, 1, NULL, NULL),
(88, 'Alvarez, Dareen C', 'alvarezdareen776@gmail.com', '$2y$10$d2Ds0czE87uIy0uAu9pKeuB0PVe8485Q/tURE99XnJ.5Aw39G6oXq', NULL, 'customer', NULL, NULL, NULL, 'verified', '2026-09-25 04:54:28', NULL, NULL, NULL, NULL, 1, NULL, NULL),
(89, 'Alvarez', 'alvarezdareen667@gmail.com', '$2y$10$qy9H5DkkPOC4Wn8LHHfpCeYAcwX8vue2JfOQBrznWwDhPiPrL/3xu', NULL, 'shop_owner', NULL, NULL, NULL, 'verified', '2026-09-25 05:03:03', NULL, NULL, NULL, NULL, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_remember_tokens`
--

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

--
-- Dumping data for table `user_remember_tokens`
--

INSERT INTO `user_remember_tokens` (`remember_token_id`, `user_id`, `selector`, `validator_hash`, `auth_provider`, `remember_duration_days`, `expires_at`, `last_used_at`, `created_at`) VALUES
(301, 73, '8f1c187673994ae3fba55314540f28b7b6dc', 'a5c687d474c4e32c9b16d471426a8f1b597784aae9628f2c1cc09c162b9a5a01', 'password', 180, '2027-03-24 07:10:15', '2026-09-25 07:10:15', '2026-09-25 07:10:15'),
(302, 88, '4a6ce3a857666ab81db64301119907251608', '5133aa634a80f174dee968c6d9c038409c6e646dd4595f113bcebc0761a6c788', 'google', 180, '2027-03-24 12:54:28', '2026-09-25 12:54:28', '2026-09-25 12:54:28'),
(303, 89, '4f4c9a26c2d47932e94169a93e67e9144bd5', '23f3ceced16bceaddb5744f4ce021cf43b8ec80e40ea57e28df64c6b75af31d2', 'google', 180, '2027-03-24 13:03:03', '2026-09-25 13:03:03', '2026-09-25 13:03:03'),
(304, 88, '942c0bf871f34e6ddd2ac2f559ccb4650317', '0baf70b5e9796ebf63f00d49191482dbf79c4c150ed7c2e58913e017b73c2fdd', 'google', 180, '2027-03-24 13:05:29', '2026-09-25 13:05:29', '2026-09-25 13:05:29');

-- --------------------------------------------------------

--
-- Table structure for table `user_social_accounts`
--

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
-- Dumping data for table `user_social_accounts`
--

INSERT INTO `user_social_accounts` (`social_account_id`, `user_id`, `provider`, `provider_user_id`, `provider_email`, `created_at`, `updated_at`) VALUES
(28, 88, 'google', '117970612336612457498', 'alvarezdareen776@gmail.com', '2026-09-25 04:54:28', '2026-09-25 04:54:28'),
(29, 89, 'google', '111678072395998696104', 'alvarezdareen667@gmail.com', '2026-09-25 05:03:03', '2026-09-25 05:03:03');

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=748;

--
-- AUTO_INCREMENT for table `customer_favorite_shops`
--
ALTER TABLE `customer_favorite_shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `geocode_cache`
--
ALTER TABLE `geocode_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=791;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `print_shops`
--
ALTER TABLE `print_shops`
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `rate_limit_events`
--
ALTER TABLE `rate_limit_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=403;

--
-- AUTO_INCREMENT for table `shop_custom_services`
--
ALTER TABLE `shop_custom_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shop_payment_accounts`
--
ALTER TABLE `shop_payment_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shop_payment_channels`
--
ALTER TABLE `shop_payment_channels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `shop_payment_settings`
--
ALTER TABLE `shop_payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `shop_price_records`
--
ALTER TABLE `shop_price_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `shop_services`
--
ALTER TABLE `shop_services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `shop_service_pricing`
--
ALTER TABLE `shop_service_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `shop_service_types`
--
ALTER TABLE `shop_service_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=640;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  MODIFY `remember_token_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=305;

--
-- AUTO_INCREMENT for table `user_social_accounts`
--
ALTER TABLE `user_social_accounts`
  MODIFY `social_account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `customer_favorite_shops`
--
ALTER TABLE `customer_favorite_shops`
  ADD CONSTRAINT `fk_favorite_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_favorite_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_custom_service` FOREIGN KEY (`custom_service_id`) REFERENCES `shop_custom_services` (`id`),
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_orders_service` FOREIGN KEY (`service_id`) REFERENCES `shop_services` (`service_id`),
  ADD CONSTRAINT `fk_orders_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_active_lock_order` FOREIGN KEY (`active_lock_order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `fk_payments_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `print_shops`
--
ALTER TABLE `print_shops`
  ADD CONSTRAINT `fk_print_shop_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `shop_custom_services`
--
ALTER TABLE `shop_custom_services`
  ADD CONSTRAINT `fk_shop_custom_services_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `shop_payment_accounts`
--
ALTER TABLE `shop_payment_accounts`
  ADD CONSTRAINT `fk_shop_payment_accounts_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `shop_payment_channels`
--
ALTER TABLE `shop_payment_channels`
  ADD CONSTRAINT `fk_shop_payment_channels_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`) ON DELETE CASCADE;

--
-- Constraints for table `shop_payment_settings`
--
ALTER TABLE `shop_payment_settings`
  ADD CONSTRAINT `fk_shop_payment_settings_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `shop_price_records`
--
ALTER TABLE `shop_price_records`
  ADD CONSTRAINT `fk_shop_price_records_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `shop_services`
--
ALTER TABLE `shop_services`
  ADD CONSTRAINT `fk_shop_services_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `shop_service_pricing`
--
ALTER TABLE `shop_service_pricing`
  ADD CONSTRAINT `fk_shop_service_pricing_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `shop_service_types`
--
ALTER TABLE `shop_service_types`
  ADD CONSTRAINT `fk_shop_service_types_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`);

--
-- Constraints for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD CONSTRAINT `fk_uploaded_files_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- Constraints for table `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  ADD CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_social_accounts`
--
ALTER TABLE `user_social_accounts`
  ADD CONSTRAINT `fk_social_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
