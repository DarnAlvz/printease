-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 07, 2026 at 12:18 PM
-- Server version: 10.11.19-MariaDB-cll-lve
-- PHP Version: 8.4.24

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
(654, 78, 'Saved shop profile (pending verification)', 'Shop Profile', NULL, NULL, NULL, NULL, '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 02:49:08'),
(655, 81, 'Saved shop profile (pending verification)', 'Shop Profile', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 02:59:40'),
(656, 73, 'Updated permit #25 (shop: KINGJAM) to verified', 'Permit Management', 'shop', 25, '{\"permit_status\":\"pending\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:00:29'),
(657, 73, 'Updated permit #24 (shop: PRINTAHAN NI DAREEN) to verified', 'Permit Management', 'shop', 24, '{\"permit_status\":\"pending\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"PRINTAHAN NI DAREEN\",\"owner_id\":78}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:00:32'),
(658, 73, 'Updated GCash QR Code payment setting #81 (shop: KINGJAM) to approved', 'Payment Settings', 'payment_channel', 81, '{\"channel\":\"gcash_qr\",\"approval_status\":\"pending\",\"is_active\":1,\"approved_by\":null,\"approved_at\":null,\"rejected_reason\":null}', '{\"channel\":\"gcash_qr\",\"approval_status\":\"approved\",\"is_active\":1,\"approved_by\":73,\"approved_at\":\"2026-08-28 03:00:36\",\"rejected_reason\":null,\"shop_id\":25,\"shop_name\":\"KINGJAM\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:00:36'),
(659, 73, 'Updated GCash Merchant Link payment setting #80 (shop: PRINTAHAN NI DAREEN) to approved', 'Payment Settings', 'payment_channel', 80, '{\"channel\":\"gcash_merchant_link\",\"approval_status\":\"pending\",\"is_active\":1,\"approved_by\":null,\"approved_at\":null,\"rejected_reason\":null}', '{\"channel\":\"gcash_merchant_link\",\"approval_status\":\"approved\",\"is_active\":1,\"approved_by\":73,\"approved_at\":\"2026-08-28 03:00:42\",\"rejected_reason\":null,\"shop_id\":24,\"shop_name\":\"PRINTAHAN NI DAREEN\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:00:42'),
(660, 81, 'Added service pricing: Lamination - A5 (3.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 03:01:49'),
(661, 81, 'Added service pricing: Photo Printing - Short - Bond Paper - Black & White (5.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 03:01:55'),
(662, 81, 'Service pricing disabled (ID: 38)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 03:14:23'),
(663, 81, 'Service pricing enabled (ID: 38)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 03:14:25'),
(664, 73, 'Updated permit #24 (shop: PRINTAHAN NI DAREEN) to disabled', 'Permit Management', 'shop', 24, '{\"permit_status\":\"verified\"}', '{\"permit_status\":\"disabled\",\"shop_name\":\"PRINTAHAN NI DAREEN\",\"owner_id\":78}', '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:15:09'),
(665, 73, 'Updated permit #24 (shop: PRINTAHAN NI DAREEN) to verified', 'Permit Management', 'shop', 24, '{\"permit_status\":\"disabled\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"PRINTAHAN NI DAREEN\",\"owner_id\":78}', '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:15:11'),
(666, 73, 'Updated user #79 to verified', 'User Management', 'user', 79, '{\"account_status\":\"incomplete\"}', '{\"account_status\":\"verified\",\"role\":\"customer\",\"email\":\"alvarezdareen667@gmail.com\",\"full_name\":\"Dareen Casaljay Alvarez\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:15:53'),
(667, 73, 'Updated user #79 to inactive', 'User Management', 'user', 79, '{\"account_status\":\"verified\"}', '{\"account_status\":\"inactive\",\"role\":\"customer\",\"email\":\"alvarezdareen667@gmail.com\",\"full_name\":\"Dareen Casaljay Alvarez\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:15:55'),
(668, 73, 'Updated user #79 to verified', 'User Management', 'user', 79, '{\"account_status\":\"inactive\"}', '{\"account_status\":\"verified\",\"role\":\"customer\",\"email\":\"alvarezdareen667@gmail.com\",\"full_name\":\"Dareen Casaljay Alvarez\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:15:56'),
(669, 73, 'Updated user #79 to inactive', 'User Management', 'user', 79, '{\"account_status\":\"verified\"}', '{\"account_status\":\"inactive\",\"role\":\"customer\",\"email\":\"alvarezdareen667@gmail.com\",\"full_name\":\"Dareen Casaljay Alvarez\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:15:58'),
(670, 73, 'Updated user #79 to verified', 'User Management', 'user', 79, '{\"account_status\":\"inactive\"}', '{\"account_status\":\"verified\",\"role\":\"customer\",\"email\":\"alvarezdareen667@gmail.com\",\"full_name\":\"Dareen Casaljay Alvarez\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 03:16:01'),
(671, 79, 'Updated customer profile', 'Customer Profile', 'user', 79, '{\"full_name\":\"Dareen Casaljay Alvarez\",\"phone_number\":null,\"address\":null,\"account_status\":\"verified\",\"has_profile_picture\":false,\"has_valid_id\":false}', '{\"full_name\":\"Dareen Casaljay Alvarez\",\"phone_number\":\"09368472994\",\"address\":\"Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines\",\"account_status\":\"verified\",\"changed_fields\":[\"phone_number\",\"address\",\"profile_picture\",\"valid_id_file\"],\"profile_picture_updated\":true,\"valid_id_updated\":true}', '222.127.50.180', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36', '2026-08-28 03:25:41'),
(672, 82, 'Saved shop profile (pending verification)', 'Shop Profile', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:13:59'),
(673, 81, 'Saved shop profile', 'Shop Profile', NULL, NULL, NULL, NULL, '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 04:17:31'),
(674, 82, 'Saved shop profile with logo', 'Shop Profile', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:19:29'),
(675, 82, 'Saved shop profile', 'Shop Profile', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:20:51'),
(676, 73, 'Updated permit #26 (shop: Print Files) to verified', 'Permit Management', 'shop', 26, '{\"permit_status\":\"pending\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"Print Files\",\"owner_id\":82}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 04:21:29'),
(677, 73, 'Updated GCash QR Code payment setting #82 (shop: Print Files) to approved', 'Payment Settings', 'payment_channel', 82, '{\"channel\":\"gcash_qr\",\"approval_status\":\"pending\",\"is_active\":1,\"approved_by\":null,\"approved_at\":null,\"rejected_reason\":null}', '{\"channel\":\"gcash_qr\",\"approval_status\":\"approved\",\"is_active\":1,\"approved_by\":73,\"approved_at\":\"2026-08-28 04:21:32\",\"rejected_reason\":null,\"shop_id\":26,\"shop_name\":\"Print Files\"}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-28 04:21:32'),
(678, 82, 'Saved shop profile', 'Shop Profile', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:22:02'),
(679, 82, 'Added service pricing: Lamination - A5 (10.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:26:51'),
(680, 82, 'Added service pricing: Lamination - A4 (20.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:27:09'),
(681, 82, 'Added service pricing: Lamination - A3 (25.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:27:30'),
(682, 82, 'Added service pricing: Photo Printing - 2R - Photocopy - Glossy (10.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:28:07'),
(683, 82, 'Added service pricing: Photo Printing - 2R - Photocopy - Matte (5.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:28:31'),
(684, 82, 'Added service pricing: Photo Printing - 3R - Photocopy - Glossy (3.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:30:11'),
(685, 82, 'Added service pricing: Photo Printing - 3R - Photocopy - Matte (5.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.127.215', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-08-28 04:30:39'),
(686, 78, 'Updated print job #PE-20260828-674C0E status to Processing', 'Print Job Management', NULL, NULL, NULL, NULL, '180.190.52.161', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 05:54:46'),
(687, 78, 'Updated print job #PE-20260828-674C0E status to Ready for pickup', 'Print Job Management', NULL, NULL, NULL, NULL, '180.190.52.161', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 05:54:56'),
(688, 78, 'Updated print job #PE-20260828-674C0E status to Completed', 'Print Job Management', NULL, NULL, NULL, NULL, '180.190.52.161', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-28 05:55:18'),
(689, 81, 'Saved shop profile', 'Shop Profile', NULL, NULL, NULL, NULL, '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 06:09:11'),
(690, 81, 'Saved shop profile', 'Shop Profile', NULL, NULL, NULL, NULL, '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 06:12:12'),
(691, 81, 'Updated document pricing: Short / Bond Paper / Black & White (6.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '180.191.100.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 06:13:12'),
(692, 81, 'Deleted service pricing: Photo Printing - Bond Paper', 'Service Pricing', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 06:58:48'),
(693, 81, 'Deleted service pricing: Lamination - A5', 'Service Pricing', NULL, NULL, NULL, NULL, '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 07:23:27'),
(694, 81, 'Document pricing disabled (ID: 26)', 'Service Pricing', NULL, NULL, NULL, NULL, '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 07:23:31'),
(695, 81, 'Document pricing enabled (ID: 26)', 'Service Pricing', NULL, NULL, NULL, NULL, '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 07:23:33'),
(696, 81, 'Added service pricing: Lamination - A5 (4.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-29 07:27:28'),
(697, 79, 'Updated customer profile', 'Customer Profile', 'user', 79, '{\"full_name\":\"Dareen Casaljay Alvarez\",\"phone_number\":\"09368472994\",\"address\":\"Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines\",\"account_status\":\"verified\",\"has_profile_picture\":true,\"has_valid_id_front\":true,\"has_valid_id_back\":false}', '{\"full_name\":\"Dareen Casaljay Alvarez\",\"phone_number\":\"09368472994\",\"address\":\"Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines\",\"account_status\":\"verified\",\"changed_fields\":[\"valid_id_front_file\",\"valid_id_back_file\"],\"profile_picture_updated\":false,\"valid_id_front_updated\":true,\"valid_id_back_updated\":true}', '180.190.200.100', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Mobile Safari/537.36', '2026-08-29 08:50:15'),
(698, 73, 'Updated permit #25 (shop: KINGJAM) to disabled', 'Permit Management', 'shop', 25, '{\"permit_status\":\"verified\"}', '{\"permit_status\":\"disabled\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:11:52'),
(699, 73, 'Updated permit #25 (shop: KINGJAM) to verified', 'Permit Management', 'shop', 25, '{\"permit_status\":\"disabled\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:11:53'),
(700, 73, 'Updated permit #25 (shop: KINGJAM) to disabled', 'Permit Management', 'shop', 25, '{\"permit_status\":\"verified\"}', '{\"permit_status\":\"disabled\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:11:55'),
(701, 73, 'Updated permit #25 (shop: KINGJAM) to verified', 'Permit Management', 'shop', 25, '{\"permit_status\":\"disabled\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:11:56'),
(702, 73, 'Updated permit #25 (shop: KINGJAM) to disabled', 'Permit Management', 'shop', 25, '{\"permit_status\":\"verified\"}', '{\"permit_status\":\"disabled\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:13:37'),
(703, 73, 'Updated permit #25 (shop: KINGJAM) to verified', 'Permit Management', 'shop', 25, '{\"permit_status\":\"disabled\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:13:39'),
(704, 73, 'Updated permit #25 (shop: KINGJAM) to disabled', 'Permit Management', 'shop', 25, '{\"permit_status\":\"verified\"}', '{\"permit_status\":\"disabled\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:25:20'),
(705, 73, 'Updated permit #25 (shop: KINGJAM) to verified', 'Permit Management', 'shop', 25, '{\"permit_status\":\"disabled\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:25:22'),
(706, 73, 'Updated permit #25 (shop: KINGJAM) to disabled', 'Permit Management', 'shop', 25, '{\"permit_status\":\"verified\"}', '{\"permit_status\":\"disabled\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:25:23'),
(707, 73, 'Updated permit #25 (shop: KINGJAM) to verified', 'Permit Management', 'shop', 25, '{\"permit_status\":\"disabled\"}', '{\"permit_status\":\"verified\",\"shop_name\":\"KINGJAM\",\"owner_id\":81}', '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-29 09:25:25'),
(708, 78, 'Updated document pricing: Short / Bond Paper / Black & White (5.00)', 'Service Pricing', NULL, NULL, NULL, NULL, '222.127.50.180', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-08-30 06:25:32'),
(709, 78, 'Saved shop profile', 'Shop Profile', NULL, NULL, NULL, NULL, '180.190.200.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-03 13:49:45');

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
(1, 12.0660502, 124.5892970, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 02:48:33'),
(2, 12.0660825, 124.5888768, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 03:25:15'),
(3, 12.0540541, 124.6032303, 'Purok 2, Rawis, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 04:21:30'),
(4, 12.0670500, 124.5928818, 'High Rise, Senator Tomas Gomez Street, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 04:21:37'),
(5, 12.0637785, 124.6106157, 'Greenland Subdivision, Bagacay, Calbayog District, Carayman, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 04:21:45'),
(6, 12.0632459, 124.6109764, 'Greenland Subdivision, Bagacay, Calbayog District, Carayman, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 04:21:48'),
(7, 12.0631309, 124.6112989, 'Greenland Subdivision, Bagacay, Calbayog District, Carayman, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 04:21:52'),
(8, 12.0625489, 124.6108737, 'PhilGEPS Sub-Depot (Procurement), Magsaysay Boulevard Extension, Purok 5, Bagacay, Calbayog District, Carayman, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-28 04:21:56'),
(9, 12.0664407, 124.5891397, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-29 06:08:49'),
(10, 12.0664908, 124.5892491, 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', '2026-08-29 06:12:09');

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
(688, 73, 'permit_submitted', 'New shop owner registered: Alvarez, Dareen C', 'alvarezdareen776@gmail.com has signed up as a print shop owner. They still need to set up their shop.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php', '{\"user_id\":78,\"role\":\"shop_owner\",\"stage\":\"registered\"}', 1, '2026-08-28 11:00:54', '2026-08-26 14:08:25'),
(689, 73, 'account_submitted', 'New customer registered: Dareen Casaljay Alvarez', 'alvarezdareen667@gmail.com has signed up as a customer. Review their account.', 'https://printease.org/frontend/user/superadmin/manage_users.php', '{\"user_id\":79,\"role\":\"customer\",\"stage\":\"registered\"}', 1, '2026-08-28 11:00:54', '2026-08-26 16:18:11'),
(690, 73, 'account_submitted', 'New customer registered: Joshua Cristian Moreno', 'calculator4002@gmail.com has signed up as a customer. Review their account.', 'https://printease.org/frontend/user/superadmin/manage_users.php', '{\"user_id\":80,\"role\":\"customer\",\"stage\":\"registered\"}', 1, '2026-08-28 11:00:54', '2026-08-27 05:19:08'),
(691, 73, 'permit_submitted', 'Permit verification submitted: PRINTAHAN NI DAREEN', '\"PRINTAHAN NI DAREEN\" submitted its business permit for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php?status=pending', '{\"shop_id\":24,\"owner_id\":78}', 1, '2026-08-28 11:00:54', '2026-08-28 02:49:08'),
(692, 73, 'payment_settings_submitted', 'Payment details updated: PRINTAHAN NI DAREEN', 'GCash QR Code & GCash Merchant Link for \"PRINTAHAN NI DAREEN\" is ready for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php#payment-settings-review', '{\"shop_id\":24,\"owner_id\":78,\"channels\":[\"GCash QR Code\",\"GCash Merchant Link\"]}', 1, '2026-08-28 11:00:54', '2026-08-28 02:49:08'),
(693, 73, 'permit_submitted', 'New shop owner registered: Don Zeravla', 'dawn94671@gmail.com has signed up as a print shop owner. They still need to set up their shop.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php', '{\"user_id\":81,\"role\":\"shop_owner\",\"stage\":\"registered\"}', 1, '2026-08-28 11:00:54', '2026-08-28 02:51:22'),
(694, 73, 'permit_submitted', 'Permit verification submitted: KINGJAM', '\"KINGJAM\" submitted its business permit for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php?status=pending', '{\"shop_id\":25,\"owner_id\":81}', 1, '2026-08-28 11:00:54', '2026-08-28 02:59:40'),
(695, 73, 'payment_settings_submitted', 'Payment details updated: KINGJAM', 'GCash QR Code for \"KINGJAM\" is ready for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php#payment-settings-review', '{\"shop_id\":25,\"owner_id\":81,\"channels\":[\"GCash QR Code\"]}', 1, '2026-08-28 11:00:14', '2026-08-28 02:59:40'),
(696, 81, 'permit_status', 'Permit status updated', 'Your business permit for \"KINGJAM\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"verified\"}', 1, '2026-08-28 11:01:05', '2026-08-28 03:00:29'),
(697, 78, 'permit_status', 'Permit status updated', 'Your business permit for \"PRINTAHAN NI DAREEN\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":24,\"status\":\"verified\"}', 1, '2026-08-29 14:34:03', '2026-08-28 03:00:32'),
(698, 81, 'payment_settings_status', 'GCash QR Code payment setting approved', 'Your GCash QR Code payment setting for \"KINGJAM\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"channel\":\"gcash_qr\",\"status\":\"approved\"}', 1, '2026-08-28 11:01:03', '2026-08-28 03:00:36'),
(699, 78, 'payment_settings_status', 'GCash Merchant Link payment setting approved', 'Your GCash Merchant Link payment setting for \"PRINTAHAN NI DAREEN\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":24,\"channel\":\"gcash_merchant_link\",\"status\":\"approved\"}', 1, '2026-08-29 14:33:54', '2026-08-28 03:00:42'),
(700, 78, 'permit_status', 'Permit status updated', 'Your print shop \"PRINTAHAN NI DAREEN\" has been disabled by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":24,\"status\":\"disabled\"}', 1, '2026-08-29 14:33:52', '2026-08-28 03:15:09'),
(701, 78, 'permit_status', 'Permit status updated', 'Your business permit for \"PRINTAHAN NI DAREEN\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":24,\"status\":\"verified\"}', 1, '2026-08-28 13:52:26', '2026-08-28 03:15:11'),
(702, 79, 'account_status', 'Account status updated', 'Your account has been set to Active.', 'https://printease.org/frontend/user/customer/profile.php', '{\"status\":\"verified\",\"role\":\"customer\"}', 1, '2026-08-28 11:24:48', '2026-08-28 03:15:53'),
(703, 79, 'account_status', 'Account status updated', 'Your account has been deactivated by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/customer/profile.php', '{\"status\":\"inactive\",\"role\":\"customer\"}', 1, '2026-08-28 11:24:48', '2026-08-28 03:15:55'),
(704, 79, 'account_status', 'Account status updated', 'Your account has been set to Active.', 'https://printease.org/frontend/user/customer/profile.php', '{\"status\":\"verified\",\"role\":\"customer\"}', 1, '2026-08-28 11:24:48', '2026-08-28 03:15:56'),
(705, 79, 'account_status', 'Account status updated', 'Your account has been deactivated by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/customer/profile.php', '{\"status\":\"inactive\",\"role\":\"customer\"}', 1, '2026-08-28 11:24:48', '2026-08-28 03:15:58'),
(706, 79, 'account_status', 'Account status updated', 'Your account has been set to Active.', 'https://printease.org/frontend/user/customer/profile.php', '{\"status\":\"verified\",\"role\":\"customer\"}', 1, '2026-08-28 11:24:48', '2026-08-28 03:16:01'),
(707, 73, 'permit_submitted', 'New shop owner registered: Unknown big Boss', 'bustergrave90@gmail.com has signed up as a print shop owner. They still need to set up their shop.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php', '{\"user_id\":82,\"role\":\"shop_owner\",\"stage\":\"registered\"}', 1, '2026-08-28 11:43:11', '2026-08-28 03:36:35'),
(708, 73, 'permit_submitted', 'New shop owner registered: Abigail Klarrize Cahayon', 'bhie.cahayon@gmail.com has signed up as a print shop owner. They still need to set up their shop.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php', '{\"user_id\":83,\"role\":\"shop_owner\",\"stage\":\"registered\"}', 1, '2026-08-28 12:16:10', '2026-08-28 03:49:34'),
(709, 73, 'permit_submitted', 'New shop owner registered: Shaira Mae Amasan', 'amasanshairamae@gmail.com has signed up as a print shop owner. They still need to set up their shop.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php', '{\"user_id\":84,\"role\":\"shop_owner\",\"stage\":\"registered\"}', 1, '2026-08-28 12:16:08', '2026-08-28 04:09:20'),
(710, 73, 'permit_submitted', 'Permit verification submitted: Print Files', '\"Print Files\" submitted its business permit for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php?status=pending', '{\"shop_id\":26,\"owner_id\":82}', 1, '2026-08-28 12:16:02', '2026-08-28 04:13:59'),
(711, 73, 'payment_settings_submitted', 'Payment details updated: Print Files', 'GCash QR Code for \"Print Files\" is ready for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php#payment-settings-review', '{\"shop_id\":26,\"owner_id\":82,\"channels\":[\"GCash QR Code\"]}', 1, '2026-08-28 12:15:12', '2026-08-28 04:13:59'),
(712, 82, 'permit_status', 'Permit status updated', 'Your business permit for \"Print Files\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":26,\"status\":\"verified\"}', 1, '2026-08-28 12:30:56', '2026-08-28 04:21:29'),
(713, 82, 'payment_settings_status', 'GCash QR Code payment setting approved', 'Your GCash QR Code payment setting for \"Print Files\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":26,\"channel\":\"gcash_qr\",\"status\":\"approved\"}', 1, '2026-08-28 12:22:10', '2026-08-28 04:21:32'),
(714, 81, 'order_new', 'New print request', 'New print request received. Request #PE-20260828-ECCD95.', 'https://printease.org/frontend/user/shop_owner/orders.php?focus_order_id=140', '{\"order_id\":140,\"order_code\":\"PE-20260828-ECCD95\"}', 1, '2026-08-29 14:01:43', '2026-08-28 05:51:20'),
(715, 78, 'order_new', 'New print request', 'New print request received. Request #PE-20260828-674C0E.', 'https://printease.org/frontend/user/shop_owner/orders.php?focus_order_id=141', '{\"order_id\":141,\"order_code\":\"PE-20260828-674C0E\"}', 1, '2026-08-28 13:53:28', '2026-08-28 05:53:19'),
(716, 78, 'payment_submitted', 'Payment proof submitted', 'Payment proof submitted for request #PE-20260828-674C0E.', 'https://printease.org/frontend/user/shop_owner/orders.php?focus_order_id=141', '{\"order_id\":141,\"order_code\":\"PE-20260828-674C0E\"}', 1, '2026-08-28 13:54:14', '2026-08-28 05:54:06'),
(717, 79, 'payment_verified', 'Payment verified', 'Your payment for request #PE-20260828-674C0E has been verified.', 'https://printease.org/frontend/user/customer/orders.php?focus_order_id=141', '{\"order_id\":141,\"order_code\":\"PE-20260828-674C0E\",\"status\":\"pending\"}', 1, '2026-08-28 13:57:28', '2026-08-28 05:54:43'),
(718, 79, 'order_status', 'Request status updated', 'Your request #PE-20260828-674C0E is now Processing.', 'https://printease.org/frontend/user/customer/orders.php?focus_order_id=141', '{\"order_id\":141,\"order_code\":\"PE-20260828-674C0E\",\"status\":\"processing\"}', 1, '2026-08-28 13:57:28', '2026-08-28 05:54:46'),
(719, 79, 'order_status', 'Request status updated', 'Your request #PE-20260828-674C0E is now Ready for pickup.', 'https://printease.org/frontend/user/customer/orders.php?focus_order_id=141', '{\"order_id\":141,\"order_code\":\"PE-20260828-674C0E\",\"status\":\"ready_for_pickup\"}', 1, '2026-08-28 13:55:04', '2026-08-28 05:54:56'),
(720, 79, 'order_status', 'Request status updated', 'Your request #PE-20260828-674C0E is now Completed.', 'https://printease.org/frontend/user/customer/orders.php?focus_order_id=141', '{\"order_id\":141,\"order_code\":\"PE-20260828-674C0E\",\"status\":\"completed\"}', 1, '2026-08-28 13:55:25', '2026-08-28 05:55:18'),
(721, 81, 'permit_status', 'Permit status updated', 'Your print shop \"KINGJAM\" has been disabled by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"disabled\"}', 1, '2026-08-29 17:12:10', '2026-08-29 09:11:52'),
(722, 81, 'permit_status', 'Permit status updated', 'Your business permit for \"KINGJAM\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"verified\"}', 1, '2026-08-29 17:12:10', '2026-08-29 09:11:53'),
(723, 81, 'permit_status', 'Permit status updated', 'Your print shop \"KINGJAM\" has been disabled by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"disabled\"}', 1, '2026-08-29 17:12:10', '2026-08-29 09:11:55'),
(724, 81, 'permit_status', 'Permit status updated', 'Your business permit for \"KINGJAM\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"verified\"}', 1, '2026-08-29 17:12:07', '2026-08-29 09:11:56'),
(725, 81, 'permit_status', 'Permit status updated', 'Your print shop \"KINGJAM\" has been disabled by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"disabled\"}', 1, '2026-08-29 17:14:00', '2026-08-29 09:13:37'),
(726, 81, 'permit_status', 'Permit status updated', 'Your business permit for \"KINGJAM\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"verified\"}', 1, '2026-08-29 17:14:00', '2026-08-29 09:13:39'),
(727, 81, 'permit_status', 'Permit status updated', 'Your print shop \"KINGJAM\" has been disabled by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"disabled\"}', 1, '2026-08-29 17:25:40', '2026-08-29 09:25:20'),
(728, 81, 'permit_status', 'Permit status updated', 'Your business permit for \"KINGJAM\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"verified\"}', 1, '2026-08-29 17:25:40', '2026-08-29 09:25:22'),
(729, 81, 'permit_status', 'Permit status updated', 'Your print shop \"KINGJAM\" has been disabled by the administrator. Please contact support for assistance.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"disabled\"}', 1, '2026-08-29 17:25:40', '2026-08-29 09:25:23'),
(730, 81, 'permit_status', 'Permit status updated', 'Your business permit for \"KINGJAM\" has been approved.', 'https://printease.org/frontend/user/shop_owner/shop_profile.php', '{\"shop_id\":25,\"status\":\"verified\"}', 1, '2026-08-29 17:25:40', '2026-08-29 09:25:25'),
(731, 73, 'payment_settings_submitted', 'Payment details updated: PRINTAHAN NI DAREEN', 'GCash QR Code for \"PRINTAHAN NI DAREEN\" is ready for review.', 'https://printease.org/frontend/user/superadmin/manage_print_shops.php#payment-settings-review', '{\"shop_id\":24,\"owner_id\":78,\"channels\":[\"GCash QR Code\"]}', 0, NULL, '2026-09-03 13:49:45');

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

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_code`, `submit_token`, `customer_id`, `shop_id`, `service_id`, `paper_size`, `paper_type`, `print_type`, `copies`, `customer_instruction`, `pickup_datetime`, `pickup_reminder_sent`, `order_status`, `customer_deleted_at`, `total_amount`, `created_at`, `page_count`, `service_category`, `custom_service_id`, `service_name`) VALUES
(140, 'PE-20260828-ECCD95', '1c514c7ec7ed7ff848d0ac39b4deca73', 79, 25, 26, 'Short', 'Bond Paper', 'Black & White', 1, '', '2026-08-28 14:50:00', 0, 'pending', NULL, 110.00, '2026-08-28 05:51:20', 22, 'document_printing', NULL, NULL),
(141, 'PE-20260828-674C0E', '36604a255af8d9b19d7450c97efbd02b', 79, 24, 33, 'Short', 'Bond Paper', 'Black & White', 1, '', '2026-08-28 14:53:00', 0, 'completed', NULL, 88.00, '2026-08-28 05:53:19', 22, 'document_printing', NULL, NULL);

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `active_lock_order_id`, `customer_id`, `amount`, `payment_method`, `reference_number`, `ocr_reference_number`, `ocr_payment_date`, `payment_reference_match`, `proof_of_payment_file`, `payment_status`, `verification_status`, `verified_by`, `verified_at`, `rejection_reason`, `paid_at`, `created_at`) VALUES
(77, 141, 141, 79, 88.00, 'gcash_direct', '1044368798726', '1044368798726', '2026-08-26', 'detected', 'uploads/payment_proofs/1787896445_proof_a468090ed1be9bf12d69e52b331a2d4a.jpg', 'paid', 'verified', 78, '2026-08-28 05:54:43', NULL, '2026-08-28 05:54:43', '2026-08-28 05:54:06');

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
(24, 78, 'PRINTAHAN NI DAREEN', 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', 'Magsaysay Blvd', 'Near Christ The King', 12.06605017, 124.58929700, '1787885348_e2cbce438f91507e12e4346c459358a7.jpg', '1787885345_762766105e88d7c8a92c24cede09cfcb.jfif', 'available', '2026-08-28 02:49:08', NULL, '', '', NULL, NULL, 'GCash Merchant Link', 'verified', '00:48:00', '01:48:00', '00:48:00', '02:48:00'),
(25, 81, 'KINGJAM', 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', 'Rueda Street', 'next to RCBC', 12.06649082, 124.58924908, '1787885980_abaf3290103b35323330e47fc9f10165.jpg', '1787885980_19cf8e1d11c2d1a0d071dae18ef105ea.png', 'available', '2026-08-28 02:59:40', NULL, '', '', '1787885980_a6bd089e915c863340d456d3c9342942.jpg', NULL, 'GCash Merchant Link', 'verified', '00:59:00', '02:59:00', '01:59:00', '02:59:00'),
(26, 82, 'Print Files', 'PhilGEPS Sub-Depot (Procurement), Magsaysay Boulevard Extension, Purok 5, Bagacay, Calbayog District, Carayman, Calbayog, Samar, Eastern Visayas, 6710, Philippines', 'Diversion Strt.', 'Police Station RFM', 12.06254893, 124.61087368, '1787890439_4914750cc516e546c2fc4bb78ae2dcb1.jpeg', '1787890769_ab137aa8f5097ed47f258278372b3d62.jpeg', 'available', '2026-08-28 04:13:59', NULL, 'John Dennis Y Chan', '09943364768', '1787890439_605bea58d3e1b22dc10d3f0b05849899.jpg', NULL, 'GCash Merchant Link', 'verified', '18:00:00', '18:00:00', '00:00:00', '12:00:00');

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
(80, 24, 'gcash_merchant_link', NULL, NULL, NULL, 'https://gcash.com/', 'Pay the exact print request total using this GCash account, then upload your reference number and payment screenshot.', 'approved', 73, '2026-08-27 19:00:42', NULL, 1, '2026-08-28 02:49:08', '2026-09-03 13:49:45'),
(81, 25, 'gcash_qr', '', '', '1787885980_a6bd089e915c863340d456d3c9342942.jpg', NULL, 'Pay the exact print request total using this GCash account, then upload your reference number and payment screenshot.', 'approved', 73, '2026-08-27 19:00:36', NULL, 1, '2026-08-28 02:59:40', '2026-08-28 03:00:36'),
(82, 26, 'gcash_qr', 'John Dennis Y Chan', '09943364768', '1787890439_605bea58d3e1b22dc10d3f0b05849899.jpg', NULL, 'Pay the exact print request total using this GCash account, then upload your reference number and payment screenshot.', 'approved', 73, '2026-08-27 20:21:32', NULL, 1, '2026-08-28 04:13:59', '2026-08-28 04:21:32');

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

--
-- Dumping data for table `shop_price_records`
--

INSERT INTO `shop_price_records` (`id`, `shop_id`, `service_type`, `size_name`, `variant`, `width`, `height`, `dimension_unit`, `pricing_basis`, `min_quantity`, `max_quantity`, `price`, `is_available`, `legacy_source`, `legacy_id`, `created_at`, `updated_at`) VALUES
(69, 25, 'Document Printing', 'Short', 'Bond Paper / Black & White', NULL, NULL, NULL, 'per page', NULL, NULL, 6.00, 1, 'shop_services', 26, '2026-08-28 03:01:41', '2026-08-29 07:23:33'),
(74, 26, 'Document Printing', 'A4', 'Colored Paper, glossy / Colored', NULL, NULL, NULL, 'per page', NULL, NULL, 5.00, 1, 'shop_services', 27, '2026-08-28 04:23:47', '2026-08-28 04:23:47'),
(75, 26, 'Document Printing', 'Short', 'Bond Paper / Colored', NULL, NULL, NULL, 'per page', NULL, NULL, 5.00, 1, 'shop_services', 28, '2026-08-28 04:24:19', '2026-08-28 04:24:19'),
(76, 26, 'Document Printing', 'Long', 'Colored Paper, glossy / Colored', NULL, NULL, NULL, 'per page', NULL, NULL, 7.00, 1, 'shop_services', 29, '2026-08-28 04:24:51', '2026-08-28 04:24:51'),
(77, 26, 'Document Printing', 'Letter', 'Colored,Glossy / Colored', NULL, NULL, NULL, 'per page', NULL, NULL, 10.00, 1, 'shop_services', 30, '2026-08-28 04:25:24', '2026-08-28 04:25:24'),
(78, 26, 'Document Printing', 'A4', 'Glossy / Black& white', NULL, NULL, NULL, 'per page', NULL, NULL, 3.00, 1, 'shop_services', 31, '2026-08-28 04:25:59', '2026-08-28 04:25:59'),
(79, 26, 'Document Printing', 'Short', 'Colored,Glossy / Black& white', NULL, NULL, NULL, 'per page', NULL, NULL, 2.00, 1, 'shop_services', 32, '2026-08-28 04:26:32', '2026-08-28 04:26:32'),
(80, 26, 'Lamination', 'A5', NULL, NULL, NULL, NULL, 'flat rate', NULL, NULL, 10.00, 1, 'shop_service_pricing', 40, '2026-08-28 04:26:51', '2026-08-28 04:26:51'),
(81, 26, 'Lamination', 'A4', NULL, NULL, NULL, NULL, 'flat rate', NULL, NULL, 20.00, 1, 'shop_service_pricing', 41, '2026-08-28 04:27:09', '2026-08-28 04:27:09'),
(82, 26, 'Lamination', 'A3', NULL, NULL, NULL, NULL, 'flat rate', NULL, NULL, 25.00, 1, 'shop_service_pricing', 42, '2026-08-28 04:27:30', '2026-08-28 04:27:30'),
(83, 26, 'Photo Printing', '2R', 'Photocopy / Glossy', NULL, NULL, NULL, 'per item', NULL, NULL, 10.00, 1, 'shop_service_pricing', 43, '2026-08-28 04:28:07', '2026-08-28 04:28:07'),
(84, 26, 'Photo Printing', '2R', 'Photocopy / Matte', NULL, NULL, NULL, 'per item', NULL, NULL, 5.00, 1, 'shop_service_pricing', 44, '2026-08-28 04:28:31', '2026-08-28 04:28:31'),
(85, 26, 'Photo Printing', '3R', 'Photocopy / Glossy', NULL, NULL, NULL, 'per item', NULL, NULL, 3.00, 1, 'shop_service_pricing', 45, '2026-08-28 04:30:11', '2026-08-28 04:30:11'),
(86, 26, 'Photo Printing', '3R', 'Photocopy / Matte', NULL, NULL, NULL, 'per item', NULL, NULL, 5.00, 1, 'shop_service_pricing', 46, '2026-08-28 04:30:39', '2026-08-28 04:30:39'),
(87, 24, 'Document Printing', 'Short', 'Bond Paper / Black & White', NULL, NULL, NULL, 'per page', NULL, NULL, 5.00, 1, 'shop_services', 33, '2026-08-28 05:52:56', '2026-08-30 06:25:32'),
(91, 25, 'Lamination', 'A5', NULL, NULL, NULL, NULL, 'flat rate', NULL, NULL, 4.00, 1, 'shop_service_pricing', 47, '2026-08-29 07:27:28', '2026-08-29 07:27:28');

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

--
-- Dumping data for table `shop_services`
--

INSERT INTO `shop_services` (`service_id`, `shop_id`, `paper_size`, `paper_type`, `print_type`, `price_per_page`, `is_available`, `created_at`) VALUES
(26, 25, 'Short', 'Bond Paper', 'Black & White', 6.00, 1, '2026-08-28 03:01:41'),
(27, 26, 'A4', 'Colored Paper, glossy', 'Colored', 5.00, 1, '2026-08-28 04:23:47'),
(28, 26, 'Short', 'Bond Paper', 'Colored', 5.00, 1, '2026-08-28 04:24:19'),
(29, 26, 'Long', 'Colored Paper, glossy', 'Colored', 7.00, 1, '2026-08-28 04:24:51'),
(30, 26, 'Letter', 'Colored,Glossy', 'Colored', 10.00, 1, '2026-08-28 04:25:24'),
(31, 26, 'A4', 'Glossy', 'Black& white', 3.00, 1, '2026-08-28 04:25:59'),
(32, 26, 'Short', 'Colored,Glossy', 'Black& white', 2.00, 1, '2026-08-28 04:26:32'),
(33, 24, 'Short', 'Bond Paper', 'Black & White', 5.00, 1, '2026-08-28 05:52:56');

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

--
-- Dumping data for table `shop_service_pricing`
--

INSERT INTO `shop_service_pricing` (`id`, `shop_id`, `service_type`, `option_size`, `option_label`, `variant`, `unit`, `price`, `is_available`, `created_at`) VALUES
(40, 26, 'Lamination', NULL, 'A5', NULL, NULL, 10.00, 1, '2026-08-28 04:26:51'),
(41, 26, 'Lamination', NULL, 'A4', NULL, NULL, 20.00, 1, '2026-08-28 04:27:09'),
(42, 26, 'Lamination', NULL, 'A3', NULL, NULL, 25.00, 1, '2026-08-28 04:27:30'),
(43, 26, 'Photo Printing', '2R', 'Photocopy', NULL, 'Glossy', 10.00, 1, '2026-08-28 04:28:07'),
(44, 26, 'Photo Printing', '2R', 'Photocopy', NULL, 'Matte', 5.00, 1, '2026-08-28 04:28:31'),
(45, 26, 'Photo Printing', '3R', 'Photocopy', NULL, 'Glossy', 3.00, 1, '2026-08-28 04:30:11'),
(46, 26, 'Photo Printing', '3R', 'Photocopy', NULL, 'Matte', 5.00, 1, '2026-08-28 04:30:39'),
(47, 25, 'Lamination', NULL, 'A5', NULL, NULL, 4.00, 1, '2026-08-29 07:27:28');

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
(566, 26, 'Lamination', 1, 1, NULL, '2026-08-28 04:22:02'),
(567, 26, 'Photo Printing', 1, 1, NULL, '2026-08-28 04:22:02'),
(568, 26, 'Photocopy', 1, 0, 'This service requires physical documents. Online request is unavailable.', '2026-08-28 04:22:02'),
(569, 26, 'Document Printing', 1, 1, NULL, '2026-08-28 04:22:02'),
(586, 25, 'Lamination', 1, 1, NULL, '2026-08-29 06:12:12'),
(587, 25, 'Photo Printing', 1, 1, NULL, '2026-08-29 06:12:12'),
(588, 25, 'Tarpaulin Printing', 1, 1, NULL, '2026-08-29 06:12:12'),
(589, 25, 'ID Printing', 1, 1, NULL, '2026-08-29 06:12:12'),
(590, 25, 'Invitation / Card Printing', 1, 1, NULL, '2026-08-29 06:12:12'),
(591, 25, 'Photocopy', 1, 0, 'This service requires physical documents. Online request is unavailable.', '2026-08-29 06:12:12'),
(592, 25, 'Binding', 1, 0, 'Physical document submission is required. Please visit the shop.', '2026-08-29 06:12:12'),
(593, 25, 'Scanning', 1, 0, 'Original documents are required. Online request is unavailable.', '2026-08-29 06:12:12'),
(594, 25, 'Document Printing', 1, 1, NULL, '2026-08-29 06:12:12'),
(595, 24, 'Lamination', 1, 1, NULL, '2026-09-03 13:49:45'),
(596, 24, 'Photo Printing', 1, 1, NULL, '2026-09-03 13:49:45'),
(597, 24, 'Tarpaulin Printing', 1, 1, NULL, '2026-09-03 13:49:45'),
(598, 24, 'ID Printing', 1, 1, NULL, '2026-09-03 13:49:45'),
(599, 24, 'Invitation / Card Printing', 1, 1, NULL, '2026-09-03 13:49:45'),
(600, 24, 'Photocopy', 1, 0, 'This service requires physical documents. Online request is unavailable.', '2026-09-03 13:49:45'),
(601, 24, 'Binding', 1, 0, 'Physical document submission is required. Please visit the shop.', '2026-09-03 13:49:45'),
(602, 24, 'Scanning', 1, 0, 'Original documents are required. Online request is unavailable.', '2026-09-03 13:49:45'),
(603, 24, 'Document Printing', 1, 1, NULL, '2026-09-03 13:49:45');

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

--
-- Dumping data for table `uploaded_files`
--

INSERT INTO `uploaded_files` (`file_id`, `order_id`, `file_name`, `file_path`, `file_type`, `uploaded_at`, `cloudinary_public_id`) VALUES
(109, 140, 'Arduino_Programming_Module.pdf', 'https://res.cloudinary.com/dhmz4js82/raw/upload/v1787896280/printease/orders/arduino_programming_module_20260828_135115_3ba068.pdf', 'pdf', '2026-08-28 05:51:20', NULL),
(110, 141, 'Arduino_Programming_Module.pdf', 'https://res.cloudinary.com/dhmz4js82/raw/upload/v1787896398/printease/orders/arduino_programming_module_20260828_135315_00ba2e.pdf', 'pdf', '2026-08-28 05:53:19', NULL);

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
(78, 'Alvarez, Dareen C', 'alvarezdareen776@gmail.com', '$2y$10$DoXKJpcE6IeJx8k/an9ksO5k6U92KTS9enhletmcd/TUkjsgEajvu', NULL, 'shop_owner', NULL, NULL, NULL, 'verified', '2026-08-26 14:08:25', NULL, NULL, NULL, NULL, 2, NULL, NULL),
(79, 'Dareen Casaljay Alvarez', 'alvarezdareen667@gmail.com', '$2y$10$/ZICAIZrF8tKCBdK/428C.uFbHIGD7o9UKhzSbjv3HfjYRLDiJkg6', '09368472994', 'customer', 'uploads/customers/valid_id_79_b5502956c752042231935e59eb0d37ef.jpg', 'uploads/customers/valid_id_front_79_19e30c024d6a074f20a5f09435a565f5.jpg', 'uploads/customers/valid_id_back_79_4218829ad6515284cbeb40473faee7c0.jpg', 'verified', '2026-08-26 16:18:11', 'Capoocan Old Road, Purok 2, Obrero, Calbayog District, Calbayog, Samar, Eastern Visayas, 6710, Philippines', 'uploads/customers/profile_79_a13944c57543b447e26c379a7cb362b4.jpg', NULL, NULL, 1, NULL, NULL),
(80, 'Joshua Cristian Moreno', 'calculator4002@gmail.com', '$2y$10$Ybd/F2vREJX31zUnKsVB8OrewOVTErbcVNPB5rJARCGyvBF8iX8kK', NULL, 'customer', NULL, NULL, NULL, 'incomplete', '2026-08-27 05:19:08', NULL, NULL, NULL, NULL, 1, NULL, NULL),
(81, 'Don Zeravla', 'dawn94671@gmail.com', '$2y$10$Om/uR2nzarrEBhMnqmeMj.0rwxF6eT7dClU3KKSkkUH2f9QSLJKBa', NULL, 'shop_owner', NULL, NULL, NULL, 'verified', '2026-08-28 02:51:22', NULL, NULL, NULL, NULL, 1, NULL, NULL),
(82, 'Unknown big Boss', 'bustergrave90@gmail.com', '$2y$10$sLYIV6Wswelg3flcrfADreZbdFSW8mo5g8z6lxHEnVhWDtfNowMIG', NULL, 'shop_owner', NULL, NULL, NULL, 'verified', '2026-08-28 03:36:35', NULL, NULL, NULL, NULL, 1, NULL, NULL),
(83, 'Abigail Klarrize Cahayon', 'bhie.cahayon@gmail.com', '$2y$10$1rquuTQzp5sihti82nWch.rcAH/cvBuyQPfNGjg0iUP5How6zN1sm', NULL, 'shop_owner', NULL, NULL, NULL, 'incomplete', '2026-08-28 03:49:34', NULL, NULL, NULL, NULL, 1, NULL, NULL),
(84, 'Shaira Mae Amasan', 'amasanshairamae@gmail.com', '$2y$10$T21pDAiVKGLNhrxY8/VDje1skDaoflWIwqvIzA9STd0t5K50U5DSG', NULL, 'shop_owner', NULL, NULL, NULL, 'incomplete', '2026-08-28 04:09:20', NULL, NULL, NULL, NULL, 1, NULL, NULL);

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
(265, 80, '264a225d8de3ec41bdb8efd8e14d09679699', 'e75de16a12d1c5e901fdf293405e87f78c6c61a90b70434a3101c605ad908469', 'google', 180, '2027-02-23 13:19:08', '2026-08-27 13:19:08', '2026-08-27 13:19:08'),
(266, 78, '4ae85cd5cb0a01dbd962fc62aa9cc0284edd', '13bc7412c675e3c974d9afa436d1d7c8d6954a522fd92790a928f42a15fe26ce', 'password', 180, '2027-02-24 09:58:34', '2026-08-28 09:58:34', '2026-08-28 09:58:34'),
(267, 81, '5c89fab417f8b9c6f325ce9da11b2b9ccbdf', '8fb2840b1f49af8e5e8b158902cf27da6f610b83fec45a71698edf39491e17b6', 'password', 180, '2027-02-24 10:51:31', '2026-08-28 10:51:31', '2026-08-28 10:51:31'),
(268, 73, '4381a4c361dbb9cf3e06e01279c6004c4cb8', '46b16d3e2a53e551a0700d7ddf655a3e65205f6af8d73f6e1eaabe98d856c813', 'password', 180, '2027-02-24 11:00:02', '2026-08-28 11:00:02', '2026-08-28 11:00:02'),
(269, 79, '1de05634097fd46c3f6c439a62264459ea42', '1dc19e0e301f40087541704270398fc91729e03cf63531d9fd8e38988998966a', 'google', 180, '2027-02-24 11:24:37', '2026-08-28 11:24:37', '2026-08-28 11:24:37'),
(270, 82, '4829cbca1182cba027c97e8c4f1072574799', 'd1d1032dc0e9db4f2962cbb7e46920b183835390ddbd9b0efd43a1c6d32f1dc0', 'password', 30, '2026-09-27 11:36:46', '2026-08-28 11:36:46', '2026-08-28 11:36:46'),
(271, 83, '376e32613186008e84a47f4975c9cfd54aab', '6a08dd54575d43708c556fd1d10e3891f10057cadded8cffc2b56173b3c707f0', 'google', 180, '2027-02-24 11:49:34', '2026-08-28 11:49:34', '2026-08-28 11:49:34'),
(272, 84, '0564884bce26484da23ce1fe23de4ae8da34', '1c807a764c561a9c08294861b93e77b3088066c400a986c96c1af1bdd9f8947c', 'password', 180, '2027-02-24 12:09:51', '2026-08-28 12:09:51', '2026-08-28 12:09:51'),
(273, 79, '007ca1b878b0c510d2b33784336da2ef2c4b', '80e5f4a5d22b162a03b2d77c24a33c476efa8b2c2fa1e9136ce540779e8b2544', 'google', 180, '2027-02-24 12:23:37', '2026-08-28 12:23:37', '2026-08-28 12:23:37'),
(274, 78, '3f5e363461bbe19add6178c9546afd7e3c90', '75e6fa11d5d1609fcd17c49cdc53c001bd7704adcd9187a3f9ebdadfc554a31b', 'password', 180, '2027-02-24 13:47:19', '2026-08-28 13:47:19', '2026-08-28 13:47:19'),
(275, 78, '40dc2f73e3bf87707aae7b5c33f4754feab3', 'e43c2af4831aa13d982ce7d97d63c0560da2689f48f17fcd0481aa9e91811ee1', 'password', 180, '2027-02-24 13:47:19', '2026-08-28 13:47:19', '2026-08-28 13:47:19'),
(276, 78, '7426cd876d7ebf34125d0c4b8aa56e7e046b', 'a54f18aafe34a89b6d4c884da5f8359b38e32b8f799ce2da7baa55c2b4a5bae1', 'password', 180, '2027-02-24 13:52:03', '2026-08-28 13:52:03', '2026-08-28 13:52:03'),
(277, 78, '901fe1dd355be71f516e9803d247a77f12f2', '764e881c1843d7dfb29105b7df2b8f7a860b08f9f8849bbbf3941b32106270c6', 'password', 180, '2027-02-25 14:33:49', '2026-08-29 14:33:49', '2026-08-29 14:33:49'),
(278, 78, 'bac280e5f0963ec1402da9414a51e72ad4a9', '67f702dc4f7e2fe70140ef79a326612ddd4665500ebc8874ee40fe2af15cb3af', 'password', 180, '2027-02-26 11:46:23', '2026-08-30 11:46:23', '2026-08-30 11:46:23'),
(279, 81, '38fd1b9cb445ff165f90be2cceebe4971164', '6643147a5c4a591b8c5f82ba1a679b2c1e89a14e52f99a1397f12ce61264d810', 'password', 180, '2027-03-05 09:13:40', '2026-09-06 09:13:40', '2026-09-06 09:13:40');

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
(22, 78, 'google', '117970612336612457498', 'alvarezdareen776@gmail.com', '2026-08-26 14:08:25', '2026-08-26 14:08:25'),
(23, 80, 'google', '105608778461351616376', 'calculator4002@gmail.com', '2026-08-27 05:19:08', '2026-08-27 05:19:08'),
(24, 79, 'google', '111678072395998696104', 'alvarezdareen667@gmail.com', '2026-08-28 03:24:37', '2026-08-28 03:24:37'),
(25, 83, 'google', '106183447379094385142', 'bhie.cahayon@gmail.com', '2026-08-28 03:49:34', '2026-08-28 03:49:34');

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=710;

--
-- AUTO_INCREMENT for table `customer_favorite_shops`
--
ALTER TABLE `customer_favorite_shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `geocode_cache`
--
ALTER TABLE `geocode_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=732;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `print_shops`
--
ALTER TABLE `print_shops`
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `rate_limit_events`
--
ALTER TABLE `rate_limit_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=315;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `shop_payment_settings`
--
ALTER TABLE `shop_payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `shop_price_records`
--
ALTER TABLE `shop_price_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `shop_services`
--
ALTER TABLE `shop_services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `shop_service_pricing`
--
ALTER TABLE `shop_service_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `shop_service_types`
--
ALTER TABLE `shop_service_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=604;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `user_remember_tokens`
--
ALTER TABLE `user_remember_tokens`
  MODIFY `remember_token_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=280;

--
-- AUTO_INCREMENT for table `user_social_accounts`
--
ALTER TABLE `user_social_accounts`
  MODIFY `social_account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `shop_payment_channels`
--
ALTER TABLE `shop_payment_channels`
  ADD CONSTRAINT `fk_shop_payment_channels_shop` FOREIGN KEY (`shop_id`) REFERENCES `print_shops` (`shop_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
