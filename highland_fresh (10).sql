-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 03, 2026 at 04:35 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `highland_fresh`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `prev_hash` char(64) DEFAULT NULL,
  `entry_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(1, 1, 'create', 'sales_transactions', 27, NULL, '{\"transaction_code\":\"SI-2026-00027\",\"total\":105,\"items\":1}', '::1', 'curl/8.16.0', NULL, NULL, '2026-02-03 10:44:00'),
(2, 1, 'create', 'sales_transactions', 28, NULL, '{\"transaction_code\":\"SI-2026-00028\",\"total\":105,\"items\":1}', '::1', 'curl/8.16.0', NULL, NULL, '2026-02-03 10:44:31'),
(3, 7, 'create', 'sales_transactions', 29, NULL, '{\"transaction_code\":\"SI-2026-00029\",\"total\":60,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 10:44:45'),
(4, 7, 'create', 'sales_transactions', 30, NULL, '{\"transaction_code\":\"SI-2026-00030\",\"total\":140,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 10:55:28'),
(5, 7, 'create', 'payment_collections', 9, NULL, '{\"or_number\":\"OR-2026-00001\",\"dr_number\":\"DR-20260124-0107\",\"amount\":11250,\"method\":\"cash\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 11:44:35'),
(6, 7, 'start_shift', 'cashier_shifts', 1, NULL, '{\"opening_cash\":5000}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:22:01'),
(7, 7, 'create', 'sales_transactions', 31, NULL, '{\"transaction_code\":\"SI-2026-00031\",\"total\":50,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:22:09'),
(8, 7, 'end_shift', 'cashier_shifts', 1, '{\"status\":\"active\"}', '{\"status\":\"closed\",\"expected_cash\":5050,\"actual_cash\":null,\"variance\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:22:32'),
(9, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:27:41'),
(10, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:39:35'),
(11, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:43:02'),
(12, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:44:12'),
(13, 2, 'CREATE', 'milk_receiving', 18, NULL, '{\"receiving_code\":\"RCV-20260203-001\",\"rmr_number\":66190,\"farmer_id\":\"10\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:44:29'),
(14, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:50:03'),
(15, 2, 'CREATE', 'milk_receiving', 19, NULL, '{\"receiving_code\":\"RCV-20260203-002\",\"rmr_number\":66191,\"farmer_id\":\"2\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 13:53:17'),
(16, 2, 'CREATE', 'qc_milk_tests', 18, NULL, '{\"test_code\":\"QCT-000001\",\"receiving_id\":\"19\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 14:00:16'),
(17, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 14:05:46'),
(18, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 14:11:03'),
(19, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 14:13:20'),
(20, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-03 14:34:28'),
(21, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-04 09:35:26'),
(22, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-04 09:39:10'),
(23, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-04 09:39:45'),
(24, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:38:06'),
(25, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:38:20'),
(26, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:38:31'),
(27, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 1, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:41:35'),
(28, 4, 'approve_requisition', 'material_requisitions', 2, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:42:43'),
(29, 4, 'fulfill_requisition', 'material_requisitions', 2, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:42:49'),
(30, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:44:29'),
(31, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:44:45'),
(32, 4, 'adjust_stock', 'mro_items', 2, '{\"current_stock\":\"21.00\"}', '{\"current_stock\":\"22\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:46:07'),
(33, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:46:27'),
(34, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:47:22'),
(35, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:48:29'),
(36, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:48:38'),
(37, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:50:00'),
(38, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:51:29'),
(39, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:52:08'),
(40, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:52:24'),
(41, 4, 'approve_requisition', 'material_requisitions', 4, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:52:39'),
(42, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:54:40'),
(43, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:55:06'),
(44, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:55:32'),
(45, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:55:40'),
(46, 4, 'approve_requisition', 'material_requisitions', 5, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:55:52'),
(47, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:57:40'),
(48, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:58:16'),
(49, 4, 'fulfill_requisition', 'material_requisitions', 4, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:59:12'),
(50, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 05:59:30'),
(51, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:02:26'),
(52, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:02:43'),
(53, 2, 'CREATE', 'milk_receiving', 20, NULL, '{\"receiving_code\":\"RCV-20260205-001\",\"rmr_number\":66192,\"farmer_id\":\"3\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:02:57'),
(54, 2, 'CREATE', 'qc_milk_tests', 19, NULL, '{\"test_code\":\"QCT-000002\",\"receiving_id\":\"20\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:03:13'),
(55, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:03:20'),
(56, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 2, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:03:31'),
(57, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:03:53'),
(58, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:04:05'),
(59, 4, 'fulfill_requisition', 'material_requisitions', 4, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:05:17'),
(60, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:06:03'),
(61, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:06:49'),
(62, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:09:10'),
(63, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:09:56'),
(64, 4, 'approve_requisition', 'material_requisitions', 6, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:10:12'),
(65, 4, 'fulfill_requisition', 'material_requisitions', 6, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:10:16'),
(66, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:10:29'),
(67, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:31:53'),
(68, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 06:32:47'),
(69, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:15:46'),
(70, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:21:51'),
(71, 7, 'create', 'sales_transactions', 32, NULL, '{\"transaction_code\":\"SI-2026-00032\",\"total\":105,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:22:00'),
(72, 7, 'create', 'payment_collections', 10, NULL, '{\"or_number\":\"OR-2026-00002\",\"dr_number\":\"DR-20260127-0106\",\"amount\":7500,\"method\":\"cash\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:22:13'),
(73, 7, 'create', 'sales_transactions', 33, NULL, '{\"transaction_code\":\"SI-2026-00033\",\"total\":45,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:26:23'),
(74, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:28:00'),
(75, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:28:26'),
(76, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:28:46'),
(77, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:37:54'),
(78, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 07:58:29'),
(79, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:06:59'),
(80, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:08:52'),
(81, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:29:54'),
(82, 2, 'RELEASE', 'production_batches', 14, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:30:02'),
(83, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:30:15'),
(84, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:31:30'),
(85, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:37:03'),
(86, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:37:20'),
(87, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:42:30'),
(88, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:43:10'),
(89, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:46:45'),
(90, 6, 'CREATE', 'sales_orders', 2, NULL, '{\"action\":\"create\",\"customer_id\":5,\"customer_po_number\":null,\"sub_account_id\":null,\"delivery_date\":\"2026-02-05\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":13,\"quantity_boxes\":1,\"quantity_pieces\":1,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 08:59:12'),
(91, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 09:01:48'),
(92, 8, 'UPDATE_STATUS', 'sales_orders', 2, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:10:06'),
(93, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:10:19'),
(94, 8, 'UPDATE_STATUS', 'sales_orders', 2, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:10:33'),
(95, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:10:38'),
(96, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:16:49'),
(97, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:17:22'),
(98, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:23:35'),
(99, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:24:20'),
(100, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:25:01'),
(101, 4, 'fulfill_requisition', 'material_requisitions', 5, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:25:26'),
(102, 4, 'approve_requisition', 'material_requisitions', 7, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:25:29'),
(103, 4, 'fulfill_requisition', 'material_requisitions', 7, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:25:32'),
(104, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:29:50'),
(105, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:30:00'),
(106, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:31:25'),
(107, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:41:27'),
(108, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:41:40'),
(109, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:55:48'),
(110, 2, 'CREATE', 'milk_receiving', 21, NULL, '{\"receiving_code\":\"RCV-20260205-002\",\"rmr_number\":66193,\"farmer_id\":\"3\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:55:59'),
(111, 2, 'CREATE', 'qc_milk_tests', 20, NULL, '{\"test_code\":\"QCT-000003\",\"receiving_id\":\"21\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:56:14'),
(112, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:56:25'),
(113, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 4, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:56:49'),
(114, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:57:09'),
(115, 2, 'CREATE', 'milk_receiving', 22, NULL, '{\"receiving_code\":\"RCV-20260205-003\",\"rmr_number\":66194,\"farmer_id\":\"2\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:57:22'),
(116, 2, 'CREATE', 'qc_milk_tests', 21, NULL, '{\"test_code\":\"QCT-000004\",\"receiving_id\":\"22\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:57:34'),
(117, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:57:50'),
(118, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:58:03'),
(119, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 5, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:58:11'),
(120, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:58:20'),
(121, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:58:49'),
(122, 4, 'approve_requisition', 'material_requisitions', 8, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:59:01'),
(123, 4, 'fulfill_requisition', 'material_requisitions', 8, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:59:03'),
(124, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 10:59:12'),
(125, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:00:16'),
(126, 2, 'RELEASE', 'production_batches', 15, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:00:25'),
(127, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:09:12'),
(128, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:09:46'),
(129, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:13:15'),
(130, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:16:39'),
(131, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:17:38'),
(132, 6, 'CREATE', 'sales_orders', 3, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":null,\"sub_account_id\":\"5\",\"delivery_date\":\"2026-02-05\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":7,\"quantity\":21,\"quantity_boxes\":1,\"quantity_pieces\":1,\"unit_type\":\"mixed\",\"unit_price\":140}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:18:00'),
(133, 6, 'UPDATE_STATUS', 'sales_orders', 3, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:18:04'),
(134, 6, 'UPDATE_STATUS', 'sales_orders', 3, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:18:11'),
(135, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:18:23'),
(136, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 11:19:18'),
(137, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 12:05:08'),
(138, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 19:09:52'),
(139, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 19:10:01'),
(140, 5, 'UPDATE', 'customers', 6, '{\"id\":6,\"customer_code\":\"DEPED-CDO-001\",\"customer_type\":\"feeding_program\",\"name\":\"DepEd Region X Feeding Program\",\"sub_location\":null,\"contact_person\":\"Maria Santos\",\"contact_number\":\"09171234567\",\"email\":null,\"address\":\"DepEd Complex, Cagayan de Oro City\",\"credit_limit\":\"500000.00\",\"current_balance\":\"0.00\",\"payment_terms_days\":0,\"default_payment_type\":\"cash\",\"status\":\"active\",\"notes\":null,\"created_at\":\"2026-02-05 16:06:35\",\"updated_at\":\"2026-02-05 16:06:35\"}', '{\"action\":\"update\",\"id\":\"6\",\"name\":\"DepEd Region X Feeding Program\",\"customer_type\":\"school\",\"contact_person\":\"Maria Santos\",\"phone\":\"55656565\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 19:12:50'),
(141, 5, 'UPDATE', 'customers', 6, '{\"id\":6,\"customer_code\":\"DEPED-CDO-001\",\"customer_type\":\"\",\"name\":\"DepEd Region X Feeding Program\",\"sub_location\":null,\"contact_person\":\"Maria Santos\",\"contact_number\":\"55656565\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"credit_limit\":\"500000.00\",\"current_balance\":\"0.00\",\"payment_terms_days\":0,\"default_payment_type\":\"cash\",\"status\":\"active\",\"notes\":null,\"created_at\":\"2026-02-05 16:06:35\",\"updated_at\":\"2026-02-06 03:12:50\"}', '{\"action\":\"update\",\"id\":\"6\",\"name\":\"DepEd Region X Feeding Program\",\"customer_type\":\"supermarket\",\"contact_person\":\"Maria Santos\",\"phone\":\"089087907890\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 19:13:01'),
(142, 5, 'UPDATE', 'customers', 6, '{\"id\":6,\"customer_code\":\"DEPED-CDO-001\",\"customer_type\":\"supermarket\",\"name\":\"DepEd Region X Feeding Program\",\"sub_location\":null,\"contact_person\":\"Maria Santos\",\"contact_number\":\"089087907890\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"credit_limit\":\"500000.00\",\"current_balance\":\"0.00\",\"payment_terms_days\":0,\"default_payment_type\":\"cash\",\"status\":\"active\",\"notes\":null,\"created_at\":\"2026-02-05 16:06:35\",\"updated_at\":\"2026-02-06 03:13:01\"}', '{\"action\":\"update\",\"id\":\"6\",\"name\":\"DepEd Region X Feeding Program\",\"customer_type\":\"school\",\"contact_person\":\"Maria Santos\",\"phone\":\"65+65+\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 19:13:08'),
(143, 5, 'UPDATE', 'customers', 6, '{\"id\":6,\"customer_code\":\"DEPED-CDO-001\",\"customer_type\":\"\",\"name\":\"DepEd Region X Feeding Program\",\"sub_location\":null,\"contact_person\":\"Maria Santos\",\"contact_number\":\"65+65+\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"credit_limit\":\"500000.00\",\"current_balance\":\"0.00\",\"payment_terms_days\":0,\"default_payment_type\":\"cash\",\"status\":\"active\",\"notes\":null,\"created_at\":\"2026-02-05 16:06:35\",\"updated_at\":\"2026-02-06 03:13:08\"}', '{\"action\":\"update\",\"id\":\"6\",\"name\":\"DepEd Region X Feeding Program\",\"customer_type\":\"retail\",\"contact_person\":\"Maria Santos\",\"phone\":\"56+56+56+\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 19:13:15'),
(144, 5, 'UPDATE', 'customers', 6, '{\"id\":6,\"customer_code\":\"DEPED-CDO-001\",\"customer_type\":\"\",\"name\":\"DepEd Region X Feeding Program\",\"sub_location\":null,\"contact_person\":\"Maria Santos\",\"contact_number\":\"56+56+56+\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"credit_limit\":\"500000.00\",\"current_balance\":\"0.00\",\"payment_terms_days\":0,\"default_payment_type\":\"cash\",\"status\":\"active\",\"notes\":null,\"created_at\":\"2026-02-05 16:06:35\",\"updated_at\":\"2026-02-06 03:13:15\"}', '{\"action\":\"update\",\"id\":\"6\",\"name\":\"DepEd Region X Feeding Program\",\"customer_type\":\"supermarket\",\"contact_person\":\"Maria Santos\",\"phone\":\"09090909\",\"email\":\"\",\"address\":\"DepEd Complex, Cagayan de Oro City\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-05 19:13:21'),
(145, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-06 04:47:20'),
(146, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-06 05:16:31'),
(147, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-06 05:24:58'),
(148, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-06 05:25:08'),
(149, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 11:46:57'),
(150, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:34:15'),
(151, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:34:46'),
(152, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:43:19'),
(153, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:44:23'),
(154, 2, 'CREATE', 'disposals', 1, NULL, '{\"disposal_code\":\"DSP-20260208-0001\",\"source_type\":\"raw_milk\",\"source_id\":\"5\",\"quantity\":50,\"category\":\"expired\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:44:41'),
(155, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:44:58'),
(156, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:45:48'),
(157, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 12:48:21'),
(158, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:00:00'),
(159, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:00:19'),
(160, 8, 'REJECT', 'disposals', 1, '{\"status\":\"pending\"}', '{\"status\":\"rejected\",\"reason\":\"Rejected by GM\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:00:59'),
(161, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:02:16'),
(162, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:02:56'),
(163, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:20:32'),
(164, 8, 'CREATE', 'disposals', 2, NULL, '{\"disposal_code\":\"DSP-20260208-0002\",\"source_type\":\"raw_milk\",\"source_id\":\"5\",\"quantity\":25,\"category\":\"expired\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:24:27'),
(165, 8, 'APPROVE', 'disposals', 2, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:25:15'),
(166, 8, 'COMPLETE', 'disposals', 2, '{\"status\":\"approved\"}', '{\"status\":\"completed\",\"disposed_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:26:15'),
(167, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:27:05'),
(168, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:40:28'),
(169, 2, 'CREATE', 'yogurt_transformations', 1, NULL, '{\"transformation_code\":\"YTF-000001\",\"source_inventory_id\":\"2\",\"quantity\":50,\"production_run_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-08 13:54:32'),
(170, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 03:39:12'),
(171, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 03:47:58'),
(172, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 03:54:44'),
(173, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 03:55:16'),
(174, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:40:41'),
(175, 2, 'CREATE', 'disposals', 3, NULL, '{\"disposal_code\":\"DSP-20260209-0001\",\"source_type\":\"raw_milk\",\"source_id\":\"5\",\"quantity\":25,\"category\":\"expired\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:41:04'),
(176, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:41:10'),
(177, 8, 'APPROVE', 'disposals', 3, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:41:24'),
(178, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:41:33'),
(179, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:41:51'),
(180, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:42:12'),
(181, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:42:25'),
(182, 2, 'CREATE', 'disposals', 4, NULL, '{\"disposal_code\":\"DSP-20260209-0002\",\"source_type\":\"finished_goods\",\"source_id\":\"2\",\"quantity\":32,\"category\":\"qc_failed\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:42:56'),
(183, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:43:04'),
(184, 8, 'REJECT', 'disposals', 4, '{\"status\":\"pending\"}', '{\"status\":\"rejected\",\"reason\":\"Rejected by GM\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:43:11'),
(185, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:43:26'),
(186, 2, 'CREATE', 'disposals', 5, NULL, '{\"disposal_code\":\"DSP-20260209-0003\",\"source_type\":\"finished_goods\",\"source_id\":\"2\",\"quantity\":32,\"category\":\"qc_failed\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:43:41'),
(187, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:43:51'),
(188, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:44:06'),
(189, 8, 'APPROVE', 'disposals', 5, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:44:14'),
(190, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:44:22'),
(191, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:44:37'),
(192, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:44:44'),
(193, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:45:33'),
(194, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:48:16'),
(195, 5, 'COMPLETE', 'disposals', 5, '{\"status\":\"approved\"}', '{\"status\":\"completed\",\"disposed_by\":5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:48:36');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(196, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:48:47'),
(197, 2, 'CREATE', 'disposals', 6, NULL, '{\"disposal_code\":\"DSP-20260209-0004\",\"source_type\":\"finished_goods\",\"source_id\":\"3\",\"quantity\":99,\"category\":\"qc_failed\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:49:32'),
(198, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:49:39'),
(199, 8, 'APPROVE', 'disposals', 6, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:49:46'),
(200, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:49:52'),
(201, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:57:00'),
(202, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:58:01'),
(203, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:58:36'),
(204, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:58:53'),
(205, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 04:59:33'),
(206, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:00:25'),
(207, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:00:46'),
(208, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:01:00'),
(209, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:01:20'),
(210, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:01:29'),
(211, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:02:19'),
(212, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:02:48'),
(213, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:03:40'),
(214, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:11:31'),
(215, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:12:03'),
(216, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:15:48'),
(217, 6, 'CREATE', 'sales_orders', 4, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":\"yehey\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":17,\"quantity_boxes\":1,\"quantity_pieces\":5,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:16:24'),
(218, 6, 'UPDATE_STATUS', 'sales_orders', 4, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:16:31'),
(219, 6, 'UPDATE_STATUS', 'sales_orders', 4, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:16:34'),
(220, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:16:57'),
(221, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:17:43'),
(222, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:18:37'),
(223, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:19:09'),
(224, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:23:18'),
(225, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:24:15'),
(226, 6, 'CREATE', 'sales_orders', 5, NULL, '{\"action\":\"create\",\"customer_id\":5,\"customer_po_number\":\"Basta\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":24,\"quantity_boxes\":1,\"quantity_pieces\":12,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:25:10'),
(227, 6, 'UPDATE_STATUS', 'sales_orders', 5, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:25:13'),
(228, 6, 'UPDATE_STATUS', 'sales_orders', 5, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:25:18'),
(229, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:29:46'),
(230, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:30:01'),
(231, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:30:15'),
(232, 6, 'CREATE', 'sales_orders', 6, NULL, '{\"action\":\"create\",\"customer_id\":5,\"customer_po_number\":\"50\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":13,\"quantity_boxes\":1,\"quantity_pieces\":1,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:30:33'),
(233, 6, 'UPDATE_STATUS', 'sales_orders', 6, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:30:37'),
(234, 6, 'UPDATE_STATUS', 'sales_orders', 6, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:30:41'),
(235, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:30:51'),
(236, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:31:21'),
(237, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:31:42'),
(238, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 05:31:57'),
(239, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:13:10'),
(240, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:13:47'),
(241, 6, 'UPDATE_STATUS', 'sales_orders', 6, '{\"status\":\"dispatched\"}', '{\"status\":\"delivered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:14:25'),
(242, 6, 'UPDATE_STATUS', 'sales_orders', 5, '{\"status\":\"dispatched\"}', '{\"status\":\"delivered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:14:27'),
(243, 6, 'UPDATE_STATUS', 'sales_orders', 4, '{\"status\":\"dispatched\"}', '{\"status\":\"delivered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:14:34'),
(244, 6, 'UPDATE_STATUS', 'sales_orders', 3, '{\"status\":\"dispatched\"}', '{\"status\":\"delivered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:14:36'),
(245, 6, 'UPDATE_STATUS', 'sales_orders', 2, '{\"status\":\"dispatched\"}', '{\"status\":\"delivered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:14:39'),
(246, 6, 'CREATE', 'sales_orders', 7, NULL, '{\"action\":\"create\",\"customer_id\":5,\"customer_po_number\":null,\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":130,\"quantity_boxes\":10,\"quantity_pieces\":10,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:14:55'),
(247, 6, 'UPDATE_STATUS', 'sales_orders', 7, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:15:03'),
(248, 6, 'UPDATE_STATUS', 'sales_orders', 7, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:15:07'),
(249, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:15:27'),
(250, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:15:42'),
(251, 6, 'UPDATE_STATUS', 'sales_orders', 7, '{\"status\":\"dispatched\"}', '{\"status\":\"delivered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:15:55'),
(252, 6, 'CREATE', 'sales_orders', 8, NULL, '{\"action\":\"create\",\"customer_id\":3,\"customer_po_number\":null,\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":130,\"quantity_boxes\":10,\"quantity_pieces\":10,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:17:20'),
(253, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:19:31'),
(254, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:19:50'),
(255, 6, 'CREATE', 'sales_orders', 9, NULL, '{\"action\":\"create\",\"customer_id\":5,\"customer_po_number\":\"500\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":130,\"quantity_boxes\":10,\"quantity_pieces\":10,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:20:07'),
(256, 6, 'UPDATE_STATUS', 'sales_orders', 9, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:20:11'),
(257, 6, 'UPDATE_STATUS', 'sales_orders', 9, '{\"status\":\"pending\"}', '{\"status\":\"cancelled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:20:18'),
(258, 6, 'UPDATE_STATUS', 'sales_orders', 8, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:20:21'),
(259, 6, 'CREATE', 'sales_orders', 10, NULL, '{\"action\":\"create\",\"customer_id\":3,\"customer_po_number\":\"100\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":7,\"quantity\":30,\"quantity_boxes\":1,\"quantity_pieces\":10,\"unit_type\":\"mixed\",\"unit_price\":140}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:23:16'),
(260, 6, 'UPDATE_STATUS', 'sales_orders', 10, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:23:18'),
(261, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:23:39'),
(262, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:23:59'),
(263, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:30:56'),
(264, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:36:38'),
(265, 8, 'APPROVE', 'sales_orders', 10, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:43:34'),
(266, 8, 'APPROVE', 'sales_orders', 8, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:43:36'),
(267, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:43:43'),
(268, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:46:12'),
(269, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:47:05'),
(270, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:48:22'),
(271, 2, 'RELEASE', 'production_batches', 16, NULL, '{\"action\":\"release\",\"qc_notes\":\"GG\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:48:34'),
(272, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:49:05'),
(273, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 06:49:37'),
(274, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 07:25:43'),
(275, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 07:28:23'),
(276, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 07:29:58'),
(277, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 07:30:09'),
(278, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:26:02'),
(279, 6, 'UPDATE_STATUS', 'sales_orders', 10, '{\"status\":\"dispatched\"}', '{\"status\":\"delivered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:26:24'),
(280, 6, 'CREATE', 'sales_orders', 11, NULL, '{\"action\":\"create\",\"customer_id\":4,\"customer_po_number\":\"123456\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":3,\"quantity\":13,\"quantity_boxes\":1,\"quantity_pieces\":1,\"unit_type\":\"mixed\",\"unit_price\":45}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:33:47'),
(281, 6, 'UPDATE_STATUS', 'sales_orders', 11, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:33:50'),
(282, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:33:59'),
(283, 8, 'APPROVE', 'sales_orders', 11, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:34:15'),
(284, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:34:22'),
(285, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:35:26'),
(286, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:48:12'),
(287, 6, 'CREATE', 'sales_orders', 12, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":\"500\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"hybrid\",\"notes\":\"\",\"items\":[{\"product_id\":7,\"quantity\":21,\"quantity_boxes\":1,\"quantity_pieces\":1,\"unit_type\":\"mixed\",\"unit_price\":140}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:48:41'),
(288, 6, 'UPDATE_STATUS', 'sales_orders', 12, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:48:46'),
(289, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:48:55'),
(290, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:49:24'),
(291, 8, 'APPROVE', 'sales_orders', 12, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:49:43'),
(292, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 09:49:53'),
(293, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 10:18:11'),
(294, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 10:19:41'),
(295, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 10:26:42'),
(296, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 10:27:53'),
(297, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 10:33:38'),
(298, 5, 'COMPLETE', 'disposals', 6, '{\"status\":\"approved\"}', '{\"status\":\"completed\",\"disposed_by\":5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 10:34:15'),
(299, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', NULL, NULL, '2026-02-09 12:38:23'),
(300, 2, 'CREATE', 'disposals', 7, NULL, '{\"disposal_code\":\"DSP-20260209-0005\",\"source_type\":\"raw_milk\",\"source_id\":\"5\",\"quantity\":25,\"category\":\"expired\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', NULL, NULL, '2026-02-09 12:38:44'),
(301, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', NULL, NULL, '2026-02-09 12:38:52'),
(302, 8, 'APPROVE', 'disposals', 7, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', NULL, NULL, '2026-02-09 12:38:59'),
(303, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', NULL, NULL, '2026-02-09 12:39:07'),
(304, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', NULL, NULL, '2026-02-09 12:39:52'),
(305, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', NULL, NULL, '2026-02-09 12:40:14'),
(306, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:08:52'),
(307, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:09:25'),
(308, 4, 'approve_requisition', 'material_requisitions', 9, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:09:34'),
(309, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:10:00'),
(310, 2, 'CREATE', 'milk_receiving', 23, NULL, '{\"receiving_code\":\"RCV-20260209-001\",\"rmr_number\":66195,\"farmer_id\":\"2\",\"volume_liters\":\"100\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:10:17'),
(311, 2, 'CREATE', 'qc_milk_tests', 22, NULL, '{\"test_code\":\"QCT-000005\",\"receiving_id\":\"23\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":3000}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:10:29'),
(312, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:10:38'),
(313, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 6, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"100.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:10:49'),
(314, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:11:02'),
(315, 4, 'fulfill_requisition', 'material_requisitions', 9, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:11:11'),
(316, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:11:22'),
(317, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:14:37'),
(318, 2, 'RELEASE', 'production_batches', 17, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:14:48'),
(319, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:15:08'),
(320, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:17:05'),
(321, 6, 'CREATE', 'sales_orders', 13, NULL, '{\"action\":\"create\",\"customer_id\":2,\"customer_po_number\":null,\"sub_account_id\":null,\"delivery_date\":\"2026-02-09\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":3,\"quantity\":22,\"quantity_boxes\":1,\"quantity_pieces\":10,\"unit_type\":\"mixed\",\"unit_price\":45}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:19:41'),
(322, 6, 'UPDATE_STATUS', 'sales_orders', 13, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:19:45'),
(323, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:19:52'),
(324, 8, 'APPROVE', 'sales_orders', 13, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:19:59'),
(325, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:20:11'),
(326, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:25:51'),
(327, 7, 'create', 'payment_collections', 11, NULL, '{\"or_number\":\"OR-2026-00003\",\"dr_number\":\"DR-20260209-6858\",\"amount\":990,\"method\":\"bank_transfer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-09 13:29:59'),
(328, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 10:31:40'),
(329, 10, 'CREATE', 'purchase_orders', 22, NULL, '{\"po_number\":\"5252\",\"supplier_id\":\"5\",\"total_amount\":50,\"items_count\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 10:33:34'),
(330, 10, 'UPDATE', 'purchase_orders', 22, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 10:33:39'),
(331, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 10:33:57'),
(332, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 10:34:29'),
(333, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 10:37:46'),
(334, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 15:03:10'),
(335, 2, 'CREATE', 'yogurt_transformations', 2, NULL, '{\"transformation_code\":\"YTF-000002\",\"source_inventory_id\":\"4\",\"quantity\":96,\"production_run_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 15:05:16'),
(336, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 15:30:22'),
(337, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 15:43:53'),
(338, 11, 'LOGIN', 'users', 11, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 15:47:21'),
(339, 11, 'LOGIN', 'users', 11, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 15:55:45'),
(340, 11, 'LOGIN', 'users', 11, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 15:57:42'),
(341, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 16:35:42'),
(342, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 16:35:54'),
(343, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 16:42:00'),
(344, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 17:05:43'),
(345, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 17:27:22'),
(346, 12, 'LOGIN', 'users', 12, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 17:34:00'),
(347, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 17:54:40'),
(348, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:00:06'),
(349, 11, 'LOGIN', 'users', 11, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:05:02'),
(350, 12, 'LOGIN', 'users', 12, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:05:25'),
(351, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:06:03'),
(352, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:11:07'),
(353, 6, 'CREATE', 'sales_orders', 14, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":null,\"sub_account_id\":null,\"delivery_date\":\"2026-02-10\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":12,\"quantity_boxes\":1,\"quantity_pieces\":0,\"unit_type\":\"box\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:11:34'),
(354, 6, 'UPDATE_STATUS', 'sales_orders', 14, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:11:38'),
(355, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:11:46'),
(356, 8, 'APPROVE', 'sales_orders', 14, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:11:54'),
(357, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:12:02'),
(358, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:12:31'),
(359, 12, 'LOGIN', 'users', 12, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:12:46'),
(360, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:16:35'),
(361, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:17:26'),
(362, 2, 'CREATE', 'milk_receiving', 24, NULL, '{\"receiving_code\":\"RCV-20260211-001\",\"rmr_number\":66196,\"farmer_id\":\"3\",\"volume_liters\":\"100\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:17:36'),
(363, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:17:53'),
(364, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:18:06'),
(365, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:18:21'),
(366, 2, 'CREATE', 'milk_receiving', 25, NULL, '{\"receiving_code\":\"RCV-20260211-002\",\"rmr_number\":66197,\"farmer_id\":\"6\",\"volume_liters\":\"100\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:18:37'),
(367, 2, 'CREATE', 'milk_receiving', 26, NULL, '{\"receiving_code\":\"RCV-20260211-003\",\"rmr_number\":66198,\"farmer_id\":\"3\",\"volume_liters\":\"10\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:19:09'),
(368, 2, 'CREATE', 'milk_receiving', 27, NULL, '{\"receiving_code\":\"RCV-20260210-001\",\"rmr_number\":66199,\"farmer_id\":\"4\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:26:28'),
(369, 2, 'CREATE', 'qc_milk_tests', 23, NULL, '{\"test_code\":\"QCT-000006\",\"receiving_id\":\"27\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:26:40'),
(370, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:27:01'),
(371, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 7, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:27:14'),
(372, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:27:25'),
(373, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:27:54'),
(374, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:28:25'),
(375, 4, 'approve_requisition', 'material_requisitions', 10, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:28:37'),
(376, 4, 'fulfill_requisition', 'material_requisitions', 10, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:28:39'),
(377, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:28:58'),
(378, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:29:40'),
(379, 2, 'RELEASE', 'production_batches', 18, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:29:48'),
(380, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:30:15'),
(381, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:37:53'),
(382, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:38:13'),
(383, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:38:53'),
(384, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:41:35'),
(385, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:44:24'),
(386, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:49:40'),
(387, 2, 'RELEASE', 'production_batches', 19, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:49:59'),
(388, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:50:14'),
(389, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:52:23'),
(390, 2, 'CREATE', 'milk_receiving', 28, NULL, '{\"receiving_code\":\"RCV-20260210-002\",\"rmr_number\":66200,\"farmer_id\":\"8\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:52:37'),
(391, 2, 'CREATE', 'milk_receiving', 29, NULL, '{\"receiving_code\":\"RCV-20260210-003\",\"rmr_number\":66201,\"farmer_id\":\"3\",\"volume_liters\":\"500\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:52:49'),
(392, 2, 'CREATE', 'qc_milk_tests', 24, NULL, '{\"test_code\":\"QCT-000007\",\"receiving_id\":\"28\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:53:03'),
(393, 2, 'CREATE', 'qc_milk_tests', 25, NULL, '{\"test_code\":\"QCT-000008\",\"receiving_id\":\"29\",\"is_accepted\":true,\"final_price_per_liter\":29,\"total_amount\":14500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:53:16'),
(394, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:53:22'),
(395, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 9, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"500.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:53:32');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(396, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 8, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:53:34'),
(397, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:54:44'),
(398, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:55:21'),
(399, 4, 'approve_requisition', 'material_requisitions', 11, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:55:39'),
(400, 4, 'fulfill_requisition', 'material_requisitions', 11, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:55:42'),
(401, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:55:47'),
(402, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:56:31'),
(403, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:56:56'),
(404, 2, 'RELEASE', 'production_batches', 20, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:57:03'),
(405, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-10 18:57:24'),
(406, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 02:42:27'),
(407, 6, 'CREATE', 'sales_orders', 15, NULL, '{\"action\":\"create\",\"customer_id\":5,\"customer_po_number\":\"123456\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-11\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":7,\"quantity\":20,\"quantity_boxes\":1,\"quantity_pieces\":0,\"unit_type\":\"box\",\"unit_price\":140}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 02:42:51'),
(408, 6, 'UPDATE_STATUS', 'sales_orders', 15, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 02:42:57'),
(409, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 02:43:04'),
(410, 8, 'APPROVE', 'sales_orders', 15, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 02:43:10'),
(411, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 02:43:16'),
(412, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 02:54:05'),
(413, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 03:01:39'),
(414, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 03:02:30'),
(415, 6, 'CREATE', 'sales_orders', 16, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":\"65764456\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-11\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":1,\"quantity\":15,\"quantity_boxes\":1,\"quantity_pieces\":3,\"unit_type\":\"mixed\",\"unit_price\":105}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 03:55:39'),
(416, 6, 'UPDATE_STATUS', 'sales_orders', 16, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 03:56:14'),
(417, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 03:56:25'),
(418, 8, 'APPROVE', 'sales_orders', 16, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 03:57:59'),
(419, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 03:58:07'),
(420, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:00:58'),
(421, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:02:48'),
(422, 2, 'RELEASE', 'production_batches', 21, NULL, '{\"action\":\"release\",\"qc_notes\":\"Test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:03:06'),
(423, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:03:30'),
(424, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:04:03'),
(425, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:12:05'),
(426, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:14:14'),
(427, 7, 'create', 'payment_collections', 12, NULL, '{\"or_number\":\"OR-2026-00004\",\"dr_number\":\"DR-20260211-002\",\"amount\":50,\"method\":\"gcash\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:14:49'),
(428, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:16:45'),
(429, 2, 'CREATE', 'disposals', 8, NULL, '{\"disposal_code\":\"DSP-20260211-0001\",\"source_type\":\"raw_milk\",\"source_id\":\"5\",\"quantity\":25,\"category\":\"spoiled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:20:02'),
(430, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:20:32'),
(431, 8, 'APPROVE', 'disposals', 8, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:20:56'),
(432, 8, 'COMPLETE', 'disposals', 8, '{\"status\":\"approved\"}', '{\"status\":\"completed\",\"disposed_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:21:00'),
(433, 12, 'LOGIN', 'users', 12, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:21:15'),
(434, 11, 'LOGIN', 'users', 11, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:21:44'),
(435, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:22:01'),
(436, 12, 'LOGIN', 'users', 12, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:25:04'),
(437, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:25:40'),
(438, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:26:06'),
(439, 6, 'CREATE', 'sales_orders', 17, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":null,\"sub_account_id\":null,\"delivery_date\":\"2026-02-11\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":1,\"quantity\":12,\"quantity_boxes\":1,\"quantity_pieces\":0,\"unit_type\":\"box\",\"unit_price\":105}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:27:21'),
(440, 6, 'UPDATE_STATUS', 'sales_orders', 17, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:28:11'),
(441, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:28:26'),
(442, 8, 'APPROVE', 'sales_orders', 17, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:28:37'),
(443, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:30:12'),
(444, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:37:40'),
(445, 7, 'create', 'payment_collections', 13, NULL, '{\"or_number\":\"OR-2026-00005\",\"dr_number\":\"DR-20260211-003\",\"amount\":1260,\"method\":\"cash\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:39:31'),
(446, 7, 'start_shift', 'cashier_shifts', 2, NULL, '{\"opening_cash\":5000}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:41:32'),
(447, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 04:42:50'),
(448, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 06:04:31'),
(449, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-11 06:20:32'),
(450, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-14 02:07:37'),
(451, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-14 02:24:54'),
(452, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:46:37'),
(453, 2, 'CREATE', 'disposals', 9, NULL, '{\"disposal_code\":\"DSP-20260216-0001\",\"source_type\":\"finished_goods\",\"source_id\":\"20\",\"quantity\":50,\"category\":\"expired\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:46:48'),
(454, 2, 'CREATE', 'disposals', 10, NULL, '{\"disposal_code\":\"DSP-20260216-0002\",\"source_type\":\"finished_goods\",\"source_id\":\"21\",\"quantity\":50,\"category\":\"expired\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:47:44'),
(455, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:47:57'),
(456, 8, 'APPROVE', 'disposals', 10, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:48:06'),
(457, 8, 'APPROVE', 'disposals', 9, '{\"status\":\"pending\"}', '{\"status\":\"approved\",\"approved_by\":8}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:48:07'),
(458, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:48:13'),
(459, 5, 'COMPLETE', 'disposals', 10, '{\"status\":\"approved\"}', '{\"status\":\"completed\",\"disposed_by\":5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:48:28'),
(460, 5, 'COMPLETE', 'disposals', 9, '{\"status\":\"approved\"}', '{\"status\":\"completed\",\"disposed_by\":5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:48:38'),
(461, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-16 11:48:48'),
(462, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 07:58:45'),
(463, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 08:38:35'),
(464, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 14:49:34'),
(465, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 15:42:07'),
(466, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 15:45:01'),
(467, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 15:50:20'),
(468, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 15:50:36'),
(469, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 15:55:38'),
(470, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 15:56:06'),
(471, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 15:57:47'),
(472, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:00:35'),
(473, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:01:08'),
(474, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:01:58'),
(475, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:10:03'),
(476, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:10:35'),
(477, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:12:02'),
(478, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:28:40'),
(479, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:30:53'),
(480, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:32:14'),
(481, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:42:07'),
(482, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:46:11'),
(483, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 16:47:22'),
(484, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:01:30'),
(485, 2, 'RELEASE', 'production_batches', 23, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:04:20'),
(486, 11, 'LOGIN', 'users', 11, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:04:39'),
(487, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:04:57'),
(488, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:05:35'),
(489, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:06:15'),
(490, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:15:44'),
(491, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:17:00'),
(492, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:17:52'),
(493, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:19:37'),
(494, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:20:43'),
(495, 2, 'RELEASE', 'production_batches', 24, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:20:51'),
(496, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:21:05'),
(497, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:22:02'),
(498, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:25:05'),
(499, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:25:46'),
(500, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:36:53'),
(501, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:41:32'),
(502, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:44:43'),
(503, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:45:36'),
(504, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:46:30'),
(505, 6, 'CREATE', 'sales_orders', 18, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":\"34989483943\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-20\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":21,\"quantity\":50,\"quantity_boxes\":0,\"quantity_pieces\":50,\"unit_type\":\"piece\",\"unit_price\":50}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:51:54'),
(506, 6, 'UPDATE_STATUS', 'sales_orders', 18, '{\"status\":\"draft\"}', '{\"status\":\"cancelled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:52:02'),
(507, 6, 'CREATE', 'sales_orders', 19, NULL, '{\"action\":\"create\",\"customer_id\":1,\"customer_po_number\":\"Basta\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-20\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":21,\"quantity\":50,\"quantity_boxes\":0,\"quantity_pieces\":50,\"unit_type\":\"piece\",\"unit_price\":50}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:52:25'),
(508, 6, 'UPDATE_STATUS', 'sales_orders', 19, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:52:28'),
(509, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:52:36'),
(510, 8, 'APPROVE', 'sales_orders', 19, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:52:47'),
(511, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:53:01'),
(512, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 17:53:38'),
(513, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 22:05:58'),
(514, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 22:17:59'),
(515, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 22:23:14'),
(516, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 22:28:30'),
(517, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 22:31:19'),
(518, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 22:34:15'),
(519, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:24:45'),
(520, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:28:11'),
(521, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:29:06'),
(522, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:29:59'),
(523, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:30:30'),
(524, 2, 'RELEASE', 'production_batches', 25, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:30:44'),
(525, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:31:05'),
(526, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:31:39'),
(527, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:33:00'),
(528, 6, 'CREATE', 'sales_orders', 20, NULL, '{\"action\":\"create\",\"customer_id\":3,\"customer_po_number\":\"9894859489534\",\"sub_account_id\":null,\"delivery_date\":\"2026-02-20\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":22,\"quantity\":50,\"quantity_boxes\":0,\"quantity_pieces\":50,\"unit_type\":\"piece\",\"unit_price\":60}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:33:34'),
(529, 6, 'UPDATE_STATUS', 'sales_orders', 20, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:33:39'),
(530, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:33:53'),
(531, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:34:09'),
(532, 8, 'APPROVE', 'sales_orders', 20, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:34:23'),
(533, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:34:32'),
(534, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:36:11'),
(535, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:38:52'),
(536, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:40:57'),
(537, 2, 'RELEASE', 'production_batches', 26, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:41:07'),
(538, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:41:21'),
(539, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:42:51'),
(540, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:43:30'),
(541, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-20 23:45:06'),
(542, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-21 00:29:43'),
(543, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-21 00:54:57'),
(544, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-21 01:27:09'),
(545, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-21 02:51:57'),
(546, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-21 03:03:18'),
(547, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:03:32'),
(548, 10, 'CREATE', 'purchase_orders', 23, NULL, '{\"po_number\":\"5253\",\"supplier_id\":\"5\",\"total_amount\":0,\"payment_terms\":\"credit_30\",\"items_count\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:14:03'),
(549, 10, 'UPDATE', 'purchase_orders', 23, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:14:09'),
(550, 10, 'CREATE', 'purchase_orders', 24, NULL, '{\"po_number\":\"5254\",\"supplier_id\":\"4\",\"total_amount\":50,\"payment_terms\":\"credit_30\",\"items_count\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:14:40'),
(551, 10, 'UPDATE', 'purchase_orders', 24, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:14:43'),
(552, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:14:49'),
(553, 8, 'APPROVE', 'purchase_orders', 22, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:30:56'),
(554, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-22 09:31:06'),
(555, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-26 12:00:43'),
(556, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-26 15:41:51'),
(557, 2, 'CREATE', 'milk_receiving', 30, NULL, '{\"receiving_code\":\"RCV-20260226-001\",\"rmr_number\":66202,\"farmer_id\":\"6\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-26 15:42:03'),
(558, 10, 'LOGIN', 'users', 10, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-26 15:43:03'),
(559, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-02-27 12:36:12'),
(560, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:07:26'),
(561, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:18:14'),
(562, 8, 'LOGOUT', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:18:48'),
(563, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:18:55'),
(564, 2, 'LOGOUT', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:34:20'),
(565, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:47:00'),
(566, 2, 'CREATE', 'milk_receiving', 31, NULL, '{\"receiving_code\":\"RCV-20260313-001\",\"rmr_number\":66203,\"farmer_id\":\"3\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:47:13'),
(567, 2, 'CREATE', 'milk_receiving', 32, NULL, '{\"receiving_code\":\"RCV-20260313-002\",\"rmr_number\":66204,\"farmer_id\":\"3\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:47:47'),
(568, 2, 'CREATE', 'qc_milk_tests', 26, NULL, '{\"test_code\":\"QCT-000009\",\"receiving_id\":\"32\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', NULL, NULL, '2026-03-13 12:47:58'),
(569, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"b61b7ebc3ec36540025e17c86ffdd7dc1f49d23a467735b934275258a267ddd7\"}', '{\"authenticated\":false,\"reason\":\"idle_timeout\",\"session_id\":\"b61b7ebc3ec36540025e17c86ffdd7dc1f49d23a467735b934275258a267ddd7\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0000000000000000000000000000000000000000000000000000000000000000', '88bdd9e181ce25edc3c232d0b624fe6acd3c6d241ec6469b80166dfa34b040cf', '2026-03-13 13:49:20'),
(570, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"723e75cb9e22c2eecb331e0bf1dee2e0901fd6b1f1c3f2e15ff26540a5d2bcea\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '88bdd9e181ce25edc3c232d0b624fe6acd3c6d241ec6469b80166dfa34b040cf', 'a2f15f1d32cb59262fef6e1739fef65f93f3d5a5015407c828ecc900222499d5', '2026-03-14 01:30:11'),
(571, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"723e75cb9e22c2eecb331e0bf1dee2e0901fd6b1f1c3f2e15ff26540a5d2bcea\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"723e75cb9e22c2eecb331e0bf1dee2e0901fd6b1f1c3f2e15ff26540a5d2bcea\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a2f15f1d32cb59262fef6e1739fef65f93f3d5a5015407c828ecc900222499d5', 'e50806ba0f4138274a8f52ce74aa7341ecef701daf64f10c67d6458582f149f5', '2026-03-14 01:45:19'),
(572, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"648789360f60d1c0f253aaed12923f5bf032539d65ecc593887d3179e889d121\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e50806ba0f4138274a8f52ce74aa7341ecef701daf64f10c67d6458582f149f5', 'ae0aa03910a1c2cb7179ef13dfed919e496726712b0689a5d73097c8f0f91d34', '2026-03-14 01:45:29'),
(573, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"675cd2fec6efaff6253e58061705a8d50adf17b3d075d35eacd77a4cd754cbdc\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ae0aa03910a1c2cb7179ef13dfed919e496726712b0689a5d73097c8f0f91d34', 'f80596ad10a794258c1fd6cbeffdb637f396d0daef24d78ba6aebaa87ea40b1c', '2026-03-14 02:42:29'),
(574, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"675cd2fec6efaff6253e58061705a8d50adf17b3d075d35eacd77a4cd754cbdc\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"675cd2fec6efaff6253e58061705a8d50adf17b3d075d35eacd77a4cd754cbdc\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f80596ad10a794258c1fd6cbeffdb637f396d0daef24d78ba6aebaa87ea40b1c', 'c06b5b817f97e129e2d411fefb4d1af6252eda788ae74626ee22d7cf83a11f1b', '2026-03-14 02:42:35'),
(575, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"0c306bb0a1e83502cebb72b5fec8f7310f7e8098ddcdf4b4b751b4848d83004f\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c06b5b817f97e129e2d411fefb4d1af6252eda788ae74626ee22d7cf83a11f1b', '6b355e621663a9594d55d40958202ade4f09c224e285d3172be4e5c6865d3c01', '2026-03-21 02:54:33'),
(576, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"0c306bb0a1e83502cebb72b5fec8f7310f7e8098ddcdf4b4b751b4848d83004f\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"0c306bb0a1e83502cebb72b5fec8f7310f7e8098ddcdf4b4b751b4848d83004f\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6b355e621663a9594d55d40958202ade4f09c224e285d3172be4e5c6865d3c01', '8c9986b85f78fc48dcbc8c8822633fbf7c93295a2bb7c48eac9cde87718e00a6', '2026-03-21 03:26:33'),
(577, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"ca8d47cfb6623770945ca3658dfa708b073b5c125f5363b34dff12fba21d9c48\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8c9986b85f78fc48dcbc8c8822633fbf7c93295a2bb7c48eac9cde87718e00a6', '174535aa1a7ca3bc313256005de50bf4185cdf0181876cd6c344682ebbe8fcd5', '2026-03-21 03:28:52'),
(578, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"52eb9e189f2d1be52704cd7b0328a2c793d6b8957ca638a3a6ff4fe434aad195\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '174535aa1a7ca3bc313256005de50bf4185cdf0181876cd6c344682ebbe8fcd5', 'ca3b8c390de3a20378b4276fa149afceb45c8fb7ad03a9f929de38cc679147fe', '2026-03-28 07:15:23'),
(579, 2, 'CREATE', 'milk_receiving', 33, NULL, '{\"farmer_id\":\"4\",\"receiving_code\":\"RCV-20260328-001\",\"rmr_number\":66205,\"volume_liters\":\"40\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ca3b8c390de3a20378b4276fa149afceb45c8fb7ad03a9f929de38cc679147fe', 'c72789696656dd6da9bc4ec4c58920b1a91a560a61f411a779a5f190d24e49d6', '2026-03-28 07:15:44'),
(580, 2, 'CREATE', 'qc_milk_tests', 27, NULL, '{\"final_price_per_liter\":30,\"is_accepted\":true,\"receiving_id\":\"33\",\"test_code\":\"QCT-000010\",\"total_amount\":1200}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c72789696656dd6da9bc4ec4c58920b1a91a560a61f411a779a5f190d24e49d6', 'aa875f6a2f0d89d01c5784349e7d87918bb08b2f2972ae18f47c2d9b4f59277c', '2026-03-28 07:15:59'),
(581, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"52eb9e189f2d1be52704cd7b0328a2c793d6b8957ca638a3a6ff4fe434aad195\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"52eb9e189f2d1be52704cd7b0328a2c793d6b8957ca638a3a6ff4fe434aad195\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'aa875f6a2f0d89d01c5784349e7d87918bb08b2f2972ae18f47c2d9b4f59277c', 'a62de087386e5798a768e67f442bb1ec6e848d0b643397dbcfaf1bbcd0889153', '2026-03-28 07:16:05'),
(582, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"d035e5ae95a7d691cb453ceac436d7ed22d2abb9857c2bdad4ebb4c435180ccd\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a62de087386e5798a768e67f442bb1ec6e848d0b643397dbcfaf1bbcd0889153', 'af7f6d62b7618caedfe894cdbdc6a60fbf858fcaee6d9febef414e3e4b829972', '2026-03-28 07:16:13'),
(583, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 11, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"40.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'af7f6d62b7618caedfe894cdbdc6a60fbf858fcaee6d9febef414e3e4b829972', 'd638f5f9711406db75155f673f6f6c490e7b2c22d90639a16ed0fb5acded0b87', '2026-03-28 07:16:36'),
(584, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 10, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd638f5f9711406db75155f673f6f6c490e7b2c22d90639a16ed0fb5acded0b87', '50325cef5b3fb687fea074e7218b3192ba24af70b8444535f3055c5ecc0da7bf', '2026-03-28 07:16:50'),
(585, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"d035e5ae95a7d691cb453ceac436d7ed22d2abb9857c2bdad4ebb4c435180ccd\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d035e5ae95a7d691cb453ceac436d7ed22d2abb9857c2bdad4ebb4c435180ccd\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '50325cef5b3fb687fea074e7218b3192ba24af70b8444535f3055c5ecc0da7bf', 'feea33815a96a332773313a8ff9e4d6965ddc28fff70c7c3d76b2a4ab6d51d1e', '2026-03-28 07:17:23');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(586, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"de04c0df38e9a6460a1ebe657123ea032d5f3086504b993cdf1c08c341c2ead2\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'feea33815a96a332773313a8ff9e4d6965ddc28fff70c7c3d76b2a4ab6d51d1e', 'e1742b4e8a1b2cad1fed310c904c45377c3870562d20ef85ebf238544bc7462d', '2026-03-28 07:17:27'),
(587, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"de04c0df38e9a6460a1ebe657123ea032d5f3086504b993cdf1c08c341c2ead2\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"de04c0df38e9a6460a1ebe657123ea032d5f3086504b993cdf1c08c341c2ead2\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e1742b4e8a1b2cad1fed310c904c45377c3870562d20ef85ebf238544bc7462d', '3ccd8ac80717d61c05397a2fd47ff67557c9247d74d38a5e98cffb12d867db77', '2026-03-28 07:17:50'),
(588, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"6492a323fdc07aeb34552814327a6b6a2921a0e78219646ba153690feca5cabb\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3ccd8ac80717d61c05397a2fd47ff67557c9247d74d38a5e98cffb12d867db77', 'a964d12e91f9a5956234e9e3d1e19264b83ba6cf072e51d9d5dcba0545388af6', '2026-03-28 07:18:03'),
(589, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 12, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"112.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a964d12e91f9a5956234e9e3d1e19264b83ba6cf072e51d9d5dcba0545388af6', 'dea36907b5da5e20e232ad65de6ad1fe5c7d83ae36dd298fcd02131fc66023c9', '2026-03-28 07:22:54'),
(590, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 25, NULL, '{\"tank_code\":\"PT-001\",\"tank_id\":9,\"volume_liters\":\"401.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'dea36907b5da5e20e232ad65de6ad1fe5c7d83ae36dd298fcd02131fc66023c9', '3aad5ff7a782a1310be5126db0e56b510c2fd7cc2888b413b806680823b0ccab', '2026-03-28 07:23:27'),
(591, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 13, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"93.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3aad5ff7a782a1310be5126db0e56b510c2fd7cc2888b413b806680823b0ccab', '1688c95087529c93c326b8f3851ca01daad119d9ae57f9b00c2fb3cebc489364', '2026-03-28 07:24:50'),
(592, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 14, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"59.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1688c95087529c93c326b8f3851ca01daad119d9ae57f9b00c2fb3cebc489364', 'cf0e024d37c3e049bdcb0e47a1cbb8e927dfea2525a8af25540e6ec713ff3cdf', '2026-03-28 07:25:17'),
(593, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 15, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"40.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cf0e024d37c3e049bdcb0e47a1cbb8e927dfea2525a8af25540e6ec713ff3cdf', 'd0bd91b372c2f70d534bbb8fec7c8e13ab5571232b92672834bc016194142293', '2026-03-28 07:27:29'),
(594, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"6492a323fdc07aeb34552814327a6b6a2921a0e78219646ba153690feca5cabb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6492a323fdc07aeb34552814327a6b6a2921a0e78219646ba153690feca5cabb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd0bd91b372c2f70d534bbb8fec7c8e13ab5571232b92672834bc016194142293', 'b1862e13853774d3d682014403cd68ad6a5233bf916b64d90c1d3429ba1113bc', '2026-03-28 07:27:50'),
(595, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"adc994fab9716bf716f688c5698e8787e749cd5ae0df24f63bcd04ccfdb32186\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b1862e13853774d3d682014403cd68ad6a5233bf916b64d90c1d3429ba1113bc', '0919dbc8a6afe1083bdc5d0720b1906164c02a75a8a5c0afe6f08147f690d57d', '2026-03-28 07:28:50'),
(596, 2, 'CREATE', 'milk_receiving', 34, NULL, '{\"farmer_id\":\"4\",\"receiving_code\":\"RCV-20260328-002\",\"rmr_number\":66206,\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0919dbc8a6afe1083bdc5d0720b1906164c02a75a8a5c0afe6f08147f690d57d', 'e15c00d08e1cffc798aecc7db0e2118d4713724c78d6f565cf21d8da1c8b4f15', '2026-03-28 07:29:14'),
(597, 2, 'CREATE', 'qc_milk_tests', 28, NULL, '{\"final_price_per_liter\":30,\"is_accepted\":true,\"receiving_id\":\"34\",\"test_code\":\"QCT-000011\",\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e15c00d08e1cffc798aecc7db0e2118d4713724c78d6f565cf21d8da1c8b4f15', '544547ecfe437aedf7df4c2cdfc042356db62c1f11ae95dc98a92eea6573fdc5', '2026-03-28 07:29:45'),
(598, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"adc994fab9716bf716f688c5698e8787e749cd5ae0df24f63bcd04ccfdb32186\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"adc994fab9716bf716f688c5698e8787e749cd5ae0df24f63bcd04ccfdb32186\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '544547ecfe437aedf7df4c2cdfc042356db62c1f11ae95dc98a92eea6573fdc5', 'b7d416b14a0390a2dcd14652c35586255f4f81da9a07ab28c5e6c4d4e50a236e', '2026-03-28 07:29:52'),
(599, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"16f58e6d95eb1059b03aa1cec67dd413b26a69e92967c508f17019875ce3e703\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b7d416b14a0390a2dcd14652c35586255f4f81da9a07ab28c5e6c4d4e50a236e', '296c2fac76e760e4be8716939229285ba0a97beadbfb4e05a38857dfcbf89dc2', '2026-03-28 07:29:59'),
(600, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 27, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '296c2fac76e760e4be8716939229285ba0a97beadbfb4e05a38857dfcbf89dc2', 'a4befc31aa8311207dbf3ab08479f8439ca0ed88bfc99917887987c38540f073', '2026-03-28 07:30:27'),
(601, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"16f58e6d95eb1059b03aa1cec67dd413b26a69e92967c508f17019875ce3e703\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"16f58e6d95eb1059b03aa1cec67dd413b26a69e92967c508f17019875ce3e703\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a4befc31aa8311207dbf3ab08479f8439ca0ed88bfc99917887987c38540f073', 'f67c21f1d16661d87af014d05c7ebf6a8404c61b9a537ec7a7384a69ee6fc736', '2026-03-28 07:30:40'),
(602, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"589aa652240fc72cb384e1a9d36e641fd9b1dd649ad79dee63bb34939f2fe51b\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f67c21f1d16661d87af014d05c7ebf6a8404c61b9a537ec7a7384a69ee6fc736', 'c481f52e0dc77a295aba83d467af929711dd39e61b50ca8e148b05881fc43814', '2026-03-28 07:30:45'),
(603, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"589aa652240fc72cb384e1a9d36e641fd9b1dd649ad79dee63bb34939f2fe51b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"589aa652240fc72cb384e1a9d36e641fd9b1dd649ad79dee63bb34939f2fe51b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c481f52e0dc77a295aba83d467af929711dd39e61b50ca8e148b05881fc43814', '77ec8742b56ab290d9c55ba03061720dd2977bfd1eb6b3a6658c83df047c8fac', '2026-03-28 07:32:19'),
(604, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"a72bdbd380ff8ca8b845eead50af933e18e8f2851fc8b8806d86ea796c89c1bb\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '77ec8742b56ab290d9c55ba03061720dd2977bfd1eb6b3a6658c83df047c8fac', '74a0abdd97699f459a8348deb66418ce9a0e0a66379566786ccdc77a3805ac5a', '2026-03-28 07:32:23'),
(605, 2, 'RELEASE', 'production_batches', 27, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '74a0abdd97699f459a8348deb66418ce9a0e0a66379566786ccdc77a3805ac5a', 'a49452fcbee10c80bff197c5a6226b8b4c66b33c41979124c1c6694fbe4694b0', '2026-03-28 07:32:38'),
(606, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"a72bdbd380ff8ca8b845eead50af933e18e8f2851fc8b8806d86ea796c89c1bb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"a72bdbd380ff8ca8b845eead50af933e18e8f2851fc8b8806d86ea796c89c1bb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a49452fcbee10c80bff197c5a6226b8b4c66b33c41979124c1c6694fbe4694b0', 'ac25e131af75578930e401adb3a80ae0883a5e0e598fa1a25c536df0e7041290', '2026-03-28 07:32:54'),
(607, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"b9675e990f203cbcb7144436dc6ebf1a5249f92ae6994745ff6b841dcf34f95c\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ac25e131af75578930e401adb3a80ae0883a5e0e598fa1a25c536df0e7041290', 'e033564fc5367619f6c6349957e4584251f645b4b779c76a5401a34749657c2d', '2026-03-28 07:33:01'),
(608, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"b9675e990f203cbcb7144436dc6ebf1a5249f92ae6994745ff6b841dcf34f95c\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"b9675e990f203cbcb7144436dc6ebf1a5249f92ae6994745ff6b841dcf34f95c\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e033564fc5367619f6c6349957e4584251f645b4b779c76a5401a34749657c2d', 'fcbca76d975a9a98924f222288ed6d967f43dd7fcd683a233789e223cb3da256', '2026-03-28 07:33:13'),
(609, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"f1b489e6d994438def6bda0faeac5eb1628c1939b17ca6eaa9b007ad903aa0b1\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'fcbca76d975a9a98924f222288ed6d967f43dd7fcd683a233789e223cb3da256', 'd92399319785001e9a642dc1cb4e560087d20b64120c286bd8b2d7192f562d82', '2026-03-28 07:33:30'),
(610, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"f1b489e6d994438def6bda0faeac5eb1628c1939b17ca6eaa9b007ad903aa0b1\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"f1b489e6d994438def6bda0faeac5eb1628c1939b17ca6eaa9b007ad903aa0b1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd92399319785001e9a642dc1cb4e560087d20b64120c286bd8b2d7192f562d82', '73a71280e2c406b69d64fc30354768c94590dfd81fece4f668437bfbdc9372a2', '2026-03-28 07:33:51'),
(611, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"909695504195e54145029588b9ad0ceeaf3eff396382aa0f31b974aa0b385c36\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '73a71280e2c406b69d64fc30354768c94590dfd81fece4f668437bfbdc9372a2', '9161931cf83e947549776e0a37b46265a762b5825dde43aaaf9e8565132286fb', '2026-03-28 07:34:08'),
(612, 2, 'CREATE', 'milk_receiving', 35, NULL, '{\"farmer_id\":\"1\",\"receiving_code\":\"RCV-20260328-003\",\"rmr_number\":66207,\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9161931cf83e947549776e0a37b46265a762b5825dde43aaaf9e8565132286fb', '9d4d842012a2860ffafdf7d74f7524641d9ac0bfa6ce7972c3285fe1b3723bde', '2026-03-28 07:34:24'),
(613, 2, 'CREATE', 'qc_milk_tests', 29, NULL, '{\"final_price_per_liter\":30,\"is_accepted\":true,\"receiving_id\":\"35\",\"test_code\":\"QCT-000012\",\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9d4d842012a2860ffafdf7d74f7524641d9ac0bfa6ce7972c3285fe1b3723bde', 'b97f6c51a9dc7955b6b78c8633c403a635551abfa909aed1ec575cf95b822048', '2026-03-28 07:34:42'),
(614, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"909695504195e54145029588b9ad0ceeaf3eff396382aa0f31b974aa0b385c36\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"909695504195e54145029588b9ad0ceeaf3eff396382aa0f31b974aa0b385c36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b97f6c51a9dc7955b6b78c8633c403a635551abfa909aed1ec575cf95b822048', '0d8fb80481b620158e95ae6e72669dde5d6480652bd1556eded5f1e2191ac6f9', '2026-03-28 07:34:54'),
(615, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"b891901529a9b7e7abea80ee830d6e2db502bbf26a0f6254a895ebdc010f49b0\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0d8fb80481b620158e95ae6e72669dde5d6480652bd1556eded5f1e2191ac6f9', '98c1e113402f9483ee12d10981d1d52a9d0f2762f4c25a24df722f68ad85c736', '2026-03-28 07:34:57'),
(616, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 28, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '98c1e113402f9483ee12d10981d1d52a9d0f2762f4c25a24df722f68ad85c736', 'b8dd710305593e887da129b364aea4aba93a37533bf07fd06c7e537e326ad87f', '2026-03-28 07:35:21'),
(617, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"b891901529a9b7e7abea80ee830d6e2db502bbf26a0f6254a895ebdc010f49b0\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"b891901529a9b7e7abea80ee830d6e2db502bbf26a0f6254a895ebdc010f49b0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b8dd710305593e887da129b364aea4aba93a37533bf07fd06c7e537e326ad87f', '48440d46842faf22d38dec7384374fb168dd6cbd231fb37ffe14a37a7b5ea133', '2026-03-28 07:35:37'),
(618, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"15b898cdb5586476ff5896e58e623a56a88b3149fad04864f81288e4072cdab7\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '48440d46842faf22d38dec7384374fb168dd6cbd231fb37ffe14a37a7b5ea133', '372a974e53034f13cf2fd29863bc6c39ee1e7bd57de4ccafbd81492bdbcae0cf', '2026-03-28 07:35:41'),
(619, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"15b898cdb5586476ff5896e58e623a56a88b3149fad04864f81288e4072cdab7\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"15b898cdb5586476ff5896e58e623a56a88b3149fad04864f81288e4072cdab7\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '372a974e53034f13cf2fd29863bc6c39ee1e7bd57de4ccafbd81492bdbcae0cf', '931cc89531083a51a0a1b41e08214fb01a7b53ff7e5c95b9657cc26d3b06e3a3', '2026-03-28 07:37:22'),
(620, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"34f7ce37df8a774e3e48747e24517ec5fedc7718bf17b5e4ee7087e881e128d3\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '931cc89531083a51a0a1b41e08214fb01a7b53ff7e5c95b9657cc26d3b06e3a3', 'cff014acc20bd574c1e07b362c423f98d472db14c88d49aebe49980ab78b29b4', '2026-03-28 07:37:26'),
(621, 2, 'RELEASE', 'production_batches', 28, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cff014acc20bd574c1e07b362c423f98d472db14c88d49aebe49980ab78b29b4', 'd62ff4b2129a06e30447685912136a49e961427c232b067c92f35af92e34f422', '2026-03-28 07:37:40'),
(622, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"34f7ce37df8a774e3e48747e24517ec5fedc7718bf17b5e4ee7087e881e128d3\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"34f7ce37df8a774e3e48747e24517ec5fedc7718bf17b5e4ee7087e881e128d3\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd62ff4b2129a06e30447685912136a49e961427c232b067c92f35af92e34f422', 'af6bfa37a089e6a90d31ddfdae1b20cda536aaea0a380bad290b460d50b8fbf8', '2026-03-28 07:37:52'),
(623, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"ab30f64c88058e9ee8d175d5ac058c2cc49c3d7f3bdece6dff9abe890608ccb8\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'af6bfa37a089e6a90d31ddfdae1b20cda536aaea0a380bad290b460d50b8fbf8', '9d10409ed8d0c3f5e66ff84fe1aa9f58bb335716fbb1d2c33a6c979e21ff2f1f', '2026-03-28 07:37:56'),
(624, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"ab30f64c88058e9ee8d175d5ac058c2cc49c3d7f3bdece6dff9abe890608ccb8\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"ab30f64c88058e9ee8d175d5ac058c2cc49c3d7f3bdece6dff9abe890608ccb8\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9d10409ed8d0c3f5e66ff84fe1aa9f58bb335716fbb1d2c33a6c979e21ff2f1f', '8a601a44c05923ace7e88be54d224cf9d43e0f086b6880177a2bb07396ef0da1', '2026-03-28 07:39:05'),
(625, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"71392808c01e2d0d2027477a41cd3d0ac6e33fca059a6bdeb75d8b7b2dd499e6\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8a601a44c05923ace7e88be54d224cf9d43e0f086b6880177a2bb07396ef0da1', 'be91440a6f11b774aa32cad126481abbb85fffdd84a99e1b22b16d66bddf2c55', '2026-03-28 07:39:08'),
(626, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"71392808c01e2d0d2027477a41cd3d0ac6e33fca059a6bdeb75d8b7b2dd499e6\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"71392808c01e2d0d2027477a41cd3d0ac6e33fca059a6bdeb75d8b7b2dd499e6\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'be91440a6f11b774aa32cad126481abbb85fffdd84a99e1b22b16d66bddf2c55', '24b2f6186f531741809924b025612c044b8488196ad67b6d17562c2e39f7bdc3', '2026-03-28 07:39:49'),
(627, 6, 'LOGIN', 'users', 6, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"eda02c920f841c5f444905e8a112cf15158b007935387231462940048db2a748\",\"username\":\"sales_custodian\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '24b2f6186f531741809924b025612c044b8488196ad67b6d17562c2e39f7bdc3', 'f8e6e0e486a239d27c994e40cf2339e9cb28b2357cb2d4e1e413d4d78adbd2c0', '2026-03-28 07:39:58'),
(628, 6, 'CREATE', 'sales_orders', 21, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":\"Basta\",\"delivery_date\":\"2026-03-28\",\"items\":[{\"product_id\":21,\"quantity\":100,\"quantity_boxes\":10,\"quantity_pieces\":0,\"unit_price\":50,\"unit_type\":\"box\"}],\"notes\":\"\",\"payment_mode\":\"cash\",\"sub_account_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f8e6e0e486a239d27c994e40cf2339e9cb28b2357cb2d4e1e413d4d78adbd2c0', 'f4b11d02a3b064ae62451fe6d3d7610ef98984606e9dad1fd75928ccef658421', '2026-03-28 07:40:31'),
(629, 6, 'UPDATE_STATUS', 'sales_orders', 21, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f4b11d02a3b064ae62451fe6d3d7610ef98984606e9dad1fd75928ccef658421', 'abf5819ba4bf94ff7b7051969a4207d18e2fb54e6218d0174cd4c502e72e6692', '2026-03-28 07:40:38'),
(630, 6, 'LOGOUT', 'users', 6, '{\"authenticated\":true,\"session_id\":\"eda02c920f841c5f444905e8a112cf15158b007935387231462940048db2a748\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"eda02c920f841c5f444905e8a112cf15158b007935387231462940048db2a748\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'abf5819ba4bf94ff7b7051969a4207d18e2fb54e6218d0174cd4c502e72e6692', '23970b90c930c7e4c09eb9df9718b24490bc4ae2f42095caf1f45ecad5d046f8', '2026-03-28 07:40:43'),
(631, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"98453efc37d68c9efbf205d806ab40bb6ea32df9ee0d403f8d16117f10fb8d59\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '23970b90c930c7e4c09eb9df9718b24490bc4ae2f42095caf1f45ecad5d046f8', 'ccb7189ae9a04f105e2202dd6131012530fdf0730cdb8340a058fc60561e4d2b', '2026-03-28 07:40:47'),
(632, 8, 'APPROVE', 'sales_orders', 21, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ccb7189ae9a04f105e2202dd6131012530fdf0730cdb8340a058fc60561e4d2b', '5e0475d278e6daa12570ed0dd1600007d0674a6f9ffa8770ca6faeb05c892cf7', '2026-03-28 07:41:16'),
(633, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"53628af4088c153a0d50866101783dbf010b5ab3c7bab935cd28e475856180ef\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5e0475d278e6daa12570ed0dd1600007d0674a6f9ffa8770ca6faeb05c892cf7', '943bc6cce3a445b0257caad6ebdcf5b8b876ef080e9271d49c489178756dbd39', '2026-03-28 07:41:27'),
(634, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"53628af4088c153a0d50866101783dbf010b5ab3c7bab935cd28e475856180ef\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"53628af4088c153a0d50866101783dbf010b5ab3c7bab935cd28e475856180ef\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '943bc6cce3a445b0257caad6ebdcf5b8b876ef080e9271d49c489178756dbd39', '5036757bbb9aeb6ff3f0ca4456e24541b3d3f24351aebeb19091f974836d0d85', '2026-03-28 07:42:20'),
(635, 6, 'LOGIN', 'users', 6, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"2dcc92994e678a1ca7b02162eecbe0b1004a15e7c483f88c3f6be2fcfa091fec\",\"username\":\"sales_custodian\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5036757bbb9aeb6ff3f0ca4456e24541b3d3f24351aebeb19091f974836d0d85', 'f91476051cb0fe53225be10d4a96d4f67cb0457edf41f1cf98a62f6361cf0518', '2026-03-28 07:42:28'),
(636, 6, 'LOGOUT', 'users', 6, '{\"authenticated\":true,\"session_id\":\"2dcc92994e678a1ca7b02162eecbe0b1004a15e7c483f88c3f6be2fcfa091fec\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"2dcc92994e678a1ca7b02162eecbe0b1004a15e7c483f88c3f6be2fcfa091fec\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f91476051cb0fe53225be10d4a96d4f67cb0457edf41f1cf98a62f6361cf0518', '44bf9a1be0265b89a04ab57c42238f00d33198c8fe5dcca5f45e510301f01442', '2026-03-28 07:42:47'),
(637, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"ed0b53de387203e91d30107d5d95662cc4c670063be601ad9fa1c7af77954b8a\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '44bf9a1be0265b89a04ab57c42238f00d33198c8fe5dcca5f45e510301f01442', '6697597ca6c659d21595af5f464978dd328d9517faa5fb3c873f562e468e3e62', '2026-03-28 07:42:54'),
(638, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"ed0b53de387203e91d30107d5d95662cc4c670063be601ad9fa1c7af77954b8a\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"ed0b53de387203e91d30107d5d95662cc4c670063be601ad9fa1c7af77954b8a\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6697597ca6c659d21595af5f464978dd328d9517faa5fb3c873f562e468e3e62', 'cbef5ca92c9ab0409a2b358a94019c521729270469a15ed83af48e5c9b7d5f66', '2026-03-28 07:45:41'),
(639, 6, 'LOGIN', 'users', 6, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"805062c0b98c84fdad7f545ac3b17a387a11b183401e056701176b02af1d0815\",\"username\":\"sales_custodian\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cbef5ca92c9ab0409a2b358a94019c521729270469a15ed83af48e5c9b7d5f66', '7c2de6a813a58e63887cda1127ffe3100ef6f50ca3d1a04ebf816810ac9406c3', '2026-03-28 07:45:53'),
(640, 6, 'LOGOUT', 'users', 6, '{\"authenticated\":true,\"session_id\":\"805062c0b98c84fdad7f545ac3b17a387a11b183401e056701176b02af1d0815\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"805062c0b98c84fdad7f545ac3b17a387a11b183401e056701176b02af1d0815\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7c2de6a813a58e63887cda1127ffe3100ef6f50ca3d1a04ebf816810ac9406c3', '091e8766a8e88360a5f5da46fcb1b818e43621818440f3fe8737585e7b7c9a19', '2026-03-28 07:46:18'),
(641, 6, 'LOGIN', 'users', 6, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"75241b08f312318b94c24a6873f4639a9f0a1f1aee64b75a5e47df5c27424607\",\"username\":\"sales_custodian\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '091e8766a8e88360a5f5da46fcb1b818e43621818440f3fe8737585e7b7c9a19', '51e3288faff5ed9a5b24118d7ca2481af679576f831faeacb8859490b238563e', '2026-03-28 07:48:12'),
(642, 6, 'LOGOUT', 'users', 6, '{\"authenticated\":true,\"session_id\":\"75241b08f312318b94c24a6873f4639a9f0a1f1aee64b75a5e47df5c27424607\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"75241b08f312318b94c24a6873f4639a9f0a1f1aee64b75a5e47df5c27424607\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '51e3288faff5ed9a5b24118d7ca2481af679576f831faeacb8859490b238563e', 'f329346c089f9a9a26faa016eafe944c07ede5912015459407128d2f607dacef', '2026-03-28 07:48:21'),
(643, 6, 'LOGIN', 'users', 6, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"3d4135aafcda7d0d8a7771740b11cca7270a3ac7e1155f5776cdb3f3077d3f37\",\"username\":\"sales_custodian\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f329346c089f9a9a26faa016eafe944c07ede5912015459407128d2f607dacef', '8471fb2a2be747cf8f7a623238744c82cc7bd5f6f6a5d92dcd48056979d9269d', '2026-03-28 07:48:37'),
(644, 6, 'CREATE', 'sales_orders', 22, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":\"Basta\",\"delivery_date\":\"2026-03-28\",\"items\":[{\"product_id\":21,\"quantity\":20,\"quantity_boxes\":2,\"quantity_pieces\":0,\"unit_price\":50,\"unit_type\":\"box\"}],\"notes\":\"\",\"payment_mode\":\"cash\",\"sub_account_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8471fb2a2be747cf8f7a623238744c82cc7bd5f6f6a5d92dcd48056979d9269d', '6f0a3051a74162ec998911b6f8ecb59a923a51780300b0d389ad9685b845ef53', '2026-03-28 07:49:02'),
(645, 6, 'UPDATE_STATUS', 'sales_orders', 22, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6f0a3051a74162ec998911b6f8ecb59a923a51780300b0d389ad9685b845ef53', '1a8003763ddcfb8382f03c34f926a99e2c6b3f5605545be53a76d10b3c0897ca', '2026-03-28 07:49:10'),
(646, 6, 'LOGOUT', 'users', 6, '{\"authenticated\":true,\"session_id\":\"3d4135aafcda7d0d8a7771740b11cca7270a3ac7e1155f5776cdb3f3077d3f37\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"3d4135aafcda7d0d8a7771740b11cca7270a3ac7e1155f5776cdb3f3077d3f37\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1a8003763ddcfb8382f03c34f926a99e2c6b3f5605545be53a76d10b3c0897ca', '823b238a80a4a63d2686fdde865861548829392a9fb85f85c48edf5a07753cee', '2026-03-28 07:49:17'),
(647, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"1ef929bf2668767f57bdaebcb37f9607abb978fab83d4e83f22df85e1b789890\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '823b238a80a4a63d2686fdde865861548829392a9fb85f85c48edf5a07753cee', 'd335ed4aef96372ff27b1a38fb654331f9d0720d6065013184275c79e0709807', '2026-03-28 07:49:21'),
(648, 8, 'APPROVE', 'sales_orders', 22, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd335ed4aef96372ff27b1a38fb654331f9d0720d6065013184275c79e0709807', '789fddb89c3d37e998e6170b6cad10c1577489b9934a6389a57dd7f0a4577098', '2026-03-28 07:49:30'),
(649, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"30b33dcacf497dc746417b4c6116e2a8f843a7545589a50eb03bb989d7e7cb04\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '789fddb89c3d37e998e6170b6cad10c1577489b9934a6389a57dd7f0a4577098', '26075e798cbeced963c65fd64e313f32ba28a5c7a753f7fd612c63bebc2d5f9c', '2026-03-28 07:49:41'),
(650, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"30b33dcacf497dc746417b4c6116e2a8f843a7545589a50eb03bb989d7e7cb04\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"30b33dcacf497dc746417b4c6116e2a8f843a7545589a50eb03bb989d7e7cb04\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '26075e798cbeced963c65fd64e313f32ba28a5c7a753f7fd612c63bebc2d5f9c', '4125a2b818dd24f6da7a5eeb83c66d8b4952094d8fc8069ae920536aa4594b1b', '2026-03-28 07:50:15'),
(651, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"c886a0aa51915534f7cefab97f356f2f1fd0bf98508edc295e18245949dd4044\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4125a2b818dd24f6da7a5eeb83c66d8b4952094d8fc8069ae920536aa4594b1b', 'ad90b83303e5d87f94419f2bb88d1cb5eb926cff396e627502fdec5591b47ad4', '2026-03-28 07:50:21'),
(652, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"c886a0aa51915534f7cefab97f356f2f1fd0bf98508edc295e18245949dd4044\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"c886a0aa51915534f7cefab97f356f2f1fd0bf98508edc295e18245949dd4044\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ad90b83303e5d87f94419f2bb88d1cb5eb926cff396e627502fdec5591b47ad4', '9c95ade2bf0367ef9b1db38069337243d02a4bdbb2d79c45282250db56becf83', '2026-03-28 07:50:34'),
(653, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"d37fae8280a0b6a2fa351a56f5e0ff397c02b4067b7413ab2686ba9593580b69\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9c95ade2bf0367ef9b1db38069337243d02a4bdbb2d79c45282250db56becf83', 'b74438032e06cb925176363c619b9aa0484b1406a873769f49608f636dc61d90', '2026-03-28 07:50:37'),
(654, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"d37fae8280a0b6a2fa351a56f5e0ff397c02b4067b7413ab2686ba9593580b69\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d37fae8280a0b6a2fa351a56f5e0ff397c02b4067b7413ab2686ba9593580b69\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b74438032e06cb925176363c619b9aa0484b1406a873769f49608f636dc61d90', '8c5000a2f0d075c9e9bac15eaf94198c219c1f29c5e748d553627c7bc3291b6a', '2026-03-28 07:50:46'),
(655, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"session_id\":\"5aa0b841505df23efdf9af4db63c5deff49f251a44b04954ee971ecd12bb5278\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8c5000a2f0d075c9e9bac15eaf94198c219c1f29c5e748d553627c7bc3291b6a', 'a1c05b20ccbd8d00ab49cae939b59000ce5e99bf88183b37c48a4969dc161977', '2026-03-28 07:50:55'),
(656, 7, 'LOGIN', 'users', 7, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"username\",\"session_id\":\"320180bae27d7b251ddd6d4e122df80b0100a56added9ca9ccce28b41a2eac92\",\"username\":\"cashier\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a1c05b20ccbd8d00ab49cae939b59000ce5e99bf88183b37c48a4969dc161977', 'a3455e353d671be0a2bd65ee9049706bc5f89294038a08a04c2c33e19f25c9d4', '2026-04-24 21:21:20'),
(657, 7, 'LOGOUT', 'users', 7, '{\"authenticated\":true,\"session_id\":\"320180bae27d7b251ddd6d4e122df80b0100a56added9ca9ccce28b41a2eac92\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"320180bae27d7b251ddd6d4e122df80b0100a56added9ca9ccce28b41a2eac92\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a3455e353d671be0a2bd65ee9049706bc5f89294038a08a04c2c33e19f25c9d4', 'df36f3cae4ff3127a3a55e8bc35b9267fbb1327d81e1719fa0c8b24ff02e1d17', '2026-04-24 21:21:31'),
(658, 7, 'LOGIN', 'users', 7, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"c6d112d046d211826aa3854ddb388a7b55fbf0c782eb63594c4c628844219f56\",\"username\":\"cashier\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'df36f3cae4ff3127a3a55e8bc35b9267fbb1327d81e1719fa0c8b24ff02e1d17', '794ee499c0e02a830f3c9e11c5e218d8a285be5e313067d10ab3f91d764023b0', '2026-04-24 21:23:57'),
(659, 7, 'LOGOUT', 'users', 7, '{\"authenticated\":true,\"session_id\":\"c6d112d046d211826aa3854ddb388a7b55fbf0c782eb63594c4c628844219f56\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"c6d112d046d211826aa3854ddb388a7b55fbf0c782eb63594c4c628844219f56\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '794ee499c0e02a830f3c9e11c5e218d8a285be5e313067d10ab3f91d764023b0', 'f09d6ec8b44694ea827b0c0986b4f50d2e276513ffb608116edd8c3805ceadc4', '2026-04-24 21:24:02'),
(660, 7, 'LOGIN', 'users', 7, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"541e19cc81921161169c69e1c190a222256b59b8a7012793a85943b82e62e551\",\"username\":\"cashier\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f09d6ec8b44694ea827b0c0986b4f50d2e276513ffb608116edd8c3805ceadc4', '3b7730bbe92e8a69b5cf910dcaf476df7536a51aff5d66734aeb7c77ec93b977', '2026-04-24 21:24:05'),
(661, 7, 'LOGOUT', 'users', 7, '{\"authenticated\":true,\"session_id\":\"541e19cc81921161169c69e1c190a222256b59b8a7012793a85943b82e62e551\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"541e19cc81921161169c69e1c190a222256b59b8a7012793a85943b82e62e551\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3b7730bbe92e8a69b5cf910dcaf476df7536a51aff5d66734aeb7c77ec93b977', '52a18ebe022be1ed3d82fc5d9e8304757e46be7e7f4e45a363f65c65537e8fd3', '2026-04-24 21:29:31'),
(662, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"e16796778a958251d19a4ed28c0b8c9307b4cd49f8c18bc62f63cf9c59de5e67\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '52a18ebe022be1ed3d82fc5d9e8304757e46be7e7f4e45a363f65c65537e8fd3', '9ba1098644ce19c2fe874ef322133666589ad1f492cb583f2367a65e6bcbdaf8', '2026-04-24 21:29:46'),
(663, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"e16796778a958251d19a4ed28c0b8c9307b4cd49f8c18bc62f63cf9c59de5e67\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"e16796778a958251d19a4ed28c0b8c9307b4cd49f8c18bc62f63cf9c59de5e67\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9ba1098644ce19c2fe874ef322133666589ad1f492cb583f2367a65e6bcbdaf8', '67da3808af99e00ea3a63ca7367192654b369311a06273bf8149d63b82d61e76', '2026-04-24 21:40:40'),
(664, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"2fdca80b7c869adc5e48d8785b5a54927abe001b616cf4f08fc3357e3ca729ca\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '67da3808af99e00ea3a63ca7367192654b369311a06273bf8149d63b82d61e76', 'a28b737ca6ef3bdcccf56aa615c55b142bd843081fea627cb7ff81eabbb074f5', '2026-04-24 21:40:53'),
(665, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:41:03\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a28b737ca6ef3bdcccf56aa615c55b142bd843081fea627cb7ff81eabbb074f5', '522e4c503e805bd287291b330f22321ce87b4aae453f55be42de54dbe3adf850', '2026-04-24 21:41:03'),
(666, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:41:09\",\"method\":\"manual\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '522e4c503e805bd287291b330f22321ce87b4aae453f55be42de54dbe3adf850', '35f8c0374c88c2d9457eb240c015c29c798fc854a0e202c85b05c14c6c0d7185', '2026-04-24 21:41:09'),
(667, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:44:09\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '35f8c0374c88c2d9457eb240c015c29c798fc854a0e202c85b05c14c6c0d7185', 'bdb1ffe22583007d5eacc2547648af8597d75f250393faa98d61b97542736f30', '2026-04-24 21:44:09'),
(668, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:49:50\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bdb1ffe22583007d5eacc2547648af8597d75f250393faa98d61b97542736f30', '07b63706c4709b14183445e5616292588884e77d52edc39fc4db941729904a00', '2026-04-24 21:49:50'),
(669, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:49:55\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '07b63706c4709b14183445e5616292588884e77d52edc39fc4db941729904a00', '78d25f386d2b97802a819715fcd4f2ca3ec0829c873a6ca631a9d6f45e157294', '2026-04-24 21:49:56'),
(670, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:55:49\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '78d25f386d2b97802a819715fcd4f2ca3ec0829c873a6ca631a9d6f45e157294', '4c50839b3edec33de02f283a07bfa4c08265230a48bf7c6f04fab5bde14dca3d', '2026-04-24 21:55:56'),
(671, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:55:52\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4c50839b3edec33de02f283a07bfa4c08265230a48bf7c6f04fab5bde14dca3d', '42f483e9fbff1397cf4a5eb5cd9643d9dbedd1cc64653e089fb30c9985c78268', '2026-04-24 21:55:57'),
(672, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:57:45\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '42f483e9fbff1397cf4a5eb5cd9643d9dbedd1cc64653e089fb30c9985c78268', 'ed727d9e88e0a6a91812151fc00ff4046d7a955590254755d4271a601f8cbca8', '2026-04-24 21:57:50'),
(673, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 05:58:41\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ed727d9e88e0a6a91812151fc00ff4046d7a955590254755d4271a601f8cbca8', '659e6a57074448fbd2f4d3a4aa14428d3dea9f0f259eaf041fde6c126d6c2002', '2026-04-24 21:58:48'),
(674, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 06:00:48\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '659e6a57074448fbd2f4d3a4aa14428d3dea9f0f259eaf041fde6c126d6c2002', 'ffc803d87a08f45d64189e0346a3717de9c3ffaafd4c0668700af679dd501647', '2026-04-24 22:00:54'),
(675, 13, 'PASSWORD_SET_VIA_INVITE', 'users', 13, '{\"invite_id\":10,\"must_change_password\":1}', '{\"invite_id\":10,\"must_change_password\":0,\"password_set\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ffc803d87a08f45d64189e0346a3717de9c3ffaafd4c0668700af679dd501647', 'd413ede2536a135cd4b165239cde2e5dc5b5afa493399169ca869bb5d8bd90d1', '2026-04-24 22:01:04'),
(676, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-27 06:01:16\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd413ede2536a135cd4b165239cde2e5dc5b5afa493399169ca869bb5d8bd90d1', '0d121c5fe3fcf2b66622918b7e45e359860d3c67624fadcbdc934889ad45bbc5', '2026-04-24 22:01:22'),
(677, 13, 'PASSWORD_SET_VIA_INVITE', 'users', 13, '{\"invite_id\":11,\"must_change_password\":1}', '{\"invite_id\":11,\"must_change_password\":0,\"password_set\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0d121c5fe3fcf2b66622918b7e45e359860d3c67624fadcbdc934889ad45bbc5', '03b5e27b198eeec522fe247c37f0d2a51bb967be0b29657386a7d26dd5cd80ff', '2026-04-24 22:01:42'),
(678, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"2fdca80b7c869adc5e48d8785b5a54927abe001b616cf4f08fc3357e3ca729ca\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"2fdca80b7c869adc5e48d8785b5a54927abe001b616cf4f08fc3357e3ca729ca\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '03b5e27b198eeec522fe247c37f0d2a51bb967be0b29657386a7d26dd5cd80ff', 'bad21dd00bdb26c19c4a01534dcbbaa752ca1c3676aeab623de0d19ee5e71f25', '2026-04-24 22:01:45'),
(679, 13, 'LOGIN', 'users', 13, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"fb905266e1a892973aad73f2e0865c23601ad81284d12e73a48374803356fd45\",\"username\":\"ragasibrian2\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bad21dd00bdb26c19c4a01534dcbbaa752ca1c3676aeab623de0d19ee5e71f25', 'f195961e9ce1409f75974a6f7fd342399da9e1375a5732961b1a7e28921b0c0f', '2026-04-24 22:01:50'),
(680, 13, 'LOGOUT', 'users', 13, '{\"authenticated\":true,\"session_id\":\"fb905266e1a892973aad73f2e0865c23601ad81284d12e73a48374803356fd45\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"fb905266e1a892973aad73f2e0865c23601ad81284d12e73a48374803356fd45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f195961e9ce1409f75974a6f7fd342399da9e1375a5732961b1a7e28921b0c0f', '6c89505f682640fd04d6a41fd0cd1336a14f642a69d302379e179d1b7b7d6574', '2026-04-24 22:01:54'),
(681, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"7077f80064faa31d50356a19ef5d21fd109eaf25a69476e84560bc9078ab5b3e\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6c89505f682640fd04d6a41fd0cd1336a14f642a69d302379e179d1b7b7d6574', '935b3f783537e6dc85dc2e0407f770617f601c23a5e139d3935f95cfea95a59e', '2026-04-26 09:33:17'),
(682, 2, 'CREATE', 'milk_receiving', 36, NULL, '{\"farmer_id\":\"1\",\"receiving_code\":\"RCV-20260426-001\",\"rmr_number\":66208,\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '935b3f783537e6dc85dc2e0407f770617f601c23a5e139d3935f95cfea95a59e', '4eb8820fdcfbaaba418efdbeea3acc99ecf23c44cffbbec98ab2b97028dead40', '2026-04-26 09:33:31'),
(683, 2, 'CREATE', 'qc_milk_tests', 30, NULL, '{\"final_price_per_liter\":30,\"is_accepted\":true,\"receiving_id\":\"36\",\"test_code\":\"QCT-000013\",\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4eb8820fdcfbaaba418efdbeea3acc99ecf23c44cffbbec98ab2b97028dead40', 'f5869d457072da8fb4524d971ccbd2706f53b50c4b24f080f731f4a11737a4e7', '2026-04-26 09:33:41'),
(684, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"7077f80064faa31d50356a19ef5d21fd109eaf25a69476e84560bc9078ab5b3e\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"7077f80064faa31d50356a19ef5d21fd109eaf25a69476e84560bc9078ab5b3e\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f5869d457072da8fb4524d971ccbd2706f53b50c4b24f080f731f4a11737a4e7', '3b27b4f1809401ed11496ccfd594ee381fc7f3c5c27d021f81d0e9ad8e75a85d', '2026-04-26 09:33:49'),
(685, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"9e6d74a5ca230f08d12e39012309dcba10bfe950fcdf8099aa044403cc655116\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3b27b4f1809401ed11496ccfd594ee381fc7f3c5c27d021f81d0e9ad8e75a85d', '7d457b6366e007a05bd1491520af5b3f52a61135b69605632535bf6d5051e6a6', '2026-04-26 09:34:05');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(686, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 16, NULL, '{\"tank_code\":\"PRT-001\",\"tank_id\":10,\"volume_liters\":\"598.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7d457b6366e007a05bd1491520af5b3f52a61135b69605632535bf6d5051e6a6', '8b840aeb70bdc73195c573697dee8f22d51c201c4d46c39b00c960b1e3da4d8c', '2026-04-26 09:34:28'),
(687, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"9e6d74a5ca230f08d12e39012309dcba10bfe950fcdf8099aa044403cc655116\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"9e6d74a5ca230f08d12e39012309dcba10bfe950fcdf8099aa044403cc655116\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8b840aeb70bdc73195c573697dee8f22d51c201c4d46c39b00c960b1e3da4d8c', 'e15750406e3d4767bed3aabfc8defe469ef0c76ddb858adbac8dc02db34d3f38', '2026-04-26 09:34:32'),
(688, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"64fd55088e3db8be25b9019622cac0e42687a32105b9466c4faf71920266bb1a\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e15750406e3d4767bed3aabfc8defe469ef0c76ddb858adbac8dc02db34d3f38', '186e8f71f0a32c049e1b8825f2f6b59a0715ee6122ab250a1c8accf9be788343', '2026-04-26 09:34:52'),
(689, 6, 'LOGIN', 'users', 6, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"0001d36592824d1e1464f10e924769a0d99af00e16bd4a42aed1855115de7cb6\",\"username\":\"sales_custodian\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '186e8f71f0a32c049e1b8825f2f6b59a0715ee6122ab250a1c8accf9be788343', '90012e00541be61244f4ec41cb593ea3147d8a23727b88f482a265947edd97e7', '2026-04-27 06:14:28'),
(690, 6, 'LOGOUT', 'users', 6, '{\"authenticated\":true,\"session_id\":\"0001d36592824d1e1464f10e924769a0d99af00e16bd4a42aed1855115de7cb6\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"0001d36592824d1e1464f10e924769a0d99af00e16bd4a42aed1855115de7cb6\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '90012e00541be61244f4ec41cb593ea3147d8a23727b88f482a265947edd97e7', '4a9a685f805d5e03f125a5ae73cec61bd9507de27fd7087faf0ff4362a138532', '2026-04-27 06:16:47'),
(691, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"21f7cd6d979dfdc827b407cd86ad1066a4915a08cae0664269e60fa2af644ca9\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4a9a685f805d5e03f125a5ae73cec61bd9507de27fd7087faf0ff4362a138532', '2236ab9e06d0fd0738a843be02b42f046044254705d11e72277bd3987bde8bd3', '2026-04-27 06:16:59'),
(692, 8, 'INVITE_CREATED', 'auth_invites', 13, NULL, '{\"expires_at\":\"2026-04-29 14:17:14\",\"method\":\"email\",\"user_email\":\"ragasibrian2@gmail.com\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2236ab9e06d0fd0738a843be02b42f046044254705d11e72277bd3987bde8bd3', '802a716e3f74645776c0fc5d5367228f3912384cc59dcf5cf8141d6b2f8c0e57', '2026-04-27 06:17:23'),
(693, 13, 'PASSWORD_SET_VIA_INVITE', 'users', 13, '{\"invite_id\":12,\"must_change_password\":1}', '{\"invite_id\":12,\"must_change_password\":0,\"password_set\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '802a716e3f74645776c0fc5d5367228f3912384cc59dcf5cf8141d6b2f8c0e57', '2b42ab3d5684c1a0f643db1dd205b3276627307500491879a808c9b848db159d', '2026-04-27 06:18:38'),
(694, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"21f7cd6d979dfdc827b407cd86ad1066a4915a08cae0664269e60fa2af644ca9\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"21f7cd6d979dfdc827b407cd86ad1066a4915a08cae0664269e60fa2af644ca9\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2b42ab3d5684c1a0f643db1dd205b3276627307500491879a808c9b848db159d', '50ae49796b00dce3f1e115c2236e03dffe9b4fa1ba91f25e8c497369488919e7', '2026-04-27 06:18:56'),
(695, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"d714134098414b964acfa22d24a0567a4f02a000b351739d4f1784255223018b\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '50ae49796b00dce3f1e115c2236e03dffe9b4fa1ba91f25e8c497369488919e7', '843dfde6790628b131a4510781896b962d80949c9c7124c4f47e5c1b7f6a67d0', '2026-04-27 06:19:04'),
(696, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"d714134098414b964acfa22d24a0567a4f02a000b351739d4f1784255223018b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d714134098414b964acfa22d24a0567a4f02a000b351739d4f1784255223018b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '843dfde6790628b131a4510781896b962d80949c9c7124c4f47e5c1b7f6a67d0', 'bd97b330f39ada3056d48c788af6e9605bb87a1a052b8731eba4437ad060479e', '2026-04-27 06:19:48'),
(697, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a089712f6984db04bea1e26f4558d64a2db3884e5564ee0f24d4fc81a4dca2b2\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bd97b330f39ada3056d48c788af6e9605bb87a1a052b8731eba4437ad060479e', '5345ec06bdc55632c705965a94416a12981d1bad393c2ee62281f57d5e2220a0', '2026-04-27 06:19:53'),
(698, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"5c329565cb06cb4301739eb3818edaacae4016293e313e1d8587fb63af48335c\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5345ec06bdc55632c705965a94416a12981d1bad393c2ee62281f57d5e2220a0', '4bc39445df2be04f6a9d20f0c17399c6c3b22a7ffa30fb2a52411aa7fbf9945a', '2026-04-27 07:01:11'),
(699, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"5c329565cb06cb4301739eb3818edaacae4016293e313e1d8587fb63af48335c\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"5c329565cb06cb4301739eb3818edaacae4016293e313e1d8587fb63af48335c\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4bc39445df2be04f6a9d20f0c17399c6c3b22a7ffa30fb2a52411aa7fbf9945a', '3e02a943699e6374023babd6840afe5d2a90cf887d15755aabb0bc89c5f61ff1', '2026-04-27 07:03:04'),
(700, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"018cd33be65e6923929c3d3805ea3a78a69fc832cb9493a5ca515e4b8c62e85d\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3e02a943699e6374023babd6840afe5d2a90cf887d15755aabb0bc89c5f61ff1', 'ecbe7d3492663691ef07bdc416c8b56c057a1dba3435e98fed7ede8940d47de7', '2026-04-27 07:03:12'),
(701, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"018cd33be65e6923929c3d3805ea3a78a69fc832cb9493a5ca515e4b8c62e85d\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"018cd33be65e6923929c3d3805ea3a78a69fc832cb9493a5ca515e4b8c62e85d\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ecbe7d3492663691ef07bdc416c8b56c057a1dba3435e98fed7ede8940d47de7', '61c0c059aa41f4c9bd55ea690db33cd59ace6a69a2961fd154b38a5f548ee1a0', '2026-04-27 07:05:10'),
(702, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"912a3daced5a95a48735e4939d85afc829283a5b659b59b3ad5124f5f1e4998c\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '61c0c059aa41f4c9bd55ea690db33cd59ace6a69a2961fd154b38a5f548ee1a0', 'b74594c737989ecf0e7720ccfec12f170244258f2faeac9391e044e627896362', '2026-04-27 07:05:26'),
(703, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"912a3daced5a95a48735e4939d85afc829283a5b659b59b3ad5124f5f1e4998c\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"912a3daced5a95a48735e4939d85afc829283a5b659b59b3ad5124f5f1e4998c\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b74594c737989ecf0e7720ccfec12f170244258f2faeac9391e044e627896362', '43a0f6209e27e7250495c569d9ec82419910cb556e0d48c8bc8af9da52db84e7', '2026-04-27 07:06:12'),
(704, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"3597f613a4f7c83267c6bb3a1d8b7d965e0921c362940ab4a43d9ad5db8b6bf5\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '43a0f6209e27e7250495c569d9ec82419910cb556e0d48c8bc8af9da52db84e7', '9eebaa3b3a24aa375e2120503abe9bb334873a2fcdbffa0272f82583bfb39751', '2026-04-27 07:06:24'),
(705, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"3597f613a4f7c83267c6bb3a1d8b7d965e0921c362940ab4a43d9ad5db8b6bf5\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"3597f613a4f7c83267c6bb3a1d8b7d965e0921c362940ab4a43d9ad5db8b6bf5\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9eebaa3b3a24aa375e2120503abe9bb334873a2fcdbffa0272f82583bfb39751', '49076075b45c240f0060de7eda6d88bfeea339ed8768a42f2db71bf6531b6330', '2026-04-27 07:08:38'),
(706, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"853f0ff127f57c9331fd690b8fc6b16c1cc9f97944bd93bde308238610c4c194\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '49076075b45c240f0060de7eda6d88bfeea339ed8768a42f2db71bf6531b6330', '6e37db9a68e3964b7cfd15d2851e72109e3c823764d6ff9b55fa155d78beb917', '2026-04-27 07:09:04'),
(707, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"853f0ff127f57c9331fd690b8fc6b16c1cc9f97944bd93bde308238610c4c194\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"853f0ff127f57c9331fd690b8fc6b16c1cc9f97944bd93bde308238610c4c194\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6e37db9a68e3964b7cfd15d2851e72109e3c823764d6ff9b55fa155d78beb917', 'a7ec28fca06418a8daa13b83c76859ec2d626b3e2bc81f5c7790979af3f50d8e', '2026-04-27 07:12:56'),
(708, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"1b3630ae759b5e6acb1517a700ac76b52c1d21254298ae219414686ef7a2b534\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a7ec28fca06418a8daa13b83c76859ec2d626b3e2bc81f5c7790979af3f50d8e', 'f4a72a92de7e70e34412017ea6a6f1056f0e474ed13b9ffd011214db70b73d6c', '2026-04-27 07:13:09'),
(709, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"1b3630ae759b5e6acb1517a700ac76b52c1d21254298ae219414686ef7a2b534\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"1b3630ae759b5e6acb1517a700ac76b52c1d21254298ae219414686ef7a2b534\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f4a72a92de7e70e34412017ea6a6f1056f0e474ed13b9ffd011214db70b73d6c', 'ef3cb39fc58f4a331c299d59f98e6dac30f1692effe583eaa41f6127237be994', '2026-04-27 07:13:36'),
(710, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a294ca5b2c11c30b276009d029323de423886856263a9ea53025aa986575c53f\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ef3cb39fc58f4a331c299d59f98e6dac30f1692effe583eaa41f6127237be994', '4ccb4bf66498f624c7630961318cf5b6516b88299ff3699c77abe0e95ee1d426', '2026-04-27 07:13:42'),
(711, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"a294ca5b2c11c30b276009d029323de423886856263a9ea53025aa986575c53f\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"a294ca5b2c11c30b276009d029323de423886856263a9ea53025aa986575c53f\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4ccb4bf66498f624c7630961318cf5b6516b88299ff3699c77abe0e95ee1d426', 'ba37d4159c43ace07ab0289b75ab26f22db295783a4e4ef8e97c824234faf9d3', '2026-04-27 07:16:18'),
(712, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"4569641126c328d6dc73a5962b90d4098c91f99ab8d60ce27d909c9273cd5413\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ba37d4159c43ace07ab0289b75ab26f22db295783a4e4ef8e97c824234faf9d3', '3ddf1acfb977a75b5ed607cd2e87cffd9db88dc4b0e365187eb2908728265209', '2026-04-27 07:16:23'),
(713, 10, 'CREATE', 'purchase_orders', 25, NULL, '{\"items_count\":2,\"payment_terms\":\"credit_7\",\"po_number\":\"5255\",\"supplier_id\":\"2\",\"total_amount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3ddf1acfb977a75b5ed607cd2e87cffd9db88dc4b0e365187eb2908728265209', '30b83df6a88b31c46dda9bb0bc96691d398ad7fb1e710b08865f5f5291e10a85', '2026-04-27 07:18:27'),
(714, 10, 'UPDATE', 'purchase_orders', 25, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '30b83df6a88b31c46dda9bb0bc96691d398ad7fb1e710b08865f5f5291e10a85', '764543cc184b608e465048c2b2141447132e264ddce59d87f5abc1aec522099a', '2026-04-27 07:18:47'),
(715, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"4569641126c328d6dc73a5962b90d4098c91f99ab8d60ce27d909c9273cd5413\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"4569641126c328d6dc73a5962b90d4098c91f99ab8d60ce27d909c9273cd5413\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '764543cc184b608e465048c2b2141447132e264ddce59d87f5abc1aec522099a', 'c39af8bcbcc7d277be7b9603e1161033b4ba09ffb7f4f3d72252f2db4477b7ff', '2026-04-27 07:18:52'),
(716, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"cb467bb66056cb0caab5dac93420f61ed3f76adeb1a05a2b6eaed3e9e27b93d7\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c39af8bcbcc7d277be7b9603e1161033b4ba09ffb7f4f3d72252f2db4477b7ff', '16a44488e90ad5639ddd835827f96e60f85c4ad235bbb76968610d11f337e952', '2026-04-27 07:18:58'),
(717, 8, 'APPROVE', 'purchase_orders', 25, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '16a44488e90ad5639ddd835827f96e60f85c4ad235bbb76968610d11f337e952', '391bd192f23d21bc444221c0b996c2c8d155eb5dfe9d62a40028dd0f9d3ef145', '2026-04-27 07:19:53'),
(718, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"cb467bb66056cb0caab5dac93420f61ed3f76adeb1a05a2b6eaed3e9e27b93d7\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"cb467bb66056cb0caab5dac93420f61ed3f76adeb1a05a2b6eaed3e9e27b93d7\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '391bd192f23d21bc444221c0b996c2c8d155eb5dfe9d62a40028dd0f9d3ef145', '691cbd3efbfc0e3555959d443bd37bdd2c848114da42fce93c1f49bdfeaba3da', '2026-04-27 07:20:02'),
(719, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"7ac337d926caf3ce36f436be7d563a6b17a43b690a8d37e78671d8c077b054fb\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '691cbd3efbfc0e3555959d443bd37bdd2c848114da42fce93c1f49bdfeaba3da', '664d6d8c1f93047390676bf141de0ecfee220f9b500cbae0df6b7ba1c16ff44e', '2026-04-27 07:20:07'),
(720, 10, 'UPDATE', 'purchase_orders', 25, '{\"status\":\"approved\"}', '{\"status\":\"ordered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '664d6d8c1f93047390676bf141de0ecfee220f9b500cbae0df6b7ba1c16ff44e', '2e600f41562b6f562c38c3e4025ae41b39d6938010f0d4a69d2916ab2a875b2b', '2026-04-27 07:21:03'),
(721, 10, 'RECEIVE', 'purchase_orders', 25, '{\"status\":\"ordered\"}', '{\"price_updates\":0,\"status\":\"received\",\"stocked_in_items\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2e600f41562b6f562c38c3e4025ae41b39d6938010f0d4a69d2916ab2a875b2b', '6531b24fbb8844dc6f82e2d6636b371b20ee4df9eafc8b398ce5c8b6a4a3bcb7', '2026-04-27 07:21:17'),
(722, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"7ac337d926caf3ce36f436be7d563a6b17a43b690a8d37e78671d8c077b054fb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"7ac337d926caf3ce36f436be7d563a6b17a43b690a8d37e78671d8c077b054fb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6531b24fbb8844dc6f82e2d6636b371b20ee4df9eafc8b398ce5c8b6a4a3bcb7', '2b30bf13b77d7bddbfd2e72d9ce5bf47bb286f384a98491eb0c89a80da8517bb', '2026-04-27 07:21:26'),
(723, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"933439b006bd68d11ca7fe00945e18c270d9380976968590f54e08d78cc3aa60\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2b30bf13b77d7bddbfd2e72d9ce5bf47bb286f384a98491eb0c89a80da8517bb', '8dee9018f95c68aa0e76d58ab3557e6be392f0f837ce27f1abca95f7080f34e4', '2026-04-27 07:21:55'),
(724, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"933439b006bd68d11ca7fe00945e18c270d9380976968590f54e08d78cc3aa60\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"933439b006bd68d11ca7fe00945e18c270d9380976968590f54e08d78cc3aa60\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8dee9018f95c68aa0e76d58ab3557e6be392f0f837ce27f1abca95f7080f34e4', 'c2d61026a37ee97cd155b53c24f1ae113e3f4228cb6b9e11b083aad923ee7fd8', '2026-04-27 07:23:02'),
(725, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"70714cdee57b0aee8c0f8855c73b84b06cc9c46830772fb1f6da7175aa04360b\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c2d61026a37ee97cd155b53c24f1ae113e3f4228cb6b9e11b083aad923ee7fd8', '3a02dd717450546e568835479784197ce0c405fb2e7256ac1c71c70c10037d3f', '2026-04-27 07:23:17'),
(726, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"70714cdee57b0aee8c0f8855c73b84b06cc9c46830772fb1f6da7175aa04360b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"70714cdee57b0aee8c0f8855c73b84b06cc9c46830772fb1f6da7175aa04360b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3a02dd717450546e568835479784197ce0c405fb2e7256ac1c71c70c10037d3f', 'c4140f12f19ca91e05d143a97e9a9e6a282b0e13db292385a6697df512439eb2', '2026-04-27 07:23:45'),
(727, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"bbec9a9f94dec0f6106f7331072bea7f1286f5600cd6a6bbaa3e11a531a702bc\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c4140f12f19ca91e05d143a97e9a9e6a282b0e13db292385a6697df512439eb2', 'f09e57031386b148a54ef624fbe27ba4a1703e9cbc4239ef0464399bc1d1fe17', '2026-04-27 07:23:50'),
(728, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"95c0aeae186fd721be5dc61781d97da3764baeb0aff2b068cbe47ac2b1a8c982\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f09e57031386b148a54ef624fbe27ba4a1703e9cbc4239ef0464399bc1d1fe17', '0c40a5698be80578cef10396272812c2709dcadfc5bd467210385df5dfda0cfc', '2026-05-01 08:21:07'),
(729, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"95c0aeae186fd721be5dc61781d97da3764baeb0aff2b068cbe47ac2b1a8c982\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"95c0aeae186fd721be5dc61781d97da3764baeb0aff2b068cbe47ac2b1a8c982\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0c40a5698be80578cef10396272812c2709dcadfc5bd467210385df5dfda0cfc', 'a435e367a29c17e6d88dc401fad8b062260881aea587fba44a47fecbdc3925fe', '2026-05-01 08:22:04'),
(730, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"af234b152df2e8d7b5d80567b98868ceb61ba078888db9892881b737791c971e\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a435e367a29c17e6d88dc401fad8b062260881aea587fba44a47fecbdc3925fe', 'be9318d6903d469de638e76c870e585b67b1e43fb3c5dc9fd880bd71899d03d6', '2026-05-01 08:22:10'),
(731, 4, 'adjust_stock', 'ingredients', 16, '{\"current_stock\":\"8.00\"}', '{\"current_stock\":\"1\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'be9318d6903d469de638e76c870e585b67b1e43fb3c5dc9fd880bd71899d03d6', '129ea36dfaa1782afac763b48e0dc978083c3340f09044b6d52b351c5fc6acb0', '2026-05-01 08:39:13'),
(732, 4, 'CREATE', 'purchase_requests', 1, NULL, '{\"items_count\":1,\"pr_number\":\"PR-20260501-001\",\"priority\":\"high\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '129ea36dfaa1782afac763b48e0dc978083c3340f09044b6d52b351c5fc6acb0', 'ffccb4940df4778270064e61de6e6543f87c498d8177f4d8bb650b115686affd', '2026-05-01 08:55:36'),
(733, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"af234b152df2e8d7b5d80567b98868ceb61ba078888db9892881b737791c971e\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"af234b152df2e8d7b5d80567b98868ceb61ba078888db9892881b737791c971e\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ffccb4940df4778270064e61de6e6543f87c498d8177f4d8bb650b115686affd', 'e5900d76dd3054bfbaefe15b90b6f656c2aefa62e81cf40c6b906cba4a7e78ef', '2026-05-01 08:55:40'),
(734, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"1685bd47a807fffc5180585d7453c625ea55a8e59493c48a16debd09dbbe4f00\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e5900d76dd3054bfbaefe15b90b6f656c2aefa62e81cf40c6b906cba4a7e78ef', '107ddf44a1c46969ee1bdeaef945b06336b3dafd0b6b46253658adaabc81da03', '2026-05-01 08:55:45'),
(735, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"1685bd47a807fffc5180585d7453c625ea55a8e59493c48a16debd09dbbe4f00\"}', '{\"authenticated\":false,\"reason\":\"idle_timeout\",\"session_id\":\"1685bd47a807fffc5180585d7453c625ea55a8e59493c48a16debd09dbbe4f00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '107ddf44a1c46969ee1bdeaef945b06336b3dafd0b6b46253658adaabc81da03', 'ba1a388910bfaa800f888171ddb3b248bcfadaf27cc66329da40a6826d96139f', '2026-05-01 09:51:07'),
(736, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"f735d04d46e8bb9e60e899bb14410878d83029ea91b48d6e4c0e02049cfe4937\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ba1a388910bfaa800f888171ddb3b248bcfadaf27cc66329da40a6826d96139f', '2a880bfd8d659ec6c5ee46f51ddbab93ab18e700a26cbfb24582aef0fc002399', '2026-05-01 14:03:38'),
(737, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"60.00\"}', '{\"current_stock\":\"40\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2a880bfd8d659ec6c5ee46f51ddbab93ab18e700a26cbfb24582aef0fc002399', 'd185dd4e060c991cfb532884078c50381fdb6d89389f84d25073e19798a1a7f5', '2026-05-01 14:05:22'),
(738, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"40.00\"}', '{\"current_stock\":\"40\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd185dd4e060c991cfb532884078c50381fdb6d89389f84d25073e19798a1a7f5', '73ab7be0fad9093942be79a3c32941f3fe6ea08ed5c189d35d0d4000a0bf7db9', '2026-05-01 14:05:25'),
(739, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"40.00\"}', '{\"current_stock\":\"40\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '73ab7be0fad9093942be79a3c32941f3fe6ea08ed5c189d35d0d4000a0bf7db9', '3ed595591e3418a332bbb294ad275158cc28a069c490733c8f7af8e5b543f814', '2026-05-01 14:05:25'),
(740, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"40.00\"}', '{\"current_stock\":\"30\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3ed595591e3418a332bbb294ad275158cc28a069c490733c8f7af8e5b543f814', '3b96177880dc753dabf7c78112aa78ee8520c4b51c1464c00e062b9a5c47a0be', '2026-05-01 14:07:32'),
(741, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"30.00\"}', '{\"current_stock\":\"30\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3b96177880dc753dabf7c78112aa78ee8520c4b51c1464c00e062b9a5c47a0be', '2d8ed2dc24faf0d8185bdf037c12430115f1b613d9073e2b1cc05eae24a66116', '2026-05-01 14:07:33'),
(742, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"30.00\"}', '{\"current_stock\":\"30\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2d8ed2dc24faf0d8185bdf037c12430115f1b613d9073e2b1cc05eae24a66116', '4780125029a1ced04cc2e6326f3bc92eea8b76d0201d3e0ca3e67549a1b2b44a', '2026-05-01 14:07:33'),
(743, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"30.00\"}', '{\"current_stock\":\"30\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4780125029a1ced04cc2e6326f3bc92eea8b76d0201d3e0ca3e67549a1b2b44a', 'ab24cac6b66f0d0e5d7d1d0689018cf5bc4753a483625ab9731d387ec23e79ee', '2026-05-01 14:07:39'),
(744, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"30.00\"}', '{\"current_stock\":\"30\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ab24cac6b66f0d0e5d7d1d0689018cf5bc4753a483625ab9731d387ec23e79ee', '0df35b5a52a226a1157acff6d378c58f9c87c20dd8774ac7c12cc1b54f8f60e2', '2026-05-01 14:07:40'),
(745, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"30.00\"}', '{\"current_stock\":\"30\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0df35b5a52a226a1157acff6d378c58f9c87c20dd8774ac7c12cc1b54f8f60e2', '1c9711c884323b91b95a361910cd0df7dac27f7303d650cf2f81b83faf8537f4', '2026-05-01 14:07:40'),
(746, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":\"30.00\"}', '{\"current_stock\":\"30\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1c9711c884323b91b95a361910cd0df7dac27f7303d650cf2f81b83faf8537f4', '237dc42de9d272e7e7a80a572352066ac0f34d822988b74fdb961516c51eb9b9', '2026-05-01 14:08:18'),
(747, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":30}', '{\"current_stock\":10,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '237dc42de9d272e7e7a80a572352066ac0f34d822988b74fdb961516c51eb9b9', 'de9abdbee3658a6e45de58545886de2e3f12a13a2edbb349de81ddad7de05c9d', '2026-05-01 14:10:32'),
(748, 4, 'adjust_stock', 'ingredients', 13, '{\"current_stock\":10}', '{\"current_stock\":10,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'de9abdbee3658a6e45de58545886de2e3f12a13a2edbb349de81ddad7de05c9d', '04992de4d1761cb1235856d46279f7113df28a39c7796b35effb7e4a28624eb9', '2026-05-01 14:10:43'),
(749, 4, 'adjust_stock', 'ingredients', 15, '{\"current_stock\":10}', '{\"current_stock\":1,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '04992de4d1761cb1235856d46279f7113df28a39c7796b35effb7e4a28624eb9', '4eca15df9e9df08a61ee6abe504ca754aa2df15dfeb90b3f7ede2c378116928f', '2026-05-01 14:21:03'),
(750, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"f735d04d46e8bb9e60e899bb14410878d83029ea91b48d6e4c0e02049cfe4937\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"f735d04d46e8bb9e60e899bb14410878d83029ea91b48d6e4c0e02049cfe4937\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4eca15df9e9df08a61ee6abe504ca754aa2df15dfeb90b3f7ede2c378116928f', '45994193705bc0089f80a4dd389050237f21b699d0e29df39bfb0df8400d165a', '2026-05-01 14:21:30'),
(751, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"65bdd540bfac9632d96584cf9512a0469d48c7d379a611464dc3277a4d6eeceb\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '45994193705bc0089f80a4dd389050237f21b699d0e29df39bfb0df8400d165a', '3371cf8b168ac38e38d9d5a31ee15a56ae2a4c8745d014ff824e713453fea6af', '2026-05-01 14:21:41'),
(752, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"65bdd540bfac9632d96584cf9512a0469d48c7d379a611464dc3277a4d6eeceb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"65bdd540bfac9632d96584cf9512a0469d48c7d379a611464dc3277a4d6eeceb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3371cf8b168ac38e38d9d5a31ee15a56ae2a4c8745d014ff824e713453fea6af', '1e1c12bd0e710ae26ab642188528f0b679aff989a0a1521bd8a1ca46c7933abb', '2026-05-01 14:22:11'),
(753, 13, 'LOGIN', 'users', 13, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"3b096377b86817782dded9e05658350b9dc50ecd495bc38c948bb1d4b37c93fb\",\"username\":\"ragasibrian2\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1e1c12bd0e710ae26ab642188528f0b679aff989a0a1521bd8a1ca46c7933abb', 'bd27f9ece5092043efb10446f1f2e63d179a1c2356d12482de00f9affca729fa', '2026-05-02 13:00:14'),
(754, 13, 'LOGOUT', 'users', 13, '{\"authenticated\":true,\"session_id\":\"3b096377b86817782dded9e05658350b9dc50ecd495bc38c948bb1d4b37c93fb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"3b096377b86817782dded9e05658350b9dc50ecd495bc38c948bb1d4b37c93fb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bd27f9ece5092043efb10446f1f2e63d179a1c2356d12482de00f9affca729fa', '111a1ca3de90dfb0956c77ee3afde34c18f1bf5f80ff71bc14c3f767b5d735f2', '2026-05-02 13:00:31'),
(755, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a0596e866c31a86d0efa67e6549314c91dfaabc10546e99bb74fe648e530d365\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '111a1ca3de90dfb0956c77ee3afde34c18f1bf5f80ff71bc14c3f767b5d735f2', '2b630efaf93780db7e69bda4a2c58b479da2c03724797741755c5994e37fb432', '2026-05-02 13:00:35'),
(756, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":75}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2b630efaf93780db7e69bda4a2c58b479da2c03724797741755c5994e37fb432', 'f0f8791a22724834ae4d4bda8c2e545bca8a7f9d4ad2da5274b68c61fd98211c', '2026-05-02 13:01:32'),
(757, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f0f8791a22724834ae4d4bda8c2e545bca8a7f9d4ad2da5274b68c61fd98211c', '456db7b9abb2bf6f833d9c5ff902463eeed6419249926fa1be54914225e8c731', '2026-05-02 13:01:38'),
(758, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '456db7b9abb2bf6f833d9c5ff902463eeed6419249926fa1be54914225e8c731', 'b60f818ff29ba7550045fb12032bad42f10c9708e42a2d40cdc013402064e50e', '2026-05-02 13:01:39'),
(759, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b60f818ff29ba7550045fb12032bad42f10c9708e42a2d40cdc013402064e50e', '89cc97326089f272e9e1ba2aaf4b3e4514e85abc6e6ee424c394360fabe290dd', '2026-05-02 13:03:00'),
(760, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '89cc97326089f272e9e1ba2aaf4b3e4514e85abc6e6ee424c394360fabe290dd', 'd356ee5939b7b2bd3eee27c3d45c3df8c8caf24a48de83f6c2cd1ed3e527ff37', '2026-05-02 13:03:00'),
(761, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd356ee5939b7b2bd3eee27c3d45c3df8c8caf24a48de83f6c2cd1ed3e527ff37', 'a8d7d03210a420f239187aa01951049caca974967334f4ba9bd2b225f2205bdf', '2026-05-02 13:03:00'),
(762, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a8d7d03210a420f239187aa01951049caca974967334f4ba9bd2b225f2205bdf', '9edc775a292d4abb321effbb8f483f44ba100c701780c4a07772a237858176a0', '2026-05-02 13:03:00'),
(763, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9edc775a292d4abb321effbb8f483f44ba100c701780c4a07772a237858176a0', '76db790b624e3fc0b5b5893636bd9754220c871a5316c3e878b6e42958cdc097', '2026-05-02 13:03:04'),
(764, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '76db790b624e3fc0b5b5893636bd9754220c871a5316c3e878b6e42958cdc097', '37f64bb36c6447650a805e69e02fd8fbb24b6f62f8d009b67ad2ac042f855c08', '2026-05-02 13:03:06'),
(765, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Damage/spoilage\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '37f64bb36c6447650a805e69e02fd8fbb24b6f62f8d009b67ad2ac042f855c08', 'faba68847fee1cb84021925a03dab307575470f2d9ac35472433c9d5703bbe12', '2026-05-02 13:03:08'),
(766, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Damage/spoilage\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'faba68847fee1cb84021925a03dab307575470f2d9ac35472433c9d5703bbe12', 'c13d22b6735e38368411450906aa21d40b3d78addfcd3d4cdee6066e4c11d4ca', '2026-05-02 13:03:09'),
(767, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":70,\"reason\":\"Damage/spoilage\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c13d22b6735e38368411450906aa21d40b3d78addfcd3d4cdee6066e4c11d4ca', '59081af45070424bddf2736a33409517b3b76e4604a4922578dca82f15ad4a87', '2026-05-02 13:04:35'),
(768, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":70}', '{\"current_stock\":50,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '59081af45070424bddf2736a33409517b3b76e4604a4922578dca82f15ad4a87', '37ee81d9d00f76cc172e7c53cfdf60f462b2ef4b66b2551ea5c8010886343335', '2026-05-02 13:04:48'),
(769, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":50}', '{\"current_stock\":50,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '37ee81d9d00f76cc172e7c53cfdf60f462b2ef4b66b2551ea5c8010886343335', '63692f82fab76f5ad14114a8f4aac916aa7ea3d022e1c0752817395c4a2981ae', '2026-05-02 13:04:49'),
(770, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":50}', '{\"current_stock\":50,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '63692f82fab76f5ad14114a8f4aac916aa7ea3d022e1c0752817395c4a2981ae', '60ff5dc8dfdfd0d85f0d86f9b00b9b611caa8e1c28c477ecfd31e7e60c80ce59', '2026-05-02 13:04:50'),
(771, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":50}', '{\"current_stock\":20,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '60ff5dc8dfdfd0d85f0d86f9b00b9b611caa8e1c28c477ecfd31e7e60c80ce59', '9d2f433da6060c6fb057926769e684b4a11e80c09b0fe35a0d8acbc163a97160', '2026-05-02 13:05:00'),
(772, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":20}', '{\"current_stock\":10,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9d2f433da6060c6fb057926769e684b4a11e80c09b0fe35a0d8acbc163a97160', '3f6daf916685a0d0b775fe08db0eec49c1f28e9b66ff437257948fc66f350e69', '2026-05-02 13:05:52'),
(773, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":10}', '{\"current_stock\":5,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3f6daf916685a0d0b775fe08db0eec49c1f28e9b66ff437257948fc66f350e69', '05564122a1fbc12f6508fdf09c2f913a9ae6e7a6005344a418b457e9e93a491d', '2026-05-02 13:06:24'),
(774, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":5}', '{\"current_stock\":5,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '05564122a1fbc12f6508fdf09c2f913a9ae6e7a6005344a418b457e9e93a491d', 'c5a3efdde93d6f5b528deb9b4dea96cfdf3fc696082aa4eea5a5debd44e9a5fa', '2026-05-02 13:06:28'),
(775, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":5}', '{\"current_stock\":5,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c5a3efdde93d6f5b528deb9b4dea96cfdf3fc696082aa4eea5a5debd44e9a5fa', 'eaec49ab1bdf37964252df38d88b8bfc3355eef2719d54f73b1033ccc364c49a', '2026-05-02 13:06:30'),
(776, 4, 'adjust_stock', 'ingredients', 14, '{\"current_stock\":100}', '{\"current_stock\":40,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'eaec49ab1bdf37964252df38d88b8bfc3355eef2719d54f73b1033ccc364c49a', '38e40ab4a06eb313fba2608d96980f0f8eafc4087e595d2a522278114ada29d1', '2026-05-02 13:07:17'),
(777, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"a0596e866c31a86d0efa67e6549314c91dfaabc10546e99bb74fe648e530d365\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"a0596e866c31a86d0efa67e6549314c91dfaabc10546e99bb74fe648e530d365\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '38e40ab4a06eb313fba2608d96980f0f8eafc4087e595d2a522278114ada29d1', 'cf114a48eae12f0a1883e1b9bb7fa9674cf31b32490e2271de3d7d42667d1d9a', '2026-05-02 13:08:21'),
(778, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"0a8978fd2505fd88c91147032e6e77df12931b90bfbe169bcae4d498a9ba0d0c\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cf114a48eae12f0a1883e1b9bb7fa9674cf31b32490e2271de3d7d42667d1d9a', '345bcc7860dc1089ea2774b70279e0ea025f472440633ffb94e9d11d6f8642b9', '2026-05-02 13:08:24'),
(779, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"0a8978fd2505fd88c91147032e6e77df12931b90bfbe169bcae4d498a9ba0d0c\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"0a8978fd2505fd88c91147032e6e77df12931b90bfbe169bcae4d498a9ba0d0c\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '345bcc7860dc1089ea2774b70279e0ea025f472440633ffb94e9d11d6f8642b9', 'f709eae190bbc73e89f7937c3326e3d0df460567b95548dec6bfa262cc6b5794', '2026-05-02 13:12:55'),
(780, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"404365c198f31e5a150692fa203b2814e6cc6baf0934de335997aaec7ff1051b\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f709eae190bbc73e89f7937c3326e3d0df460567b95548dec6bfa262cc6b5794', '33f93fc06459de3e639280f2b9633d264724c98e4da80cf28f35b40992047c7b', '2026-05-02 13:13:01'),
(781, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"404365c198f31e5a150692fa203b2814e6cc6baf0934de335997aaec7ff1051b\"}', '{\"authenticated\":false,\"reason\":\"idle_timeout\",\"session_id\":\"404365c198f31e5a150692fa203b2814e6cc6baf0934de335997aaec7ff1051b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '33f93fc06459de3e639280f2b9633d264724c98e4da80cf28f35b40992047c7b', '4cb4788130e7c499cab5c300c9629f8349c470ad21c02f3945d7d4e5b64779c9', '2026-05-02 14:10:45'),
(782, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"2579071264af5b05c0194ed920e144cec271e98e4768fb0ed00f2a9ee2331f58\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4cb4788130e7c499cab5c300c9629f8349c470ad21c02f3945d7d4e5b64779c9', '4af0d29d1176b8abc24e186bb5d09493e7bc97394dc31353e2d5e5117ee96755', '2026-05-02 18:01:07'),
(783, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"2579071264af5b05c0194ed920e144cec271e98e4768fb0ed00f2a9ee2331f58\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"2579071264af5b05c0194ed920e144cec271e98e4768fb0ed00f2a9ee2331f58\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4af0d29d1176b8abc24e186bb5d09493e7bc97394dc31353e2d5e5117ee96755', '5d2cef950756058f31025796a860d325448995ce454fd03c24e79838732db9dc', '2026-05-02 18:03:16'),
(784, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a58edc992132f74770f4a46aecebd6c0fe3f6688e511040dc995b7c22fde555b\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5d2cef950756058f31025796a860d325448995ce454fd03c24e79838732db9dc', 'f5a91b5ea30eb898aa361af96db6951ad4aa0bdb2896dcc0e4905fdcc82e2dbd', '2026-05-02 18:03:19'),
(785, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"a58edc992132f74770f4a46aecebd6c0fe3f6688e511040dc995b7c22fde555b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"a58edc992132f74770f4a46aecebd6c0fe3f6688e511040dc995b7c22fde555b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f5a91b5ea30eb898aa361af96db6951ad4aa0bdb2896dcc0e4905fdcc82e2dbd', '5a57ea6f83954344c5788dd3771aaa5c40f3ea03317b529d2e0da91dfc01295c', '2026-05-02 18:03:27'),
(786, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a66e67297feb18b08f87c291040434ee7c734011df198d7c5f50a9ceb512e856\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5a57ea6f83954344c5788dd3771aaa5c40f3ea03317b529d2e0da91dfc01295c', '1537009349a832a42fb14ca321172e2f6153b527ad607687d5bab2965729774d', '2026-05-02 18:03:33'),
(787, 8, 'REJECT', 'purchase_orders', 24, '{\"status\":\"pending\"}', '{\"reason\":\"Basta\",\"status\":\"cancelled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1537009349a832a42fb14ca321172e2f6153b527ad607687d5bab2965729774d', '288dc3905c70e4e409aa748ebddd1822da7cc3f6d9063e1ffc31d5aedf3d0ebf', '2026-05-02 18:05:01'),
(788, 8, 'REJECT', 'purchase_orders', 23, '{\"status\":\"pending\"}', '{\"reason\":\"Basta\",\"status\":\"cancelled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '288dc3905c70e4e409aa748ebddd1822da7cc3f6d9063e1ffc31d5aedf3d0ebf', '1138c94db20b216a2a6bbf45969fb0719235b8f2991de467e029b19d96544caa', '2026-05-02 18:05:03');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(789, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"0f769830834639d6f199d30fc9b013bd54cfe57279af03131033ac059d8beb0a\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1138c94db20b216a2a6bbf45969fb0719235b8f2991de467e029b19d96544caa', '9bd54762b5ecac2a4e76cdca1a7af1d1029e1e479614cd2129b8989b29a2b9e7', '2026-05-03 05:58:10'),
(790, 8, 'APPROVE', 'purchase_requests', 1, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9bd54762b5ecac2a4e76cdca1a7af1d1029e1e479614cd2129b8989b29a2b9e7', '840c97b32f2f8654dd5c872cfaf2470479b1416be00b28d73a532bf8bc63d5a4', '2026-05-03 05:58:25'),
(791, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"0f769830834639d6f199d30fc9b013bd54cfe57279af03131033ac059d8beb0a\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"0f769830834639d6f199d30fc9b013bd54cfe57279af03131033ac059d8beb0a\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '840c97b32f2f8654dd5c872cfaf2470479b1416be00b28d73a532bf8bc63d5a4', '60dc561030755e77f03e447c843fc7d1c24a21afae6b6bc713a49e59662f2ffd', '2026-05-03 05:58:27'),
(792, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"80288efebd8b6cf4b473d54c813374f7a87299bab11199adb6ef9c8a74e659c5\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '60dc561030755e77f03e447c843fc7d1c24a21afae6b6bc713a49e59662f2ffd', '3c77f2e2a06eb25f44fd616987850ca56ea468c9ebc65d08799d0e3b0595d010', '2026-05-03 05:58:35'),
(793, 10, 'CREATE', 'purchase_orders', 26, NULL, '{\"items_count\":1,\"payment_terms\":\"credit_30\",\"po_number\":\"5256\",\"pr_number\":\"PR-20260501-001\",\"purchase_request_id\":\"1\",\"supplier_id\":\"5\",\"total_amount\":50}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3c77f2e2a06eb25f44fd616987850ca56ea468c9ebc65d08799d0e3b0595d010', '446ad2530a62716f065a050ee2e4b8de4d128086afed7f687fe63aa5ee15b09a', '2026-05-03 06:01:00'),
(794, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"80288efebd8b6cf4b473d54c813374f7a87299bab11199adb6ef9c8a74e659c5\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"80288efebd8b6cf4b473d54c813374f7a87299bab11199adb6ef9c8a74e659c5\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '446ad2530a62716f065a050ee2e4b8de4d128086afed7f687fe63aa5ee15b09a', 'cf1eefd376c88d78ca5e21c7462d76c243a278500d17ede4c2cfeb42bb0a865a', '2026-05-03 06:01:26'),
(795, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"6626d2493b06fc1092aa21bfee59e07eb9a257dff94e9dcbf59e1b8583dd3dda\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cf1eefd376c88d78ca5e21c7462d76c243a278500d17ede4c2cfeb42bb0a865a', 'd3a0ecd3d625a43af6ec7b890e7e940f0261a6c15c9fc99757bf9f27b43d83d4', '2026-05-03 06:01:30'),
(796, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"6626d2493b06fc1092aa21bfee59e07eb9a257dff94e9dcbf59e1b8583dd3dda\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6626d2493b06fc1092aa21bfee59e07eb9a257dff94e9dcbf59e1b8583dd3dda\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd3a0ecd3d625a43af6ec7b890e7e940f0261a6c15c9fc99757bf9f27b43d83d4', '69e8c2c3575f5c1a60d978bab3bcb959382d3445356f68656123cb1c743ec3c7', '2026-05-03 06:02:13'),
(797, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"7475766f0cf3654bba9b62ac245aec86f919378a3e3acab6847f20b5dc2025dd\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '69e8c2c3575f5c1a60d978bab3bcb959382d3445356f68656123cb1c743ec3c7', '117d007a28ef86e766fb9f6431ee46e6f8da0d75e821698c4d5e441f37b4f607', '2026-05-03 06:02:17'),
(798, 10, 'UPDATE', 'purchase_orders', 26, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '117d007a28ef86e766fb9f6431ee46e6f8da0d75e821698c4d5e441f37b4f607', '7f1bb2983ee15f10fc7ca06ad61c37ed1ac40aef23aac117c9573decfeff148a', '2026-05-03 06:02:25'),
(799, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"7475766f0cf3654bba9b62ac245aec86f919378a3e3acab6847f20b5dc2025dd\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"7475766f0cf3654bba9b62ac245aec86f919378a3e3acab6847f20b5dc2025dd\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7f1bb2983ee15f10fc7ca06ad61c37ed1ac40aef23aac117c9573decfeff148a', '713ad7b9b21940057c41ccb5f17920a2f1ff3372a28b09abefe32b6cde58defd', '2026-05-03 06:02:27'),
(800, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"ea2e0896ce6774fba1b0d3729eeaf4e75b9de27dd7d16fd3f244523ece2bd09d\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '713ad7b9b21940057c41ccb5f17920a2f1ff3372a28b09abefe32b6cde58defd', 'fc1d510949570b706f7105e830a1e70c8aaeab040840ff7fd0876f2fecb2a0cd', '2026-05-03 06:02:51'),
(801, 8, 'APPROVE', 'purchase_orders', 26, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'fc1d510949570b706f7105e830a1e70c8aaeab040840ff7fd0876f2fecb2a0cd', '5b6ad18b9f7020c3e9ba2c39ed85c5f2d594f49acfb2527ca468625edd875879', '2026-05-03 06:04:21'),
(802, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"ea2e0896ce6774fba1b0d3729eeaf4e75b9de27dd7d16fd3f244523ece2bd09d\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"ea2e0896ce6774fba1b0d3729eeaf4e75b9de27dd7d16fd3f244523ece2bd09d\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5b6ad18b9f7020c3e9ba2c39ed85c5f2d594f49acfb2527ca468625edd875879', '9b91ec6be49e98c8938260193792469c40d07fbccdad95d931f758eedb728106', '2026-05-03 06:04:24'),
(803, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"dc70372604843854caf7e18fb4b91518f04707af1554c6a8499eb13d3928799f\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9b91ec6be49e98c8938260193792469c40d07fbccdad95d931f758eedb728106', 'c042ebb4ee293febeab113e30442516bdac409a27bb6f6871be69d1eac36311a', '2026-05-03 06:04:28'),
(804, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"dc70372604843854caf7e18fb4b91518f04707af1554c6a8499eb13d3928799f\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"dc70372604843854caf7e18fb4b91518f04707af1554c6a8499eb13d3928799f\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c042ebb4ee293febeab113e30442516bdac409a27bb6f6871be69d1eac36311a', '05df6dc42a8b1ba00711be1ecbed7d520dabebb80662adcd5a5fc3e56bd3bf4a', '2026-05-03 06:06:25'),
(805, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"35d22e0bcb64c9c39d436700dd561eba490623f9e526612135c442e2cc21e156\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '05df6dc42a8b1ba00711be1ecbed7d520dabebb80662adcd5a5fc3e56bd3bf4a', '76fd2061ff31b4f91445b942058b613ca7b6dca47f06962e1c3903d2a233e755', '2026-05-03 06:06:29'),
(806, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"35d22e0bcb64c9c39d436700dd561eba490623f9e526612135c442e2cc21e156\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"35d22e0bcb64c9c39d436700dd561eba490623f9e526612135c442e2cc21e156\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '76fd2061ff31b4f91445b942058b613ca7b6dca47f06962e1c3903d2a233e755', 'cd0b9f658f64e33a3de8f5f4a2754618e215274ada5a8583c7221997047e7d87', '2026-05-03 06:15:04'),
(807, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"751ca1cb3fea96ea27d3556b64f5f9da21b08dc6e7357820b4ff9f231ceb4534\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cd0b9f658f64e33a3de8f5f4a2754618e215274ada5a8583c7221997047e7d87', '36dcd9cd6386563d38aca87f00ad799df0b63931d77787011f7a809d45c37657', '2026-05-03 06:15:09'),
(808, 10, 'UPDATE', 'purchase_orders', 26, '{\"status\":\"approved\"}', '{\"status\":\"ordered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '36dcd9cd6386563d38aca87f00ad799df0b63931d77787011f7a809d45c37657', 'c166c741969c815b02a95aa772d6f2b4ff0155915aa6297a8fa569d1f06d5065', '2026-05-03 06:15:18'),
(809, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"751ca1cb3fea96ea27d3556b64f5f9da21b08dc6e7357820b4ff9f231ceb4534\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"751ca1cb3fea96ea27d3556b64f5f9da21b08dc6e7357820b4ff9f231ceb4534\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c166c741969c815b02a95aa772d6f2b4ff0155915aa6297a8fa569d1f06d5065', '20087973ba5c837947dbdb227c501d09cb586eca00403916ed2bbce8d7b652b4', '2026-05-03 06:15:24'),
(810, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"ef982bd9c2c34cbf15f3c7a222570fd6f8068f457f5013b910483eadfb024b03\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '20087973ba5c837947dbdb227c501d09cb586eca00403916ed2bbce8d7b652b4', 'b2af2647a746fd3e4b98a8fec47acd45d2f5674645ee590d2fdf0a4be9d83c2a', '2026-05-03 06:15:29'),
(811, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"ef982bd9c2c34cbf15f3c7a222570fd6f8068f457f5013b910483eadfb024b03\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"ef982bd9c2c34cbf15f3c7a222570fd6f8068f457f5013b910483eadfb024b03\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b2af2647a746fd3e4b98a8fec47acd45d2f5674645ee590d2fdf0a4be9d83c2a', 'ad00be24e53ec2eac2da3ee9a43c897275819559ed4a5378680ebc1c186424a3', '2026-05-03 06:20:19'),
(812, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"d91af59ea3dc495364dfe7129b3ae906c8a1f30de9e778ef8a547a0cc9e835f1\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ad00be24e53ec2eac2da3ee9a43c897275819559ed4a5378680ebc1c186424a3', 'f658172ae1402ed4a810e219e568a89b64e350897f33af0fec8b7d0e1741aa2d', '2026-05-03 06:20:22'),
(813, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"d91af59ea3dc495364dfe7129b3ae906c8a1f30de9e778ef8a547a0cc9e835f1\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d91af59ea3dc495364dfe7129b3ae906c8a1f30de9e778ef8a547a0cc9e835f1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f658172ae1402ed4a810e219e568a89b64e350897f33af0fec8b7d0e1741aa2d', '9afc21bec6e8cc1487ca8e599c55330e13c5286a812ab0063c044a24395854bf', '2026-05-03 06:23:05'),
(814, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"259ca92f9510363a80a76202c6c81b5d35ff05b5f92f1e259d1913d8249740f4\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9afc21bec6e8cc1487ca8e599c55330e13c5286a812ab0063c044a24395854bf', '647bfaa83c3c767537bff567dcf6978eb42e2a5c4db96f7250814b1e3640f131', '2026-05-03 06:23:09'),
(815, 4, 'RECEIVE', 'purchase_orders', 26, '{\"status\":\"ordered\"}', '{\"accepted\":1,\"rejected\":0,\"status\":\"received\",\"stocked_in_items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '647bfaa83c3c767537bff567dcf6978eb42e2a5c4db96f7250814b1e3640f131', '7940d5953bdd80ec8f79e84258563742613bb16345f5daf3b9bfb35d5f4ef6bc', '2026-05-03 06:23:40'),
(816, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"259ca92f9510363a80a76202c6c81b5d35ff05b5f92f1e259d1913d8249740f4\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"259ca92f9510363a80a76202c6c81b5d35ff05b5f92f1e259d1913d8249740f4\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7940d5953bdd80ec8f79e84258563742613bb16345f5daf3b9bfb35d5f4ef6bc', 'e3ee505d633af2e2f51a91fea3244c2f17482c837d287f01f88c6c5c86ee83cd', '2026-05-03 06:24:47'),
(817, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a55e324c03c21910f9aeec9ffb70bd0256d538f186c8fc3ca4698e4d524a4b88\",\"username\":\"finance_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e3ee505d633af2e2f51a91fea3244c2f17482c837d287f01f88c6c5c86ee83cd', 'bcbd37eee76943e4d2417bc789227631080ec9b711b68d9a4d9e3a39cc421137', '2026-05-03 06:24:56'),
(818, 11, 'LOGOUT', 'users', 11, '{\"authenticated\":true,\"session_id\":\"a55e324c03c21910f9aeec9ffb70bd0256d538f186c8fc3ca4698e4d524a4b88\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"a55e324c03c21910f9aeec9ffb70bd0256d538f186c8fc3ca4698e4d524a4b88\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bcbd37eee76943e4d2417bc789227631080ec9b711b68d9a4d9e3a39cc421137', 'cd37e4f000ac90bcf6b240f1bdec8eaa76f5b066038f667fca06c7755fe62235', '2026-05-03 06:27:39'),
(819, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"1cb69db23d793bee6b9bee60fbf5a36ed212a333bdcb1ad4f67381541d5eb5ef\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cd37e4f000ac90bcf6b240f1bdec8eaa76f5b066038f667fca06c7755fe62235', '2eaf962f47c93fb1c56be4802e519ac7e3deeff65955ec521ce9c6fa0c42f1f9', '2026-05-03 06:27:43'),
(820, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a960ef06dce94b03e007f109ea86c08d895bbc2441579846c7a297bde78a8562\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2eaf962f47c93fb1c56be4802e519ac7e3deeff65955ec521ce9c6fa0c42f1f9', '702b05a48315d873b6961420c413c4fdace20f0b084d26e31fb280a174c32486', '2026-05-03 07:29:00'),
(821, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"a960ef06dce94b03e007f109ea86c08d895bbc2441579846c7a297bde78a8562\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"a960ef06dce94b03e007f109ea86c08d895bbc2441579846c7a297bde78a8562\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '702b05a48315d873b6961420c413c4fdace20f0b084d26e31fb280a174c32486', 'b228ad622ee5a4dde55aeb51912b0f839a10a6a21e1d3ee5920fe7b051b423d1', '2026-05-03 07:31:34'),
(822, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"ecb172dd23c04b9c18a4e45b7a7d12fb8d8fc3fb8bcf171b2ea764c41372b827\",\"username\":\"finance_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b228ad622ee5a4dde55aeb51912b0f839a10a6a21e1d3ee5920fe7b051b423d1', '7f9e052f41bf33f46653a4833e7a40b15bb28b8a83c85af1b132b5796ffe9a6a', '2026-05-03 07:32:11'),
(823, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"b5e132b60c6cb41fcbe3966f66c2f7e93a7aa2da8989eaad5886d4958c49c7da\",\"username\":\"finance_officer\"}', '::1', 'curl/8.18.0', '7f9e052f41bf33f46653a4833e7a40b15bb28b8a83c85af1b132b5796ffe9a6a', '267bd5df2343d0ae7c560a4258f08fd60bb6b3967fad2d12d24a198470c79dd3', '2026-05-03 07:38:04'),
(824, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"ffe2005c3369638b8bfbcb512246cbc5203a81b6f1e11ecd6cdb66c103acf26d\",\"username\":\"finance_officer\"}', '::1', 'curl/8.18.0', '267bd5df2343d0ae7c560a4258f08fd60bb6b3967fad2d12d24a198470c79dd3', 'd304ef64c2ede3e3b3ac31b52f3cc30ad058338d7cbc95edca4d05a33f15e9d8', '2026-05-03 07:38:29'),
(825, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"8bdfde43e5a18283848e98e370a087d77b0bd36e7055752a6766e4de4fe3ff3d\",\"username\":\"finance_officer\"}', '::1', 'curl/8.18.0', 'd304ef64c2ede3e3b3ac31b52f3cc30ad058338d7cbc95edca4d05a33f15e9d8', '4ff2c7dbd23d5c709568d51d7f4977bea6148f96568d23c6967b59f6857c1687', '2026-05-03 07:39:09'),
(826, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"23a9b282ea0090981f048d1f1531a3731cdf1ccc709ef5eb49e92bb7eaeb6b80\",\"username\":\"finance_officer\"}', '::1', 'curl/8.18.0', '4ff2c7dbd23d5c709568d51d7f4977bea6148f96568d23c6967b59f6857c1687', '8aabe5f8391dae8c40c1a13f727745363e4089bddcba189380644ad763aed835', '2026-05-03 07:39:14'),
(827, 11, 'PAYMENT_RELEASE', 'purchase_orders', 26, '{\"payment_status\":\"unpaid\",\"total_amount\":\"50.00\"}', '{\"notes\":\"\",\"payment_method\":\"cash\",\"payment_status\":\"paid\",\"reference_number\":\"12312312\",\"total_amount\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8aabe5f8391dae8c40c1a13f727745363e4089bddcba189380644ad763aed835', '48898b587d1fa4224613e5a96e09ca8d1e7a8fa1fc882c197c26948de14cd6bc', '2026-05-03 07:39:53'),
(828, 11, 'LOGOUT', 'users', 11, '{\"authenticated\":true,\"session_id\":\"ecb172dd23c04b9c18a4e45b7a7d12fb8d8fc3fb8bcf171b2ea764c41372b827\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"ecb172dd23c04b9c18a4e45b7a7d12fb8d8fc3fb8bcf171b2ea764c41372b827\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '48898b587d1fa4224613e5a96e09ca8d1e7a8fa1fc882c197c26948de14cd6bc', '9679d504c53462ec2135711dd00da436fb73b16c82f70225a7e258eafe8dd468', '2026-05-03 07:40:19'),
(829, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"6a927917541f565f462c1d9428e3ee726c54549c7ff73efc492519f70f4dbf07\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9679d504c53462ec2135711dd00da436fb73b16c82f70225a7e258eafe8dd468', 'cae7f414c185c061a9d2ea59d07288b6c5faba719f69b5c20becdd1daf9e3b30', '2026-05-03 07:47:47'),
(830, 4, 'CREATE', 'purchase_requests', 2, NULL, '{\"items_count\":1,\"pr_number\":\"PR-20260503-001\",\"priority\":\"high\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cae7f414c185c061a9d2ea59d07288b6c5faba719f69b5c20becdd1daf9e3b30', 'ad9ff5597ff70ecf3f1d0bd799e022c7b921d1732517945ff25b2efcbb649c10', '2026-05-03 07:50:25'),
(831, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"6a927917541f565f462c1d9428e3ee726c54549c7ff73efc492519f70f4dbf07\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6a927917541f565f462c1d9428e3ee726c54549c7ff73efc492519f70f4dbf07\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ad9ff5597ff70ecf3f1d0bd799e022c7b921d1732517945ff25b2efcbb649c10', 'b1c97d944fd546edd5377d5a2f5547afc19c0bedec18129ee1aa47a5f079105c', '2026-05-03 07:50:43'),
(832, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"4df45388047a025b6561a59a27f5d856152336595e508c0e333f7f9a11537dd3\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b1c97d944fd546edd5377d5a2f5547afc19c0bedec18129ee1aa47a5f079105c', 'f4f57dd801bf480a034f91b18eb6b0147983c0674896a8e40e4001a7e4878e7a', '2026-05-03 07:51:17'),
(833, 8, 'APPROVE', 'purchase_requests', 2, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f4f57dd801bf480a034f91b18eb6b0147983c0674896a8e40e4001a7e4878e7a', 'd79cd20dd4cf52e0c5f70982682506450f61f406baaf7eb5fea8770bdf3e3c3e', '2026-05-03 07:51:30'),
(834, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"4df45388047a025b6561a59a27f5d856152336595e508c0e333f7f9a11537dd3\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"4df45388047a025b6561a59a27f5d856152336595e508c0e333f7f9a11537dd3\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd79cd20dd4cf52e0c5f70982682506450f61f406baaf7eb5fea8770bdf3e3c3e', '496868896173d7a7e5c10b4e9ecbbce3bbba50172649b7cd7973ecf3b68e068d', '2026-05-03 07:51:35'),
(835, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"7524f036a50261f089d42c3138112cf8d62684cfd524777cd6161a9f7cf774a1\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '496868896173d7a7e5c10b4e9ecbbce3bbba50172649b7cd7973ecf3b68e068d', '3ac3144cb87eb7a2e3b3b928a2b816b0593a1ace9217be49ba1c523787c64c34', '2026-05-03 07:51:50'),
(836, 10, 'CREATE', 'purchase_orders', 27, NULL, '{\"items_count\":1,\"payment_terms\":\"credit_30\",\"po_number\":\"5257\",\"pr_number\":\"PR-20260503-001\",\"purchase_request_id\":\"2\",\"supplier_id\":\"5\",\"total_amount\":8000}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3ac3144cb87eb7a2e3b3b928a2b816b0593a1ace9217be49ba1c523787c64c34', '454921ef172df05226866c815366fda044df3c3a7c16866d0a25dcf1ba5817b7', '2026-05-03 07:59:09'),
(837, 10, 'UPDATE', 'purchase_orders', 27, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '454921ef172df05226866c815366fda044df3c3a7c16866d0a25dcf1ba5817b7', 'c92f507bab297a50fb37f1711c0cfdccd99c2c2a825ad1e93dcac1fe55132037', '2026-05-03 07:59:16'),
(838, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"7524f036a50261f089d42c3138112cf8d62684cfd524777cd6161a9f7cf774a1\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"7524f036a50261f089d42c3138112cf8d62684cfd524777cd6161a9f7cf774a1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c92f507bab297a50fb37f1711c0cfdccd99c2c2a825ad1e93dcac1fe55132037', '68fb564cb39bbebc7eacd35715353294564743d04a240320be16ccf7dc3d4cdb', '2026-05-03 07:59:18'),
(839, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"9c9399f571da22bdbf6a8c7595d1a7aabb5ade2311d3df7f57b07261981fe629\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '68fb564cb39bbebc7eacd35715353294564743d04a240320be16ccf7dc3d4cdb', '3bc1728eab7e3202933177339536deeab78cfb84ebb866020a72037304b5a7f3', '2026-05-03 07:59:24'),
(840, 8, 'APPROVE', 'purchase_orders', 27, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3bc1728eab7e3202933177339536deeab78cfb84ebb866020a72037304b5a7f3', 'a3aca221c5232dd3ca5bceef8693d9854b2a03d9da4a179bcda5b5779840bd5c', '2026-05-03 08:17:12'),
(841, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"9c9399f571da22bdbf6a8c7595d1a7aabb5ade2311d3df7f57b07261981fe629\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"9c9399f571da22bdbf6a8c7595d1a7aabb5ade2311d3df7f57b07261981fe629\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a3aca221c5232dd3ca5bceef8693d9854b2a03d9da4a179bcda5b5779840bd5c', 'dd1b996075dc7657dabd7e14275022415fdfd96b34fbd6bc8bdc15196cf0058f', '2026-05-03 08:17:14'),
(842, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"3f8965774358d2bd397d4d838f5123141bd0d77ab8e27dbbd86765a8ffb374b5\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'dd1b996075dc7657dabd7e14275022415fdfd96b34fbd6bc8bdc15196cf0058f', '07743558077bc26f689aa9634a14d1c4238606984a2242750f2c16d239e812ac', '2026-05-03 08:17:19'),
(843, 10, 'UPDATE', 'purchase_orders', 27, '{\"status\":\"approved\"}', '{\"status\":\"ordered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '07743558077bc26f689aa9634a14d1c4238606984a2242750f2c16d239e812ac', 'e27039fed51c4950ff43001ef722f49c9a78dcc6c7d8c028808ce3cd54de19fd', '2026-05-03 08:17:53'),
(844, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"3f8965774358d2bd397d4d838f5123141bd0d77ab8e27dbbd86765a8ffb374b5\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"3f8965774358d2bd397d4d838f5123141bd0d77ab8e27dbbd86765a8ffb374b5\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e27039fed51c4950ff43001ef722f49c9a78dcc6c7d8c028808ce3cd54de19fd', '04eca2660ce06af4587766359a7f351119766c12a9cc00449adf16173312d1b6', '2026-05-03 08:17:58'),
(845, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"cae2e3a9746d3b743aa27f6aeda3d37674c8ddf5fe6d0bc9e510f3d1a77405da\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '04eca2660ce06af4587766359a7f351119766c12a9cc00449adf16173312d1b6', '52164cd0bc2a85cdb23f36647748c13b5b00fff81918585ee781f14095e4b883', '2026-05-03 08:18:12'),
(846, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"cae2e3a9746d3b743aa27f6aeda3d37674c8ddf5fe6d0bc9e510f3d1a77405da\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"cae2e3a9746d3b743aa27f6aeda3d37674c8ddf5fe6d0bc9e510f3d1a77405da\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '52164cd0bc2a85cdb23f36647748c13b5b00fff81918585ee781f14095e4b883', 'c19b418bf9857713ced389136f0e978489868ffb4e4e1272244614e9e1fb190a', '2026-05-03 08:22:01'),
(847, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"d2e526483b5ae5b3ffe2c977eaa191a39d66bedbf650f095ee4bc3827249adb5\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c19b418bf9857713ced389136f0e978489868ffb4e4e1272244614e9e1fb190a', '5d09a876b84d88e827252b725a588a4cc73fc46391da74f2b46665231518ab0c', '2026-05-03 08:22:05'),
(848, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"d2e526483b5ae5b3ffe2c977eaa191a39d66bedbf650f095ee4bc3827249adb5\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d2e526483b5ae5b3ffe2c977eaa191a39d66bedbf650f095ee4bc3827249adb5\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5d09a876b84d88e827252b725a588a4cc73fc46391da74f2b46665231518ab0c', 'b323a29167a72a9e8577923621a9ff0e2fc01d26325df16794eda01a4801dd92', '2026-05-03 08:28:14'),
(849, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"fe843650bb876b47aa5a8b0afbf6864366bf23aae3a2426667ef3474b3b76ce6\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b323a29167a72a9e8577923621a9ff0e2fc01d26325df16794eda01a4801dd92', 'facd05eb9ac4a9135d59b5965d3073315d3862bee37f9ceffa969e86c21cef70', '2026-05-03 08:28:27'),
(850, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"fe843650bb876b47aa5a8b0afbf6864366bf23aae3a2426667ef3474b3b76ce6\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"fe843650bb876b47aa5a8b0afbf6864366bf23aae3a2426667ef3474b3b76ce6\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'facd05eb9ac4a9135d59b5965d3073315d3862bee37f9ceffa969e86c21cef70', '8351912b2072b9b7046a9c07b44cd39b059dc5d0206877386b5beb51dc9986ef', '2026-05-03 08:29:09'),
(851, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"f28cf3036abc81fd8c247785beca305057494244b86965c99e73fea01920cb2d\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8351912b2072b9b7046a9c07b44cd39b059dc5d0206877386b5beb51dc9986ef', '17b0fe4c2fc7360d4ec800634ab53d7592c3578fab5708c2d8e50529f5a5aaa6', '2026-05-03 08:29:14'),
(852, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"f28cf3036abc81fd8c247785beca305057494244b86965c99e73fea01920cb2d\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"f28cf3036abc81fd8c247785beca305057494244b86965c99e73fea01920cb2d\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '17b0fe4c2fc7360d4ec800634ab53d7592c3578fab5708c2d8e50529f5a5aaa6', 'd008cec0fe63732e75f6520467a4bd46a10c4e896837a913e4f29bb44731feae', '2026-05-03 08:29:21'),
(853, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"ca050b03accb7c213ee06225681ab44ced351b21a2f997896dbe52e54a60afb6\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd008cec0fe63732e75f6520467a4bd46a10c4e896837a913e4f29bb44731feae', '34f5641d2d1d24cf759f27ba285f945f39c2a53a187382241a7093a22a417085', '2026-05-03 08:29:25'),
(854, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"ca050b03accb7c213ee06225681ab44ced351b21a2f997896dbe52e54a60afb6\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"ca050b03accb7c213ee06225681ab44ced351b21a2f997896dbe52e54a60afb6\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '34f5641d2d1d24cf759f27ba285f945f39c2a53a187382241a7093a22a417085', '19461d10809466a3f2c7783027295c6ef04404e4cebfe39c8fa83c9411611b78', '2026-05-03 08:29:50'),
(855, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"5eaf077a46e9fcd32c509b6791c549ecff4077c69027981cd0afe94c2cab1ee0\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '19461d10809466a3f2c7783027295c6ef04404e4cebfe39c8fa83c9411611b78', '5b651fab6e410f65b3ff703edf0741be678979ca5811e8f4668ada1470ff1d85', '2026-05-03 08:29:58'),
(856, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"5eaf077a46e9fcd32c509b6791c549ecff4077c69027981cd0afe94c2cab1ee0\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"5eaf077a46e9fcd32c509b6791c549ecff4077c69027981cd0afe94c2cab1ee0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5b651fab6e410f65b3ff703edf0741be678979ca5811e8f4668ada1470ff1d85', 'd7f1df92ce194cb4b4c4e5dbd59224a108854f5a3291e4631fa33fd4855f752c', '2026-05-03 08:30:04'),
(857, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"30221b4a3aad88b6aa0b946d487ae835e3d79470de19f599ccf4ed0cc059f0a7\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd7f1df92ce194cb4b4c4e5dbd59224a108854f5a3291e4631fa33fd4855f752c', '4746502f8f3168d6bce702aab639627c2a9088511231f4a17470ddea134bc312', '2026-05-03 08:30:07'),
(858, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"30221b4a3aad88b6aa0b946d487ae835e3d79470de19f599ccf4ed0cc059f0a7\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"30221b4a3aad88b6aa0b946d487ae835e3d79470de19f599ccf4ed0cc059f0a7\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4746502f8f3168d6bce702aab639627c2a9088511231f4a17470ddea134bc312', '114b01db494a7bde4a8cc9ad72166b51f4fda890b6c30ae966bf2ab2b616e2df', '2026-05-03 08:35:12'),
(859, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"5e309914d8c86f620b1050078033df07e75c55fe94065c7d30294a19a91e5d23\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '114b01db494a7bde4a8cc9ad72166b51f4fda890b6c30ae966bf2ab2b616e2df', '7b536629601b82d3657f4b140756c29b6e5f680373f6427bb4ce099cd4e6db38', '2026-05-03 08:35:22'),
(860, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"5e309914d8c86f620b1050078033df07e75c55fe94065c7d30294a19a91e5d23\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"5e309914d8c86f620b1050078033df07e75c55fe94065c7d30294a19a91e5d23\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7b536629601b82d3657f4b140756c29b6e5f680373f6427bb4ce099cd4e6db38', 'af08c455ea68dc4b4ac58d497ed50a23d3ef3d50053048bae0613a8f3c2252a7', '2026-05-03 08:35:52'),
(861, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"60340fb4760065bc5f019a1426232872e28fdd711fc712ad13b7d1ff9a0ce645\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'af08c455ea68dc4b4ac58d497ed50a23d3ef3d50053048bae0613a8f3c2252a7', 'd3818442f715d63fa4eee772a85d27d2747c606873358584c9ad3dbdd913d120', '2026-05-03 08:35:59'),
(862, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"60340fb4760065bc5f019a1426232872e28fdd711fc712ad13b7d1ff9a0ce645\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"60340fb4760065bc5f019a1426232872e28fdd711fc712ad13b7d1ff9a0ce645\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'd3818442f715d63fa4eee772a85d27d2747c606873358584c9ad3dbdd913d120', '578a0d59614c1e051426a35aa344b19d27a720bcc4efa9d3e12a5d2bbb2f3430', '2026-05-03 08:36:56'),
(863, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"43bbb8cd10dd25d2a8114fb53c1ed39128bb255abef5b384b7b98cde3d7d8591\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '578a0d59614c1e051426a35aa344b19d27a720bcc4efa9d3e12a5d2bbb2f3430', '13c58d1aa135e50424ef004ffad6eae4b81ae6a7e10b9b64e990f09e76d3d3ae', '2026-05-03 08:37:02'),
(864, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"43bbb8cd10dd25d2a8114fb53c1ed39128bb255abef5b384b7b98cde3d7d8591\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"43bbb8cd10dd25d2a8114fb53c1ed39128bb255abef5b384b7b98cde3d7d8591\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '13c58d1aa135e50424ef004ffad6eae4b81ae6a7e10b9b64e990f09e76d3d3ae', '5396c359a62ff747aaba283d369b57c07c3df74cb6156c7b45963351785a65e8', '2026-05-03 08:37:29'),
(865, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"24e3754ff64b517dccdecbd684e022601974aa5f4f1a6b5228d3effa6794418f\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5396c359a62ff747aaba283d369b57c07c3df74cb6156c7b45963351785a65e8', 'bd5033951e8bc6734e14a3376806763652cab43d101da85f01c242ec46232f6b', '2026-05-03 08:37:33'),
(866, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"24e3754ff64b517dccdecbd684e022601974aa5f4f1a6b5228d3effa6794418f\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"24e3754ff64b517dccdecbd684e022601974aa5f4f1a6b5228d3effa6794418f\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bd5033951e8bc6734e14a3376806763652cab43d101da85f01c242ec46232f6b', '80bedabe8bd87ca7227ac2209f3eb98165ce874557e0d85004bc62f71c26b8cd', '2026-05-03 08:39:15'),
(867, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"3e899cd748b02120980f6577184a98708efc8f32c7b9765cf4a80032b9e284e4\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '80bedabe8bd87ca7227ac2209f3eb98165ce874557e0d85004bc62f71c26b8cd', 'aa59e714659d9e952e091858c99379d59ef19f40d2025d3fc518d7d4a6af1b57', '2026-05-03 08:39:18'),
(868, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"3e899cd748b02120980f6577184a98708efc8f32c7b9765cf4a80032b9e284e4\"}', '{\"authenticated\":false,\"reason\":\"idle_timeout\",\"session_id\":\"3e899cd748b02120980f6577184a98708efc8f32c7b9765cf4a80032b9e284e4\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'aa59e714659d9e952e091858c99379d59ef19f40d2025d3fc518d7d4a6af1b57', 'f6b4868548c41d0f27dda34a6f2c38c61b9cf1a93f5bb00dc655e6229216f7c3', '2026-05-03 08:55:55'),
(869, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"be944f3ecc7eb1f5b3030fe5bbbd2e988c2dc9a8fb391496981758f79693571a\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f6b4868548c41d0f27dda34a6f2c38c61b9cf1a93f5bb00dc655e6229216f7c3', 'c0d79566ca94a7b0b5c960b173dcac2692b7021a330d514bcd88e54520b2697e', '2026-05-03 12:00:28'),
(870, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"be944f3ecc7eb1f5b3030fe5bbbd2e988c2dc9a8fb391496981758f79693571a\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"be944f3ecc7eb1f5b3030fe5bbbd2e988c2dc9a8fb391496981758f79693571a\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c0d79566ca94a7b0b5c960b173dcac2692b7021a330d514bcd88e54520b2697e', '4047f627391a5f3bf945466ae1f77784322d987f8f140e639ccf0df852647edc', '2026-05-03 12:01:06'),
(871, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"ce6b4b3898603d23d7c14da9135a1bb5011b6ac28c42c675ab696d62c7a000b0\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4047f627391a5f3bf945466ae1f77784322d987f8f140e639ccf0df852647edc', '0bff6c90ed17a3625dc4a15d6fd9718292ef0eb91aee22b35d2e9c342c35fe0e', '2026-05-03 12:01:15'),
(872, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"ce6b4b3898603d23d7c14da9135a1bb5011b6ac28c42c675ab696d62c7a000b0\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"ce6b4b3898603d23d7c14da9135a1bb5011b6ac28c42c675ab696d62c7a000b0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0bff6c90ed17a3625dc4a15d6fd9718292ef0eb91aee22b35d2e9c342c35fe0e', '487b4b7108570ddc686d7c34b40aa2677559f19764229e4ecbb4d9c0809d6da2', '2026-05-03 12:06:40'),
(873, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"d45fc252e15b1361316ed1e8fbc09723adfb2e93a38973d11d94c555b71461f8\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '487b4b7108570ddc686d7c34b40aa2677559f19764229e4ecbb4d9c0809d6da2', 'c8fdaa368b15800238e60eb994e83bafc518487c88160d050351e5875a74df98', '2026-05-03 12:07:12'),
(874, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"d45fc252e15b1361316ed1e8fbc09723adfb2e93a38973d11d94c555b71461f8\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d45fc252e15b1361316ed1e8fbc09723adfb2e93a38973d11d94c555b71461f8\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c8fdaa368b15800238e60eb994e83bafc518487c88160d050351e5875a74df98', '1de73a495b9814250bc248122884427da31518b73862c96abc162f9f064bcd81', '2026-05-03 12:08:39'),
(875, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"d3758ba312e17048221d7aff63d62e0195ca296bd3ab4477a4cf3a7bb16141ed\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1de73a495b9814250bc248122884427da31518b73862c96abc162f9f064bcd81', '99eec23987bf60de0a736f2ce903f4671d3aadd20d88f2f1d8fc392d1721a1c5', '2026-05-03 12:09:57'),
(876, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"d3758ba312e17048221d7aff63d62e0195ca296bd3ab4477a4cf3a7bb16141ed\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d3758ba312e17048221d7aff63d62e0195ca296bd3ab4477a4cf3a7bb16141ed\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '99eec23987bf60de0a736f2ce903f4671d3aadd20d88f2f1d8fc392d1721a1c5', '10d069cd7acebd7f7d46753608a72c22fe9a0025a0b109fa965e5e20ea9f67b7', '2026-05-03 12:11:03'),
(877, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"5f7486a99d19cb15e581c110324f193a7bbfec2a8d5cc7c49037868a64ecce7a\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '10d069cd7acebd7f7d46753608a72c22fe9a0025a0b109fa965e5e20ea9f67b7', 'a3c2b12324002de01961c721a74a9423d944200d968bc4fb03a38e3f36c9b69c', '2026-05-03 12:11:10'),
(878, 4, 'CREATE', 'purchase_requests', 3, NULL, '{\"items_count\":1,\"pr_number\":\"PR-20260503-002\",\"priority\":\"high\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a3c2b12324002de01961c721a74a9423d944200d968bc4fb03a38e3f36c9b69c', 'a3b97baeeb1097cd4898e9158575a8858854e4025209899c18417eca03a5d7ef', '2026-05-03 12:11:54'),
(879, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"5f7486a99d19cb15e581c110324f193a7bbfec2a8d5cc7c49037868a64ecce7a\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"5f7486a99d19cb15e581c110324f193a7bbfec2a8d5cc7c49037868a64ecce7a\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a3b97baeeb1097cd4898e9158575a8858854e4025209899c18417eca03a5d7ef', '545867423436a2a9d129dc5241e01ba8ca9d8f784f15a432c98ef78b789867c1', '2026-05-03 12:11:58'),
(880, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"7f94362c1f97486516c4da7594767743df0aa3b4c3a34b62578f6f9f3d1c61e8\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '545867423436a2a9d129dc5241e01ba8ca9d8f784f15a432c98ef78b789867c1', '7b369fe5bb88694e5fa903a5d99f120df74a7b04266d918f77e8ee0a52c86593', '2026-05-03 12:12:04'),
(881, 8, 'APPROVE', 'purchase_requests', 3, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7b369fe5bb88694e5fa903a5d99f120df74a7b04266d918f77e8ee0a52c86593', '30af4f3a545227ab5d6b76f656f3c400ed4356fb52fed44d766f894361a2cfae', '2026-05-03 12:12:12'),
(882, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"7f94362c1f97486516c4da7594767743df0aa3b4c3a34b62578f6f9f3d1c61e8\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"7f94362c1f97486516c4da7594767743df0aa3b4c3a34b62578f6f9f3d1c61e8\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '30af4f3a545227ab5d6b76f656f3c400ed4356fb52fed44d766f894361a2cfae', 'cb02b8f0476dda7c9000435998ddc490bd6ab01b4fbb4c115a6f3221d764b014', '2026-05-03 12:12:14'),
(883, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"733b7a2abe1fd658b60105ef03e772b32960c8c840a67066fb3706ada8a24d3b\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cb02b8f0476dda7c9000435998ddc490bd6ab01b4fbb4c115a6f3221d764b014', '8a129ef354a04930ce597c5cfbe2e2bbc4e304c4507b11b85badbd97484e12e9', '2026-05-03 12:12:18'),
(884, 10, 'CREATE', 'purchase_orders', 28, NULL, '{\"items_count\":1,\"payment_terms\":\"credit_30\",\"po_number\":\"5258\",\"pr_number\":\"PR-20260503-002\",\"purchase_request_id\":\"3\",\"supplier_id\":\"5\",\"total_amount\":8000}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8a129ef354a04930ce597c5cfbe2e2bbc4e304c4507b11b85badbd97484e12e9', '021932eda60593413a5f0990241a021822f3eac120e9c4a30197c440471f52ef', '2026-05-03 12:12:37'),
(885, 10, 'UPDATE', 'purchase_orders', 28, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '021932eda60593413a5f0990241a021822f3eac120e9c4a30197c440471f52ef', 'e8a80198304444374550ca4052a78c3e0307874d6b264f89569b40cafd00eb98', '2026-05-03 12:12:41');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(886, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"733b7a2abe1fd658b60105ef03e772b32960c8c840a67066fb3706ada8a24d3b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"733b7a2abe1fd658b60105ef03e772b32960c8c840a67066fb3706ada8a24d3b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e8a80198304444374550ca4052a78c3e0307874d6b264f89569b40cafd00eb98', 'a4e6f501487726bf9f6ac084b4b87df3da8c4d3a7bc6c139dd405ca38ecfd1a9', '2026-05-03 12:12:43'),
(887, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"a68ec53f3983837b731425c1d48da83e6621c20f7e9b5a2bafec0bfffb658459\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a4e6f501487726bf9f6ac084b4b87df3da8c4d3a7bc6c139dd405ca38ecfd1a9', '25254277c0b04d7e5b284748a21de21e259c66e54e9b7f66a8ab58260c283338', '2026-05-03 12:12:50'),
(888, 8, 'APPROVE', 'purchase_orders', 28, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '25254277c0b04d7e5b284748a21de21e259c66e54e9b7f66a8ab58260c283338', '2661ad91e6828ff4c8fc21737381f6186b39bf54de562198e26535a93921157f', '2026-05-03 12:13:01'),
(889, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"a68ec53f3983837b731425c1d48da83e6621c20f7e9b5a2bafec0bfffb658459\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"a68ec53f3983837b731425c1d48da83e6621c20f7e9b5a2bafec0bfffb658459\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2661ad91e6828ff4c8fc21737381f6186b39bf54de562198e26535a93921157f', 'b80776587fbc0bae8aa554bdaa3b6f0e05dbd34d29fabcd2a324d4ebcabdc2e1', '2026-05-03 12:13:03'),
(890, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"c00a0fdd118eeb66313c7260dc85ed5fffcdc496ed4a9134bfe1d5a2f6241dc9\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b80776587fbc0bae8aa554bdaa3b6f0e05dbd34d29fabcd2a324d4ebcabdc2e1', '06a70db98d946199811a9feb3c966c7a2c9240fa3fd1112219465e15bc4788c4', '2026-05-03 12:13:07'),
(891, 4, 'RECEIVE', 'purchase_orders', 28, '{\"status\":\"approved\"}', '{\"accepted\":20,\"rejected\":5,\"status\":\"received\",\"stocked_in_items\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '06a70db98d946199811a9feb3c966c7a2c9240fa3fd1112219465e15bc4788c4', 'fd1ff3660656ee04fb4a3e43e92e23a5f6fa5dd6e661ceb927c5c32405097fc0', '2026-05-03 12:14:18'),
(892, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"c00a0fdd118eeb66313c7260dc85ed5fffcdc496ed4a9134bfe1d5a2f6241dc9\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"c00a0fdd118eeb66313c7260dc85ed5fffcdc496ed4a9134bfe1d5a2f6241dc9\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'fd1ff3660656ee04fb4a3e43e92e23a5f6fa5dd6e661ceb927c5c32405097fc0', 'e011df9487eb44763bc604116ecbea7ee3b447fe1a06a785ad601e0d833bda20', '2026-05-03 12:14:22'),
(893, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"424a2cbed4fc4d475fc697bc990a3f756a421fa7c74c858834462b139862ead8\",\"username\":\"finance_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e011df9487eb44763bc604116ecbea7ee3b447fe1a06a785ad601e0d833bda20', '04f3126aeea0b5216ed7e72fd5594c1f89fbbbeb7eea3ce8e9d14715f0c260dc', '2026-05-03 12:14:26'),
(894, 11, 'LOGOUT', 'users', 11, '{\"authenticated\":true,\"session_id\":\"424a2cbed4fc4d475fc697bc990a3f756a421fa7c74c858834462b139862ead8\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"424a2cbed4fc4d475fc697bc990a3f756a421fa7c74c858834462b139862ead8\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '04f3126aeea0b5216ed7e72fd5594c1f89fbbbeb7eea3ce8e9d14715f0c260dc', '16709453f92eed76aa27be05b25861a0a1dcf13086bee59cc44a5173d9079be3', '2026-05-03 12:15:34'),
(895, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"0bc239a7af7f2c1233a1180d0b744cfdd6b5c3a0881473b3b3d22209afb9b9b1\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '16709453f92eed76aa27be05b25861a0a1dcf13086bee59cc44a5173d9079be3', '358e58e06fe52db1db5aec07c0d8c6874d0cd389de2685b006b38d39d98f5291', '2026-05-03 12:15:40'),
(896, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"0bc239a7af7f2c1233a1180d0b744cfdd6b5c3a0881473b3b3d22209afb9b9b1\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"0bc239a7af7f2c1233a1180d0b744cfdd6b5c3a0881473b3b3d22209afb9b9b1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '358e58e06fe52db1db5aec07c0d8c6874d0cd389de2685b006b38d39d98f5291', 'ff338be6fbea4ea562844a80d80f0c78b690b6959e4685272c9fa06c3d731c6b', '2026-05-03 12:16:16'),
(897, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"eea62db0faf1d0490355eed79899b708b7441147a40391d654ece5ed78f16c79\",\"username\":\"finance_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ff338be6fbea4ea562844a80d80f0c78b690b6959e4685272c9fa06c3d731c6b', '457501c1b536aeddb244068cb7c849ca2ce08fada331e2d94e14edcc680e15fb', '2026-05-03 12:16:20'),
(898, 11, 'LOGOUT', 'users', 11, '{\"authenticated\":true,\"session_id\":\"eea62db0faf1d0490355eed79899b708b7441147a40391d654ece5ed78f16c79\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"eea62db0faf1d0490355eed79899b708b7441147a40391d654ece5ed78f16c79\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '457501c1b536aeddb244068cb7c849ca2ce08fada331e2d94e14edcc680e15fb', '6f6ffe7e760719c17fcfb603da6c5d5caffa94b7d6fcca390aa65416351abc2a', '2026-05-03 12:22:41'),
(899, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"90786cff5e115006ee6aad9b604b2c99b8f62bdc370813ea15cb0b2dc27fd4cb\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6f6ffe7e760719c17fcfb603da6c5d5caffa94b7d6fcca390aa65416351abc2a', '6356f32f1c2f7f3bb5a3881652fe340dc925dd65731354a8511f0912467ccc3e', '2026-05-03 12:22:46'),
(900, 4, 'CREATE', 'purchase_requests', 4, NULL, '{\"items_count\":1,\"pr_number\":\"PR-20260503-003\",\"priority\":\"high\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6356f32f1c2f7f3bb5a3881652fe340dc925dd65731354a8511f0912467ccc3e', '9a0a7d574033f7e16ef3951f49eef3e335b4360ad072dbd8ff84fcedfc326596', '2026-05-03 12:22:52'),
(901, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"90786cff5e115006ee6aad9b604b2c99b8f62bdc370813ea15cb0b2dc27fd4cb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"90786cff5e115006ee6aad9b604b2c99b8f62bdc370813ea15cb0b2dc27fd4cb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9a0a7d574033f7e16ef3951f49eef3e335b4360ad072dbd8ff84fcedfc326596', 'b75370d10ca96f6d6ee493d8aa9fbdf915b051d253a33da4149f8f11c70dacc4', '2026-05-03 12:22:55'),
(902, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"25ebffb3de2cec81717b6931e8c29e2460699f284193d1355233fa2b4683386c\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b75370d10ca96f6d6ee493d8aa9fbdf915b051d253a33da4149f8f11c70dacc4', '4ccb941c9bafb59a4c595c8f1499c68d9a008e560f001fbfc59c27ed0b665408', '2026-05-03 12:22:58'),
(903, 8, 'APPROVE', 'purchase_requests', 4, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4ccb941c9bafb59a4c595c8f1499c68d9a008e560f001fbfc59c27ed0b665408', 'a14bbe7cac65ec11556aa0f44d7516d5a664fad2e15c37146c76d1570efacb8f', '2026-05-03 12:23:05'),
(904, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"25ebffb3de2cec81717b6931e8c29e2460699f284193d1355233fa2b4683386c\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"25ebffb3de2cec81717b6931e8c29e2460699f284193d1355233fa2b4683386c\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a14bbe7cac65ec11556aa0f44d7516d5a664fad2e15c37146c76d1570efacb8f', '46cb1de317e10c432327d3968a795c45c81006f2c6d97d72ad362cc4888f8168', '2026-05-03 12:23:07'),
(905, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"6d010940ebfa421c1d5d10ddec3bc0ce18a425a8d4e5d9f5f386f8eb1d739bf8\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '46cb1de317e10c432327d3968a795c45c81006f2c6d97d72ad362cc4888f8168', '1c8e67db56968b1e88fae0278f4d5005c88c57e71da52bb35446cc2879e4fb2d', '2026-05-03 12:23:10'),
(906, 10, 'CREATE', 'purchase_orders', 29, NULL, '{\"items_count\":1,\"payment_terms\":\"credit_30\",\"po_number\":\"5259\",\"pr_number\":\"PR-20260503-003\",\"purchase_request_id\":\"4\",\"supplier_id\":\"1\",\"total_amount\":3000}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1c8e67db56968b1e88fae0278f4d5005c88c57e71da52bb35446cc2879e4fb2d', 'b003c92d936e9cdf36df3bf20c1c66586d3aebcc1642ad3b0ffd430ea5a07b11', '2026-05-03 12:23:23'),
(907, 10, 'UPDATE', 'purchase_orders', 29, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b003c92d936e9cdf36df3bf20c1c66586d3aebcc1642ad3b0ffd430ea5a07b11', '68cf3c266ce1f29348e0185815e0b8bde71eb40f3d08b52ddf1c794f02144f03', '2026-05-03 12:23:28'),
(908, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"6d010940ebfa421c1d5d10ddec3bc0ce18a425a8d4e5d9f5f386f8eb1d739bf8\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6d010940ebfa421c1d5d10ddec3bc0ce18a425a8d4e5d9f5f386f8eb1d739bf8\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '68cf3c266ce1f29348e0185815e0b8bde71eb40f3d08b52ddf1c794f02144f03', 'bfe811d211bad2bb8d77db1a4f85f633ca3bac027c5a1e62a8ef13f49dd03d4d', '2026-05-03 12:23:30'),
(909, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"092daaba57f276a90953c77a994e6433c9a2d425de7ed07c422c497fd9df4ecb\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bfe811d211bad2bb8d77db1a4f85f633ca3bac027c5a1e62a8ef13f49dd03d4d', 'c4f1af4f9142db7ce1416f906869a6cf606267ac6d5661637999dc0ffb6b8975', '2026-05-03 12:23:33'),
(910, 8, 'APPROVE', 'purchase_orders', 29, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c4f1af4f9142db7ce1416f906869a6cf606267ac6d5661637999dc0ffb6b8975', 'c732d44dad6d2473f80ecea21b8daacd48ad8bc3bf403ad2e63017128f853a79', '2026-05-03 12:23:42'),
(911, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"092daaba57f276a90953c77a994e6433c9a2d425de7ed07c422c497fd9df4ecb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"092daaba57f276a90953c77a994e6433c9a2d425de7ed07c422c497fd9df4ecb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c732d44dad6d2473f80ecea21b8daacd48ad8bc3bf403ad2e63017128f853a79', '86146e843b3c9a67d7fdb5082a32aa84b608287478abf19d99d5e29dda5a59cb', '2026-05-03 12:23:44'),
(912, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"194b2dde115ea4737fc43a141425e9d6a7a6a875f23172f376e8bec47e2f7c29\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '86146e843b3c9a67d7fdb5082a32aa84b608287478abf19d99d5e29dda5a59cb', '33c3a5161e5b0859d0bfb5cb7fd74ed216fb6e8010ff5738cce38a3701a6a366', '2026-05-03 12:23:49'),
(913, 4, 'RECEIVE', 'purchase_orders', 29, '{\"status\":\"approved\"}', '{\"accepted\":15,\"rejected\":5,\"status\":\"received\",\"stocked_in_items\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '33c3a5161e5b0859d0bfb5cb7fd74ed216fb6e8010ff5738cce38a3701a6a366', '7b5bea838c337ee7dbb84b111b869653a3507a17ad60e8fe63e6dc076fed8bc7', '2026-05-03 12:24:47'),
(914, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"194b2dde115ea4737fc43a141425e9d6a7a6a875f23172f376e8bec47e2f7c29\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"194b2dde115ea4737fc43a141425e9d6a7a6a875f23172f376e8bec47e2f7c29\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7b5bea838c337ee7dbb84b111b869653a3507a17ad60e8fe63e6dc076fed8bc7', '02c5063e86ec3794593457ec35d7e840b20067aac4c61e5fea38a192a4355218', '2026-05-03 12:25:45'),
(915, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"1b20847afcbf6fed6bfe814c79fc5bcb6337bb2e293e049862e84c5fe6d40a8b\",\"username\":\"finance_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '02c5063e86ec3794593457ec35d7e840b20067aac4c61e5fea38a192a4355218', 'a2d872c9ca69b8899e14669e4067287a43e5d0ce6a5f3eeb85c3f6a2721b8238', '2026-05-03 12:25:49'),
(916, 11, 'LOGOUT', 'users', 11, '{\"authenticated\":true,\"session_id\":\"1b20847afcbf6fed6bfe814c79fc5bcb6337bb2e293e049862e84c5fe6d40a8b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"1b20847afcbf6fed6bfe814c79fc5bcb6337bb2e293e049862e84c5fe6d40a8b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a2d872c9ca69b8899e14669e4067287a43e5d0ce6a5f3eeb85c3f6a2721b8238', 'eb96339a2d15fddabb9226ca0c230c701d2739ed0e522e685c1045c931430307', '2026-05-03 12:26:45'),
(917, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"34b3257d15cb3b7840637b613e8e52bc409f1b3bdb895032cf419ecf07c74569\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'eb96339a2d15fddabb9226ca0c230c701d2739ed0e522e685c1045c931430307', 'ccbcef8010eb98b4b5c8852259a0dbba8da9090c8833978762d7e61f55e0b750', '2026-05-03 12:26:51'),
(918, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"34b3257d15cb3b7840637b613e8e52bc409f1b3bdb895032cf419ecf07c74569\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"34b3257d15cb3b7840637b613e8e52bc409f1b3bdb895032cf419ecf07c74569\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ccbcef8010eb98b4b5c8852259a0dbba8da9090c8833978762d7e61f55e0b750', '265d3c63cb299c4179dd38fd8651f134d9001f4377dd1e19410a62f0a6062fed', '2026-05-03 12:27:14'),
(919, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"7b03a0c590c287d2616a6cf52f2e4cf913adeb2a8a16f9abcc171ff8e3f78824\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '265d3c63cb299c4179dd38fd8651f134d9001f4377dd1e19410a62f0a6062fed', '3730f8fdbf697266e95280588b7321344380ca3d1149728bdbcb9d248ad4e258', '2026-05-03 12:27:19'),
(920, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"7b03a0c590c287d2616a6cf52f2e4cf913adeb2a8a16f9abcc171ff8e3f78824\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"7b03a0c590c287d2616a6cf52f2e4cf913adeb2a8a16f9abcc171ff8e3f78824\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3730f8fdbf697266e95280588b7321344380ca3d1149728bdbcb9d248ad4e258', 'c2ac9ce3dc7b28cf271aec23ae39f9904a912cf00940e73b743a20f7b9386269', '2026-05-03 12:31:39'),
(921, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"f28c97fc2a9f34c4f60467d507de4de12e002e64cc65fbf4bf2c520b73266709\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c2ac9ce3dc7b28cf271aec23ae39f9904a912cf00940e73b743a20f7b9386269', 'aeb150a6e6bd23085d9223cbcf8d04bd1d91deb5e4861346a0fa494733e84c96', '2026-05-03 12:31:44'),
(922, 4, 'adjust_stock', 'ingredients', 11, '{\"current_stock\":25}', '{\"current_stock\":20,\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'aeb150a6e6bd23085d9223cbcf8d04bd1d91deb5e4861346a0fa494733e84c96', 'e0adc202996a80693b02df90d22d087fbe4388ac619e98749ba475b9d437d7fc', '2026-05-03 12:32:37'),
(923, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"f28c97fc2a9f34c4f60467d507de4de12e002e64cc65fbf4bf2c520b73266709\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"f28c97fc2a9f34c4f60467d507de4de12e002e64cc65fbf4bf2c520b73266709\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e0adc202996a80693b02df90d22d087fbe4388ac619e98749ba475b9d437d7fc', '955e3df05ac078c55fd3dea10e0f344b029f2863e505e2097a631e9ce4f473ec', '2026-05-03 12:39:01'),
(924, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"6630ab9345bce971de35099569194889ca1402ddd4909e475a981cabab79b376\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '955e3df05ac078c55fd3dea10e0f344b029f2863e505e2097a631e9ce4f473ec', '5d9c0a2779b242754dea285c23d8a68a3b14810cfedb8863e82176eeca8cd52a', '2026-05-03 12:39:09'),
(925, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"6630ab9345bce971de35099569194889ca1402ddd4909e475a981cabab79b376\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6630ab9345bce971de35099569194889ca1402ddd4909e475a981cabab79b376\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5d9c0a2779b242754dea285c23d8a68a3b14810cfedb8863e82176eeca8cd52a', '5818a0793995b8f8d78bfac864c40c49d9da4d4eb3ccc3aab25d43ff3b4bf33c', '2026-05-03 12:40:29'),
(926, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"c3feb455a3ec467bbd66f9566aff92dfb7efb200b869ed2b5681980f82bc05b4\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5818a0793995b8f8d78bfac864c40c49d9da4d4eb3ccc3aab25d43ff3b4bf33c', 'c2ca6b01ef049ecd83a80ca3f60d6ff02e428e49ba25cfb3a31b57d4f1d5cfdd', '2026-05-03 12:40:32'),
(927, 2, 'RELEASE', 'production_batches', 31, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c2ca6b01ef049ecd83a80ca3f60d6ff02e428e49ba25cfb3a31b57d4f1d5cfdd', 'b74b46c07126d41481db813d6084162590c956974f71880ab623970f6e146152', '2026-05-03 12:40:46'),
(928, 2, 'RELEASE', 'production_batches', 30, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b74b46c07126d41481db813d6084162590c956974f71880ab623970f6e146152', '467baed7c6ee94d6fcf4172ffd59e1828983e1073c101ca64662f41f2dc80ad8', '2026-05-03 12:40:53'),
(929, 2, 'RELEASE', 'production_batches', 29, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '467baed7c6ee94d6fcf4172ffd59e1828983e1073c101ca64662f41f2dc80ad8', '9582c51c8d677847ae722d04abc2e3dbe22ea5e489a1aaa66a22b175756d4fe6', '2026-05-03 12:41:02'),
(930, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"c3feb455a3ec467bbd66f9566aff92dfb7efb200b869ed2b5681980f82bc05b4\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"c3feb455a3ec467bbd66f9566aff92dfb7efb200b869ed2b5681980f82bc05b4\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9582c51c8d677847ae722d04abc2e3dbe22ea5e489a1aaa66a22b175756d4fe6', '81adfdfe54e0a6c52a83a5125a9302aa40d07d9a12951e6accdedbc2fa927b75', '2026-05-03 12:41:04'),
(931, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"bf02841bc793b5c62bd7c133a21b899bf305b2cddeb50cebd8efef1539b56289\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '81adfdfe54e0a6c52a83a5125a9302aa40d07d9a12951e6accdedbc2fa927b75', 'f845dd41744909ce455fb810fd9d1569c9cb89037c649835e73b22797959bc8a', '2026-05-03 12:41:09'),
(932, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"bf02841bc793b5c62bd7c133a21b899bf305b2cddeb50cebd8efef1539b56289\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"bf02841bc793b5c62bd7c133a21b899bf305b2cddeb50cebd8efef1539b56289\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f845dd41744909ce455fb810fd9d1569c9cb89037c649835e73b22797959bc8a', '216e23b9e8c5c7cda5f14aa266cecd677aca7f809d8271453281b1d40b95a806', '2026-05-03 12:41:24'),
(933, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"f60f658ba26cbb5fdaea1f1163ced595371e9420cde62548d4fc7146221abee2\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '216e23b9e8c5c7cda5f14aa266cecd677aca7f809d8271453281b1d40b95a806', 'a17c0596151194670dfcfb2f23f50e8a1075c19bb0387d4f804de3093af7a10b', '2026-05-03 12:41:28'),
(934, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"f60f658ba26cbb5fdaea1f1163ced595371e9420cde62548d4fc7146221abee2\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"f60f658ba26cbb5fdaea1f1163ced595371e9420cde62548d4fc7146221abee2\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a17c0596151194670dfcfb2f23f50e8a1075c19bb0387d4f804de3093af7a10b', 'afef3e4f3a81e6d7d437b462253641856188006a490b2e4d27cf65f33813f431', '2026-05-03 12:42:12'),
(935, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"b72dc7e93548436de7aa6a30e75867180dda57be112e0f9fdf5b5e24ea53bb4b\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'afef3e4f3a81e6d7d437b462253641856188006a490b2e4d27cf65f33813f431', '8d52e9bf44dd68dfffe715face9b881cc74194d3a4920f8d057a4b874993df8b', '2026-05-03 12:42:14'),
(936, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"b72dc7e93548436de7aa6a30e75867180dda57be112e0f9fdf5b5e24ea53bb4b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"b72dc7e93548436de7aa6a30e75867180dda57be112e0f9fdf5b5e24ea53bb4b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8d52e9bf44dd68dfffe715face9b881cc74194d3a4920f8d057a4b874993df8b', '4d63392757f4ec80c5c4278ce7612b5a9b14c6400acbed638d1abfdb628b6135', '2026-05-03 12:42:18'),
(937, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"059dde39b0181b1190028c8ce1c20feae3d29668bb318ad51a3b18b08aef17b5\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4d63392757f4ec80c5c4278ce7612b5a9b14c6400acbed638d1abfdb628b6135', '3e4b18681e635e7755e02e3050ebc628065391b80166333773db3923ec762df1', '2026-05-03 12:42:26'),
(938, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"059dde39b0181b1190028c8ce1c20feae3d29668bb318ad51a3b18b08aef17b5\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"059dde39b0181b1190028c8ce1c20feae3d29668bb318ad51a3b18b08aef17b5\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3e4b18681e635e7755e02e3050ebc628065391b80166333773db3923ec762df1', 'e8c786363f2e15f22549722f923d80e458d3d937da121af80c21784475b8341e', '2026-05-03 12:44:01'),
(939, 5, 'LOGIN', 'users', 5, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"10a4c9375c8a32f7b76f4bbbbaa0f37e1d191cfa803878fc309bef48f6d611e4\",\"username\":\"warehouse_fg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e8c786363f2e15f22549722f923d80e458d3d937da121af80c21784475b8341e', 'ba167c5fb6a19b1e5bcf5e0c732e5e6145f7198322f3a2ae5fd4943e60c329bd', '2026-05-03 12:44:08'),
(940, 5, 'LOGOUT', 'users', 5, '{\"authenticated\":true,\"session_id\":\"10a4c9375c8a32f7b76f4bbbbaa0f37e1d191cfa803878fc309bef48f6d611e4\"}', '{\"authenticated\":false,\"reason\":\"idle_timeout\",\"session_id\":\"10a4c9375c8a32f7b76f4bbbbaa0f37e1d191cfa803878fc309bef48f6d611e4\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ba167c5fb6a19b1e5bcf5e0c732e5e6145f7198322f3a2ae5fd4943e60c329bd', '9017a228dca2f95c483e44c8625cea3dfff7b4a5d6a85774dad0f5a5902da1c2', '2026-05-03 13:18:45'),
(941, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"3d4822291e5d62b68df4a74198267633539387b96c9108d6b9206da696f87785\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9017a228dca2f95c483e44c8625cea3dfff7b4a5d6a85774dad0f5a5902da1c2', '6457e30dd10ece21609a30865dcd9563079506174e6f605b53309c65d2a98c9a', '2026-05-03 14:01:11'),
(942, 4, 'CREATE', 'purchase_requests', 5, NULL, '{\"items_count\":1,\"pr_number\":\"PR-20260503-004\",\"priority\":\"high\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6457e30dd10ece21609a30865dcd9563079506174e6f605b53309c65d2a98c9a', '61a777db27ddd1b3d23725bb16a771ce95d3b4017c4e979c0e1af1b391a937e6', '2026-05-03 14:02:59'),
(943, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"3d4822291e5d62b68df4a74198267633539387b96c9108d6b9206da696f87785\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"3d4822291e5d62b68df4a74198267633539387b96c9108d6b9206da696f87785\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '61a777db27ddd1b3d23725bb16a771ce95d3b4017c4e979c0e1af1b391a937e6', 'e261d7871c898337f3eb87cf6ff7d4608e7ab8bc4a84937515cf1a9402ceb5c1', '2026-05-03 14:03:09'),
(944, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"b086c53185813b6e733ae65827468f52c04e679c482b75162093fb2ddd78cd48\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e261d7871c898337f3eb87cf6ff7d4608e7ab8bc4a84937515cf1a9402ceb5c1', 'b1b536d1118a4532e3ff0ceb499b054ca183f8ecede5efcd9472b6fecbe5259b', '2026-05-03 14:03:14'),
(945, 8, 'APPROVE', 'purchase_requests', 5, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b1b536d1118a4532e3ff0ceb499b054ca183f8ecede5efcd9472b6fecbe5259b', 'dd31ce81be50f96ec142500129015636494dbc45f5cebb257e53345b1194d65b', '2026-05-03 14:03:38'),
(946, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"b086c53185813b6e733ae65827468f52c04e679c482b75162093fb2ddd78cd48\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"b086c53185813b6e733ae65827468f52c04e679c482b75162093fb2ddd78cd48\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'dd31ce81be50f96ec142500129015636494dbc45f5cebb257e53345b1194d65b', '4340d6e13484af872bbf38931df89284a97792b7b9daff501c7796baadfa59c1', '2026-05-03 14:03:41'),
(947, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"e7c556f6b78661fe44ad0e065073f44a6708b303f6750e93e30ce4d875564c75\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4340d6e13484af872bbf38931df89284a97792b7b9daff501c7796baadfa59c1', '5d2f86ca7308729093f37596076fa779c8ddd7a272dc60a2192e8ae100eccc04', '2026-05-03 14:03:45'),
(948, 10, 'CREATE', 'purchase_orders', 30, NULL, '{\"items_count\":1,\"payment_terms\":\"credit_30\",\"po_number\":\"5260\",\"pr_number\":\"PR-20260503-004\",\"purchase_request_id\":\"5\",\"supplier_id\":\"5\",\"total_amount\":3200}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5d2f86ca7308729093f37596076fa779c8ddd7a272dc60a2192e8ae100eccc04', '20d51125010fea2336b10eb89bb58b98841e70978be0724a4b5f37e8089aaa5f', '2026-05-03 14:04:09'),
(949, 10, 'UPDATE', 'purchase_orders', 30, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '20d51125010fea2336b10eb89bb58b98841e70978be0724a4b5f37e8089aaa5f', 'b8bf5061fa9bd77690c08ab05a1b5b42c6866c604014a6cc2a3d127d27364985', '2026-05-03 14:04:45'),
(950, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"e7c556f6b78661fe44ad0e065073f44a6708b303f6750e93e30ce4d875564c75\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"e7c556f6b78661fe44ad0e065073f44a6708b303f6750e93e30ce4d875564c75\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'b8bf5061fa9bd77690c08ab05a1b5b42c6866c604014a6cc2a3d127d27364985', '0c969ad24da487bd765a631c07f8fabad3d625501e00051f6e0f01975c7ab03a', '2026-05-03 14:04:46'),
(951, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"6319d02c2a62b1584258e1361a2e091732280a3086464097cfe61d7ec66be61a\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0c969ad24da487bd765a631c07f8fabad3d625501e00051f6e0f01975c7ab03a', 'ca2cb160a8188799e93d903c8b87c90d7e3ec46bf827d576cfdab2d2fe466891', '2026-05-03 14:04:51'),
(952, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"6319d02c2a62b1584258e1361a2e091732280a3086464097cfe61d7ec66be61a\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6319d02c2a62b1584258e1361a2e091732280a3086464097cfe61d7ec66be61a\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ca2cb160a8188799e93d903c8b87c90d7e3ec46bf827d576cfdab2d2fe466891', 'bb80278c3e6f114831fc22eb8ea0e48fcf57a4b8bbfd1a840868b65858586b10', '2026-05-03 14:05:16'),
(953, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"b148314a8aa071af789be593ef4378d786276e2f701ed7fb174033dd1465bc0a\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bb80278c3e6f114831fc22eb8ea0e48fcf57a4b8bbfd1a840868b65858586b10', '1ff0da6c3977ceb40f3c29670cfae282d16e36175176830b2cfc99b5f55b67a6', '2026-05-03 14:05:20'),
(954, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"b148314a8aa071af789be593ef4378d786276e2f701ed7fb174033dd1465bc0a\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"b148314a8aa071af789be593ef4378d786276e2f701ed7fb174033dd1465bc0a\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1ff0da6c3977ceb40f3c29670cfae282d16e36175176830b2cfc99b5f55b67a6', '0902f35377357a7b12003c2e9d96f1fc53ce04a5426f63278c10388e43bdec34', '2026-05-03 14:06:12'),
(955, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"d763dfb5f14d0a2d724392d958302984bb746c0b574d73fbc6805bdbca7ff0c5\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0902f35377357a7b12003c2e9d96f1fc53ce04a5426f63278c10388e43bdec34', '65759362cdbab28ea17e75353b5816bc1f7586ebf0f5a415298618f4c31b29e3', '2026-05-03 14:06:21'),
(956, 8, 'APPROVE', 'purchase_orders', 30, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '65759362cdbab28ea17e75353b5816bc1f7586ebf0f5a415298618f4c31b29e3', '3f7301fb72445b03b397e666b2251a3da28083941e711c3a922eb7edf091f815', '2026-05-03 14:06:30'),
(957, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"d763dfb5f14d0a2d724392d958302984bb746c0b574d73fbc6805bdbca7ff0c5\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"d763dfb5f14d0a2d724392d958302984bb746c0b574d73fbc6805bdbca7ff0c5\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3f7301fb72445b03b397e666b2251a3da28083941e711c3a922eb7edf091f815', 'ba316fa5d5ed1db6a2695bf5691c69206d5c87de6fe045264b6903c3813ee249', '2026-05-03 14:06:32'),
(958, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"1899c5b191c0f063a41267cce07fcd085bee48957e2e701a422d8c452988a94c\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'ba316fa5d5ed1db6a2695bf5691c69206d5c87de6fe045264b6903c3813ee249', 'fcae9da1e348af5a916b3a717bc67969053f549659ee1b0ecd0cf6dd840ce0f8', '2026-05-03 14:06:39'),
(959, 10, 'UPDATE', 'purchase_orders', 30, '{\"status\":\"approved\"}', '{\"status\":\"ordered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'fcae9da1e348af5a916b3a717bc67969053f549659ee1b0ecd0cf6dd840ce0f8', '133d3419dee922eea8d99a62b5e263596c394185cdd0c8bd2efceffe82989297', '2026-05-03 14:07:29'),
(960, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"1899c5b191c0f063a41267cce07fcd085bee48957e2e701a422d8c452988a94c\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"1899c5b191c0f063a41267cce07fcd085bee48957e2e701a422d8c452988a94c\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '133d3419dee922eea8d99a62b5e263596c394185cdd0c8bd2efceffe82989297', 'c5b974af75d8866b110fbcd38a06bef4d98a68a7fb552a1e6c85b9d4850b229d', '2026-05-03 14:07:32'),
(961, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"6a8e8717b0e53f599b2a04c744165f5b8f978a741850c70c04654bd62d821722\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c5b974af75d8866b110fbcd38a06bef4d98a68a7fb552a1e6c85b9d4850b229d', '41fce4c1927634d3f3454e2b32e2e46a676c9cd173a8e5dc59c83d18072e8c5b', '2026-05-03 14:07:35'),
(962, 4, 'RECEIVE', 'purchase_orders', 30, '{\"status\":\"ordered\"}', '{\"accepted\":8,\"rejected\":2,\"status\":\"received\",\"stocked_in_items\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '41fce4c1927634d3f3454e2b32e2e46a676c9cd173a8e5dc59c83d18072e8c5b', 'c7893864c44af6bb9e8ca33eb4103d7b45512a5d46b101f83a82c01ec764a629', '2026-05-03 14:08:06'),
(963, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"6a8e8717b0e53f599b2a04c744165f5b8f978a741850c70c04654bd62d821722\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6a8e8717b0e53f599b2a04c744165f5b8f978a741850c70c04654bd62d821722\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'c7893864c44af6bb9e8ca33eb4103d7b45512a5d46b101f83a82c01ec764a629', '0567407aaf21b535c2994a3aacc2dac0d693e1f71f5f60eced64d9575350d85c', '2026-05-03 14:08:38'),
(964, 11, 'LOGIN', 'users', 11, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"f5fbe0cbe2cef24cf6d53af9f010b62211404e9751bc00f76ac5b5095d984803\",\"username\":\"finance_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0567407aaf21b535c2994a3aacc2dac0d693e1f71f5f60eced64d9575350d85c', '67116920ead18a24a40b277a3a605e4bfceed4249f5f9714fa935a6f8b23f855', '2026-05-03 14:08:42'),
(965, 11, 'LOGOUT', 'users', 11, '{\"authenticated\":true,\"session_id\":\"f5fbe0cbe2cef24cf6d53af9f010b62211404e9751bc00f76ac5b5095d984803\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"f5fbe0cbe2cef24cf6d53af9f010b62211404e9751bc00f76ac5b5095d984803\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '67116920ead18a24a40b277a3a605e4bfceed4249f5f9714fa935a6f8b23f855', '31b2be701495f0737256f85b8cedae5a967958ac84aa807fd49cc071be929d01', '2026-05-03 14:10:11'),
(966, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"19672737ee3ca04b7bd3c21cd55c6b40f6e8408cd77bdee41cd815fb911140a5\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '31b2be701495f0737256f85b8cedae5a967958ac84aa807fd49cc071be929d01', '1156cd0b88f80979f42e2e1d9144e2b55a3a934b449018193b25a434f0982431', '2026-05-03 14:11:42'),
(967, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"19672737ee3ca04b7bd3c21cd55c6b40f6e8408cd77bdee41cd815fb911140a5\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"19672737ee3ca04b7bd3c21cd55c6b40f6e8408cd77bdee41cd815fb911140a5\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1156cd0b88f80979f42e2e1d9144e2b55a3a934b449018193b25a434f0982431', '26f4fb638617e5ba89f781c3a08707eafe945d01af456d21fc44bacb4c1b9302', '2026-05-03 14:11:55'),
(968, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"351eade1aba1a621d89e40f168c040f7dc0c3dc4795f8cd810951c73e5cb4768\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '26f4fb638617e5ba89f781c3a08707eafe945d01af456d21fc44bacb4c1b9302', 'f89a03fa38a495c54ef3b3396181b4057f4455f0f8e0591064ea5f2e2ee8ab82', '2026-05-03 14:11:59'),
(969, 4, 'CREATE', 'purchase_requests', 6, NULL, '{\"items_count\":1,\"pr_number\":\"PR-20260503-005\",\"priority\":\"normal\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f89a03fa38a495c54ef3b3396181b4057f4455f0f8e0591064ea5f2e2ee8ab82', '05caaffa07e2ce56491b4e9d1e6068ab59175bbb530946f9f5b2173be62d0b44', '2026-05-03 14:12:42'),
(970, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"351eade1aba1a621d89e40f168c040f7dc0c3dc4795f8cd810951c73e5cb4768\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"351eade1aba1a621d89e40f168c040f7dc0c3dc4795f8cd810951c73e5cb4768\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '05caaffa07e2ce56491b4e9d1e6068ab59175bbb530946f9f5b2173be62d0b44', '86f6e45649d50b824b6c1d403e3cb1de5dbff6cacfa35333a2f5d1b980836911', '2026-05-03 14:12:45'),
(971, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"922ba815be7bd9d1373a415463143d172d011c3c557a20d1116962c44181cf0b\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '86f6e45649d50b824b6c1d403e3cb1de5dbff6cacfa35333a2f5d1b980836911', '4d928b85aaceef9d30ec9f81696a7982bd91c7a6685bc43740d9735edf7f87ae', '2026-05-03 14:12:48'),
(972, 8, 'APPROVE', 'purchase_requests', 6, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4d928b85aaceef9d30ec9f81696a7982bd91c7a6685bc43740d9735edf7f87ae', '7a677680bc4801e7b4efaa6a044ff9ddb49be16651bee8c52a8b8af215a91d2e', '2026-05-03 14:12:54'),
(973, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"922ba815be7bd9d1373a415463143d172d011c3c557a20d1116962c44181cf0b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"922ba815be7bd9d1373a415463143d172d011c3c557a20d1116962c44181cf0b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7a677680bc4801e7b4efaa6a044ff9ddb49be16651bee8c52a8b8af215a91d2e', '24173efc247a9f003282fc48bda499342c961a15ca03310426d317ea2ca59380', '2026-05-03 14:12:55'),
(974, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"51418b8501e6f33b76a3813d6c76b4d0c152761f55473c509ef5149391795ec4\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '24173efc247a9f003282fc48bda499342c961a15ca03310426d317ea2ca59380', 'beb95e7fdf04d6d124a43dfeca796a21d38fb9aa541548e08a148b9b76e529a2', '2026-05-03 14:12:58'),
(975, 10, 'CREATE', 'purchase_orders', 31, NULL, '{\"items_count\":1,\"payment_terms\":\"credit_30\",\"po_number\":\"5261\",\"pr_number\":\"PR-20260503-005\",\"purchase_request_id\":\"6\",\"supplier_id\":\"4\",\"total_amount\":640}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'beb95e7fdf04d6d124a43dfeca796a21d38fb9aa541548e08a148b9b76e529a2', '6504853ff5eeaea5077fa3ad90e3e5a2367a9037a1cd20a469f90c8294dcca3a', '2026-05-03 14:13:41'),
(976, 10, 'UPDATE', 'purchase_orders', 31, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6504853ff5eeaea5077fa3ad90e3e5a2367a9037a1cd20a469f90c8294dcca3a', '3b0c20972f43528ebb8b915c90db9b3cf5464d3de9c8ebf20bd619f2db0cd1da', '2026-05-03 14:13:45'),
(977, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"51418b8501e6f33b76a3813d6c76b4d0c152761f55473c509ef5149391795ec4\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"51418b8501e6f33b76a3813d6c76b4d0c152761f55473c509ef5149391795ec4\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3b0c20972f43528ebb8b915c90db9b3cf5464d3de9c8ebf20bd619f2db0cd1da', '46964574cd633ccbb1efc54dcb07386ec6048f6cd68085691fe055b5d909c927', '2026-05-03 14:13:47'),
(978, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"362c87f899e086503a40b30e556539b6b4bcc174c9e1761f925d9daa99358c45\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '46964574cd633ccbb1efc54dcb07386ec6048f6cd68085691fe055b5d909c927', 'e8aa3ee83e14180d82e30ab2f9cc7f7683db10181f24366665abe3c6246ba614', '2026-05-03 14:13:51'),
(979, 8, 'APPROVE', 'purchase_orders', 31, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e8aa3ee83e14180d82e30ab2f9cc7f7683db10181f24366665abe3c6246ba614', '9256121f5924623bf748d51ed28967f87fa586bbef738d3de018a39638420ac1', '2026-05-03 14:14:05'),
(980, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"362c87f899e086503a40b30e556539b6b4bcc174c9e1761f925d9daa99358c45\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"362c87f899e086503a40b30e556539b6b4bcc174c9e1761f925d9daa99358c45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '9256121f5924623bf748d51ed28967f87fa586bbef738d3de018a39638420ac1', 'f6df7169d706e87fee94eff489bb4c2be0044d84ac88fe3f61c8945e46403f55', '2026-05-03 14:14:06'),
(981, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"896884fbd518373eae16db2365dea16b5679ec851f72d26a5debfc3fd7fdfdaa\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f6df7169d706e87fee94eff489bb4c2be0044d84ac88fe3f61c8945e46403f55', 'f55327c1c18e6fa1b346397ded87655c90c165e532928a89c668d65ab69624d4', '2026-05-03 14:14:11'),
(982, 10, 'UPDATE', 'purchase_orders', 31, '{\"status\":\"approved\"}', '{\"status\":\"ordered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f55327c1c18e6fa1b346397ded87655c90c165e532928a89c668d65ab69624d4', '688818cdd92701a7c973c811cde5487e9c9a3cbd6cb152ef51e617fadc8c1e3d', '2026-05-03 14:14:44'),
(983, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"896884fbd518373eae16db2365dea16b5679ec851f72d26a5debfc3fd7fdfdaa\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"896884fbd518373eae16db2365dea16b5679ec851f72d26a5debfc3fd7fdfdaa\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '688818cdd92701a7c973c811cde5487e9c9a3cbd6cb152ef51e617fadc8c1e3d', '2273db5c6238ca0e3a50ff8bf3a86d87cb0bfea9cefaaffaab1aed0e0a4b59b5', '2026-05-03 14:14:48');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `prev_hash`, `entry_hash`, `created_at`) VALUES
(984, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"b0033c20f23625df4b438ec2777658da8c941d4d83deef3c2e53cec7734cd4bf\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2273db5c6238ca0e3a50ff8bf3a86d87cb0bfea9cefaaffaab1aed0e0a4b59b5', '6e5b3b02fcc5655b090da9ef7c088343ae7a22a2f28c377a1a5d259861000e1e', '2026-05-03 14:14:54'),
(985, 4, 'RECEIVE', 'purchase_orders', 31, '{\"status\":\"ordered\"}', '{\"accepted\":2,\"rejected\":0,\"status\":\"received\",\"stocked_in_items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6e5b3b02fcc5655b090da9ef7c088343ae7a22a2f28c377a1a5d259861000e1e', '420d3abc63e68652094a9c332ad6e18202dc8a1ba24693ca59031c07cfdbd34d', '2026-05-03 14:15:37'),
(986, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"b0033c20f23625df4b438ec2777658da8c941d4d83deef3c2e53cec7734cd4bf\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"b0033c20f23625df4b438ec2777658da8c941d4d83deef3c2e53cec7734cd4bf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '420d3abc63e68652094a9c332ad6e18202dc8a1ba24693ca59031c07cfdbd34d', '5e3ce4288226b442ee90046f8c9dfd443aeae9a1ace2dabf27a4a779df762de7', '2026-05-03 14:16:13'),
(987, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"c77d71bc6b2a69f912000c7da243292152412b32fc537ccd5825e100013bf988\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '5e3ce4288226b442ee90046f8c9dfd443aeae9a1ace2dabf27a4a779df762de7', 'da5599361fd863f5eee6cd4e8fb5c92be9bd8c47588417659fa6f41d83f338d0', '2026-05-03 14:16:17'),
(988, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"c77d71bc6b2a69f912000c7da243292152412b32fc537ccd5825e100013bf988\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"c77d71bc6b2a69f912000c7da243292152412b32fc537ccd5825e100013bf988\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'da5599361fd863f5eee6cd4e8fb5c92be9bd8c47588417659fa6f41d83f338d0', '7b14083006a60a93c42daccc967d91a9c98a74f9c85d4eae02b89cf235ae0cfc', '2026-05-03 14:16:20'),
(989, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"de2f0bd2bc619224f16139633d797e66611c1cc09c3b605f1b52ad35bc0821d6\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '7b14083006a60a93c42daccc967d91a9c98a74f9c85d4eae02b89cf235ae0cfc', 'fb9aadb5a03bd162d670d477ca869f0d9f33456ed3e3de5aee8d6a7e6bcf8da8', '2026-05-03 14:16:25'),
(990, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"de2f0bd2bc619224f16139633d797e66611c1cc09c3b605f1b52ad35bc0821d6\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"de2f0bd2bc619224f16139633d797e66611c1cc09c3b605f1b52ad35bc0821d6\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'fb9aadb5a03bd162d670d477ca869f0d9f33456ed3e3de5aee8d6a7e6bcf8da8', '207b40d17f838c105b833933e6d6385fd743a7ac0b7e4b13aaa76aa19f1f032a', '2026-05-03 14:16:38'),
(991, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"c86c8d71a579bec724a36c3dea4e124c5c4d80379d0dc8bd68eadee8e254dcff\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '207b40d17f838c105b833933e6d6385fd743a7ac0b7e4b13aaa76aa19f1f032a', '80a6cef5403dcdce1cc764ec8c63f6272226dc8c25ca8dfc861cb919c1e3772a', '2026-05-03 14:16:49'),
(992, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"c86c8d71a579bec724a36c3dea4e124c5c4d80379d0dc8bd68eadee8e254dcff\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"c86c8d71a579bec724a36c3dea4e124c5c4d80379d0dc8bd68eadee8e254dcff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '80a6cef5403dcdce1cc764ec8c63f6272226dc8c25ca8dfc861cb919c1e3772a', '1b865f74784be956d24c99771d89adbbd94e29e031fa80a0ec9ef144f63a7b53', '2026-05-03 14:17:35'),
(993, 3, 'LOGIN', 'users', 3, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"7cb8be4c88c0ad98087942ded95abd1d70bdd135cc0683917e525005ebe64acb\",\"username\":\"production_staff\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1b865f74784be956d24c99771d89adbbd94e29e031fa80a0ec9ef144f63a7b53', '080fc6701c211b0f20a7d0efb043eb62a6b51c8178bd39ec9f22953bb514ad07', '2026-05-03 14:17:40'),
(994, 3, 'LOGOUT', 'users', 3, '{\"authenticated\":true,\"session_id\":\"7cb8be4c88c0ad98087942ded95abd1d70bdd135cc0683917e525005ebe64acb\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"7cb8be4c88c0ad98087942ded95abd1d70bdd135cc0683917e525005ebe64acb\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '080fc6701c211b0f20a7d0efb043eb62a6b51c8178bd39ec9f22953bb514ad07', 'fbb226e89ba77fc3f4276064a8464797c84376d0a7ca9ab9edae1dafa1fa7937', '2026-05-03 14:25:03'),
(995, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"b4052b0804cf86288a1a3a2afbad1d2694953545585dd3565744fe443747f32f\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'fbb226e89ba77fc3f4276064a8464797c84376d0a7ca9ab9edae1dafa1fa7937', '0357042098ea30ab6989d33961b99bc2e9cedcf329bbab1e953e2fce84acca7c', '2026-05-03 14:25:11'),
(996, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"b4052b0804cf86288a1a3a2afbad1d2694953545585dd3565744fe443747f32f\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"b4052b0804cf86288a1a3a2afbad1d2694953545585dd3565744fe443747f32f\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '0357042098ea30ab6989d33961b99bc2e9cedcf329bbab1e953e2fce84acca7c', 'e99d4ab6490a910d308bfbce0f70de8c4d9a459e9843236cbad3aa0859b6138e', '2026-05-03 14:25:35'),
(997, 2, 'LOGIN', 'users', 2, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"c3f35fdbd2905b0b9737dc32a833b75cb7a4ab91c170dab8d351d0b8d666606f\",\"username\":\"qc_officer\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'e99d4ab6490a910d308bfbce0f70de8c4d9a459e9843236cbad3aa0859b6138e', 'bca16d4c540e39bd48c68a5b7003e3430ea0ebd2d1490d4480700e763d592fbd', '2026-05-03 14:25:43'),
(998, 2, 'LOGOUT', 'users', 2, '{\"authenticated\":true,\"session_id\":\"c3f35fdbd2905b0b9737dc32a833b75cb7a4ab91c170dab8d351d0b8d666606f\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"c3f35fdbd2905b0b9737dc32a833b75cb7a4ab91c170dab8d351d0b8d666606f\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'bca16d4c540e39bd48c68a5b7003e3430ea0ebd2d1490d4480700e763d592fbd', 'cf7cf893cd89e584f53ea886de76526eefa73f1b49009c8f74cf87d43d0f677e', '2026-05-03 14:26:08'),
(999, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"e5dc0e7b952e4545c82ec12700cf5412ab19be60173b1db2f7a6c4bd6603fd4e\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'cf7cf893cd89e584f53ea886de76526eefa73f1b49009c8f74cf87d43d0f677e', '6dfe0ca3e3b836bebbee72935e48218b76836bfee843aaa3207a907fe5d992ed', '2026-05-03 14:26:13'),
(1000, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"e5dc0e7b952e4545c82ec12700cf5412ab19be60173b1db2f7a6c4bd6603fd4e\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"e5dc0e7b952e4545c82ec12700cf5412ab19be60173b1db2f7a6c4bd6603fd4e\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '6dfe0ca3e3b836bebbee72935e48218b76836bfee843aaa3207a907fe5d992ed', '151d8ca07cd9d8c245171d81c878c1cd28791600b0f7609fde2a6e287c04c015', '2026-05-03 14:26:26'),
(1001, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"49bb3a13854d421eb9b18b87b2c8111896f8e21c8b7c0add72919e1ce9cc2608\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '151d8ca07cd9d8c245171d81c878c1cd28791600b0f7609fde2a6e287c04c015', '1a285806aae76a73bb527f2e4e82da4697723850ca1335078453eee1bae597f3', '2026-05-03 14:26:32'),
(1002, 4, 'CREATE', 'purchase_requests', 7, NULL, '{\"items_count\":1,\"pr_number\":\"PR-20260503-006\",\"priority\":\"high\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1a285806aae76a73bb527f2e4e82da4697723850ca1335078453eee1bae597f3', '1b8d9f68196a55179df38e56ff6ac975e2f1c04d0a275a4d0a57fae68d6c6d0c', '2026-05-03 14:27:01'),
(1003, 4, 'LOGOUT', 'users', 4, '{\"authenticated\":true,\"session_id\":\"49bb3a13854d421eb9b18b87b2c8111896f8e21c8b7c0add72919e1ce9cc2608\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"49bb3a13854d421eb9b18b87b2c8111896f8e21c8b7c0add72919e1ce9cc2608\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '1b8d9f68196a55179df38e56ff6ac975e2f1c04d0a275a4d0a57fae68d6c6d0c', '742662f6c975ec6f0b1e35fa3b14a29b605fdb132c53683a7be9aa03abc55695', '2026-05-03 14:27:10'),
(1004, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"e1cbd7b46ac28de89cea8ad6fdd42218c5deae358b0ae62aa75b0d0a622bd8d3\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '742662f6c975ec6f0b1e35fa3b14a29b605fdb132c53683a7be9aa03abc55695', '524af02d2d23f6903558ec4bc3b634551bdce6c3c3bc985d547185563b71877d', '2026-05-03 14:27:13'),
(1005, 8, 'APPROVE', 'purchase_requests', 7, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '524af02d2d23f6903558ec4bc3b634551bdce6c3c3bc985d547185563b71877d', '3b0afebbabec3d6a1c541ce184b57d91a02bbd85d58c4d48e6b1dc240a950455', '2026-05-03 14:27:20'),
(1006, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"e1cbd7b46ac28de89cea8ad6fdd42218c5deae358b0ae62aa75b0d0a622bd8d3\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"e1cbd7b46ac28de89cea8ad6fdd42218c5deae358b0ae62aa75b0d0a622bd8d3\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '3b0afebbabec3d6a1c541ce184b57d91a02bbd85d58c4d48e6b1dc240a950455', '69303c9f3c492aac0d78076a1ecf4b0beb9f81e7820cc45e7a453bebbc5b9779', '2026-05-03 14:27:22'),
(1007, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"6b13a159f0260533419351e8204428270f542e1cd99c1456584fc069c7a5ff8b\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '69303c9f3c492aac0d78076a1ecf4b0beb9f81e7820cc45e7a453bebbc5b9779', 'a45568ab7c3264874f266e83df74a5d496bbb8d0659d444137a470d6897d004e', '2026-05-03 14:27:30'),
(1008, 10, 'CREATE', 'purchase_orders', 32, NULL, '{\"items_count\":1,\"payment_terms\":\"credit_30\",\"po_number\":\"5262\",\"pr_number\":\"PR-20260503-006\",\"purchase_request_id\":\"7\",\"supplier_id\":\"3\",\"total_amount\":700}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a45568ab7c3264874f266e83df74a5d496bbb8d0659d444137a470d6897d004e', 'afa6cfa64409e96db8613057cd9f7d45c7afadc631d1bcb53b53240b91ad3508', '2026-05-03 14:27:48'),
(1009, 10, 'UPDATE', 'purchase_orders', 32, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'afa6cfa64409e96db8613057cd9f7d45c7afadc631d1bcb53b53240b91ad3508', 'f1216fa7f1b201f078080faad7134ffce9176a54cd2573daf83aac9bbfe21655', '2026-05-03 14:27:58'),
(1010, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"6b13a159f0260533419351e8204428270f542e1cd99c1456584fc069c7a5ff8b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"6b13a159f0260533419351e8204428270f542e1cd99c1456584fc069c7a5ff8b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'f1216fa7f1b201f078080faad7134ffce9176a54cd2573daf83aac9bbfe21655', '265fef372d7552390e1d5aa2507885fab2d3ae7b5382b5ab96b3db61b5d9f151', '2026-05-03 14:28:01'),
(1011, 8, 'LOGIN', 'users', 8, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"1047aece70c43d251359faeec954ca32c04315a50399ee5603a7f5f073a005df\",\"username\":\"general_manager\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '265fef372d7552390e1d5aa2507885fab2d3ae7b5382b5ab96b3db61b5d9f151', '8bafe58ddedb2229b0ed71722a9947f7202e0470915ff01425d7e7c6b18dc680', '2026-05-03 14:28:14'),
(1012, 8, 'APPROVE', 'purchase_orders', 32, '{\"status\":\"pending\"}', '{\"approved_by\":8,\"status\":\"approved\",\"step_up_verified\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '8bafe58ddedb2229b0ed71722a9947f7202e0470915ff01425d7e7c6b18dc680', '4bbb7568c039c998b29a0904c61eb2b82b25c56afdc890accf4a109b32a09206', '2026-05-03 14:28:25'),
(1013, 8, 'LOGOUT', 'users', 8, '{\"authenticated\":true,\"session_id\":\"1047aece70c43d251359faeec954ca32c04315a50399ee5603a7f5f073a005df\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"1047aece70c43d251359faeec954ca32c04315a50399ee5603a7f5f073a005df\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '4bbb7568c039c998b29a0904c61eb2b82b25c56afdc890accf4a109b32a09206', '038ab3b6775aa07f0aa322235bd7e7ed2fd00223eb81fa4b5f54f0644a2c548d', '2026-05-03 14:28:26'),
(1014, 10, 'LOGIN', 'users', 10, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"68304499e4a771a7e137dede22123de5da86c04f0202b98f1ec2b5968c8ce10b\",\"username\":\"purchaser\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '038ab3b6775aa07f0aa322235bd7e7ed2fd00223eb81fa4b5f54f0644a2c548d', '91f954c301cbc44ad2cbf92472e174af8e614951c5581ab89c95664be54f684e', '2026-05-03 14:28:30'),
(1015, 10, 'UPDATE', 'purchase_orders', 32, '{\"status\":\"approved\"}', '{\"status\":\"ordered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '91f954c301cbc44ad2cbf92472e174af8e614951c5581ab89c95664be54f684e', 'a1b57d9d4a1346a4449bb7289f1f099fbff72d8e7328fee54515c26ffed6602d', '2026-05-03 14:28:38'),
(1016, 10, 'LOGOUT', 'users', 10, '{\"authenticated\":true,\"session_id\":\"68304499e4a771a7e137dede22123de5da86c04f0202b98f1ec2b5968c8ce10b\"}', '{\"authenticated\":false,\"reason\":\"manual_logout\",\"session_id\":\"68304499e4a771a7e137dede22123de5da86c04f0202b98f1ec2b5968c8ce10b\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'a1b57d9d4a1346a4449bb7289f1f099fbff72d8e7328fee54515c26ffed6602d', '2c972002a793dcb7a9c7d863ea9543662feb406b53053cfff7c54a43c0d92cbd', '2026-05-03 14:28:49'),
(1017, 4, 'LOGIN', 'users', 4, '{\"authenticated\":false}', '{\"authenticated\":true,\"login_type\":\"email\",\"session_id\":\"0ebb07711fdc2e691ef06a8f113daf4ac184195e689f631bc187d4729cd05420\",\"username\":\"warehouse_raw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2c972002a793dcb7a9c7d863ea9543662feb406b53053cfff7c54a43c0d92cbd', '494eb4b37a7e4e39f5531b1de84cc1ea93de8106480ebd114e58c4717a8a9f27', '2026-05-03 14:28:52'),
(1018, 4, 'RECEIVE', 'purchase_orders', 32, '{\"status\":\"ordered\"}', '{\"accepted\":1,\"rejected\":1,\"status\":\"received\",\"stocked_in_items\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '494eb4b37a7e4e39f5531b1de84cc1ea93de8106480ebd114e58c4717a8a9f27', 'c1b2227a0005711e1775001e3d3193dc1af21c74cedc3769b90140e8d50f6dca', '2026-05-03 14:30:13');

-- --------------------------------------------------------

--
-- Table structure for table `auth_invites`
--

CREATE TABLE `auth_invites` (
  `id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL COMMENT 'SHA-256 hash of the invite token',
  `user_id` int(11) NOT NULL COMMENT 'The user this invite is for',
  `invite_type` enum('email','manual') NOT NULL DEFAULT 'email' COMMENT 'How the invite was delivered',
  `email_sent_to` varchar(255) DEFAULT NULL COMMENT 'Email address the invite was sent to',
  `expires_at` datetime NOT NULL COMMENT 'When the invite token expires',
  `used_at` datetime DEFAULT NULL COMMENT 'When the invite was used (set-password)',
  `created_by` int(11) NOT NULL COMMENT 'Admin who created the invite',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_invites`
--

INSERT INTO `auth_invites` (`id`, `token_hash`, `user_id`, `invite_type`, `email_sent_to`, `expires_at`, `used_at`, `created_by`, `created_at`) VALUES
(1, '7a393927817ae0f7b840875484d6ebbaa9e58060aa194f37b23f9965bd871952', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:41:03', '2026-04-25 05:41:09', 8, '2026-04-24 21:41:03'),
(2, '7b2295ec24465733b74f2d27e82827a5042e2395db0d8a104e6517ac4a5fb2ff', 13, 'manual', NULL, '2026-04-27 05:41:09', '2026-04-25 05:44:09', 8, '2026-04-24 21:41:09'),
(3, '2aa170f760b54f524d95b64f76329c57f7a900acfa7d27f363480ad30fc7ceaf', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:44:09', '2026-04-25 05:49:50', 8, '2026-04-24 21:44:09'),
(4, '3ef478ba8da0e696a3ab7e03022871d585729912fb08220c10b47ea466d5d4ae', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:49:50', '2026-04-25 05:49:55', 8, '2026-04-24 21:49:50'),
(5, 'd2256f6603e3c5bcee9f86a9701b4c9f3b47bbdc71fed339824e3b75451fc87e', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:49:55', '2026-04-25 05:55:49', 8, '2026-04-24 21:49:55'),
(6, '0bddcf73a56ec4b7219f056552fc29e8548f494977fdde46001bd1bfb5c2b032', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:55:49', '2026-04-25 05:55:52', 8, '2026-04-24 21:55:49'),
(7, '902b43a2e2e91c1882fe0f7ebda0cb9dc33c827172d538e3913021a18cd60924', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:55:52', '2026-04-25 05:56:40', 8, '2026-04-24 21:55:52'),
(8, '9bfbc3a4393e1384a53dd62551c6432358011e7f05647c79993f6b71934f51a0', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:57:45', '2026-04-25 05:58:02', 8, '2026-04-24 21:57:45'),
(9, '8158756f482d781d66f8989ddef3b8f9515f838588ec35b573d3efeab880c6b7', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 05:58:41', '2026-04-25 05:59:01', 8, '2026-04-24 21:58:41'),
(10, '3c2218c0d53b2ecd6231ad9c7e6e0dd5f63b82e368c585edea468bd2d29da896', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 06:00:48', '2026-04-25 06:01:03', 8, '2026-04-24 22:00:48'),
(11, 'dda6c84c8e1d0eded10fe045b7db3e92a7d11d1e777f1f42ed20cda333df2842', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-27 06:01:16', '2026-04-25 06:01:42', 8, '2026-04-24 22:01:16'),
(12, 'b370b42863c3db9fc4c844bca29abfcd0b1fdb1b4041765b6bb1d08fd4851a40', 13, 'email', 'ragasibrian2@gmail.com', '2026-04-29 14:17:14', '2026-04-27 14:18:38', 8, '2026-04-27 06:17:14');

-- --------------------------------------------------------

--
-- Table structure for table `auth_password_resets`
--

CREATE TABLE `auth_password_resets` (
  `id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL COMMENT 'SHA-256 hash of the reset token',
  `user_id` int(11) NOT NULL,
  `email_sent_to` varchar(255) NOT NULL COMMENT 'Email that received the link',
  `expires_at` datetime NOT NULL COMMENT 'Token expiry',
  `used_at` datetime DEFAULT NULL COMMENT 'When the token was consumed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_sessions`
--

CREATE TABLE `auth_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `issued_at` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_reason` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_sessions`
--

INSERT INTO `auth_sessions` (`id`, `session_id`, `user_id`, `ip_address`, `user_agent`, `issued_at`, `last_activity`, `expires_at`, `revoked_at`, `revoked_reason`, `created_at`, `updated_at`) VALUES
(1, 'c98190ecb0d6339122f796cc631c883b2504ac0274c78416a42d3a5dea4a57b3', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-13 20:18:14', '2026-03-13 20:18:48', '2026-03-14 04:18:14', '2026-03-13 20:18:48', 'manual_logout', '2026-03-13 12:18:14', '2026-03-13 12:18:48'),
(2, '6784e35289f059a6ea8785a7c8a55571d4ccbb490f3dd1bd8be35e1722ef0629', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-13 20:18:55', '2026-03-13 20:34:20', '2026-03-14 04:18:55', '2026-03-13 20:34:20', 'idle_timeout', '2026-03-13 12:18:55', '2026-03-13 12:34:20'),
(3, 'b61b7ebc3ec36540025e17c86ffdd7dc1f49d23a467735b934275258a267ddd7', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-13 20:47:00', '2026-03-13 21:49:20', '2026-03-14 04:47:00', '2026-03-13 21:49:20', 'idle_timeout', '2026-03-13 12:47:00', '2026-03-13 13:49:20'),
(4, '723e75cb9e22c2eecb331e0bf1dee2e0901fd6b1f1c3f2e15ff26540a5d2bcea', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-14 09:30:11', '2026-03-14 09:45:19', '2026-03-14 17:30:11', '2026-03-14 09:45:19', 'manual_logout', '2026-03-14 01:30:11', '2026-03-14 01:45:19'),
(5, '648789360f60d1c0f253aaed12923f5bf032539d65ecc593887d3179e889d121', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-14 09:45:29', '2026-03-14 10:14:53', '2026-03-14 17:45:29', '2026-03-14 10:42:02', 'idle_timeout', '2026-03-14 01:45:29', '2026-03-14 02:42:02'),
(6, '675cd2fec6efaff6253e58061705a8d50adf17b3d075d35eacd77a4cd754cbdc', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-14 10:42:29', '2026-03-14 10:42:35', '2026-03-14 18:42:29', '2026-03-14 10:42:35', 'manual_logout', '2026-03-14 02:42:29', '2026-03-14 02:42:35'),
(7, '0c306bb0a1e83502cebb72b5fec8f7310f7e8098ddcdf4b4b751b4848d83004f', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-21 10:54:33', '2026-03-21 11:26:33', '2026-03-21 18:54:33', '2026-03-21 11:26:33', 'manual_logout', '2026-03-21 02:54:33', '2026-03-21 03:26:33'),
(8, 'ca8d47cfb6623770945ca3658dfa708b073b5c125f5363b34dff12fba21d9c48', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-21 11:28:52', '2026-03-21 11:33:24', '2026-03-21 19:28:52', NULL, NULL, '2026-03-21 03:28:52', '2026-03-21 03:33:24'),
(9, '52eb9e189f2d1be52704cd7b0328a2c793d6b8957ca638a3a6ff4fe434aad195', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:15:23', '2026-03-28 15:16:05', '2026-03-28 23:15:23', '2026-03-28 15:16:05', 'manual_logout', '2026-03-28 07:15:23', '2026-03-28 07:16:05'),
(10, 'd035e5ae95a7d691cb453ceac436d7ed22d2abb9857c2bdad4ebb4c435180ccd', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:16:13', '2026-03-28 15:17:23', '2026-03-28 23:16:13', '2026-03-28 15:17:23', 'manual_logout', '2026-03-28 07:16:13', '2026-03-28 07:17:23'),
(11, 'de04c0df38e9a6460a1ebe657123ea032d5f3086504b993cdf1c08c341c2ead2', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:17:27', '2026-03-28 15:17:50', '2026-03-28 23:17:27', '2026-03-28 15:17:50', 'manual_logout', '2026-03-28 07:17:27', '2026-03-28 07:17:50'),
(12, '6492a323fdc07aeb34552814327a6b6a2921a0e78219646ba153690feca5cabb', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:18:03', '2026-03-28 15:27:50', '2026-03-28 23:18:03', '2026-03-28 15:27:50', 'manual_logout', '2026-03-28 07:18:03', '2026-03-28 07:27:50'),
(13, 'adc994fab9716bf716f688c5698e8787e749cd5ae0df24f63bcd04ccfdb32186', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:28:50', '2026-03-28 15:29:52', '2026-03-28 23:28:50', '2026-03-28 15:29:52', 'manual_logout', '2026-03-28 07:28:50', '2026-03-28 07:29:52'),
(14, '16f58e6d95eb1059b03aa1cec67dd413b26a69e92967c508f17019875ce3e703', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:29:59', '2026-03-28 15:30:40', '2026-03-28 23:29:59', '2026-03-28 15:30:40', 'manual_logout', '2026-03-28 07:29:59', '2026-03-28 07:30:40'),
(15, '589aa652240fc72cb384e1a9d36e641fd9b1dd649ad79dee63bb34939f2fe51b', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:30:45', '2026-03-28 15:32:19', '2026-03-28 23:30:45', '2026-03-28 15:32:19', 'manual_logout', '2026-03-28 07:30:45', '2026-03-28 07:32:19'),
(16, 'a72bdbd380ff8ca8b845eead50af933e18e8f2851fc8b8806d86ea796c89c1bb', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:32:23', '2026-03-28 15:32:54', '2026-03-28 23:32:23', '2026-03-28 15:32:54', 'manual_logout', '2026-03-28 07:32:23', '2026-03-28 07:32:54'),
(17, 'b9675e990f203cbcb7144436dc6ebf1a5249f92ae6994745ff6b841dcf34f95c', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:33:01', '2026-03-28 15:33:13', '2026-03-28 23:33:01', '2026-03-28 15:33:13', 'manual_logout', '2026-03-28 07:33:01', '2026-03-28 07:33:13'),
(18, 'f1b489e6d994438def6bda0faeac5eb1628c1939b17ca6eaa9b007ad903aa0b1', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:33:30', '2026-03-28 15:33:51', '2026-03-28 23:33:30', '2026-03-28 15:33:51', 'manual_logout', '2026-03-28 07:33:30', '2026-03-28 07:33:51'),
(19, '909695504195e54145029588b9ad0ceeaf3eff396382aa0f31b974aa0b385c36', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:34:08', '2026-03-28 15:34:54', '2026-03-28 23:34:08', '2026-03-28 15:34:54', 'manual_logout', '2026-03-28 07:34:08', '2026-03-28 07:34:54'),
(20, 'b891901529a9b7e7abea80ee830d6e2db502bbf26a0f6254a895ebdc010f49b0', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:34:57', '2026-03-28 15:35:37', '2026-03-28 23:34:57', '2026-03-28 15:35:37', 'manual_logout', '2026-03-28 07:34:57', '2026-03-28 07:35:37'),
(21, '15b898cdb5586476ff5896e58e623a56a88b3149fad04864f81288e4072cdab7', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:35:41', '2026-03-28 15:37:22', '2026-03-28 23:35:41', '2026-03-28 15:37:22', 'manual_logout', '2026-03-28 07:35:41', '2026-03-28 07:37:22'),
(22, '34f7ce37df8a774e3e48747e24517ec5fedc7718bf17b5e4ee7087e881e128d3', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:37:26', '2026-03-28 15:37:52', '2026-03-28 23:37:26', '2026-03-28 15:37:52', 'manual_logout', '2026-03-28 07:37:26', '2026-03-28 07:37:52'),
(23, 'ab30f64c88058e9ee8d175d5ac058c2cc49c3d7f3bdece6dff9abe890608ccb8', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:37:56', '2026-03-28 15:39:04', '2026-03-28 23:37:56', '2026-03-28 15:39:04', 'manual_logout', '2026-03-28 07:37:56', '2026-03-28 07:39:04'),
(24, '71392808c01e2d0d2027477a41cd3d0ac6e33fca059a6bdeb75d8b7b2dd499e6', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:39:08', '2026-03-28 15:39:49', '2026-03-28 23:39:08', '2026-03-28 15:39:49', 'manual_logout', '2026-03-28 07:39:08', '2026-03-28 07:39:49'),
(25, 'eda02c920f841c5f444905e8a112cf15158b007935387231462940048db2a748', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:39:58', '2026-03-28 15:40:43', '2026-03-28 23:39:58', '2026-03-28 15:40:43', 'manual_logout', '2026-03-28 07:39:58', '2026-03-28 07:40:43'),
(26, '98453efc37d68c9efbf205d806ab40bb6ea32df9ee0d403f8d16117f10fb8d59', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:40:47', '2026-03-28 15:41:16', '2026-03-28 23:40:47', NULL, NULL, '2026-03-28 07:40:47', '2026-03-28 07:41:16'),
(27, '53628af4088c153a0d50866101783dbf010b5ab3c7bab935cd28e475856180ef', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:41:27', '2026-03-28 15:42:20', '2026-03-28 23:41:27', '2026-03-28 15:42:20', 'manual_logout', '2026-03-28 07:41:27', '2026-03-28 07:42:20'),
(28, '2dcc92994e678a1ca7b02162eecbe0b1004a15e7c483f88c3f6be2fcfa091fec', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:42:28', '2026-03-28 15:42:47', '2026-03-28 23:42:28', '2026-03-28 15:42:47', 'manual_logout', '2026-03-28 07:42:28', '2026-03-28 07:42:47'),
(29, 'ed0b53de387203e91d30107d5d95662cc4c670063be601ad9fa1c7af77954b8a', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:42:54', '2026-03-28 15:45:41', '2026-03-28 23:42:54', '2026-03-28 15:45:41', 'manual_logout', '2026-03-28 07:42:54', '2026-03-28 07:45:41'),
(30, '805062c0b98c84fdad7f545ac3b17a387a11b183401e056701176b02af1d0815', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:45:53', '2026-03-28 15:46:18', '2026-03-28 23:45:53', '2026-03-28 15:46:18', 'manual_logout', '2026-03-28 07:45:53', '2026-03-28 07:46:18'),
(31, '75241b08f312318b94c24a6873f4639a9f0a1f1aee64b75a5e47df5c27424607', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:48:12', '2026-03-28 15:48:21', '2026-03-28 23:48:12', '2026-03-28 15:48:21', 'manual_logout', '2026-03-28 07:48:12', '2026-03-28 07:48:21'),
(32, '3d4135aafcda7d0d8a7771740b11cca7270a3ac7e1155f5776cdb3f3077d3f37', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:48:37', '2026-03-28 15:49:17', '2026-03-28 23:48:37', '2026-03-28 15:49:17', 'manual_logout', '2026-03-28 07:48:37', '2026-03-28 07:49:17'),
(33, '1ef929bf2668767f57bdaebcb37f9607abb978fab83d4e83f22df85e1b789890', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:49:20', '2026-03-28 15:49:30', '2026-03-28 23:49:20', NULL, NULL, '2026-03-28 07:49:20', '2026-03-28 07:49:30'),
(34, '30b33dcacf497dc746417b4c6116e2a8f843a7545589a50eb03bb989d7e7cb04', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:49:41', '2026-03-28 15:50:15', '2026-03-28 23:49:41', '2026-03-28 15:50:15', 'manual_logout', '2026-03-28 07:49:41', '2026-03-28 07:50:15'),
(35, 'c886a0aa51915534f7cefab97f356f2f1fd0bf98508edc295e18245949dd4044', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:50:21', '2026-03-28 15:50:34', '2026-03-28 23:50:21', '2026-03-28 15:50:34', 'manual_logout', '2026-03-28 07:50:21', '2026-03-28 07:50:34'),
(36, 'd37fae8280a0b6a2fa351a56f5e0ff397c02b4067b7413ab2686ba9593580b69', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:50:37', '2026-03-28 15:50:46', '2026-03-28 23:50:37', '2026-03-28 15:50:46', 'manual_logout', '2026-03-28 07:50:37', '2026-03-28 07:50:46'),
(37, '5aa0b841505df23efdf9af4db63c5deff49f251a44b04954ee971ecd12bb5278', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-03-28 15:50:55', '2026-03-28 16:00:13', '2026-03-28 23:50:55', NULL, NULL, '2026-03-28 07:50:55', '2026-03-28 08:00:13'),
(38, '320180bae27d7b251ddd6d4e122df80b0100a56added9ca9ccce28b41a2eac92', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-25 05:21:20', '2026-04-25 05:21:31', '2026-04-25 13:21:20', '2026-04-25 05:21:31', 'manual_logout', '2026-04-24 21:21:20', '2026-04-24 21:21:31'),
(39, 'c6d112d046d211826aa3854ddb388a7b55fbf0c782eb63594c4c628844219f56', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-25 05:23:57', '2026-04-25 05:24:02', '2026-04-25 13:23:57', '2026-04-25 05:24:02', 'manual_logout', '2026-04-24 21:23:57', '2026-04-24 21:24:02'),
(40, '541e19cc81921161169c69e1c190a222256b59b8a7012793a85943b82e62e551', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-25 05:24:04', '2026-04-25 05:29:31', '2026-04-25 13:24:04', '2026-04-25 05:29:31', 'manual_logout', '2026-04-24 21:24:04', '2026-04-24 21:29:31'),
(41, 'e16796778a958251d19a4ed28c0b8c9307b4cd49f8c18bc62f63cf9c59de5e67', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-25 05:29:46', '2026-04-25 05:40:39', '2026-04-25 13:29:46', '2026-04-25 05:40:39', 'manual_logout', '2026-04-24 21:29:46', '2026-04-24 21:40:39'),
(42, '2fdca80b7c869adc5e48d8785b5a54927abe001b616cf4f08fc3357e3ca729ca', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-25 05:40:53', '2026-04-25 06:01:45', '2026-04-25 13:40:53', '2026-04-25 06:01:45', 'manual_logout', '2026-04-24 21:40:53', '2026-04-24 22:01:45'),
(43, 'fb905266e1a892973aad73f2e0865c23601ad81284d12e73a48374803356fd45', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-25 06:01:50', '2026-04-25 06:01:54', '2026-04-25 14:01:50', '2026-04-25 06:01:54', 'manual_logout', '2026-04-24 22:01:50', '2026-04-24 22:01:54'),
(44, '7077f80064faa31d50356a19ef5d21fd109eaf25a69476e84560bc9078ab5b3e', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-26 17:33:17', '2026-04-26 17:33:49', '2026-04-27 01:33:17', '2026-04-26 17:33:49', 'manual_logout', '2026-04-26 09:33:17', '2026-04-26 09:33:49'),
(45, '9e6d74a5ca230f08d12e39012309dcba10bfe950fcdf8099aa044403cc655116', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-26 17:34:05', '2026-04-26 17:34:32', '2026-04-27 01:34:05', '2026-04-26 17:34:32', 'manual_logout', '2026-04-26 09:34:05', '2026-04-26 09:34:32'),
(46, '64fd55088e3db8be25b9019622cac0e42687a32105b9466c4faf71920266bb1a', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-26 17:34:52', '2026-04-26 17:36:23', '2026-04-27 01:34:52', NULL, NULL, '2026-04-26 09:34:52', '2026-04-26 09:36:23'),
(47, '0001d36592824d1e1464f10e924769a0d99af00e16bd4a42aed1855115de7cb6', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 14:14:28', '2026-04-27 14:16:47', '2026-04-27 22:14:28', '2026-04-27 14:16:47', 'manual_logout', '2026-04-27 06:14:28', '2026-04-27 06:16:47'),
(48, '21f7cd6d979dfdc827b407cd86ad1066a4915a08cae0664269e60fa2af644ca9', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 14:16:59', '2026-04-27 14:18:56', '2026-04-27 22:16:59', '2026-04-27 14:18:56', 'manual_logout', '2026-04-27 06:16:59', '2026-04-27 06:18:56'),
(49, 'd714134098414b964acfa22d24a0567a4f02a000b351739d4f1784255223018b', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 14:19:04', '2026-04-27 14:19:48', '2026-04-27 22:19:04', '2026-04-27 14:19:48', 'manual_logout', '2026-04-27 06:19:04', '2026-04-27 06:19:48'),
(50, 'a089712f6984db04bea1e26f4558d64a2db3884e5564ee0f24d4fc81a4dca2b2', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 14:19:53', '2026-04-27 14:27:24', '2026-04-27 22:19:53', '2026-04-27 14:58:56', 'idle_timeout', '2026-04-27 06:19:53', '2026-04-27 06:58:56'),
(51, '5c329565cb06cb4301739eb3818edaacae4016293e313e1d8587fb63af48335c', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:01:11', '2026-04-27 15:03:04', '2026-04-27 23:01:11', '2026-04-27 15:03:04', 'manual_logout', '2026-04-27 07:01:11', '2026-04-27 07:03:04'),
(52, '018cd33be65e6923929c3d3805ea3a78a69fc832cb9493a5ca515e4b8c62e85d', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:03:12', '2026-04-27 15:05:10', '2026-04-27 23:03:12', '2026-04-27 15:05:10', 'manual_logout', '2026-04-27 07:03:12', '2026-04-27 07:05:10'),
(53, '912a3daced5a95a48735e4939d85afc829283a5b659b59b3ad5124f5f1e4998c', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:05:26', '2026-04-27 15:06:12', '2026-04-27 23:05:26', '2026-04-27 15:06:12', 'manual_logout', '2026-04-27 07:05:26', '2026-04-27 07:06:12'),
(54, '3597f613a4f7c83267c6bb3a1d8b7d965e0921c362940ab4a43d9ad5db8b6bf5', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:06:24', '2026-04-27 15:08:38', '2026-04-27 23:06:24', '2026-04-27 15:08:38', 'manual_logout', '2026-04-27 07:06:24', '2026-04-27 07:08:38'),
(55, '853f0ff127f57c9331fd690b8fc6b16c1cc9f97944bd93bde308238610c4c194', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:09:04', '2026-04-27 15:12:56', '2026-04-27 23:09:04', '2026-04-27 15:12:56', 'manual_logout', '2026-04-27 07:09:04', '2026-04-27 07:12:56'),
(56, '1b3630ae759b5e6acb1517a700ac76b52c1d21254298ae219414686ef7a2b534', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:13:09', '2026-04-27 15:13:36', '2026-04-27 23:13:09', '2026-04-27 15:13:36', 'manual_logout', '2026-04-27 07:13:09', '2026-04-27 07:13:36'),
(57, 'a294ca5b2c11c30b276009d029323de423886856263a9ea53025aa986575c53f', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:13:42', '2026-04-27 15:16:17', '2026-04-27 23:13:42', '2026-04-27 15:16:17', 'manual_logout', '2026-04-27 07:13:42', '2026-04-27 07:16:17'),
(58, '4569641126c328d6dc73a5962b90d4098c91f99ab8d60ce27d909c9273cd5413', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:16:23', '2026-04-27 15:18:52', '2026-04-27 23:16:23', '2026-04-27 15:18:52', 'manual_logout', '2026-04-27 07:16:23', '2026-04-27 07:18:52'),
(59, 'cb467bb66056cb0caab5dac93420f61ed3f76adeb1a05a2b6eaed3e9e27b93d7', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:18:58', '2026-04-27 15:20:02', '2026-04-27 23:18:58', '2026-04-27 15:20:02', 'manual_logout', '2026-04-27 07:18:58', '2026-04-27 07:20:02'),
(60, '7ac337d926caf3ce36f436be7d563a6b17a43b690a8d37e78671d8c077b054fb', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:20:07', '2026-04-27 15:21:26', '2026-04-27 23:20:07', '2026-04-27 15:21:26', 'manual_logout', '2026-04-27 07:20:07', '2026-04-27 07:21:26'),
(61, '933439b006bd68d11ca7fe00945e18c270d9380976968590f54e08d78cc3aa60', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:21:55', '2026-04-27 15:23:01', '2026-04-27 23:21:55', '2026-04-27 15:23:01', 'manual_logout', '2026-04-27 07:21:55', '2026-04-27 07:23:01'),
(62, '70714cdee57b0aee8c0f8855c73b84b06cc9c46830772fb1f6da7175aa04360b', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:23:17', '2026-04-27 15:23:45', '2026-04-27 23:23:17', '2026-04-27 15:23:45', 'manual_logout', '2026-04-27 07:23:17', '2026-04-27 07:23:45'),
(63, 'bbec9a9f94dec0f6106f7331072bea7f1286f5600cd6a6bbaa3e11a531a702bc', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-04-27 15:23:50', '2026-04-27 15:27:18', '2026-04-27 23:23:50', NULL, NULL, '2026-04-27 07:23:50', '2026-04-27 07:27:18'),
(64, '95c0aeae186fd721be5dc61781d97da3764baeb0aff2b068cbe47ac2b1a8c982', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-01 16:21:07', '2026-05-01 16:22:04', '2026-05-02 00:21:07', '2026-05-01 16:22:04', 'manual_logout', '2026-05-01 08:21:07', '2026-05-01 08:22:04'),
(65, 'af234b152df2e8d7b5d80567b98868ceb61ba078888db9892881b737791c971e', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-01 16:22:10', '2026-05-01 16:55:40', '2026-05-02 00:22:10', '2026-05-01 16:55:40', 'manual_logout', '2026-05-01 08:22:10', '2026-05-01 08:55:40'),
(66, '1685bd47a807fffc5180585d7453c625ea55a8e59493c48a16debd09dbbe4f00', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-01 16:55:45', '2026-05-01 17:51:07', '2026-05-02 00:55:45', '2026-05-01 17:51:07', 'idle_timeout', '2026-05-01 08:55:45', '2026-05-01 09:51:07'),
(67, 'f735d04d46e8bb9e60e899bb14410878d83029ea91b48d6e4c0e02049cfe4937', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-01 22:03:38', '2026-05-01 22:21:30', '2026-05-02 06:03:38', '2026-05-01 22:21:30', 'manual_logout', '2026-05-01 14:03:38', '2026-05-01 14:21:30'),
(68, '65bdd540bfac9632d96584cf9512a0469d48c7d379a611464dc3277a4d6eeceb', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-01 22:21:41', '2026-05-01 22:22:11', '2026-05-02 06:21:41', '2026-05-01 22:22:11', 'manual_logout', '2026-05-01 14:21:41', '2026-05-01 14:22:11'),
(69, '3b096377b86817782dded9e05658350b9dc50ecd495bc38c948bb1d4b37c93fb', 13, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-02 21:00:13', '2026-05-02 21:00:31', '2026-05-03 05:00:13', '2026-05-02 21:00:31', 'manual_logout', '2026-05-02 13:00:13', '2026-05-02 13:00:31'),
(70, 'a0596e866c31a86d0efa67e6549314c91dfaabc10546e99bb74fe648e530d365', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-02 21:00:35', '2026-05-02 21:08:21', '2026-05-03 05:00:35', '2026-05-02 21:08:21', 'manual_logout', '2026-05-02 13:00:35', '2026-05-02 13:08:21'),
(71, '0a8978fd2505fd88c91147032e6e77df12931b90bfbe169bcae4d498a9ba0d0c', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-02 21:08:24', '2026-05-02 21:12:55', '2026-05-03 05:08:24', '2026-05-02 21:12:55', 'manual_logout', '2026-05-02 13:08:24', '2026-05-02 13:12:55'),
(72, '404365c198f31e5a150692fa203b2814e6cc6baf0934de335997aaec7ff1051b', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-02 21:13:01', '2026-05-02 22:10:44', '2026-05-03 05:13:01', '2026-05-02 22:10:44', 'idle_timeout', '2026-05-02 13:13:01', '2026-05-02 14:10:44'),
(73, '2579071264af5b05c0194ed920e144cec271e98e4768fb0ed00f2a9ee2331f58', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 02:01:07', '2026-05-03 02:03:16', '2026-05-03 10:01:07', '2026-05-03 02:03:16', 'manual_logout', '2026-05-02 18:01:07', '2026-05-02 18:03:16'),
(74, 'a58edc992132f74770f4a46aecebd6c0fe3f6688e511040dc995b7c22fde555b', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 02:03:19', '2026-05-03 02:03:27', '2026-05-03 10:03:19', '2026-05-03 02:03:27', 'manual_logout', '2026-05-02 18:03:19', '2026-05-02 18:03:27'),
(75, 'a66e67297feb18b08f87c291040434ee7c734011df198d7c5f50a9ceb512e856', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 02:03:33', '2026-05-03 02:27:00', '2026-05-03 10:03:33', '2026-05-03 02:49:20', 'idle_timeout', '2026-05-02 18:03:33', '2026-05-02 18:49:20'),
(76, '0f769830834639d6f199d30fc9b013bd54cfe57279af03131033ac059d8beb0a', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 13:58:10', '2026-05-03 13:58:27', '2026-05-03 21:58:10', '2026-05-03 13:58:27', 'manual_logout', '2026-05-03 05:58:10', '2026-05-03 05:58:27'),
(77, '80288efebd8b6cf4b473d54c813374f7a87299bab11199adb6ef9c8a74e659c5', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 13:58:35', '2026-05-03 14:01:26', '2026-05-03 21:58:35', '2026-05-03 14:01:26', 'manual_logout', '2026-05-03 05:58:35', '2026-05-03 06:01:26'),
(78, '6626d2493b06fc1092aa21bfee59e07eb9a257dff94e9dcbf59e1b8583dd3dda', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:01:30', '2026-05-03 14:02:13', '2026-05-03 22:01:30', '2026-05-03 14:02:13', 'manual_logout', '2026-05-03 06:01:30', '2026-05-03 06:02:13'),
(79, '7475766f0cf3654bba9b62ac245aec86f919378a3e3acab6847f20b5dc2025dd', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:02:17', '2026-05-03 14:02:27', '2026-05-03 22:02:17', '2026-05-03 14:02:27', 'manual_logout', '2026-05-03 06:02:17', '2026-05-03 06:02:27'),
(80, 'ea2e0896ce6774fba1b0d3729eeaf4e75b9de27dd7d16fd3f244523ece2bd09d', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:02:51', '2026-05-03 14:04:24', '2026-05-03 22:02:51', '2026-05-03 14:04:24', 'manual_logout', '2026-05-03 06:02:51', '2026-05-03 06:04:24'),
(81, 'dc70372604843854caf7e18fb4b91518f04707af1554c6a8499eb13d3928799f', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:04:28', '2026-05-03 14:06:25', '2026-05-03 22:04:28', '2026-05-03 14:06:25', 'manual_logout', '2026-05-03 06:04:28', '2026-05-03 06:06:25'),
(82, '35d22e0bcb64c9c39d436700dd561eba490623f9e526612135c442e2cc21e156', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:06:29', '2026-05-03 14:15:04', '2026-05-03 22:06:29', '2026-05-03 14:15:04', 'manual_logout', '2026-05-03 06:06:29', '2026-05-03 06:15:04'),
(83, '751ca1cb3fea96ea27d3556b64f5f9da21b08dc6e7357820b4ff9f231ceb4534', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:15:09', '2026-05-03 14:15:24', '2026-05-03 22:15:09', '2026-05-03 14:15:24', 'manual_logout', '2026-05-03 06:15:09', '2026-05-03 06:15:24'),
(84, 'ef982bd9c2c34cbf15f3c7a222570fd6f8068f457f5013b910483eadfb024b03', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:15:29', '2026-05-03 14:20:19', '2026-05-03 22:15:29', '2026-05-03 14:20:19', 'manual_logout', '2026-05-03 06:15:29', '2026-05-03 06:20:19'),
(85, 'd91af59ea3dc495364dfe7129b3ae906c8a1f30de9e778ef8a547a0cc9e835f1', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:20:22', '2026-05-03 14:23:05', '2026-05-03 22:20:22', '2026-05-03 14:23:05', 'manual_logout', '2026-05-03 06:20:22', '2026-05-03 06:23:05'),
(86, '259ca92f9510363a80a76202c6c81b5d35ff05b5f92f1e259d1913d8249740f4', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:23:09', '2026-05-03 14:24:47', '2026-05-03 22:23:09', '2026-05-03 14:24:47', 'manual_logout', '2026-05-03 06:23:09', '2026-05-03 06:24:47'),
(87, 'a55e324c03c21910f9aeec9ffb70bd0256d538f186c8fc3ca4698e4d524a4b88', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:24:56', '2026-05-03 14:27:39', '2026-05-03 22:24:56', '2026-05-03 14:27:39', 'manual_logout', '2026-05-03 06:24:56', '2026-05-03 06:27:39'),
(88, '1cb69db23d793bee6b9bee60fbf5a36ed212a333bdcb1ad4f67381541d5eb5ef', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 14:27:43', '2026-05-03 14:40:49', '2026-05-03 22:27:43', '2026-05-03 15:28:55', 'idle_timeout', '2026-05-03 06:27:43', '2026-05-03 07:28:55'),
(89, 'a960ef06dce94b03e007f109ea86c08d895bbc2441579846c7a297bde78a8562', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 15:29:00', '2026-05-03 15:31:34', '2026-05-03 23:29:00', '2026-05-03 15:31:34', 'manual_logout', '2026-05-03 07:29:00', '2026-05-03 07:31:34'),
(90, 'ecb172dd23c04b9c18a4e45b7a7d12fb8d8fc3fb8bcf171b2ea764c41372b827', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 15:32:11', '2026-05-03 15:40:19', '2026-05-03 23:32:11', '2026-05-03 15:40:19', 'manual_logout', '2026-05-03 07:32:11', '2026-05-03 07:40:19'),
(91, 'b5e132b60c6cb41fcbe3966f66c2f7e93a7aa2da8989eaad5886d4958c49c7da', 11, '::1', 'curl/8.18.0', '2026-05-03 15:38:04', '2026-05-03 15:38:04', '2026-05-03 23:38:04', NULL, NULL, '2026-05-03 07:38:04', '2026-05-03 07:38:04'),
(92, 'ffe2005c3369638b8bfbcb512246cbc5203a81b6f1e11ecd6cdb66c103acf26d', 11, '::1', 'curl/8.18.0', '2026-05-03 15:38:29', '2026-05-03 15:38:30', '2026-05-03 23:38:29', NULL, NULL, '2026-05-03 07:38:29', '2026-05-03 07:38:30'),
(93, '8bdfde43e5a18283848e98e370a087d77b0bd36e7055752a6766e4de4fe3ff3d', 11, '::1', 'curl/8.18.0', '2026-05-03 15:39:09', '2026-05-03 15:39:09', '2026-05-03 23:39:09', NULL, NULL, '2026-05-03 07:39:09', '2026-05-03 07:39:09'),
(94, '23a9b282ea0090981f048d1f1531a3731cdf1ccc709ef5eb49e92bb7eaeb6b80', 11, '::1', 'curl/8.18.0', '2026-05-03 15:39:13', '2026-05-03 15:39:14', '2026-05-03 23:39:13', NULL, NULL, '2026-05-03 07:39:13', '2026-05-03 07:39:14'),
(95, '6a927917541f565f462c1d9428e3ee726c54549c7ff73efc492519f70f4dbf07', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 15:47:47', '2026-05-03 15:50:43', '2026-05-03 23:47:47', '2026-05-03 15:50:43', 'manual_logout', '2026-05-03 07:47:47', '2026-05-03 07:50:43'),
(96, '4df45388047a025b6561a59a27f5d856152336595e508c0e333f7f9a11537dd3', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 15:51:17', '2026-05-03 15:51:35', '2026-05-03 23:51:17', '2026-05-03 15:51:35', 'manual_logout', '2026-05-03 07:51:17', '2026-05-03 07:51:35'),
(97, '7524f036a50261f089d42c3138112cf8d62684cfd524777cd6161a9f7cf774a1', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 15:51:50', '2026-05-03 15:59:18', '2026-05-03 23:51:50', '2026-05-03 15:59:18', 'manual_logout', '2026-05-03 07:51:50', '2026-05-03 07:59:18'),
(98, '9c9399f571da22bdbf6a8c7595d1a7aabb5ade2311d3df7f57b07261981fe629', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 15:59:24', '2026-05-03 16:17:14', '2026-05-03 23:59:24', '2026-05-03 16:17:14', 'manual_logout', '2026-05-03 07:59:24', '2026-05-03 08:17:14'),
(99, '3f8965774358d2bd397d4d838f5123141bd0d77ab8e27dbbd86765a8ffb374b5', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:17:19', '2026-05-03 16:17:58', '2026-05-04 00:17:19', '2026-05-03 16:17:58', 'manual_logout', '2026-05-03 08:17:19', '2026-05-03 08:17:58'),
(100, 'cae2e3a9746d3b743aa27f6aeda3d37674c8ddf5fe6d0bc9e510f3d1a77405da', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:18:12', '2026-05-03 16:22:01', '2026-05-04 00:18:12', '2026-05-03 16:22:01', 'manual_logout', '2026-05-03 08:18:12', '2026-05-03 08:22:01'),
(101, 'd2e526483b5ae5b3ffe2c977eaa191a39d66bedbf650f095ee4bc3827249adb5', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:22:05', '2026-05-03 16:28:14', '2026-05-04 00:22:05', '2026-05-03 16:28:14', 'manual_logout', '2026-05-03 08:22:05', '2026-05-03 08:28:14'),
(102, 'fe843650bb876b47aa5a8b0afbf6864366bf23aae3a2426667ef3474b3b76ce6', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:28:26', '2026-05-03 16:29:09', '2026-05-04 00:28:26', '2026-05-03 16:29:09', 'manual_logout', '2026-05-03 08:28:26', '2026-05-03 08:29:09'),
(103, 'f28cf3036abc81fd8c247785beca305057494244b86965c99e73fea01920cb2d', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:29:14', '2026-05-03 16:29:21', '2026-05-04 00:29:14', '2026-05-03 16:29:21', 'manual_logout', '2026-05-03 08:29:14', '2026-05-03 08:29:21'),
(104, 'ca050b03accb7c213ee06225681ab44ced351b21a2f997896dbe52e54a60afb6', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:29:25', '2026-05-03 16:29:50', '2026-05-04 00:29:25', '2026-05-03 16:29:50', 'manual_logout', '2026-05-03 08:29:25', '2026-05-03 08:29:50'),
(105, '5eaf077a46e9fcd32c509b6791c549ecff4077c69027981cd0afe94c2cab1ee0', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:29:58', '2026-05-03 16:30:04', '2026-05-04 00:29:58', '2026-05-03 16:30:04', 'manual_logout', '2026-05-03 08:29:58', '2026-05-03 08:30:04'),
(106, '30221b4a3aad88b6aa0b946d487ae835e3d79470de19f599ccf4ed0cc059f0a7', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:30:06', '2026-05-03 16:35:12', '2026-05-04 00:30:06', '2026-05-03 16:35:12', 'manual_logout', '2026-05-03 08:30:06', '2026-05-03 08:35:12'),
(107, '5e309914d8c86f620b1050078033df07e75c55fe94065c7d30294a19a91e5d23', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:35:22', '2026-05-03 16:35:52', '2026-05-04 00:35:22', '2026-05-03 16:35:52', 'manual_logout', '2026-05-03 08:35:22', '2026-05-03 08:35:52'),
(108, '60340fb4760065bc5f019a1426232872e28fdd711fc712ad13b7d1ff9a0ce645', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:35:59', '2026-05-03 16:36:56', '2026-05-04 00:35:59', '2026-05-03 16:36:56', 'manual_logout', '2026-05-03 08:35:59', '2026-05-03 08:36:56'),
(109, '43bbb8cd10dd25d2a8114fb53c1ed39128bb255abef5b384b7b98cde3d7d8591', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:37:02', '2026-05-03 16:37:29', '2026-05-04 00:37:02', '2026-05-03 16:37:29', 'manual_logout', '2026-05-03 08:37:02', '2026-05-03 08:37:29'),
(110, '24e3754ff64b517dccdecbd684e022601974aa5f4f1a6b5228d3effa6794418f', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:37:33', '2026-05-03 16:39:15', '2026-05-04 00:37:33', '2026-05-03 16:39:15', 'manual_logout', '2026-05-03 08:37:33', '2026-05-03 08:39:15'),
(111, '3e899cd748b02120980f6577184a98708efc8f32c7b9765cf4a80032b9e284e4', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 16:39:18', '2026-05-03 16:55:55', '2026-05-04 00:39:18', '2026-05-03 16:55:55', 'idle_timeout', '2026-05-03 08:39:18', '2026-05-03 08:55:55'),
(112, 'be944f3ecc7eb1f5b3030fe5bbbd2e988c2dc9a8fb391496981758f79693571a', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:00:28', '2026-05-03 20:01:06', '2026-05-04 04:00:28', '2026-05-03 20:01:06', 'manual_logout', '2026-05-03 12:00:28', '2026-05-03 12:01:06'),
(113, 'ce6b4b3898603d23d7c14da9135a1bb5011b6ac28c42c675ab696d62c7a000b0', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:01:15', '2026-05-03 20:06:40', '2026-05-04 04:01:15', '2026-05-03 20:06:40', 'manual_logout', '2026-05-03 12:01:15', '2026-05-03 12:06:40'),
(114, 'd45fc252e15b1361316ed1e8fbc09723adfb2e93a38973d11d94c555b71461f8', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:07:12', '2026-05-03 20:08:39', '2026-05-04 04:07:12', '2026-05-03 20:08:39', 'manual_logout', '2026-05-03 12:07:12', '2026-05-03 12:08:39'),
(115, 'd3758ba312e17048221d7aff63d62e0195ca296bd3ab4477a4cf3a7bb16141ed', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:09:57', '2026-05-03 20:11:03', '2026-05-04 04:09:57', '2026-05-03 20:11:03', 'manual_logout', '2026-05-03 12:09:57', '2026-05-03 12:11:03'),
(116, '5f7486a99d19cb15e581c110324f193a7bbfec2a8d5cc7c49037868a64ecce7a', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:11:10', '2026-05-03 20:11:58', '2026-05-04 04:11:10', '2026-05-03 20:11:58', 'manual_logout', '2026-05-03 12:11:10', '2026-05-03 12:11:58'),
(117, '7f94362c1f97486516c4da7594767743df0aa3b4c3a34b62578f6f9f3d1c61e8', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:12:04', '2026-05-03 20:12:14', '2026-05-04 04:12:04', '2026-05-03 20:12:14', 'manual_logout', '2026-05-03 12:12:04', '2026-05-03 12:12:14'),
(118, '733b7a2abe1fd658b60105ef03e772b32960c8c840a67066fb3706ada8a24d3b', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:12:18', '2026-05-03 20:12:43', '2026-05-04 04:12:18', '2026-05-03 20:12:43', 'manual_logout', '2026-05-03 12:12:18', '2026-05-03 12:12:43'),
(119, 'a68ec53f3983837b731425c1d48da83e6621c20f7e9b5a2bafec0bfffb658459', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:12:50', '2026-05-03 20:13:03', '2026-05-04 04:12:50', '2026-05-03 20:13:03', 'manual_logout', '2026-05-03 12:12:50', '2026-05-03 12:13:03'),
(120, 'c00a0fdd118eeb66313c7260dc85ed5fffcdc496ed4a9134bfe1d5a2f6241dc9', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:13:06', '2026-05-03 20:14:22', '2026-05-04 04:13:06', '2026-05-03 20:14:22', 'manual_logout', '2026-05-03 12:13:06', '2026-05-03 12:14:22'),
(121, '424a2cbed4fc4d475fc697bc990a3f756a421fa7c74c858834462b139862ead8', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:14:26', '2026-05-03 20:15:34', '2026-05-04 04:14:26', '2026-05-03 20:15:34', 'manual_logout', '2026-05-03 12:14:26', '2026-05-03 12:15:34'),
(122, '0bc239a7af7f2c1233a1180d0b744cfdd6b5c3a0881473b3b3d22209afb9b9b1', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:15:40', '2026-05-03 20:16:16', '2026-05-04 04:15:40', '2026-05-03 20:16:16', 'manual_logout', '2026-05-03 12:15:40', '2026-05-03 12:16:16'),
(123, 'eea62db0faf1d0490355eed79899b708b7441147a40391d654ece5ed78f16c79', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:16:20', '2026-05-03 20:22:41', '2026-05-04 04:16:20', '2026-05-03 20:22:41', 'manual_logout', '2026-05-03 12:16:20', '2026-05-03 12:22:41'),
(124, '90786cff5e115006ee6aad9b604b2c99b8f62bdc370813ea15cb0b2dc27fd4cb', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:22:46', '2026-05-03 20:22:55', '2026-05-04 04:22:46', '2026-05-03 20:22:55', 'manual_logout', '2026-05-03 12:22:46', '2026-05-03 12:22:55'),
(125, '25ebffb3de2cec81717b6931e8c29e2460699f284193d1355233fa2b4683386c', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:22:58', '2026-05-03 20:23:07', '2026-05-04 04:22:58', '2026-05-03 20:23:07', 'manual_logout', '2026-05-03 12:22:58', '2026-05-03 12:23:07'),
(126, '6d010940ebfa421c1d5d10ddec3bc0ce18a425a8d4e5d9f5f386f8eb1d739bf8', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:23:10', '2026-05-03 20:23:30', '2026-05-04 04:23:10', '2026-05-03 20:23:30', 'manual_logout', '2026-05-03 12:23:10', '2026-05-03 12:23:30'),
(127, '092daaba57f276a90953c77a994e6433c9a2d425de7ed07c422c497fd9df4ecb', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:23:33', '2026-05-03 20:23:44', '2026-05-04 04:23:33', '2026-05-03 20:23:44', 'manual_logout', '2026-05-03 12:23:33', '2026-05-03 12:23:44'),
(128, '194b2dde115ea4737fc43a141425e9d6a7a6a875f23172f376e8bec47e2f7c29', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:23:49', '2026-05-03 20:25:45', '2026-05-04 04:23:49', '2026-05-03 20:25:45', 'manual_logout', '2026-05-03 12:23:49', '2026-05-03 12:25:45'),
(129, '1b20847afcbf6fed6bfe814c79fc5bcb6337bb2e293e049862e84c5fe6d40a8b', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:25:49', '2026-05-03 20:26:45', '2026-05-04 04:25:49', '2026-05-03 20:26:45', 'manual_logout', '2026-05-03 12:25:49', '2026-05-03 12:26:45'),
(130, '34b3257d15cb3b7840637b613e8e52bc409f1b3bdb895032cf419ecf07c74569', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:26:51', '2026-05-03 20:27:14', '2026-05-04 04:26:51', '2026-05-03 20:27:14', 'manual_logout', '2026-05-03 12:26:51', '2026-05-03 12:27:14'),
(131, '7b03a0c590c287d2616a6cf52f2e4cf913adeb2a8a16f9abcc171ff8e3f78824', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:27:19', '2026-05-03 20:31:39', '2026-05-04 04:27:19', '2026-05-03 20:31:39', 'manual_logout', '2026-05-03 12:27:19', '2026-05-03 12:31:39'),
(132, 'f28c97fc2a9f34c4f60467d507de4de12e002e64cc65fbf4bf2c520b73266709', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:31:44', '2026-05-03 20:39:01', '2026-05-04 04:31:44', '2026-05-03 20:39:01', 'manual_logout', '2026-05-03 12:31:44', '2026-05-03 12:39:01'),
(133, '6630ab9345bce971de35099569194889ca1402ddd4909e475a981cabab79b376', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:39:09', '2026-05-03 20:40:29', '2026-05-04 04:39:09', '2026-05-03 20:40:29', 'manual_logout', '2026-05-03 12:39:09', '2026-05-03 12:40:29'),
(134, 'c3feb455a3ec467bbd66f9566aff92dfb7efb200b869ed2b5681980f82bc05b4', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:40:32', '2026-05-03 20:41:04', '2026-05-04 04:40:32', '2026-05-03 20:41:04', 'manual_logout', '2026-05-03 12:40:32', '2026-05-03 12:41:04'),
(135, 'bf02841bc793b5c62bd7c133a21b899bf305b2cddeb50cebd8efef1539b56289', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:41:09', '2026-05-03 20:41:24', '2026-05-04 04:41:09', '2026-05-03 20:41:24', 'manual_logout', '2026-05-03 12:41:09', '2026-05-03 12:41:24'),
(136, 'f60f658ba26cbb5fdaea1f1163ced595371e9420cde62548d4fc7146221abee2', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:41:28', '2026-05-03 20:42:11', '2026-05-04 04:41:28', '2026-05-03 20:42:11', 'manual_logout', '2026-05-03 12:41:28', '2026-05-03 12:42:11'),
(137, 'b72dc7e93548436de7aa6a30e75867180dda57be112e0f9fdf5b5e24ea53bb4b', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:42:14', '2026-05-03 20:42:18', '2026-05-04 04:42:14', '2026-05-03 20:42:18', 'manual_logout', '2026-05-03 12:42:14', '2026-05-03 12:42:18'),
(138, '059dde39b0181b1190028c8ce1c20feae3d29668bb318ad51a3b18b08aef17b5', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:42:26', '2026-05-03 20:44:01', '2026-05-04 04:42:26', '2026-05-03 20:44:01', 'manual_logout', '2026-05-03 12:42:26', '2026-05-03 12:44:01'),
(139, '10a4c9375c8a32f7b76f4bbbbaa0f37e1d191cfa803878fc309bef48f6d611e4', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 20:44:08', '2026-05-03 21:18:45', '2026-05-04 04:44:08', '2026-05-03 21:18:45', 'idle_timeout', '2026-05-03 12:44:08', '2026-05-03 13:18:45'),
(140, '3d4822291e5d62b68df4a74198267633539387b96c9108d6b9206da696f87785', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:01:11', '2026-05-03 22:03:09', '2026-05-04 06:01:11', '2026-05-03 22:03:09', 'manual_logout', '2026-05-03 14:01:11', '2026-05-03 14:03:09'),
(141, 'b086c53185813b6e733ae65827468f52c04e679c482b75162093fb2ddd78cd48', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:03:14', '2026-05-03 22:03:41', '2026-05-04 06:03:14', '2026-05-03 22:03:41', 'manual_logout', '2026-05-03 14:03:14', '2026-05-03 14:03:41'),
(142, 'e7c556f6b78661fe44ad0e065073f44a6708b303f6750e93e30ce4d875564c75', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:03:45', '2026-05-03 22:04:46', '2026-05-04 06:03:45', '2026-05-03 22:04:46', 'manual_logout', '2026-05-03 14:03:45', '2026-05-03 14:04:46'),
(143, '6319d02c2a62b1584258e1361a2e091732280a3086464097cfe61d7ec66be61a', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:04:51', '2026-05-03 22:05:16', '2026-05-04 06:04:51', '2026-05-03 22:05:16', 'manual_logout', '2026-05-03 14:04:51', '2026-05-03 14:05:16');
INSERT INTO `auth_sessions` (`id`, `session_id`, `user_id`, `ip_address`, `user_agent`, `issued_at`, `last_activity`, `expires_at`, `revoked_at`, `revoked_reason`, `created_at`, `updated_at`) VALUES
(144, 'b148314a8aa071af789be593ef4378d786276e2f701ed7fb174033dd1465bc0a', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:05:20', '2026-05-03 22:06:12', '2026-05-04 06:05:20', '2026-05-03 22:06:12', 'manual_logout', '2026-05-03 14:05:20', '2026-05-03 14:06:12'),
(145, 'd763dfb5f14d0a2d724392d958302984bb746c0b574d73fbc6805bdbca7ff0c5', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:06:21', '2026-05-03 22:06:32', '2026-05-04 06:06:21', '2026-05-03 22:06:32', 'manual_logout', '2026-05-03 14:06:21', '2026-05-03 14:06:32'),
(146, '1899c5b191c0f063a41267cce07fcd085bee48957e2e701a422d8c452988a94c', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:06:39', '2026-05-03 22:07:32', '2026-05-04 06:06:39', '2026-05-03 22:07:32', 'manual_logout', '2026-05-03 14:06:39', '2026-05-03 14:07:32'),
(147, '6a8e8717b0e53f599b2a04c744165f5b8f978a741850c70c04654bd62d821722', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:07:35', '2026-05-03 22:08:38', '2026-05-04 06:07:35', '2026-05-03 22:08:38', 'manual_logout', '2026-05-03 14:07:35', '2026-05-03 14:08:38'),
(148, 'f5fbe0cbe2cef24cf6d53af9f010b62211404e9751bc00f76ac5b5095d984803', 11, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:08:42', '2026-05-03 22:10:11', '2026-05-04 06:08:42', '2026-05-03 22:10:11', 'manual_logout', '2026-05-03 14:08:42', '2026-05-03 14:10:11'),
(149, '19672737ee3ca04b7bd3c21cd55c6b40f6e8408cd77bdee41cd815fb911140a5', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:11:42', '2026-05-03 22:11:55', '2026-05-04 06:11:42', '2026-05-03 22:11:55', 'manual_logout', '2026-05-03 14:11:42', '2026-05-03 14:11:55'),
(150, '351eade1aba1a621d89e40f168c040f7dc0c3dc4795f8cd810951c73e5cb4768', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:11:59', '2026-05-03 22:12:44', '2026-05-04 06:11:59', '2026-05-03 22:12:44', 'manual_logout', '2026-05-03 14:11:59', '2026-05-03 14:12:44'),
(151, '922ba815be7bd9d1373a415463143d172d011c3c557a20d1116962c44181cf0b', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:12:48', '2026-05-03 22:12:55', '2026-05-04 06:12:48', '2026-05-03 22:12:55', 'manual_logout', '2026-05-03 14:12:48', '2026-05-03 14:12:55'),
(152, '51418b8501e6f33b76a3813d6c76b4d0c152761f55473c509ef5149391795ec4', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:12:58', '2026-05-03 22:13:47', '2026-05-04 06:12:58', '2026-05-03 22:13:47', 'manual_logout', '2026-05-03 14:12:58', '2026-05-03 14:13:47'),
(153, '362c87f899e086503a40b30e556539b6b4bcc174c9e1761f925d9daa99358c45', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:13:51', '2026-05-03 22:14:06', '2026-05-04 06:13:51', '2026-05-03 22:14:06', 'manual_logout', '2026-05-03 14:13:51', '2026-05-03 14:14:06'),
(154, '896884fbd518373eae16db2365dea16b5679ec851f72d26a5debfc3fd7fdfdaa', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:14:11', '2026-05-03 22:14:48', '2026-05-04 06:14:11', '2026-05-03 22:14:48', 'manual_logout', '2026-05-03 14:14:11', '2026-05-03 14:14:48'),
(155, 'b0033c20f23625df4b438ec2777658da8c941d4d83deef3c2e53cec7734cd4bf', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:14:54', '2026-05-03 22:16:13', '2026-05-04 06:14:54', '2026-05-03 22:16:13', 'manual_logout', '2026-05-03 14:14:54', '2026-05-03 14:16:13'),
(156, 'c77d71bc6b2a69f912000c7da243292152412b32fc537ccd5825e100013bf988', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:16:17', '2026-05-03 22:16:20', '2026-05-04 06:16:17', '2026-05-03 22:16:20', 'manual_logout', '2026-05-03 14:16:17', '2026-05-03 14:16:20'),
(157, 'de2f0bd2bc619224f16139633d797e66611c1cc09c3b605f1b52ad35bc0821d6', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:16:25', '2026-05-03 22:16:38', '2026-05-04 06:16:25', '2026-05-03 22:16:38', 'manual_logout', '2026-05-03 14:16:25', '2026-05-03 14:16:38'),
(158, 'c86c8d71a579bec724a36c3dea4e124c5c4d80379d0dc8bd68eadee8e254dcff', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:16:49', '2026-05-03 22:17:35', '2026-05-04 06:16:49', '2026-05-03 22:17:35', 'manual_logout', '2026-05-03 14:16:49', '2026-05-03 14:17:35'),
(159, '7cb8be4c88c0ad98087942ded95abd1d70bdd135cc0683917e525005ebe64acb', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:17:40', '2026-05-03 22:25:03', '2026-05-04 06:17:40', '2026-05-03 22:25:03', 'manual_logout', '2026-05-03 14:17:40', '2026-05-03 14:25:03'),
(160, 'b4052b0804cf86288a1a3a2afbad1d2694953545585dd3565744fe443747f32f', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:25:11', '2026-05-03 22:25:35', '2026-05-04 06:25:11', '2026-05-03 22:25:35', 'manual_logout', '2026-05-03 14:25:11', '2026-05-03 14:25:35'),
(161, 'c3f35fdbd2905b0b9737dc32a833b75cb7a4ab91c170dab8d351d0b8d666606f', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:25:43', '2026-05-03 22:26:08', '2026-05-04 06:25:43', '2026-05-03 22:26:08', 'manual_logout', '2026-05-03 14:25:43', '2026-05-03 14:26:08'),
(162, 'e5dc0e7b952e4545c82ec12700cf5412ab19be60173b1db2f7a6c4bd6603fd4e', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:26:13', '2026-05-03 22:26:26', '2026-05-04 06:26:13', '2026-05-03 22:26:26', 'manual_logout', '2026-05-03 14:26:13', '2026-05-03 14:26:26'),
(163, '49bb3a13854d421eb9b18b87b2c8111896f8e21c8b7c0add72919e1ce9cc2608', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:26:32', '2026-05-03 22:27:10', '2026-05-04 06:26:32', '2026-05-03 22:27:10', 'manual_logout', '2026-05-03 14:26:32', '2026-05-03 14:27:10'),
(164, 'e1cbd7b46ac28de89cea8ad6fdd42218c5deae358b0ae62aa75b0d0a622bd8d3', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:27:13', '2026-05-03 22:27:22', '2026-05-04 06:27:13', '2026-05-03 22:27:22', 'manual_logout', '2026-05-03 14:27:13', '2026-05-03 14:27:22'),
(165, '6b13a159f0260533419351e8204428270f542e1cd99c1456584fc069c7a5ff8b', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:27:30', '2026-05-03 22:28:01', '2026-05-04 06:27:30', '2026-05-03 22:28:01', 'manual_logout', '2026-05-03 14:27:30', '2026-05-03 14:28:01'),
(166, '1047aece70c43d251359faeec954ca32c04315a50399ee5603a7f5f073a005df', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:28:14', '2026-05-03 22:28:26', '2026-05-04 06:28:14', '2026-05-03 22:28:26', 'manual_logout', '2026-05-03 14:28:14', '2026-05-03 14:28:26'),
(167, '68304499e4a771a7e137dede22123de5da86c04f0202b98f1ec2b5968c8ce10b', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:28:30', '2026-05-03 22:28:49', '2026-05-04 06:28:30', '2026-05-03 22:28:49', 'manual_logout', '2026-05-03 14:28:30', '2026-05-03 14:28:49'),
(168, '0ebb07711fdc2e691ef06a8f113daf4ac184195e689f631bc187d4729cd05420', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-05-03 22:28:52', '2026-05-03 22:33:53', '2026-05-04 06:28:52', NULL, NULL, '2026-05-03 14:28:52', '2026-05-03 14:33:53');

-- --------------------------------------------------------

--
-- Table structure for table `auth_stepups`
--

CREATE TABLE `auth_stepups` (
  `id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `scope` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_stepups`
--

INSERT INTO `auth_stepups` (`id`, `token_hash`, `user_id`, `session_id`, `scope`, `expires_at`, `used_at`, `created_at`) VALUES
(9, 'afebb532fd7c13cb5de02c9b4fca50bd079d4ec89b3b37dea8094a6278ca26b3', 8, '1047aece70c43d251359faeec954ca32c04315a50399ee5603a7f5f073a005df', 'po_approval', '2026-05-03 22:33:24', '2026-05-03 22:28:25', '2026-05-03 14:28:24');

-- --------------------------------------------------------

--
-- Table structure for table `batch_recalls`
--

CREATE TABLE `batch_recalls` (
  `id` int(11) NOT NULL,
  `recall_code` varchar(30) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `batch_code` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `recall_class` enum('class_i','class_ii','class_iii') NOT NULL,
  `reason` text NOT NULL,
  `evidence_notes` text DEFAULT NULL,
  `evidence_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_files`)),
  `total_produced` int(11) NOT NULL DEFAULT 0,
  `total_dispatched` int(11) NOT NULL DEFAULT 0,
  `total_in_warehouse` int(11) NOT NULL DEFAULT 0,
  `total_recovered` int(11) NOT NULL DEFAULT 0,
  `status` enum('initiated','pending_approval','approved','in_progress','completed','cancelled') NOT NULL DEFAULT 'initiated',
  `initiated_by` int(11) NOT NULL,
  `initiated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_recalls`
--

INSERT INTO `batch_recalls` (`id`, `recall_code`, `batch_id`, `batch_code`, `product_id`, `product_name`, `recall_class`, `reason`, `evidence_notes`, `evidence_files`, `total_produced`, `total_dispatched`, `total_in_warehouse`, `total_recovered`, `status`, `initiated_by`, `initiated_at`, `approved_by`, `approved_at`, `approval_notes`, `rejection_reason`, `completed_by`, `completed_at`, `completion_notes`, `created_at`, `updated_at`) VALUES
(1, 'RCL-20260503-490', 3, 'BATCH-20260203-003', 3, 'Chocolate Milk 1L', 'class_i', 'BVasta', 'Yehey', NULL, 100, 13, 0, 163, 'in_progress', 2, '2026-05-03 16:36:50', 8, '2026-05-03 16:37:20', 'Yes', NULL, NULL, NULL, NULL, '2026-05-03 16:36:50', '2026-05-03 16:38:58');

--
-- Triggers `batch_recalls`
--
DELIMITER $$
CREATE TRIGGER `tr_recall_status_change` AFTER UPDATE ON `batch_recalls` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO recall_activity_log (recall_id, action, action_by, details)
        VALUES (
            NEW.id,
            CASE NEW.status
                WHEN 'approved' THEN 'approved'
                WHEN 'completed' THEN 'completed'
                WHEN 'cancelled' THEN 'cancelled'
                ELSE 'updated'
            END,
            COALESCE(NEW.approved_by, NEW.completed_by, NEW.initiated_by),
            JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status)
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `box_opening_log`
--

CREATE TABLE `box_opening_log` (
  `id` int(11) NOT NULL,
  `opening_code` varchar(30) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `boxes_opened` int(11) NOT NULL DEFAULT 1,
  `pieces_from_opening` int(11) NOT NULL,
  `reason` enum('partial_sale','transfer','adjustment','qc_check','other') DEFAULT 'partial_sale',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `opened_by` int(11) NOT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `canvass_quotes`
--

CREATE TABLE `canvass_quotes` (
  `id` int(11) NOT NULL,
  `canvass_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `delivery_days` int(11) DEFAULT 7,
  `payment_terms` enum('cash','credit_7','credit_15','credit_30','credit_45','credit_60') DEFAULT 'cash',
  `validity_date` date DEFAULT NULL,
  `is_selected` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `quoted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashier_shifts`
--

CREATE TABLE `cashier_shifts` (
  `id` int(11) NOT NULL,
  `shift_code` varchar(30) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `opening_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expected_cash` decimal(12,2) DEFAULT NULL,
  `actual_cash` decimal(12,2) DEFAULT NULL,
  `cash_variance` decimal(12,2) DEFAULT NULL,
  `total_sales` decimal(12,2) DEFAULT 0.00,
  `total_collections` decimal(12,2) DEFAULT 0.00,
  `total_transactions` int(11) DEFAULT 0,
  `cash_in` decimal(12,2) DEFAULT 0.00,
  `cash_out` decimal(12,2) DEFAULT 0.00,
  `status` enum('active','closed','reconciled') DEFAULT 'active',
  `opening_notes` text DEFAULT NULL,
  `closing_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cashier_shifts`
--

INSERT INTO `cashier_shifts` (`id`, `shift_code`, `cashier_id`, `start_time`, `end_time`, `opening_cash`, `expected_cash`, `actual_cash`, `cash_variance`, `total_sales`, `total_collections`, `total_transactions`, `cash_in`, `cash_out`, `status`, `opening_notes`, `closing_notes`, `created_at`, `updated_at`) VALUES
(1, 'SHIFT-20260203-001', 7, '2026-02-03 21:22:01', '2026-02-03 21:22:32', 5000.00, 5050.00, NULL, NULL, 50.00, 0.00, 1, 0.00, 0.00, 'closed', '', '', '2026-02-03 13:22:01', '2026-02-03 13:22:32'),
(2, 'SHIFT-20260211-001', 7, '2026-02-11 12:41:32', NULL, 5000.00, NULL, NULL, NULL, 0.00, 0.00, 0, 0.00, 0.00, 'active', '', NULL, '2026-02-11 04:41:32', '2026-02-11 04:41:32');

-- --------------------------------------------------------

--
-- Table structure for table `cash_adjustments`
--

CREATE TABLE `cash_adjustments` (
  `id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `adjustment_type` enum('in','out') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ccp_logs`
--

CREATE TABLE `ccp_logs` (
  `id` int(11) NOT NULL,
  `ccp_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `production_run_id` int(11) DEFAULT NULL,
  `measured_value` varchar(100) DEFAULT NULL,
  `is_within_limit` tinyint(1) DEFAULT 1,
  `deviation_action` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `log_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ccp_standards`
--

CREATE TABLE `ccp_standards` (
  `id` int(11) NOT NULL,
  `ccp_name` varchar(100) NOT NULL,
  `category` enum('receiving','storage','pasteurization','cooling','packaging','distribution') NOT NULL,
  `critical_limit` varchar(200) NOT NULL,
  `target_value` varchar(200) DEFAULT NULL,
  `monitoring_frequency` varchar(100) DEFAULT NULL,
  `corrective_action` text DEFAULT NULL,
  `hazard_description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ccp_standards`
--

INSERT INTO `ccp_standards` (`id`, `ccp_name`, `category`, `critical_limit`, `target_value`, `monitoring_frequency`, `corrective_action`, `hazard_description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Raw Milk Reception Temperature', 'receiving', '???10??C', '4-8??C', 'Every delivery', 'Reject milk if >10??C or chill immediately', 'Biological: Bacterial growth if milk too warm', 'active', '2026-02-03 14:43:08', NULL),
(2, 'Pasteurization Temperature', 'pasteurization', '???72??C for 15 seconds', '73??C for 15-20 seconds', 'Continuous', 'Divert flow to reprocess, do not release batch', 'Biological: Pathogen survival if underprocessed', 'active', '2026-02-03 14:43:08', NULL),
(3, 'Pasteurization Time', 'pasteurization', '???15 seconds at 72??C', '15-20 seconds', 'Continuous', 'Divert flow, extend holding time', 'Biological: Insufficient heat treatment', 'active', '2026-02-03 14:43:08', NULL),
(4, 'Post-Pasteurization Cooling', 'cooling', '???4??C within 2 hours', '4??C within 1 hour', 'After each batch', 'Accelerate cooling, check refrigeration', 'Biological: Bacterial regrowth if cooling delayed', 'active', '2026-02-03 14:43:08', NULL),
(5, 'Cold Storage Temperature', 'storage', '2-8??C', '4??C', 'Every 4 hours', 'Transfer to functioning unit, investigate cause', 'Biological: Bacterial growth in warm storage', 'active', '2026-02-03 14:43:08', NULL),
(6, 'Packaging Seal Integrity', 'packaging', 'Complete seal, no leaks', 'Airtight seal', 'Every batch / random sampling', 'Reject and repackage affected units', 'Biological: Contamination through seal failure', 'active', '2026-02-03 14:43:08', NULL),
(7, 'Distribution Vehicle Temperature', 'distribution', '???8??C', '4-6??C', 'Before dispatch and on arrival', 'Do not dispatch if >8??C, repair refrigeration', 'Biological: Temperature abuse during transport', 'active', '2026-02-03 14:43:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chillers`
--

CREATE TABLE `chillers` (
  `id` int(11) NOT NULL,
  `chiller_name` varchar(100) NOT NULL,
  `chiller_code` varchar(50) NOT NULL,
  `chiller_type` enum('walk_in','reach_in','blast','display') NOT NULL DEFAULT 'walk_in',
  `capacity_liters` decimal(10,2) NOT NULL,
  `location` varchar(200) DEFAULT NULL,
  `target_temperature` decimal(5,2) DEFAULT 4.00,
  `temperature_tolerance` decimal(5,2) DEFAULT 2.00,
  `current_temperature` decimal(5,2) DEFAULT NULL,
  `status` enum('running','stopped','maintenance','fault','decommissioned') DEFAULT 'running',
  `last_maintenance` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chillers`
--

INSERT INTO `chillers` (`id`, `chiller_name`, `chiller_code`, `chiller_type`, `capacity_liters`, `location`, `target_temperature`, `temperature_tolerance`, `current_temperature`, `status`, `last_maintenance`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Cold Room 1', 'CH-001', 'walk_in', 50000.00, 'Main Storage Building', 4.00, 2.00, NULL, 'running', NULL, NULL, '2026-02-03 14:43:08', NULL),
(2, 'Cold Room 2', 'CH-002', 'walk_in', 30000.00, 'Distribution Center', 4.00, 2.00, NULL, 'running', NULL, NULL, '2026-02-03 14:43:08', NULL),
(3, 'Display Chiller 1', 'CH-003', 'display', 500.00, 'Retail Store', 6.00, 2.00, NULL, 'running', NULL, NULL, '2026-02-03 14:43:08', NULL),
(4, 'Blast Chiller 1', 'CH-004', 'blast', 1000.00, 'Processing Area', 2.00, 2.00, NULL, 'stopped', NULL, NULL, '2026-02-03 14:43:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chiller_locations`
--

CREATE TABLE `chiller_locations` (
  `id` int(11) NOT NULL,
  `chiller_code` varchar(20) NOT NULL,
  `chiller_name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL COMMENT 'Maximum units capacity',
  `current_count` int(11) NOT NULL DEFAULT 0,
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `min_temperature` decimal(4,1) DEFAULT 2.0,
  `max_temperature` decimal(4,1) DEFAULT 8.0,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('available','full','maintenance','offline') NOT NULL DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chiller_locations`
--

INSERT INTO `chiller_locations` (`id`, `chiller_code`, `chiller_name`, `capacity`, `current_count`, `temperature_celsius`, `min_temperature`, `max_temperature`, `location`, `status`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'CHILL-A1', 'Chiller A - Section 1', 500, 807, 10.0, 2.0, 4.0, 'Main Warehouse', 'full', 1, NULL, '2026-02-03 09:12:00', '2026-02-11 04:03:42'),
(2, 'CHILL-A2', 'Chiller A - Section 2', 500, 51, 3.2, 2.0, 4.0, 'Main Warehouse', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-10 18:50:20'),
(3, 'CHILL-B1', 'Chiller B - Section 1', 400, 149, 2.8, 2.0, 4.0, 'Main Warehouse', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-09 06:49:49'),
(4, 'CHILL-B2', 'Chiller B - Section 2', 400, 10, 3.1, 2.0, 4.0, 'Main Warehouse', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-09 13:15:19'),
(5, 'CHILL-C1', 'Dispatch Chillers', 200, 0, 3.5, 2.0, 4.0, 'Dispatch Area', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-05 07:18:34'),
(6, 'FREEZE-01', 'Freezer Unit 1', 300, 0, -19.0, -20.0, -18.0, 'Cold Storage', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-03 09:12:00');

-- --------------------------------------------------------

--
-- Table structure for table `chiller_temperature_logs`
--

CREATE TABLE `chiller_temperature_logs` (
  `id` int(11) NOT NULL,
  `chiller_id` int(11) NOT NULL,
  `temperature_celsius` decimal(4,1) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chiller_temperature_logs`
--

INSERT INTO `chiller_temperature_logs` (`id`, `chiller_id`, `temperature_celsius`, `recorded_by`, `notes`, `recorded_at`) VALUES
(1, 1, 10.0, 5, '', '2026-02-05 18:34:18');

-- --------------------------------------------------------

--
-- Table structure for table `chiller_temp_logs`
--

CREATE TABLE `chiller_temp_logs` (
  `id` int(11) NOT NULL,
  `chiller_id` int(11) NOT NULL,
  `temperature` decimal(5,2) NOT NULL,
  `log_date` date NOT NULL,
  `log_time` time NOT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `status` enum('normal','warning','critical') DEFAULT 'normal',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(30) DEFAULT NULL,
  `customer_type` enum('walk_in','institutional','supermarket','feeding_program','distributor','restaurant') NOT NULL,
  `name` varchar(200) NOT NULL,
  `sub_location` varchar(200) DEFAULT NULL COMMENT 'e.g., specific school for feeding program',
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `current_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Outstanding receivables',
  `payment_terms_days` int(11) DEFAULT 0 COMMENT '0=cash, 15/30/60 for credit',
  `default_payment_type` enum('cash','credit') DEFAULT 'cash',
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `customer_type`, `name`, `sub_location`, `contact_person`, `contact_number`, `email`, `address`, `credit_limit`, `current_balance`, `payment_terms_days`, `default_payment_type`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, NULL, 'institutional', 'SM Supermarket', 'Tacloban Branch', 'John Manager', '09171234567', 'sm@example.com', 'SM Tacloban', 100000.00, -7500.00, 30, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-05 07:22:13'),
(2, NULL, 'institutional', 'Robinson\'s Supermarket', 'Downtown', 'Maria Supervisor', '09181234567', 'robinsons@example.com', 'Robinsons Place Tacloban', 80000.00, -12240.00, 30, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-09 13:29:59'),
(3, NULL, 'supermarket', 'Metro Gaisano', 'Downtown', 'Pedro Cruz', '09191234567', 'gaisano@example.com', 'Downtown Tacloban', 50000.00, 0.00, 15, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-03 09:47:31'),
(4, NULL, 'supermarket', 'PureGold', 'Real Street', 'Ana Reyes', '09201234567', 'puregold@example.com', 'Real Street Tacloban', 75000.00, 0.00, 30, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-03 09:47:31'),
(5, NULL, 'restaurant', 'Hotel 101', 'Main', 'Chris Santos', '09211234567', 'hotel101@example.com', 'Hotel 101 Tacloban', 30000.00, 0.00, 7, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-03 09:47:31'),
(6, 'DEPED-CDO-001', 'supermarket', 'DepEd Region X Feeding Program', NULL, 'Maria Santos', '09090909', '', 'DepEd Complex, Cagayan de Oro City', 500000.00, -1310.00, 0, 'cash', 'active', NULL, '2026-02-05 08:06:35', '2026-02-11 04:39:31'),
(8, 'CUS00001', 'institutional', 'COC', 'PHINMA-COC', 'test@gmail.com', '09078734040', 'test@gmail.com', 'PHINMA CoC CARMEn', 500.00, 0.00, 30, 'credit', 'active', '', '2026-02-21 01:10:50', '2026-02-21 01:10:50');

-- --------------------------------------------------------

--
-- Table structure for table `customer_returns`
--

CREATE TABLE `customer_returns` (
  `id` int(11) NOT NULL,
  `return_code` varchar(30) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `dr_number` varchar(30) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(200) NOT NULL,
  `return_date` date NOT NULL,
  `return_reason` enum('damaged_transit','expired','customer_rejection','quality_issue','wrong_order','overage','other') NOT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `disposition` enum('return_to_inventory','hold_for_qc','dispose','rework') DEFAULT 'hold_for_qc',
  `qc_inspection_required` tinyint(1) DEFAULT 1,
  `qc_inspected_by` int(11) DEFAULT NULL,
  `qc_inspected_at` datetime DEFAULT NULL,
  `qc_decision` enum('restock','dispose','rework') DEFAULT NULL,
  `status` enum('pending','received','inspected','processed','closed') NOT NULL DEFAULT 'pending',
  `received_by` int(11) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_return_items`
--

CREATE TABLE `customer_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `delivery_item_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `batch_code` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_value` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `condition_status` enum('good','damaged','expired','questionable') NOT NULL,
  `disposition` enum('return_to_inventory','hold_for_qc','dispose','rework') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int(11) NOT NULL,
  `dr_number` varchar(30) NOT NULL COMMENT 'Delivery Receipt Number',
  `sales_order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(200) NOT NULL,
  `customer_type` enum('walk_in','institutional','supermarket','feeding_program') NOT NULL,
  `sub_location` varchar(200) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('preparing','ready','dispatched','in_transit','delivered','accepted','partial_accepted','rejected','returned','cancelled') DEFAULT 'preparing',
  `dispatched_at` datetime DEFAULT NULL,
  `dispatched_by` int(11) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `delivered_by` int(11) DEFAULT NULL,
  `acceptance_status` enum('pending','accepted','partial','rejected') DEFAULT 'pending',
  `accepted_at` datetime DEFAULT NULL COMMENT 'When customer signed DR',
  `customer_signature` varchar(255) DEFAULT NULL COMMENT 'Signature image path or digital signature',
  `received_by_name` varchar(100) DEFAULT NULL COMMENT 'Name of person who received',
  `received_by_position` varchar(100) DEFAULT NULL,
  `has_returns` tinyint(1) DEFAULT 0,
  `return_reason` text DEFAULT NULL,
  `return_quantity` int(11) DEFAULT 0,
  `return_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Stores item-level return info' CHECK (json_valid(`return_details`)),
  `payment_type` enum('cash','credit') NOT NULL DEFAULT 'cash',
  `amount_collected` decimal(12,2) DEFAULT 0.00 COMMENT 'For COD',
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_items`
--

CREATE TABLE `delivery_items` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `sales_order_item_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `variant` varchar(100) DEFAULT NULL,
  `size_value` decimal(10,2) NOT NULL,
  `size_unit` varchar(10) NOT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_dispatched` int(11) NOT NULL DEFAULT 0,
  `quantity_accepted` int(11) DEFAULT 0 COMMENT 'Accepted by customer',
  `quantity_rejected` int(11) DEFAULT 0 COMMENT 'Rejected by customer',
  `quantity_returned` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `barcode_scanned` varchar(50) DEFAULT NULL,
  `manufacturing_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('pending','picked','dispatched','accepted','partial','rejected','returned') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `picked_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `delivery_items`
--
DELIMITER $$
CREATE TRIGGER `tr_delivery_items_set_batch_id` BEFORE INSERT ON `delivery_items` FOR EACH ROW BEGIN
    IF NEW.batch_id IS NULL AND NEW.inventory_id IS NOT NULL THEN
        SET NEW.batch_id = (
            SELECT batch_id FROM finished_goods_inventory 
            WHERE id = NEW.inventory_id
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_receipts`
--

CREATE TABLE `delivery_receipts` (
  `id` int(11) NOT NULL,
  `dr_number` varchar(50) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `customer_type` varchar(50) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `status` enum('draft','pending','picking','preparing','ready','dispatched','delivered','cancelled') DEFAULT 'draft',
  `picking_started_at` datetime DEFAULT NULL,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `priority` enum('normal','rush','scheduled') DEFAULT 'normal',
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `prepared_by` int(11) DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `checked_by` int(11) DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `dispatched_by` int(11) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `returns_processed` tinyint(1) DEFAULT 0,
  `returns_processed_at` timestamp NULL DEFAULT NULL,
  `returns_processed_by` int(11) DEFAULT NULL,
  `received_by_name` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_receipts`
--

INSERT INTO `delivery_receipts` (`id`, `dr_number`, `order_id`, `customer_id`, `customer_name`, `customer_type`, `delivery_address`, `contact_person`, `contact_number`, `total_items`, `total_amount`, `amount_paid`, `status`, `picking_started_at`, `payment_status`, `priority`, `scheduled_date`, `scheduled_time`, `prepared_by`, `prepared_at`, `checked_by`, `checked_at`, `dispatched_by`, `dispatched_at`, `vehicle_number`, `driver_name`, `delivered_at`, `returns_processed`, `returns_processed_at`, `returns_processed_by`, `received_by_name`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'DR-20260203-0101', NULL, 1, 'SM Supermarket', NULL, 'SM Tacloban', NULL, '09171234567', 50, 8500.00, 0.00, 'delivered', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', NULL, NULL, '2026-02-03 17:47:31', 0, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(2, 'DR-20260203-0102', NULL, 2, 'Robinson\'s Supermarket', NULL, 'Robinsons Tacloban', NULL, '09181234567', 30, 5250.00, 0.00, 'delivered', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', NULL, NULL, '2026-02-03 17:47:31', 0, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(3, 'DR-20260203-0103', NULL, 3, 'Metro Gaisano', NULL, 'Downtown Tacloban', NULL, '09191234567', 75, 12750.00, 5000.00, 'delivered', NULL, 'partial', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', NULL, NULL, '2026-02-03 17:47:31', 0, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(4, 'DR-20260131-0104', NULL, 4, 'PureGold', NULL, 'Real Street Tacloban', NULL, '09201234567', 40, 9500.00, 0.00, 'delivered', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-31 17:47:31', NULL, NULL, '2026-01-31 17:47:31', 0, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(5, 'DR-20260129-0105', NULL, 5, 'Hotel 101', NULL, 'Hotel 101 Tacloban', NULL, '09211234567', 25, 4500.00, 2000.00, 'delivered', NULL, 'partial', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-29 17:47:31', NULL, NULL, '2026-01-29 17:47:31', 0, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(6, 'DR-20260127-0106', NULL, 1, 'SM Supermarket', NULL, 'SM Tacloban', NULL, '09171234567', 60, 15000.00, 15000.00, 'delivered', NULL, 'paid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-27 17:47:31', NULL, NULL, '2026-01-27 17:47:31', 0, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-05 15:22:13'),
(7, 'DR-20260124-0107', NULL, 2, 'Robinson\'s Supermarket', NULL, 'Robinsons Tacloban', NULL, '09181234567', 45, 11250.00, 11250.00, 'delivered', NULL, 'paid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-24 17:47:31', NULL, NULL, '2026-01-24 17:47:31', 0, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 19:44:35'),
(8, 'DR-20260205-1010', 2, 5, 'Hotel 101', NULL, 'Hotel 101 Tacloban', NULL, '09211234567', 1, 1235.00, 0.00, 'dispatched', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-05 18:31:32', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 6, '2026-02-05 18:17:05', '2026-02-05 18:31:32'),
(9, 'DR-20260205-0201', 3, 6, 'DepEd Region X Feeding Program', NULL, 'DepEd Complex, Cagayan de Oro City', NULL, '09171234567', 1, 2940.00, 0.00, 'dispatched', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-05 19:18:31', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 6, '2026-02-05 19:18:16', '2026-02-05 19:18:31'),
(10, 'DR-20260209-1623', 4, 6, 'DepEd Region X Feeding Program', NULL, 'DepEd Complex, Cagayan de Oro City', NULL, '09090909', 1, 1615.00, 0.00, 'dispatched', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-09 13:17:11', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 6, '2026-02-09 13:16:43', '2026-02-09 13:17:11'),
(11, 'DR-20260209-8948', 6, 5, 'Hotel 101', NULL, 'Hotel 101 Tacloban', NULL, '09211234567', 1, 1235.00, 0.00, 'dispatched', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-09 14:13:37', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 5, '2026-02-09 14:13:26', '2026-02-09 14:13:37'),
(12, 'DR-20260209-4188', 5, 5, 'Hotel 101', NULL, 'Hotel 101 Tacloban', NULL, '09211234567', 1, 2280.00, 0.00, 'dispatched', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-09 14:13:35', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 5, '2026-02-09 14:13:28', '2026-02-09 14:13:35'),
(13, 'DR-20260209-8129', 7, 5, 'Hotel 101', NULL, 'Hotel 101 Tacloban', NULL, '09211234567', 1, 12350.00, 0.00, 'dispatched', NULL, 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-09 14:15:34', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 5, '2026-02-09 14:15:32', '2026-02-09 14:15:34'),
(14, 'DR-20260209-3057', 10, 3, 'Metro Gaisano', NULL, 'Downtown Tacloban', NULL, '09191234567', 1, 4200.00, 0.00, 'delivered', NULL, 'unpaid', 'normal', NULL, NULL, 5, '2026-02-09 15:23:23', NULL, NULL, 5, '2026-02-09 15:32:45', NULL, NULL, '2026-02-09 20:40:45', 1, '2026-02-09 12:40:39', 5, NULL, NULL, 5, '2026-02-09 14:43:47', '2026-02-09 20:40:45'),
(15, 'DR-20260209-6063', 8, 3, 'Metro Gaisano', NULL, 'Downtown Tacloban', NULL, '09191234567', 1, 12350.00, 0.00, 'delivered', NULL, 'unpaid', 'normal', NULL, NULL, 5, '2026-02-09 17:38:45', NULL, NULL, 5, '2026-02-09 17:38:55', NULL, NULL, '2026-02-09 17:41:02', 0, NULL, NULL, NULL, NULL, 5, '2026-02-09 14:43:49', '2026-02-09 17:41:02'),
(16, 'DR-20260209-0996', 11, 4, 'PureGold', NULL, 'Real Street Tacloban', NULL, '09201234567', 1, 585.00, 0.00, 'delivered', NULL, 'unpaid', 'normal', NULL, NULL, 5, '2026-02-09 18:16:17', NULL, NULL, 5, '2026-02-09 18:16:35', NULL, NULL, '2026-02-09 18:28:08', 1, '2026-02-09 10:25:56', 5, NULL, NULL, 5, '2026-02-09 17:35:37', '2026-02-09 18:28:08'),
(17, 'DR-20260209-1746', 12, 6, 'DepEd Region X Feeding Program', NULL, 'DepEd Complex, Cagayan de Oro City', NULL, '09090909', 1, 2940.00, 0.00, 'delivered', NULL, 'unpaid', 'normal', NULL, NULL, 5, '2026-02-09 17:52:47', NULL, NULL, 5, '2026-02-09 17:52:58', NULL, NULL, '2026-02-09 18:02:13', 0, NULL, NULL, NULL, NULL, 5, '2026-02-09 17:50:00', '2026-02-09 18:02:13'),
(18, 'DR-20260209-6858', 13, 2, 'Robinson\'s Supermarket', NULL, 'Robinsons Place Tacloban', NULL, '09181234567', 1, 990.00, 990.00, 'delivered', NULL, 'paid', 'normal', NULL, NULL, 5, '2026-02-09 21:20:56', NULL, NULL, 5, '2026-02-09 21:21:46', NULL, NULL, '2026-02-09 21:22:01', 1, '2026-02-09 13:21:57', 5, NULL, NULL, 5, '2026-02-09 21:20:19', '2026-02-09 21:29:59'),
(19, 'DR-20260211-001', 14, 6, 'DepEd Region X Feeding Program', NULL, 'DepEd Complex, Cagayan de Oro City', NULL, '09090909', 1, 1140.00, 0.00, 'dispatched', '2026-02-11 02:57:40', 'unpaid', 'normal', NULL, NULL, 5, '2026-02-11 10:59:49', NULL, NULL, 5, '2026-02-11 11:01:16', NULL, NULL, NULL, 1, '2026-02-11 03:01:29', 5, NULL, NULL, 5, '2026-02-11 02:57:40', '2026-02-11 11:01:29'),
(20, 'DR-20260211-004', 15, 5, 'Hotel 101', NULL, 'Hotel 101 Tacloban', NULL, '09211234567', 1, 2800.00, 0.00, 'dispatched', '2026-02-11 10:43:26', 'unpaid', 'normal', NULL, NULL, 5, '2026-02-11 14:05:33', NULL, NULL, 5, '2026-02-11 14:05:55', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 5, '2026-02-11 10:43:26', '2026-02-11 14:05:55'),
(21, 'DR-20260211-002', 16, 6, 'DepEd Region X Feeding Program', NULL, 'DepEd Complex, Cagayan de Oro City', NULL, '09090909', 1, 1575.00, 50.00, 'delivered', '2026-02-11 11:58:15', 'partial', 'normal', NULL, NULL, 5, '2026-02-11 12:08:28', NULL, NULL, 5, '2026-02-11 12:10:15', NULL, NULL, '2026-02-11 14:04:59', 1, '2026-02-11 04:11:55', 5, NULL, NULL, 5, '2026-02-11 11:58:15', '2026-02-11 14:04:59'),
(22, 'DR-20260211-003', 17, 6, 'DepEd Region X Feeding Program', NULL, 'DepEd Complex, Cagayan de Oro City', NULL, '09090909', 1, 1260.00, 1260.00, 'delivered', '2026-02-11 12:30:53', 'paid', 'normal', NULL, NULL, 5, '2026-02-11 12:35:52', NULL, NULL, 5, '2026-02-11 12:37:24', NULL, NULL, '2026-02-11 14:04:57', 1, '2026-02-11 04:37:30', 5, NULL, NULL, 5, '2026-02-11 12:30:53', '2026-02-11 14:04:57'),
(23, 'DR-20260221-001', 19, 1, 'SM Supermarket', NULL, 'SM Tacloban', NULL, '09171234567', 1, 2500.00, 0.00, 'delivered', '2026-02-21 06:15:08', 'unpaid', 'normal', NULL, NULL, 5, '2026-02-21 06:16:19', NULL, NULL, 5, '2026-02-21 06:16:28', NULL, NULL, '2026-02-21 06:16:48', 1, '2026-02-20 22:16:41', 5, NULL, NULL, 5, '2026-02-21 06:15:08', '2026-02-21 06:16:48'),
(24, 'DR-20260221-002', 20, 3, 'Metro Gaisano', NULL, 'Downtown Tacloban', NULL, '09191234567', 1, 3000.00, 0.00, 'delivered', '2026-02-21 07:34:41', 'unpaid', 'normal', NULL, NULL, 5, '2026-02-21 07:35:06', NULL, NULL, 5, '2026-02-21 07:35:19', NULL, NULL, '2026-02-21 07:35:37', 1, '2026-02-20 23:35:30', 5, NULL, NULL, 5, '2026-02-21 07:34:41', '2026-02-21 07:35:37'),
(25, 'DR-20260328-001', 22, 6, 'DepEd Region X Feeding Program', NULL, 'DepEd Complex, Cagayan de Oro City', NULL, '09090909', 1, 1000.00, 0.00, 'dispatched', '2026-03-28 15:49:53', 'unpaid', 'normal', NULL, NULL, 5, '2026-03-28 15:52:08', NULL, NULL, 5, '2026-03-28 15:52:20', NULL, NULL, NULL, 1, '2026-03-28 07:52:43', 5, NULL, NULL, 5, '2026-03-28 15:49:53', '2026-03-28 15:52:43');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_receipt_items`
--

CREATE TABLE `delivery_receipt_items` (
  `id` int(11) NOT NULL,
  `delivery_receipt_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_packed` int(11) DEFAULT 0,
  `quantity_picked` int(11) DEFAULT 0,
  `quantity_delivered` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00,
  `chiller_source_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `picked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_receipt_items`
--

INSERT INTO `delivery_receipt_items` (`id`, `delivery_receipt_id`, `product_id`, `batch_id`, `inventory_id`, `quantity_ordered`, `quantity_packed`, `quantity_picked`, `quantity_delivered`, `unit_price`, `total_price`, `chiller_source_id`, `notes`, `picked_at`, `created_at`) VALUES
(1, 8, 8, NULL, NULL, 13, 0, 0, 0, 95.00, 1235.00, NULL, NULL, NULL, '2026-02-05 18:17:05'),
(2, 9, 7, NULL, NULL, 21, 0, 0, 0, 140.00, 2940.00, NULL, NULL, NULL, '2026-02-05 19:18:16'),
(3, 10, 8, NULL, NULL, 17, 0, 0, 0, 95.00, 1615.00, NULL, NULL, NULL, '2026-02-09 13:16:43'),
(4, 11, 8, NULL, NULL, 13, 0, 0, 0, 95.00, 1235.00, NULL, NULL, NULL, '2026-02-09 14:13:26'),
(5, 12, 8, NULL, NULL, 24, 0, 0, 0, 95.00, 2280.00, NULL, NULL, NULL, '2026-02-09 14:13:28'),
(6, 13, 8, NULL, NULL, 130, 0, 0, 0, 95.00, 12350.00, NULL, NULL, NULL, '2026-02-09 14:15:32'),
(7, 14, 7, NULL, NULL, 30, 0, 0, 0, 140.00, 4200.00, NULL, NULL, NULL, '2026-02-09 14:43:47'),
(9, 14, 1, 1, NULL, 13, 13, 0, 0, 0.00, 0.00, NULL, NULL, NULL, '2026-02-09 15:10:09'),
(10, 14, 1, 1, NULL, 24, 24, 0, 0, 0.00, 0.00, NULL, NULL, NULL, '2026-02-09 15:23:23'),
(11, 16, 3, 3, NULL, 13, 13, 0, 3, 45.00, 585.00, NULL, NULL, NULL, '2026-02-09 17:35:37'),
(12, 15, 8, 8, NULL, 96, 96, 0, 0, 0.00, 0.00, NULL, NULL, NULL, '2026-02-09 17:38:45'),
(14, 17, 7, 7, NULL, 21, 21, 0, 21, 140.00, 2940.00, NULL, NULL, NULL, '2026-02-09 17:52:47'),
(15, 18, 3, 17, NULL, 22, 10, 0, 10, 45.00, 990.00, NULL, NULL, NULL, '2026-02-09 21:20:19'),
(16, 19, 8, NULL, 26, 12, 0, 12, 0, 95.00, 1140.00, NULL, NULL, '2026-02-11 10:59:49', '2026-02-11 02:57:40'),
(17, 20, 7, NULL, 8, 20, 0, 20, 0, 140.00, 2800.00, NULL, NULL, '2026-02-11 14:05:33', '2026-02-11 10:43:26'),
(18, 21, 1, NULL, 27, 15, 0, 15, 0, 105.00, 1575.00, NULL, NULL, '2026-02-11 12:08:28', '2026-02-11 11:58:15'),
(19, 22, 1, NULL, 24, 12, 0, 12, 0, 105.00, 1260.00, NULL, NULL, '2026-02-11 12:35:52', '2026-02-11 12:30:53'),
(20, 23, 21, NULL, 34, 50, 0, 50, 0, 50.00, 2500.00, NULL, NULL, '2026-02-21 06:16:19', '2026-02-21 06:15:08'),
(21, 24, 22, NULL, 35, 50, 0, 50, 0, 60.00, 3000.00, NULL, NULL, '2026-02-21 07:35:06', '2026-02-21 07:34:41'),
(22, 25, 21, NULL, 38, 20, 0, 20, 0, 50.00, 1000.00, NULL, NULL, '2026-03-28 15:52:08', '2026-03-28 15:49:53');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_returns`
--

CREATE TABLE `delivery_returns` (
  `id` int(11) NOT NULL,
  `delivery_receipt_id` int(11) NOT NULL,
  `dr_item_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_returned` decimal(10,2) NOT NULL,
  `return_reason` enum('damaged_in_transit','customer_rejection','wrong_order','expired_near_expiry','quality_issue','customer_not_available','wrong_address','other') NOT NULL,
  `condition` enum('resellable','damaged','expired','qc_hold') NOT NULL DEFAULT 'resellable',
  `disposition` enum('return_to_inventory','dispose','qc_review','pending') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_returns`
--

INSERT INTO `delivery_returns` (`id`, `delivery_receipt_id`, `dr_item_id`, `product_id`, `batch_id`, `quantity_returned`, `return_reason`, `condition`, `disposition`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 16, 11, 3, 3, 10.00, 'wrong_order', 'resellable', 'return_to_inventory', 'Basta', 5, '2026-02-09 10:25:56', '2026-02-09 10:25:56');

-- --------------------------------------------------------

--
-- Table structure for table `disposals`
--

CREATE TABLE `disposals` (
  `id` int(11) NOT NULL,
  `disposal_code` varchar(30) NOT NULL,
  `source_type` enum('raw_milk','finished_goods','ingredients','production_batch','milk_receiving') NOT NULL,
  `source_id` int(11) NOT NULL,
  `source_reference` varchar(100) DEFAULT NULL COMMENT 'Batch code, receiving code, etc.',
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs' COMMENT 'pcs, liters, kg, boxes',
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `total_value` decimal(14,2) DEFAULT 0.00 COMMENT 'Total loss value',
  `disposal_category` enum('qc_failed','expired','spoiled','contaminated','damaged','rejected_receipt','production_waste','other') NOT NULL,
  `disposal_reason` text NOT NULL COMMENT 'Detailed reason for disposal',
  `disposal_method` enum('drain','incinerate','animal_feed','compost','special_waste','other') NOT NULL DEFAULT 'drain',
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `initiated_by` int(11) NOT NULL,
  `initiated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `disposed_by` int(11) DEFAULT NULL,
  `disposed_at` datetime DEFAULT NULL,
  `disposal_location` varchar(100) DEFAULT NULL,
  `documentation_path` varchar(255) DEFAULT NULL COMMENT 'Path to photos/documents',
  `witness_name` varchar(100) DEFAULT NULL COMMENT 'Witness during disposal',
  `notes` text DEFAULT NULL,
  `recall_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disposals`
--

INSERT INTO `disposals` (`id`, `disposal_code`, `source_type`, `source_id`, `source_reference`, `product_id`, `product_name`, `quantity`, `unit`, `unit_cost`, `total_value`, `disposal_category`, `disposal_reason`, `disposal_method`, `status`, `initiated_by`, `initiated_at`, `approved_by`, `approved_at`, `approval_notes`, `disposed_by`, `disposed_at`, `disposal_location`, `documentation_path`, `witness_name`, `notes`, `recall_id`, `created_at`, `updated_at`) VALUES
(1, 'DSP-20260208-0001', 'raw_milk', 5, 'RAW-20260205-911', NULL, 'Raw Milk', 50.00, 'liters', 30.00, 1500.00, 'expired', 'Basta', 'drain', 'rejected', 2, '2026-02-08 20:44:41', 8, '2026-02-08 21:00:59', 'Rejected by GM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-08 12:44:41', '2026-02-08 13:00:59'),
(2, 'DSP-20260208-0002', 'raw_milk', 5, 'RAW-20260205-911', NULL, 'Raw Milk', 25.00, 'liters', 30.00, 750.00, 'expired', 'Expired raw milk - past shelf life by 1 day, not safe for processing. Disposal via drain per SOP.', 'drain', 'completed', 8, '2026-02-08 21:24:27', 8, '2026-02-08 21:25:15', 'Approved - confirmed expired inventory needs disposal per food safety protocol.', 8, '2026-02-08 21:26:15', 'Processing Area Drain #2', NULL, 'Juan Dela Cruz', '\n[Execution] Drained 25 liters of expired raw milk. Area cleaned and sanitized after disposal.', NULL, '2026-02-08 13:24:27', '2026-02-08 13:26:15'),
(3, 'DSP-20260209-0001', 'raw_milk', 5, 'RAW-20260205-911', NULL, 'Raw Milk', 25.00, 'liters', 30.00, 750.00, 'expired', 'Basta', 'drain', 'approved', 2, '2026-02-09 12:41:04', 8, '2026-02-09 12:41:24', 'Yehey', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 04:41:04', '2026-02-09 04:41:24'),
(4, 'DSP-20260209-0002', 'finished_goods', 2, 'BATCH-20260203-001', 1, 'Fresh Milk 1L', 32.00, 'pcs', 0.00, 0.00, 'qc_failed', 'Yehey', 'drain', 'rejected', 2, '2026-02-09 12:42:56', 8, '2026-02-09 12:43:11', 'Rejected by GM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 04:42:56', '2026-02-09 04:43:11'),
(5, 'DSP-20260209-0003', 'finished_goods', 2, 'BATCH-20260203-001', 1, 'Fresh Milk 1L', 32.00, 'pcs', 0.00, 0.00, 'qc_failed', 'Yehey', 'drain', 'completed', 2, '2026-02-09 12:43:41', 8, '2026-02-09 12:44:14', '', 5, '2026-02-09 12:48:36', 'Basta', NULL, '', '\n[Execution] Ngekngok', NULL, '2026-02-09 04:43:41', '2026-02-09 04:48:36'),
(6, 'DSP-20260209-0004', 'finished_goods', 3, 'BATCH-20260203-002', 2, 'Fresh Milk 500ml', 99.00, 'pcs', 0.00, 0.00, 'qc_failed', 'Basta', 'drain', 'completed', 2, '2026-02-09 12:49:32', 8, '2026-02-09 12:49:46', '', 5, '2026-02-09 18:34:15', 'Yehey', NULL, 'Basta', '\n[Execution] BOOM', NULL, '2026-02-09 04:49:32', '2026-02-09 10:34:15'),
(7, 'DSP-20260209-0005', 'raw_milk', 5, 'RAW-20260205-911', NULL, 'Raw Milk', 25.00, 'liters', 30.00, 750.00, 'expired', 'Basta', 'drain', 'approved', 2, '2026-02-09 20:38:44', 8, '2026-02-09 20:38:59', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-09 12:38:44', '2026-02-09 12:38:59'),
(8, 'DSP-20260211-0001', 'raw_milk', 5, 'RAW-20260205-911', NULL, 'Raw Milk', 25.00, 'liters', 30.00, 750.00, 'spoiled', 'TEST', 'drain', 'completed', 2, '2026-02-11 12:20:02', 8, '2026-02-11 12:20:56', 'Basta', 8, '2026-02-11 12:21:00', '', NULL, '', '\n[Execution] ', NULL, '2026-02-11 04:20:02', '2026-02-11 04:21:00'),
(9, 'DSP-20260216-0001', 'finished_goods', 20, 'BATCH-20260205-0004', 2, 'Fresh Milk 500ml', 50.00, 'pcs', 0.00, 0.00, 'expired', 'Basta', 'drain', 'completed', 2, '2026-02-16 19:46:48', 8, '2026-02-16 19:48:07', '', 5, '2026-02-16 19:48:38', 'Tangina', NULL, 'Tangina', '\n[Execution] Tangina', NULL, '2026-02-16 11:46:48', '2026-02-16 11:48:38'),
(10, 'DSP-20260216-0002', 'finished_goods', 21, 'BATCH-20260205-0005', 2, 'Fresh Milk 500ml', 50.00, 'pcs', 0.00, 0.00, 'expired', 'Basta', 'drain', 'completed', 2, '2026-02-16 19:47:44', 8, '2026-02-16 19:48:06', '', 5, '2026-02-16 19:48:28', 'Area B', NULL, 'Brybry', '\n[Execution] Tangina', NULL, '2026-02-16 11:47:44', '2026-02-16 11:48:28');

-- --------------------------------------------------------

--
-- Table structure for table `disposal_items`
--

CREATE TABLE `disposal_items` (
  `id` int(11) NOT NULL,
  `disposal_id` int(11) NOT NULL,
  `source_type` enum('raw_milk','finished_goods','ingredients','production_batch') NOT NULL,
  `source_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `batch_code` varchar(50) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `line_total` decimal(14,2) DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `farmers`
--

CREATE TABLE `farmers` (
  `id` int(11) NOT NULL,
  `farmer_code` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `milk_type_id` int(11) NOT NULL DEFAULT 1 COMMENT 'Primary milk type supplied by farmer',
  `membership_type` enum('member','non_member') NOT NULL DEFAULT 'member',
  `base_price_per_liter` decimal(10,2) NOT NULL DEFAULT 40.00,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `farmers`
--

INSERT INTO `farmers` (`id`, `farmer_code`, `first_name`, `last_name`, `contact_number`, `address`, `milk_type_id`, `membership_type`, `base_price_per_liter`, `bank_name`, `bank_account_number`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'F-0001', 'Lacandula', '', '123456789123456789', '', 1, 'member', 39.25, '', '', 1, '2026-02-03 08:04:13', '2026-02-09 06:26:22'),
(2, 'F-0002', 'Galla', '', NULL, NULL, 1, 'member', 40.00, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(3, 'F-0003', 'DMDC', '', NULL, NULL, 1, 'member', 40.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(4, 'F-0004', 'Dumindin', '', NULL, NULL, 1, 'member', 39.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(5, 'F-0005', 'Paraguya', '', NULL, NULL, 1, 'member', 40.00, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(6, 'F-0006', 'MMDC', '', NULL, NULL, 1, 'member', 39.75, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(7, 'F-0007', 'Bernales', '', NULL, NULL, 1, 'member', 40.00, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(8, 'F-0008', 'Tagadan', '', NULL, NULL, 1, 'member', 70.00, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(9, 'F-0009', 'Abonitalla', '', NULL, NULL, 1, 'member', 39.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(10, 'F-0010', 'C1/Dumaluan', '', NULL, NULL, 1, 'member', 39.50, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(11, 'F-0011', 'C1/Dumaluan Goat', '', NULL, NULL, 2, 'member', 69.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(12, 'F-0012', 'C3/Valledor', '', NULL, NULL, 1, 'member', 39.75, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(13, 'F-0013', 'Jardin', '', NULL, NULL, 1, 'member', 40.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(14, 'F-0014', 'Malig', '', NULL, NULL, 1, 'member', 40.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(15, 'F-0015', 'Abriol', '', NULL, NULL, 1, 'member', 40.00, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(16, 'F-0016', 'Gargar', '', NULL, NULL, 1, 'member', 39.75, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
(17, 'F-0017', 'Navarro', '', NULL, NULL, 1, 'member', 40.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19');

-- --------------------------------------------------------

--
-- Table structure for table `fg_customers`
--

CREATE TABLE `fg_customers` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `customer_type` enum('wholesaler','retailer','distributor','institutional') DEFAULT 'retailer',
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `payment_terms` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fg_customers`
--

INSERT INTO `fg_customers` (`id`, `customer_code`, `customer_name`, `customer_type`, `contact_person`, `phone`, `email`, `address`, `city`, `credit_limit`, `payment_terms`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CUST-001', 'SM Supermarket - Baguio', 'retailer', 'Juan Dela Cruz', '09171234567', NULL, 'SM City Baguio, Luneta Hill', 'Baguio City', 100000.00, 30, 1, '2026-02-03 17:10:27', '2026-02-03 17:10:27'),
(2, 'CUST-002', 'Puregold - La Trinidad', 'retailer', 'Maria Santos', '09181234567', NULL, 'Km 5 La Trinidad', 'La Trinidad', 75000.00, 15, 1, '2026-02-03 17:10:27', '2026-02-03 17:10:27'),
(3, 'CUST-003', 'Highland Cafe', 'institutional', 'Pedro Reyes', '09191234567', NULL, 'Session Road', 'Baguio City', 25000.00, 7, 1, '2026-02-03 17:10:27', '2026-02-03 17:10:27'),
(4, 'CUST-004', 'Benguet Cold Chain', 'distributor', 'Ana Garcia', '09201234567', NULL, 'Tomay, La Trinidad', 'La Trinidad', 200000.00, 30, 1, '2026-02-03 17:10:27', '2026-02-03 17:10:27'),
(5, 'CUST-005', 'Camp John Hay Commissary', 'institutional', 'Robert Smith', '09211234567', NULL, 'Camp John Hay', 'Baguio City', 50000.00, 15, 1, '2026-02-03 17:10:27', '2026-02-03 17:10:27');

-- --------------------------------------------------------

--
-- Table structure for table `fg_dispatch_log`
--

CREATE TABLE `fg_dispatch_log` (
  `id` int(11) NOT NULL,
  `dispatch_code` varchar(30) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `batch_code` varchar(50) DEFAULT NULL,
  `dr_id` int(11) DEFAULT NULL,
  `quantity_released` int(11) NOT NULL,
  `boxes_released` int(11) DEFAULT 0,
  `pieces_released` int(11) DEFAULT 0,
  `unit_of_measure` varchar(20) DEFAULT 'pcs',
  `released_by` int(11) NOT NULL,
  `released_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fg_dispatch_log`
--

INSERT INTO `fg_dispatch_log` (`id`, `dispatch_code`, `inventory_id`, `product_id`, `batch_code`, `dr_id`, `quantity_released`, `boxes_released`, `pieces_released`, `unit_of_measure`, `released_by`, `released_at`, `reference_type`, `reference_id`, `notes`) VALUES
(4, 'DSP-20260209-0384', 2, 1, 'BATCH-20260203-001', 14, 13, 0, 0, 'pcs', 5, '2026-02-09 07:10:09', NULL, NULL, NULL),
(5, 'DSP-20260209-9250', 2, 1, 'BATCH-20260203-001', 14, 24, 0, 0, 'pcs', 5, '2026-02-09 07:23:23', NULL, NULL, NULL),
(6, 'DSP-20260209-2758', 9, 8, 'BATCH-20260203-008', 15, 96, 0, 0, 'pcs', 5, '2026-02-09 09:38:45', NULL, NULL, NULL),
(7, 'DSP-20260209-1431', 8, 7, 'BATCH-20260203-007', 17, 21, 0, 0, 'pcs', 5, '2026-02-09 09:52:47', NULL, NULL, NULL),
(8, 'DSP-20260209-0038', 4, 3, 'BATCH-20260203-003', 16, 13, 0, 0, 'pcs', 5, '2026-02-09 10:16:17', NULL, NULL, NULL),
(9, 'DSP-20260209-2536', 23, 3, 'BATCH-20260209-0007', 18, 10, 0, 0, 'pcs', 5, '2026-02-09 13:20:56', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `fg_inventory_transactions`
--

CREATE TABLE `fg_inventory_transactions` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `transaction_type` enum('receive','sale','transfer','adjustment','disposal','return') NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `boxes_quantity` int(11) DEFAULT 0,
  `pieces_quantity` int(11) DEFAULT 0,
  `quantity_before` int(11) DEFAULT NULL,
  `quantity_after` int(11) DEFAULT NULL,
  `boxes_before` int(11) DEFAULT NULL,
  `pieces_before` int(11) DEFAULT NULL,
  `boxes_after` int(11) DEFAULT NULL,
  `pieces_after` int(11) DEFAULT NULL,
  `from_chiller_id` int(11) DEFAULT NULL,
  `to_chiller_id` int(11) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fg_inventory_transactions`
--

INSERT INTO `fg_inventory_transactions` (`id`, `transaction_code`, `transaction_type`, `inventory_id`, `product_id`, `quantity`, `boxes_quantity`, `pieces_quantity`, `quantity_before`, `quantity_after`, `boxes_before`, `pieces_before`, `boxes_after`, `pieces_after`, `from_chiller_id`, `to_chiller_id`, `performed_by`, `reason`, `reference_type`, `reference_id`, `created_at`) VALUES
(1, 'FGT-20260203-4215', 'sale', 6, 5, 1, 0, 1, 100, 99, 2, 4, 2, 3, 1, NULL, 7, 'POS Sale - SI-2026-00010', 'sales_transaction', 10, '2026-02-03 10:34:55'),
(2, 'FGT-20260203-2990', 'sale', 2, 1, 1, 0, 1, 100, 99, 8, 4, 8, 3, 1, NULL, 7, 'POS Sale - SI-2026-00011', 'sales_transaction', 11, '2026-02-03 10:35:09'),
(3, 'FGT-20260203-4099', 'sale', 2, 1, 1, 0, 1, 99, 98, 8, 3, 8, 2, 1, NULL, 7, 'POS Sale - SI-2026-00012', 'sales_transaction', 12, '2026-02-03 10:35:13'),
(4, 'FGT-20260203-3963', 'sale', 2, 1, 1, 0, 1, 98, 97, 8, 2, 8, 1, 1, NULL, 7, 'POS Sale - SI-2026-00013', 'sales_transaction', 13, '2026-02-03 10:35:14'),
(5, 'FGT-20260203-1988', 'sale', 2, 1, 1, 0, 1, 97, 96, 8, 1, 8, 0, 1, NULL, 7, 'POS Sale - SI-2026-00014', 'sales_transaction', 14, '2026-02-03 10:35:14'),
(6, 'FGT-20260203-1703', 'sale', 2, 1, 1, 1, -11, 96, 95, 8, 0, 7, 11, 1, NULL, 7, 'POS Sale - SI-2026-00015', 'sales_transaction', 15, '2026-02-03 10:35:14'),
(7, 'FGT-20260203-7768', 'sale', 2, 1, 1, 0, 1, 95, 94, 7, 11, 7, 10, 1, NULL, 7, 'POS Sale - SI-2026-00016', 'sales_transaction', 16, '2026-02-03 10:35:15'),
(8, 'FGT-20260203-7617', 'sale', 2, 1, 1, 0, 1, 94, 93, 7, 10, 7, 9, 1, NULL, 7, 'POS Sale - SI-2026-00017', 'sales_transaction', 17, '2026-02-03 10:35:19'),
(9, 'FGT-20260203-4561', 'sale', 2, 1, 1, 0, 1, 93, 92, 7, 9, 7, 8, 1, NULL, 7, 'POS Sale - SI-2026-00018', 'sales_transaction', 18, '2026-02-03 10:35:20'),
(10, 'FGT-20260203-9196', 'sale', 2, 1, 1, 0, 1, 92, 91, 7, 8, 7, 7, 1, NULL, 7, 'POS Sale - SI-2026-00019', 'sales_transaction', 19, '2026-02-03 10:35:20'),
(11, 'FGT-20260203-5153', 'sale', 2, 1, 1, 0, 1, 91, 90, 7, 7, 7, 6, 1, NULL, 7, 'POS Sale - SI-2026-00020', 'sales_transaction', 20, '2026-02-03 10:35:20'),
(12, 'FGT-20260203-2477', 'sale', 5, 4, 1, 1, -19, 100, 99, 5, 0, 4, 19, 1, NULL, 7, 'POS Sale - SI-2026-00021', 'sales_transaction', 21, '2026-02-03 10:38:36'),
(13, 'FGT-20260203-7944', 'sale', 2, 1, 1, 0, 1, 90, 89, 7, 6, 7, 5, 1, NULL, 1, 'POS Sale - SI-2026-00022', 'sales_transaction', 22, '2026-02-03 10:39:26'),
(14, 'FGT-20260203-4603', 'sale', 2, 1, 1, 0, 1, 89, 88, 7, 5, 7, 4, 1, NULL, 1, 'POS Sale - SI-2026-00023', 'sales_transaction', 23, '2026-02-03 10:39:38'),
(15, 'FGT-20260203-4635', 'sale', 2, 1, 1, 0, 1, 88, 87, 7, 4, 7, 3, 1, NULL, 1, 'POS Sale - SI-2026-00024', 'sales_transaction', 24, '2026-02-03 10:39:45'),
(16, 'FGT-20260203-3191', 'sale', 2, 1, 1, 0, 1, 87, 86, 7, 3, 7, 2, 1, NULL, 1, 'POS Sale - SI-2026-00025', 'sales_transaction', 25, '2026-02-03 10:40:42'),
(17, 'FGT-20260203-5675', 'sale', 2, 1, 1, 0, 1, 86, 85, 7, 2, 7, 1, 1, NULL, 1, 'POS Sale - SI-2026-00026', 'sales_transaction', 26, '2026-02-03 10:43:07'),
(18, 'FGT-20260203-6028', 'sale', 2, 1, 1, 0, 1, 85, 84, 7, 1, 7, 0, 1, NULL, 1, 'POS Sale - SI-2026-00027', 'sales_transaction', 27, '2026-02-03 10:44:00'),
(19, 'FGT-20260203-2662', 'sale', 2, 1, 1, 1, -11, 84, 83, 7, 0, 6, 11, 1, NULL, 1, 'POS Sale - SI-2026-00028', 'sales_transaction', 28, '2026-02-03 10:44:31'),
(20, 'FGT-20260203-0170', 'sale', 3, 2, 1, 0, 1, 100, 99, 4, 4, 4, 3, 1, NULL, 7, 'POS Sale - SI-2026-00029', 'sales_transaction', 29, '2026-02-03 10:44:45'),
(21, 'FGT-20260203-6753', 'sale', 8, 7, 1, 1, -19, 100, 99, 5, 0, 4, 19, 1, NULL, 7, 'POS Sale - SI-2026-00030', 'sales_transaction', 30, '2026-02-03 10:55:28'),
(22, 'FGT-20260203-2935', 'sale', 5, 4, 1, 0, 1, 99, 98, 4, 19, 4, 18, 1, NULL, 7, 'POS Sale - SI-2026-00031', 'sales_transaction', 31, '2026-02-03 13:22:09'),
(23, 'FGT-20260205-0341', 'sale', 2, 1, 1, 0, 1, 83, 82, 6, 11, 6, 10, 1, NULL, 7, 'POS Sale - SI-2026-00032', 'sales_transaction', 32, '2026-02-05 07:22:00'),
(24, 'FGT-20260205-0171', 'sale', 4, 3, 1, 0, 1, 100, 99, 8, 4, 8, 3, 1, NULL, 7, 'POS Sale - SI-2026-00033', 'sales_transaction', 33, '2026-02-05 07:26:23'),
(25, 'FGT-20260205-0020', 'receive', 20, 2, 50, 0, 50, 0, 50, 0, 0, 0, 50, NULL, 2, 5, 'Received from production batch BATCH-20260205-0004', NULL, NULL, '2026-02-05 08:36:51'),
(26, 'FGT-20260205-0021', 'receive', 21, 2, 50, 0, 50, 0, 50, 0, 0, 0, 50, NULL, 1, 5, 'Received from production batch BATCH-20260205-0005', NULL, NULL, '2026-02-05 11:17:02'),
(27, 'DSP-20260209-2655', 'disposal', 2, 1, 32, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, 'Disposal: DSP-20260209-0003 - Yehey', 'disposal', 5, '2026-02-09 04:48:36'),
(28, 'FGT-20260209-69896839c2efe', 'transfer', 3, 2, 99, 4, 3, 99, 99, 4, 3, 4, 3, 1, 3, 5, '', NULL, NULL, '2026-02-09 04:53:13'),
(29, 'FGT-20260209-0022', 'receive', 22, 1, 50, 0, 50, 0, 50, 0, 0, 0, 50, NULL, 3, 5, 'Received from production batch BATCH-20260209-0006', NULL, NULL, '2026-02-09 06:49:49'),
(30, 'TXN-20260209-2822', 'sale', 2, 1, -13, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, NULL, 'delivery_receipt', 14, '2026-02-09 07:10:09'),
(31, 'TXN-20260209-3554', 'sale', 2, 1, -24, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, NULL, 'delivery_receipt', 14, '2026-02-09 07:23:23'),
(32, 'TXN-20260209-8978', 'sale', 9, 8, -96, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, NULL, 'delivery_receipt', 15, '2026-02-09 09:38:45'),
(33, 'TXN-20260209-8205', 'sale', 8, 7, -21, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, NULL, 'delivery_receipt', 17, '2026-02-09 09:52:47'),
(34, 'TXN-20260209-5843', 'sale', 4, 3, -13, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, NULL, 'delivery_receipt', 16, '2026-02-09 10:16:17'),
(35, 'DSP-20260209-2280', 'disposal', 3, 2, 99, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, 'Disposal: DSP-20260209-0004 - Basta', 'disposal', 6, '2026-02-09 10:34:15'),
(36, 'FGT-20260209-0023', 'receive', 23, 3, 10, 0, 10, 0, 10, 0, 0, 0, 10, NULL, 4, 5, 'Received from production batch BATCH-20260209-0007', NULL, NULL, '2026-02-09 13:15:19'),
(37, 'TXN-20260209-4190', 'sale', 23, 3, -10, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, NULL, 'delivery_receipt', 18, '2026-02-09 13:20:56'),
(38, 'FGT-20260211-0024', 'receive', 24, 1, 50, 0, 50, 0, 50, 0, 0, 0, 50, NULL, 1, 5, 'Received from production batch BATCH-20260211-0008', NULL, NULL, '2026-02-10 18:30:27'),
(39, 'FGT-20260211-0025', 'receive', 25, 8, 1, 0, 1, 0, 1, 0, 0, 0, 1, NULL, 2, 5, 'Received from production batch BATCH-20260211-0009', NULL, NULL, '2026-02-10 18:50:20'),
(40, 'FGT-20260211-0026', 'receive', 26, 8, 20, 0, 20, 0, 20, 0, 0, 0, 20, NULL, 1, 5, 'Received from production batch BATCH-20260211-0010', NULL, NULL, '2026-02-10 18:57:36'),
(41, 'FGT-20260211-0027', 'receive', 27, 1, 10, 0, 10, 0, 10, 0, 0, 0, 10, NULL, 1, 5, 'Received from production batch BATCH-20260211-0011', NULL, NULL, '2026-02-11 04:03:42'),
(42, 'DSP-20260216-3223', 'disposal', 21, 2, 50, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, 'Disposal: DSP-20260216-0002 - Basta', 'disposal', 10, '2026-02-16 11:48:28'),
(43, 'DSP-20260216-2210', 'disposal', 20, 2, 50, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, 'Disposal: DSP-20260216-0001 - Basta', 'disposal', 9, '2026-02-16 11:48:38'),
(44, '', 'receive', 32, 0, 0, 0, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 5, 'Warehouse receiving - assigned to chiller', NULL, NULL, '2026-02-20 17:30:46'),
(45, '', 'receive', 33, 0, 0, 0, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 5, 'Warehouse receiving - assigned to chiller', NULL, NULL, '2026-02-20 17:30:48'),
(46, '', 'receive', 35, 22, 0, 0, 100, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 5, 'Test', NULL, NULL, '2026-02-20 23:31:57'),
(47, '', 'receive', 36, 22, 0, 0, 50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 5, 'Warehouse receiving - assigned to chiller', NULL, NULL, '2026-02-20 23:43:08'),
(48, '', 'receive', 37, 22, 0, 0, 50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 5, 'Warehouse receiving - assigned to chiller', NULL, NULL, '2026-02-20 23:43:11'),
(49, '', 'receive', 38, 21, 0, 0, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 5, 'Test', NULL, NULL, '2026-03-28 07:39:22'),
(50, '', 'receive', 39, 21, 0, 0, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 5, 'Test', NULL, NULL, '2026-03-28 07:39:32'),
(51, '', 'receive', 40, 21, 0, 0, 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 5, 'Test', NULL, NULL, '2026-03-28 07:39:36'),
(52, '', 'receive', 41, 21, 0, 0, 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 5, 'Warehouse receiving - assigned to chiller', NULL, NULL, '2026-05-03 12:44:16'),
(53, '', 'receive', 42, 21, 0, 0, 95, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 5, 'Warehouse receiving - assigned to chiller', NULL, NULL, '2026-05-03 12:44:19');

-- --------------------------------------------------------

--
-- Table structure for table `fg_receiving`
--

CREATE TABLE `fg_receiving` (
  `id` int(11) NOT NULL,
  `receiving_code` varchar(50) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_received` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `chiller_location_id` int(11) DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `temperature_on_receipt` decimal(5,2) DEFAULT NULL,
  `qc_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `qc_checked_by` int(11) DEFAULT NULL,
  `qc_checked_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finished_goods_inventory`
--

CREATE TABLE `finished_goods_inventory` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `qc_release_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `milk_type_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar','cream','flavored_milk') NOT NULL,
  `product_variant` varchar(100) DEFAULT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `size_ml` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL COMMENT 'Original quantity received',
  `remaining_quantity` int(11) NOT NULL,
  `quantity_available` int(11) NOT NULL DEFAULT 0,
  `disposed_quantity` int(11) DEFAULT 0,
  `quantity_reserved` int(11) NOT NULL DEFAULT 0,
  `quantity_boxes` int(11) NOT NULL DEFAULT 0,
  `quantity_pieces` int(11) NOT NULL DEFAULT 0,
  `boxes_available` int(11) NOT NULL DEFAULT 0,
  `pieces_available` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `unit_price` decimal(10,2) DEFAULT NULL,
  `manufacturing_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `chiller_id` int(11) DEFAULT NULL,
  `chiller_location` varchar(50) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_movement_at` datetime DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `status` enum('available','low_stock','reserved','dispatched','expired') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `disposal_id` int(11) DEFAULT NULL,
  `disposed_at` datetime DEFAULT NULL,
  `disposal_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `finished_goods_inventory`
--

INSERT INTO `finished_goods_inventory` (`id`, `batch_id`, `qc_release_id`, `product_id`, `milk_type_id`, `product_name`, `product_type`, `product_variant`, `variant`, `size_ml`, `quantity`, `remaining_quantity`, `quantity_available`, `disposed_quantity`, `quantity_reserved`, `quantity_boxes`, `quantity_pieces`, `boxes_available`, `pieces_available`, `unit`, `unit_price`, `manufacturing_date`, `expiry_date`, `barcode`, `chiller_id`, `chiller_location`, `received_at`, `last_movement_at`, `received_by`, `status`, `notes`, `disposal_id`, `disposed_at`, `disposal_reason`) VALUES
(2, 1, 1, 1, 1, 'Fresh Milk 1L', 'bottled_milk', NULL, NULL, NULL, 63, 68, 0, 32, 0, 3, 9, -1, 0, 'bottle', NULL, '2026-02-03', '2026-02-10', NULL, 1, NULL, '2026-02-03 09:35:24', '2026-02-11 12:08:29', 1, '', NULL, 5, '2026-02-09 12:48:36', 'Yehey'),
(3, 2, 2, 2, 1, 'Fresh Milk 500ml', 'bottled_milk', NULL, NULL, NULL, 100, 1, 0, 99, 0, 4, 3, 4, 3, 'bottle', NULL, '2026-02-03', '2026-02-10', NULL, 3, NULL, '2026-02-03 09:35:24', NULL, 1, '', NULL, 6, '2026-02-09 18:34:15', 'Basta'),
(4, 3, 3, 3, 1, 'Chocolate Milk 1L', 'bottled_milk', NULL, NULL, NULL, 97, 100, 0, 0, 0, 7, 2, 7, 12, 'bottle', NULL, '2026-02-03', '2026-02-10', NULL, 1, NULL, '2026-02-03 09:35:24', '2026-02-09 18:25:56', 1, '', NULL, NULL, NULL, NULL),
(5, 4, 4, 4, 1, 'Plain Yogurt 500g', 'yogurt', NULL, NULL, NULL, 100, 100, 98, 0, 0, 4, 18, 4, 18, 'cup', NULL, '2026-02-03', '2026-02-17', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL, NULL, NULL, NULL),
(6, 5, 5, 5, 1, 'Strawberry Yogurt 150g', 'yogurt', NULL, NULL, NULL, 100, 100, 99, 0, 0, 2, 3, 2, 3, 'cup', NULL, '2026-02-03', '2026-02-17', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL, NULL, NULL, NULL),
(7, 6, 6, 6, 1, 'Kesong Puti 250g', 'cheese', NULL, NULL, NULL, 100, 100, 100, 0, 0, 4, 4, 4, 4, 'pack', NULL, '2026-02-03', '2026-02-24', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL, NULL, NULL, NULL),
(8, 7, 7, 7, 1, 'Butter 250g', 'butter', NULL, NULL, NULL, 79, 100, 48, 0, 0, 2, 8, -1, 0, 'block', NULL, '2026-02-03', '2026-03-05', NULL, 1, NULL, '2026-02-03 09:35:24', '2026-02-11 14:05:33', 1, 'available', NULL, NULL, NULL, NULL),
(9, 8, 8, 8, 1, 'Fresh Cream 1L', 'bottled_milk', NULL, NULL, NULL, 4, 100, 4, 0, 0, 0, 4, -1, 0, 'bottle', NULL, '2026-02-03', '2026-02-13', NULL, 1, NULL, '2026-02-03 09:35:24', '2026-02-11 10:59:49', 1, 'available', NULL, NULL, NULL, NULL),
(20, 14, 9, 2, 1, 'Fresh Milk 500ml', 'bottled_milk', '500ml', NULL, NULL, 50, 0, 0, 50, 0, 0, 50, 0, 50, 'pcs', NULL, '2026-02-05', '2026-02-12', NULL, 2, NULL, '2026-02-05 08:36:51', NULL, 5, '', '', 9, '2026-02-16 19:48:38', 'Basta'),
(21, 15, 10, 2, 1, 'Fresh Milk 500ml', 'bottled_milk', '500ml', NULL, NULL, 50, 0, 0, 50, 0, 0, 50, 0, 50, 'pcs', NULL, '2026-02-05', '2026-02-12', NULL, 1, NULL, '2026-02-05 11:17:02', NULL, 5, '', '', 10, '2026-02-16 19:48:28', 'Basta'),
(22, 16, 11, 1, 1, 'Fresh Milk 1L', 'bottled_milk', '1 Liter', NULL, NULL, 50, 50, 13, 0, 0, 1, 1, -1, 0, 'pcs', NULL, '2026-02-09', '2026-02-16', NULL, 3, NULL, '2026-02-09 06:49:49', '2026-02-11 12:08:29', 5, 'available', 'Basta', NULL, NULL, NULL),
(23, 17, 12, 3, 1, 'Chocolate Milk 1L', 'bottled_milk', '1 Liter', NULL, NULL, 0, 10, 0, 0, 0, 0, 10, 0, 10, 'pcs', NULL, '2026-02-09', '2026-02-16', NULL, 4, NULL, '2026-02-09 13:15:19', '2026-02-21 06:15:50', 5, 'available', '', NULL, NULL, NULL),
(24, 18, 13, 1, 1, 'Fresh Milk 1L', 'bottled_milk', '1 Liter', NULL, NULL, 50, 50, 50, 0, 0, 0, 50, 1, 33, 'pcs', NULL, '2026-02-11', '2026-02-18', NULL, 1, NULL, '2026-02-10 18:30:27', '2026-02-11 12:35:52', 5, 'available', '', NULL, NULL, NULL),
(25, 19, 14, 8, 1, 'Fresh Cream 1L', '', NULL, NULL, NULL, 1, 1, 1, 0, 0, 0, 1, -1, 0, 'pcs', NULL, '2026-02-11', '2026-02-18', NULL, 2, NULL, '2026-02-10 18:50:20', '2026-02-11 10:59:49', 5, 'available', '', NULL, NULL, NULL),
(26, 20, 15, 8, 1, 'Fresh Cream 1L', '', NULL, NULL, NULL, 20, 20, 20, 0, 0, 0, 20, 0, 13, 'pcs', NULL, '2026-02-11', '2026-02-18', NULL, 1, NULL, '2026-02-10 18:57:36', '2026-02-11 10:59:49', 5, 'available', '', NULL, NULL, NULL),
(27, 21, 16, 1, 1, 'Fresh Milk 1L', 'bottled_milk', '1 Liter', NULL, NULL, 10, 10, 10, 0, 0, 0, 10, 0, 10, 'pcs', NULL, '2026-02-11', '2026-02-18', NULL, 1, NULL, '2026-02-11 04:03:42', NULL, 5, 'available', '', NULL, NULL, NULL),
(32, 23, NULL, 17, 1, 'Basta', 'flavored_milk', '200ml', '200ml', 200, 5, 5, 5, 0, 0, 0, 5, 0, 5, 'pcs', NULL, '2026-02-21', '2026-02-27', NULL, 1, 'Chiller A - Section 1', '2026-02-20 17:01:14', '2026-02-21 06:15:50', 3, 'available', 'From packaging run PKG-20260221-001 · batch BATCH-20260221-0013\nAssigned to Chiller A - Section 1 by warehouse on 2026-02-21 01:30:46', NULL, NULL, NULL),
(33, 23, NULL, 17, 1, 'Basta', 'flavored_milk', '200ml', '200ml', 200, 5, 5, 5, 0, 0, 0, 5, 0, 5, 'pcs', NULL, '2026-02-21', '2026-02-27', NULL, 1, 'Chiller A - Section 1', '2026-02-20 17:01:14', '2026-02-21 06:15:50', 3, 'available', 'From packaging run PKG-20260221-001 · batch BATCH-20260221-0013\nAssigned to Chiller A - Section 1 by warehouse on 2026-02-21 01:30:48', NULL, NULL, NULL),
(34, 24, NULL, 21, 1, 'MilkBarBisaya', 'bottled_milk', '50ml', '50ml', 50, 100, 30, 30, 0, 0, 0, 0, 5, 0, 'pcs', NULL, '2026-02-21', '2026-02-27', NULL, 1, 'Chiller A - Section 1', '2026-02-20 17:25:36', '2026-03-28 15:52:08', 3, 'available', 'From packaging run PKG-20260221-002 · batch BATCH-20260221-0014\nAssigned to Chiller A - Section 1 by warehouse on 2026-02-21 01:27:50', NULL, NULL, NULL),
(35, 25, NULL, 22, 1, 'AnotherTesting', 'flavored_milk', 'Yippe', 'Yippe', 250, 100, 100, 100, 0, 0, 0, 100, 0, 50, 'pcs', NULL, '2026-02-21', '2026-02-27', NULL, 2, 'Chiller A - Section 2', '2026-02-20 23:31:28', '2026-02-21 07:35:06', 3, 'available', 'From packaging run PKG-20260221-003 · batch BATCH-20260221-0015\nAssigned to Chiller A - Section 2 by warehouse on 2026-02-21 07:31:57 - Test', NULL, NULL, NULL),
(36, 26, NULL, 22, 1, 'AnotherTesting', 'flavored_milk', 'Yippe', 'Yippe', 250, 50, 50, 50, 0, 0, 0, 50, 0, 50, 'pcs', NULL, '2026-02-21', '2026-02-27', NULL, 1, 'Chiller A - Section 1', '2026-02-20 23:42:40', '2026-02-21 07:43:08', 3, 'available', 'From packaging run PKG-20260221-004 · batch BATCH-20260221-0016\nAssigned to Chiller A - Section 1 by warehouse on 2026-02-21 07:43:08', NULL, NULL, NULL),
(37, 26, NULL, 22, 1, 'AnotherTesting', 'flavored_milk', 'Yippe', 'Yippe', 100, 50, 50, 50, 0, 0, 0, 50, 0, 50, 'pcs', NULL, '2026-02-21', '2026-02-27', NULL, 2, 'Chiller A - Section 2', '2026-02-20 23:42:40', '2026-02-21 07:43:11', 3, 'available', 'From packaging run PKG-20260221-004 · batch BATCH-20260221-0016\nAssigned to Chiller A - Section 2 by warehouse on 2026-02-21 07:43:11', NULL, NULL, NULL),
(38, 28, NULL, 21, 1, 'MilkBarBisaya', 'bottled_milk', 'IkawBahala', 'IkawBahala', 50, 20, 20, 20, 0, 0, 0, 20, 0, 20, 'pcs', NULL, '2026-03-28', '2026-04-04', NULL, 1, 'Chiller A - Section 1', '2026-03-28 07:38:29', '2026-03-28 15:39:22', 3, 'available', 'From packaging run PKG-20260328-001 · batch BATCH-20260328-0018\nAssigned to Chiller A - Section 1 by warehouse on 2026-03-28 15:39:22 - Test', NULL, NULL, NULL),
(39, 28, NULL, 21, 1, 'MilkBarBisaya', 'bottled_milk', 'IkawBahala', 'IkawBahala', 50, 20, 20, 20, 0, 0, 0, 20, 0, 20, 'pcs', NULL, '2026-03-28', '2026-04-04', NULL, 2, 'Chiller A - Section 2', '2026-03-28 07:38:46', '2026-03-28 15:39:32', 3, 'available', 'From packaging run PKG-20260328-002 · batch BATCH-20260328-0018\nAssigned to Chiller A - Section 2 by warehouse on 2026-03-28 15:39:32 - Test', NULL, NULL, NULL),
(40, 28, NULL, 21, 1, 'MilkBarBisaya', 'bottled_milk', 'IkawBahala', 'IkawBahala', 50, 20, 20, 20, 0, 0, 0, 20, 0, 20, 'pcs', NULL, '2026-03-28', '2026-04-04', NULL, 1, 'Chiller A - Section 1', '2026-03-28 07:38:54', '2026-03-28 15:39:36', 3, 'available', 'From packaging run PKG-20260328-003 · batch BATCH-20260328-0018\nAssigned to Chiller A - Section 1 by warehouse on 2026-03-28 15:39:36 - Test', NULL, NULL, NULL),
(41, 31, NULL, 21, 1, 'MilkBarBisaya', 'bottled_milk', 'IkawBahala', 'IkawBahala', 50, 5, 5, 5, 0, 0, 0, 5, 0, 5, 'pcs', NULL, '2026-05-03', '2026-05-10', NULL, 1, 'Chiller A - Section 1', '2026-05-03 12:43:27', '2026-05-03 20:44:16', 3, 'available', 'From packaging run PKG-20260503-001 · batch BATCH-20260503-0021\nAssigned to Chiller A - Section 1 by warehouse on 2026-05-03 20:44:16', NULL, NULL, NULL),
(42, 31, NULL, 21, 1, 'MilkBarBisaya', 'bottled_milk', 'IkawBahala', 'IkawBahala', 50, 95, 95, 95, 0, 0, 0, 95, 0, 95, 'pcs', NULL, '2026-05-03', '2026-05-10', NULL, 2, 'Chiller A - Section 2', '2026-05-03 12:43:57', '2026-05-03 20:44:19', 3, 'available', 'From packaging run PKG-20260503-002 · batch BATCH-20260503-0021\nAssigned to Chiller A - Section 2 by warehouse on 2026-05-03 20:44:19', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `gm_pending_approvals`
-- (See below for the actual view)
--
CREATE TABLE `gm_pending_approvals` (
`type` varchar(11)
,`id` int(11)
,`reference_code` varchar(30)
,`description` varchar(291)
,`amount` decimal(12,2)
,`requested_by` varchar(100)
,`requested_at` timestamp
,`status` varchar(9)
);

-- --------------------------------------------------------

--
-- Table structure for table `ingredients`
--

CREATE TABLE `ingredients` (
  `id` int(11) NOT NULL,
  `ingredient_code` varchar(30) NOT NULL,
  `ingredient_name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `minimum_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_point` decimal(10,2) DEFAULT 0.00,
  `lead_time_days` int(11) DEFAULT 7,
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reserved_stock` decimal(10,2) DEFAULT 0.00,
  `available_stock` decimal(10,2) GENERATED ALWAYS AS (`current_stock` - `reserved_stock`) STORED,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `market_price` decimal(12,2) DEFAULT NULL,
  `last_price_update` date DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `storage_requirements` text DEFAULT NULL,
  `shelf_life_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `ingredient_code`, `ingredient_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `reorder_point`, `lead_time_days`, `current_stock`, `reserved_stock`, `unit_cost`, `market_price`, `last_price_update`, `storage_location`, `storage_requirements`, `shelf_life_days`, `is_active`, `created_at`, `updated_at`) VALUES
(9, 'ING-001', 'Sugar', 1, 'kg', 50.00, 75.00, 3, 200.00, 0.00, 45.00, NULL, NULL, 'Dry Storage A', 'Cool dry place', 365, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(10, 'ING-002', 'Vanilla Extract', 2, 'liter', 5.00, 8.00, 7, 15.00, 0.00, 850.00, NULL, NULL, 'Cold Storage', 'Refrigerated', 180, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(11, 'ING-003', 'Chocolate Powder X', 2, 'kg', 20.00, 30.00, 5, 30.00, 0.00, 320.00, NULL, NULL, 'Dry Storage A', 'Cool dry place', 270, 1, '2026-02-03 08:50:32', '2026-05-03 14:15:37'),
(12, 'ING-004', 'Stabilizer', 3, 'kg', 10.00, 15.00, 7, 40.00, 0.00, 480.00, NULL, NULL, 'Dry Storage B', 'Cool dry place', 365, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(13, 'ING-005', 'Cultures (Yogurt)', 4, 'packet', 20.00, 30.00, 14, 25.00, 0.00, 150.00, NULL, NULL, 'Freezer', 'Frozen -18C', 90, 1, '2026-02-03 08:50:32', '2026-05-03 12:24:46'),
(14, 'ING-006', 'Salt', 3, 'kg', 25.00, 40.00, 3, 40.00, 0.00, 25.00, NULL, NULL, 'Dry Storage A', 'Cool dry place', 730, 1, '2026-02-03 08:50:32', '2026-05-02 13:07:17'),
(15, 'ING-007', 'Rennet', 4, 'liter', 3.00, 5.00, 14, 1.00, 0.00, 1200.00, NULL, NULL, 'Cold Storage', 'Refrigerated', 180, 1, '2026-02-03 08:50:32', '2026-05-01 14:21:03'),
(16, 'ING-008', 'Food Coloring', 3, 'liter', 2.00, 4.00, 5, 2.00, 0.00, 350.00, NULL, NULL, 'Dry Storage B', 'Cool dry place', 365, 1, '2026-02-03 08:50:32', '2026-05-03 14:30:13');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_batches`
--

CREATE TABLE `ingredient_batches` (
  `id` int(11) NOT NULL,
  `batch_code` varchar(30) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL COMMENT 'Purchase order reference',
  `quantity` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_batch_no` varchar(50) DEFAULT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `qc_status` enum('pending','approved','rejected','quarantine') DEFAULT 'pending',
  `qc_tested_by` int(11) DEFAULT NULL,
  `qc_tested_at` datetime DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `status` enum('quarantine','available','partially_used','consumed','expired','returned') NOT NULL DEFAULT 'quarantine',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredient_batches`
--

INSERT INTO `ingredient_batches` (`id`, `batch_code`, `ingredient_id`, `po_id`, `quantity`, `remaining_quantity`, `unit_cost`, `supplier_id`, `supplier_batch_no`, `received_date`, `expiry_date`, `qc_status`, `qc_tested_by`, `qc_tested_at`, `received_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'IB-PO28-20260503-213', 11, 28, 20.00, 20.00, 320.00, 5, NULL, '2026-05-03', '2026-05-05', 'approved', NULL, NULL, 4, 'available', 'Received from PO#5258 | Condition: acceptable', '2026-05-03 12:14:18', '2026-05-03 12:14:18'),
(2, 'IB-PO29-20260503-648', 13, 29, 15.00, 15.00, 150.00, 1, NULL, '2026-05-03', '2026-05-04', 'approved', NULL, NULL, 4, 'available', 'Received from PO#5259 | Condition: acceptable', '2026-05-03 12:24:46', '2026-05-03 12:24:46'),
(3, 'IB-PO30-20260503-930', 11, 30, 8.00, 8.00, 320.00, 5, NULL, '2026-05-03', '2026-05-01', 'approved', NULL, NULL, 4, 'available', 'Received from PO#5260 | Condition: acceptable', '2026-05-03 14:08:06', '2026-05-03 14:08:06'),
(4, 'IB-PO31-20260503-043', 11, 31, 2.00, 2.00, 320.00, 4, NULL, '2026-05-03', '2026-05-30', 'approved', NULL, NULL, 4, 'available', 'Received from PO#5261 | Supplier lot: 5 | Condition: acceptable', '2026-05-03 14:15:37', '2026-05-03 14:15:37'),
(5, 'IB-PO32-20260503-264', 16, 32, 1.00, 1.00, 350.00, 3, NULL, '2026-05-03', '2026-05-11', 'approved', NULL, NULL, 4, 'available', 'Received from PO#5262 | Supplier lot: 1 | Condition: acceptable', '2026-05-03 14:30:13', '2026-05-03 14:30:13');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_categories`
--

CREATE TABLE `ingredient_categories` (
  `id` int(11) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredient_categories`
--

INSERT INTO `ingredient_categories` (`id`, `category_code`, `category_name`, `description`, `is_active`, `created_at`) VALUES
(1, 'SWEET', 'Sweeteners', 'Sugar and other sweeteners', 1, '2026-02-03 08:50:32'),
(2, 'FLAVOR', 'Flavorings', 'Vanilla, chocolate, and other flavorings', 1, '2026-02-03 08:50:32'),
(3, 'ADDIT', 'Additives', 'Stabilizers, salt, colorings', 1, '2026-02-03 08:50:32'),
(4, 'CULT', 'Cultures & Enzymes', 'Yogurt cultures, rennet, etc.', 1, '2026-02-03 08:50:32');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_consumption`
--

CREATE TABLE `ingredient_consumption` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
  `ingredient_name` varchar(100) DEFAULT NULL,
  `quantity_used` decimal(10,3) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `batch_code` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredient_consumption`
--

INSERT INTO `ingredient_consumption` (`id`, `run_id`, `ingredient_id`, `ingredient_name`, `quantity_used`, `unit`, `batch_code`, `notes`, `created_at`) VALUES
(1, 7, 11, 'Chocolate Powder', 0.500, 'kg', 'BATCH-20260209-0007', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-09 13:14:01'),
(2, 7, 9, 'Sugar', 0.800, 'kg', 'BATCH-20260209-0007', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-09 13:14:01'),
(3, 7, 12, 'Stabilizer', 0.050, 'kg', 'BATCH-20260209-0007', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-09 13:14:01'),
(4, 8, 9, 'Sugar', 0.500, 'kg', 'BATCH-20260211-0008', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-10 18:29:35'),
(5, 8, 10, 'Vanilla Extract', 0.020, 'L', 'BATCH-20260211-0008', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-10 18:29:35'),
(6, 11, 9, 'Sugar', 0.500, 'kg', 'BATCH-20260211-0011', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-11 04:02:41'),
(7, 11, 10, 'Vanilla Extract', 0.020, 'L', 'BATCH-20260211-0011', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-11 04:02:41'),
(8, 12, 9, 'Sugar', 0.500, 'kg', 'BATCH-20260220-0012', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-20 08:47:52'),
(9, 12, 10, 'Vanilla Extract', 0.020, 'L', 'BATCH-20260220-0012', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-20 08:47:52'),
(10, 14, 15, 'Rennet', 1.000, 'liter', 'BATCH-20260221-0014', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-20 17:20:23'),
(11, 14, 11, 'Chocolate Powder X', 1.000, 'kg', 'BATCH-20260221-0014', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-20 17:20:23'),
(12, 15, 15, 'Rennet', 50.000, 'liter', 'BATCH-20260221-0015', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-20 23:30:16'),
(13, 16, 15, 'Rennet', 50.000, 'liter', 'BATCH-20260221-0016', 'Auto-recorded on run completion. Scale factor: 1', '2026-02-20 23:40:14'),
(14, 17, 15, 'Rennet', 1.000, 'liter', 'BATCH-20260328-0017', 'Auto-recorded on run completion. Scale factor: 1', '2026-03-28 07:32:04'),
(15, 17, 11, 'Chocolate Powder X', 1.000, 'kg', 'BATCH-20260328-0017', 'Auto-recorded on run completion. Scale factor: 1', '2026-03-28 07:32:04'),
(16, 18, 15, 'Rennet', 1.000, 'liter', 'BATCH-20260328-0018', 'Auto-recorded on run completion. Scale factor: 1', '2026-03-28 07:37:09'),
(17, 18, 11, 'Chocolate Powder X', 1.000, 'kg', 'BATCH-20260328-0018', 'Auto-recorded on run completion. Scale factor: 1', '2026-03-28 07:37:09'),
(18, 19, 15, 'Rennet', 1.000, 'liter', 'BATCH-20260426-0019', 'Auto-recorded on run completion. Scale factor: 1', '2026-04-26 09:35:50'),
(19, 19, 11, 'Chocolate Powder X', 1.000, 'kg', 'BATCH-20260426-0019', 'Auto-recorded on run completion. Scale factor: 1', '2026-04-26 09:35:50'),
(20, 20, 15, 'Rennet', 1.000, 'liter', 'BATCH-20260427-0020', 'Auto-recorded on run completion. Scale factor: 1', '2026-04-27 07:08:16'),
(21, 20, 11, 'Chocolate Powder X', 1.000, 'kg', 'BATCH-20260427-0020', 'Auto-recorded on run completion. Scale factor: 1', '2026-04-27 07:08:16'),
(22, 21, 15, 'Rennet', 1.000, 'liter', 'BATCH-20260503-0021', 'Auto-recorded on run completion. Scale factor: 1', '2026-05-03 12:40:14'),
(23, 21, 11, 'Chocolate Powder X', 1.000, 'kg', 'BATCH-20260503-0021', 'Auto-recorded on run completion. Scale factor: 1', '2026-05-03 12:40:14');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_price_history`
--

CREATE TABLE `ingredient_price_history` (
  `id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `old_price` decimal(12,2) NOT NULL,
  `new_price` decimal(12,2) NOT NULL,
  `price_change` decimal(12,2) GENERATED ALWAYS AS (`new_price` - `old_price`) STORED,
  `change_percent` decimal(5,2) GENERATED ALWAYS AS ((`new_price` - `old_price`) / `old_price` * 100) STORED,
  `po_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` bigint(20) NOT NULL,
  `transaction_code` varchar(30) NOT NULL,
  `transaction_type` enum('po_receive','supplier_return','qc_approve','qc_reject','production_issue','production_return','production_output','sales_release','customer_return','physical_adjust','transfer','dispose') NOT NULL,
  `item_type` enum('raw_milk','pasteurized_milk','ingredient','packaging','mro','finished_good') NOT NULL,
  `item_id` int(11) NOT NULL COMMENT 'ID in the respective item table',
  `batch_id` int(11) DEFAULT NULL COMMENT 'Specific batch affected',
  `quantity` decimal(12,3) NOT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `quantity_before` decimal(12,3) DEFAULT NULL COMMENT 'Stock before transaction',
  `quantity_after` decimal(12,3) DEFAULT NULL COMMENT 'Stock after transaction',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g., purchase_order, requisition, sales_order',
  `reference_id` int(11) DEFAULT NULL,
  `from_location` varchar(100) DEFAULT NULL,
  `to_location` varchar(100) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(12,2) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL COMMENT 'For adjustments requiring approval',
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `transaction_code`, `transaction_type`, `item_type`, `item_id`, `batch_id`, `quantity`, `unit_of_measure`, `quantity_before`, `quantity_after`, `reference_type`, `reference_id`, `from_location`, `to_location`, `unit_cost`, `total_cost`, `performed_by`, `approved_by`, `reason`, `created_at`) VALUES
(1, 'TX-746180', 'transfer', 'raw_milk', 1, 1, 50.000, 'L', NULL, NULL, 'tank_assignment', 1, NULL, 'PRT-001', NULL, NULL, 4, NULL, 'Basta', '2026-02-05 05:41:35'),
(2, 'TX-480672', '', 'mro', 1, 1, 1.000, 'set', NULL, NULL, 'requisition', 2, 'Shelf A1', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 05:42:49'),
(3, 'TX-184772', '', 'mro', 7, 7, 2.000, 'liter', NULL, NULL, 'requisition', 2, 'Lube Room', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 05:42:49'),
(4, 'TX-149422', '', 'mro', 2, NULL, 1.000, 'pcs', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 21.00, New: 22)', '2026-02-05 05:46:07'),
(5, 'TX-344271', '', 'raw_milk', 1, 1, 50.000, 'L', NULL, NULL, 'requisition', 4, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 05:59:12'),
(6, 'TX-349870', 'transfer', 'raw_milk', 2, 2, 50.000, 'L', NULL, NULL, 'tank_assignment', 2, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-02-05 06:03:31'),
(7, 'TX-234313', '', 'raw_milk', 2, 2, 50.000, 'L', NULL, NULL, 'requisition', 4, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 06:05:17'),
(8, 'TX-394739', '', 'raw_milk', 3, 3, 50.000, 'L', NULL, NULL, 'requisition', 6, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 06:10:16'),
(9, 'TX-892333', '', 'raw_milk', 3, 3, 50.000, 'L', NULL, NULL, 'requisition', 5, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 10:25:26'),
(10, 'TX-848918', '', 'raw_milk', 3, 3, 100.000, 'L', NULL, NULL, 'requisition', 7, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 10:25:32'),
(11, 'TX-240119', 'transfer', 'raw_milk', 4, 4, 50.000, 'L', NULL, NULL, 'tank_assignment', 4, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-02-05 10:56:49'),
(12, 'TX-501848', 'transfer', 'raw_milk', 5, 5, 50.000, 'L', NULL, NULL, 'tank_assignment', 5, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-02-05 10:58:11'),
(13, 'TX-619053', '', 'raw_milk', 4, 4, 50.000, 'L', NULL, NULL, 'requisition', 8, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 10:59:03'),
(14, 'TX-766387', 'transfer', 'raw_milk', 6, 6, 100.000, 'L', NULL, NULL, 'tank_assignment', 6, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-02-09 13:10:49'),
(15, 'TX-111431', '', 'raw_milk', 6, 6, 50.000, 'L', NULL, NULL, 'requisition', 9, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-09 13:11:11'),
(16, 'TX-375243', 'transfer', 'raw_milk', 7, 7, 50.000, 'L', NULL, NULL, 'tank_assignment', 7, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-02-10 18:27:14'),
(17, 'TX-823521', '', 'raw_milk', 6, 6, 50.000, 'L', NULL, NULL, 'requisition', 10, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-10 18:28:39'),
(18, 'TX-270708', 'transfer', 'raw_milk', 9, 9, 500.000, 'L', NULL, NULL, 'tank_assignment', 9, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-02-10 18:53:32'),
(19, 'TX-184022', 'transfer', 'raw_milk', 8, 8, 50.000, 'L', NULL, NULL, 'tank_assignment', 8, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-02-10 18:53:34'),
(20, 'TX-907578', '', 'raw_milk', 7, 7, 50.000, 'L', NULL, NULL, 'requisition', 11, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-10 18:55:42'),
(21, 'TX-041807', '', 'raw_milk', 8, 8, 50.000, 'L', NULL, NULL, 'requisition', 11, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-10 18:55:42'),
(22, 'TX-154694', '', 'raw_milk', 9, 9, 400.000, 'L', NULL, NULL, 'requisition', 11, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-10 18:55:42'),
(23, 'TX-495050', 'transfer', 'raw_milk', 11, 11, 40.000, 'L', NULL, NULL, 'tank_assignment', 11, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-03-28 07:16:36'),
(24, 'TX-174228', 'transfer', 'raw_milk', 10, 10, 50.000, 'L', NULL, NULL, 'tank_assignment', 10, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-03-28 07:16:50'),
(25, 'TX-037109', 'transfer', 'raw_milk', 12, 12, 112.000, 'L', NULL, NULL, 'tank_assignment', 12, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-03-28 07:22:54'),
(26, 'TX-929682', 'transfer', 'raw_milk', 25, 25, 401.000, 'L', NULL, NULL, 'tank_assignment', 25, NULL, 'PT-001', NULL, NULL, 4, NULL, 'wqe', '2026-03-28 07:23:27'),
(27, 'TX-855609', 'transfer', 'raw_milk', 13, 13, 93.000, 'L', NULL, NULL, 'tank_assignment', 13, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-03-28 07:24:50'),
(28, 'TX-078139', 'transfer', 'raw_milk', 14, 14, 59.000, 'L', NULL, NULL, 'tank_assignment', 14, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-03-28 07:25:17'),
(29, 'TX-917642', 'transfer', 'raw_milk', 15, 15, 40.000, 'L', NULL, NULL, 'tank_assignment', 15, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-03-28 07:27:29'),
(30, 'TX-615960', 'transfer', 'raw_milk', 27, 27, 50.000, 'L', NULL, NULL, 'tank_assignment', 27, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-03-28 07:30:27'),
(31, 'TX-707225', 'transfer', 'raw_milk', 28, 28, 50.000, 'L', NULL, NULL, 'tank_assignment', 28, NULL, 'PRT-001', NULL, NULL, 4, NULL, 'Test', '2026-03-28 07:35:21'),
(32, 'TX-268484', 'transfer', 'raw_milk', 16, 16, 598.000, 'L', NULL, NULL, 'tank_assignment', 16, NULL, 'PRT-001', NULL, NULL, 4, NULL, '', '2026-04-26 09:34:28'),
(33, 'TX-193581', 'physical_adjust', 'ingredient', 16, NULL, -7.000, 'liter', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 8.00, New: 1)', '2026-05-01 08:39:13'),
(34, 'TX-817788', 'physical_adjust', 'ingredient', 13, NULL, -20.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 60.00, New: 40)', '2026-05-01 14:05:22'),
(35, 'TX-773293', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 40.00, New: 40)', '2026-05-01 14:05:25'),
(36, 'TX-756184', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 40.00, New: 40)', '2026-05-01 14:05:25'),
(37, 'TX-453597', 'physical_adjust', 'ingredient', 13, NULL, -10.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 40.00, New: 30)', '2026-05-01 14:07:32'),
(38, 'TX-096046', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 30.00, New: 30)', '2026-05-01 14:07:33'),
(39, 'TX-711440', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 30.00, New: 30)', '2026-05-01 14:07:33'),
(40, 'TX-500723', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 30.00, New: 30)', '2026-05-01 14:07:39'),
(41, 'TX-762288', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 30.00, New: 30)', '2026-05-01 14:07:40'),
(42, 'TX-860686', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 30.00, New: 30)', '2026-05-01 14:07:40'),
(43, 'TX-092064', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 30.00, New: 30)', '2026-05-01 14:08:18'),
(44, 'TX-868624', 'physical_adjust', 'ingredient', 13, NULL, -20.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 30, New: 10)', '2026-05-01 14:10:31'),
(45, 'TX-055493', 'physical_adjust', 'ingredient', 13, NULL, 0.000, 'packet', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 10, New: 10)', '2026-05-01 14:10:43'),
(46, 'TX-853627', 'physical_adjust', 'ingredient', 15, NULL, -9.000, 'liter', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 10, New: 1)', '2026-05-01 14:21:03'),
(47, 'TX-492745', 'physical_adjust', 'ingredient', 11, NULL, -5.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 75, New: 70)', '2026-05-02 13:01:32'),
(48, 'TX-610800', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:01:38'),
(49, 'TX-270825', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:01:39'),
(50, 'TX-817550', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:03:00'),
(51, 'TX-105720', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:03:00'),
(52, 'TX-230079', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:03:00'),
(53, 'TX-901721', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:03:00'),
(54, 'TX-871830', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:03:04'),
(55, 'TX-167950', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 70)', '2026-05-02 13:03:06'),
(56, 'TX-339175', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Damage/spoilage (Old: 70, New: 70)', '2026-05-02 13:03:08'),
(57, 'TX-323401', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Damage/spoilage (Old: 70, New: 70)', '2026-05-02 13:03:09'),
(58, 'TX-094625', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Damage/spoilage (Old: 70, New: 70)', '2026-05-02 13:04:35'),
(59, 'TX-157431', 'physical_adjust', 'ingredient', 11, NULL, -20.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 70, New: 50)', '2026-05-02 13:04:48'),
(60, 'TX-584250', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 50, New: 50)', '2026-05-02 13:04:49'),
(61, 'TX-176101', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 50, New: 50)', '2026-05-02 13:04:50'),
(62, 'TX-627572', 'physical_adjust', 'ingredient', 11, NULL, -30.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 50, New: 20)', '2026-05-02 13:04:59'),
(63, 'TX-112083', 'physical_adjust', 'ingredient', 11, NULL, -10.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 20, New: 10)', '2026-05-02 13:05:52'),
(64, 'TX-384627', 'physical_adjust', 'ingredient', 11, NULL, -5.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 10, New: 5)', '2026-05-02 13:06:24'),
(65, 'TX-063427', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 5, New: 5)', '2026-05-02 13:06:28'),
(66, 'TX-513647', 'physical_adjust', 'ingredient', 11, NULL, 0.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 5, New: 5)', '2026-05-02 13:06:30'),
(67, 'TX-547530', 'physical_adjust', 'ingredient', 14, NULL, -60.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 100, New: 40)', '2026-05-02 13:07:17'),
(68, 'TX-600002', 'po_receive', 'mro', 2, 9, 1.000, 'pcs', NULL, NULL, 'purchase_order', 26, NULL, 'Shelf A2', NULL, NULL, 4, NULL, 'Received from PO#5256', '2026-05-03 06:23:40'),
(69, 'TX-980175', 'po_receive', 'ingredient', 11, 1, 20.000, 'kg', NULL, NULL, 'purchase_order', 28, NULL, 'Dry Storage A', NULL, NULL, 4, NULL, 'Received from PO#5258', '2026-05-03 12:14:18'),
(70, 'TX-721015', 'po_receive', 'ingredient', 13, 2, 15.000, 'packet', NULL, NULL, 'purchase_order', 29, NULL, 'Freezer', NULL, NULL, 4, NULL, 'Received from PO#5259', '2026-05-03 12:24:46'),
(71, 'TX-671441', 'physical_adjust', 'ingredient', 11, NULL, -5.000, 'kg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, NULL, 'Stock adjustment: Physical count correction (Old: 25, New: 20)', '2026-05-03 12:32:37'),
(72, 'TX-468306', 'po_receive', 'ingredient', 11, 3, 8.000, 'kg', NULL, NULL, 'purchase_order', 30, NULL, 'Dry Storage A', NULL, NULL, 4, NULL, 'Received from PO#5260', '2026-05-03 14:08:06'),
(73, 'TX-735752', 'po_receive', 'ingredient', 11, 4, 2.000, 'kg', NULL, NULL, 'purchase_order', 31, NULL, 'Dry Storage A', NULL, NULL, 4, NULL, 'Received from PO#5261', '2026-05-03 14:15:37'),
(74, 'TX-231698', 'po_receive', 'ingredient', 16, 5, 1.000, 'liter', NULL, NULL, 'purchase_order', 32, NULL, 'Dry Storage B', NULL, NULL, 4, NULL, 'Received from PO#5262', '2026-05-03 14:30:13');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `first_failed_at` datetime DEFAULT NULL,
  `last_failed_at` datetime DEFAULT NULL,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `failed_attempts`, `first_failed_at`, `last_failed_at`, `locked_until`, `created_at`, `updated_at`) VALUES
(2, 'yawa_world', '::1', 1, '2026-03-13 20:06:51', '2026-03-13 20:06:51', NULL, '2026-03-13 12:06:51', '2026-03-13 12:06:51'),
(3, 'admin', '::1', 4, '2026-03-14 13:56:37', '2026-03-14 13:56:55', NULL, '2026-03-14 05:56:37', '2026-03-14 05:56:55'),
(4, 'sales_staff', '::1', 1, '2026-03-28 15:39:54', '2026-03-28 15:39:54', NULL, '2026-03-28 07:39:54', '2026-03-28 07:39:54'),
(7, 'general_manager@gmail.com', '::1', 2, '2026-05-03 16:28:18', '2026-05-03 22:06:18', NULL, '2026-04-27 06:16:55', '2026-05-03 14:06:18'),
(8, 'warehouse.raw', '::1', 1, '2026-04-27 15:08:58', '2026-04-27 15:08:58', NULL, '2026-04-27 07:08:58', '2026-04-27 07:08:58'),
(9, 'warehouse_fg@gmail.com', '::1', 2, '2026-04-27 15:21:35', '2026-04-27 15:23:12', NULL, '2026-04-27 07:21:35', '2026-04-27 07:23:12'),
(10, 'gewarehouse.fg@gmail.com', '::1', 1, '2026-05-03 16:35:17', '2026-05-03 16:35:17', NULL, '2026-05-03 08:35:17', '2026-05-03 08:35:17'),
(11, 'brid.ragasi.coc@phinmaed.com', '::1', 1, '2026-05-03 19:56:25', '2026-05-03 19:56:25', NULL, '2026-05-03 11:56:25', '2026-05-03 11:56:25');

-- --------------------------------------------------------

--
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` int(11) NOT NULL,
  `machine_code` varchar(30) NOT NULL,
  `machine_name` varchar(150) NOT NULL,
  `machine_type` enum('pasteurizer','homogenizer','retort','fill_seal','tank','pump','chiller','other') NOT NULL,
  `manufacturer` varchar(150) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL COMMENT 'Physical location in plant',
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `status` enum('operational','needs_maintenance','under_repair','offline','decommissioned') DEFAULT 'operational',
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_due` date DEFAULT NULL,
  `maintenance_interval_days` int(11) DEFAULT 30 COMMENT 'Scheduled maintenance interval',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `machine_code`, `machine_name`, `machine_type`, `manufacturer`, `model_number`, `serial_number`, `location`, `purchase_date`, `warranty_expiry`, `status`, `last_maintenance_date`, `next_maintenance_due`, `maintenance_interval_days`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'MCH-PAST-001', 'Primary Pasteurizer', 'pasteurizer', NULL, NULL, NULL, 'Processing Area A', NULL, NULL, 'operational', NULL, NULL, 14, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(2, 'MCH-HOMO-001', 'Main Homogenizer', 'homogenizer', NULL, NULL, NULL, 'Processing Area A', NULL, NULL, 'operational', NULL, NULL, 30, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(3, 'MCH-RTRT-001', 'Retort Machine #1', 'retort', NULL, NULL, NULL, 'Sterilization Room', NULL, NULL, 'operational', NULL, NULL, 7, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(4, 'MCH-FILL-001', 'Fill-Seal Machine #1', 'fill_seal', NULL, NULL, NULL, 'Packaging Area', NULL, NULL, 'operational', NULL, NULL, 14, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(5, 'MCH-FILL-002', 'Fill-Seal Machine #2', 'fill_seal', NULL, NULL, NULL, 'Packaging Area', NULL, NULL, 'operational', NULL, NULL, 14, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(6, 'MCH-TANK-001', 'Storage Tank A', 'tank', NULL, NULL, NULL, 'Cold Storage', NULL, NULL, 'operational', NULL, NULL, 30, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(7, 'MCH-TANK-002', 'Storage Tank B', 'tank', NULL, NULL, NULL, 'Cold Storage', NULL, NULL, 'operational', NULL, NULL, 30, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(8, 'MCH-PUMP-001', 'Transfer Pump #1', 'pump', NULL, NULL, NULL, 'Processing Area', NULL, NULL, 'operational', NULL, NULL, 30, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31'),
(9, 'MCH-CHLR-001', 'Main Chiller Unit', 'chiller', NULL, NULL, NULL, 'Utility Room', NULL, NULL, 'operational', NULL, NULL, 30, NULL, 1, '2026-02-10 17:15:31', '2026-02-10 17:15:31');

-- --------------------------------------------------------

--
-- Table structure for table `machine_repairs`
--

CREATE TABLE `machine_repairs` (
  `id` int(11) NOT NULL,
  `repair_code` varchar(30) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `repair_type` enum('preventive','corrective','emergency','inspection') NOT NULL DEFAULT 'corrective',
  `priority` enum('low','normal','high','critical') DEFAULT 'normal',
  `issue_description` text NOT NULL COMMENT 'What was the problem?',
  `diagnosis` text DEFAULT NULL COMMENT 'Root cause analysis',
  `repair_actions` text DEFAULT NULL COMMENT 'What was done to fix it?',
  `status` enum('reported','diagnosed','in_progress','awaiting_parts','completed','cancelled') DEFAULT 'reported',
  `reported_by` int(11) NOT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `downtime_hours` decimal(6,2) DEFAULT NULL COMMENT 'How long was machine down?',
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `parts_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`labor_cost` + `parts_cost`) STORED,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requisitions`
--

CREATE TABLE `maintenance_requisitions` (
  `id` int(11) NOT NULL,
  `requisition_code` varchar(30) NOT NULL,
  `repair_id` int(11) DEFAULT NULL COMMENT 'Link to repair if requesting for specific repair',
  `machine_id` int(11) DEFAULT NULL COMMENT 'Link to machine if for scheduled maintenance',
  `status` enum('pending','approved','rejected','fulfilled','partially_fulfilled','cancelled') DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `purpose` text DEFAULT NULL COMMENT 'Why are these parts needed?',
  `needed_by_date` datetime DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_requisitions`
--

INSERT INTO `maintenance_requisitions` (`id`, `requisition_code`, `repair_id`, `machine_id`, `status`, `priority`, `purpose`, `needed_by_date`, `requested_by`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`, `fulfilled_by`, `fulfilled_at`, `total_items`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'MRO-20260211-001', NULL, 3, 'pending', 'normal', '', NULL, 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, NULL, '2026-02-10 18:05:56', '2026-02-10 18:05:56');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requisition_items`
--

CREATE TABLE `maintenance_requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `mro_item_id` int(11) NOT NULL,
  `requested_quantity` decimal(10,2) NOT NULL,
  `approved_quantity` decimal(10,2) DEFAULT NULL,
  `issued_quantity` decimal(10,2) DEFAULT NULL,
  `unit_of_measure` varchar(20) DEFAULT 'pcs',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_requisition_items`
--

INSERT INTO `maintenance_requisition_items` (`id`, `requisition_id`, `mro_item_id`, `requested_quantity`, `approved_quantity`, `issued_quantity`, `unit_of_measure`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1.00, NULL, NULL, 'pcs', '', '2026-02-10 18:05:56', '2026-02-10 18:05:56'),
(2, 1, 1, 1.00, NULL, NULL, 'pcs', '', '2026-02-10 18:05:56', '2026-02-10 18:05:56');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedules`
--

CREATE TABLE `maintenance_schedules` (
  `id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `schedule_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `frequency_type` enum('daily','weekly','monthly','quarterly','yearly','hours_based') DEFAULT 'monthly',
  `frequency_value` int(11) DEFAULT 1 COMMENT 'Every X days/weeks/months or hours',
  `last_performed` date DEFAULT NULL,
  `next_due` date DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_recipes`
--

CREATE TABLE `master_recipes` (
  `id` int(11) NOT NULL,
  `recipe_code` varchar(30) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar','cream','flavored_milk') NOT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `milk_type_id` int(11) NOT NULL COMMENT 'Required milk type - cow, goat, or mixed',
  `description` text DEFAULT NULL,
  `base_milk_liters` decimal(10,2) NOT NULL,
  `expected_yield` int(11) NOT NULL,
  `yield_unit` varchar(20) DEFAULT 'units',
  `shelf_life_days` int(11) NOT NULL DEFAULT 7,
  `pasteurization_temp` decimal(5,2) DEFAULT 81.00,
  `pasteurization_time_mins` int(11) DEFAULT 15,
  `cooling_temp` decimal(5,2) DEFAULT 4.00,
  `special_instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_recipes`
--

INSERT INTO `master_recipes` (`id`, `recipe_code`, `product_id`, `product_name`, `product_type`, `variant`, `milk_type_id`, `description`, `base_milk_liters`, `expected_yield`, `yield_unit`, `shelf_life_days`, `pasteurization_temp`, `pasteurization_time_mins`, `cooling_temp`, `special_instructions`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(12, 'RCP-FM-1L', 1, 'Fresh Milk', 'bottled_milk', '1 Liter', 1, NULL, 100.00, 95, 'bottles', 7, 75.00, 15, 4.00, 'Standard pasteurization process for 1L fresh milk', 1, 1, '2026-02-03 09:22:39', '2026-02-20 15:49:13'),
(13, 'RCP-FM-500', 2, 'Fresh Milk', 'bottled_milk', '500ml', 1, NULL, 100.00, 190, 'bottles', 7, 75.00, 15, 4.00, 'Standard pasteurization process for 500ml fresh milk', 1, 1, '2026-02-03 09:22:39', '2026-02-20 15:49:13'),
(14, 'RCP-CHO-1L', 3, 'Chocolate Milk', 'bottled_milk', '1 Liter', 1, NULL, 100.00, 92, 'bottles', 7, 75.00, 15, 4.00, 'Add chocolate powder after pasteurization', 1, 1, '2026-02-03 09:22:39', '2026-02-20 15:49:13'),
(15, 'RCP-YOG-500', 4, 'Plain Yogurt', 'yogurt', '500g', 1, NULL, 100.00, 180, 'cups', 14, 85.00, 30, 43.00, 'Fermentation for 6-8 hours at 43C', 1, 1, '2026-02-03 09:22:39', '2026-02-20 15:49:13'),
(16, 'RCP-CHE-250', 6, 'Kesong Puti', 'cheese', '250g', 1, NULL, 100.00, 35, 'packs', 21, 75.00, 15, 35.00, 'Add rennet and cultures, age for 24 hours', 1, 1, '2026-02-03 09:22:39', '2026-02-20 15:49:13'),
(17, 'RCP-BUT-250', 7, 'Butter', 'butter', '250g', 1, '', 100.00, 20, '', 30, 75.00, 15, 10.00, 'Churn cream until butter forms, wash and shap', 1, 1, '2026-02-03 09:22:39', '2026-02-20 15:49:13'),
(18, 'RCP-CRM-001', 8, 'Fresh Cream', 'cream', NULL, 1, 'Fresh cream separated from whole milk through centrifugation. Pasteurized for safety.', 10.00, 1, 'units', 5, 72.00, 15, 4.00, 'Separate cream at 40-50??C for best results. Adjust separator for 35-40% fat content. Cool immediately after pasteurization.', 1, 1, '2026-02-10 18:44:56', '2026-02-20 15:49:13'),
(19, 'RCP-0019', NULL, 'Basta', 'yogurt', NULL, 1, 'Basta', 10.00, 95, 'kg', 7, 81.00, 15, 4.00, 'Basta', 0, 8, '2026-02-20 15:56:53', '2026-02-20 16:11:33'),
(20, 'RCP-0020', NULL, 'Basta', 'flavored_milk', NULL, 1, '', 10.00, 95, 'liters', 7, 81.00, 15, 4.00, '', 1, 8, '2026-02-20 16:11:54', '2026-02-20 16:11:54'),
(21, 'RCP-0021', NULL, 'MilkBarBisaya', '', NULL, 1, '', 10.00, 95, 'liters', 7, 81.00, 15, 4.00, '', 1, 8, '2026-02-20 17:19:21', '2026-02-20 17:19:21'),
(22, 'RCP-0022', NULL, 'AnotherTesting', 'flavored_milk', NULL, 1, 'Test', 50.00, 95, 'liters', 7, 81.00, 15, 4.00, 'Basta', 1, 8, '2026-02-20 23:27:52', '2026-02-20 23:27:52'),
(23, 'RCP-0023', NULL, 'Milkbar-UBE', 'flavored_milk', NULL, 2, '', 10.00, 95, 'liters', 7, 81.00, 15, 4.00, '', 1, 8, '2026-02-21 02:59:07', '2026-02-21 02:59:07');

-- --------------------------------------------------------

--
-- Table structure for table `material_requisitions`
--

CREATE TABLE `material_requisitions` (
  `id` int(11) NOT NULL,
  `requisition_code` varchar(30) NOT NULL,
  `production_run_id` int(11) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `department` enum('production','maintenance','other') NOT NULL DEFAULT 'production',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `needed_by_date` datetime DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `status` enum('draft','pending','approved','rejected','partial','fulfilled','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_requisitions`
--

INSERT INTO `material_requisitions` (`id`, `requisition_code`, `production_run_id`, `requested_by`, `department`, `priority`, `needed_by_date`, `purpose`, `total_items`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `fulfilled_by`, `fulfilled_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'REQ-2026-0001', NULL, 2, 'production', 'normal', '2026-02-04 00:00:00', 'Production batch YOG-001', 3, 'partial', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-22 09:14:03'),
(2, 'REQ-2026-0002', NULL, 2, 'maintenance', 'high', '2026-02-03 00:00:00', 'Pasteurizer maintenance', 2, 'fulfilled', 4, '2026-02-05 13:42:43', NULL, 4, '2026-02-05 13:42:49', NULL, '2026-02-03 08:46:55', '2026-02-05 05:42:49'),
(3, 'REQ-2026-0003', NULL, 2, 'production', 'urgent', '2026-02-03 00:00:00', 'Emergency production run', 2, 'partial', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-04-27 07:18:27'),
(4, 'REQ-20260205-001', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 13:52:39', NULL, 4, '2026-02-05 14:05:17', NULL, '2026-02-05 05:47:17', '2026-02-05 06:08:37'),
(5, 'REQ-20260205-002', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 13:55:51', NULL, 4, '2026-02-05 18:25:26', NULL, '2026-02-05 05:54:55', '2026-02-05 10:25:26'),
(6, 'REQ-20260205-003', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 14:10:12', NULL, 4, '2026-02-05 14:10:16', NULL, '2026-02-05 06:09:28', '2026-02-05 06:10:16'),
(7, 'REQ-20260205-004', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 18:25:29', NULL, 4, '2026-02-05 18:25:32', NULL, '2026-02-05 10:24:53', '2026-02-05 10:25:32'),
(8, 'REQ-20260205-005', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 18:59:01', NULL, 4, '2026-02-05 18:59:03', NULL, '2026-02-05 10:58:37', '2026-02-05 10:59:03'),
(9, 'REQ-20260209-001', 6, 3, 'production', 'normal', NULL, 'Basta', 1, 'fulfilled', 4, '2026-02-09 21:09:34', NULL, 4, '2026-02-09 21:11:11', NULL, '2026-02-09 13:09:19', '2026-02-09 13:11:11'),
(10, 'REQ-20260211-001', 7, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-11 02:28:37', NULL, 4, '2026-02-11 02:28:39', NULL, '2026-02-10 18:27:46', '2026-02-10 18:28:39'),
(11, 'REQ-20260211-002', 9, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-11 02:55:39', NULL, 4, '2026-02-11 02:55:42', NULL, '2026-02-10 18:55:14', '2026-02-10 18:55:42');

-- --------------------------------------------------------

--
-- Table structure for table `milk_grading_standards`
--

CREATE TABLE `milk_grading_standards` (
  `id` int(11) NOT NULL,
  `grade_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `fat_min` decimal(5,2) DEFAULT NULL,
  `fat_max` decimal(5,2) DEFAULT NULL,
  `snf_min` decimal(5,2) DEFAULT NULL,
  `snf_max` decimal(5,2) DEFAULT NULL,
  `temperature_max` decimal(5,2) DEFAULT NULL,
  `price_per_liter` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milk_grading_standards`
--

INSERT INTO `milk_grading_standards` (`id`, `grade_name`, `description`, `fat_min`, `fat_max`, `snf_min`, `snf_max`, `temperature_max`, `price_per_liter`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Grade A', 'Premium quality milk with high fat content', 3.50, NULL, 8.50, NULL, 8.00, 55.00, 'active', '2026-02-03 14:43:08', '2026-02-03 14:45:38'),
(2, 'Grade B', 'Standard quality milk', 3.00, 3.49, 8.00, 8.49, 10.00, 50.00, 'active', '2026-02-03 14:43:08', NULL),
(3, 'Grade C', 'Below standard quality', 2.50, 2.99, 7.50, 7.99, 12.00, 45.00, 'active', '2026-02-03 14:43:08', NULL),
(4, 'Rejected', 'Does not meet minimum standards', NULL, 2.49, NULL, 7.49, NULL, 0.00, 'active', '2026-02-03 14:43:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `milk_receiving`
--

CREATE TABLE `milk_receiving` (
  `id` int(11) NOT NULL,
  `receiving_code` varchar(30) NOT NULL,
  `rmr_number` varchar(20) DEFAULT NULL COMMENT 'Raw Milk Receipt Number',
  `farmer_id` int(11) NOT NULL,
  `milk_type_id` int(11) NOT NULL COMMENT 'COW or GOAT - Issue #2',
  `receiving_date` date NOT NULL,
  `receiving_time` time DEFAULT NULL,
  `volume_liters` decimal(10,2) NOT NULL COMMENT 'Total volume delivered',
  `rejected_liters` decimal(10,2) DEFAULT 0.00,
  `accepted_liters` decimal(10,2) DEFAULT 0.00 COMMENT 'Only populated after QC pass',
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `transport_container` varchar(50) DEFAULT NULL COMMENT 'Can, Tank, etc.',
  `visual_inspection` enum('pass','fail','pending') DEFAULT 'pending',
  `visual_notes` text DEFAULT NULL,
  `apt_result` enum('positive','negative') DEFAULT 'negative' COMMENT 'Antibiotic Presence Test result',
  `status` enum('pending_qc','in_testing','accepted','rejected','partial') DEFAULT 'pending_qc',
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milk_receiving`
--

INSERT INTO `milk_receiving` (`id`, `receiving_code`, `rmr_number`, `farmer_id`, `milk_type_id`, `receiving_date`, `receiving_time`, `volume_liters`, `rejected_liters`, `accepted_liters`, `temperature_celsius`, `transport_container`, `visual_inspection`, `visual_notes`, `apt_result`, `status`, `received_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'RCV-2025-10-21-001', '66173', 1, 1, '2025-10-21', '08:00:00', 55.00, 0.00, 55.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(2, 'RCV-2025-10-21-002', '66174', 2, 1, '2025-10-21', '08:00:00', 112.00, 0.00, 112.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱500.00', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(3, 'RCV-2025-10-21-003', '66175', 3, 1, '2025-10-21', '08:00:00', 20.00, 87.00, -67.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱141.51', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(4, 'RCV-2025-10-21-004', '66176', 4, 1, '2025-10-21', '08:00:00', 93.00, 0.00, 93.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱658.02', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(5, 'RCV-2025-10-21-005', '66177', 5, 1, '2025-10-21', '08:00:00', 59.00, 0.00, 59.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱417.45', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(6, 'RCV-2025-10-21-006', '66178', 6, 1, '2025-10-21', '08:00:00', 40.00, 0.00, 40.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱283.02', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(7, 'RCV-2025-10-21-007', '66179', 7, 1, '2025-10-21', '08:00:00', 598.00, 0.00, 598.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(8, 'RCV-2025-10-21-008', '66180', 8, 1, '2025-10-21', '08:00:00', 26.00, 0.00, 26.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(9, 'RCV-2025-10-21-009', '66181', 9, 1, '2025-10-21', '08:00:00', 124.00, 0.00, 124.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(10, 'RCV-2025-10-21-010', '66182', 10, 1, '2025-10-21', '08:00:00', 201.00, 0.00, 201.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱258.35', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(11, 'RCV-2025-10-21-011', '66183', 11, 2, '2025-10-21', '08:00:00', 8.00, 0.00, 8.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱10.28', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(12, 'RCV-2025-10-21-012', '66184', 12, 1, '2025-10-21', '08:00:00', 149.00, 57.00, 92.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱191.52', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(13, 'RCV-2025-10-21-013', '66185', 13, 1, '2025-10-21', '08:00:00', 42.00, 0.00, 42.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱53.98', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(14, 'RCV-2025-10-21-014', '66186', 14, 1, '2025-10-21', '08:00:00', 91.00, 0.00, 91.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱116.97', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(15, 'RCV-2025-10-21-015', '66187', 15, 1, '2025-10-21', '08:00:00', 173.00, 27.00, 146.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱222.37', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(16, 'RCV-2025-10-21-016', '66188', 16, 1, '2025-10-21', '08:00:00', 401.00, 0.00, 401.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱515.42', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(17, 'RCV-2025-10-21-017', '66189', 17, 1, '2025-10-21', '08:00:00', 102.00, 0.00, 102.00, NULL, NULL, 'pass', NULL, 'negative', 'accepted', 4, 'Transport cost: ₱131.11', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(18, 'RCV-20260203-001', '66190', 10, 1, '2026-02-03', '21:44:29', 50.00, 0.00, 0.00, NULL, NULL, 'pending', '', 'negative', 'pending_qc', 2, 'Basta', '2026-02-03 13:44:29', '2026-02-03 13:44:29'),
(19, 'RCV-20260203-002', '66191', 2, 1, '2026-02-03', '21:53:17', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, '', '2026-02-03 13:53:17', '2026-02-03 14:00:16'),
(20, 'RCV-20260205-001', '66192', 3, 1, '2026-02-05', '14:02:57', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Basta', '2026-02-05 06:02:57', '2026-02-05 06:03:13'),
(21, 'RCV-20260205-002', '66193', 3, 1, '2026-02-05', '18:55:59', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, '', '2026-02-05 10:55:59', '2026-02-05 10:56:14'),
(22, 'RCV-20260205-003', '66194', 2, 1, '2026-02-05', '18:57:22', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, '', '2026-02-05 10:57:22', '2026-02-05 10:57:34'),
(23, 'RCV-20260209-001', '66195', 2, 1, '2026-02-09', '21:10:17', 100.00, 0.00, 100.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Test', '2026-02-09 13:10:17', '2026-02-09 13:10:29'),
(24, 'RCV-20260211-001', '66196', 3, 1, '2026-02-11', '02:17:36', 100.00, 0.00, 0.00, NULL, NULL, 'pending', '', 'negative', 'pending_qc', 2, 'YEhey', '2026-02-10 18:17:36', '2026-02-10 18:17:36'),
(25, 'RCV-20260211-002', '66197', 6, 1, '2026-02-11', '02:18:37', 100.00, 0.00, 0.00, NULL, NULL, 'pending', '', 'negative', 'pending_qc', 2, 'Test', '2026-02-10 18:18:37', '2026-02-10 18:18:37'),
(26, 'RCV-20260211-003', '66198', 3, 1, '2026-02-11', '02:19:09', 10.00, 0.00, 0.00, NULL, NULL, 'pending', '', 'negative', 'pending_qc', 2, 'Yeheyt', '2026-02-10 18:19:09', '2026-02-10 18:19:09'),
(27, 'RCV-20260210-001', '66199', 4, 1, '2026-02-10', '02:26:00', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Test', '2026-02-10 18:26:28', '2026-02-10 18:26:40'),
(28, 'RCV-20260210-002', '66200', 8, 1, '2026-02-10', '02:52:00', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Basta', '2026-02-10 18:52:37', '2026-02-10 18:53:03'),
(29, 'RCV-20260210-003', '66201', 3, 1, '2026-02-10', '02:52:00', 500.00, 0.00, 500.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Basta', '2026-02-10 18:52:49', '2026-02-10 18:53:16'),
(30, 'RCV-20260226-001', '66202', 6, 1, '2026-02-26', '23:41:00', 50.00, 0.00, 0.00, NULL, NULL, 'pending', '', 'negative', 'pending_qc', 2, 'Basta', '2026-02-26 15:42:03', '2026-02-26 15:42:03'),
(31, 'RCV-20260313-001', '66203', 3, 1, '2026-03-13', '20:47:00', 50.00, 0.00, 0.00, NULL, NULL, 'pending', '', 'positive', 'pending_qc', 2, 'Basta', '2026-03-13 12:47:13', '2026-03-13 12:47:13'),
(32, 'RCV-20260313-002', '66204', 3, 1, '2026-03-13', '20:47:00', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Basta', '2026-03-13 12:47:47', '2026-03-13 12:47:58'),
(33, 'RCV-20260328-001', '66205', 4, 1, '2026-03-28', '15:15:00', 40.00, 0.00, 40.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Test', '2026-03-28 07:15:44', '2026-03-28 07:15:59'),
(34, 'RCV-20260328-002', '66206', 4, 1, '2026-03-28', '15:29:00', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Test', '2026-03-28 07:29:14', '2026-03-28 07:29:45'),
(35, 'RCV-20260328-003', '66207', 1, 1, '2026-03-28', '15:34:00', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, 'Test', '2026-03-28 07:34:24', '2026-03-28 07:34:42'),
(36, 'RCV-20260426-001', '66208', 1, 1, '2026-04-26', '17:33:00', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'negative', 'accepted', 2, '', '2026-04-26 09:33:31', '2026-04-26 09:33:41');

-- --------------------------------------------------------

--
-- Table structure for table `milk_types`
--

CREATE TABLE `milk_types` (
  `id` int(11) NOT NULL,
  `type_code` varchar(10) NOT NULL COMMENT 'COW, GOAT',
  `type_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price_per_liter` decimal(10,2) NOT NULL DEFAULT 40.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milk_types`
--

INSERT INTO `milk_types` (`id`, `type_code`, `type_name`, `description`, `base_price_per_liter`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'COW', 'Cow Milk', 'Fresh cow milk from local farmers', 40.00, 1, '2026-02-03 07:05:51', '2026-02-03 07:05:51'),
(2, 'GOAT', 'Goat Milk', 'Fresh goat milk - premium pricing', 70.00, 1, '2026-02-03 07:05:51', '2026-02-03 07:05:51');

-- --------------------------------------------------------

--
-- Table structure for table `mro_categories`
--

CREATE TABLE `mro_categories` (
  `id` int(11) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mro_categories`
--

INSERT INTO `mro_categories` (`id`, `category_code`, `category_name`, `description`, `is_active`, `created_at`) VALUES
(1, 'SPARE', 'Spare Parts', 'Equipment spare parts and replacements', 1, '2026-02-03 08:46:55'),
(2, 'TOOLS', 'Tools', 'Hand tools and power tools', 1, '2026-02-03 08:46:55'),
(3, 'CLEAN', 'Cleaning Supplies', 'Cleaning chemicals and equipment', 1, '2026-02-03 08:46:55'),
(4, 'SAFETY', 'Safety Equipment', 'PPE and safety supplies', 1, '2026-02-03 08:46:55'),
(5, 'LUBE', 'Lubricants', 'Oils, greases, and lubricants', 1, '2026-02-03 08:46:55');

-- --------------------------------------------------------

--
-- Table structure for table `mro_inventory`
--

CREATE TABLE `mro_inventory` (
  `id` int(11) NOT NULL,
  `batch_code` varchar(30) NOT NULL,
  `mro_item_id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `received_date` date NOT NULL,
  `received_by` int(11) NOT NULL,
  `status` enum('available','partially_used','consumed','returned') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mro_inventory`
--

INSERT INTO `mro_inventory` (`id`, `batch_code`, `mro_item_id`, `po_id`, `quantity`, `remaining_quantity`, `unit_cost`, `supplier_name`, `supplier_id`, `received_date`, `received_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'MRO-20260203-7069', 1, NULL, 22.00, 21.00, 369.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'partially_used', NULL, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(2, 'MRO-20260203-1365', 2, NULL, 21.00, 21.00, 120.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(3, 'MRO-20260203-4751', 3, NULL, 33.00, 33.00, 398.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(4, 'MRO-20260203-4081', 4, NULL, 20.00, 20.00, 298.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(5, 'MRO-20260203-1961', 5, NULL, 34.00, 34.00, 283.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(6, 'MRO-20260203-5431', 6, NULL, 42.00, 42.00, 106.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(7, 'MRO-20260203-2808', 7, NULL, 34.00, 32.00, 324.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'partially_used', NULL, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(8, 'MRO-20260203-4206', 8, NULL, 20.00, 20.00, 165.00, 'MRO Supplier', NULL, '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(9, 'MRO-PO26-20260503-540', 2, 26, 1.00, 1.00, 50.00, 'Anco Merchandising', 5, '2026-05-03', 4, 'available', 'Received from PO#5256', '2026-05-03 06:23:40', '2026-05-03 06:23:40');

-- --------------------------------------------------------

--
-- Table structure for table `mro_items`
--

CREATE TABLE `mro_items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(30) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_of_measure` varchar(20) NOT NULL DEFAULT 'pcs',
  `minimum_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `lead_time_days` int(11) DEFAULT 7,
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `market_price` decimal(12,2) DEFAULT NULL,
  `last_price_update` date DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `compatible_equipment` text DEFAULT NULL,
  `is_critical` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mro_items`
--

INSERT INTO `mro_items` (`id`, `item_code`, `item_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `lead_time_days`, `current_stock`, `unit_cost`, `market_price`, `last_price_update`, `storage_location`, `compatible_equipment`, `is_critical`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'MRO-001', 'Pasteurizer Gasket Set', 1, 'set', 3.00, 7, 21.00, 369.00, NULL, NULL, 'Shelf A1', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(2, 'MRO-002', 'Homogenizer Valve', 1, 'pcs', 2.00, 7, 23.00, 50.00, NULL, NULL, 'Shelf A2', NULL, 1, 1, '2026-02-03 08:49:05', '2026-05-03 06:23:40'),
(3, 'MRO-003', 'Tank Agitator Belt', 1, 'pcs', 5.00, 7, 33.00, 398.00, NULL, NULL, 'Shelf A3', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(4, 'MRO-004', 'Temperature Sensor', 1, 'pcs', 2.00, 7, 20.00, 298.00, NULL, NULL, 'Shelf B1', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(5, 'MRO-005', 'Pump Seal Kit', 1, 'kit', 3.00, 7, 34.00, 283.00, NULL, NULL, 'Shelf B2', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(6, 'MRO-006', 'CIP Cleaning Solution', 3, 'liter', 20.00, 7, 42.00, 106.00, NULL, NULL, 'Chemical Room', NULL, 0, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(7, 'MRO-007', 'Food Grade Lubricant', 5, 'liter', 5.00, 7, 32.00, 324.00, NULL, NULL, 'Lube Room', NULL, 0, 1, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(8, 'MRO-008', 'Safety Goggles', 4, 'pcs', 10.00, 7, 20.00, 165.00, NULL, NULL, 'PPE Cabinet', NULL, 0, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05');

-- --------------------------------------------------------

--
-- Table structure for table `mro_price_history`
--

CREATE TABLE `mro_price_history` (
  `id` int(11) NOT NULL,
  `mro_item_id` int(11) NOT NULL,
  `old_price` decimal(12,2) NOT NULL,
  `new_price` decimal(12,2) NOT NULL,
  `price_change` decimal(12,2) GENERATED ALWAYS AS (`new_price` - `old_price`) STORED,
  `change_percent` decimal(5,2) GENERATED ALWAYS AS ((`new_price` - `old_price`) / `old_price` * 100) STORED,
  `po_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `packaging_runs`
--

CREATE TABLE `packaging_runs` (
  `id` int(11) NOT NULL,
  `packaging_code` varchar(30) NOT NULL,
  `production_run_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `batch_code` varchar(50) DEFAULT NULL,
  `product_type` varchar(50) DEFAULT NULL,
  `total_pieces_packaged` int(11) NOT NULL DEFAULT 0,
  `packaging_date` date NOT NULL,
  `packaged_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('completed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packaging_runs`
--

INSERT INTO `packaging_runs` (`id`, `packaging_code`, `production_run_id`, `batch_id`, `batch_code`, `product_type`, `total_pieces_packaged`, `packaging_date`, `packaged_by`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(5, 'PKG-20260221-001', 13, 23, 'BATCH-20260221-0013', 'flavored_milk', 10, '2026-02-21', 3, '', 'completed', '2026-02-20 17:01:14', '2026-02-20 17:01:14'),
(6, 'PKG-20260221-002', 14, 24, 'BATCH-20260221-0014', '', 100, '2026-02-21', 3, '', 'completed', '2026-02-20 17:25:36', '2026-02-20 17:25:36'),
(7, 'PKG-20260221-003', 15, 25, 'BATCH-20260221-0015', 'flavored_milk', 100, '2026-02-21', 3, '', 'completed', '2026-02-20 23:31:28', '2026-02-20 23:31:28'),
(8, 'PKG-20260221-004', 16, 26, 'BATCH-20260221-0016', 'flavored_milk', 100, '2026-02-21', 3, '', 'completed', '2026-02-20 23:42:40', '2026-02-20 23:42:40'),
(9, 'PKG-20260328-001', 18, 28, 'BATCH-20260328-0018', '', 20, '2026-03-28', 3, 'Test', 'completed', '2026-03-28 07:38:29', '2026-03-28 07:38:29'),
(10, 'PKG-20260328-002', 18, 28, 'BATCH-20260328-0018', '', 20, '2026-03-28', 3, '', 'completed', '2026-03-28 07:38:46', '2026-03-28 07:38:46'),
(11, 'PKG-20260328-003', 18, 28, 'BATCH-20260328-0018', '', 20, '2026-03-28', 3, '', 'completed', '2026-03-28 07:38:54', '2026-03-28 07:38:54'),
(12, 'PKG-20260503-001', 21, 31, 'BATCH-20260503-0021', '', 5, '2026-05-03', 3, '', 'completed', '2026-05-03 12:43:27', '2026-05-03 12:43:27'),
(13, 'PKG-20260503-002', 21, 31, 'BATCH-20260503-0021', '', 95, '2026-05-03', 3, '', 'completed', '2026-05-03 12:43:57', '2026-05-03 12:43:57');

-- --------------------------------------------------------

--
-- Table structure for table `packaging_run_items`
--

CREATE TABLE `packaging_run_items` (
  `id` int(11) NOT NULL,
  `packaging_run_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_variant` varchar(100) DEFAULT NULL,
  `size_ml` decimal(10,2) DEFAULT NULL,
  `unit_measure` varchar(10) DEFAULT 'ml',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `fg_inventory_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packaging_run_items`
--

INSERT INTO `packaging_run_items` (`id`, `packaging_run_id`, `product_id`, `product_name`, `product_variant`, `size_ml`, `unit_measure`, `quantity`, `fg_inventory_id`, `created_at`) VALUES
(1, 5, NULL, 'Basta', '200ml', 200.00, 'ml', 5, 32, '2026-02-20 17:01:14'),
(2, 5, NULL, 'Basta', '200ml', 200.00, 'ml', 5, 33, '2026-02-20 17:01:14'),
(3, 6, NULL, 'MilkBarBisaya', '50ml', 50.00, 'ml', 100, 34, '2026-02-20 17:25:36'),
(4, 7, 22, 'AnotherTesting', 'Yippe', 250.00, 'ml', 100, 35, '2026-02-20 23:31:28'),
(5, 8, 22, 'AnotherTesting', 'Yippe', 250.00, 'ml', 50, 36, '2026-02-20 23:42:40'),
(6, 8, 22, 'AnotherTesting', 'Yippe', 100.00, 'ml', 50, 37, '2026-02-20 23:42:40'),
(7, 9, 21, 'MilkBarBisaya', 'IkawBahala', 50.00, 'ml', 20, 38, '2026-03-28 07:38:29'),
(8, 10, 21, 'MilkBarBisaya', 'IkawBahala', 50.00, 'ml', 20, 39, '2026-03-28 07:38:46'),
(9, 11, 21, 'MilkBarBisaya', 'IkawBahala', 50.00, 'ml', 20, 40, '2026-03-28 07:38:54'),
(10, 12, 21, 'MilkBarBisaya', 'IkawBahala', 50.00, 'ml', 5, 41, '2026-05-03 12:43:27'),
(11, 13, 21, 'MilkBarBisaya', 'IkawBahala', 50.00, 'ml', 95, 42, '2026-05-03 12:43:57');

-- --------------------------------------------------------

--
-- Table structure for table `pasteurization_runs`
--

CREATE TABLE `pasteurization_runs` (
  `id` int(11) NOT NULL,
  `run_code` varchar(50) NOT NULL,
  `input_milk_liters` decimal(10,2) NOT NULL,
  `output_milk_liters` decimal(10,2) DEFAULT NULL,
  `temperature` decimal(5,2) NOT NULL DEFAULT 75.00,
  `duration_mins` int(11) NOT NULL DEFAULT 15,
  `status` enum('pending','in_progress','completed','failed') DEFAULT 'pending',
  `performed_by` int(11) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pasteurization_runs`
--

INSERT INTO `pasteurization_runs` (`id`, `run_code`, `input_milk_liters`, `output_milk_liters`, `temperature`, `duration_mins`, `status`, `performed_by`, `started_at`, `completed_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'PAST-20260220-001', 100.00, 0.00, 75.00, 15, 'in_progress', 3, '2026-02-20 23:59:34', NULL, '', '2026-02-20 23:59:34', '2026-02-20 23:59:34');

-- --------------------------------------------------------

--
-- Table structure for table `pasteurized_milk_inventory`
--

CREATE TABLE `pasteurized_milk_inventory` (
  `id` int(11) NOT NULL,
  `batch_code` varchar(50) NOT NULL,
  `pasteurization_run_id` int(11) DEFAULT NULL,
  `volume_liters` decimal(10,2) NOT NULL,
  `remaining_liters` decimal(10,2) NOT NULL,
  `pasteurization_temp` decimal(5,2) DEFAULT NULL,
  `pasteurized_at` datetime NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('available','reserved','used','expired','disposed') DEFAULT 'available',
  `storage_tank_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_type` varchar(50) DEFAULT 'pasteurization_run',
  `pasteurization_duration_mins` int(11) DEFAULT NULL,
  `pasteurized_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_collections`
--

CREATE TABLE `payment_collections` (
  `id` int(11) NOT NULL,
  `or_number` varchar(30) NOT NULL COMMENT 'Official Receipt Number: OR-YYYY-XXXXX',
  `dr_id` int(11) DEFAULT NULL COMMENT 'Link to delivery_receipts',
  `dr_number` varchar(30) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL COMMENT 'Link to sales_transactions for credit sales',
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(200) NOT NULL,
  `amount_collected` decimal(12,2) NOT NULL,
  `balance_before` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','gcash','bank_transfer','check') NOT NULL DEFAULT 'cash',
  `payment_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'check_number, check_bank, check_date, gcash_ref, bank_ref' CHECK (json_valid(`payment_metadata`)),
  `collected_by` int(11) NOT NULL,
  `collected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `status` enum('confirmed','bounced','cancelled') DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_collections`
--

INSERT INTO `payment_collections` (`id`, `or_number`, `dr_id`, `dr_number`, `transaction_id`, `customer_id`, `customer_name`, `amount_collected`, `balance_before`, `balance_after`, `payment_method`, `payment_metadata`, `collected_by`, `collected_at`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(6, 'OR-TEST-001', 1, 'DR-20260203-0101', NULL, NULL, 'SM Supermarket', 1000.00, 8500.00, 7500.00, 'cash', NULL, 4, '2026-02-03 11:11:05', 'Test collection', 'confirmed', '2026-02-03 11:11:05', '2026-02-03 11:11:05'),
(9, 'OR-2026-00001', 7, 'DR-20260124-0107', NULL, 2, 'Robinson\'s Supermarket', 11250.00, 11250.00, 0.00, 'cash', '[]', 7, '2026-02-03 11:44:35', '', 'confirmed', '2026-02-03 11:44:35', '2026-02-03 11:44:35'),
(10, 'OR-2026-00002', 6, 'DR-20260127-0106', NULL, 1, 'SM Supermarket', 7500.00, 7500.00, 0.00, 'cash', '[]', 7, '2026-02-05 07:22:13', '', 'confirmed', '2026-02-05 07:22:13', '2026-02-05 07:22:13'),
(11, 'OR-2026-00003', 18, 'DR-20260209-6858', NULL, 2, 'Robinson\'s Supermarket', 990.00, 990.00, 0.00, 'bank_transfer', '{\"bank_name\":\"Metrobank\",\"bank_ref\":\"123123123\",\"account_number\":null}', 7, '2026-02-09 13:29:59', 'Yehey', 'confirmed', '2026-02-09 13:29:59', '2026-02-09 13:29:59'),
(12, 'OR-2026-00004', 21, 'DR-20260211-002', NULL, 6, 'DepEd Region X Feeding Program', 50.00, 1575.00, 1525.00, 'gcash', '{\"gcash_ref\":\"5948594859\",\"gcash_number\":null}', 7, '2026-02-11 04:14:49', 'NOTES', 'confirmed', '2026-02-11 04:14:49', '2026-02-11 04:14:49'),
(13, 'OR-2026-00005', 22, 'DR-20260211-003', NULL, 6, 'DepEd Region X Feeding Program', 1260.00, 1260.00, 0.00, 'cash', '[]', 7, '2026-02-11 04:39:31', 'qweqwe', 'confirmed', '2026-02-11 04:39:31', '2026-02-11 04:39:31');

-- --------------------------------------------------------

--
-- Table structure for table `pos_transactions`
--

CREATE TABLE `pos_transactions` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(30) NOT NULL,
  `transaction_type` enum('cash','employee_credit') NOT NULL DEFAULT 'cash',
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(200) DEFAULT 'Walk-in Customer',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_tendered` decimal(12,2) DEFAULT 0.00,
  `change_amount` decimal(12,2) DEFAULT 0.00,
  `payment_method` enum('cash','gcash','bank_transfer') DEFAULT 'cash',
  `payment_reference` varchar(100) DEFAULT NULL,
  `cashier_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `status` enum('completed','voided','refunded') DEFAULT 'completed',
  `voided_by` int(11) DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_transaction_items`
--

CREATE TABLE `pos_transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_receiving_log`
--

CREATE TABLE `po_receiving_log` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `po_item_id` int(11) NOT NULL,
  `quantity_accepted` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity_rejected` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `rejection_category` enum('spoiled','defective','wrong_item','short_delivery','expired','other') DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_receiving_log`
--

INSERT INTO `po_receiving_log` (`id`, `po_id`, `po_item_id`, `quantity_accepted`, `quantity_rejected`, `rejection_reason`, `rejection_category`, `received_by`, `received_at`, `notes`) VALUES
(1, 26, 43, 1.00, 0.00, NULL, NULL, 4, '2026-05-03 06:23:40', NULL),
(2, 28, 45, 20.00, 5.00, 'Basta', 'spoiled', 4, '2026-05-03 12:14:18', 'Item condition: acceptable | Expiry: 2026-05-05'),
(3, 29, 46, 15.00, 5.00, 'basta', 'wrong_item', 4, '2026-05-03 12:24:46', 'Item condition: acceptable | Expiry: 2026-05-04'),
(4, 30, 47, 8.00, 2.00, 'Basta', 'spoiled', 4, '2026-05-03 14:08:06', 'Item condition: acceptable | Expiry: 2026-05-01'),
(5, 31, 48, 2.00, 0.00, NULL, NULL, 4, '2026-05-03 14:15:37', 'Item condition: acceptable | Supplier lot: 5 | Expiry: 2026-05-30'),
(6, 32, 49, 1.00, 1.00, 'Damaged Packaging', 'spoiled', 4, '2026-05-03 14:30:13', 'Item condition: acceptable | Supplier lot: 1 | Expiry: 2026-05-11');

-- --------------------------------------------------------

--
-- Table structure for table `price_canvass`
--

CREATE TABLE `price_canvass` (
  `id` int(11) NOT NULL,
  `canvass_code` varchar(30) NOT NULL,
  `item_type` enum('ingredient','mro','other') NOT NULL DEFAULT 'ingredient',
  `ingredient_id` int(11) DEFAULT NULL,
  `mro_item_id` int(11) DEFAULT NULL,
  `item_description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `status` enum('open','completed','cancelled') DEFAULT 'open',
  `selected_quote_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_batches`
--

CREATE TABLE `production_batches` (
  `id` int(11) NOT NULL,
  `batch_code` varchar(50) NOT NULL,
  `run_id` int(11) DEFAULT NULL,
  `recipe_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `milk_type_id` int(11) NOT NULL COMMENT 'Milk type used - for traceability',
  `product_type` varchar(50) NOT NULL,
  `product_variant` varchar(50) DEFAULT NULL,
  `raw_milk_liters` decimal(10,2) NOT NULL,
  `batch_size_multiplier` decimal(5,2) DEFAULT 1.00,
  `manufacturing_date` date NOT NULL,
  `manufacturing_time` time DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `pasteurization_temp` decimal(5,2) DEFAULT NULL,
  `pasteurization_time` time DEFAULT NULL,
  `cooling_temp` decimal(5,2) DEFAULT NULL,
  `cooling_time` time DEFAULT NULL,
  `organoleptic_taste` tinyint(1) DEFAULT 0,
  `organoleptic_appearance` tinyint(1) DEFAULT 0,
  `organoleptic_smell` tinyint(1) DEFAULT 0,
  `qc_status` enum('pending','in_testing','released','rejected','on_hold') DEFAULT 'pending',
  `qc_released_at` datetime DEFAULT NULL,
  `qc_notes` text DEFAULT NULL,
  `fg_received` tinyint(1) DEFAULT 0,
  `fg_received_at` datetime DEFAULT NULL,
  `fg_received_by` int(11) DEFAULT NULL,
  `expected_yield` int(11) DEFAULT NULL,
  `actual_yield` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `released_by` int(11) DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_batches`
--

INSERT INTO `production_batches` (`id`, `batch_code`, `run_id`, `recipe_id`, `product_id`, `milk_type_id`, `product_type`, `product_variant`, `raw_milk_liters`, `batch_size_multiplier`, `manufacturing_date`, `manufacturing_time`, `expiry_date`, `pasteurization_temp`, `pasteurization_time`, `cooling_temp`, `cooling_time`, `organoleptic_taste`, `organoleptic_appearance`, `organoleptic_smell`, `qc_status`, `qc_released_at`, `qc_notes`, `fg_received`, `fg_received_at`, `fg_received_by`, `expected_yield`, `actual_yield`, `barcode`, `created_by`, `released_by`, `released_at`, `created_at`, `updated_at`) VALUES
(1, 'BATCH-20260203-001', NULL, NULL, 1, 1, 'pasteurized_milk', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-02-10', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-001-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(2, 'BATCH-20260203-002', NULL, NULL, 2, 1, 'pasteurized_milk', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-02-10', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-002-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(3, 'BATCH-20260203-003', NULL, NULL, 3, 1, 'flavored_milk', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-02-10', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-003-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(4, 'BATCH-20260203-004', NULL, NULL, 4, 1, 'yogurt', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-02-17', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-004-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(5, 'BATCH-20260203-005', NULL, NULL, 5, 1, 'yogurt', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-02-17', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-005-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(6, 'BATCH-20260203-006', NULL, NULL, 6, 1, 'cheese', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-02-24', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-006-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(7, 'BATCH-20260203-007', NULL, NULL, 7, 1, 'butter', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-03-05', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-007-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(8, 'BATCH-20260203-008', NULL, NULL, 8, 1, 'cream', NULL, 50.00, 1.00, '2026-02-03', NULL, '2026-02-13', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', NULL, NULL, 0, NULL, NULL, 120, 100, 'BATCH-20260203-008-260203', 1, NULL, NULL, '2026-02-03 09:34:04', '2026-02-05 08:49:54'),
(14, 'BATCH-20260205-0004', 4, 13, 2, 1, 'bottled_milk', NULL, 100.00, 1.00, '2026-02-05', NULL, '2026-02-12', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-05 16:30:02', '', 1, NULL, NULL, 50, 50, 'BATCH-20260205-0004-260205', 3, 2, '2026-02-05 08:30:02', '2026-02-05 08:29:27', '2026-02-05 08:36:51'),
(15, 'BATCH-20260205-0005', 5, 13, NULL, 1, 'bottled_milk', NULL, 100.00, 1.00, '2026-02-05', NULL, '2026-02-12', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-05 19:00:25', '', 1, NULL, NULL, 50, 50, 'BATCH-20260205-0005-260205', 3, 2, '2026-02-05 11:00:25', '2026-02-05 11:00:03', '2026-02-05 11:17:02'),
(16, 'BATCH-20260209-0006', 6, 12, NULL, 1, 'bottled_milk', NULL, 100.00, 1.00, '2026-02-09', NULL, '2026-02-16', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-09 14:48:34', 'GG', 1, NULL, NULL, 50, 50, 'BATCH-20260209-0006-260209', 3, 2, '2026-02-09 06:48:34', '2026-02-09 06:48:15', '2026-02-09 06:49:49'),
(17, 'BATCH-20260209-0007', 7, 14, NULL, 1, 'bottled_milk', NULL, 10.87, 1.00, '2026-02-09', NULL, '2026-02-16', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-09 21:14:48', '', 1, NULL, NULL, 10, 10, 'BATCH-20260209-0007-260209', 3, 2, '2026-02-09 13:14:48', '2026-02-09 13:14:01', '2026-02-09 13:15:19'),
(18, 'BATCH-20260211-0008', 8, 12, NULL, 1, 'bottled_milk', NULL, 52.63, 1.00, '2026-02-11', NULL, '2026-02-18', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-11 02:29:48', '', 1, NULL, NULL, 50, 50, 'BATCH-20260211-0008-260211', 3, 2, '2026-02-10 18:29:48', '2026-02-10 18:29:35', '2026-02-10 18:30:27'),
(19, 'BATCH-20260211-0009', 9, 18, NULL, 1, 'cream', NULL, 10.00, 1.00, '2026-02-11', NULL, '2026-02-18', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-11 02:49:59', '', 1, NULL, NULL, 1, 1, 'BATCH-20260211-0009-260211', 3, 2, '2026-02-10 18:49:59', '2026-02-10 18:49:32', '2026-02-10 18:50:20'),
(20, 'BATCH-20260211-0010', 10, 18, NULL, 1, 'cream', NULL, 200.00, 1.00, '2026-02-11', NULL, '2026-02-18', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-11 02:57:03', '', 1, NULL, NULL, 20, 20, 'BATCH-20260211-0010-260211', 3, 2, '2026-02-10 18:57:03', '2026-02-10 18:56:23', '2026-02-10 18:57:36'),
(21, 'BATCH-20260211-0011', 11, 12, NULL, 1, 'bottled_milk', NULL, 10.53, 1.00, '2026-02-11', NULL, '2026-02-18', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-11 12:03:06', 'Test', 1, NULL, NULL, 10, 10, 'BATCH-20260211-0011-260211', 3, 2, '2026-02-11 04:03:06', '2026-02-11 04:02:41', '2026-02-11 04:03:42'),
(22, 'BATCH-20260220-0012', 12, 12, NULL, 1, 'bottled_milk', NULL, 10.53, 1.00, '2026-02-20', NULL, '2026-02-27', NULL, NULL, NULL, NULL, 0, 0, 0, 'pending', NULL, NULL, 0, NULL, NULL, 10, 10, NULL, 3, NULL, NULL, '2026-02-20 08:47:52', '2026-02-20 08:47:52'),
(23, 'BATCH-20260221-0013', 13, 20, NULL, 1, 'flavored_milk', NULL, 1.05, 1.00, '2026-02-21', NULL, '2026-02-28', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-21 01:04:20', '', 0, NULL, NULL, 10, 10, 'BATCH-20260221-0013-260221', 3, 2, '2026-02-20 17:04:20', '2026-02-20 16:12:51', '2026-02-20 17:04:20'),
(24, 'BATCH-20260221-0014', 14, 21, NULL, 1, '', NULL, 10.53, 1.00, '2026-02-21', NULL, '2026-02-28', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-21 01:20:51', '', 0, NULL, NULL, 100, 100, 'BATCH-20260221-0014-260221', 3, 2, '2026-02-20 17:20:51', '2026-02-20 17:20:23', '2026-02-20 17:20:51'),
(25, 'BATCH-20260221-0015', 15, 22, NULL, 1, 'flavored_milk', NULL, 52.63, 1.00, '2026-02-21', NULL, '2026-02-28', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-21 07:30:44', '', 0, NULL, NULL, 100, 100, 'BATCH-20260221-0015-260221', 3, 2, '2026-02-20 23:30:44', '2026-02-20 23:30:16', '2026-02-20 23:30:44'),
(26, 'BATCH-20260221-0016', 16, 22, NULL, 1, 'flavored_milk', NULL, 52.63, 1.00, '2026-02-21', NULL, '2026-02-28', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-21 07:41:07', '', 0, NULL, NULL, 100, 100, 'BATCH-20260221-0016-260221', 3, 2, '2026-02-20 23:41:07', '2026-02-20 23:40:14', '2026-02-20 23:41:07'),
(27, 'BATCH-20260328-0017', 17, 21, NULL, 1, '', NULL, 1.05, 1.00, '2026-03-28', NULL, '2026-04-04', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-03-28 15:32:38', '', 0, NULL, NULL, 10, 10, 'BATCH-20260328-0017-260328', 3, 2, '2026-03-28 07:32:38', '2026-03-28 07:32:04', '2026-03-28 07:32:38'),
(28, 'BATCH-20260328-0018', 18, 21, NULL, 1, '', NULL, 6.32, 1.00, '2026-03-28', NULL, '2026-04-04', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-03-28 15:37:40', '', 0, NULL, NULL, 60, 60, 'BATCH-20260328-0018-260328', 3, 2, '2026-03-28 07:37:40', '2026-03-28 07:37:09', '2026-03-28 07:37:40'),
(29, 'BATCH-20260426-0019', 19, 21, NULL, 1, '', NULL, 10.53, 1.00, '2026-04-26', NULL, '2026-05-03', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-05-03 20:41:02', '', 0, NULL, NULL, 100, 100, 'BATCH-20260426-0019-260426', 3, 2, '2026-05-03 12:41:02', '2026-04-26 09:35:50', '2026-05-03 12:41:02'),
(30, 'BATCH-20260427-0020', 20, 21, NULL, 1, '', NULL, 10.53, 1.00, '2026-04-27', NULL, '2026-05-04', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-05-03 20:40:53', '', 0, NULL, NULL, 100, 100, 'BATCH-20260427-0020-260427', 3, 2, '2026-05-03 12:40:53', '2026-04-27 07:08:16', '2026-05-03 12:40:53'),
(31, 'BATCH-20260503-0021', 21, 21, NULL, 1, '', NULL, 10.53, 1.00, '2026-05-03', NULL, '2026-05-10', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-05-03 20:40:46', '', 0, NULL, NULL, 100, 100, 'BATCH-20260503-0021-260503', 3, 2, '2026-05-03 12:40:46', '2026-05-03 12:40:14', '2026-05-03 12:40:46');

-- --------------------------------------------------------

--
-- Table structure for table `production_byproducts`
--

CREATE TABLE `production_byproducts` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `byproduct_type` enum('buttermilk','whey','cream','skim_milk','other') DEFAULT 'other',
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT 'liters',
  `status` enum('pending','stored','used','disposed') DEFAULT 'pending',
  `destination` varchar(100) DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_ccp_logs`
--

CREATE TABLE `production_ccp_logs` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `check_type` enum('chilling','preheating','homogenization','pasteurization','cooling','storage','intermediate') NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `pressure_psi` int(11) DEFAULT NULL,
  `hold_time_mins` int(11) DEFAULT 0,
  `hold_time_secs` int(11) DEFAULT 0,
  `target_temp` decimal(5,2) DEFAULT NULL,
  `temp_tolerance` decimal(5,2) DEFAULT 2.00,
  `status` enum('pass','fail','warning') DEFAULT 'pass',
  `check_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_ccp_logs`
--

INSERT INTO `production_ccp_logs` (`id`, `run_id`, `check_type`, `temperature`, `pressure_psi`, `hold_time_mins`, `hold_time_secs`, `target_temp`, `temp_tolerance`, `status`, `check_datetime`, `verified_by`, `notes`, `created_at`) VALUES
(1, 3, 'preheating', 75.00, NULL, 0, 0, 65.00, 2.00, 'pass', '2026-02-05 08:19:50', 3, '', '2026-02-05 08:19:50'),
(2, 3, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-05 08:19:55', 3, '', '2026-02-05 08:19:55'),
(3, 3, 'pasteurization', 75.00, NULL, 0, 0, 75.00, 2.00, 'warning', '2026-02-05 08:20:12', 3, '', '2026-02-05 08:20:12'),
(4, 3, 'pasteurization', 75.00, NULL, 0, 0, 75.00, 2.00, 'warning', '2026-02-05 08:20:19', 3, '', '2026-02-05 08:20:19'),
(5, 3, 'pasteurization', 75.00, NULL, 0, 0, 75.00, 2.00, 'warning', '2026-02-05 08:20:27', 3, '', '2026-02-05 08:20:27'),
(6, 3, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-05 08:24:31', 3, '', '2026-02-05 08:24:31'),
(7, 4, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-05 08:26:46', 3, '', '2026-02-05 08:26:46'),
(8, 4, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-05 08:26:59', 3, '', '2026-02-05 08:26:59'),
(9, 5, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-05 10:59:46', 3, '', '2026-02-05 10:59:46'),
(10, 5, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-05 10:59:53', 3, '', '2026-02-05 10:59:53'),
(11, 6, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-09 06:47:53', 3, '', '2026-02-09 06:47:53'),
(12, 6, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-09 06:47:59', 3, '', '2026-02-09 06:47:59'),
(13, 7, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-09 13:13:43', 3, '', '2026-02-09 13:13:43'),
(14, 7, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-09 13:13:52', 3, '', '2026-02-09 13:13:52'),
(15, 8, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-10 18:29:23', 3, '', '2026-02-10 18:29:23'),
(16, 8, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-10 18:29:29', 3, '', '2026-02-10 18:29:29'),
(17, 9, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-10 18:49:20', 3, '', '2026-02-10 18:49:20'),
(18, 9, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-10 18:49:25', 3, '', '2026-02-10 18:49:25'),
(19, 10, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-10 18:56:10', 3, '', '2026-02-10 18:56:10'),
(20, 10, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-10 18:56:15', 3, '', '2026-02-10 18:56:15'),
(21, 11, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-11 04:01:41', 3, '', '2026-02-11 04:01:41'),
(22, 11, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-11 04:02:09', 3, '', '2026-02-11 04:02:09'),
(23, 12, 'pasteurization', 75.00, NULL, 0, 0, 75.00, 2.00, 'warning', '2026-02-20 08:47:24', 3, '', '2026-02-20 08:47:24'),
(24, 12, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-20 08:47:31', 3, '', '2026-02-20 08:47:31'),
(25, 12, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-20 08:47:36', 3, '', '2026-02-20 08:47:36'),
(26, 13, 'pasteurization', 75.00, NULL, 0, 0, 75.00, 2.00, 'warning', '2026-02-20 16:12:30', 3, '', '2026-02-20 16:12:30'),
(27, 13, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-20 16:12:40', 3, '', '2026-02-20 16:12:40'),
(28, 13, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-20 16:12:46', 3, '', '2026-02-20 16:12:46'),
(29, 14, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-20 17:20:03', 3, '', '2026-02-20 17:20:03'),
(30, 14, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-20 17:20:09', 3, '', '2026-02-20 17:20:09'),
(31, 15, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-20 23:28:46', 3, '', '2026-02-20 23:28:46'),
(32, 15, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-20 23:28:51', 3, '', '2026-02-20 23:28:51'),
(33, 16, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-02-20 23:39:58', 3, '', '2026-02-20 23:39:58'),
(34, 16, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-20 23:40:03', 3, '', '2026-02-20 23:40:03'),
(35, 17, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-03-28 07:31:40', 3, '', '2026-03-28 07:31:40'),
(36, 17, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-03-28 07:31:48', 3, '', '2026-03-28 07:31:48'),
(37, 18, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-03-28 07:36:47', 3, '', '2026-03-28 07:36:47'),
(38, 18, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-03-28 07:36:53', 3, '', '2026-03-28 07:36:53'),
(39, 19, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-04-26 09:35:40', 3, '', '2026-04-26 09:35:40'),
(40, 19, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-04-26 09:35:45', 3, '', '2026-04-26 09:35:45'),
(41, 20, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-04-27 07:07:09', 3, '', '2026-04-27 07:07:09'),
(42, 20, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-04-27 07:08:05', 3, '', '2026-04-27 07:08:05'),
(43, 21, 'pasteurization', 75.00, NULL, 0, 15, 75.00, 2.00, 'pass', '2026-05-03 12:40:03', 3, '', '2026-05-03 12:40:03'),
(44, 21, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-05-03 12:40:09', 3, '', '2026-05-03 12:40:09');

-- --------------------------------------------------------

--
-- Table structure for table `production_material_usage`
--

CREATE TABLE `production_material_usage` (
  `id` bigint(20) NOT NULL,
  `run_id` int(11) NOT NULL,
  `material_type` enum('raw_milk','pasteurized_milk','ingredient') NOT NULL,
  `source_batch_id` int(11) NOT NULL COMMENT 'ID from raw_milk_inventory or ingredient_batches',
  `source_batch_code` varchar(50) NOT NULL,
  `milk_type_id` int(11) DEFAULT NULL COMMENT 'For milk materials',
  `quantity_used` decimal(10,3) NOT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `used_at` datetime NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_output`
--

CREATE TABLE `production_output` (
  `id` bigint(20) NOT NULL,
  `run_id` int(11) NOT NULL,
  `output_batch_id` int(11) NOT NULL COMMENT 'production_batches.id',
  `output_batch_code` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `milk_type_id` int(11) NOT NULL,
  `quantity_produced` int(11) NOT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `produced_at` datetime NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `production_runs`
--

CREATE TABLE `production_runs` (
  `id` int(11) NOT NULL,
  `run_code` varchar(30) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `milk_type_id` int(11) NOT NULL COMMENT 'Actual milk type used',
  `planned_quantity` int(11) NOT NULL,
  `actual_quantity` int(11) DEFAULT NULL,
  `milk_liters_used` decimal(10,2) DEFAULT NULL,
  `milk_source_type` enum('raw','pasteurized') DEFAULT 'raw',
  `pasteurized_milk_batch_id` int(11) DEFAULT NULL,
  `status` enum('planned','in_progress','pasteurization','processing','cooling','packaging','completed','cancelled') DEFAULT 'planned',
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `started_by` int(11) DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `yield_variance` decimal(10,2) DEFAULT NULL,
  `variance_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `output_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Stores unit breakdown: total_pieces, secondary_count, remaining_primary, etc.' CHECK (json_valid(`output_breakdown`)),
  `milk_batch_source` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`milk_batch_source`)),
  `process_temperature` decimal(5,2) DEFAULT NULL,
  `process_duration_mins` int(11) DEFAULT NULL,
  `ingredient_adjustments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ingredient_adjustments`)),
  `cream_output_kg` decimal(10,2) DEFAULT NULL,
  `skim_milk_output_liters` decimal(10,2) DEFAULT NULL,
  `cheese_state` varchar(50) DEFAULT NULL,
  `is_salted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_runs`
--

INSERT INTO `production_runs` (`id`, `run_code`, `recipe_id`, `milk_type_id`, `planned_quantity`, `actual_quantity`, `milk_liters_used`, `milk_source_type`, `pasteurized_milk_batch_id`, `status`, `start_datetime`, `end_datetime`, `started_by`, `completed_by`, `yield_variance`, `variance_reason`, `notes`, `created_at`, `updated_at`, `output_breakdown`, `milk_batch_source`, `process_temperature`, `process_duration_mins`, `ingredient_adjustments`, `cream_output_kg`, `skim_milk_output_liters`, `cheese_state`, `is_salted`) VALUES
(3, 'PRD-20260205-001', 13, 1, 50, NULL, 100.00, 'raw', NULL, 'cancelled', '2026-02-05 16:13:46', NULL, 3, NULL, NULL, NULL, '', '2026-02-05 08:13:43', '2026-02-05 08:25:21', NULL, '{\"source\":\"requisition_based\",\"available_at_creation\":150,\"allocated\":\"100.00\",\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(4, 'PRD-20260205-002', 13, 1, 50, 50, 100.00, 'raw', NULL, 'completed', '2026-02-05 16:26:30', '2026-02-05 16:29:27', 3, 3, 0.00, '', '', '2026-02-05 08:25:50', '2026-02-05 08:29:27', '{\"total_pieces\":50,\"secondary_count\":2,\"secondary_unit\":\"crates\",\"remaining_primary\":2,\"primary_unit\":\"bottles\",\"input_quantity\":50,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":150,\"allocated\":\"100.00\",\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(5, 'PRD-20260205-003', 13, 1, 50, 50, 100.00, 'raw', NULL, 'completed', '2026-02-05 18:59:26', '2026-02-05 19:00:03', 3, 3, 0.00, '', '', '2026-02-05 10:59:24', '2026-02-05 11:00:03', '{\"total_pieces\":50,\"secondary_count\":2,\"secondary_unit\":\"crates\",\"remaining_primary\":2,\"primary_unit\":\"bottles\",\"input_quantity\":50,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":200,\"allocated\":\"100.00\",\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(6, 'PRD-20260209-001', 12, 1, 50, 50, 100.00, 'raw', NULL, 'completed', '2026-02-09 14:47:38', '2026-02-09 14:48:15', 3, 3, 0.00, '', 'Basta', '2026-02-09 06:47:33', '2026-02-09 06:48:15', '{\"total_pieces\":50,\"secondary_count\":2,\"secondary_unit\":\"crates\",\"remaining_primary\":2,\"primary_unit\":\"bottles\",\"input_quantity\":50,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":100,\"allocated\":\"100.00\",\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(7, 'PRD-20260209-002', 14, 1, 10, 10, 10.87, 'raw', NULL, 'completed', '2026-02-09 21:13:24', '2026-02-09 21:14:01', 3, 3, 0.00, '', 'Test', '2026-02-09 13:13:19', '2026-02-09 13:14:01', '{\"total_pieces\":10,\"secondary_count\":0,\"secondary_unit\":\"crates\",\"remaining_primary\":10,\"primary_unit\":\"bottles\",\"input_quantity\":10,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":50,\"allocated\":10.87,\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(8, 'PRD-20260211-001', 12, 1, 50, 50, 52.63, 'raw', NULL, 'completed', '2026-02-11 02:29:14', '2026-02-11 02:29:35', 3, 3, 0.00, '', '', '2026-02-10 18:29:11', '2026-02-10 18:29:35', '{\"total_pieces\":50,\"secondary_count\":2,\"secondary_unit\":\"crates\",\"remaining_primary\":2,\"primary_unit\":\"bottles\",\"input_quantity\":50,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":89.13,\"allocated\":52.63,\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(9, 'PRD-20260211-002', 18, 1, 1, 1, 10.00, 'raw', NULL, 'completed', '2026-02-11 02:49:11', '2026-02-11 02:49:32', 3, 3, 0.00, '', '', '2026-02-10 18:49:09', '2026-02-10 18:49:32', '{\"total_pieces\":1,\"secondary_count\":0,\"secondary_unit\":\"crates\",\"remaining_primary\":1,\"primary_unit\":\"bottles\",\"input_quantity\":1,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":36.5,\"allocated\":10,\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(10, 'PRD-20260211-003', 18, 1, 20, 20, 200.00, 'raw', NULL, 'completed', '2026-02-11 02:56:00', '2026-02-11 02:56:23', 3, 3, 0.00, '', 'Test', '2026-02-10 18:55:58', '2026-02-10 18:56:23', '{\"total_pieces\":20,\"secondary_count\":0,\"secondary_unit\":\"crates\",\"remaining_primary\":20,\"primary_unit\":\"bottles\",\"input_quantity\":20,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":526.5,\"allocated\":200,\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(11, 'PRD-20260211-004', 12, 1, 10, 10, 10.53, 'raw', NULL, 'completed', '2026-02-11 12:01:28', '2026-02-11 12:02:41', 3, 3, 0.00, '', '', '2026-02-11 04:01:25', '2026-02-11 04:02:41', '{\"total_pieces\":10,\"secondary_count\":0,\"secondary_unit\":\"crates\",\"remaining_primary\":10,\"primary_unit\":\"bottles\",\"input_quantity\":10,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":326.5,\"allocated\":10.53,\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0),
(12, 'PRD-20260220-001', 12, 1, 10, 10, 10.53, 'raw', NULL, 'completed', '2026-02-20 16:47:15', '2026-02-20 16:47:52', 3, 3, 0.00, '', 'Basta', '2026-02-20 08:47:12', '2026-02-20 08:47:52', '{\"total_pieces\":10,\"secondary_count\":0,\"secondary_unit\":\"crates\",\"remaining_primary\":10,\"primary_unit\":\"bottles\",\"input_quantity\":10,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":315.97,\"allocated\":10.53,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(13, 'PRD-20260221-001', 20, 1, 10, 10, 1.05, 'raw', NULL, 'completed', '2026-02-21 00:12:21', '2026-02-21 00:12:51', 3, 3, 0.00, '', '', '2026-02-20 16:12:18', '2026-02-20 16:12:51', '{\"total_pieces\":10,\"secondary_count\":0,\"secondary_unit\":\"crates\",\"remaining_primary\":10,\"primary_unit\":\"bottles\",\"input_quantity\":10,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":305.44000000000005,\"allocated\":1.05,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(14, 'PRD-20260221-002', 21, 1, 100, 100, 10.53, 'raw', NULL, 'completed', '2026-02-21 01:19:54', '2026-02-21 01:20:23', 3, 3, 0.00, '', 'Basta', '2026-02-20 17:19:52', '2026-02-20 17:20:23', '{\"total_pieces\":100,\"secondary_count\":4,\"secondary_unit\":\"crates\",\"remaining_primary\":4,\"primary_unit\":\"bottles\",\"input_quantity\":100,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":304.39,\"allocated\":10.53,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(15, 'PRD-20260221-003', 22, 1, 100, 100, 52.63, 'raw', NULL, 'completed', '2026-02-21 07:28:34', '2026-02-21 07:30:16', 3, 3, 0.00, '', 'Basta', '2026-02-20 23:28:30', '2026-02-20 23:30:16', '{\"total_pieces\":100,\"secondary_count\":4,\"secondary_unit\":\"crates\",\"remaining_primary\":4,\"primary_unit\":\"bottles\",\"input_quantity\":100,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":293.86,\"allocated\":52.63,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(16, 'PRD-20260221-004', 22, 1, 100, 100, 52.63, 'raw', NULL, 'completed', '2026-02-21 07:39:40', '2026-02-21 07:40:13', 3, 3, 0.00, '', '', '2026-02-20 23:39:37', '2026-02-20 23:40:13', '{\"total_pieces\":100,\"secondary_count\":4,\"secondary_unit\":\"crates\",\"remaining_primary\":4,\"primary_unit\":\"bottles\",\"input_quantity\":100,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":241.23000000000002,\"allocated\":52.63,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(17, 'PRD-20260328-001', 21, 1, 10, 10, 1.05, 'raw', NULL, 'completed', '2026-03-28 15:31:07', '2026-03-28 15:32:04', 3, 3, 0.00, '', '', '2026-03-28 07:31:02', '2026-03-28 07:32:04', '{\"total_pieces\":10,\"secondary_count\":0,\"secondary_unit\":\"crates\",\"remaining_primary\":10,\"primary_unit\":\"bottles\",\"input_quantity\":10,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":188.60000000000002,\"allocated\":1.05,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(18, 'PRD-20260328-002', 21, 1, 60, 60, 6.32, 'raw', NULL, 'completed', '2026-03-28 15:36:23', '2026-03-28 15:37:09', 3, 3, 0.00, '', 'Test', '2026-03-28 07:36:10', '2026-03-28 07:37:09', '{\"total_pieces\":60,\"secondary_count\":2,\"secondary_unit\":\"crates\",\"remaining_primary\":12,\"primary_unit\":\"bottles\",\"input_quantity\":60,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":187.54999999999995,\"allocated\":6.32,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(19, 'PRD-20260426-001', 21, 1, 100, 100, 10.53, 'raw', NULL, 'completed', '2026-04-26 17:35:27', '2026-04-26 17:35:50', 3, 3, 0.00, '', 'Test', '2026-04-26 09:35:24', '2026-04-26 09:35:50', '{\"total_pieces\":100,\"secondary_count\":4,\"secondary_unit\":\"crates\",\"remaining_primary\":4,\"primary_unit\":\"bottles\",\"input_quantity\":100,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":181.23000000000002,\"allocated\":10.53,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(20, 'PRD-20260427-001', 21, 1, 100, 100, 10.53, 'raw', NULL, 'completed', '2026-04-27 15:06:49', '2026-04-27 15:08:16', 3, 3, 0.00, '', '', '2026-04-27 07:06:44', '2026-04-27 07:08:16', '{\"total_pieces\":100,\"secondary_count\":4,\"secondary_unit\":\"crates\",\"remaining_primary\":4,\"primary_unit\":\"bottles\",\"input_quantity\":100,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":170.70000000000005,\"allocated\":10.53,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(21, 'PRD-20260503-001', 21, 1, 100, 100, 10.53, 'raw', NULL, 'completed', '2026-05-03 20:39:52', '2026-05-03 20:40:14', 3, 3, 0.00, '', '', '2026-05-03 12:39:23', '2026-05-03 12:40:14', '{\"total_pieces\":100,\"secondary_count\":4,\"secondary_unit\":\"crates\",\"remaining_primary\":4,\"primary_unit\":\"bottles\",\"input_quantity\":100,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":160.16999999999996,\"allocated\":10.53,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0),
(22, 'PRD-20260503-002', 21, 1, 10, NULL, 1.05, 'raw', NULL, 'in_progress', '2026-05-03 22:24:59', NULL, 3, NULL, NULL, NULL, '', '2026-05-03 14:18:23', '2026-05-03 14:24:59', NULL, '{\"source\":\"requisition_based\",\"available_at_creation\":149.64,\"allocated\":1.05,\"pasteurized_batch_id\":null}', NULL, NULL, NULL, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `production_run_milk_usage`
--

CREATE TABLE `production_run_milk_usage` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `receiving_id` int(11) NOT NULL,
  `milk_liters_allocated` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category` enum('pasteurized_milk','flavored_milk','yogurt','cheese','butter','cream') NOT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `milk_type_id` int(11) DEFAULT NULL COMMENT 'Required milk type for this product',
  `description` text DEFAULT NULL,
  `unit_size` decimal(10,2) DEFAULT NULL,
  `unit_measure` varchar(20) DEFAULT 'ml',
  `shelf_life_days` int(11) DEFAULT 7,
  `storage_temp_min` decimal(4,2) DEFAULT 2.00,
  `storage_temp_max` decimal(4,2) DEFAULT 6.00,
  `base_unit` varchar(20) DEFAULT 'piece',
  `box_unit` varchar(20) DEFAULT 'box',
  `pieces_per_box` int(11) DEFAULT 1,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `selling_price` decimal(12,2) DEFAULT 0.00,
  `cost_price` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `product_name`, `category`, `variant`, `milk_type_id`, `description`, `unit_size`, `unit_measure`, `shelf_life_days`, `storage_temp_min`, `storage_temp_max`, `base_unit`, `box_unit`, `pieces_per_box`, `unit_price`, `selling_price`, `cost_price`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'FMK-1L', 'Fresh Milk 1L', 'pasteurized_milk', '1 Liter', NULL, NULL, 1000.00, 'ml', 7, 2.00, 4.00, 'bottle', 'box', 12, 95.00, 105.00, 78.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(2, 'FMK-500', 'Fresh Milk 500ml', 'pasteurized_milk', '500ml', NULL, NULL, 500.00, 'ml', 7, 2.00, 4.00, 'bottle', 'box', 24, 55.00, 60.00, 45.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(3, 'CHO-1L', 'Chocolate Milk 1L', 'flavored_milk', '1 Liter', NULL, NULL, 1000.00, 'ml', 7, 2.00, 4.00, 'bottle', 'box', 12, 40.00, 45.00, 32.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(4, 'YOG-500', 'Plain Yogurt 500g', 'yogurt', '500g', NULL, NULL, 500.00, 'g', 14, 2.00, 4.00, 'cup', 'box', 20, 45.00, 50.00, 35.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(5, 'YOG-STR', 'Strawberry Yogurt 150g', 'yogurt', '150g', NULL, NULL, 150.00, 'g', 14, 2.00, 4.00, 'cup', 'box', 48, 45.00, 50.00, 35.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(6, 'CHE-250', 'Kesong Puti 250g', 'cheese', '250g', NULL, NULL, 250.00, 'g', 21, 2.00, 4.00, 'pack', 'box', 24, 150.00, 175.00, 120.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(7, 'BUT-250', 'Butter 250g', 'butter', '250g', NULL, NULL, 250.00, 'g', 30, 2.00, 4.00, 'block', 'box', 20, 120.00, 140.00, 95.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(8, 'CRM-1L', 'Fresh Cream 1L', 'cream', '1 Liter', NULL, NULL, 1000.00, 'ml', 10, 2.00, 4.00, 'bottle', 'box', 12, 80.00, 95.00, 65.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04'),
(9, 'BAR-2021', 'MilkBar', 'pasteurized_milk', '100G', 1, 'Test', 1000.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 1, 0.00, 0.00, 0.00, 1, '2026-02-14 02:35:15', '2026-02-14 02:35:15'),
(10, 'PANDAN-2021', 'MilkBAR-PANDAN', 'pasteurized_milk', '100G', 1, '', 1000.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 1, 50.00, 50.00, 0.00, 1, '2026-02-14 02:35:56', '2026-02-20 17:18:40'),
(14, 'PM0001', 'BastaGatas', 'pasteurized_milk', NULL, 1, '', 1000.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 1, 0.00, 0.00, 0.00, 1, '2026-02-20 14:50:13', '2026-02-20 16:44:30'),
(15, 'FM0001', 'Basta', 'flavored_milk', 'Chocolate', NULL, NULL, 1000.00, 'ml', 7, 2.00, 6.00, 'bottle', 'box', 1, 0.00, 45.00, 0.00, 1, '2026-02-20 16:44:30', '2026-02-20 16:44:30'),
(16, 'FM0002', 'Basta', 'flavored_milk', 'Chocolate', NULL, NULL, 500.00, 'ml', 7, 2.00, 6.00, 'bottle', 'box', 1, 0.00, 25.00, 0.00, 1, '2026-02-20 16:44:30', '2026-02-20 16:44:30'),
(17, 'FM0003', 'Basta', 'flavored_milk', 'Chocolate', NULL, NULL, 200.00, 'ml', 7, 2.00, 6.00, 'bottle', 'box', 1, 0.00, 15.00, 0.00, 1, '2026-02-20 16:44:30', '2026-02-20 16:44:30'),
(18, 'FM0004', 'Basta', 'flavored_milk', 'Yippe', NULL, NULL, 1000.00, 'ml', 7, 2.00, 6.00, 'bottle', 'box', 1, 0.00, 45.00, 0.00, 1, '2026-02-20 16:44:30', '2026-02-20 16:44:30'),
(19, 'FM0005', 'Basta', 'flavored_milk', 'Yippe', NULL, NULL, 500.00, 'ml', 7, 2.00, 6.00, 'bottle', 'box', 1, 0.00, 25.00, 0.00, 1, '2026-02-20 16:44:30', '2026-02-20 16:44:30'),
(21, 'PM0002', 'MilkBarBisaya', 'pasteurized_milk', 'IkawBahala', 1, '', 50.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 10, 50.00, 50.00, 0.00, 1, '2026-02-20 17:18:26', '2026-02-20 22:22:52'),
(22, 'FM0006', 'AnotherTesting', 'flavored_milk', 'Yippe', 1, '', 250.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 10, 60.00, 60.00, 0.00, 1, '2026-02-20 23:26:06', '2026-02-20 23:29:34'),
(23, 'PM0003', 'MilkBar', 'pasteurized_milk', 'Chocolate', 1, 'Test', 100.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 1, 0.00, 50.00, 0.00, 1, '2026-02-20 23:37:12', '2026-02-20 23:37:12'),
(24, 'FM0007', 'Milkbar-UBE', 'flavored_milk', 'Ube', 2, '', 60.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 1, 0.00, 50.00, 0.00, 1, '2026-02-21 01:15:11', '2026-02-21 01:15:11'),
(26, 'FM0008', 'Milkbar-UBE', 'flavored_milk', 'Ube', 2, '', 75.00, 'ml', 7, 2.00, 6.00, 'piece', 'box', 1, 0.00, 60.00, 0.00, 1, '2026-02-21 01:15:54', '2026-02-21 01:15:54');

-- --------------------------------------------------------

--
-- Table structure for table `product_prices`
--

CREATE TABLE `product_prices` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price_type` enum('retail','wholesale','special') DEFAULT 'retail',
  `unit_price` decimal(12,2) NOT NULL,
  `selling_price` decimal(12,2) NOT NULL,
  `min_quantity` int(11) DEFAULT 1,
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_prices`
--

INSERT INTO `product_prices` (`id`, `product_id`, `price_type`, `unit_price`, `selling_price`, `min_quantity`, `effective_date`, `end_date`, `is_active`, `created_by`, `created_at`) VALUES
(1, 1, 'retail', 95.00, 105.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24'),
(2, 2, 'retail', 55.00, 60.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24'),
(3, 3, 'retail', 40.00, 45.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24'),
(4, 4, 'retail', 45.00, 50.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24'),
(5, 5, 'retail', 45.00, 50.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24'),
(6, 6, 'retail', 150.00, 175.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24'),
(7, 7, 'retail', 120.00, 140.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24'),
(8, 8, 'retail', 80.00, 95.00, 1, '2026-02-03', NULL, 1, NULL, '2026-02-03 09:35:24');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(30) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `status` enum('draft','pending','approved','ordered','partial_received','received','cancelled') DEFAULT 'pending',
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `vat_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `payment_terms` enum('cash','credit_7','credit_15','credit_30','credit_45','credit_60') DEFAULT 'cash',
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `requisition_id` int(11) DEFAULT NULL,
  `purchase_request_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `order_date`, `expected_delivery`, `status`, `subtotal`, `vat_amount`, `total_amount`, `payment_status`, `payment_terms`, `due_date`, `notes`, `requisition_id`, `purchase_request_id`, `created_by`, `approved_by`, `approved_at`, `received_at`, `created_at`, `updated_at`) VALUES
(1, '5231', 1, '2025-01-04', '2025-01-11', 'received', 29750.00, 0.00, 29750.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(2, '5232', 2, '2025-01-07', '2025-01-14', 'received', 102000.00, 0.00, 102000.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(3, '5233', 1, '2025-01-08', '2025-01-15', 'received', 59500.00, 0.00, 59500.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(4, '5234', 2, '2025-01-09', '2025-01-16', 'received', 83400.00, 0.00, 83400.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(5, '5235', 3, '2025-01-14', '2025-01-21', 'received', 13600.00, 1632.00, 15232.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(6, '5236', 1, '2025-01-11', '2025-01-18', 'received', 29750.00, 0.00, 29750.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(7, '5237', 2, '2025-01-15', '2025-01-22', 'received', 105000.00, 0.00, 105000.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(8, '5238', 3, '2025-01-17', '2025-01-24', 'received', 40388.25, 0.00, 40388.25, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(9, '5239', 1, '2025-01-15', '2025-01-22', 'received', 59500.00, 0.00, 59500.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(10, '5240', 3, '2024-11-19', '2024-11-26', 'received', 600000.00, 0.00, 600000.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(11, '5241', 4, '2025-01-17', '2025-01-24', 'received', 28000.00, 0.00, 28000.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(12, '5242', 1, '2025-01-18', '2025-01-25', 'received', 64796.00, 0.00, 64796.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(13, '5243', 1, '2025-01-21', '2025-01-28', 'received', 49980.00, 0.00, 49980.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(14, '5244', 1, '2025-01-22', '2025-01-29', 'received', 17850.00, 0.00, 17850.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(15, '5245', 5, '2025-01-24', '2025-01-31', 'received', 56000.00, 0.00, 56000.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(16, '5246', 6, '2025-01-24', '2025-01-31', 'received', 61000.00, 0.00, 61000.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(17, '5247', 1, '2025-01-24', '2025-01-31', 'received', 59500.00, 0.00, 59500.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(18, '5248', 2, '2025-01-24', '2025-01-31', 'received', 158500.00, 0.00, 158500.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(19, '5249', 2, '2025-01-27', '2025-02-03', 'received', 87000.00, 0.00, 87000.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(20, '5250', 1, '2025-01-29', '2025-02-05', 'received', 44625.00, 0.00, 44625.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(21, '5251', 2, '2025-01-31', '2025-02-07', 'received', 112500.00, 0.00, 112500.00, 'paid', 'cash', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(22, '5252', 5, '2026-02-10', '2026-02-11', 'approved', 50.00, 0.00, 50.00, 'unpaid', 'cash', NULL, 'Basta', NULL, NULL, 10, 8, '2026-02-22 17:30:56', NULL, '2026-02-10 10:33:34', '2026-02-22 09:30:56'),
(23, '5253', 5, '2026-02-22', '2026-02-25', 'cancelled', 0.00, 0.00, 0.00, 'unpaid', 'credit_30', '2026-03-24', 'Basta\n[REJECTED: Basta]', 1, NULL, 10, NULL, NULL, NULL, '2026-02-22 09:14:03', '2026-05-02 18:05:03'),
(24, '5254', 4, '2026-02-22', NULL, 'cancelled', 50.00, 0.00, 50.00, 'unpaid', 'credit_30', '2026-03-24', 'Paldo\n[REJECTED: Basta]', NULL, NULL, 10, NULL, NULL, NULL, '2026-02-22 09:14:40', '2026-05-02 18:05:01'),
(25, '5255', 2, '2026-04-27', '2026-04-30', 'received', 0.00, 0.00, 0.00, 'unpaid', 'credit_7', '2026-05-04', NULL, 3, NULL, 10, 8, '2026-04-27 15:19:53', '2026-04-27 15:21:17', '2026-04-27 07:18:27', '2026-04-27 07:21:17'),
(26, '5256', 5, '2026-05-03', NULL, 'received', 50.00, 0.00, 50.00, 'paid', 'credit_30', '2026-06-02', NULL, 3, 1, 10, 8, '2026-05-03 14:04:21', '2026-05-03 14:23:40', '2026-05-03 06:01:00', '2026-05-03 07:39:53'),
(27, '5257', 5, '2026-05-03', NULL, 'ordered', 8000.00, 0.00, 8000.00, 'unpaid', 'credit_30', '2026-06-02', NULL, NULL, 2, 10, 8, '2026-05-03 16:17:12', NULL, '2026-05-03 07:59:09', '2026-05-03 08:17:53'),
(28, '5258', 5, '2026-05-03', NULL, 'received', 8000.00, 0.00, 8000.00, 'unpaid', 'credit_30', '2026-06-02', NULL, NULL, 3, 10, 8, '2026-05-03 20:13:01', '2026-05-03 20:14:18', '2026-05-03 12:12:37', '2026-05-03 12:14:18'),
(29, '5259', 1, '2026-05-03', NULL, 'received', 3000.00, 0.00, 3000.00, 'unpaid', 'credit_30', '2026-06-02', NULL, NULL, 4, 10, 8, '2026-05-03 20:23:42', '2026-05-03 20:24:46', '2026-05-03 12:23:23', '2026-05-03 12:24:46'),
(30, '5260', 5, '2026-05-03', NULL, 'received', 3200.00, 0.00, 3200.00, 'unpaid', 'credit_30', '2026-06-02', NULL, NULL, 5, 10, 8, '2026-05-03 22:06:30', '2026-05-03 22:08:06', '2026-05-03 14:04:09', '2026-05-03 14:08:06'),
(31, '5261', 4, '2026-05-03', '2026-05-04', 'received', 640.00, 0.00, 640.00, 'unpaid', 'credit_30', '2026-06-02', NULL, NULL, 6, 10, 8, '2026-05-03 22:14:05', '2026-05-03 22:15:37', '2026-05-03 14:13:41', '2026-05-03 14:15:37'),
(32, '5262', 3, '2026-05-03', '2026-05-20', 'received', 700.00, 0.00, 700.00, 'unpaid', 'credit_30', '2026-06-02', NULL, NULL, 7, 10, 8, '2026-05-03 22:28:25', '2026-05-03 22:30:13', '2026-05-03 14:27:48', '2026-05-03 14:30:13');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
  `mro_item_id` int(11) DEFAULT NULL,
  `item_description` varchar(200) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `quantity_received` decimal(12,2) DEFAULT 0.00,
  `quantity_rejected` decimal(12,2) DEFAULT 0.00,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `is_vat_item` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `po_id`, `ingredient_id`, `mro_item_id`, `item_description`, `quantity`, `unit`, `unit_price`, `total_amount`, `quantity_received`, `quantity_rejected`, `rejection_reason`, `is_vat_item`, `notes`, `created_at`) VALUES
(1, 1, NULL, NULL, 'BOTTLES 1000ml', 5950.00, 'PCS', 4.38, 26061.00, 5950.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(2, 1, NULL, NULL, 'CAPS', 5950.00, 'PCS', 0.62, 3689.00, 5950.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(3, 2, NULL, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3400.00, 102000.00, 30.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(4, 3, NULL, NULL, 'BOTTLES 1000ml', 11900.00, 'PCS', 4.38, 52122.00, 11900.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(5, 3, NULL, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 11900.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(6, 4, NULL, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2780.00, 83400.00, 30.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(7, 5, NULL, NULL, 'RIBBON ROLL', 20.00, 'ROLL', 680.00, 13600.00, 20.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(8, 5, NULL, NULL, 'PLUS VAT', 1.00, '-', 1632.00, 1632.00, 1.00, 0.00, NULL, 1, NULL, '2026-02-03 08:17:18'),
(9, 6, NULL, NULL, 'BOTTLES 1000ml', 5950.00, 'PCS', 4.38, 26061.00, 5950.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(10, 6, NULL, NULL, 'CAPS', 5950.00, 'PCS', 0.62, 3689.00, 5950.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(11, 7, NULL, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3500.00, 105000.00, 30.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(12, 8, NULL, NULL, 'LINX SOLVENT', 6.00, 'BOTS', 2315.25, 13891.50, 6.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(13, 8, NULL, NULL, 'LINX INK', 5.00, 'BOTS', 5299.35, 26496.75, 5.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(14, 9, NULL, NULL, 'BOTTLES 1000ml', 11900.00, 'PCS', 4.38, 52122.00, 11900.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(15, 9, NULL, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 11900.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(16, 10, NULL, NULL, 'TT500 THERMA', 5.00, 'Unit', 120000.00, 600000.00, 5.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(17, 11, NULL, NULL, 'BROWN SUGAR', 10.00, 'SCKS', 2800.00, 28000.00, 10.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(18, 12, NULL, NULL, 'BOTTLES 1000ml', 5950.00, 'PCS', 4.38, 26061.00, 5950.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(19, 12, NULL, NULL, 'BOTTLES 500ml', 6570.00, 'PCS', 2.38, 15636.60, 6570.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(20, 12, NULL, NULL, 'BOTTLES 330ml', 5680.00, 'PCS', 2.08, 11814.40, 5680.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(21, 12, NULL, NULL, 'CAPS', 18200.00, 'PCS', 0.62, 11284.00, 18200.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(22, 13, NULL, NULL, 'BOTTLES 1000ml', 9996.00, 'PCS', 4.38, 43782.48, 9996.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(23, 13, NULL, NULL, 'CAPS', 9996.00, 'PCS', 0.62, 6197.52, 9996.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(24, 14, NULL, NULL, 'BOTTLES 1000ml', 3570.00, 'PCS', 4.38, 15636.60, 3570.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(25, 14, NULL, NULL, 'CAPS', 3570.00, 'PCS', 0.62, 2213.40, 3570.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(26, 15, NULL, NULL, 'CAUSTIC SODA', 20.00, 'SCKS', 2800.00, 56000.00, 20.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(27, 16, NULL, NULL, 'CHLORINIX', 10.00, 'BOXES', 800.00, 8000.00, 10.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(28, 16, NULL, NULL, 'LINOL-LIQUID D', 10.00, 'BOXES', 1400.00, 14000.00, 10.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(29, 16, NULL, NULL, 'ADVACIP 200', 10.00, 'CAR', 3900.00, 39000.00, 10.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(30, 17, NULL, NULL, 'BOTTLES 1000ml', 11900.00, 'PCS', 4.38, 52122.00, 11900.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(31, 17, NULL, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 11900.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(32, 18, NULL, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2850.00, 85500.00, 30.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(33, 18, NULL, NULL, 'WHITE SUGAR', 20.00, 'SCKS', 3650.00, 73000.00, 20.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(34, 19, NULL, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2900.00, 87000.00, 30.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(35, 20, NULL, NULL, 'BOTTLES 1000ml', 8925.00, 'PCS', 4.38, 39091.50, 8925.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(36, 20, NULL, NULL, 'CAPS', 8925.00, 'PCS', 0.62, 5533.50, 8925.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(37, 21, NULL, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3750.00, 112500.00, 30.00, 0.00, NULL, 0, NULL, '2026-02-03 08:17:18'),
(38, 22, NULL, NULL, 'Yehey', 1.00, 'pcs', 50.00, 50.00, 0.00, 0.00, NULL, 0, NULL, '2026-02-10 10:33:34'),
(39, 23, NULL, NULL, 'Yehey', 1.00, 'pcs', 0.00, 0.00, 0.00, 0.00, NULL, 0, NULL, '2026-02-22 09:14:03'),
(40, 24, NULL, NULL, 'Yehey', 1.00, 'pcs', 50.00, 50.00, 0.00, 0.00, NULL, 0, NULL, '2026-02-22 09:14:40'),
(41, 25, NULL, NULL, 'test', 1.00, 'pcs', 0.00, 0.00, 1.00, 0.00, NULL, 0, NULL, '2026-04-27 07:18:27'),
(42, 25, NULL, NULL, 'Test', 1.00, 'pcs', 0.00, 0.00, 1.00, 0.00, NULL, 0, NULL, '2026-04-27 07:18:27'),
(43, 26, NULL, 2, 'Homogenizer Valve', 1.00, 'pcs', 50.00, 50.00, 1.00, 0.00, NULL, 0, NULL, '2026-05-03 06:01:00'),
(44, 27, 11, NULL, 'Chocolate Powder X', 25.00, 'kg', 320.00, 8000.00, 0.00, 0.00, NULL, 0, NULL, '2026-05-03 07:59:09'),
(45, 28, 11, NULL, 'Chocolate Powder X', 25.00, 'kg', 320.00, 8000.00, 20.00, 5.00, 'Basta', 0, NULL, '2026-05-03 12:12:37'),
(46, 29, 13, NULL, 'Cultures (Yogurt)', 20.00, 'packet', 150.00, 3000.00, 15.00, 5.00, 'basta', 0, NULL, '2026-05-03 12:23:23'),
(47, 30, 11, NULL, 'Chocolate Powder X', 10.00, 'kg', 320.00, 3200.00, 8.00, 2.00, 'Basta', 0, NULL, '2026-05-03 14:04:09'),
(48, 31, 11, NULL, 'Chocolate Powder X', 2.00, 'kg', 320.00, 640.00, 2.00, 0.00, NULL, 0, NULL, '2026-05-03 14:13:41'),
(49, 32, 16, NULL, 'Food Coloring', 2.00, 'liter', 350.00, 700.00, 1.00, 1.00, 'Damaged Packaging', 0, NULL, '2026-05-03 14:27:48');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `pr_number` varchar(30) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `department` varchar(50) NOT NULL DEFAULT 'warehouse_raw',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `needed_by_date` date DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `pr_number`, `requested_by`, `department`, `priority`, `needed_by_date`, `purpose`, `notes`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 'PR-20260501-001', 4, 'warehouse_raw', 'high', '2026-05-15', 'Sugar Stock', NULL, 'approved', 8, '2026-05-03 13:58:25', NULL, '2026-05-01 08:55:36', '2026-05-03 05:58:25'),
(2, 'PR-20260503-001', 4, 'warehouse_raw', 'high', '2026-05-08', 'Reorder alert: Chocolate Powder X', 'Auto-created from Reorder Alerts. Current stock: 5 kg. Reorder point: 30 kg. Status: CRITICAL.', 'approved', 8, '2026-05-03 15:51:30', NULL, '2026-05-03 07:50:25', '2026-05-03 07:51:30'),
(3, 'PR-20260503-002', 4, 'warehouse_raw', 'high', '2026-05-08', 'Reorder alert: Chocolate Powder X', 'Auto-created from Reorder Alerts. Current stock: 5 kg. Reorder point: 30 kg. Status: CRITICAL.', 'approved', 8, '2026-05-03 20:12:12', NULL, '2026-05-03 12:11:54', '2026-05-03 12:12:12'),
(4, 'PR-20260503-003', 4, 'warehouse_raw', 'high', '2026-05-17', 'Reorder alert: Cultures (Yogurt)', 'Auto-created from Reorder Alerts. Current stock: 10 packet. Reorder point: 30 packet. Status: CRITICAL.', 'approved', 8, '2026-05-03 20:23:05', NULL, '2026-05-03 12:22:52', '2026-05-03 12:23:05'),
(5, 'PR-20260503-004', 4, 'warehouse_raw', 'high', '2026-05-08', 'Reorder alert: Chocolate Powder X', 'Auto-created from Reorder Alerts. Current stock: 20 kg. Reorder point: 30 kg. Status: CRITICAL.', 'approved', 8, '2026-05-03 22:03:38', NULL, '2026-05-03 14:02:58', '2026-05-03 14:03:38'),
(6, 'PR-20260503-005', 4, 'warehouse_raw', 'normal', '2026-05-08', 'Reorder alert: Chocolate Powder X', 'Auto-created from Reorder Alerts. Current stock: 28 kg. Reorder point: 30 kg. Status: LOW.', 'approved', 8, '2026-05-03 22:12:54', NULL, '2026-05-03 14:12:42', '2026-05-03 14:12:54'),
(7, 'PR-20260503-006', 4, 'warehouse_raw', 'high', '2026-05-08', 'Reorder alert: Food Coloring', 'Auto-created from Reorder Alerts. Current stock: 1 liter. Reorder point: 3 liter. Status: CRITICAL.', 'approved', 8, '2026-05-03 22:27:20', NULL, '2026-05-03 14:27:01', '2026-05-03 14:27:20');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_request_items`
--

CREATE TABLE `purchase_request_items` (
  `id` int(11) NOT NULL,
  `purchase_request_id` int(11) NOT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
  `mro_item_id` int(11) DEFAULT NULL,
  `item_description` varchar(200) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'units',
  `estimated_unit_price` decimal(12,2) DEFAULT NULL,
  `estimated_total` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_request_items`
--

INSERT INTO `purchase_request_items` (`id`, `purchase_request_id`, `ingredient_id`, `mro_item_id`, `item_description`, `quantity`, `unit`, `estimated_unit_price`, `estimated_total`, `notes`, `created_at`) VALUES
(1, 1, NULL, NULL, 'Basta', 5.00, 'kg', NULL, NULL, 'Test', '2026-05-01 08:55:36'),
(2, 2, 11, NULL, 'Chocolate Powder X', 25.00, 'kg', 320.00, 8000.00, 'Generated from reorder alert for ING-003.', '2026-05-03 07:50:25'),
(3, 3, 11, NULL, 'Chocolate Powder X', 25.00, 'kg', 320.00, 8000.00, 'Generated from reorder alert for ING-003.', '2026-05-03 12:11:54'),
(4, 4, 13, NULL, 'Cultures (Yogurt)', 20.00, 'packet', 150.00, 3000.00, 'Generated from reorder alert for ING-005.', '2026-05-03 12:22:52'),
(5, 5, 11, NULL, 'Chocolate Powder X', 10.00, 'kg', 320.00, 3200.00, 'Generated from reorder alert for ING-003.', '2026-05-03 14:02:58'),
(6, 6, 11, NULL, 'Chocolate Powder X', 2.00, 'kg', 320.00, 640.00, 'Generated from reorder alert for ING-003.', '2026-05-03 14:12:42'),
(7, 7, 16, NULL, 'Food Coloring', 2.00, 'liter', 350.00, 700.00, 'Generated from reorder alert for ING-008.', '2026-05-03 14:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `qc_batch_release`
--

CREATE TABLE `qc_batch_release` (
  `id` int(11) NOT NULL,
  `release_code` varchar(30) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `inspection_datetime` datetime NOT NULL,
  `sensory_appearance` enum('pass','fail','acceptable') DEFAULT 'pass',
  `sensory_odor` enum('pass','fail','acceptable') DEFAULT 'pass',
  `sensory_taste` enum('pass','fail','acceptable') DEFAULT 'pass',
  `sensory_texture` enum('pass','fail','acceptable') DEFAULT 'pass',
  `sensory_notes` text DEFAULT NULL,
  `packaging_integrity` enum('pass','fail') DEFAULT 'pass',
  `label_accuracy` enum('pass','fail') DEFAULT 'pass',
  `seal_quality` enum('pass','fail') DEFAULT 'pass',
  `date_code_correct` tinyint(1) DEFAULT 1,
  `ccp_records_complete` tinyint(1) DEFAULT 0,
  `ccp_all_passed` tinyint(1) DEFAULT 0,
  `sample_retained` tinyint(1) DEFAULT 0,
  `sample_location` varchar(100) DEFAULT NULL,
  `release_decision` enum('approved','rejected','hold') NOT NULL,
  `rejection_reason` text DEFAULT NULL,
  `corrective_action` text DEFAULT NULL,
  `inspected_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_datetime` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_batch_release`
--

INSERT INTO `qc_batch_release` (`id`, `release_code`, `batch_id`, `inspection_datetime`, `sensory_appearance`, `sensory_odor`, `sensory_taste`, `sensory_texture`, `sensory_notes`, `packaging_integrity`, `label_accuracy`, `seal_quality`, `date_code_correct`, `ccp_records_complete`, `ccp_all_passed`, `sample_retained`, `sample_location`, `release_decision`, `rejection_reason`, `corrective_action`, `inspected_by`, `approved_by`, `approval_datetime`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'QCR-20260203-001', 1, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(2, 'QCR-20260203-002', 2, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(3, 'QCR-20260203-003', 3, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(4, 'QCR-20260203-004', 4, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(5, 'QCR-20260203-005', 5, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(6, 'QCR-20260203-006', 6, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(7, 'QCR-20260203-007', 7, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(8, 'QCR-20260203-008', 8, '2026-02-03 17:35:24', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 1, 1, '2026-02-03 17:35:24', NULL, '2026-02-03 09:35:24', '2026-02-03 09:35:24'),
(9, 'QCR-20260205-0014', 14, '2026-02-05 16:34:29', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-05 08:34:29', '2026-02-05 08:34:29'),
(10, 'QCR-20260205-0015', 15, '2026-02-05 19:17:02', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-05 11:17:02', '2026-02-05 11:17:02'),
(11, 'QCR-20260209-0016', 16, '2026-02-09 14:49:49', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-09 06:49:49', '2026-02-09 06:49:49'),
(12, 'QCR-20260209-0017', 17, '2026-02-09 21:15:19', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-09 13:15:19', '2026-02-09 13:15:19'),
(13, 'QCR-20260211-0018', 18, '2026-02-11 02:30:27', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-10 18:30:27', '2026-02-10 18:30:27'),
(14, 'QCR-20260211-0019', 19, '2026-02-11 02:50:20', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-10 18:50:20', '2026-02-10 18:50:20'),
(15, 'QCR-20260211-0020', 20, '2026-02-11 02:57:36', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-10 18:57:36', '2026-02-10 18:57:36'),
(16, 'QCR-20260211-0021', 21, '2026-02-11 12:03:42', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-11 04:03:42', '2026-02-11 04:03:42');

-- --------------------------------------------------------

--
-- Table structure for table `qc_milk_tests`
--

CREATE TABLE `qc_milk_tests` (
  `id` int(11) NOT NULL,
  `test_code` varchar(30) NOT NULL,
  `receiving_id` int(11) NOT NULL COMMENT 'Links to milk_receiving',
  `test_datetime` datetime NOT NULL,
  `milk_type_id` int(11) NOT NULL,
  `fat_percentage` decimal(5,2) DEFAULT NULL,
  `titratable_acidity` decimal(5,4) DEFAULT NULL COMMENT 'TA %',
  `acidity_ph` decimal(4,2) DEFAULT NULL,
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `specific_gravity` decimal(6,4) DEFAULT NULL,
  `protein_percentage` decimal(5,2) DEFAULT NULL,
  `snf_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Solids-Not-Fat',
  `lactose_percentage` decimal(5,2) DEFAULT NULL,
  `total_solids_percentage` decimal(5,2) DEFAULT NULL,
  `added_water_percentage` decimal(5,2) DEFAULT NULL,
  `freezing_point` decimal(6,4) DEFAULT NULL,
  `sediment_level` enum('clean','slight','moderate','heavy') DEFAULT 'clean',
  `sediment_grade` tinyint(1) DEFAULT 1,
  `apt_result` enum('positive','negative') DEFAULT NULL COMMENT 'Antibiotic Presence Test',
  `alcohol_test` enum('pass','fail') DEFAULT NULL,
  `clot_on_boiling` enum('pass','fail') DEFAULT NULL,
  `grade` varchar(20) DEFAULT NULL COMMENT 'A, B, C, Rejected',
  `base_price_per_liter` decimal(10,2) NOT NULL,
  `fat_adjustment` decimal(10,2) DEFAULT 0.00,
  `quality_adjustment` decimal(10,2) DEFAULT 0.00,
  `acidity_deduction` decimal(10,2) DEFAULT 0.00,
  `sediment_deduction` decimal(10,2) DEFAULT 0.00,
  `final_price_per_liter` decimal(10,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `is_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `rejection_reason` text DEFAULT NULL,
  `tested_by` int(11) NOT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'QC supervisor verification',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_milk_tests`
--

INSERT INTO `qc_milk_tests` (`id`, `test_code`, `receiving_id`, `test_datetime`, `milk_type_id`, `fat_percentage`, `titratable_acidity`, `acidity_ph`, `temperature_celsius`, `specific_gravity`, `protein_percentage`, `snf_percentage`, `lactose_percentage`, `total_solids_percentage`, `added_water_percentage`, `freezing_point`, `sediment_level`, `sediment_grade`, `apt_result`, `alcohol_test`, `clot_on_boiling`, `grade`, `base_price_per_liter`, `fat_adjustment`, `quality_adjustment`, `acidity_deduction`, `sediment_deduction`, `final_price_per_liter`, `total_amount`, `is_accepted`, `rejection_reason`, `tested_by`, `verified_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'QC-2025-10-21-001', 1, '2025-10-21 09:00:00', 1, 2.50, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 39.25, 0.00, 0.00, 0.00, 0.00, 39.25, 2158.75, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 2.5%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(2, 'QC-2025-10-21-002', 2, '2025-10-21 09:00:00', 1, 5.00, 0.2000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.00, 0.00, 0.00, 0.00, 0.00, 40.00, 3980.00, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 5%, TA: 0.2%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(3, 'QC-2025-10-21-003', 3, '2025-10-21 09:00:00', 1, 5.00, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.25, 0.00, 0.00, 0.00, 0.00, 40.25, 663.49, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 5%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(4, 'QC-2025-10-21-004', 4, '2025-10-21 09:00:00', 1, 2.90, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 39.25, 0.00, 0.00, 0.00, 0.00, 39.25, 2992.23, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 2.9%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(5, 'QC-2025-10-21-005', 5, '2025-10-21 09:00:00', 1, 4.40, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.00, 0.00, 0.00, 0.00, 0.00, 40.00, 1942.55, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 4.4%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(6, 'QC-2025-10-21-006', 6, '2025-10-21 09:00:00', 1, 3.70, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 39.75, 0.00, 0.00, 0.00, 0.00, 39.75, 1306.98, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 3.7%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(7, 'QC-2025-10-21-007', 7, '2025-10-21 09:00:00', 1, 3.60, 0.1800, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.00, 0.00, 0.00, 0.00, 0.00, 40.00, 23920.00, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 3.6%, TA: 0.18%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(8, 'QC-2025-10-21-008', 8, '2025-10-21 09:00:00', 1, 4.00, 0.1800, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 70.00, 0.00, 0.00, 0.00, 0.00, 70.00, 1820.00, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 4%, TA: 0.18%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(9, 'QC-2025-10-21-009', 9, '2025-10-21 09:00:00', 1, 2.80, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 39.25, 0.00, 0.00, 0.00, 0.00, 39.25, 4867.00, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 2.8%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(10, 'QC-2025-10-21-010', 10, '2025-10-21 09:00:00', 1, 3.60, 0.2000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 39.50, 0.00, 0.00, 0.00, 0.00, 39.50, 7681.15, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 3.6%, TA: 0.2%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(11, 'QC-2025-10-21-011', 11, '2025-10-21 09:00:00', 2, 2.50, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 69.25, 0.00, 0.00, 0.00, 0.00, 69.25, 543.72, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 2.5%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(12, 'QC-2025-10-21-012', 12, '2025-10-21 09:00:00', 1, 3.90, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 39.75, 0.00, 0.00, 0.00, 0.00, 39.75, 5731.23, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 3.9%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(13, 'QC-2025-10-21-013', 13, '2025-10-21 09:00:00', 1, 5.00, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.25, 0.00, 0.00, 0.00, 0.00, 40.25, 1636.52, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 5%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(14, 'QC-2025-10-21-014', 14, '2025-10-21 09:00:00', 1, 4.80, 0.1800, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.25, 0.00, 0.00, 0.00, 0.00, 40.25, 3545.78, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 4.8%, TA: 0.18%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(15, 'QC-2025-10-21-015', 15, '2025-10-21 09:00:00', 1, 4.40, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.00, 0.00, 0.00, 0.00, 0.00, 40.00, 6697.63, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 4.4%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(16, 'QC-2025-10-21-016', 16, '2025-10-21 09:00:00', 1, 3.70, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 39.75, 0.00, 0.00, 0.00, 0.00, 39.75, 15424.33, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 3.7%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(17, 'QC-2025-10-21-017', 17, '2025-10-21 09:00:00', 1, 5.00, 0.1900, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'A', 40.25, 0.00, 0.00, 0.00, 0.00, 40.25, 3974.39, 1, NULL, 2, NULL, 'Sediment: G-1, FAT: 5%, TA: 0.19%', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(18, 'QCT-000001', 19, '2026-02-03 22:00:16', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-02-03 14:00:16', '2026-02-03 14:00:16'),
(19, 'QCT-000002', 20, '2026-02-05 14:03:13', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-02-05 06:03:13', '2026-02-05 06:03:13'),
(20, 'QCT-000003', 21, '2026-02-05 18:56:14', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-02-05 10:56:14', '2026-02-05 10:56:14'),
(21, 'QCT-000004', 22, '2026-02-05 18:57:34', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-02-05 10:57:34', '2026-02-05 10:57:34'),
(22, 'QCT-000005', 23, '2026-02-09 21:10:29', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 3000.00, 1, NULL, 2, NULL, 'Test', '2026-02-09 13:10:29', '2026-02-09 13:10:29'),
(23, 'QCT-000006', 27, '2026-02-11 02:26:40', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-02-10 18:26:40', '2026-02-10 18:26:40'),
(24, 'QCT-000007', 28, '2026-02-11 02:53:03', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, 'Basta', '2026-02-10 18:53:03', '2026-02-10 18:53:03'),
(25, 'QCT-000008', 29, '2026-02-11 02:53:16', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 3, NULL, NULL, NULL, 'D', 30.00, 0.00, 0.00, 0.00, 1.00, 29.00, 14500.00, 1, NULL, 2, NULL, 'Basta', '2026-02-10 18:53:16', '2026-02-10 18:53:16'),
(26, 'QCT-000009', 32, '2026-03-13 20:47:58', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-03-13 12:47:58', '2026-03-13 12:47:58'),
(27, 'QCT-000010', 33, '2026-03-28 15:15:59', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1200.00, 1, NULL, 2, NULL, '', '2026-03-28 07:15:59', '2026-03-28 07:15:59'),
(28, 'QCT-000011', 34, '2026-03-28 15:29:45', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-03-28 07:29:45', '2026-03-28 07:29:45'),
(29, 'QCT-000012', 35, '2026-03-28 15:34:42', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-03-28 07:34:42', '2026-03-28 07:34:42'),
(30, 'QCT-000013', 36, '2026-04-26 17:33:41', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-04-26 09:33:41', '2026-04-26 09:33:41');

-- --------------------------------------------------------

--
-- Table structure for table `qc_test_parameters`
--

CREATE TABLE `qc_test_parameters` (
  `id` int(11) NOT NULL,
  `parameter_name` varchar(100) NOT NULL,
  `category` enum('raw_milk','pasteurized','finished_goods','packaging') NOT NULL DEFAULT 'raw_milk',
  `unit` varchar(50) DEFAULT NULL,
  `min_value` decimal(10,4) DEFAULT NULL,
  `max_value` decimal(10,4) DEFAULT NULL,
  `target_value` decimal(10,4) DEFAULT NULL,
  `test_method` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_test_parameters`
--

INSERT INTO `qc_test_parameters` (`id`, `parameter_name`, `category`, `unit`, `min_value`, `max_value`, `target_value`, `test_method`, `description`, `is_mandatory`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Fat Content', 'raw_milk', '%', 3.0000, NULL, 3.5000, 'Gerber Method', 'Percentage of milk fat', 1, 'active', '2026-02-03 14:43:08', NULL),
(2, 'SNF (Solids-Not-Fat)', 'raw_milk', '%', 8.0000, NULL, 8.5000, 'Lactometer Reading', 'Non-fat solids content', 1, 'active', '2026-02-03 14:43:08', NULL),
(3, 'Temperature', 'raw_milk', '??C', NULL, 10.0000, 4.0000, 'Digital Thermometer', 'Milk temperature at collection', 1, 'active', '2026-02-03 14:43:08', NULL),
(4, 'Acidity', 'raw_milk', '% LA', 0.1200, 0.1600, 0.1400, 'Titration Method', 'Lactic acid percentage', 1, 'active', '2026-02-03 14:43:08', NULL),
(5, 'Density', 'raw_milk', 'g/ml', 1.0260, 1.0320, 1.0290, 'Lactometer', 'Specific gravity of milk', 1, 'active', '2026-02-03 14:43:08', NULL),
(6, 'Alcohol Test', 'raw_milk', 'Result', NULL, NULL, NULL, '68% Alcohol Test', 'Clot on Boiling / Alcohol Test', 1, 'active', '2026-02-03 14:43:08', NULL),
(7, 'Organoleptic', 'raw_milk', 'Result', NULL, NULL, NULL, 'Sensory Evaluation', 'Color, smell, taste, appearance', 1, 'active', '2026-02-03 14:43:08', NULL),
(8, 'MBRT', 'raw_milk', 'hours', 5.0000, NULL, 6.0000, 'Methylene Blue Reduction', 'Bacterial load indicator', 1, 'active', '2026-02-03 14:43:08', NULL),
(9, 'Pasteurization Temp', 'pasteurized', '??C', 72.0000, 75.0000, 73.0000, 'Inline Temperature Sensor', 'Heat treatment temperature', 1, 'active', '2026-02-03 14:43:08', NULL),
(10, 'Pasteurization Time', 'pasteurized', 'seconds', 15.0000, NULL, 15.0000, 'Timer', 'Holding time at pasteurization temp', 1, 'active', '2026-02-03 14:43:08', NULL),
(11, 'Phosphatase Test', 'pasteurized', 'Result', NULL, NULL, NULL, 'Phosphatase Test Kit', 'Verify complete pasteurization', 1, 'active', '2026-02-03 14:43:08', NULL),
(12, 'Coliform Count', 'finished_goods', 'CFU/ml', NULL, 10.0000, 0.0000, 'Plate Count Method', 'Coliform bacteria count', 1, 'active', '2026-02-03 14:43:08', NULL),
(13, 'TPC', 'finished_goods', 'CFU/ml', NULL, 30000.0000, NULL, 'Standard Plate Count', 'Total Plate Count', 1, 'active', '2026-02-03 14:43:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `raw_milk_inventory`
--

CREATE TABLE `raw_milk_inventory` (
  `id` int(11) NOT NULL,
  `batch_code` varchar(30) NOT NULL,
  `receiving_id` int(11) NOT NULL COMMENT 'Source receiving record',
  `qc_test_id` int(11) NOT NULL COMMENT 'QC test that approved this batch',
  `milk_type_id` int(11) NOT NULL,
  `tank_id` int(11) DEFAULT NULL,
  `volume_liters` decimal(10,2) NOT NULL,
  `remaining_liters` decimal(10,2) NOT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date NOT NULL COMMENT 'Raw milk expires in 2-3 days',
  `fat_percentage` decimal(5,2) DEFAULT NULL,
  `grade` varchar(20) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL COMMENT 'Cost per liter',
  `status` enum('available','reserved','in_production','depleted','expired') DEFAULT 'available',
  `qc_status` enum('approved') DEFAULT 'approved' COMMENT 'Only approved milk enters inventory',
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `disposed_liters` decimal(10,2) DEFAULT 0.00,
  `disposal_id` int(11) DEFAULT NULL,
  `disposed_at` datetime DEFAULT NULL,
  `disposal_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `raw_milk_inventory`
--

INSERT INTO `raw_milk_inventory` (`id`, `batch_code`, `receiving_id`, `qc_test_id`, `milk_type_id`, `tank_id`, `volume_liters`, `remaining_liters`, `received_date`, `expiry_date`, `fat_percentage`, `grade`, `unit_cost`, `status`, `qc_status`, `received_by`, `notes`, `created_at`, `updated_at`, `disposed_liters`, `disposal_id`, `disposed_at`, `disposal_reason`) VALUES
(1, 'RAW-20260203-599', 19, 18, 1, 10, 50.00, 0.00, '2026-02-03', '2026-02-05', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-03 14:00:16', '2026-02-05 06:29:48', 0.00, NULL, NULL, NULL),
(2, 'RAW-20260205-090', 20, 19, 1, 10, 50.00, 0.00, '2026-02-05', '2026-02-07', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-05 06:03:13', '2026-02-05 06:29:48', 0.00, NULL, NULL, NULL),
(3, 'RAW-20260205-100', 1, 1, 1, 10, 200.00, 0.00, '2026-02-05', '2026-02-08', NULL, NULL, NULL, 'depleted', 'approved', NULL, NULL, '2026-02-05 06:07:40', '2026-02-05 10:25:32', 0.00, NULL, NULL, NULL),
(4, 'RAW-20260205-045', 21, 20, 1, 10, 50.00, 0.00, '2026-02-05', '2026-02-07', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-05 10:56:14', '2026-02-05 10:59:03', 0.00, NULL, NULL, NULL),
(5, 'RAW-20260205-911', 22, 21, 1, 10, 50.00, 0.00, '2026-02-05', '2026-02-07', 3.75, 'B', 30.00, '', 'approved', 2, NULL, '2026-02-05 10:57:34', '2026-02-11 04:21:00', 50.00, 8, '2026-02-11 12:21:00', 'TEST'),
(6, 'RAW-20260209-281', 23, 22, 1, 10, 100.00, 0.00, '2026-02-09', '2026-02-11', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-09 13:10:29', '2026-02-10 18:28:39', 0.00, NULL, NULL, NULL),
(7, 'RAW-20260211-914', 27, 23, 1, 10, 50.00, 0.00, '2026-02-11', '2026-02-13', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-10 18:26:40', '2026-02-10 18:55:42', 0.00, NULL, NULL, NULL),
(8, 'RAW-20260211-691', 28, 24, 1, 10, 50.00, 0.00, '2026-02-11', '2026-02-13', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-10 18:53:03', '2026-02-10 18:55:42', 0.00, NULL, NULL, NULL),
(9, 'RAW-20260211-944', 29, 25, 1, 10, 500.00, 100.00, '2026-02-11', '2026-02-13', 3.75, 'D', 29.00, 'available', 'approved', 2, NULL, '2026-02-10 18:53:16', '2026-02-10 18:55:42', 0.00, NULL, NULL, NULL),
(10, 'RAW-20260313-538', 32, 26, 1, 10, 50.00, 50.00, '2026-03-13', '2026-03-15', 3.75, 'B', 30.00, 'available', 'approved', 2, NULL, '2026-03-13 12:47:58', '2026-03-28 07:16:50', 0.00, NULL, NULL, NULL),
(11, 'RAW-20260328-495', 33, 27, 1, 10, 40.00, 40.00, '2026-03-28', '2026-03-30', 3.75, 'B', 30.00, 'available', 'approved', 2, NULL, '2026-03-28 07:15:59', '2026-03-28 07:16:36', 0.00, NULL, NULL, NULL),
(12, 'RAW-RCV-000002', 2, 2, 1, 10, 112.00, 112.00, '2025-10-21', '2025-10-23', 5.00, 'A', 40.00, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-002', '2026-03-28 07:22:40', '2026-03-28 07:22:54', 0.00, NULL, NULL, NULL),
(13, 'RAW-RCV-000004', 4, 4, 1, 10, 93.00, 93.00, '2025-10-21', '2025-10-23', 2.90, 'A', 39.25, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-004', '2026-03-28 07:22:40', '2026-03-28 07:24:50', 0.00, NULL, NULL, NULL),
(14, 'RAW-RCV-000005', 5, 5, 1, 10, 59.00, 59.00, '2025-10-21', '2025-10-23', 4.40, 'A', 40.00, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-005', '2026-03-28 07:22:40', '2026-03-28 07:25:17', 0.00, NULL, NULL, NULL),
(15, 'RAW-RCV-000006', 6, 6, 1, 10, 40.00, 40.00, '2025-10-21', '2025-10-23', 3.70, 'A', 39.75, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-006', '2026-03-28 07:22:40', '2026-03-28 07:27:29', 0.00, NULL, NULL, NULL),
(16, 'RAW-RCV-000007', 7, 7, 1, 10, 598.00, 598.00, '2025-10-21', '2025-10-23', 3.60, 'A', 40.00, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-007', '2026-03-28 07:22:40', '2026-04-26 09:34:28', 0.00, NULL, NULL, NULL),
(17, 'RAW-RCV-000008', 8, 8, 1, NULL, 26.00, 26.00, '2025-10-21', '2025-10-23', 4.00, 'A', 70.00, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-008', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(18, 'RAW-RCV-000009', 9, 9, 1, NULL, 124.00, 124.00, '2025-10-21', '2025-10-23', 2.80, 'A', 39.25, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-009', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(19, 'RAW-RCV-000010', 10, 10, 1, NULL, 201.00, 201.00, '2025-10-21', '2025-10-23', 3.60, 'A', 39.50, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-010', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(20, 'RAW-RCV-000011', 11, 11, 2, NULL, 8.00, 8.00, '2025-10-21', '2025-10-23', 2.50, 'A', 69.25, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-011', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(21, 'RAW-RCV-000012', 12, 12, 1, NULL, 92.00, 92.00, '2025-10-21', '2025-10-23', 3.90, 'A', 39.75, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-012', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(22, 'RAW-RCV-000013', 13, 13, 1, NULL, 42.00, 42.00, '2025-10-21', '2025-10-23', 5.00, 'A', 40.25, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-013', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(23, 'RAW-RCV-000014', 14, 14, 1, NULL, 91.00, 91.00, '2025-10-21', '2025-10-23', 4.80, 'A', 40.25, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-014', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(24, 'RAW-RCV-000015', 15, 15, 1, NULL, 146.00, 146.00, '2025-10-21', '2025-10-23', 4.40, 'A', 40.00, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-015', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(25, 'RAW-RCV-000016', 16, 16, 1, 9, 401.00, 401.00, '2025-10-21', '2025-10-23', 3.70, 'A', 39.75, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-016', '2026-03-28 07:22:40', '2026-03-28 07:23:27', 0.00, NULL, NULL, NULL),
(26, 'RAW-RCV-000017', 17, 17, 1, NULL, 102.00, 102.00, '2025-10-21', '2025-10-23', 5.00, 'A', 40.25, 'available', 'approved', 2, 'Backfilled from accepted QC receiving RCV-2025-10-21-017', '2026-03-28 07:22:40', '2026-03-28 07:22:40', 0.00, NULL, NULL, NULL),
(27, 'RAW-RCV-000034', 34, 28, 1, 10, 50.00, 50.00, '2026-03-28', '2026-03-30', 3.75, 'B', 30.00, 'available', 'approved', 2, NULL, '2026-03-28 07:29:45', '2026-03-28 07:30:27', 0.00, NULL, NULL, NULL),
(28, 'RAW-RCV-000035', 35, 29, 1, 10, 50.00, 50.00, '2026-03-28', '2026-03-30', 3.75, 'B', 30.00, 'available', 'approved', 2, NULL, '2026-03-28 07:34:42', '2026-03-28 07:35:21', 0.00, NULL, NULL, NULL),
(29, 'RAW-RCV-000036', 36, 30, 1, NULL, 50.00, 50.00, '2026-04-26', '2026-04-28', 3.75, 'B', 30.00, 'available', 'approved', 2, NULL, '2026-04-26 09:33:41', '2026-04-26 09:33:41', 0.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `recall_activity_log`
--

CREATE TABLE `recall_activity_log` (
  `id` int(11) NOT NULL,
  `recall_id` int(11) NOT NULL,
  `action` enum('created','updated','approved','rejected','notification_sent','return_logged','completed','cancelled','note_added') NOT NULL,
  `action_by` int(11) NOT NULL,
  `action_at` datetime NOT NULL DEFAULT current_timestamp(),
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recall_activity_log`
--

INSERT INTO `recall_activity_log` (`id`, `recall_id`, `action`, `action_by`, `action_at`, `details`, `notes`) VALUES
(1, 1, 'created', 2, '2026-05-03 16:36:50', '{\"recall_class\":\"class_i\",\"reason\":\"BVasta\"}', NULL),
(2, 1, 'approved', 8, '2026-05-03 16:37:20', '{\"old_status\": \"pending_approval\", \"new_status\": \"approved\"}', NULL),
(3, 1, 'updated', 8, '2026-05-03 16:38:11', '{\"old_status\": \"approved\", \"new_status\": \"in_progress\"}', NULL),
(4, 1, 'return_logged', 2, '2026-05-03 16:38:11', '{\"location\":\"Distribution Points (Manual Tracking Required)\",\"units\":\"50\"}', NULL),
(5, 1, 'return_logged', 2, '2026-05-03 16:38:23', '{\"location\":\"Distribution Points (Manual Tracking Required)\",\"units\":\"13\"}', NULL),
(6, 1, 'return_logged', 2, '2026-05-03 16:38:58', '{\"location\":\"Distribution Points (Manual Tracking Required)\",\"units\":\"100\"}', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `recall_affected_locations`
--

CREATE TABLE `recall_affected_locations` (
  `id` int(11) NOT NULL,
  `recall_id` int(11) NOT NULL,
  `location_type` enum('store','distributor','direct_customer','internal') NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `location_name` varchar(255) NOT NULL,
  `location_address` text DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `dispatch_reference` varchar(100) DEFAULT NULL,
  `units_dispatched` int(11) NOT NULL DEFAULT 0,
  `units_returned` int(11) NOT NULL DEFAULT 0,
  `units_destroyed_onsite` int(11) NOT NULL DEFAULT 0,
  `units_consumed` int(11) NOT NULL DEFAULT 0,
  `units_unaccounted` int(11) GENERATED ALWAYS AS (`units_dispatched` - `units_returned` - `units_destroyed_onsite` - `units_consumed`) STORED,
  `notification_sent` tinyint(1) NOT NULL DEFAULT 0,
  `notification_sent_at` datetime DEFAULT NULL,
  `notification_method` enum('sms','email','phone','in_person') DEFAULT NULL,
  `notification_sent_by` int(11) DEFAULT NULL,
  `acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by_name` varchar(255) DEFAULT NULL,
  `return_status` enum('pending','partial','complete','none','destroyed_onsite') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recall_affected_locations`
--

INSERT INTO `recall_affected_locations` (`id`, `recall_id`, `location_type`, `location_id`, `location_name`, `location_address`, `contact_person`, `contact_phone`, `contact_email`, `dispatch_date`, `dispatch_reference`, `units_dispatched`, `units_returned`, `units_destroyed_onsite`, `units_consumed`, `notification_sent`, `notification_sent_at`, `notification_method`, `notification_sent_by`, `acknowledged`, `acknowledged_at`, `acknowledged_by_name`, `return_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'internal', NULL, 'Distribution Points (Manual Tracking Required)', NULL, NULL, NULL, NULL, NULL, NULL, 13, 163, 0, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, 'complete', 'Delivery records not linked to this batch. Please add affected locations manually.', '2026-05-03 16:36:50', '2026-05-03 16:38:58');

-- --------------------------------------------------------

--
-- Table structure for table `recall_returns`
--

CREATE TABLE `recall_returns` (
  `id` int(11) NOT NULL,
  `recall_id` int(11) NOT NULL,
  `affected_location_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `units_returned` int(11) NOT NULL,
  `condition_status` enum('good','damaged','spoiled','unknown') NOT NULL DEFAULT 'unknown',
  `condition_notes` text DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `disposal_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recall_returns`
--

INSERT INTO `recall_returns` (`id`, `recall_id`, `affected_location_id`, `return_date`, `units_returned`, `condition_status`, `condition_notes`, `received_by`, `received_at`, `disposal_id`, `created_at`) VALUES
(1, 1, 1, '2026-05-03', 50, 'damaged', 'Wew', 2, '2026-05-03 16:38:11', NULL, '2026-05-03 16:38:11'),
(2, 1, 1, '2026-05-03', 13, 'damaged', 'Basta', 2, '2026-05-03 16:38:23', NULL, '2026-05-03 16:38:23'),
(3, 1, 1, '2026-05-03', 100, 'damaged', NULL, 2, '2026-05-03 16:38:58', NULL, '2026-05-03 16:38:58');

--
-- Triggers `recall_returns`
--
DELIMITER $$
CREATE TRIGGER `tr_recall_return_update` AFTER INSERT ON `recall_returns` FOR EACH ROW BEGIN
    
    UPDATE recall_affected_locations 
    SET units_returned = units_returned + NEW.units_returned,
        return_status = CASE 
            WHEN (units_returned + NEW.units_returned) >= units_dispatched THEN 'complete'
            WHEN (units_returned + NEW.units_returned) > 0 THEN 'partial'
            ELSE 'pending'
        END
    WHERE id = NEW.affected_location_id;
    
    
    UPDATE batch_recalls 
    SET total_recovered = (
        SELECT COALESCE(SUM(units_returned), 0) 
        FROM recall_affected_locations 
        WHERE recall_id = NEW.recall_id
    )
    WHERE id = NEW.recall_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `recipe_ingredients`
--

CREATE TABLE `recipe_ingredients` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
  `ingredient_name` varchar(100) NOT NULL,
  `ingredient_category` enum('milk','sugar','flavoring','powder','culture','rennet','salt','packaging','other') NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `is_optional` tinyint(1) DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipe_ingredients`
--

INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `ingredient_id`, `ingredient_name`, `ingredient_category`, `quantity`, `unit`, `is_optional`, `notes`) VALUES
(1, 12, 9, 'Sugar', 'sugar', 0.500, 'kg', 0, 'White sugar'),
(2, 12, 10, 'Vanilla Extract', 'flavoring', 0.020, 'L', 0, 'Pure vanilla'),
(3, 13, 9, 'Sugar', 'sugar', 0.250, 'kg', 0, 'White sugar'),
(4, 13, 10, 'Vanilla Extract', 'flavoring', 0.010, 'L', 0, 'Pure vanilla'),
(5, 14, 11, 'Chocolate Powder', 'powder', 0.500, 'kg', 0, 'Cocoa powder'),
(6, 14, 9, 'Sugar', 'sugar', 0.800, 'kg', 0, 'White sugar'),
(7, 14, 12, 'Stabilizer', 'other', 0.050, 'kg', 0, 'Carrageenan'),
(8, 15, 13, 'Cultures (Yogurt)', 'culture', 0.010, 'kg', 0, 'Yogurt starter culture'),
(9, 15, 9, 'Sugar', 'sugar', 0.100, 'kg', 0, 'Optional sweetener'),
(10, 16, 10, 'Vanilla Extract', 'flavoring', 0.005, 'L', 0, 'For flavor'),
(11, 16, 12, 'Stabilizer', 'other', 0.020, 'kg', 0, 'Texture enhancer'),
(13, 17, 12, 'Stabilizer', '', 0.010, 'kg', 0, 'For consistency'),
(14, 19, 14, 'Salt', '', 1.000, 'kg', 0, 'basta'),
(15, 21, 15, 'Rennet', '', 1.000, 'liter', 0, ''),
(16, 21, 11, 'Chocolate Powder X', '', 1.000, 'kg', 0, ''),
(17, 22, 15, 'Rennet', '', 50.000, 'liter', 0, 'Morning'),
(18, 23, 9, 'Sugar', '', 1.000, 'kg', 0, 'test');

-- --------------------------------------------------------

--
-- Table structure for table `repair_parts_used`
--

CREATE TABLE `repair_parts_used` (
  `id` int(11) NOT NULL,
  `repair_id` int(11) NOT NULL,
  `mro_item_id` int(11) NOT NULL,
  `mro_inventory_id` int(11) DEFAULT NULL COMMENT 'Specific batch used',
  `quantity_used` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`quantity_used` * `unit_cost`) STORED,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--

CREATE TABLE `requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `item_type` enum('raw_milk','pasteurized_milk','ingredient','packaging','mro') DEFAULT 'ingredient',
  `item_id` int(11) DEFAULT NULL,
  `item_code` varchar(30) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `milk_type_id` int(11) DEFAULT NULL COMMENT 'For milk items',
  `requested_quantity` decimal(10,2) NOT NULL,
  `issued_quantity` decimal(10,2) DEFAULT 0.00,
  `unit_of_measure` varchar(20) DEFAULT 'units',
  `status` enum('pending','partial','fulfilled','cancelled') DEFAULT 'pending',
  `fulfilled_from_batch` varchar(50) DEFAULT NULL COMMENT 'Batch code issued from',
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_items`
--

INSERT INTO `requisition_items` (`id`, `requisition_id`, `item_type`, `item_id`, `item_code`, `item_name`, `milk_type_id`, `requested_quantity`, `issued_quantity`, `unit_of_measure`, `status`, `fulfilled_from_batch`, `fulfilled_by`, `fulfilled_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'ingredient', 1, 'ING-001', 'Sugar', NULL, 10.00, 0.00, 'kg', 'pending', NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-03 08:46:55'),
(2, 1, 'ingredient', 2, 'ING-002', 'Vanilla Extract', NULL, 2.00, 0.00, 'liter', 'pending', NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-03 08:46:55'),
(3, 1, 'raw_milk', NULL, NULL, 'Raw Cow Milk', NULL, 500.00, 0.00, 'liters', 'pending', NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-03 08:46:55'),
(4, 2, 'mro', 1, 'MRO-001', 'Pasteurizer Gasket Set', NULL, 1.00, 1.00, 'set', 'fulfilled', NULL, 4, '2026-02-05 13:42:49', NULL, '2026-02-03 08:46:55', '2026-02-05 05:42:49'),
(5, 2, 'mro', 7, 'MRO-007', 'Food Grade Lubricant', NULL, 2.00, 2.00, 'liter', 'fulfilled', NULL, 4, '2026-02-05 13:42:49', NULL, '2026-02-03 08:46:55', '2026-02-05 05:42:49'),
(6, 3, 'ingredient', 5, 'ING-005', 'Cultures (Yogurt)', NULL, 5.00, 0.00, 'packet', 'pending', NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-03 08:46:55'),
(7, 3, 'ingredient', 4, 'ING-004', 'Stabilizer', NULL, 2.00, 0.00, 'kg', 'pending', NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-03 08:46:55'),
(8, 4, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 100.00, 100.00, 'liters', 'fulfilled', NULL, 4, '2026-02-05 14:05:17', '', '2026-02-05 05:47:17', '2026-02-05 06:08:37'),
(9, 5, 'ingredient', 0, NULL, 'Raw', NULL, 50.00, 50.00, 'liters', 'fulfilled', NULL, 4, '2026-02-05 18:25:26', '', '2026-02-05 05:54:55', '2026-02-05 10:25:26'),
(10, 6, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 50.00, 50.00, 'liters', 'fulfilled', NULL, 4, '2026-02-05 14:10:16', '', '2026-02-05 06:09:28', '2026-02-05 06:10:16'),
(11, 7, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 100.00, 100.00, 'liters', 'fulfilled', NULL, 4, '2026-02-05 18:25:32', '', '2026-02-05 10:24:54', '2026-02-05 10:25:32'),
(12, 8, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 50.00, 50.00, 'liters', 'fulfilled', NULL, 4, '2026-02-05 18:59:03', '', '2026-02-05 10:58:37', '2026-02-05 10:59:03'),
(13, 9, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 50.00, 50.00, 'liters', 'fulfilled', NULL, 4, '2026-02-09 21:11:11', 'Basta', '2026-02-09 13:09:20', '2026-02-09 13:11:11'),
(14, 10, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 50.00, 50.00, 'liters', 'fulfilled', NULL, 4, '2026-02-11 02:28:39', '', '2026-02-10 18:27:46', '2026-02-10 18:28:39'),
(15, 11, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 500.00, 500.00, 'liters', 'fulfilled', NULL, 4, '2026-02-11 02:55:42', '', '2026-02-10 18:55:14', '2026-02-10 18:55:42');

-- --------------------------------------------------------

--
-- Table structure for table `sales_customer_sub_accounts`
--

CREATE TABLE `sales_customer_sub_accounts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sub_name` varchar(200) NOT NULL COMMENT 'School name or branch name',
  `address` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='For Feeding Program sub-accounts like individual schools under DepEd';

--
-- Dumping data for table `sales_customer_sub_accounts`
--

INSERT INTO `sales_customer_sub_accounts` (`id`, `customer_id`, `sub_name`, `address`, `contact_person`, `contact_number`, `status`, `created_at`) VALUES
(1, 6, 'Lumbia Elementary School', 'Lumbia, CDO', 'Juan Cruz', '09181234567', 'active', '2026-02-05 08:08:04'),
(2, 6, 'Macabalan Elementary School', 'Macabalan, CDO', 'Ana Reyes', '09191234567', 'active', '2026-02-05 08:08:04'),
(3, 6, 'Lapasan National High School', 'Lapasan, CDO', 'Pedro Garcia', '09201234567', 'active', '2026-02-05 08:08:04'),
(4, 6, 'Bulua Elementary School', 'Bulua, CDO', 'Elena Torres', '09211234567', 'active', '2026-02-05 08:08:04'),
(5, 6, 'Kauswagan Central School', 'Kauswagan, CDO', 'Roberto Lim', '09221234567', 'active', '2026-02-05 08:08:04');

-- --------------------------------------------------------

--
-- Table structure for table `sales_invoices`
--

CREATE TABLE `sales_invoices` (
  `id` int(11) NOT NULL,
  `csi_number` varchar(30) NOT NULL,
  `invoice_number` varchar(30) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `dr_id` int(11) DEFAULT NULL,
  `dr_number` varchar(50) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `balance_due` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `status` enum('active','voided') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `void_reason` text DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `last_payment_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_invoice_items`
--

CREATE TABLE `sales_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_invoice_payments`
--

CREATE TABLE `sales_invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `status` enum('active','voided') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `check_number` varchar(50) DEFAULT NULL,
  `check_date` date DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `sub_account_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_type` enum('supermarket','school','feeding_program','restaurant','distributor','walk_in','other') NOT NULL DEFAULT 'other',
  `customer_po_number` varchar(50) DEFAULT NULL COMMENT 'Customer PO reference',
  `payment_type` enum('cash','credit') NOT NULL DEFAULT 'cash',
  `payment_terms_days` int(11) DEFAULT 0 COMMENT '0 = cash, 15/30/60 for credit',
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `total_quantity` int(11) NOT NULL DEFAULT 0,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `balance_due` decimal(12,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL COMMENT 'For credit sales',
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `status` enum('draft','pending','approved','picking','preparing','ready','dispatched','delivered','accepted','partially_accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('normal','rush','urgent') NOT NULL DEFAULT 'normal',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `order_number`, `customer_id`, `sub_account_id`, `customer_name`, `customer_type`, `customer_po_number`, `payment_type`, `payment_terms_days`, `contact_person`, `contact_number`, `delivery_address`, `delivery_date`, `total_items`, `total_quantity`, `subtotal`, `discount_percent`, `tax_amount`, `discount_amount`, `total_amount`, `amount_paid`, `balance_due`, `due_date`, `payment_status`, `status`, `priority`, `created_by`, `approved_by`, `approved_at`, `assigned_to`, `notes`, `cancellation_reason`, `cancelled_by`, `cancelled_at`, `created_at`, `updated_at`) VALUES
(2, 'SO-20260205-001', 5, NULL, '', 'other', NULL, 'cash', 0, NULL, NULL, 'Hotel 101 Tacloban', '2026-02-05', 0, 0, 1235.00, 0.00, 0.00, 0.00, 1235.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-05 08:59:12', '2026-02-09 06:14:39'),
(3, 'SO-20260205-002', 6, 5, '', 'other', NULL, 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-05', 0, 0, 2940.00, 0.00, 0.00, 0.00, 2940.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-05 11:18:00', '2026-02-09 06:14:36'),
(4, 'SO-20260209-001', 6, NULL, '', 'other', 'yehey', 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-09', 0, 0, 1615.00, 0.00, 0.00, 0.00, 1615.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-09 05:16:24', '2026-02-09 06:14:34'),
(5, 'SO-20260209-002', 5, NULL, '', 'other', 'Basta', 'cash', 0, NULL, NULL, 'Hotel 101 Tacloban', '2026-02-09', 0, 0, 2280.00, 0.00, 0.00, 0.00, 2280.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-09 05:25:10', '2026-02-09 06:14:27'),
(6, 'SO-20260209-003', 5, NULL, '', 'other', '50', 'cash', 0, NULL, NULL, 'Hotel 101 Tacloban', '2026-02-09', 0, 0, 1235.00, 0.00, 0.00, 0.00, 1235.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-09 05:30:33', '2026-02-09 06:14:25'),
(7, 'SO-20260209-004', 5, NULL, '', 'other', NULL, 'cash', 0, NULL, NULL, 'Hotel 101 Tacloban', '2026-02-09', 0, 0, 12350.00, 0.00, 0.00, 0.00, 12350.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-09 06:14:55', '2026-02-09 06:15:55'),
(8, 'SO-20260209-005', 3, NULL, '', 'other', NULL, 'cash', 0, NULL, NULL, 'Downtown Tacloban', '2026-02-09', 0, 0, 12350.00, 0.00, 0.00, 0.00, 12350.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-09 14:43:36', NULL, '', NULL, NULL, NULL, '2026-02-09 06:17:20', '2026-02-09 09:41:02'),
(9, 'SO-20260209-006', 5, NULL, '', 'other', '500', 'cash', 0, NULL, NULL, 'Hotel 101 Tacloban', '2026-02-09', 0, 0, 12350.00, 0.00, 0.00, 0.00, 12350.00, 0.00, 0.00, NULL, 'unpaid', 'cancelled', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-09 06:20:07', '2026-02-09 06:20:18'),
(10, 'SO-20260209-007', 3, NULL, '', 'other', '100', 'cash', 0, NULL, NULL, 'Downtown Tacloban', '2026-02-09', 0, 0, 4200.00, 0.00, 0.00, 0.00, 4200.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-09 14:43:34', NULL, '', NULL, NULL, NULL, '2026-02-09 06:23:16', '2026-02-09 09:26:24'),
(11, 'SO-20260209-008', 4, NULL, '', 'other', '123456', 'cash', 0, NULL, NULL, 'Real Street Tacloban', '2026-02-09', 0, 0, 585.00, 0.00, 0.00, 0.00, 585.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-09 17:34:15', NULL, '', NULL, NULL, NULL, '2026-02-09 09:33:47', '2026-02-09 10:28:08'),
(12, 'SO-20260209-009', 6, NULL, '', 'other', '500', 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-09', 0, 0, 2940.00, 0.00, 0.00, 0.00, 2940.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-09 17:49:43', NULL, '', NULL, NULL, NULL, '2026-02-09 09:48:41', '2026-02-09 10:02:13'),
(13, 'SO-20260209-010', 2, NULL, '', 'other', NULL, 'cash', 0, NULL, NULL, 'Robinsons Place Tacloban', '2026-02-09', 0, 0, 990.00, 0.00, 0.00, 0.00, 990.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-09 21:19:59', NULL, '', NULL, NULL, NULL, '2026-02-09 13:19:41', '2026-02-09 13:22:01'),
(14, 'SO-20260211-001', 6, NULL, '', 'other', NULL, 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-10', 0, 0, 1140.00, 0.00, 0.00, 0.00, 1140.00, 0.00, 0.00, NULL, 'unpaid', 'dispatched', 'normal', 6, 8, '2026-02-11 02:11:53', NULL, '', NULL, NULL, NULL, '2026-02-10 18:11:34', '2026-02-11 03:01:16'),
(15, 'SO-20260211-002', 5, NULL, '', 'other', '123456', 'cash', 0, NULL, NULL, 'Hotel 101 Tacloban', '2026-02-11', 0, 0, 2800.00, 0.00, 0.00, 0.00, 2800.00, 0.00, 0.00, NULL, 'unpaid', 'dispatched', 'normal', 6, 8, '2026-02-11 10:43:10', NULL, '', NULL, NULL, NULL, '2026-02-11 02:42:51', '2026-02-11 06:05:55'),
(16, 'SO-20260211-003', 6, NULL, '', 'other', '65764456', 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-11', 0, 0, 1575.00, 0.00, 0.00, 0.00, 1575.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-11 11:57:59', NULL, '', NULL, NULL, NULL, '2026-02-11 03:55:39', '2026-02-11 06:04:59'),
(17, 'SO-20260211-004', 6, NULL, '', 'other', NULL, 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-11', 0, 0, 1260.00, 0.00, 0.00, 0.00, 1260.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-11 12:28:37', NULL, '', NULL, NULL, NULL, '2026-02-11 04:27:21', '2026-02-11 06:04:57'),
(18, 'SO-20260221-001', 6, NULL, '', 'other', '34989483943', 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-20', 0, 0, 2500.00, 0.00, 0.00, 0.00, 2500.00, 0.00, 0.00, NULL, 'unpaid', 'cancelled', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-20 17:51:54', '2026-02-20 17:52:02'),
(19, 'SO-20260221-002', 1, NULL, '', 'other', 'Basta', 'cash', 0, NULL, NULL, 'SM Tacloban', '2026-02-20', 0, 0, 2500.00, 0.00, 0.00, 0.00, 2500.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-21 01:52:47', NULL, '', NULL, NULL, NULL, '2026-02-20 17:52:25', '2026-02-20 22:16:48'),
(20, 'SO-20260221-003', 3, NULL, '', 'other', '9894859489534', 'cash', 0, NULL, NULL, 'Downtown Tacloban', '2026-02-20', 0, 0, 3000.00, 0.00, 0.00, 0.00, 3000.00, 0.00, 0.00, NULL, 'unpaid', 'delivered', 'normal', 6, 8, '2026-02-21 07:34:23', NULL, '', NULL, NULL, NULL, '2026-02-20 23:33:34', '2026-02-20 23:35:37'),
(22, 'SO-20260328-001', 6, NULL, '', 'other', 'Basta', 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-03-28', 0, 0, 1000.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 0.00, NULL, 'unpaid', 'dispatched', 'normal', 6, 8, '2026-03-28 15:49:30', NULL, '', NULL, NULL, NULL, '2026-03-28 07:49:02', '2026-03-28 07:52:20');

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_items`
--

CREATE TABLE `sales_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `variant` varchar(100) DEFAULT NULL,
  `size_value` decimal(10,2) NOT NULL,
  `size_unit` varchar(10) NOT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_boxes` int(11) DEFAULT 0,
  `quantity_pieces` int(11) DEFAULT 0,
  `unit_type` enum('box','piece','mixed') DEFAULT 'box',
  `quantity_fulfilled` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `status` enum('pending','partial','fulfilled','out_of_stock','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_order_items`
--

INSERT INTO `sales_order_items` (`id`, `order_id`, `product_id`, `product_name`, `variant`, `size_value`, `size_unit`, `quantity_ordered`, `quantity_boxes`, `quantity_pieces`, `unit_type`, `quantity_fulfilled`, `unit_price`, `line_total`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 13, 1, 1, 'mixed', 0, 95.00, 1235.00, 'pending', NULL, '2026-02-05 08:59:12', '2026-02-05 08:59:12'),
(2, 3, 7, 'Butter 250g', NULL, 250.00, 'g', 21, 1, 1, 'mixed', 0, 140.00, 2940.00, 'pending', NULL, '2026-02-05 11:18:00', '2026-02-05 11:18:00'),
(3, 4, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 17, 1, 5, 'mixed', 0, 95.00, 1615.00, 'pending', NULL, '2026-02-09 05:16:24', '2026-02-09 05:16:24'),
(4, 5, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 24, 1, 12, 'mixed', 0, 95.00, 2280.00, 'pending', NULL, '2026-02-09 05:25:10', '2026-02-09 05:25:10'),
(5, 6, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 13, 1, 1, 'mixed', 0, 95.00, 1235.00, 'pending', NULL, '2026-02-09 05:30:33', '2026-02-09 05:30:33'),
(6, 7, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 130, 10, 10, 'mixed', 0, 95.00, 12350.00, 'pending', NULL, '2026-02-09 06:14:55', '2026-02-09 06:14:55'),
(7, 8, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 130, 10, 10, 'mixed', 0, 95.00, 12350.00, 'pending', NULL, '2026-02-09 06:17:20', '2026-02-09 06:17:20'),
(8, 9, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 130, 10, 10, 'mixed', 0, 95.00, 12350.00, 'pending', NULL, '2026-02-09 06:20:07', '2026-02-09 06:20:07'),
(9, 10, 7, 'Butter 250g', NULL, 250.00, 'g', 30, 1, 10, 'mixed', 0, 140.00, 4200.00, 'pending', NULL, '2026-02-09 06:23:16', '2026-02-09 06:23:16'),
(10, 11, 3, 'Chocolate Milk 1L', NULL, 1000.00, 'ml', 13, 1, 1, 'mixed', 0, 45.00, 585.00, 'pending', NULL, '2026-02-09 09:33:47', '2026-02-09 09:33:47'),
(11, 12, 7, 'Butter 250g', NULL, 250.00, 'g', 21, 1, 1, 'mixed', 0, 140.00, 2940.00, 'pending', NULL, '2026-02-09 09:48:41', '2026-02-09 09:48:41'),
(12, 13, 3, 'Chocolate Milk 1L', NULL, 1000.00, 'ml', 22, 1, 10, 'mixed', 0, 45.00, 990.00, 'pending', NULL, '2026-02-09 13:19:41', '2026-02-09 13:19:41'),
(13, 14, 8, 'Fresh Cream 1L', NULL, 1000.00, 'ml', 12, 1, 0, 'box', 0, 95.00, 1140.00, 'pending', NULL, '2026-02-10 18:11:34', '2026-02-10 18:11:34'),
(14, 15, 7, 'Butter 250g', NULL, 250.00, 'g', 20, 1, 0, 'box', 0, 140.00, 2800.00, 'pending', NULL, '2026-02-11 02:42:51', '2026-02-11 02:42:51'),
(15, 16, 1, 'Fresh Milk 1L', NULL, 1000.00, 'ml', 15, 1, 3, 'mixed', 0, 105.00, 1575.00, 'pending', NULL, '2026-02-11 03:55:39', '2026-02-11 03:55:39'),
(16, 17, 1, 'Fresh Milk 1L', NULL, 1000.00, 'ml', 12, 1, 0, 'box', 0, 105.00, 1260.00, 'pending', NULL, '2026-02-11 04:27:21', '2026-02-11 04:27:21'),
(17, 18, 21, 'MilkBarBisaya', NULL, 50.00, 'ml', 50, 0, 50, 'piece', 0, 50.00, 2500.00, 'pending', NULL, '2026-02-20 17:51:54', '2026-02-20 17:51:54'),
(18, 19, 21, 'MilkBarBisaya', NULL, 50.00, 'ml', 50, 0, 50, 'piece', 0, 50.00, 2500.00, 'pending', NULL, '2026-02-20 17:52:25', '2026-02-20 17:52:25'),
(19, 20, 22, 'AnotherTesting', NULL, 250.00, 'ml', 50, 0, 50, 'piece', 0, 60.00, 3000.00, 'pending', NULL, '2026-02-20 23:33:34', '2026-02-20 23:33:34'),
(21, 22, 21, 'MilkBarBisaya', NULL, 50.00, 'ml', 20, 2, 0, 'box', 0, 50.00, 1000.00, 'pending', NULL, '2026-03-28 07:49:02', '2026-03-28 07:49:02');

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_status_history`
--

CREATE TABLE `sales_order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_order_status_history`
--

INSERT INTO `sales_order_status_history` (`id`, `order_id`, `status`, `notes`, `changed_by`, `created_at`) VALUES
(1, 2, 'draft', 'Order created', 6, '2026-02-05 08:59:12'),
(2, 2, 'pending', NULL, 8, '2026-02-05 10:10:06'),
(3, 2, 'approved', NULL, 8, '2026-02-05 10:10:33'),
(4, 3, 'draft', 'Order created', 6, '2026-02-05 11:18:00'),
(5, 3, 'pending', NULL, 6, '2026-02-05 11:18:04'),
(6, 3, 'approved', NULL, 6, '2026-02-05 11:18:11'),
(7, 4, 'draft', 'Order created', 6, '2026-02-09 05:16:24'),
(8, 4, 'pending', NULL, 6, '2026-02-09 05:16:31'),
(9, 4, 'approved', NULL, 6, '2026-02-09 05:16:34'),
(10, 5, 'draft', 'Order created', 6, '2026-02-09 05:25:10'),
(11, 5, 'pending', NULL, 6, '2026-02-09 05:25:13'),
(12, 5, 'approved', NULL, 6, '2026-02-09 05:25:18'),
(13, 6, 'draft', 'Order created', 6, '2026-02-09 05:30:33'),
(14, 6, 'pending', NULL, 6, '2026-02-09 05:30:37'),
(15, 6, 'approved', NULL, 6, '2026-02-09 05:30:41'),
(16, 6, 'delivered', NULL, 6, '2026-02-09 06:14:25'),
(17, 5, 'delivered', NULL, 6, '2026-02-09 06:14:27'),
(18, 4, 'delivered', NULL, 6, '2026-02-09 06:14:34'),
(19, 3, 'delivered', NULL, 6, '2026-02-09 06:14:36'),
(20, 2, 'delivered', NULL, 6, '2026-02-09 06:14:39'),
(21, 7, 'draft', 'Order created', 6, '2026-02-09 06:14:55'),
(22, 7, 'pending', NULL, 6, '2026-02-09 06:15:03'),
(23, 7, 'approved', NULL, 6, '2026-02-09 06:15:07'),
(24, 7, 'delivered', NULL, 6, '2026-02-09 06:15:55'),
(25, 8, 'draft', 'Order created', 6, '2026-02-09 06:17:20'),
(26, 9, 'draft', 'Order created', 6, '2026-02-09 06:20:07'),
(27, 9, 'pending', NULL, 6, '2026-02-09 06:20:11'),
(28, 9, 'cancelled', NULL, 6, '2026-02-09 06:20:18'),
(29, 8, 'pending', NULL, 6, '2026-02-09 06:20:21'),
(30, 10, 'draft', 'Order created', 6, '2026-02-09 06:23:16'),
(31, 10, 'pending', NULL, 6, '2026-02-09 06:23:18'),
(32, 10, 'approved', 'Order approved', 8, '2026-02-09 06:43:34'),
(33, 8, 'approved', 'Order approved', 8, '2026-02-09 06:43:36'),
(34, 10, 'delivered', NULL, 6, '2026-02-09 09:26:24'),
(35, 11, 'draft', 'Order created', 6, '2026-02-09 09:33:47'),
(36, 11, 'pending', NULL, 6, '2026-02-09 09:33:50'),
(37, 11, 'approved', 'Order approved', 8, '2026-02-09 09:34:15'),
(38, 12, 'draft', 'Order created', 6, '2026-02-09 09:48:41'),
(39, 12, 'pending', NULL, 6, '2026-02-09 09:48:46'),
(40, 12, 'approved', 'Order approved', 8, '2026-02-09 09:49:43'),
(41, 13, 'draft', 'Order created', 6, '2026-02-09 13:19:41'),
(42, 13, 'pending', NULL, 6, '2026-02-09 13:19:45'),
(43, 13, 'approved', 'Order approved', 8, '2026-02-09 13:19:59'),
(44, 14, 'draft', 'Order created', 6, '2026-02-10 18:11:34'),
(45, 14, 'pending', NULL, 6, '2026-02-10 18:11:38'),
(46, 14, 'approved', 'Order approved', 8, '2026-02-10 18:11:54'),
(47, 15, 'draft', 'Order created', 6, '2026-02-11 02:42:51'),
(48, 15, 'pending', NULL, 6, '2026-02-11 02:42:57'),
(49, 15, 'approved', 'Order approved', 8, '2026-02-11 02:43:10'),
(50, 16, 'draft', 'Order created', 6, '2026-02-11 03:55:39'),
(51, 16, 'pending', NULL, 6, '2026-02-11 03:56:14'),
(52, 16, 'approved', 'Order approved', 8, '2026-02-11 03:57:59'),
(53, 17, 'draft', 'Order created', 6, '2026-02-11 04:27:21'),
(54, 17, 'pending', NULL, 6, '2026-02-11 04:28:11'),
(55, 17, 'approved', 'Order approved', 8, '2026-02-11 04:28:37'),
(56, 18, 'draft', 'Order created', 6, '2026-02-20 17:51:54'),
(57, 18, 'cancelled', NULL, 6, '2026-02-20 17:52:02'),
(58, 19, 'draft', 'Order created', 6, '2026-02-20 17:52:25'),
(59, 19, 'pending', NULL, 6, '2026-02-20 17:52:28'),
(60, 19, 'approved', 'Order approved', 8, '2026-02-20 17:52:47'),
(61, 20, 'draft', 'Order created', 6, '2026-02-20 23:33:34'),
(62, 20, 'pending', NULL, 6, '2026-02-20 23:33:39'),
(63, 20, 'approved', 'Order approved', 8, '2026-02-20 23:34:23'),
(67, 22, 'draft', 'Order created', 6, '2026-03-28 07:49:02'),
(68, 22, 'pending', NULL, 6, '2026-03-28 07:49:10'),
(69, 22, 'approved', 'Order approved', 8, '2026-03-28 07:49:30');

-- --------------------------------------------------------

--
-- Table structure for table `sales_transactions`
--

CREATE TABLE `sales_transactions` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(30) NOT NULL,
  `transaction_type` enum('cash','credit','csi') NOT NULL DEFAULT 'cash',
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_type` enum('walk_in','regular','wholesale') DEFAULT 'walk_in',
  `subtotal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('none','percentage','fixed') DEFAULT 'none',
  `discount_value` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','gcash','check','bank_transfer','credit') NOT NULL DEFAULT 'cash',
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `change_amount` decimal(12,2) DEFAULT 0.00,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_metadata`)),
  `payment_status` enum('paid','partial','unpaid','voided') NOT NULL DEFAULT 'paid',
  `shift_id` int(11) DEFAULT NULL,
  `dr_id` int(11) DEFAULT NULL,
  `cashier_id` int(11) NOT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_transactions`
--

INSERT INTO `sales_transactions` (`id`, `transaction_code`, `transaction_type`, `customer_id`, `customer_name`, `customer_type`, `subtotal_amount`, `discount_type`, `discount_value`, `discount_amount`, `tax_amount`, `total_amount`, `payment_method`, `amount_paid`, `change_amount`, `payment_reference`, `payment_metadata`, `payment_status`, `shift_id`, `dr_id`, `cashier_id`, `voided_by`, `voided_at`, `void_reason`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'SI-2026-00001', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:21:37', '2026-02-05 07:20:46'),
(2, 'SI-2026-00002', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:22:10', '2026-02-05 07:20:46'),
(3, 'SI-2026-00003', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:22:14', '2026-02-05 07:20:46'),
(4, 'SI-2026-00004', 'cash', NULL, 'Walk-in Customer', 'walk_in', 45.00, 'none', 0.00, 0.00, 0.00, 45.00, 'cash', 45.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:26:31', '2026-02-05 07:20:46'),
(5, 'SI-2026-00005', 'cash', NULL, 'Walk-in Customer', 'walk_in', 45.00, 'none', 0.00, 0.00, 0.00, 45.00, 'cash', 45.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:26:40', '2026-02-05 07:20:46'),
(6, 'SI-2026-00006', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:30:05', '2026-02-05 07:20:46'),
(7, 'SI-2026-00007', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:30:10', '2026-02-05 07:20:46'),
(8, 'SI-2026-00008', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:30:10', '2026-02-05 07:20:46'),
(9, 'SI-2026-00009', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'voided', NULL, NULL, 7, NULL, '2026-02-05 15:20:46', NULL, ' [AUDIT: Voided - no items found]', '2026-02-03 10:30:10', '2026-02-05 07:20:46'),
(10, 'SI-2026-00010', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:34:55', '2026-02-03 10:34:55'),
(11, 'SI-2026-00011', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:09', '2026-02-03 10:35:09'),
(12, 'SI-2026-00012', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:13', '2026-02-03 10:35:13'),
(13, 'SI-2026-00013', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:14', '2026-02-03 10:35:14'),
(14, 'SI-2026-00014', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:14', '2026-02-03 10:35:14'),
(15, 'SI-2026-00015', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:14', '2026-02-03 10:35:14'),
(16, 'SI-2026-00016', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:15', '2026-02-03 10:35:15'),
(17, 'SI-2026-00017', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:19', '2026-02-03 10:35:19'),
(18, 'SI-2026-00018', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:20', '2026-02-03 10:35:20'),
(19, 'SI-2026-00019', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:20', '2026-02-03 10:35:20'),
(20, 'SI-2026-00020', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'gcash', 105.00, 0.00, 'GCash: ', NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:35:20', '2026-02-03 10:35:20'),
(21, 'SI-2026-00021', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:38:36', '2026-02-03 10:38:36'),
(22, 'SI-2026-00022', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-03 10:39:26', '2026-02-03 10:39:26'),
(23, 'SI-2026-00023', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-03 10:39:38', '2026-02-03 10:39:38'),
(24, 'SI-2026-00024', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-03 10:39:45', '2026-02-03 10:39:45'),
(25, 'SI-2026-00025', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-03 10:40:42', '2026-02-03 10:40:42'),
(26, 'SI-2026-00026', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-03 10:43:07', '2026-02-03 10:43:07'),
(27, 'SI-2026-00027', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-03 10:44:00', '2026-02-03 10:44:00'),
(28, 'SI-2026-00028', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-02-03 10:44:31', '2026-02-03 10:44:31'),
(29, 'SI-2026-00029', 'cash', NULL, 'Walk-in Customer', 'walk_in', 60.00, 'none', 0.00, 0.00, 0.00, 60.00, 'cash', 60.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:44:45', '2026-02-03 10:44:45'),
(30, 'SI-2026-00030', 'cash', NULL, 'Walk-in Customer', 'walk_in', 140.00, 'none', 0.00, 0.00, 0.00, 140.00, 'cash', 140.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 10:55:28', '2026-02-03 10:55:28'),
(31, 'SI-2026-00031', 'cash', NULL, 'Walk-in Customer', 'walk_in', 50.00, 'none', 0.00, 0.00, 0.00, 50.00, 'cash', 50.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-03 13:22:09', '2026-02-03 13:22:09'),
(32, 'SI-2026-00032', 'cash', NULL, 'Walk-in Customer', 'walk_in', 105.00, 'none', 0.00, 0.00, 0.00, 105.00, 'cash', 105.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-05 07:22:00', '2026-02-05 07:22:00'),
(33, 'SI-2026-00033', 'cash', NULL, 'Walk-in Customer', 'walk_in', 45.00, 'none', 0.00, 0.00, 0.00, 45.00, 'cash', 45.00, 0.00, NULL, NULL, 'paid', NULL, NULL, 7, NULL, NULL, NULL, '', '2026-02-05 07:26:23', '2026-02-05 07:26:23');

-- --------------------------------------------------------

--
-- Table structure for table `sales_transaction_items`
--

CREATE TABLE `sales_transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `variant` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL,
  `inventory_deductions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`inventory_deductions`)),
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_transaction_items`
--

INSERT INTO `sales_transaction_items` (`id`, `transaction_id`, `product_id`, `inventory_id`, `product_code`, `product_name`, `variant`, `quantity`, `unit_price`, `discount_amount`, `line_total`, `inventory_deductions`, `batch_number`, `expiry_date`, `created_at`) VALUES
(1, 10, 5, NULL, '', 'Strawberry Yogurt 150g', '150g', 1, 50.00, 0.00, 50.00, '[{\"inventory_id\":6,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-17\"}]', NULL, NULL, '2026-02-03 10:34:55'),
(2, 11, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:09'),
(3, 12, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:13'),
(4, 13, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:14'),
(5, 14, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:14'),
(6, 15, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:14'),
(7, 16, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:15'),
(8, 17, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:19'),
(9, 18, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:20'),
(10, 19, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:20'),
(11, 20, 1, NULL, '', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:35:20'),
(12, 21, 4, NULL, 'YOG-500', 'Plain Yogurt 500g', '500g', 1, 50.00, 0.00, 50.00, '[{\"inventory_id\":5,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-17\"}]', NULL, NULL, '2026-02-03 10:38:36'),
(13, 22, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:39:26'),
(14, 23, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:39:38'),
(15, 24, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:39:45'),
(16, 25, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:40:43'),
(17, 26, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:43:07'),
(18, 27, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:44:00'),
(19, 28, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:44:31'),
(20, 29, 2, NULL, 'FMK-500', 'Fresh Milk 500ml', '500ml', 1, 60.00, 0.00, 60.00, '[{\"inventory_id\":3,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-03 10:44:45'),
(21, 30, 7, NULL, 'BUT-250', 'Butter 250g', '250g', 1, 140.00, 0.00, 140.00, '[{\"inventory_id\":8,\"quantity_deducted\":1,\"expiry_date\":\"2026-03-05\"}]', NULL, NULL, '2026-02-03 10:55:28'),
(22, 31, 4, NULL, 'YOG-500', 'Plain Yogurt 500g', '500g', 1, 50.00, 0.00, 50.00, '[{\"inventory_id\":5,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-17\"}]', NULL, NULL, '2026-02-03 13:22:09'),
(23, 32, 1, NULL, 'FMK-1L', 'Fresh Milk 1L', '1 Liter', 1, 105.00, 0.00, 105.00, '[{\"inventory_id\":2,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-05 07:22:00'),
(24, 33, 3, NULL, 'CHO-1L', 'Chocolate Milk 1L', '1 Liter', 1, 45.00, 0.00, 45.00, '[{\"inventory_id\":4,\"quantity_deducted\":1,\"expiry_date\":\"2026-02-10\"}]', NULL, NULL, '2026-02-05 07:26:23');

-- --------------------------------------------------------

--
-- Table structure for table `storage_tanks`
--

CREATE TABLE `storage_tanks` (
  `id` int(11) NOT NULL,
  `tank_code` varchar(20) NOT NULL,
  `tank_name` varchar(100) NOT NULL,
  `milk_type_id` int(11) DEFAULT NULL COMMENT 'Dedicated tank for specific milk type',
  `capacity_liters` decimal(10,2) NOT NULL,
  `current_volume` decimal(10,2) NOT NULL DEFAULT 0.00,
  `location` varchar(100) DEFAULT NULL,
  `tank_type` enum('receiving','primary','secondary','holding','chiller','pasteurized') NOT NULL DEFAULT 'primary',
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `last_cleaned_at` datetime DEFAULT NULL,
  `status` enum('available','in_use','cleaning','maintenance','offline') NOT NULL DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fill_percentage` decimal(5,2) GENERATED ALWAYS AS (case when `capacity_liters` > 0 then `current_volume` / `capacity_liters` * 100 else 0 end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `storage_tanks`
--

INSERT INTO `storage_tanks` (`id`, `tank_code`, `tank_name`, `milk_type_id`, `capacity_liters`, `current_volume`, `location`, `tank_type`, `temperature_celsius`, `last_cleaned_at`, `status`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'TANK-001', 'Raw Milk Tank 1', 1, 2000.00, 0.00, 'Raw Storage Area', 'receiving', 4.0, NULL, 'available', 1, NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(2, 'TANK-002', 'Raw Milk Tank 2', 1, 2000.00, 0.00, 'Raw Storage Area', 'receiving', 4.0, NULL, 'available', 1, NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(3, 'TANK-003', 'Raw Milk Tank 3', 1, 3000.00, 0.00, 'Raw Storage Area', 'primary', 4.0, NULL, 'available', 1, NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(4, 'TANK-004', 'Pasteurized Tank 1', 1, 1500.00, 0.00, 'Processing Area', 'pasteurized', 4.0, NULL, 'available', 1, NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(5, 'TANK-005', 'Pasteurized Tank 2', 1, 1500.00, 0.00, 'Processing Area', 'pasteurized', 4.0, NULL, 'available', 1, NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(6, 'TANK-006', 'Holding Tank', 1, 1000.00, 0.00, 'Processing Area', 'holding', 4.0, NULL, 'available', 1, NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(7, 'RMT-001', 'Raw Milk Tank 1', NULL, 5000.00, 0.00, 'Receiving Area A', '', NULL, NULL, 'in_use', 1, NULL, '2026-02-03 14:43:08', '2026-02-05 06:29:48'),
(8, 'RMT-002', 'Raw Milk Tank 2', NULL, 5000.00, 0.00, 'Receiving Area A', '', NULL, NULL, 'available', 1, NULL, '2026-02-03 14:43:08', '2026-02-03 14:43:08'),
(9, 'PT-001', 'Pasteurized Tank 1', NULL, 3000.00, 401.00, 'Processing Area', 'pasteurized', NULL, NULL, 'in_use', 1, NULL, '2026-02-03 14:43:08', '2026-03-28 07:23:27'),
(10, 'PRT-001', 'Processing Tank 1', NULL, 2000.00, 1242.00, 'Processing Area', '', NULL, NULL, 'in_use', 1, NULL, '2026-02-03 14:43:08', '2026-04-26 09:34:28'),
(11, 'ST-001', 'Storage Tank 1', NULL, 10000.00, 0.00, 'Cold Storage Room', '', NULL, NULL, 'in_use', 1, NULL, '2026-02-03 14:43:08', '2026-02-05 06:29:48');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_code` varchar(30) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_terms` varchar(50) DEFAULT '30 days',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `payment_terms`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'SUP-001', 'LPC Packaging', 'LPC Sales', '', NULL, NULL, '30 days', 1, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(2, 'SUP-002', 'Ian Gao Trading', 'Ian Gao', '', NULL, NULL, '30 days', 1, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(3, 'SUP-003', 'Elixir Industries', 'Elixir Sales', '', NULL, NULL, '30 days', 1, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(4, 'SUP-004', 'Aya Commercial', 'Aya Commercial', '', NULL, NULL, '30 days', 1, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(5, 'SUP-005', 'Anco Merchandising', 'Anco Sales', '', NULL, NULL, '30 days', 1, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(6, 'SUP-006', 'Kalinisan Chemicals', 'Kalinisan Sales', '', NULL, NULL, '30 days', 1, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_returns`
--

CREATE TABLE `supplier_returns` (
  `id` int(11) NOT NULL,
  `return_code` varchar(30) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL COMMENT 'Original PO reference',
  `return_date` date NOT NULL,
  `return_reason` enum('defective','wrong_item','expired','quality_issue','damaged','other') NOT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','shipped','received_by_supplier','credited','cancelled') DEFAULT 'pending',
  `credit_memo_number` varchar(50) DEFAULT NULL COMMENT 'Supplier credit memo',
  `credit_amount` decimal(12,2) DEFAULT NULL,
  `initiated_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_return_items`
--

CREATE TABLE `supplier_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `po_item_id` int(11) DEFAULT NULL,
  `ingredient_batch_id` int(11) DEFAULT NULL,
  `item_description` varchar(200) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `employee_id` varchar(100) DEFAULT NULL,
  `role` enum('general_manager','qc_officer','production_staff','warehouse_raw','warehouse_fg','sales_custodian','cashier','purchaser','finance_officer','bookkeeper','maintenance_head') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `login_identifier` varchar(255) DEFAULT NULL,
  `login_type` enum('email','employee_id','username') NOT NULL DEFAULT 'username',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `password_set_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `first_name`, `last_name`, `employee_id`, `role`, `email`, `login_identifier`, `login_type`, `must_change_password`, `password_set_at`, `last_login_at`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'System', 'Admin', NULL, 'general_manager', 'admin@gmail.com', 'admin@gmail.com', 'email', 0, '2026-04-25 05:23:25', NULL, 1, '2026-02-03 07:57:05', '2026-04-24 21:32:30'),
(2, 'qc_officer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos', 'Maria', 'Santos', NULL, 'qc_officer', 'qc@gmail.com', 'qc@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-05-03 22:25:43', 1, '2026-02-03 07:57:05', '2026-05-03 14:25:43'),
(3, 'production_staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', 'Juan', 'Dela Cruz', NULL, 'production_staff', 'production@gmail.com', 'production@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-05-03 22:17:40', 1, '2026-02-03 07:57:05', '2026-05-03 14:17:40'),
(4, 'warehouse_raw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos Mendoza', 'Carlos', 'Mendoza', NULL, 'warehouse_raw', 'warehouse.raw@gmail.com', 'warehouse.raw@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-05-03 22:28:52', 1, '2026-02-03 07:57:05', '2026-05-03 14:28:52'),
(5, 'warehouse_fg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro Garcia', 'Pedro', 'Garcia', NULL, 'warehouse_fg', 'warehouse.fg@gmail.com', 'warehouse.fg@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-05-03 20:44:08', 1, '2026-02-03 07:57:05', '2026-05-03 12:44:08'),
(6, 'sales_custodian', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Miguel Torres', 'Miguel', 'Torres', NULL, 'sales_custodian', 'sales@gmail.com', 'sales@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-04-27 14:14:28', 1, '2026-02-03 07:57:05', '2026-04-27 06:14:28'),
(7, 'cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Reyes', 'Ana', 'Reyes', NULL, 'cashier', 'cashier@gmail.com', 'cashier@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-04-25 05:24:04', 1, '2026-02-03 07:57:05', '2026-04-24 21:32:30'),
(8, 'general_manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'General Manager', 'General', 'Manager', NULL, 'general_manager', 'gm@gmail.com', 'gm@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-05-03 22:28:14', 1, '2026-02-03 07:57:05', '2026-05-03 14:28:14'),
(10, 'purchaser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rosa Villanueva', 'Rosa', 'Villanueva', NULL, 'purchaser', 'purchasing@gmail.com', 'purchasing@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-05-03 22:28:30', 1, '2026-02-10 10:25:56', '2026-05-03 14:28:30'),
(11, 'finance_officer', '$2y$12$huffGwsovTEJOO6dBJ/Nx.4tuDgPgqEO8E8SQf.441HIH7BiafeW2', 'Maria Santos', 'Maria', 'Santos', 'EMP-FIN-001', 'finance_officer', 'finance@gmail.com', 'finance@gmail.com', 'email', 0, '2026-04-25 05:23:25', '2026-05-03 22:08:42', 1, '2026-02-10 15:43:16', '2026-05-03 14:08:42'),
(12, 'maintenance_head', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'Juan', 'Dela Cruz', NULL, 'maintenance_head', 'maintenance@gmail.com', 'maintenance@gmail.com', 'email', 0, '2026-04-25 05:23:25', NULL, 1, '2026-02-10 17:15:31', '2026-04-24 21:32:30'),
(13, 'ragasibrian2', '$2y$12$eImAFYpakUQiayibyvqIuubZ7Gfro8EUCJD07oHQ0Kz4oh8xOL3Vq', 'Brian Ragasi', 'Brian', 'Ragasi', '2323', 'sales_custodian', 'ragasibrian2@gmail.com', 'ragasibrian2@gmail.com', 'email', 0, '2026-04-27 14:18:38', '2026-05-02 21:00:13', 1, '2026-02-21 00:58:17', '2026-05-02 13:00:13');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_disposal_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_disposal_summary` (
`id` int(11)
,`disposal_code` varchar(30)
,`source_type` enum('raw_milk','finished_goods','ingredients','production_batch','milk_receiving')
,`source_reference` varchar(100)
,`product_name` varchar(100)
,`quantity` decimal(12,2)
,`unit` varchar(20)
,`total_value` decimal(14,2)
,`disposal_category` enum('qc_failed','expired','spoiled','contaminated','damaged','rejected_receipt','production_waste','other')
,`disposal_method` enum('drain','incinerate','animal_feed','compost','special_waste','other')
,`status` enum('pending','approved','rejected','completed','cancelled')
,`initiated_at` datetime
,`approved_at` datetime
,`disposed_at` datetime
,`initiated_by_name` varchar(201)
,`approved_by_name` varchar(201)
,`disposed_by_name` varchar(201)
,`recall_code` varchar(30)
,`recall_class` enum('class_i','class_ii','class_iii')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_recall_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_recall_summary` (
`id` int(11)
,`recall_code` varchar(30)
,`batch_code` varchar(50)
,`product_name` varchar(255)
,`recall_class` enum('class_i','class_ii','class_iii')
,`status` enum('initiated','pending_approval','approved','in_progress','completed','cancelled')
,`total_produced` int(11)
,`total_dispatched` int(11)
,`total_recovered` int(11)
,`recovery_rate` decimal(16,2)
,`initiated_at` datetime
,`approved_at` datetime
,`completed_at` datetime
,`initiated_by_name` varchar(201)
,`approved_by_name` varchar(201)
,`affected_locations_count` bigint(21)
,`notifications_sent` decimal(22,0)
,`acknowledgments_received` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `yogurt_transformations`
--

CREATE TABLE `yogurt_transformations` (
  `id` int(11) NOT NULL,
  `transformation_code` varchar(30) NOT NULL,
  `source_inventory_id` int(11) DEFAULT NULL COMMENT 'FK to finished_goods_inventory.id',
  `source_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `source_volume_liters` decimal(10,2) NOT NULL DEFAULT 0.00,
  `target_product` varchar(100) DEFAULT 'Yogurt',
  `target_recipe_id` int(11) DEFAULT NULL COMMENT 'FK to master_recipes.id',
  `target_quantity` decimal(10,2) DEFAULT NULL COMMENT 'Actual yield after transformation',
  `production_run_id` int(11) DEFAULT NULL COMMENT 'FK to production_runs.id',
  `transformation_date` date NOT NULL,
  `initiated_by` int(11) DEFAULT NULL COMMENT 'FK to users.id',
  `approved_by` int(11) DEFAULT NULL COMMENT 'FK to users.id',
  `approval_datetime` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL COMMENT 'FK to users.id',
  `completed_at` datetime DEFAULT NULL,
  `safety_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `yogurt_transformations`
--

INSERT INTO `yogurt_transformations` (`id`, `transformation_code`, `source_inventory_id`, `source_quantity`, `source_volume_liters`, `target_product`, `target_recipe_id`, `target_quantity`, `production_run_id`, `transformation_date`, `initiated_by`, `approved_by`, `approval_datetime`, `completed_by`, `completed_at`, `safety_verified`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'YTF-000001', 2, 50.00, 50.00, 'Yogurt', 15, NULL, NULL, '2026-02-08', 2, 2, '2026-02-08 21:54:32', NULL, NULL, 1, 'pending', 'Transformation from near-expiry: Fresh Milk 1L', '2026-02-08 13:54:32', '2026-02-08 13:54:32'),
(2, 'YTF-000002', 4, 96.00, 96.00, 'Yogurt', 15, NULL, NULL, '2026-02-10', 2, 2, '2026-02-10 23:05:16', NULL, NULL, 1, 'pending', 'Basta', '2026-02-10 15:05:16', '2026-02-10 15:05:16');

-- --------------------------------------------------------

--
-- Structure for view `gm_pending_approvals`
--
DROP TABLE IF EXISTS `gm_pending_approvals`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `gm_pending_approvals`  AS SELECT 'PO' AS `type`, `po`.`id` AS `id`, `po`.`po_number` AS `reference_code`, concat('Purchase Order - ',`s`.`supplier_name`) AS `description`, `po`.`total_amount` AS `amount`, `u`.`full_name` AS `requested_by`, `po`.`created_at` AS `requested_at`, 'pending' AS `status` FROM ((`purchase_orders` `po` join `suppliers` `s` on(`po`.`supplier_id` = `s`.`id`)) left join `users` `u` on(`po`.`created_by` = `u`.`id`)) WHERE `po`.`status` = 'pending'union all select 'REQUISITION' AS `type`,`mr`.`id` AS `id`,`mr`.`requisition_code` AS `reference_code`,concat('Material Requisition - ',`mr`.`department`,': ',coalesce(`mr`.`purpose`,'No description')) AS `description`,NULL AS `amount`,`u`.`full_name` AS `requested_by`,`mr`.`created_at` AS `requested_at`,`mr`.`status` AS `status` from (`material_requisitions` `mr` left join `users` `u` on(`mr`.`requested_by` = `u`.`id`)) where `mr`.`status` = 'pending' order by `requested_at`  ;

-- --------------------------------------------------------

--
-- Structure for view `vw_disposal_summary`
--
DROP TABLE IF EXISTS `vw_disposal_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_disposal_summary`  AS SELECT `d`.`id` AS `id`, `d`.`disposal_code` AS `disposal_code`, `d`.`source_type` AS `source_type`, `d`.`source_reference` AS `source_reference`, `d`.`product_name` AS `product_name`, `d`.`quantity` AS `quantity`, `d`.`unit` AS `unit`, `d`.`total_value` AS `total_value`, `d`.`disposal_category` AS `disposal_category`, `d`.`disposal_method` AS `disposal_method`, `d`.`status` AS `status`, `d`.`initiated_at` AS `initiated_at`, `d`.`approved_at` AS `approved_at`, `d`.`disposed_at` AS `disposed_at`, concat(`ui`.`first_name`,' ',`ui`.`last_name`) AS `initiated_by_name`, concat(`ua`.`first_name`,' ',`ua`.`last_name`) AS `approved_by_name`, concat(`ud`.`first_name`,' ',`ud`.`last_name`) AS `disposed_by_name`, `br`.`recall_code` AS `recall_code`, `br`.`recall_class` AS `recall_class` FROM ((((`disposals` `d` left join `users` `ui` on(`d`.`initiated_by` = `ui`.`id`)) left join `users` `ua` on(`d`.`approved_by` = `ua`.`id`)) left join `users` `ud` on(`d`.`disposed_by` = `ud`.`id`)) left join `batch_recalls` `br` on(`d`.`recall_id` = `br`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_recall_summary`
--
DROP TABLE IF EXISTS `vw_recall_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_recall_summary`  AS SELECT `br`.`id` AS `id`, `br`.`recall_code` AS `recall_code`, `br`.`batch_code` AS `batch_code`, `br`.`product_name` AS `product_name`, `br`.`recall_class` AS `recall_class`, `br`.`status` AS `status`, `br`.`total_produced` AS `total_produced`, `br`.`total_dispatched` AS `total_dispatched`, `br`.`total_recovered` AS `total_recovered`, CASE WHEN `br`.`total_dispatched` > 0 THEN round(`br`.`total_recovered` / `br`.`total_dispatched` * 100,2) ELSE 0 END AS `recovery_rate`, `br`.`initiated_at` AS `initiated_at`, `br`.`approved_at` AS `approved_at`, `br`.`completed_at` AS `completed_at`, concat(`ui`.`first_name`,' ',`ui`.`last_name`) AS `initiated_by_name`, concat(`ua`.`first_name`,' ',`ua`.`last_name`) AS `approved_by_name`, count(distinct `ral`.`id`) AS `affected_locations_count`, sum(case when `ral`.`notification_sent` = 1 then 1 else 0 end) AS `notifications_sent`, sum(case when `ral`.`acknowledged` = 1 then 1 else 0 end) AS `acknowledgments_received` FROM (((`batch_recalls` `br` left join `users` `ui` on(`br`.`initiated_by` = `ui`.`id`)) left join `users` `ua` on(`br`.`approved_by` = `ua`.`id`)) left join `recall_affected_locations` `ral` on(`br`.`id` = `ral`.`recall_id`)) GROUP BY `br`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_logs_entry_hash` (`entry_hash`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_table` (`table_name`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_audit_logs_prev_hash` (`prev_hash`);

--
-- Indexes for table `auth_invites`
--
ALTER TABLE `auth_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_auth_invites_token_hash` (`token_hash`),
  ADD KEY `idx_auth_invites_user_id` (`user_id`),
  ADD KEY `idx_auth_invites_expires_at` (`expires_at`);

--
-- Indexes for table `auth_password_resets`
--
ALTER TABLE `auth_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_password_resets_token_hash` (`token_hash`),
  ADD KEY `idx_password_resets_user_id` (`user_id`),
  ADD KEY `idx_password_resets_expires_at` (`expires_at`);

--
-- Indexes for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_auth_sessions_session_id` (`session_id`),
  ADD KEY `idx_auth_sessions_user_id` (`user_id`),
  ADD KEY `idx_auth_sessions_expires_at` (`expires_at`),
  ADD KEY `idx_auth_sessions_revoked_at` (`revoked_at`);

--
-- Indexes for table `auth_stepups`
--
ALTER TABLE `auth_stepups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_auth_stepups_token_hash` (`token_hash`),
  ADD KEY `idx_auth_stepups_user_session_scope` (`user_id`,`session_id`,`scope`),
  ADD KEY `idx_auth_stepups_expires_at` (`expires_at`);

--
-- Indexes for table `batch_recalls`
--
ALTER TABLE `batch_recalls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `recall_code` (`recall_code`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_recall_class` (`recall_class`),
  ADD KEY `idx_initiated_at` (`initiated_at`);

--
-- Indexes for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_id` (`inventory_id`),
  ADD KEY `idx_opened_at` (`opened_at`),
  ADD KEY `idx_opened_by` (`opened_by`);

--
-- Indexes for table `canvass_quotes`
--
ALTER TABLE `canvass_quotes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_canvass` (`canvass_id`),
  ADD KEY `idx_supplier` (`supplier_id`);

--
-- Indexes for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shift_code` (`shift_code`),
  ADD KEY `idx_cashier` (`cashier_id`),
  ADD KEY `idx_date` (`start_time`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `cash_adjustments`
--
ALTER TABLE `cash_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shift` (`shift_id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_type` (`adjustment_type`);

--
-- Indexes for table `ccp_logs`
--
ALTER TABLE `ccp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ccp_id` (`ccp_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_ccp_logs_datetime` (`log_datetime`);

--
-- Indexes for table `ccp_standards`
--
ALTER TABLE `ccp_standards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ccp_category` (`category`);

--
-- Indexes for table `chillers`
--
ALTER TABLE `chillers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chiller_code` (`chiller_code`),
  ADD KEY `idx_chillers_status` (`status`);

--
-- Indexes for table `chiller_locations`
--
ALTER TABLE `chiller_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chiller_code` (`chiller_code`),
  ADD KEY `idx_chiller_code` (`chiller_code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `chiller_temperature_logs`
--
ALTER TABLE `chiller_temperature_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_chiller_date` (`chiller_id`,`recorded_at`);

--
-- Indexes for table `chiller_temp_logs`
--
ALTER TABLE `chiller_temp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chiller_id` (`chiller_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_chiller_logs_date` (`log_date`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`customer_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `customer_returns`
--
ALTER TABLE `customer_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_code` (`return_code`),
  ADD KEY `idx_return_code` (`return_code`),
  ADD KEY `idx_delivery` (`delivery_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_cr_receiver` (`received_by`),
  ADD KEY `fk_cr_processor` (`processed_by`),
  ADD KEY `fk_cr_qc` (`qc_inspected_by`);

--
-- Indexes for table `customer_return_items`
--
ALTER TABLE `customer_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_return` (`return_id`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `fk_cri_delivery_item` (`delivery_item_id`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dr_number` (`dr_number`),
  ADD KEY `idx_dr_number` (`dr_number`),
  ADD KEY `idx_sales_order` (`sales_order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_acceptance` (`acceptance_status`),
  ADD KEY `idx_dispatched` (`dispatched_at`),
  ADD KEY `idx_accepted` (`accepted_at`),
  ADD KEY `fk_delivery_customer` (`customer_id`),
  ADD KEY `fk_delivery_dispatcher` (`dispatched_by`),
  ADD KEY `fk_delivery_deliverer` (`delivered_by`),
  ADD KEY `fk_delivery_creator` (`created_by`);

--
-- Indexes for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery` (`delivery_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_di_soi` (`sales_order_item_id`),
  ADD KEY `idx_batch_id` (`batch_id`);

--
-- Indexes for table `delivery_receipts`
--
ALTER TABLE `delivery_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dr_number` (`dr_number`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `delivery_receipt_items`
--
ALTER TABLE `delivery_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dr` (`delivery_receipt_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `delivery_returns`
--
ALTER TABLE `delivery_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dr_id` (`delivery_receipt_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_return_reason` (`return_reason`),
  ADD KEY `idx_disposition` (`disposition`);

--
-- Indexes for table `disposals`
--
ALTER TABLE `disposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `disposal_code` (`disposal_code`),
  ADD KEY `idx_disposal_code` (`disposal_code`),
  ADD KEY `idx_source` (`source_type`,`source_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`disposal_category`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_approved_by` (`approved_by`),
  ADD KEY `idx_initiated_by` (`initiated_by`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `disposed_by` (`disposed_by`),
  ADD KEY `idx_recall_id` (`recall_id`);

--
-- Indexes for table `disposal_items`
--
ALTER TABLE `disposal_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_disposal_id` (`disposal_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `farmers`
--
ALTER TABLE `farmers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `farmer_code` (`farmer_code`),
  ADD KEY `fk_farmer_milk_type` (`milk_type_id`);

--
-- Indexes for table `fg_customers`
--
ALTER TABLE `fg_customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`);

--
-- Indexes for table `fg_dispatch_log`
--
ALTER TABLE `fg_dispatch_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_id` (`inventory_id`),
  ADD KEY `idx_dr_id` (`dr_id`),
  ADD KEY `idx_released_at` (`released_at`),
  ADD KEY `idx_released_by` (`released_by`);

--
-- Indexes for table `fg_inventory_transactions`
--
ALTER TABLE `fg_inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_code` (`transaction_code`),
  ADD KEY `idx_type` (`transaction_type`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `fg_receiving`
--
ALTER TABLE `fg_receiving`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receiving_code` (`receiving_code`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_date` (`received_at`);

--
-- Indexes for table `finished_goods_inventory`
--
ALTER TABLE `finished_goods_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_barcode` (`barcode`),
  ADD KEY `fk_fg_inv_qc` (`qc_release_id`),
  ADD KEY `fk_fg_inv_chiller` (`chiller_id`),
  ADD KEY `fk_fg_inv_receiver` (`received_by`);

--
-- Indexes for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ingredient_code` (`ingredient_code`),
  ADD KEY `idx_ingredient_code` (`ingredient_code`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `ingredient_batches`
--
ALTER TABLE `ingredient_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `idx_batch_code` (`batch_code`),
  ADD KEY `idx_ingredient` (`ingredient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_qc_status` (`qc_status`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `fk_ing_batch_supplier` (`supplier_id`),
  ADD KEY `fk_ing_batch_receiver` (`received_by`);

--
-- Indexes for table `ingredient_categories`
--
ALTER TABLE `ingredient_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_code` (`category_code`);

--
-- Indexes for table `ingredient_consumption`
--
ALTER TABLE `ingredient_consumption`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run_id` (`run_id`);

--
-- Indexes for table `ingredient_price_history`
--
ALTER TABLE `ingredient_price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_ingredient` (`ingredient_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `idx_transaction_code` (`transaction_code`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_item` (`item_type`,`item_id`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `fk_inv_trans_performed` (`performed_by`),
  ADD KEY `fk_inv_trans_approved` (`approved_by`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_login_attempts_username_ip` (`username`,`ip_address`),
  ADD KEY `idx_login_attempts_username` (`username`),
  ADD KEY `idx_login_attempts_locked_until` (`locked_until`);

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `machine_code` (`machine_code`),
  ADD KEY `idx_machine_type` (`machine_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_next_maintenance` (`next_maintenance_due`);

--
-- Indexes for table `machine_repairs`
--
ALTER TABLE `machine_repairs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `repair_code` (`repair_code`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `completed_by` (`completed_by`),
  ADD KEY `idx_machine` (`machine_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_repair_type` (`repair_type`),
  ADD KEY `idx_reported_at` (`reported_at`);

--
-- Indexes for table `maintenance_requisitions`
--
ALTER TABLE `maintenance_requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requisition_code` (`requisition_code`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `rejected_by` (`rejected_by`),
  ADD KEY `fulfilled_by` (`fulfilled_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_repair` (`repair_id`),
  ADD KEY `idx_machine` (`machine_id`),
  ADD KEY `idx_requested_by` (`requested_by`);

--
-- Indexes for table `maintenance_requisition_items`
--
ALTER TABLE `maintenance_requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requisition` (`requisition_id`),
  ADD KEY `idx_mro_item` (`mro_item_id`);

--
-- Indexes for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_machine` (`machine_id`),
  ADD KEY `idx_next_due` (`next_due`);

--
-- Indexes for table `master_recipes`
--
ALTER TABLE `master_recipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `recipe_code` (`recipe_code`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `fk_recipe_product` (`product_id`),
  ADD KEY `fk_recipe_creator` (`created_by`);

--
-- Indexes for table `material_requisitions`
--
ALTER TABLE `material_requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requisition_code` (`requisition_code`),
  ADD KEY `idx_requisition_code` (`requisition_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_production_run` (`production_run_id`);

--
-- Indexes for table `milk_grading_standards`
--
ALTER TABLE `milk_grading_standards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `grade_name` (`grade_name`),
  ADD KEY `idx_grading_status` (`status`);

--
-- Indexes for table `milk_receiving`
--
ALTER TABLE `milk_receiving`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receiving_code` (`receiving_code`),
  ADD KEY `idx_farmer` (`farmer_id`),
  ADD KEY `idx_date` (`receiving_date`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_receiving_user` (`received_by`);

--
-- Indexes for table `milk_types`
--
ALTER TABLE `milk_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_code` (`type_code`);

--
-- Indexes for table `mro_categories`
--
ALTER TABLE `mro_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_code` (`category_code`);

--
-- Indexes for table `mro_inventory`
--
ALTER TABLE `mro_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `idx_mro_item` (`mro_item_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `mro_items`
--
ALTER TABLE `mro_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `mro_price_history`
--
ALTER TABLE `mro_price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_mro_item` (`mro_item_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `packaging_runs`
--
ALTER TABLE `packaging_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `packaging_code` (`packaging_code`),
  ADD KEY `production_run_id` (`production_run_id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `packaged_by` (`packaged_by`);

--
-- Indexes for table `packaging_run_items`
--
ALTER TABLE `packaging_run_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `packaging_run_id` (`packaging_run_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `pasteurization_runs`
--
ALTER TABLE `pasteurization_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `run_code` (`run_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `pasteurized_milk_inventory`
--
ALTER TABLE `pasteurized_milk_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `payment_collections`
--
ALTER TABLE `payment_collections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `or_number` (`or_number`),
  ADD KEY `idx_or_number` (`or_number`),
  ADD KEY `idx_dr` (`dr_id`,`dr_number`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_collected_at` (`collected_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `pos_transactions`
--
ALTER TABLE `pos_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `idx_transaction_code` (`transaction_code`),
  ADD KEY `idx_type` (`transaction_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `fk_pos_customer` (`customer_id`),
  ADD KEY `fk_pos_cashier` (`cashier_id`),
  ADD KEY `fk_pos_voider` (`voided_by`);

--
-- Indexes for table `pos_transaction_items`
--
ALTER TABLE `pos_transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_inventory` (`inventory_id`);

--
-- Indexes for table `po_receiving_log`
--
ALTER TABLE `po_receiving_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_item_id` (`po_item_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `idx_po_id` (`po_id`),
  ADD KEY `idx_received_at` (`received_at`);

--
-- Indexes for table `price_canvass`
--
ALTER TABLE `price_canvass`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `canvass_code` (`canvass_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `mro_item_id` (`mro_item_id`);

--
-- Indexes for table `production_batches`
--
ALTER TABLE `production_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `idx_batch_code` (`batch_code`),
  ADD KEY `idx_run` (`run_id`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `idx_qc_status` (`qc_status`),
  ADD KEY `idx_fg_received` (`fg_received`),
  ADD KEY `fk_batch_recipe` (`recipe_id`),
  ADD KEY `fk_batch_product` (`product_id`),
  ADD KEY `fk_batch_creator` (`created_by`),
  ADD KEY `fk_batch_releaser` (`released_by`),
  ADD KEY `fk_batch_fg_receiver` (`fg_received_by`);

--
-- Indexes for table `production_byproducts`
--
ALTER TABLE `production_byproducts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run` (`run_id`),
  ADD KEY `idx_type` (`byproduct_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `production_ccp_logs`
--
ALTER TABLE `production_ccp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run` (`run_id`),
  ADD KEY `idx_check_type` (`check_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_ccp_verifier` (`verified_by`);

--
-- Indexes for table `production_material_usage`
--
ALTER TABLE `production_material_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run` (`run_id`),
  ADD KEY `idx_source` (`material_type`,`source_batch_id`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `fk_pmu_recorder` (`recorded_by`);

--
-- Indexes for table `production_output`
--
ALTER TABLE `production_output`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run` (`run_id`),
  ADD KEY `idx_batch` (`output_batch_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `fk_po_recorder` (`recorded_by`);

--
-- Indexes for table `production_runs`
--
ALTER TABLE `production_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `run_code` (`run_code`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_run_recipe` (`recipe_id`),
  ADD KEY `fk_run_started` (`started_by`),
  ADD KEY `fk_run_completed` (`completed_by`);

--
-- Indexes for table `production_run_milk_usage`
--
ALTER TABLE `production_run_milk_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run` (`run_id`),
  ADD KEY `idx_receiving` (`receiving_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_milk_type` (`milk_type_id`);

--
-- Indexes for table `product_prices`
--
ALTER TABLE `product_prices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_effective` (`effective_date`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `idx_po_number` (`po_number`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_po_creator` (`created_by`),
  ADD KEY `fk_po_approver` (`approved_by`),
  ADD KEY `idx_payment_terms` (`payment_terms`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `fk_po_requisition` (`requisition_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po` (`po_id`),
  ADD KEY `fk_poi_ingredient` (`ingredient_id`),
  ADD KEY `fk_poi_mro` (`mro_item_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pr_number` (`pr_number`),
  ADD KEY `idx_pr_status` (`status`),
  ADD KEY `idx_pr_requested_by` (`requested_by`);

--
-- Indexes for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pri_pr_id` (`purchase_request_id`);

--
-- Indexes for table `qc_batch_release`
--
ALTER TABLE `qc_batch_release`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `release_code` (`release_code`),
  ADD UNIQUE KEY `unique_batch` (`batch_id`),
  ADD KEY `idx_release_code` (`release_code`),
  ADD KEY `idx_decision` (`release_decision`),
  ADD KEY `fk_qc_release_inspector` (`inspected_by`),
  ADD KEY `fk_qc_release_approver` (`approved_by`);

--
-- Indexes for table `qc_milk_tests`
--
ALTER TABLE `qc_milk_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_code` (`test_code`),
  ADD KEY `idx_receiving` (`receiving_id`),
  ADD KEY `idx_grade` (`grade`),
  ADD KEY `idx_test_date` (`test_datetime`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `fk_qc_test_tester` (`tested_by`),
  ADD KEY `fk_qc_test_verifier` (`verified_by`);

--
-- Indexes for table `qc_test_parameters`
--
ALTER TABLE `qc_test_parameters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qc_params_category` (`category`);

--
-- Indexes for table `raw_milk_inventory`
--
ALTER TABLE `raw_milk_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `idx_batch_code` (`batch_code`),
  ADD KEY `idx_milk_type` (`milk_type_id`),
  ADD KEY `idx_tank` (`tank_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `fk_raw_inv_receiving` (`receiving_id`),
  ADD KEY `fk_raw_inv_qc` (`qc_test_id`),
  ADD KEY `fk_raw_inv_user` (`received_by`);

--
-- Indexes for table `recall_activity_log`
--
ALTER TABLE `recall_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action_by` (`action_by`),
  ADD KEY `idx_recall_id` (`recall_id`),
  ADD KEY `idx_action_at` (`action_at`);

--
-- Indexes for table `recall_affected_locations`
--
ALTER TABLE `recall_affected_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recall_id` (`recall_id`),
  ADD KEY `idx_return_status` (`return_status`);

--
-- Indexes for table `recall_returns`
--
ALTER TABLE `recall_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `affected_location_id` (`affected_location_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `idx_recall_id` (`recall_id`),
  ADD KEY `idx_return_date` (`return_date`);

--
-- Indexes for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipe` (`recipe_id`),
  ADD KEY `fk_recipe_ing_ingredient` (`ingredient_id`);

--
-- Indexes for table `repair_parts_used`
--
ALTER TABLE `repair_parts_used`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mro_inventory_id` (`mro_inventory_id`),
  ADD KEY `idx_repair` (`repair_id`),
  ADD KEY `idx_mro_item` (`mro_item_id`);

--
-- Indexes for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requisition` (`requisition_id`),
  ADD KEY `idx_item` (`item_type`,`item_id`);

--
-- Indexes for table `sales_customer_sub_accounts`
--
ALTER TABLE `sales_customer_sub_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `sales_invoices`
--
ALTER TABLE `sales_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `csi_number` (`csi_number`),
  ADD KEY `idx_csi` (`csi_number`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `sales_invoice_items`
--
ALTER TABLE `sales_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `sales_invoice_payments`
--
ALTER TABLE `sales_invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_payment_type` (`payment_type`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_delivery_date` (`delivery_date`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `fk_so_creator` (`created_by`),
  ADD KEY `fk_so_approver` (`approved_by`),
  ADD KEY `fk_so_assignee` (`assigned_to`);

--
-- Indexes for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `sales_order_status_history`
--
ALTER TABLE `sales_order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_changed_by` (`changed_by`);

--
-- Indexes for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `idx_transaction_code` (`transaction_code`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_cashier` (`cashier_id`),
  ADD KEY `idx_shift` (`shift_id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_type` (`transaction_type`),
  ADD KEY `idx_status` (`payment_status`),
  ADD KEY `idx_dr` (`dr_id`);

--
-- Indexes for table `sales_transaction_items`
--
ALTER TABLE `sales_transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_inventory` (`inventory_id`);

--
-- Indexes for table `storage_tanks`
--
ALTER TABLE `storage_tanks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tank_code` (`tank_code`),
  ADD KEY `idx_tank_code` (`tank_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tank_type` (`tank_type`),
  ADD KEY `fk_tank_milk_type` (`milk_type_id`),
  ADD KEY `idx_tanks_status` (`status`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Indexes for table `supplier_returns`
--
ALTER TABLE `supplier_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_code` (`return_code`),
  ADD KEY `idx_return_code` (`return_code`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_sr_po` (`po_id`),
  ADD KEY `fk_sr_initiator` (`initiated_by`),
  ADD KEY `fk_sr_approver` (`approved_by`);

--
-- Indexes for table `supplier_return_items`
--
ALTER TABLE `supplier_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_return` (`return_id`),
  ADD KEY `fk_sri_po_item` (`po_item_id`),
  ADD KEY `fk_sri_batch` (`ingredient_batch_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_users_login_identifier` (`login_identifier`);

--
-- Indexes for table `yogurt_transformations`
--
ALTER TABLE `yogurt_transformations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_transformation_code` (`transformation_code`),
  ADD KEY `idx_source_inventory` (`source_inventory_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_transformation_date` (`transformation_date`),
  ADD KEY `idx_production_run` (`production_run_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1019;

--
-- AUTO_INCREMENT for table `auth_invites`
--
ALTER TABLE `auth_invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `auth_password_resets`
--
ALTER TABLE `auth_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `auth_stepups`
--
ALTER TABLE `auth_stepups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `batch_recalls`
--
ALTER TABLE `batch_recalls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `canvass_quotes`
--
ALTER TABLE `canvass_quotes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cash_adjustments`
--
ALTER TABLE `cash_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ccp_logs`
--
ALTER TABLE `ccp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ccp_standards`
--
ALTER TABLE `ccp_standards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `chillers`
--
ALTER TABLE `chillers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chiller_locations`
--
ALTER TABLE `chiller_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chiller_temperature_logs`
--
ALTER TABLE `chiller_temperature_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chiller_temp_logs`
--
ALTER TABLE `chiller_temp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `customer_returns`
--
ALTER TABLE `customer_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_return_items`
--
ALTER TABLE `customer_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_items`
--
ALTER TABLE `delivery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_receipts`
--
ALTER TABLE `delivery_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `delivery_receipt_items`
--
ALTER TABLE `delivery_receipt_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `delivery_returns`
--
ALTER TABLE `delivery_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disposals`
--
ALTER TABLE `disposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `disposal_items`
--
ALTER TABLE `disposal_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `farmers`
--
ALTER TABLE `farmers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `fg_customers`
--
ALTER TABLE `fg_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `fg_dispatch_log`
--
ALTER TABLE `fg_dispatch_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `fg_inventory_transactions`
--
ALTER TABLE `fg_inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `fg_receiving`
--
ALTER TABLE `fg_receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finished_goods_inventory`
--
ALTER TABLE `finished_goods_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `ingredient_batches`
--
ALTER TABLE `ingredient_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ingredient_categories`
--
ALTER TABLE `ingredient_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ingredient_consumption`
--
ALTER TABLE `ingredient_consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `ingredient_price_history`
--
ALTER TABLE `ingredient_price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `machine_repairs`
--
ALTER TABLE `machine_repairs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_requisitions`
--
ALTER TABLE `maintenance_requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `maintenance_requisition_items`
--
ALTER TABLE `maintenance_requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_recipes`
--
ALTER TABLE `master_recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `material_requisitions`
--
ALTER TABLE `material_requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `milk_grading_standards`
--
ALTER TABLE `milk_grading_standards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `milk_receiving`
--
ALTER TABLE `milk_receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `milk_types`
--
ALTER TABLE `milk_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mro_categories`
--
ALTER TABLE `mro_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `mro_inventory`
--
ALTER TABLE `mro_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `mro_items`
--
ALTER TABLE `mro_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `mro_price_history`
--
ALTER TABLE `mro_price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packaging_runs`
--
ALTER TABLE `packaging_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `packaging_run_items`
--
ALTER TABLE `packaging_run_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `pasteurization_runs`
--
ALTER TABLE `pasteurization_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pasteurized_milk_inventory`
--
ALTER TABLE `pasteurized_milk_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_collections`
--
ALTER TABLE `payment_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `pos_transactions`
--
ALTER TABLE `pos_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_transaction_items`
--
ALTER TABLE `pos_transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `po_receiving_log`
--
ALTER TABLE `po_receiving_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `price_canvass`
--
ALTER TABLE `price_canvass`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `production_batches`
--
ALTER TABLE `production_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `production_byproducts`
--
ALTER TABLE `production_byproducts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_ccp_logs`
--
ALTER TABLE `production_ccp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `production_material_usage`
--
ALTER TABLE `production_material_usage`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_output`
--
ALTER TABLE `production_output`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_runs`
--
ALTER TABLE `production_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `production_run_milk_usage`
--
ALTER TABLE `production_run_milk_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `product_prices`
--
ALTER TABLE `product_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `qc_batch_release`
--
ALTER TABLE `qc_batch_release`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `qc_milk_tests`
--
ALTER TABLE `qc_milk_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `qc_test_parameters`
--
ALTER TABLE `qc_test_parameters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `raw_milk_inventory`
--
ALTER TABLE `raw_milk_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `recall_activity_log`
--
ALTER TABLE `recall_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `recall_affected_locations`
--
ALTER TABLE `recall_affected_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recall_returns`
--
ALTER TABLE `recall_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `repair_parts_used`
--
ALTER TABLE `repair_parts_used`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sales_customer_sub_accounts`
--
ALTER TABLE `sales_customer_sub_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sales_invoices`
--
ALTER TABLE `sales_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_invoice_items`
--
ALTER TABLE `sales_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_invoice_payments`
--
ALTER TABLE `sales_invoice_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `sales_order_status_history`
--
ALTER TABLE `sales_order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `sales_transaction_items`
--
ALTER TABLE `sales_transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `storage_tanks`
--
ALTER TABLE `storage_tanks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supplier_returns`
--
ALTER TABLE `supplier_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_return_items`
--
ALTER TABLE `supplier_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `yogurt_transformations`
--
ALTER TABLE `yogurt_transformations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  ADD CONSTRAINT `fk_box_opening_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `fk_box_opening_user` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `canvass_quotes`
--
ALTER TABLE `canvass_quotes`
  ADD CONSTRAINT `canvass_quotes_ibfk_1` FOREIGN KEY (`canvass_id`) REFERENCES `price_canvass` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `canvass_quotes_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `ccp_logs`
--
ALTER TABLE `ccp_logs`
  ADD CONSTRAINT `ccp_logs_ibfk_1` FOREIGN KEY (`ccp_id`) REFERENCES `ccp_standards` (`id`),
  ADD CONSTRAINT `ccp_logs_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chiller_temperature_logs`
--
ALTER TABLE `chiller_temperature_logs`
  ADD CONSTRAINT `chiller_temperature_logs_ibfk_1` FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations` (`id`),
  ADD CONSTRAINT `chiller_temperature_logs_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `chiller_temp_logs`
--
ALTER TABLE `chiller_temp_logs`
  ADD CONSTRAINT `chiller_temp_logs_ibfk_1` FOREIGN KEY (`chiller_id`) REFERENCES `chillers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chiller_temp_logs_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_returns`
--
ALTER TABLE `customer_returns`
  ADD CONSTRAINT `fk_cr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_cr_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`),
  ADD CONSTRAINT `fk_cr_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_cr_qc` FOREIGN KEY (`qc_inspected_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_cr_receiver` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `customer_return_items`
--
ALTER TABLE `customer_return_items`
  ADD CONSTRAINT `fk_cri_delivery_item` FOREIGN KEY (`delivery_item_id`) REFERENCES `delivery_items` (`id`),
  ADD CONSTRAINT `fk_cri_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `fk_cri_return` FOREIGN KEY (`return_id`) REFERENCES `customer_returns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `fk_delivery_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_delivery_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_delivery_deliverer` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_delivery_dispatcher` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_delivery_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`);

--
-- Constraints for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD CONSTRAINT `fk_di_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_di_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `fk_di_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_di_soi` FOREIGN KEY (`sales_order_item_id`) REFERENCES `sales_order_items` (`id`);

--
-- Constraints for table `delivery_receipt_items`
--
ALTER TABLE `delivery_receipt_items`
  ADD CONSTRAINT `delivery_receipt_items_ibfk_1` FOREIGN KEY (`delivery_receipt_id`) REFERENCES `delivery_receipts` (`id`);

--
-- Constraints for table `disposals`
--
ALTER TABLE `disposals`
  ADD CONSTRAINT `disposals_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `disposals_ibfk_2` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `disposals_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `disposals_ibfk_4` FOREIGN KEY (`disposed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `disposal_items`
--
ALTER TABLE `disposal_items`
  ADD CONSTRAINT `disposal_items_ibfk_1` FOREIGN KEY (`disposal_id`) REFERENCES `disposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disposal_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `farmers`
--
ALTER TABLE `farmers`
  ADD CONSTRAINT `fk_farmer_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`);

--
-- Constraints for table `fg_dispatch_log`
--
ALTER TABLE `fg_dispatch_log`
  ADD CONSTRAINT `fk_dispatch_dr` FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts` (`id`),
  ADD CONSTRAINT `fk_dispatch_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `fk_dispatch_user` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `finished_goods_inventory`
--
ALTER TABLE `finished_goods_inventory`
  ADD CONSTRAINT `fk_fg_inv_batch` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`),
  ADD CONSTRAINT `fk_fg_inv_chiller` FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations` (`id`),
  ADD CONSTRAINT `fk_fg_inv_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_fg_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_fg_inv_qc` FOREIGN KEY (`qc_release_id`) REFERENCES `qc_batch_release` (`id`),
  ADD CONSTRAINT `fk_fg_inv_receiver` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD CONSTRAINT `fk_ingredient_category` FOREIGN KEY (`category_id`) REFERENCES `ingredient_categories` (`id`);

--
-- Constraints for table `ingredient_batches`
--
ALTER TABLE `ingredient_batches`
  ADD CONSTRAINT `fk_ing_batch_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_ing_batch_receiver` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ing_batch_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `ingredient_price_history`
--
ALTER TABLE `ingredient_price_history`
  ADD CONSTRAINT `ingredient_price_history_ibfk_1` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `ingredient_price_history_ibfk_2` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ingredient_price_history_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ingredient_price_history_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inv_trans_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_inv_trans_performed` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `machine_repairs`
--
ALTER TABLE `machine_repairs`
  ADD CONSTRAINT `machine_repairs_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  ADD CONSTRAINT `machine_repairs_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `machine_repairs_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `machine_repairs_ibfk_4` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `maintenance_requisitions`
--
ALTER TABLE `maintenance_requisitions`
  ADD CONSTRAINT `maintenance_requisitions_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `machine_repairs` (`id`),
  ADD CONSTRAINT `maintenance_requisitions_ibfk_2` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  ADD CONSTRAINT `maintenance_requisitions_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `maintenance_requisitions_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `maintenance_requisitions_ibfk_5` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `maintenance_requisitions_ibfk_6` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `maintenance_requisition_items`
--
ALTER TABLE `maintenance_requisition_items`
  ADD CONSTRAINT `maintenance_requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `maintenance_requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requisition_items_ibfk_2` FOREIGN KEY (`mro_item_id`) REFERENCES `mro_items` (`id`);

--
-- Constraints for table `maintenance_schedules`
--
ALTER TABLE `maintenance_schedules`
  ADD CONSTRAINT `maintenance_schedules_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  ADD CONSTRAINT `maintenance_schedules_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `master_recipes`
--
ALTER TABLE `master_recipes`
  ADD CONSTRAINT `fk_recipe_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_recipe_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_recipe_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `milk_receiving`
--
ALTER TABLE `milk_receiving`
  ADD CONSTRAINT `fk_receiving_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`id`),
  ADD CONSTRAINT `fk_receiving_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_receiving_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `mro_items`
--
ALTER TABLE `mro_items`
  ADD CONSTRAINT `fk_mro_category` FOREIGN KEY (`category_id`) REFERENCES `mro_categories` (`id`);

--
-- Constraints for table `mro_price_history`
--
ALTER TABLE `mro_price_history`
  ADD CONSTRAINT `mro_price_history_ibfk_1` FOREIGN KEY (`mro_item_id`) REFERENCES `mro_items` (`id`),
  ADD CONSTRAINT `mro_price_history_ibfk_2` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mro_price_history_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mro_price_history_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `packaging_runs`
--
ALTER TABLE `packaging_runs`
  ADD CONSTRAINT `packaging_runs_ibfk_1` FOREIGN KEY (`production_run_id`) REFERENCES `production_runs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `packaging_runs_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `packaging_runs_ibfk_3` FOREIGN KEY (`packaged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `packaging_run_items`
--
ALTER TABLE `packaging_run_items`
  ADD CONSTRAINT `packaging_run_items_ibfk_1` FOREIGN KEY (`packaging_run_id`) REFERENCES `packaging_runs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `packaging_run_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pos_transactions`
--
ALTER TABLE `pos_transactions`
  ADD CONSTRAINT `fk_pos_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_pos_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_pos_voider` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `pos_transaction_items`
--
ALTER TABLE `pos_transaction_items`
  ADD CONSTRAINT `fk_pti_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `fk_pti_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `po_receiving_log`
--
ALTER TABLE `po_receiving_log`
  ADD CONSTRAINT `po_receiving_log_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `po_receiving_log_ibfk_2` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`),
  ADD CONSTRAINT `po_receiving_log_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `price_canvass`
--
ALTER TABLE `price_canvass`
  ADD CONSTRAINT `price_canvass_ibfk_1` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `price_canvass_ibfk_2` FOREIGN KEY (`mro_item_id`) REFERENCES `mro_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `price_canvass_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `production_batches`
--
ALTER TABLE `production_batches`
  ADD CONSTRAINT `fk_batch_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_batch_fg_receiver` FOREIGN KEY (`fg_received_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_batch_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_batch_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `master_recipes` (`id`),
  ADD CONSTRAINT `fk_batch_releaser` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_batch_run` FOREIGN KEY (`run_id`) REFERENCES `production_runs` (`id`);

--
-- Constraints for table `production_ccp_logs`
--
ALTER TABLE `production_ccp_logs`
  ADD CONSTRAINT `fk_ccp_run` FOREIGN KEY (`run_id`) REFERENCES `production_runs` (`id`),
  ADD CONSTRAINT `fk_ccp_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `production_material_usage`
--
ALTER TABLE `production_material_usage`
  ADD CONSTRAINT `fk_pmu_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_pmu_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_pmu_run` FOREIGN KEY (`run_id`) REFERENCES `production_runs` (`id`);

--
-- Constraints for table `production_output`
--
ALTER TABLE `production_output`
  ADD CONSTRAINT `fk_po_batch` FOREIGN KEY (`output_batch_id`) REFERENCES `production_batches` (`id`),
  ADD CONSTRAINT `fk_po_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_po_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_po_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_po_run` FOREIGN KEY (`run_id`) REFERENCES `production_runs` (`id`);

--
-- Constraints for table `production_runs`
--
ALTER TABLE `production_runs`
  ADD CONSTRAINT `fk_run_completed` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_run_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_run_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `master_recipes` (`id`),
  ADD CONSTRAINT `fk_run_started` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`);

--
-- Constraints for table `product_prices`
--
ALTER TABLE `product_prices`
  ADD CONSTRAINT `product_prices_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_po_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_po_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_po_requisition` FOREIGN KEY (`requisition_id`) REFERENCES `material_requisitions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_poi_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_poi_mro` FOREIGN KEY (`mro_item_id`) REFERENCES `mro_items` (`id`),
  ADD CONSTRAINT `fk_poi_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_batch_release`
--
ALTER TABLE `qc_batch_release`
  ADD CONSTRAINT `fk_qc_release_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_qc_release_batch` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`),
  ADD CONSTRAINT `fk_qc_release_inspector` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `qc_milk_tests`
--
ALTER TABLE `qc_milk_tests`
  ADD CONSTRAINT `fk_qc_test_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_qc_test_receiving` FOREIGN KEY (`receiving_id`) REFERENCES `milk_receiving` (`id`),
  ADD CONSTRAINT `fk_qc_test_tester` FOREIGN KEY (`tested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_qc_test_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `raw_milk_inventory`
--
ALTER TABLE `raw_milk_inventory`
  ADD CONSTRAINT `fk_raw_inv_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`),
  ADD CONSTRAINT `fk_raw_inv_qc` FOREIGN KEY (`qc_test_id`) REFERENCES `qc_milk_tests` (`id`),
  ADD CONSTRAINT `fk_raw_inv_receiving` FOREIGN KEY (`receiving_id`) REFERENCES `milk_receiving` (`id`),
  ADD CONSTRAINT `fk_raw_inv_tank` FOREIGN KEY (`tank_id`) REFERENCES `storage_tanks` (`id`),
  ADD CONSTRAINT `fk_raw_inv_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `recall_activity_log`
--
ALTER TABLE `recall_activity_log`
  ADD CONSTRAINT `recall_activity_log_ibfk_1` FOREIGN KEY (`recall_id`) REFERENCES `batch_recalls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recall_activity_log_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `recall_affected_locations`
--
ALTER TABLE `recall_affected_locations`
  ADD CONSTRAINT `recall_affected_locations_ibfk_1` FOREIGN KEY (`recall_id`) REFERENCES `batch_recalls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recall_returns`
--
ALTER TABLE `recall_returns`
  ADD CONSTRAINT `recall_returns_ibfk_1` FOREIGN KEY (`recall_id`) REFERENCES `batch_recalls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recall_returns_ibfk_2` FOREIGN KEY (`affected_location_id`) REFERENCES `recall_affected_locations` (`id`),
  ADD CONSTRAINT `recall_returns_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD CONSTRAINT `fk_recipe_ing_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_recipe_ing_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `master_recipes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `repair_parts_used`
--
ALTER TABLE `repair_parts_used`
  ADD CONSTRAINT `repair_parts_used_ibfk_1` FOREIGN KEY (`repair_id`) REFERENCES `machine_repairs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repair_parts_used_ibfk_2` FOREIGN KEY (`mro_item_id`) REFERENCES `mro_items` (`id`),
  ADD CONSTRAINT `repair_parts_used_ibfk_3` FOREIGN KEY (`mro_inventory_id`) REFERENCES `mro_inventory` (`id`);

--
-- Constraints for table `sales_customer_sub_accounts`
--
ALTER TABLE `sales_customer_sub_accounts`
  ADD CONSTRAINT `sales_customer_sub_accounts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `sales_invoice_items`
--
ALTER TABLE `sales_invoice_items`
  ADD CONSTRAINT `sales_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`id`);

--
-- Constraints for table `sales_invoice_payments`
--
ALTER TABLE `sales_invoice_payments`
  ADD CONSTRAINT `sales_invoice_payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`id`);

--
-- Constraints for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `fk_so_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_so_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_so_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_so_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD CONSTRAINT `fk_soi_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_soi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sales_transaction_items`
--
ALTER TABLE `sales_transaction_items`
  ADD CONSTRAINT `sales_transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `storage_tanks`
--
ALTER TABLE `storage_tanks`
  ADD CONSTRAINT `fk_tank_milk_type` FOREIGN KEY (`milk_type_id`) REFERENCES `milk_types` (`id`);

--
-- Constraints for table `supplier_returns`
--
ALTER TABLE `supplier_returns`
  ADD CONSTRAINT `fk_sr_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sr_initiator` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_sr_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `fk_sr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `supplier_return_items`
--
ALTER TABLE `supplier_return_items`
  ADD CONSTRAINT `fk_sri_batch` FOREIGN KEY (`ingredient_batch_id`) REFERENCES `ingredient_batches` (`id`),
  ADD CONSTRAINT `fk_sri_po_item` FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items` (`id`),
  ADD CONSTRAINT `fk_sri_return` FOREIGN KEY (`return_id`) REFERENCES `supplier_returns` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
