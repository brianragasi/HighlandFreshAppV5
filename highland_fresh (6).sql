-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 05, 2026 at 12:33 PM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'create', 'sales_transactions', 27, NULL, '{\"transaction_code\":\"SI-2026-00027\",\"total\":105,\"items\":1}', '::1', 'curl/8.16.0', '2026-02-03 10:44:00'),
(2, 1, 'create', 'sales_transactions', 28, NULL, '{\"transaction_code\":\"SI-2026-00028\",\"total\":105,\"items\":1}', '::1', 'curl/8.16.0', '2026-02-03 10:44:31'),
(3, 7, 'create', 'sales_transactions', 29, NULL, '{\"transaction_code\":\"SI-2026-00029\",\"total\":60,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 10:44:45'),
(4, 7, 'create', 'sales_transactions', 30, NULL, '{\"transaction_code\":\"SI-2026-00030\",\"total\":140,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 10:55:28'),
(5, 7, 'create', 'payment_collections', 9, NULL, '{\"or_number\":\"OR-2026-00001\",\"dr_number\":\"DR-20260124-0107\",\"amount\":11250,\"method\":\"cash\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 11:44:35'),
(6, 7, 'start_shift', 'cashier_shifts', 1, NULL, '{\"opening_cash\":5000}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:22:01'),
(7, 7, 'create', 'sales_transactions', 31, NULL, '{\"transaction_code\":\"SI-2026-00031\",\"total\":50,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:22:09'),
(8, 7, 'end_shift', 'cashier_shifts', 1, '{\"status\":\"active\"}', '{\"status\":\"closed\",\"expected_cash\":5050,\"actual_cash\":null,\"variance\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:22:32'),
(9, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:27:41'),
(10, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:39:35'),
(11, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:43:02'),
(12, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:44:12'),
(13, 2, 'CREATE', 'milk_receiving', 18, NULL, '{\"receiving_code\":\"RCV-20260203-001\",\"rmr_number\":66190,\"farmer_id\":\"10\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:44:29'),
(14, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:50:03'),
(15, 2, 'CREATE', 'milk_receiving', 19, NULL, '{\"receiving_code\":\"RCV-20260203-002\",\"rmr_number\":66191,\"farmer_id\":\"2\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 13:53:17'),
(16, 2, 'CREATE', 'qc_milk_tests', 18, NULL, '{\"test_code\":\"QCT-000001\",\"receiving_id\":\"19\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 14:00:16'),
(17, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 14:05:46'),
(18, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 14:11:03'),
(19, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 14:13:20'),
(20, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-03 14:34:28'),
(21, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-04 09:35:26'),
(22, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-04 09:39:10'),
(23, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-04 09:39:45'),
(24, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:38:06'),
(25, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:38:20'),
(26, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:38:31'),
(27, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 1, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:41:35'),
(28, 4, 'approve_requisition', 'material_requisitions', 2, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:42:43'),
(29, 4, 'fulfill_requisition', 'material_requisitions', 2, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:42:49'),
(30, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:44:29'),
(31, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:44:45'),
(32, 4, 'adjust_stock', 'mro_items', 2, '{\"current_stock\":\"21.00\"}', '{\"current_stock\":\"22\",\"reason\":\"Physical count correction\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:46:07'),
(33, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:46:27'),
(34, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:47:22'),
(35, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:48:29'),
(36, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:48:38'),
(37, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:50:00'),
(38, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:51:29'),
(39, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:52:08'),
(40, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:52:24'),
(41, 4, 'approve_requisition', 'material_requisitions', 4, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:52:39'),
(42, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:54:40'),
(43, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:55:06'),
(44, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:55:32'),
(45, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:55:40'),
(46, 4, 'approve_requisition', 'material_requisitions', 5, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:55:52'),
(47, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:57:40'),
(48, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:58:16'),
(49, 4, 'fulfill_requisition', 'material_requisitions', 4, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:59:12'),
(50, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 05:59:30'),
(51, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:02:26'),
(52, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:02:43'),
(53, 2, 'CREATE', 'milk_receiving', 20, NULL, '{\"receiving_code\":\"RCV-20260205-001\",\"rmr_number\":66192,\"farmer_id\":\"3\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:02:57'),
(54, 2, 'CREATE', 'qc_milk_tests', 19, NULL, '{\"test_code\":\"QCT-000002\",\"receiving_id\":\"20\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:03:13'),
(55, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:03:20'),
(56, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 2, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:03:31'),
(57, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:03:53'),
(58, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:04:05'),
(59, 4, 'fulfill_requisition', 'material_requisitions', 4, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:05:17'),
(60, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:06:03'),
(61, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:06:49'),
(62, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:09:10'),
(63, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:09:56'),
(64, 4, 'approve_requisition', 'material_requisitions', 6, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:10:12'),
(65, 4, 'fulfill_requisition', 'material_requisitions', 6, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:10:16'),
(66, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:10:29'),
(67, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:31:53'),
(68, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 06:32:47'),
(69, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:15:46'),
(70, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:21:51'),
(71, 7, 'create', 'sales_transactions', 32, NULL, '{\"transaction_code\":\"SI-2026-00032\",\"total\":105,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:22:00'),
(72, 7, 'create', 'payment_collections', 10, NULL, '{\"or_number\":\"OR-2026-00002\",\"dr_number\":\"DR-20260127-0106\",\"amount\":7500,\"method\":\"cash\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:22:13'),
(73, 7, 'create', 'sales_transactions', 33, NULL, '{\"transaction_code\":\"SI-2026-00033\",\"total\":45,\"items\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:26:23'),
(74, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:28:00'),
(75, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:28:26'),
(76, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:28:46'),
(77, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:37:54'),
(78, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 07:58:29'),
(79, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:06:59'),
(80, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:08:52'),
(81, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:29:54'),
(82, 2, 'RELEASE', 'production_batches', 14, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:30:02'),
(83, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:30:15'),
(84, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:31:30'),
(85, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:37:03'),
(86, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:37:20'),
(87, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:42:30'),
(88, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:43:10'),
(89, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:46:45'),
(90, 6, 'CREATE', 'sales_orders', 2, NULL, '{\"action\":\"create\",\"customer_id\":5,\"customer_po_number\":null,\"sub_account_id\":null,\"delivery_date\":\"2026-02-05\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":8,\"quantity\":13,\"quantity_boxes\":1,\"quantity_pieces\":1,\"unit_type\":\"mixed\",\"unit_price\":95}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 08:59:12'),
(91, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 09:01:48'),
(92, 8, 'UPDATE_STATUS', 'sales_orders', 2, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:10:06'),
(93, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:10:19'),
(94, 8, 'UPDATE_STATUS', 'sales_orders', 2, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:10:33'),
(95, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:10:38'),
(96, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:16:49'),
(97, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:17:22'),
(98, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:23:35'),
(99, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:24:20'),
(100, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:25:01'),
(101, 4, 'fulfill_requisition', 'material_requisitions', 5, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:25:26'),
(102, 4, 'approve_requisition', 'material_requisitions', 7, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:25:29'),
(103, 4, 'fulfill_requisition', 'material_requisitions', 7, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:25:32'),
(104, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:29:50'),
(105, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:30:00'),
(106, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:31:25'),
(107, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:41:27'),
(108, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:41:40'),
(109, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:55:48'),
(110, 2, 'CREATE', 'milk_receiving', 21, NULL, '{\"receiving_code\":\"RCV-20260205-002\",\"rmr_number\":66193,\"farmer_id\":\"3\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:55:59'),
(111, 2, 'CREATE', 'qc_milk_tests', 20, NULL, '{\"test_code\":\"QCT-000003\",\"receiving_id\":\"21\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:56:14'),
(112, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:56:25'),
(113, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 4, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:56:49'),
(114, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:57:09'),
(115, 2, 'CREATE', 'milk_receiving', 22, NULL, '{\"receiving_code\":\"RCV-20260205-003\",\"rmr_number\":66194,\"farmer_id\":\"2\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:57:22'),
(116, 2, 'CREATE', 'qc_milk_tests', 21, NULL, '{\"test_code\":\"QCT-000004\",\"receiving_id\":\"22\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:57:34'),
(117, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:57:50'),
(118, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:58:03'),
(119, 4, 'assign_milk_to_tank', 'raw_milk_inventory', 5, NULL, '{\"tank_id\":10,\"tank_code\":\"PRT-001\",\"volume_liters\":\"50.00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:58:11'),
(120, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:58:20'),
(121, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:58:49'),
(122, 4, 'approve_requisition', 'material_requisitions', 8, NULL, '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:59:01'),
(123, 4, 'fulfill_requisition', 'material_requisitions', 8, '{\"status\":\"approved\"}', '{\"status\":\"fulfilled\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:59:03'),
(124, 3, 'LOGIN', 'users', 3, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 10:59:12'),
(125, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:00:16'),
(126, 2, 'RELEASE', 'production_batches', 15, NULL, '{\"action\":\"release\",\"qc_notes\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:00:25'),
(127, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:09:12'),
(128, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:09:46'),
(129, 2, 'LOGIN', 'users', 2, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:13:15'),
(130, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:16:39'),
(131, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:17:38'),
(132, 6, 'CREATE', 'sales_orders', 3, NULL, '{\"action\":\"create\",\"customer_id\":6,\"customer_po_number\":null,\"sub_account_id\":\"5\",\"delivery_date\":\"2026-02-05\",\"payment_mode\":\"cash\",\"notes\":\"\",\"items\":[{\"product_id\":7,\"quantity\":21,\"quantity_boxes\":1,\"quantity_pieces\":1,\"unit_type\":\"mixed\",\"unit_price\":140}]}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:18:00'),
(133, 6, 'UPDATE_STATUS', 'sales_orders', 3, '{\"status\":\"draft\"}', '{\"status\":\"pending\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:18:04'),
(134, 6, 'UPDATE_STATUS', 'sales_orders', 3, '{\"status\":\"pending\"}', '{\"status\":\"approved\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:18:11'),
(135, 5, 'LOGIN', 'users', 5, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:18:23'),
(136, 6, 'LOGIN', 'users', 6, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-02-05 11:19:18');

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
(1, 'SHIFT-20260203-001', 7, '2026-02-03 21:22:01', '2026-02-03 21:22:32', 5000.00, 5050.00, NULL, NULL, 50.00, 0.00, 1, 0.00, 0.00, 'closed', '', '', '2026-02-03 13:22:01', '2026-02-03 13:22:32');

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
(1, 'CHILL-A1', 'Chiller A - Section 1', 500, 826, 10.0, 2.0, 4.0, 'Main Warehouse', 'full', 1, NULL, '2026-02-03 09:12:00', '2026-02-05 11:17:02'),
(2, 'CHILL-A2', 'Chiller A - Section 2', 500, 50, 3.2, 2.0, 4.0, 'Main Warehouse', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-05 08:36:51'),
(3, 'CHILL-B1', 'Chiller B - Section 1', 400, 0, 2.8, 2.0, 4.0, 'Main Warehouse', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-03 09:12:00'),
(4, 'CHILL-B2', 'Chiller B - Section 2', 400, 0, 3.1, 2.0, 4.0, 'Main Warehouse', 'available', 1, NULL, '2026-02-03 09:12:00', '2026-02-03 09:12:00'),
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
(2, NULL, 'institutional', 'Robinson\'s Supermarket', 'Downtown', 'Maria Supervisor', '09181234567', 'robinsons@example.com', 'Robinsons Place Tacloban', 80000.00, -11250.00, 30, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-03 11:44:35'),
(3, NULL, 'supermarket', 'Metro Gaisano', 'Downtown', 'Pedro Cruz', '09191234567', 'gaisano@example.com', 'Downtown Tacloban', 50000.00, 0.00, 15, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-03 09:47:31'),
(4, NULL, 'supermarket', 'PureGold', 'Real Street', 'Ana Reyes', '09201234567', 'puregold@example.com', 'Real Street Tacloban', 75000.00, 0.00, 30, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-03 09:47:31'),
(5, NULL, 'restaurant', 'Hotel 101', 'Main', 'Chris Santos', '09211234567', 'hotel101@example.com', 'Hotel 101 Tacloban', 30000.00, 0.00, 7, 'cash', 'active', NULL, '2026-02-03 09:47:31', '2026-02-03 09:47:31'),
(6, 'DEPED-CDO-001', 'feeding_program', 'DepEd Region X Feeding Program', NULL, 'Maria Santos', '09171234567', NULL, 'DepEd Complex, Cagayan de Oro City', 500000.00, 0.00, 0, 'cash', 'active', NULL, '2026-02-05 08:06:35', '2026-02-05 08:06:35');

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
  `delivery_address` text DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `status` enum('draft','pending','preparing','ready','dispatched','delivered','cancelled') DEFAULT 'draft',
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
  `received_by_name` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_receipts`
--

INSERT INTO `delivery_receipts` (`id`, `dr_number`, `order_id`, `customer_id`, `customer_name`, `delivery_address`, `contact_person`, `contact_number`, `total_items`, `total_amount`, `amount_paid`, `status`, `payment_status`, `priority`, `scheduled_date`, `scheduled_time`, `prepared_by`, `prepared_at`, `checked_by`, `checked_at`, `dispatched_by`, `dispatched_at`, `vehicle_number`, `driver_name`, `delivered_at`, `received_by_name`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'DR-20260203-0101', NULL, 1, 'SM Supermarket', 'SM Tacloban', NULL, '09171234567', 50, 8500.00, 0.00, 'delivered', 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', NULL, NULL, '2026-02-03 17:47:31', NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(2, 'DR-20260203-0102', NULL, 2, 'Robinson\'s Supermarket', 'Robinsons Tacloban', NULL, '09181234567', 30, 5250.00, 0.00, 'delivered', 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', NULL, NULL, '2026-02-03 17:47:31', NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(3, 'DR-20260203-0103', NULL, 3, 'Metro Gaisano', 'Downtown Tacloban', NULL, '09191234567', 75, 12750.00, 5000.00, 'delivered', 'partial', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-03 17:47:31', NULL, NULL, '2026-02-03 17:47:31', NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(4, 'DR-20260131-0104', NULL, 4, 'PureGold', 'Real Street Tacloban', NULL, '09201234567', 40, 9500.00, 0.00, 'delivered', 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-31 17:47:31', NULL, NULL, '2026-01-31 17:47:31', NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(5, 'DR-20260129-0105', NULL, 5, 'Hotel 101', 'Hotel 101 Tacloban', NULL, '09211234567', 25, 4500.00, 2000.00, 'delivered', 'partial', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-29 17:47:31', NULL, NULL, '2026-01-29 17:47:31', NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 17:47:31'),
(6, 'DR-20260127-0106', NULL, 1, 'SM Supermarket', 'SM Tacloban', NULL, '09171234567', 60, 15000.00, 15000.00, 'delivered', 'paid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-27 17:47:31', NULL, NULL, '2026-01-27 17:47:31', NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-05 15:22:13'),
(7, 'DR-20260124-0107', NULL, 2, 'Robinson\'s Supermarket', 'Robinsons Tacloban', NULL, '09181234567', 45, 11250.00, 11250.00, 'delivered', 'paid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-24 17:47:31', NULL, NULL, '2026-01-24 17:47:31', NULL, NULL, 1, '2026-02-03 17:47:31', '2026-02-03 19:44:35'),
(8, 'DR-20260205-1010', 2, 5, 'Hotel 101', 'Hotel 101 Tacloban', NULL, '09211234567', 1, 1235.00, 0.00, 'dispatched', 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-05 18:31:32', NULL, NULL, NULL, NULL, NULL, 6, '2026-02-05 18:17:05', '2026-02-05 18:31:32'),
(9, 'DR-20260205-0201', 3, 6, 'DepEd Region X Feeding Program', 'DepEd Complex, Cagayan de Oro City', NULL, '09171234567', 1, 2940.00, 0.00, 'dispatched', 'unpaid', 'normal', NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-02-05 19:18:31', NULL, NULL, NULL, NULL, NULL, 6, '2026-02-05 19:18:16', '2026-02-05 19:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_receipt_items`
--

CREATE TABLE `delivery_receipt_items` (
  `id` int(11) NOT NULL,
  `delivery_receipt_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_packed` int(11) DEFAULT 0,
  `quantity_delivered` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00,
  `chiller_source_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_receipt_items`
--

INSERT INTO `delivery_receipt_items` (`id`, `delivery_receipt_id`, `product_id`, `batch_id`, `quantity_ordered`, `quantity_packed`, `quantity_delivered`, `unit_price`, `total_price`, `chiller_source_id`, `notes`, `created_at`) VALUES
(1, 8, 8, NULL, 13, 0, 0, 95.00, 1235.00, NULL, NULL, '2026-02-05 18:17:05'),
(2, 9, 7, NULL, 21, 0, 0, 140.00, 2940.00, NULL, NULL, '2026-02-05 19:18:16');

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
(1, 'F-0001', 'Lacandula', '', NULL, NULL, 1, 'member', 39.25, NULL, NULL, 1, '2026-02-03 08:04:13', '2026-02-03 13:59:19'),
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
(26, 'FGT-20260205-0021', 'receive', 21, 2, 50, 0, 50, 0, 50, 0, 0, 0, 50, NULL, 1, 5, 'Received from production batch BATCH-20260205-0005', NULL, NULL, '2026-02-05 11:17:02');

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
  `batch_id` int(11) NOT NULL,
  `qc_release_id` int(11) NOT NULL COMMENT 'QC release that approved entry',
  `product_id` int(11) DEFAULT NULL,
  `milk_type_id` int(11) NOT NULL COMMENT 'For traceability',
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar') NOT NULL,
  `product_variant` varchar(100) DEFAULT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `size_ml` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL COMMENT 'Original quantity received',
  `remaining_quantity` int(11) NOT NULL,
  `quantity_available` int(11) NOT NULL DEFAULT 0,
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
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `finished_goods_inventory`
--

INSERT INTO `finished_goods_inventory` (`id`, `batch_id`, `qc_release_id`, `product_id`, `milk_type_id`, `product_name`, `product_type`, `product_variant`, `variant`, `size_ml`, `quantity`, `remaining_quantity`, `quantity_available`, `quantity_reserved`, `quantity_boxes`, `quantity_pieces`, `boxes_available`, `pieces_available`, `unit`, `unit_price`, `manufacturing_date`, `expiry_date`, `barcode`, `chiller_id`, `chiller_location`, `received_at`, `last_movement_at`, `received_by`, `status`, `notes`) VALUES
(2, 1, 1, 1, 1, 'Fresh Milk 1L', 'bottled_milk', NULL, NULL, NULL, 100, 100, 82, 0, 0, 0, 6, 10, 'bottle', NULL, '2026-02-03', '2026-02-10', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(3, 2, 2, 2, 1, 'Fresh Milk 500ml', 'bottled_milk', NULL, NULL, NULL, 100, 100, 99, 0, 0, 0, 4, 3, 'bottle', NULL, '2026-02-03', '2026-02-10', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(4, 3, 3, 3, 1, 'Chocolate Milk 1L', 'bottled_milk', NULL, NULL, NULL, 100, 100, 99, 0, 0, 0, 8, 3, 'bottle', NULL, '2026-02-03', '2026-02-10', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(5, 4, 4, 4, 1, 'Plain Yogurt 500g', 'yogurt', NULL, NULL, NULL, 100, 100, 98, 0, 0, 0, 4, 18, 'cup', NULL, '2026-02-03', '2026-02-17', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(6, 5, 5, 5, 1, 'Strawberry Yogurt 150g', 'yogurt', NULL, NULL, NULL, 100, 100, 99, 0, 0, 0, 2, 3, 'cup', NULL, '2026-02-03', '2026-02-17', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(7, 6, 6, 6, 1, 'Kesong Puti 250g', 'cheese', NULL, NULL, NULL, 100, 100, 100, 0, 0, 0, 4, 4, 'pack', NULL, '2026-02-03', '2026-02-24', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(8, 7, 7, 7, 1, 'Butter 250g', 'butter', NULL, NULL, NULL, 100, 100, 99, 0, 0, 0, 4, 19, 'block', NULL, '2026-02-03', '2026-03-05', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(9, 8, 8, 8, 1, 'Fresh Cream 1L', 'bottled_milk', NULL, NULL, NULL, 100, 100, 100, 0, 0, 0, 8, 4, 'bottle', NULL, '2026-02-03', '2026-02-13', NULL, 1, NULL, '2026-02-03 09:35:24', NULL, 1, 'available', NULL),
(20, 14, 9, 2, 1, 'Fresh Milk 500ml', 'bottled_milk', '500ml', NULL, NULL, 50, 50, 50, 0, 0, 50, 0, 50, 'pcs', NULL, '2026-02-05', '2026-02-12', NULL, 2, NULL, '2026-02-05 08:36:51', NULL, 5, 'available', ''),
(21, 15, 10, 2, 1, 'Fresh Milk 500ml', 'bottled_milk', '500ml', NULL, NULL, 50, 50, 50, 0, 0, 50, 0, 50, 'pcs', NULL, '2026-02-05', '2026-02-12', NULL, 1, NULL, '2026-02-05 11:17:02', NULL, 5, 'available', '');

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

INSERT INTO `ingredients` (`id`, `ingredient_code`, `ingredient_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `reorder_point`, `lead_time_days`, `current_stock`, `reserved_stock`, `unit_cost`, `storage_location`, `storage_requirements`, `shelf_life_days`, `is_active`, `created_at`, `updated_at`) VALUES
(9, 'ING-001', 'Sugar', 1, 'kg', 50.00, 75.00, 3, 200.00, 0.00, 45.00, 'Dry Storage A', 'Cool dry place', 365, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(10, 'ING-002', 'Vanilla Extract', 2, 'liter', 5.00, 8.00, 7, 15.00, 0.00, 850.00, 'Cold Storage', 'Refrigerated', 180, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(11, 'ING-003', 'Chocolate Powder', 2, 'kg', 20.00, 30.00, 5, 75.00, 0.00, 320.00, 'Dry Storage A', 'Cool dry place', 270, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(12, 'ING-004', 'Stabilizer', 3, 'kg', 10.00, 15.00, 7, 40.00, 0.00, 480.00, 'Dry Storage B', 'Cool dry place', 365, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(13, 'ING-005', 'Cultures (Yogurt)', 4, 'packet', 20.00, 30.00, 14, 60.00, 0.00, 150.00, 'Freezer', 'Frozen -18C', 90, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(14, 'ING-006', 'Salt', 3, 'kg', 25.00, 40.00, 3, 100.00, 0.00, 25.00, 'Dry Storage A', 'Cool dry place', 730, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(15, 'ING-007', 'Rennet', 4, 'liter', 3.00, 5.00, 14, 10.00, 0.00, 1200.00, 'Cold Storage', 'Refrigerated', 180, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32'),
(16, 'ING-008', 'Food Coloring', 3, 'liter', 2.00, 4.00, 5, 8.00, 0.00, 350.00, 'Dry Storage B', 'Cool dry place', 365, 1, '2026-02-03 08:50:32', '2026-02-03 08:50:32');

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
(13, 'TX-619053', '', 'raw_milk', 4, 4, 50.000, 'L', NULL, NULL, 'requisition', 8, 'PRT-001', NULL, NULL, NULL, 4, NULL, 'Requisition fulfillment', '2026-02-05 10:59:03');

-- --------------------------------------------------------

--
-- Table structure for table `master_recipes`
--

CREATE TABLE `master_recipes` (
  `id` int(11) NOT NULL,
  `recipe_code` varchar(30) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar') NOT NULL,
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
(12, 'RCP-FM-1L', 1, 'Fresh Milk 1L', 'bottled_milk', '1 Liter', 1, NULL, 100.00, 95, 'bottles', 7, 75.00, 15, 4.00, 'Standard pasteurization process for 1L fresh milk', 1, 1, '2026-02-03 09:22:39', '2026-02-05 08:35:59'),
(13, 'RCP-FM-500', 2, 'Fresh Milk 500ml', 'bottled_milk', '500ml', 1, NULL, 100.00, 190, 'bottles', 7, 75.00, 15, 4.00, 'Standard pasteurization process for 500ml fresh milk', 1, 1, '2026-02-03 09:22:39', '2026-02-05 08:36:00'),
(14, 'RCP-CHO-1L', 3, 'Chocolate Milk 1L', 'bottled_milk', '1 Liter', 1, NULL, 100.00, 92, 'bottles', 7, 75.00, 15, 4.00, 'Add chocolate powder after pasteurization', 1, 1, '2026-02-03 09:22:39', '2026-02-05 08:36:00'),
(15, 'RCP-YOG-500', 4, 'Plain Yogurt 500g', 'yogurt', '500g', 1, NULL, 100.00, 180, 'cups', 14, 85.00, 30, 43.00, 'Fermentation for 6-8 hours at 43C', 1, 1, '2026-02-03 09:22:39', '2026-02-05 08:36:00'),
(16, 'RCP-CHE-250', 6, 'Kesong Puti 250g', 'cheese', '250g', 1, NULL, 100.00, 35, 'packs', 21, 75.00, 15, 35.00, 'Add rennet and cultures, age for 24 hours', 1, 1, '2026-02-03 09:22:39', '2026-02-05 08:36:00'),
(17, 'RCP-BUT-250', 7, 'Butter 250g', 'butter', '250g', 1, NULL, 100.00, 20, 'blocks', 30, 75.00, 15, 10.00, 'Churn cream until butter forms, wash and shape', 1, 1, '2026-02-03 09:22:39', '2026-02-05 08:36:00');

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
(1, 'REQ-2026-0001', NULL, 2, 'production', 'normal', '2026-02-04 00:00:00', 'Production batch YOG-001', 3, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-03 08:46:55'),
(2, 'REQ-2026-0002', NULL, 2, 'maintenance', 'high', '2026-02-03 00:00:00', 'Pasteurizer maintenance', 2, 'fulfilled', 4, '2026-02-05 13:42:43', NULL, 4, '2026-02-05 13:42:49', NULL, '2026-02-03 08:46:55', '2026-02-05 05:42:49'),
(3, 'REQ-2026-0003', NULL, 2, 'production', 'urgent', '2026-02-03 00:00:00', 'Emergency production run', 2, 'approved', NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 08:46:55', '2026-02-03 08:46:55'),
(4, 'REQ-20260205-001', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 13:52:39', NULL, 4, '2026-02-05 14:05:17', NULL, '2026-02-05 05:47:17', '2026-02-05 06:08:37'),
(5, 'REQ-20260205-002', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 13:55:51', NULL, 4, '2026-02-05 18:25:26', NULL, '2026-02-05 05:54:55', '2026-02-05 10:25:26'),
(6, 'REQ-20260205-003', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 14:10:12', NULL, 4, '2026-02-05 14:10:16', NULL, '2026-02-05 06:09:28', '2026-02-05 06:10:16'),
(7, 'REQ-20260205-004', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 18:25:29', NULL, 4, '2026-02-05 18:25:32', NULL, '2026-02-05 10:24:53', '2026-02-05 10:25:32'),
(8, 'REQ-20260205-005', NULL, 3, 'production', 'normal', NULL, '', 1, 'fulfilled', 4, '2026-02-05 18:59:01', NULL, 4, '2026-02-05 18:59:03', NULL, '2026-02-05 10:58:37', '2026-02-05 10:59:03');

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
  `status` enum('pending_qc','in_testing','accepted','rejected','partial') DEFAULT 'pending_qc',
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milk_receiving`
--

INSERT INTO `milk_receiving` (`id`, `receiving_code`, `rmr_number`, `farmer_id`, `milk_type_id`, `receiving_date`, `receiving_time`, `volume_liters`, `rejected_liters`, `accepted_liters`, `temperature_celsius`, `transport_container`, `visual_inspection`, `visual_notes`, `status`, `received_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'RCV-2025-10-21-001', '66173', 1, 1, '2025-10-21', '08:00:00', 55.00, 0.00, 55.00, NULL, NULL, 'pass', NULL, 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(2, 'RCV-2025-10-21-002', '66174', 2, 1, '2025-10-21', '08:00:00', 112.00, 0.00, 112.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱500.00', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(3, 'RCV-2025-10-21-003', '66175', 3, 1, '2025-10-21', '08:00:00', 20.00, 87.00, -67.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱141.51', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(4, 'RCV-2025-10-21-004', '66176', 4, 1, '2025-10-21', '08:00:00', 93.00, 0.00, 93.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱658.02', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(5, 'RCV-2025-10-21-005', '66177', 5, 1, '2025-10-21', '08:00:00', 59.00, 0.00, 59.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱417.45', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(6, 'RCV-2025-10-21-006', '66178', 6, 1, '2025-10-21', '08:00:00', 40.00, 0.00, 40.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱283.02', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(7, 'RCV-2025-10-21-007', '66179', 7, 1, '2025-10-21', '08:00:00', 598.00, 0.00, 598.00, NULL, NULL, 'pass', NULL, 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(8, 'RCV-2025-10-21-008', '66180', 8, 1, '2025-10-21', '08:00:00', 26.00, 0.00, 26.00, NULL, NULL, 'pass', NULL, 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(9, 'RCV-2025-10-21-009', '66181', 9, 1, '2025-10-21', '08:00:00', 124.00, 0.00, 124.00, NULL, NULL, 'pass', NULL, 'accepted', 4, NULL, '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(10, 'RCV-2025-10-21-010', '66182', 10, 1, '2025-10-21', '08:00:00', 201.00, 0.00, 201.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱258.35', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(11, 'RCV-2025-10-21-011', '66183', 11, 2, '2025-10-21', '08:00:00', 8.00, 0.00, 8.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱10.28', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(12, 'RCV-2025-10-21-012', '66184', 12, 1, '2025-10-21', '08:00:00', 149.00, 57.00, 92.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱191.52', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(13, 'RCV-2025-10-21-013', '66185', 13, 1, '2025-10-21', '08:00:00', 42.00, 0.00, 42.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱53.98', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(14, 'RCV-2025-10-21-014', '66186', 14, 1, '2025-10-21', '08:00:00', 91.00, 0.00, 91.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱116.97', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(15, 'RCV-2025-10-21-015', '66187', 15, 1, '2025-10-21', '08:00:00', 173.00, 27.00, 146.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱222.37', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(16, 'RCV-2025-10-21-016', '66188', 16, 1, '2025-10-21', '08:00:00', 401.00, 0.00, 401.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱515.42', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(17, 'RCV-2025-10-21-017', '66189', 17, 1, '2025-10-21', '08:00:00', 102.00, 0.00, 102.00, NULL, NULL, 'pass', NULL, 'accepted', 4, 'Transport cost: ₱131.11', '2026-02-03 08:04:13', '2026-02-03 08:04:13'),
(18, 'RCV-20260203-001', '66190', 10, 1, '2026-02-03', '21:44:29', 50.00, 0.00, 0.00, NULL, NULL, 'pending', '', 'pending_qc', 2, 'Basta', '2026-02-03 13:44:29', '2026-02-03 13:44:29'),
(19, 'RCV-20260203-002', '66191', 2, 1, '2026-02-03', '21:53:17', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'accepted', 2, '', '2026-02-03 13:53:17', '2026-02-03 14:00:16'),
(20, 'RCV-20260205-001', '66192', 3, 1, '2026-02-05', '14:02:57', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'accepted', 2, 'Basta', '2026-02-05 06:02:57', '2026-02-05 06:03:13'),
(21, 'RCV-20260205-002', '66193', 3, 1, '2026-02-05', '18:55:59', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'accepted', 2, '', '2026-02-05 10:55:59', '2026-02-05 10:56:14'),
(22, 'RCV-20260205-003', '66194', 2, 1, '2026-02-05', '18:57:22', 50.00, 0.00, 50.00, NULL, NULL, 'pending', '', 'accepted', 2, '', '2026-02-05 10:57:22', '2026-02-05 10:57:34');

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
  `quantity` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
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

INSERT INTO `mro_inventory` (`id`, `batch_code`, `mro_item_id`, `quantity`, `remaining_quantity`, `unit_cost`, `supplier_name`, `received_date`, `received_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'MRO-20260203-7069', 1, 22.00, 21.00, 369.00, 'MRO Supplier', '2026-02-03', 2, 'partially_used', NULL, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(2, 'MRO-20260203-1365', 2, 21.00, 21.00, 120.00, 'MRO Supplier', '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(3, 'MRO-20260203-4751', 3, 33.00, 33.00, 398.00, 'MRO Supplier', '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(4, 'MRO-20260203-4081', 4, 20.00, 20.00, 298.00, 'MRO Supplier', '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(5, 'MRO-20260203-1961', 5, 34.00, 34.00, 283.00, 'MRO Supplier', '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(6, 'MRO-20260203-5431', 6, 42.00, 42.00, 106.00, 'MRO Supplier', '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(7, 'MRO-20260203-2808', 7, 34.00, 32.00, 324.00, 'MRO Supplier', '2026-02-03', 2, 'partially_used', NULL, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(8, 'MRO-20260203-4206', 8, 20.00, 20.00, 165.00, 'MRO Supplier', '2026-02-03', 2, 'available', NULL, '2026-02-03 08:49:05', '2026-02-03 08:49:05');

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

INSERT INTO `mro_items` (`id`, `item_code`, `item_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `lead_time_days`, `current_stock`, `unit_cost`, `storage_location`, `compatible_equipment`, `is_critical`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'MRO-001', 'Pasteurizer Gasket Set', 1, 'set', 3.00, 7, 21.00, 369.00, 'Shelf A1', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(2, 'MRO-002', 'Homogenizer Valve', 1, 'pcs', 2.00, 7, 22.00, 120.00, 'Shelf A2', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-05 05:46:07'),
(3, 'MRO-003', 'Tank Agitator Belt', 1, 'pcs', 5.00, 7, 33.00, 398.00, 'Shelf A3', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(4, 'MRO-004', 'Temperature Sensor', 1, 'pcs', 2.00, 7, 20.00, 298.00, 'Shelf B1', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(5, 'MRO-005', 'Pump Seal Kit', 1, 'kit', 3.00, 7, 34.00, 283.00, 'Shelf B2', NULL, 1, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(6, 'MRO-006', 'CIP Cleaning Solution', 3, 'liter', 20.00, 7, 42.00, 106.00, 'Chemical Room', NULL, 0, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05'),
(7, 'MRO-007', 'Food Grade Lubricant', 5, 'liter', 5.00, 7, 32.00, 324.00, 'Lube Room', NULL, 0, 1, '2026-02-03 08:49:05', '2026-02-05 05:42:49'),
(8, 'MRO-008', 'Safety Goggles', 4, 'pcs', 10.00, 7, 20.00, 165.00, 'PPE Cabinet', NULL, 0, 1, '2026-02-03 08:49:05', '2026-02-03 08:49:05');

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
(10, 'OR-2026-00002', 6, 'DR-20260127-0106', NULL, 1, 'SM Supermarket', 7500.00, 7500.00, 0.00, 'cash', '[]', 7, '2026-02-05 07:22:13', '', 'confirmed', '2026-02-05 07:22:13', '2026-02-05 07:22:13');

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
(15, 'BATCH-20260205-0005', 5, 13, NULL, 1, 'bottled_milk', NULL, 100.00, 1.00, '2026-02-05', NULL, '2026-02-12', NULL, NULL, NULL, NULL, 0, 0, 0, 'released', '2026-02-05 19:00:25', '', 1, NULL, NULL, 50, 50, 'BATCH-20260205-0005-260205', 3, 2, '2026-02-05 11:00:25', '2026-02-05 11:00:03', '2026-02-05 11:17:02');

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
(10, 5, 'cooling', 4.00, NULL, 0, 0, 4.00, 1.00, 'pass', '2026-02-05 10:59:53', 3, '', '2026-02-05 10:59:53');

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
(5, 'PRD-20260205-003', 13, 1, 50, 50, 100.00, 'raw', NULL, 'completed', '2026-02-05 18:59:26', '2026-02-05 19:00:03', 3, 3, 0.00, '', '', '2026-02-05 10:59:24', '2026-02-05 11:00:03', '{\"total_pieces\":50,\"secondary_count\":2,\"secondary_unit\":\"crates\",\"remaining_primary\":2,\"primary_unit\":\"bottles\",\"input_quantity\":50,\"input_unit\":\"pieces\",\"conversion_factor\":24}', '{\"source\":\"requisition_based\",\"available_at_creation\":200,\"allocated\":\"100.00\",\"pasteurized_batch_id\":null}', 75.00, 15, NULL, NULL, NULL, NULL, 0);

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
(8, 'CRM-1L', 'Fresh Cream 1L', 'cream', '1 Liter', NULL, NULL, 1000.00, 'ml', 10, 2.00, 4.00, 'bottle', 'box', 12, 80.00, 95.00, 65.00, 1, '2026-02-03 09:12:00', '2026-02-03 09:31:04');

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
  `notes` text DEFAULT NULL,
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

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `order_date`, `expected_delivery`, `status`, `subtotal`, `vat_amount`, `total_amount`, `payment_status`, `notes`, `created_by`, `approved_by`, `approved_at`, `received_at`, `created_at`, `updated_at`) VALUES
(1, '5231', 1, '2025-01-04', '2025-01-11', 'received', 29750.00, 0.00, 29750.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(2, '5232', 2, '2025-01-07', '2025-01-14', 'received', 102000.00, 0.00, 102000.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(3, '5233', 1, '2025-01-08', '2025-01-15', 'received', 59500.00, 0.00, 59500.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(4, '5234', 2, '2025-01-09', '2025-01-16', 'received', 83400.00, 0.00, 83400.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(5, '5235', 3, '2025-01-14', '2025-01-21', 'received', 13600.00, 1632.00, 15232.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(6, '5236', 1, '2025-01-11', '2025-01-18', 'received', 29750.00, 0.00, 29750.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(7, '5237', 2, '2025-01-15', '2025-01-22', 'received', 105000.00, 0.00, 105000.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(8, '5238', 3, '2025-01-17', '2025-01-24', 'received', 40388.25, 0.00, 40388.25, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(9, '5239', 1, '2025-01-15', '2025-01-22', 'received', 59500.00, 0.00, 59500.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(10, '5240', 3, '2024-11-19', '2024-11-26', 'received', 600000.00, 0.00, 600000.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(11, '5241', 4, '2025-01-17', '2025-01-24', 'received', 28000.00, 0.00, 28000.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(12, '5242', 1, '2025-01-18', '2025-01-25', 'received', 64796.00, 0.00, 64796.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(13, '5243', 1, '2025-01-21', '2025-01-28', 'received', 49980.00, 0.00, 49980.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(14, '5244', 1, '2025-01-22', '2025-01-29', 'received', 17850.00, 0.00, 17850.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(15, '5245', 5, '2025-01-24', '2025-01-31', 'received', 56000.00, 0.00, 56000.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(16, '5246', 6, '2025-01-24', '2025-01-31', 'received', 61000.00, 0.00, 61000.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(17, '5247', 1, '2025-01-24', '2025-01-31', 'received', 59500.00, 0.00, 59500.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(18, '5248', 2, '2025-01-24', '2025-01-31', 'received', 158500.00, 0.00, 158500.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(19, '5249', 2, '2025-01-27', '2025-02-03', 'received', 87000.00, 0.00, 87000.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(20, '5250', 1, '2025-01-29', '2025-02-05', 'received', 44625.00, 0.00, 44625.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18'),
(21, '5251', 2, '2025-01-31', '2025-02-07', 'received', 112500.00, 0.00, 112500.00, 'paid', NULL, 1, NULL, NULL, NULL, '2026-02-03 08:17:18', '2026-02-03 08:17:18');

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
  `is_vat_item` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `po_id`, `ingredient_id`, `mro_item_id`, `item_description`, `quantity`, `unit`, `unit_price`, `total_amount`, `quantity_received`, `is_vat_item`, `notes`, `created_at`) VALUES
(1, 1, NULL, NULL, 'BOTTLES 1000ml', 5950.00, 'PCS', 4.38, 26061.00, 5950.00, 0, NULL, '2026-02-03 08:17:18'),
(2, 1, NULL, NULL, 'CAPS', 5950.00, 'PCS', 0.62, 3689.00, 5950.00, 0, NULL, '2026-02-03 08:17:18'),
(3, 2, NULL, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3400.00, 102000.00, 30.00, 0, NULL, '2026-02-03 08:17:18'),
(4, 3, NULL, NULL, 'BOTTLES 1000ml', 11900.00, 'PCS', 4.38, 52122.00, 11900.00, 0, NULL, '2026-02-03 08:17:18'),
(5, 3, NULL, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 11900.00, 0, NULL, '2026-02-03 08:17:18'),
(6, 4, NULL, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2780.00, 83400.00, 30.00, 0, NULL, '2026-02-03 08:17:18'),
(7, 5, NULL, NULL, 'RIBBON ROLL', 20.00, 'ROLL', 680.00, 13600.00, 20.00, 0, NULL, '2026-02-03 08:17:18'),
(8, 5, NULL, NULL, 'PLUS VAT', 1.00, '-', 1632.00, 1632.00, 1.00, 1, NULL, '2026-02-03 08:17:18'),
(9, 6, NULL, NULL, 'BOTTLES 1000ml', 5950.00, 'PCS', 4.38, 26061.00, 5950.00, 0, NULL, '2026-02-03 08:17:18'),
(10, 6, NULL, NULL, 'CAPS', 5950.00, 'PCS', 0.62, 3689.00, 5950.00, 0, NULL, '2026-02-03 08:17:18'),
(11, 7, NULL, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3500.00, 105000.00, 30.00, 0, NULL, '2026-02-03 08:17:18'),
(12, 8, NULL, NULL, 'LINX SOLVENT', 6.00, 'BOTS', 2315.25, 13891.50, 6.00, 0, NULL, '2026-02-03 08:17:18'),
(13, 8, NULL, NULL, 'LINX INK', 5.00, 'BOTS', 5299.35, 26496.75, 5.00, 0, NULL, '2026-02-03 08:17:18'),
(14, 9, NULL, NULL, 'BOTTLES 1000ml', 11900.00, 'PCS', 4.38, 52122.00, 11900.00, 0, NULL, '2026-02-03 08:17:18'),
(15, 9, NULL, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 11900.00, 0, NULL, '2026-02-03 08:17:18'),
(16, 10, NULL, NULL, 'TT500 THERMA', 5.00, 'Unit', 120000.00, 600000.00, 5.00, 0, NULL, '2026-02-03 08:17:18'),
(17, 11, NULL, NULL, 'BROWN SUGAR', 10.00, 'SCKS', 2800.00, 28000.00, 10.00, 0, NULL, '2026-02-03 08:17:18'),
(18, 12, NULL, NULL, 'BOTTLES 1000ml', 5950.00, 'PCS', 4.38, 26061.00, 5950.00, 0, NULL, '2026-02-03 08:17:18'),
(19, 12, NULL, NULL, 'BOTTLES 500ml', 6570.00, 'PCS', 2.38, 15636.60, 6570.00, 0, NULL, '2026-02-03 08:17:18'),
(20, 12, NULL, NULL, 'BOTTLES 330ml', 5680.00, 'PCS', 2.08, 11814.40, 5680.00, 0, NULL, '2026-02-03 08:17:18'),
(21, 12, NULL, NULL, 'CAPS', 18200.00, 'PCS', 0.62, 11284.00, 18200.00, 0, NULL, '2026-02-03 08:17:18'),
(22, 13, NULL, NULL, 'BOTTLES 1000ml', 9996.00, 'PCS', 4.38, 43782.48, 9996.00, 0, NULL, '2026-02-03 08:17:18'),
(23, 13, NULL, NULL, 'CAPS', 9996.00, 'PCS', 0.62, 6197.52, 9996.00, 0, NULL, '2026-02-03 08:17:18'),
(24, 14, NULL, NULL, 'BOTTLES 1000ml', 3570.00, 'PCS', 4.38, 15636.60, 3570.00, 0, NULL, '2026-02-03 08:17:18'),
(25, 14, NULL, NULL, 'CAPS', 3570.00, 'PCS', 0.62, 2213.40, 3570.00, 0, NULL, '2026-02-03 08:17:18'),
(26, 15, NULL, NULL, 'CAUSTIC SODA', 20.00, 'SCKS', 2800.00, 56000.00, 20.00, 0, NULL, '2026-02-03 08:17:18'),
(27, 16, NULL, NULL, 'CHLORINIX', 10.00, 'BOXES', 800.00, 8000.00, 10.00, 0, NULL, '2026-02-03 08:17:18'),
(28, 16, NULL, NULL, 'LINOL-LIQUID D', 10.00, 'BOXES', 1400.00, 14000.00, 10.00, 0, NULL, '2026-02-03 08:17:18'),
(29, 16, NULL, NULL, 'ADVACIP 200', 10.00, 'CAR', 3900.00, 39000.00, 10.00, 0, NULL, '2026-02-03 08:17:18'),
(30, 17, NULL, NULL, 'BOTTLES 1000ml', 11900.00, 'PCS', 4.38, 52122.00, 11900.00, 0, NULL, '2026-02-03 08:17:18'),
(31, 17, NULL, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 11900.00, 0, NULL, '2026-02-03 08:17:18'),
(32, 18, NULL, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2850.00, 85500.00, 30.00, 0, NULL, '2026-02-03 08:17:18'),
(33, 18, NULL, NULL, 'WHITE SUGAR', 20.00, 'SCKS', 3650.00, 73000.00, 20.00, 0, NULL, '2026-02-03 08:17:18'),
(34, 19, NULL, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2900.00, 87000.00, 30.00, 0, NULL, '2026-02-03 08:17:18'),
(35, 20, NULL, NULL, 'BOTTLES 1000ml', 8925.00, 'PCS', 4.38, 39091.50, 8925.00, 0, NULL, '2026-02-03 08:17:18'),
(36, 20, NULL, NULL, 'CAPS', 8925.00, 'PCS', 0.62, 5533.50, 8925.00, 0, NULL, '2026-02-03 08:17:18'),
(37, 21, NULL, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3750.00, 112500.00, 30.00, 0, NULL, '2026-02-03 08:17:18');

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
(10, 'QCR-20260205-0015', 15, '2026-02-05 19:17:02', 'pass', 'pass', 'pass', 'pass', NULL, 'pass', 'pass', 'pass', 1, 0, 0, 0, NULL, 'approved', NULL, NULL, 5, NULL, NULL, NULL, '2026-02-05 11:17:02', '2026-02-05 11:17:02');

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
(21, 'QCT-000004', 22, '2026-02-05 18:57:34', 1, 3.75, 0.1600, NULL, 4.0, 1.0280, NULL, NULL, NULL, NULL, NULL, NULL, 'clean', 1, NULL, NULL, NULL, 'B', 30.00, 0.00, 0.00, 0.00, 0.00, 30.00, 1500.00, 1, NULL, 2, NULL, '', '2026-02-05 10:57:34', '2026-02-05 10:57:34');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `raw_milk_inventory`
--

INSERT INTO `raw_milk_inventory` (`id`, `batch_code`, `receiving_id`, `qc_test_id`, `milk_type_id`, `tank_id`, `volume_liters`, `remaining_liters`, `received_date`, `expiry_date`, `fat_percentage`, `grade`, `unit_cost`, `status`, `qc_status`, `received_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'RAW-20260203-599', 19, 18, 1, 10, 50.00, 0.00, '2026-02-03', '2026-02-05', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-03 14:00:16', '2026-02-05 06:29:48'),
(2, 'RAW-20260205-090', 20, 19, 1, 10, 50.00, 0.00, '2026-02-05', '2026-02-07', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-05 06:03:13', '2026-02-05 06:29:48'),
(3, 'RAW-20260205-100', 1, 1, 1, 10, 200.00, 0.00, '2026-02-05', '2026-02-08', NULL, NULL, NULL, 'depleted', 'approved', NULL, NULL, '2026-02-05 06:07:40', '2026-02-05 10:25:32'),
(4, 'RAW-20260205-045', 21, 20, 1, 10, 50.00, 0.00, '2026-02-05', '2026-02-07', 3.75, 'B', 30.00, 'depleted', 'approved', 2, NULL, '2026-02-05 10:56:14', '2026-02-05 10:59:03'),
(5, 'RAW-20260205-911', 22, 21, 1, 10, 50.00, 50.00, '2026-02-05', '2026-02-07', 3.75, 'B', 30.00, 'available', 'approved', 2, NULL, '2026-02-05 10:57:34', '2026-02-05 10:58:11');

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
(12, 17, 12, 'Stabilizer', 'other', 0.010, 'kg', 0, 'For consistency');

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
(12, 8, 'raw_milk', 0, NULL, 'Raw Milk', NULL, 50.00, 50.00, 'liters', 'fulfilled', NULL, 4, '2026-02-05 18:59:03', '', '2026-02-05 10:58:37', '2026-02-05 10:59:03');

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
  `status` enum('draft','pending','approved','preparing','dispatched','delivered','accepted','partially_accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
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
(2, 'SO-20260205-001', 5, NULL, '', 'other', NULL, 'cash', 0, NULL, NULL, 'Hotel 101 Tacloban', '2026-02-05', 0, 0, 1235.00, 0.00, 0.00, 0.00, 1235.00, 0.00, 0.00, NULL, 'unpaid', 'dispatched', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-05 08:59:12', '2026-02-05 11:24:26'),
(3, 'SO-20260205-002', 6, 5, '', 'other', NULL, 'cash', 0, NULL, NULL, 'DepEd Complex, Cagayan de Oro City', '2026-02-05', 0, 0, 2940.00, 0.00, 0.00, 0.00, 2940.00, 0.00, 0.00, NULL, 'unpaid', 'dispatched', 'normal', 6, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-02-05 11:18:00', '2026-02-05 11:24:26');

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
(2, 3, 7, 'Butter 250g', NULL, 250.00, 'g', 21, 1, 1, 'mixed', 0, 140.00, 2940.00, 'pending', NULL, '2026-02-05 11:18:00', '2026-02-05 11:18:00');

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
(6, 3, 'approved', NULL, 6, '2026-02-05 11:18:11');

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
(9, 'PT-001', 'Pasteurized Tank 1', NULL, 3000.00, 0.00, 'Processing Area', 'pasteurized', NULL, NULL, 'in_use', 1, NULL, '2026-02-03 14:43:08', '2026-02-05 06:29:48'),
(10, 'PRT-001', 'Processing Tank 1', NULL, 2000.00, 50.00, 'Processing Area', '', NULL, NULL, 'in_use', 1, NULL, '2026-02-03 14:43:08', '2026-02-05 10:59:03'),
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
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `first_name`, `last_name`, `employee_id`, `role`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'System', 'Admin', NULL, 'general_manager', 'admin@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03'),
(2, 'qc_officer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos', 'Maria', 'Santos', NULL, 'qc_officer', 'qc@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03'),
(3, 'production_staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', 'Juan', 'Dela Cruz', NULL, 'production_staff', 'production@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03'),
(4, 'warehouse_raw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos Mendoza', 'Carlos', 'Mendoza', NULL, 'warehouse_raw', 'warehouse.raw@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03'),
(5, 'warehouse_fg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Pedro Garcia', 'Pedro', 'Garcia', NULL, 'warehouse_fg', 'warehouse.fg@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03'),
(6, 'sales_custodian', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Miguel Torres', 'Miguel', 'Torres', NULL, 'sales_custodian', 'sales@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03'),
(7, 'cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Reyes', 'Ana', 'Reyes', NULL, 'cashier', 'cashier@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03'),
(8, 'general_manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'General Manager', 'General', 'Manager', NULL, 'general_manager', 'gm@highlandfresh.com', 1, '2026-02-03 07:57:05', '2026-02-03 08:00:03');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_table` (`table_name`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_id` (`inventory_id`),
  ADD KEY `idx_opened_at` (`opened_at`),
  ADD KEY `idx_opened_by` (`opened_by`);

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
  ADD KEY `fk_di_soi` (`sales_order_item_id`);

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
  ADD KEY `fk_po_approver` (`approved_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po` (`po_id`),
  ADD KEY `fk_poi_ingredient` (`ingredient_id`),
  ADD KEY `fk_poi_mro` (`mro_item_id`);

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
-- Indexes for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipe` (`recipe_id`),
  ADD KEY `fk_recipe_ing_ingredient` (`ingredient_id`);

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
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashier_shifts`
--
ALTER TABLE `cashier_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `delivery_receipt_items`
--
ALTER TABLE `delivery_receipt_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fg_inventory_transactions`
--
ALTER TABLE `fg_inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `fg_receiving`
--
ALTER TABLE `fg_receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finished_goods_inventory`
--
ALTER TABLE `finished_goods_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `ingredient_batches`
--
ALTER TABLE `ingredient_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredient_categories`
--
ALTER TABLE `ingredient_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ingredient_consumption`
--
ALTER TABLE `ingredient_consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `master_recipes`
--
ALTER TABLE `master_recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `material_requisitions`
--
ALTER TABLE `material_requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `milk_grading_standards`
--
ALTER TABLE `milk_grading_standards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `milk_receiving`
--
ALTER TABLE `milk_receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `mro_items`
--
ALTER TABLE `mro_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pasteurization_runs`
--
ALTER TABLE `pasteurization_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pasteurized_milk_inventory`
--
ALTER TABLE `pasteurized_milk_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_collections`
--
ALTER TABLE `payment_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- AUTO_INCREMENT for table `production_batches`
--
ALTER TABLE `production_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `production_byproducts`
--
ALTER TABLE `production_byproducts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `production_ccp_logs`
--
ALTER TABLE `production_ccp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `production_run_milk_usage`
--
ALTER TABLE `production_run_milk_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `product_prices`
--
ALTER TABLE `product_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `qc_batch_release`
--
ALTER TABLE `qc_batch_release`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `qc_milk_tests`
--
ALTER TABLE `qc_milk_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `qc_test_parameters`
--
ALTER TABLE `qc_test_parameters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `raw_milk_inventory`
--
ALTER TABLE `raw_milk_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `sales_customer_sub_accounts`
--
ALTER TABLE `sales_customer_sub_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales_order_status_history`
--
ALTER TABLE `sales_order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inv_trans_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_inv_trans_performed` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

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
-- Constraints for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD CONSTRAINT `fk_recipe_ing_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_recipe_ing_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `master_recipes` (`id`) ON DELETE CASCADE;

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
