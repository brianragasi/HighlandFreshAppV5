-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 18, 2026 at 03:08 PM
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
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', '2026-01-13 12:41:10'),
(2, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 12:43:59'),
(3, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 12:46:13'),
(4, 1, 'CREATE', 'milk_deliveries', 18, NULL, '{\"delivery_code\":\"DEL-000001\",\"farmer_id\":\"4\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 12:50:30'),
(5, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 12:52:05'),
(6, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 13:10:00'),
(7, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:12:49'),
(8, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 13:18:52'),
(9, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 13:25:11'),
(10, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:25:23'),
(11, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:25:29'),
(12, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:25:41'),
(13, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:25:52'),
(14, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:26:01'),
(15, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:28:43'),
(16, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:29:33'),
(17, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:31:46'),
(18, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:31:59'),
(19, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:35:45'),
(20, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:35:54'),
(21, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:39:03'),
(22, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:41:22'),
(23, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:42:01'),
(24, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-13 13:42:52'),
(25, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 13:56:59'),
(26, 1, 'CREATE', 'milk_deliveries', 19, NULL, '{\"delivery_code\":\"DEL-000002\",\"farmer_id\":\"5\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 13:57:20'),
(27, 1, 'CREATE', 'milk_deliveries', 20, NULL, '{\"delivery_code\":\"DEL-000003\",\"farmer_id\":\"4\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 14:02:42'),
(28, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 14:11:52'),
(29, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:08:21'),
(30, 1, 'CREATE', 'milk_deliveries', 21, NULL, '{\"delivery_code\":\"DEL-000004\",\"farmer_id\":\"5\",\"volume_liters\":\"500\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:17:52'),
(31, 1, 'CREATE', 'qc_milk_tests', 3, NULL, '{\"test_code\":\"QCT-000001\",\"delivery_id\":\"21\",\"is_accepted\":false,\"final_price_per_liter\":0,\"total_amount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:19:11'),
(32, 1, 'CREATE', 'qc_milk_tests', 4, NULL, '{\"test_code\":\"QCT-000002\",\"delivery_id\":\"20\",\"is_accepted\":true,\"final_price_per_liter\":30,\"total_amount\":1500}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:25:05'),
(33, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:25:24'),
(34, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:27:12'),
(35, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:32:06'),
(36, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 15:38:33'),
(37, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 16:02:08'),
(38, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 16:10:38'),
(39, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-13 16:18:00'),
(40, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:10:05'),
(41, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:27:11'),
(42, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:35:37'),
(43, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:36:07'),
(44, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:37:48'),
(45, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:52:55'),
(46, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:56:57'),
(47, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 09:57:09'),
(48, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 10:21:08'),
(49, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 10:21:29'),
(50, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 10:36:27'),
(51, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 11:29:43'),
(52, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 11:30:13'),
(53, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 11:31:13'),
(54, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 11:34:05'),
(55, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 11:37:21'),
(56, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 11:53:42'),
(57, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 12:56:23'),
(58, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 13:00:05'),
(59, 1, 'CREATE', 'milk_deliveries', 22, NULL, '{\"delivery_code\":\"DEL-000005\",\"farmer_id\":\"3\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 13:00:24'),
(60, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 13:01:23'),
(61, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 13:01:51'),
(62, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-17 13:04:12'),
(63, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:27:19'),
(64, 1, 'CREATE', 'milk_deliveries', 23, NULL, '{\"delivery_code\":\"DEL-000006\",\"farmer_id\":\"5\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:27:41'),
(65, 1, 'CREATE', 'qc_milk_tests', 5, NULL, '{\"test_code\":\"QCT-000003\",\"delivery_id\":\"23\",\"is_accepted\":false,\"final_price_per_liter\":0,\"total_amount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:28:25'),
(66, 1, 'CREATE', 'milk_deliveries', 24, NULL, '{\"delivery_code\":\"DEL-000007\",\"farmer_id\":\"5\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:28:47'),
(67, 1, 'CREATE', 'qc_milk_tests', 6, NULL, '{\"test_code\":\"QCT-000004\",\"delivery_id\":\"24\",\"is_accepted\":false,\"final_price_per_liter\":0,\"total_amount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:29:37'),
(68, 1, 'CREATE', 'milk_deliveries', 25, NULL, '{\"delivery_code\":\"DEL-000008\",\"farmer_id\":\"8\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:29:50'),
(69, 1, 'CREATE', 'qc_milk_tests', 7, NULL, '{\"test_code\":\"QCT-000005\",\"delivery_id\":\"25\",\"is_accepted\":false,\"final_price_per_liter\":0,\"total_amount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:30:14'),
(70, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:33:01'),
(71, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:33:23'),
(72, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:39:49'),
(73, 1, 'CREATE', 'milk_deliveries', 26, NULL, '{\"delivery_code\":\"DEL-000009\",\"farmer_id\":\"6\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:47:06'),
(74, 1, 'CREATE', 'qc_milk_tests', 8, NULL, '{\"test_code\":\"QCT-000006\",\"delivery_id\":\"26\",\"is_accepted\":false,\"final_price_per_liter\":0,\"total_amount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:47:25'),
(75, 1, 'CREATE', 'milk_deliveries', 27, NULL, '{\"delivery_code\":\"DEL-000010\",\"farmer_id\":\"7\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:47:44'),
(76, 1, 'CREATE', 'milk_deliveries', 28, NULL, '{\"delivery_code\":\"DEL-000011\",\"farmer_id\":\"5\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:50:10'),
(77, 1, 'CREATE', 'qc_milk_tests', 11, NULL, '{\"test_code\":\"QCT-000007\",\"delivery_id\":\"28\",\"is_accepted\":true,\"final_price_per_liter\":29.5,\"total_amount\":1475}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:50:25'),
(78, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 07:51:19'),
(79, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:07:49'),
(80, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:08:19'),
(81, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:12:31'),
(82, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:17:57'),
(83, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:19:02'),
(84, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:21:43'),
(85, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:25:16'),
(86, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:25:24'),
(87, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:31:04'),
(88, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:31:12'),
(89, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:48:52'),
(90, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 08:57:18'),
(91, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 09:10:06'),
(92, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 09:33:44'),
(93, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 09:36:44'),
(94, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 09:40:19'),
(95, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 10:05:19'),
(96, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 10:08:58'),
(97, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 10:11:48'),
(98, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 10:51:05'),
(99, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:00:09'),
(100, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:00:42'),
(101, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:01:40'),
(102, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:01:56'),
(103, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:03:34'),
(104, 1, 'CREATE', 'milk_deliveries', 32, NULL, '{\"delivery_code\":\"DEL-000013\",\"farmer_id\":\"2\",\"volume_liters\":\"50\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:04:01'),
(105, 1, 'CREATE', 'qc_milk_tests', 31, NULL, '{\"test_code\":\"QCT-000025\",\"delivery_id\":\"32\",\"is_accepted\":true,\"final_price_per_liter\":29.5,\"total_amount\":1475}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:04:22'),
(106, 1, 'CREATE', 'farmers', 18, NULL, '{\"farmer_code\":\"FRM-018\",\"name\":\"Yawards HUHU\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:04:45'),
(107, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:18:47'),
(108, 1, 'LOGIN', 'users', 1, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:21:22'),
(109, 7, 'LOGIN', 'users', 7, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:21:34'),
(110, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 11:55:20'),
(111, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 12:26:22'),
(112, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 12:27:24'),
(113, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 12:29:05'),
(114, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 12:29:33'),
(115, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-18 13:10:20'),
(116, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', NULL, '2026-01-18 13:11:56'),
(117, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', NULL, '2026-01-18 13:14:19'),
(118, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', NULL, '2026-01-18 13:21:21'),
(119, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', NULL, '2026-01-18 13:25:25'),
(120, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-18 13:45:38'),
(121, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', '2026-01-18 13:46:28'),
(122, 4, 'LOGIN', 'users', 4, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-18 13:50:38'),
(123, 8, 'LOGIN', 'users', 8, NULL, '{\"ip\":\"::1\"}', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.26100.7462', '2026-01-18 13:51:10');

-- --------------------------------------------------------

--
-- Table structure for table `batch_ccp_logs`
--

CREATE TABLE `batch_ccp_logs` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `ccp_type` enum('pasteurization','cooling','storage','packaging') NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `target_temp` decimal(5,2) DEFAULT NULL,
  `target_duration` int(11) DEFAULT NULL,
  `is_compliant` tinyint(1) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batch_releases`
--

CREATE TABLE `batch_releases` (
  `id` int(11) NOT NULL,
  `release_code` varchar(30) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `inspection_date` datetime NOT NULL,
  `appearance_check` tinyint(1) DEFAULT 0,
  `odor_check` tinyint(1) DEFAULT 0,
  `taste_check` tinyint(1) DEFAULT 0,
  `packaging_check` tinyint(1) DEFAULT 0,
  `label_check` tinyint(1) DEFAULT 0,
  `temperature_check` tinyint(1) DEFAULT 0,
  `sample_retained` tinyint(1) DEFAULT 0,
  `overall_status` enum('pending','approved','rejected','on_hold') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `inspected_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approval_datetime` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `box_opening_log`
--

CREATE TABLE `box_opening_log` (
  `id` int(11) NOT NULL,
  `opening_code` varchar(30) NOT NULL COMMENT 'e.g., BOX-20260117-001',
  `inventory_id` int(11) NOT NULL COMMENT 'Links to finished_goods_inventory',
  `product_id` int(11) NOT NULL,
  `boxes_opened` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of boxes opened',
  `pieces_from_opening` int(11) NOT NULL COMMENT 'Pieces added to inventory from opened boxes',
  `reason` enum('partial_sale','sampling','quality_check','damage','other') NOT NULL DEFAULT 'partial_sale',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g., delivery_receipt, walk_in_sale',
  `reference_id` int(11) DEFAULT NULL COMMENT 'Links to DR or sale record',
  `opened_by` int(11) NOT NULL COMMENT 'Staff who opened the box',
  `opened_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ccp_logs`
--

CREATE TABLE `ccp_logs` (
  `id` int(11) NOT NULL,
  `log_code` varchar(30) NOT NULL,
  `ccp_point` varchar(50) NOT NULL COMMENT 'e.g., pasteurization, cooling, storage',
  `batch_id` int(11) DEFAULT NULL,
  `check_datetime` datetime NOT NULL,
  `temperature_reading` decimal(5,1) DEFAULT NULL,
  `temperature_min` decimal(5,1) DEFAULT NULL,
  `temperature_max` decimal(5,1) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `is_within_limits` tinyint(1) DEFAULT 1,
  `corrective_action` text DEFAULT NULL,
  `verified_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chiller_locations`
--

CREATE TABLE `chiller_locations` (
  `id` int(11) NOT NULL,
  `chiller_code` varchar(20) NOT NULL COMMENT 'e.g., CHILLER-01, CHILLER-02',
  `chiller_name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL COMMENT 'Maximum units capacity',
  `current_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Current inventory count',
  `temperature_celsius` decimal(4,1) DEFAULT NULL COMMENT 'Current temperature reading',
  `min_temperature` decimal(4,1) DEFAULT 2.0 COMMENT 'Minimum safe temp',
  `max_temperature` decimal(4,1) DEFAULT 8.0 COMMENT 'Maximum safe temp',
  `location` varchar(100) DEFAULT NULL COMMENT 'Physical location in facility',
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
(1, 'CHILLER-01', 'Main Chiller A', 500, 0, 4.0, 2.0, 8.0, 'Cold Storage Room A', 'available', 1, NULL, '2026-01-18 12:15:27', '2026-01-18 12:15:27'),
(2, 'CHILLER-02', 'Main Chiller B', 500, 0, 4.5, 2.0, 8.0, 'Cold Storage Room A', 'available', 1, NULL, '2026-01-18 12:15:27', '2026-01-18 12:15:27'),
(3, 'CHILLER-03', 'Dispatch Chiller', 200, 0, 3.5, 2.0, 8.0, 'Loading Area', 'available', 1, NULL, '2026-01-18 12:15:27', '2026-01-18 12:15:27'),
(4, 'CHILLER-04', 'Reserve Chiller', 1000, 563, 4.0, 2.0, 8.0, 'Cold Storage Room B', 'available', 1, NULL, '2026-01-18 12:15:27', '2026-01-18 12:16:23');

-- --------------------------------------------------------

--
-- Table structure for table `chiller_temperature_logs`
--

CREATE TABLE `chiller_temperature_logs` (
  `id` int(11) NOT NULL,
  `chiller_id` int(11) NOT NULL,
  `temperature_celsius` decimal(4,1) NOT NULL,
  `is_alert` tinyint(1) DEFAULT 0 COMMENT 'True if temp out of range',
  `logged_by` int(11) NOT NULL,
  `logged_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_type` enum('walk_in','institutional','supermarket','feeding_program') NOT NULL,
  `name` varchar(200) NOT NULL,
  `sub_location` varchar(200) DEFAULT NULL COMMENT 'e.g., specific school for feeding program',
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `current_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Outstanding receivables',
  `payment_terms` int(11) DEFAULT 0 COMMENT 'Days - 0 for cash, 30/60/90 for credit',
  `status` enum('active','inactive','blocked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_type`, `name`, `sub_location`, `contact_person`, `contact_number`, `email`, `address`, `credit_limit`, `current_balance`, `payment_terms`, `status`, `created_at`, `notes`) VALUES
(1, 'supermarket', 'SM Supermarket CDO', 'SM City Cagayan de Oro', 'Purchasing Dept', '088-123-4567', NULL, NULL, 500000.00, 0.00, 30, 'active', '2026-01-13 11:44:18', NULL),
(2, 'supermarket', 'Gaisano Mall CDO', 'Gaisano City CDO', 'Purchasing Dept', '088-234-5678', NULL, NULL, 300000.00, 0.00, 30, 'active', '2026-01-13 11:44:18', NULL),
(3, 'feeding_program', 'DepEd Division Office', 'Cagayan de Oro Division', 'School Feeding Coordinator', '088-345-6789', NULL, NULL, 1000000.00, 0.00, 60, 'active', '2026-01-13 11:44:18', NULL),
(4, 'feeding_program', 'DepEd - Central School', 'Central Elementary School', 'Principal Office', '088-456-7890', NULL, NULL, 100000.00, 0.00, 60, 'active', '2026-01-13 11:44:18', NULL),
(5, 'institutional', 'Highland Fresh Canteen', NULL, 'Canteen Manager', NULL, NULL, NULL, 50000.00, 0.00, 0, 'active', '2026-01-13 11:44:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_receipts`
--

CREATE TABLE `delivery_receipts` (
  `id` int(11) NOT NULL,
  `dr_number` varchar(30) NOT NULL,
  `customer_type` enum('walk_in','institutional','supermarket','feeding_program') NOT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `sub_location` varchar(200) DEFAULT NULL COMMENT 'e.g., specific school for feeding program',
  `contact_number` varchar(20) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','preparing','dispatched','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `dispatched_by` int(11) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_receipts`
--

INSERT INTO `delivery_receipts` (`id`, `dr_number`, `customer_type`, `customer_name`, `sub_location`, `contact_number`, `delivery_address`, `total_items`, `total_amount`, `payment_status`, `amount_paid`, `status`, `created_at`, `created_by`, `dispatched_at`, `dispatched_by`, `delivered_at`, `notes`) VALUES
(1, 'DR-20260113-4407', 'institutional', 'SM Supermarket', '', NULL, '', 0, 1500.00, 'unpaid', 0.00, 'dispatched', '2026-01-13 11:37:49', 1, '2026-01-13 19:37:50', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_receipt_items`
--

CREATE TABLE `delivery_receipt_items` (
  `id` int(11) NOT NULL,
  `dr_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL COMMENT 'Links to finished_goods_inventory',
  `product_name` varchar(150) NOT NULL,
  `variant` varchar(100) DEFAULT NULL,
  `size_value` decimal(10,2) NOT NULL,
  `size_unit` varchar(10) NOT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_boxes` int(11) NOT NULL DEFAULT 0 COMMENT 'Boxes ordered in this line',
  `quantity_pieces` int(11) NOT NULL DEFAULT 0 COMMENT 'Loose pieces ordered in this line',
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `barcode_scanned` varchar(50) DEFAULT NULL COMMENT 'Barcode validated on release',
  `manufacturing_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('pending','picked','released','cancelled') NOT NULL DEFAULT 'pending',
  `picked_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_items`
--

CREATE TABLE `dispatch_items` (
  `id` int(11) NOT NULL,
  `dr_id` int(11) NOT NULL,
  `fg_inventory_id` int(11) NOT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(12,2) DEFAULT NULL,
  `barcode_scanned` varchar(50) DEFAULT NULL COMMENT 'Barcode scanned during dispatch',
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dispatch_items`
--

INSERT INTO `dispatch_items` (`id`, `dr_id`, `fg_inventory_id`, `product_name`, `variant`, `quantity`, `unit`, `unit_price`, `total_price`, `barcode_scanned`, `scanned_at`) VALUES
(1, 1, 1, 'Highland Yogurt', 'Plain', 50, 'cups', 30.00, 1500.00, 'HF-BTH-20260113-8088-TEST', '2026-01-13 11:37:49');

-- --------------------------------------------------------

--
-- Table structure for table `employee_credits`
--

CREATE TABLE `employee_credits` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `status` enum('pending','deducted','written_off') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deducted_at` date DEFAULT NULL
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

INSERT INTO `farmers` (`id`, `farmer_code`, `first_name`, `last_name`, `contact_number`, `address`, `membership_type`, `base_price_per_liter`, `bank_name`, `bank_account_number`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'FRM-001', 'Lacandula', '', NULL, NULL, 'member', 41.25, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(2, 'FRM-002', 'Galla', '', NULL, NULL, 'member', 42.25, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(3, 'FRM-003', 'DMDC', '', NULL, NULL, 'member', 40.00, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(4, 'FRM-004', 'Dumindin', '', NULL, NULL, 'member', 41.50, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(5, 'FRM-005', 'Paraguya', '', NULL, NULL, 'member', 42.50, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(6, 'FRM-006', 'MMDC', '', NULL, NULL, 'member', 40.00, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(7, 'FRM-007', 'Bernales', '', NULL, NULL, 'member', 40.00, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(8, 'FRM-008', 'Tagadan', '', NULL, NULL, 'member', 39.25, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(9, 'FRM-009', 'Abonitalla', '', NULL, NULL, 'member', 41.25, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(10, 'FRM-010', 'C1/Dumaluan', '', NULL, NULL, 'member', 40.00, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(11, 'FRM-011', 'C1/Dumaluan Goat', '', NULL, NULL, 'member', 70.00, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(12, 'FRM-012', 'C3/Valledor', '', NULL, NULL, 'member', 41.25, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(13, 'FRM-013', 'Jardin', '', NULL, NULL, 'member', 40.75, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(14, 'FRM-014', 'Malig', '', NULL, NULL, 'member', 40.50, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(15, 'FRM-015', 'Abriol', '', NULL, NULL, 'member', 40.25, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(16, 'FRM-016', 'Gargar', '', NULL, NULL, 'member', 69.25, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(17, 'FRM-017', 'Navarro', '', NULL, NULL, 'member', 40.75, NULL, NULL, 1, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(18, 'FRM-018', 'Yawards', 'HUHU', '0917', 'Bastta', 'member', 40.00, 'BDO', 'BPI', 1, '2026-01-18 11:04:45', '2026-01-18 11:04:45');

-- --------------------------------------------------------

--
-- Table structure for table `fg_dispatch_log`
--

CREATE TABLE `fg_dispatch_log` (
  `id` int(11) NOT NULL,
  `dispatch_code` varchar(30) NOT NULL COMMENT 'e.g., DSP-20260117-001',
  `dr_id` int(11) NOT NULL,
  `dr_item_id` int(11) DEFAULT NULL COMMENT 'Specific DR line item',
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_released` int(11) NOT NULL,
  `barcode_scanned` varchar(50) DEFAULT NULL,
  `chiller_id` int(11) DEFAULT NULL COMMENT 'Chiller from which released',
  `released_by` int(11) NOT NULL,
  `released_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `boxes_released` int(11) NOT NULL DEFAULT 0 COMMENT 'Boxes released in this dispatch',
  `pieces_released` int(11) NOT NULL DEFAULT 0 COMMENT 'Loose pieces released in this dispatch'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fg_inventory_transactions`
--

CREATE TABLE `fg_inventory_transactions` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(30) NOT NULL,
  `transaction_type` enum('receive','release','transfer','adjust','dispose','return') NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_before` int(11) NOT NULL,
  `quantity_after` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g., fg_receiving, delivery_receipt',
  `reference_id` int(11) DEFAULT NULL,
  `from_chiller_id` int(11) DEFAULT NULL,
  `to_chiller_id` int(11) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `boxes_quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Boxes involved in transaction',
  `pieces_quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Loose pieces involved in transaction',
  `boxes_before` int(11) NOT NULL DEFAULT 0 COMMENT 'Boxes count before transaction',
  `pieces_before` int(11) NOT NULL DEFAULT 0 COMMENT 'Pieces count before transaction',
  `boxes_after` int(11) NOT NULL DEFAULT 0 COMMENT 'Boxes count after transaction',
  `pieces_after` int(11) NOT NULL DEFAULT 0 COMMENT 'Pieces count after transaction'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fg_receiving`
--

CREATE TABLE `fg_receiving` (
  `id` int(11) NOT NULL,
  `receiving_code` varchar(30) NOT NULL COMMENT 'e.g., FGR-20260117-001',
  `batch_id` int(11) NOT NULL COMMENT 'Production batch received',
  `product_id` int(11) NOT NULL,
  `quantity_received` int(11) NOT NULL COMMENT 'Number of units received',
  `chiller_id` int(11) NOT NULL COMMENT 'Chiller where stored',
  `received_by` int(11) NOT NULL COMMENT 'Warehouse FG staff',
  `received_at` datetime NOT NULL,
  `barcode` varchar(50) DEFAULT NULL COMMENT 'Batch barcode scanned',
  `manufacturing_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('received','verified','rejected') NOT NULL DEFAULT 'received',
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `boxes_received` int(11) NOT NULL DEFAULT 0 COMMENT 'Boxes received from production',
  `pieces_received` int(11) NOT NULL DEFAULT 0 COMMENT 'Loose pieces received (if any)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finished_goods_inventory`
--

CREATE TABLE `finished_goods_inventory` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar') NOT NULL,
  `product_variant` varchar(100) DEFAULT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `size_ml` int(11) DEFAULT NULL COMMENT 'For bottled products',
  `quantity` int(11) NOT NULL COMMENT 'Original quantity received',
  `remaining_quantity` int(11) NOT NULL COMMENT 'Current available quantity',
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `unit_price` decimal(10,2) DEFAULT NULL COMMENT 'Plant price per unit',
  `manufacturing_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `chiller_location` varchar(50) DEFAULT NULL COMMENT 'Physical storage location',
  `chiller_id` int(11) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_movement_at` datetime DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `status` enum('available','low_stock','reserved','dispatched','expired') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `quantity_available` int(11) NOT NULL DEFAULT 0,
  `quantity_boxes` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of full/sealed boxes',
  `quantity_pieces` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of loose pieces from opened boxes',
  `boxes_available` int(11) NOT NULL DEFAULT 0 COMMENT 'Boxes available (not reserved)',
  `pieces_available` int(11) NOT NULL DEFAULT 0 COMMENT 'Pieces available (not reserved)',
  `reserved_quantity` int(11) NOT NULL DEFAULT 0,
  `quantity_sold` int(11) DEFAULT 0,
  `quantity_damaged` int(11) DEFAULT 0,
  `quantity_expired` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `finished_goods_inventory`
--

INSERT INTO `finished_goods_inventory` (`id`, `batch_id`, `product_id`, `product_name`, `product_type`, `product_variant`, `variant`, `size_ml`, `quantity`, `remaining_quantity`, `unit`, `unit_price`, `manufacturing_date`, `expiry_date`, `barcode`, `chiller_location`, `chiller_id`, `received_at`, `last_movement_at`, `received_by`, `status`, `notes`, `quantity_available`, `quantity_boxes`, `quantity_pieces`, `boxes_available`, `pieces_available`, `reserved_quantity`, `quantity_sold`, `quantity_damaged`, `quantity_expired`) VALUES
(1, 4, 6, 'Highland Yogurt', 'yogurt', NULL, 'Plain', NULL, 150, 99, 'cups', 30.00, '2026-01-13', '2026-01-27', 'HF-BTH-20260113-8088-TEST', 'CHILLER-04', 4, '2026-01-13 11:28:32', NULL, 1, 'available', NULL, 150, 12, 6, 12, 6, 0, 0, 0, 0),
(2, 4, 9, 'Highland Milk Bar', 'milk_bar', 'Original', NULL, NULL, 150, 150, 'bars', NULL, '2026-01-17', '2026-02-16', 'HF-MB-001', 'CHILLER-04', 4, '2026-01-17 11:51:47', NULL, NULL, 'available', NULL, 150, 3, 0, 3, 0, 0, 0, 0, 0),
(3, 4, 10, 'Highland Chocolate Milk', 'bottled_milk', 'Chocolate 200ml', NULL, 200, 96, 96, 'bottles', NULL, '2026-01-17', '2026-01-31', 'HF-CM-001', 'CHILLER-04', 4, '2026-01-17 11:51:47', NULL, NULL, 'available', NULL, 72, 4, 0, 3, 0, 0, 0, 0, 0),
(4, 4, 11, 'Highland Butter', 'butter', 'Salted 225g', NULL, NULL, 40, 40, 'packs', NULL, '2026-01-17', '2026-03-18', 'HF-BT-001', 'CHILLER-04', 4, '2026-01-17 11:51:47', NULL, NULL, 'available', NULL, 35, 2, 0, 1, 15, 0, 0, 0, 0),
(5, 4, 12, 'Highland Fresh Milk', 'bottled_milk', 'Plain 1L', NULL, 1000, 120, 120, 'bottles', NULL, '2026-01-17', '2026-01-24', 'HF-FM-001', 'CHILLER-04', 4, '2026-01-17 11:51:47', NULL, NULL, 'available', NULL, 108, 5, 0, 4, 12, 0, 0, 0, 0),
(6, 5, 1, '', '', NULL, NULL, NULL, 48, 0, 'pcs', NULL, '2026-01-18', '2026-02-17', NULL, 'CHILLER-04', 4, '2026-01-18 10:54:50', NULL, NULL, 'available', NULL, 48, 0, 0, 0, 0, 0, 0, 0, 0);

--
-- Triggers `finished_goods_inventory`
--
DELIMITER $$
CREATE TRIGGER `trg_fg_inventory_before_update` BEFORE UPDATE ON `finished_goods_inventory` FOR EACH ROW BEGIN
    DECLARE v_pieces_per_box INT DEFAULT 1;
    
    
    SET v_pieces_per_box = CASE 
        WHEN NEW.product_type = 'milk_bar' THEN 50
        WHEN NEW.product_type = 'bottled_milk' THEN 24
        WHEN NEW.product_type = 'yogurt' THEN 12
        WHEN NEW.product_type = 'butter' THEN 20
        WHEN NEW.product_type = 'cheese' THEN 10
        ELSE 1
    END;
    
    
    IF NEW.quantity_boxes != OLD.quantity_boxes OR NEW.quantity_pieces != OLD.quantity_pieces THEN
        SET NEW.quantity = (NEW.quantity_boxes * v_pieces_per_box) + NEW.quantity_pieces;
    END IF;
    
    
    IF NEW.boxes_available != OLD.boxes_available OR NEW.pieces_available != OLD.pieces_available THEN
        SET NEW.quantity_available = (NEW.boxes_available * v_pieces_per_box) + NEW.pieces_available;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `grading_standards`
--

CREATE TABLE `grading_standards` (
  `id` int(11) NOT NULL,
  `parameter_name` varchar(50) NOT NULL,
  `min_value` decimal(10,2) DEFAULT NULL,
  `max_value` decimal(10,2) DEFAULT NULL,
  `incentive_per_liter` decimal(10,2) DEFAULT 0.00,
  `deduction_per_liter` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grading_standards`
--

INSERT INTO `grading_standards` (`id`, `parameter_name`, `min_value`, `max_value`, `incentive_per_liter`, `deduction_per_liter`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'fat_percent_high', 4.00, 5.00, 2.00, 0.00, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49'),
(2, 'fat_percent_standard', 3.50, 3.99, 0.00, 0.00, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49'),
(3, 'fat_percent_low', 3.00, 3.49, 0.00, 1.00, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49'),
(4, 'acidity_normal', 6.60, 6.80, 0.00, 0.00, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49'),
(5, 'acidity_acceptable', 6.40, 6.59, 0.00, 0.50, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49'),
(6, 'temperature_cold', 0.00, 4.00, 0.50, 0.00, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49'),
(7, 'temperature_cool', 4.01, 8.00, 0.00, 0.00, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49'),
(8, 'temperature_warm', 8.01, 15.00, 0.00, 1.00, 1, '2026-01-13 10:08:49', '2026-01-13 10:08:49');

-- --------------------------------------------------------

--
-- Table structure for table `ingredients`
--

CREATE TABLE `ingredients` (
  `id` int(11) NOT NULL,
  `ingredient_code` varchar(30) NOT NULL,
  `ingredient_name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_of_measure` varchar(20) NOT NULL COMMENT 'kg, L, pcs, g, ml',
  `minimum_stock` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Reorder point',
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(10,2) DEFAULT NULL COMMENT 'Average unit cost',
  `storage_location` varchar(100) DEFAULT NULL,
  `storage_requirements` text DEFAULT NULL COMMENT 'e.g., Keep refrigerated',
  `shelf_life_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `ingredient_code`, `ingredient_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `current_stock`, `unit_cost`, `storage_location`, `storage_requirements`, `shelf_life_days`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ING-001', 'White Sugar', 2, 'kg', 50.00, 68.00, NULL, NULL, 'Store in dry, cool place', 365, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(2, 'ING-002', 'Milk Powder', 1, 'kg', 25.00, 85.00, NULL, NULL, 'Store in dry, cool place. Keep sealed.', 180, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(3, 'ING-003', 'Chocolate Flavoring', 3, 'L', 10.00, 192.00, NULL, NULL, 'Store at room temperature', 365, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(4, 'ING-004', 'Melon Flavoring', 3, 'L', 10.00, 82.00, NULL, NULL, 'Store at room temperature', 365, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(5, 'ING-005', 'Rennet (Liquid)', 4, 'L', 5.00, 184.00, NULL, NULL, 'Refrigerate at 4??C', 90, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(6, 'ING-006', 'Salt (Iodized)', 4, 'kg', 20.00, 90.00, NULL, NULL, 'Store in dry place', 730, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(7, 'ING-007', 'Yogurt Culture Starter', 1, 'g', 500.00, 190.00, NULL, NULL, 'Keep frozen at -18??C', 365, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(8, 'ING-008', '330ml Bottles (PET)', 5, 'pcs', 1000.00, 53.00, NULL, NULL, 'Store in clean, dry area', NULL, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(9, 'ING-009', 'Bottle Caps (Blue)', 5, 'pcs', 1000.00, 0.00, NULL, NULL, 'Store in clean, dry area', NULL, 1, '2026-01-17 09:26:04', '2026-01-17 09:26:04'),
(10, 'ING-010', 'Product Labels (Milk)', 5, 'pcs', 1000.00, 0.00, NULL, NULL, 'Store away from heat', NULL, 1, '2026-01-17 09:26:04', '2026-01-17 09:26:04');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_batches`
--

CREATE TABLE `ingredient_batches` (
  `id` int(11) NOT NULL,
  `batch_code` varchar(30) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `remaining_quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `supplier_batch_no` varchar(50) DEFAULT NULL COMMENT 'Supplier batch/lot number',
  `received_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `status` enum('available','partially_used','consumed','expired','returned') NOT NULL DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ingredient_batches`
--

INSERT INTO `ingredient_batches` (`id`, `batch_code`, `ingredient_id`, `quantity`, `remaining_quantity`, `unit_cost`, `supplier_name`, `supplier_batch_no`, `received_date`, `expiry_date`, `received_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'IB-20260118-2076', 1, 68.00, 68.00, 90.00, 'Sample Supplier', 'SUP-1339', '2026-01-18', '2026-02-23', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(2, 'IB-20260118-9571', 2, 85.00, 85.00, 10.00, 'Sample Supplier', 'SUP-7653', '2026-01-18', '2026-04-15', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(3, 'IB-20260118-9513', 3, 192.00, 192.00, 82.00, 'Sample Supplier', 'SUP-9298', '2026-01-18', '2026-05-31', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(4, 'IB-20260118-1215', 4, 82.00, 82.00, 58.00, 'Sample Supplier', 'SUP-7271', '2026-01-18', '2026-03-09', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(5, 'IB-20260118-0387', 5, 184.00, 184.00, 69.00, 'Sample Supplier', 'SUP-1096', '2026-01-18', '2026-04-25', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(6, 'IB-20260118-3694', 6, 90.00, 90.00, 48.00, 'Sample Supplier', 'SUP-8509', '2026-01-18', '2026-03-02', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(7, 'IB-20260118-2382', 7, 190.00, 190.00, 35.00, 'Sample Supplier', 'SUP-9861', '2026-01-18', '2026-06-27', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(8, 'IB-20260118-7087', 8, 53.00, 53.00, 85.00, 'Sample Supplier', 'SUP-6197', '2026-01-18', '2026-04-09', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09');

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
(1, 'CAT-DAIRY', 'Dairy Additives', 'Milk powder, cream, cultures', 1, '2026-01-17 09:26:04'),
(2, 'CAT-SWEET', 'Sweeteners', 'Sugar, honey, syrups', 1, '2026-01-17 09:26:04'),
(3, 'CAT-FLAVOR', 'Flavorings', 'Chocolate, melon, vanilla extracts', 1, '2026-01-17 09:26:04'),
(4, 'CAT-ADDITIVE', 'Processing Additives', 'Rennet, salt, stabilizers', 1, '2026-01-17 09:26:04'),
(5, 'CAT-PACKAGE', 'Packaging Materials', 'Bottles, caps, labels, cartons', 1, '2026-01-17 09:26:04');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_consumption`
--

CREATE TABLE `ingredient_consumption` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `requisition_item_id` int(11) DEFAULT NULL,
  `ingredient_name` varchar(100) NOT NULL,
  `recipe_quantity` decimal(10,3) NOT NULL,
  `actual_quantity` decimal(10,3) NOT NULL,
  `variance` decimal(10,3) DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `variance_reason` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_requisitions`
--

CREATE TABLE `ingredient_requisitions` (
  `id` int(11) NOT NULL,
  `requisition_code` varchar(30) NOT NULL,
  `production_run_id` int(11) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `department` enum('production','maintenance','other') NOT NULL DEFAULT 'production',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `needed_by_date` date DEFAULT NULL,
  `needed_by` datetime DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `status` enum('draft','pending','approved','rejected','fulfilled','cancelled') DEFAULT 'draft',
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
-- Dumping data for table `ingredient_requisitions`
--

INSERT INTO `ingredient_requisitions` (`id`, `requisition_code`, `production_run_id`, `requested_by`, `department`, `priority`, `needed_by_date`, `needed_by`, `purpose`, `total_items`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `fulfilled_by`, `fulfilled_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'REQ-20260113-001', NULL, 3, 'production', 'normal', NULL, '2026-01-14 21:24:39', 'Production batch materials', 3, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-13 13:24:39', '2026-01-13 13:24:39'),
(5, 'REQ-20260118-001', 11, 7, 'production', 'urgent', NULL, '2026-01-21 11:30:00', 'i love you', 1, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-18 11:27:00', '2026-01-18 11:27:00'),
(6, 'REQ-20260118-002', NULL, 7, 'production', 'normal', NULL, NULL, '', 1, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-18 11:53:07', '2026-01-18 11:53:07'),
(7, 'REQ-20260118-003', 8, 7, 'production', 'high', NULL, '2026-01-29 23:56:00', 'BASTA', 1, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-18 11:53:54', '2026-01-18 11:53:54');

-- --------------------------------------------------------

--
-- Table structure for table `ingredient_requisition_items`
--

CREATE TABLE `ingredient_requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `ingredient_name` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(30) NOT NULL,
  `transaction_type` enum('receive','issue','adjust','transfer','return','dispose') NOT NULL,
  `item_type` enum('raw_milk','ingredient','mro') NOT NULL,
  `item_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL COMMENT 'Specific batch affected',
  `quantity` decimal(10,2) NOT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'e.g., requisition, purchase_order, adjustment',
  `reference_id` int(11) DEFAULT NULL,
  `from_location` varchar(100) DEFAULT NULL,
  `to_location` varchar(100) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `transaction_code`, `transaction_type`, `item_type`, `item_id`, `batch_id`, `quantity`, `unit_of_measure`, `reference_type`, `reference_id`, `from_location`, `to_location`, `performed_by`, `reason`, `created_at`) VALUES
(1, 'TX-20260118-0612', 'receive', 'ingredient', 1, NULL, 100.00, 'kg', 'purchase', NULL, NULL, 'Warehouse', 2, 'Initial stock from supplier', '2026-01-18 12:46:09'),
(2, 'TX-20260118-1528', 'receive', 'ingredient', 2, NULL, 100.00, 'kg', 'purchase', NULL, NULL, 'Warehouse', 2, 'Initial stock from supplier', '2026-01-18 12:46:09'),
(3, 'TX-20260118-6570', 'receive', 'ingredient', 3, NULL, 100.00, 'L', 'purchase', NULL, NULL, 'Warehouse', 2, 'Initial stock from supplier', '2026-01-18 12:46:09'),
(4, 'TX-20260118-0075', 'receive', 'ingredient', 4, NULL, 100.00, 'L', 'purchase', NULL, NULL, 'Warehouse', 2, 'Initial stock from supplier', '2026-01-18 12:46:09'),
(5, 'TX-20260118-8571', 'receive', 'ingredient', 5, NULL, 100.00, 'L', 'purchase', NULL, NULL, 'Warehouse', 2, 'Initial stock from supplier', '2026-01-18 12:46:09'),
(6, 'TX-20260118-7101', 'receive', 'mro', 1, NULL, 20.00, 'set', 'purchase', NULL, NULL, 'MRO Storage', 2, 'Initial MRO stock', '2026-01-18 12:46:09'),
(7, 'TX-20260118-6213', 'receive', 'mro', 2, NULL, 20.00, 'pcs', 'purchase', NULL, NULL, 'MRO Storage', 2, 'Initial MRO stock', '2026-01-18 12:46:09'),
(8, 'TX-20260118-5264', 'receive', 'mro', 3, NULL, 20.00, 'pcs', 'purchase', NULL, NULL, 'MRO Storage', 2, 'Initial MRO stock', '2026-01-18 12:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `master_recipes`
--

CREATE TABLE `master_recipes` (
  `id` int(11) NOT NULL,
  `recipe_code` varchar(30) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar') NOT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `size_ml` int(11) DEFAULT NULL,
  `size_grams` int(11) DEFAULT NULL,
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

INSERT INTO `master_recipes` (`id`, `recipe_code`, `product_name`, `product_type`, `variant`, `size_ml`, `size_grams`, `description`, `base_milk_liters`, `expected_yield`, `yield_unit`, `shelf_life_days`, `pasteurization_temp`, `pasteurization_time_mins`, `cooling_temp`, `special_instructions`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'RCP-001', 'Highland Fresh Choco Milk 330ml', 'bottled_milk', 'Chocolate', 330, NULL, NULL, 100.00, 300, 'units', 7, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08'),
(2, 'RCP-002', 'Highland Fresh Plain Milk 500ml', 'bottled_milk', 'Plain', 500, NULL, NULL, 100.00, 200, 'units', 7, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08'),
(3, 'RCP-003', 'Highland Fresh Melon Milk 330ml', 'bottled_milk', 'Melon', 330, NULL, NULL, 100.00, 300, 'units', 7, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08'),
(4, 'RCP-004', 'Highland Fresh Strawberry Milk 330ml', 'bottled_milk', 'Strawberry', 330, NULL, NULL, 100.00, 300, 'units', 7, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08'),
(5, 'RCP-005', 'Highland Fresh Cheese 250g', 'cheese', 'White Cheese', NULL, 250, NULL, 50.00, 20, 'kg', 30, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08'),
(6, 'RCP-006', 'Highland Fresh Butter 250g', 'butter', 'Salted', NULL, 250, NULL, 100.00, 10, 'kg', 60, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08'),
(7, 'RCP-007', 'Highland Fresh Yogurt 200ml', 'yogurt', 'Strawberry', 200, NULL, NULL, 50.00, 250, 'units', 14, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08'),
(8, 'RCP-008', 'Highland Fresh Milk Bar Choco', 'milk_bar', 'Chocolate', 75, NULL, NULL, 50.00, 500, 'units', 30, 81.00, 15, 4.00, NULL, 1, 1, '2026-01-13 13:12:08', '2026-01-13 13:12:08');

-- --------------------------------------------------------

--
-- Table structure for table `milk_deliveries`
--

CREATE TABLE `milk_deliveries` (
  `id` int(11) NOT NULL,
  `delivery_code` varchar(30) NOT NULL,
  `rmr_number` varchar(20) DEFAULT NULL COMMENT 'Raw Milk Receipt Number',
  `farmer_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_time` time DEFAULT NULL,
  `volume_liters` decimal(10,2) NOT NULL,
  `rejected_liters` decimal(10,2) DEFAULT 0.00,
  `accepted_liters` decimal(10,2) NOT NULL,
  `sediment_grade` varchar(10) DEFAULT 'G-1',
  `acidity_ta` decimal(5,2) DEFAULT NULL COMMENT 'Titratable Acidity %',
  `fat_percentage` decimal(5,2) DEFAULT NULL,
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `transport_cost` decimal(10,2) DEFAULT 0.00,
  `price_per_liter` decimal(10,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `rejection_reason` text DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `status` enum('pending','pending_test','accepted','rejected','partial') DEFAULT 'pending_test',
  `grade` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `apt_result` enum('positive','negative') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milk_deliveries`
--

INSERT INTO `milk_deliveries` (`id`, `delivery_code`, `rmr_number`, `farmer_id`, `delivery_date`, `delivery_time`, `volume_liters`, `rejected_liters`, `accepted_liters`, `sediment_grade`, `acidity_ta`, `fat_percentage`, `temperature_celsius`, `transport_cost`, `price_per_liter`, `total_amount`, `rejection_reason`, `unit_price`, `received_by`, `status`, `grade`, `notes`, `apt_result`, `created_at`, `updated_at`) VALUES
(1, 'DLV-2025-0001', '66173', 1, '2025-10-21', NULL, 104.00, 0.00, 104.00, 'G-1', 0.17, 3.50, NULL, 0.00, 41.25, 3146.00, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(2, 'DLV-2025-0002', '66174', 2, '2025-10-21', NULL, 52.00, 0.00, 52.00, 'G-1', 0.16, 3.50, NULL, 0.00, 42.25, 1573.00, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(3, 'DLV-2025-0003', '66175', 3, '2025-10-21', NULL, 115.00, 0.00, 115.00, 'G-1', 0.16, 2.50, NULL, 0.00, 40.00, 3450.00, NULL, 30.00, NULL, 'accepted', 'B', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(4, 'DLV-2025-0004', '66176', 4, '2025-10-21', NULL, 105.00, 0.00, 105.00, 'G-1', 0.17, 3.00, NULL, 0.00, 41.50, 3150.00, NULL, 30.00, NULL, 'accepted', 'B', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(5, 'DLV-2025-0005', '66177', 5, '2025-10-21', NULL, 50.00, 0.00, 50.00, 'G-1', 0.16, 3.50, NULL, 500.00, 42.50, 1500.00, NULL, 30.00, NULL, 'accepted', 'B', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(6, 'DLV-2025-0006', '66178', 6, '2025-10-21', NULL, 118.00, 0.00, 118.00, 'G-1', 0.17, 3.00, NULL, 0.00, 40.00, 3569.50, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(7, 'DLV-2025-0007', '66179', 7, '2025-10-21', NULL, 598.00, 0.00, 598.00, 'G-1', 0.17, 2.50, NULL, 0.00, 40.00, 18089.50, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(8, 'DLV-2025-0008', '66180', 8, '2025-10-21', NULL, 181.00, 171.00, 10.00, 'G-1', 0.17, 3.00, NULL, 0.00, 39.25, 392.50, NULL, NULL, NULL, 'partial', NULL, NULL, NULL, '2026-01-13 12:49:17', '2026-01-13 12:49:17'),
(9, 'DLV-2025-0009', '66181', 9, '2025-10-21', NULL, 171.00, 0.00, 171.00, 'G-1', 0.17, 3.00, NULL, 0.00, 41.25, 5172.75, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(10, 'DLV-2025-0010', '66182', 10, '2025-10-21', NULL, 88.00, 0.00, 88.00, 'G-1', 0.17, 2.50, NULL, 0.00, 40.00, 2684.00, NULL, 30.50, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(11, 'DLV-2025-0011', '66183', 11, '2025-10-21', NULL, 22.00, 0.00, 22.00, 'G-1', 0.17, 5.00, NULL, 0.00, 70.00, 660.00, NULL, 30.00, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(12, 'DLV-2025-0012', '66184', 12, '2025-10-21', NULL, 117.00, 0.00, 117.00, 'G-1', 0.17, 3.00, NULL, 500.00, 41.25, 3539.25, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(13, 'DLV-2025-0013', '66185', 13, '2025-10-21', NULL, 147.00, 0.00, 147.00, 'G-1', 0.17, 3.00, NULL, 500.00, 40.75, 4446.75, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(14, 'DLV-2025-0014', '66186', 14, '2025-10-21', NULL, 154.00, 0.00, 154.00, 'G-1', 0.17, 2.50, NULL, 500.00, 40.50, 4658.50, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(15, 'DLV-2025-0015', '66187', 15, '2025-10-21', NULL, 68.00, 0.00, 68.00, 'G-1', 0.18, 3.00, NULL, 500.00, 40.25, 2057.00, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(16, 'DLV-2025-0016', '66188', 16, '2025-10-21', NULL, 10.00, 0.00, 10.00, 'G-1', 0.18, 5.00, NULL, 0.00, 69.25, 302.50, NULL, 30.25, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(17, 'DLV-2025-0017', '66189', 17, '2025-10-21', NULL, 194.00, 0.00, 194.00, 'G-1', 0.17, 3.00, NULL, 1000.00, 40.75, 5820.00, NULL, 30.00, NULL, 'accepted', 'A', NULL, NULL, '2026-01-13 12:49:17', '2026-01-18 10:56:54'),
(18, 'DEL-000001', NULL, 4, '2026-01-13', '20:49:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, 'pending_test', NULL, '', NULL, '2026-01-13 12:50:30', '2026-01-13 14:08:27'),
(19, 'DEL-000002', NULL, 5, '2026-01-13', '21:57:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, 'pending_test', NULL, 'Yawards', NULL, '2026-01-13 13:57:20', '2026-01-13 14:08:27'),
(20, 'DEL-000003', NULL, 4, '2026-01-13', '22:02:00', 50.00, 0.00, 50.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 1500.00, NULL, 30.00, 1, 'accepted', 'B', 'Basta', NULL, '2026-01-13 14:02:42', '2026-01-18 08:46:39'),
(21, 'DEL-000004', NULL, 5, '2026-01-13', '23:17:00', 500.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, 'APT test positive; Specific gravity below 1.025 (suspected adulteration)', 0.00, 1, 'rejected', 'Rejected', '50', 'positive', '2026-01-13 15:17:52', '2026-01-13 15:19:11'),
(22, 'DEL-000005', NULL, 3, '2026-01-17', '21:00:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, 'pending_test', NULL, '', 'negative', '2026-01-17 13:00:24', '2026-01-17 13:00:24'),
(23, 'DEL-000006', NULL, 5, '2026-01-18', '15:27:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, 'APT test positive', 0.00, 1, 'rejected', 'Rejected', 'Basta', 'positive', '2026-01-18 07:27:41', '2026-01-18 07:28:25'),
(24, 'DEL-000007', NULL, 5, '2026-01-18', '15:28:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, 'APT test positive', 0.00, 1, 'rejected', 'Rejected', 'Basta', 'positive', '2026-01-18 07:28:47', '2026-01-18 07:29:37'),
(25, 'DEL-000008', NULL, 8, '2026-01-18', '15:29:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, 'APT test positive', 0.00, 1, 'rejected', 'Rejected', 'bASTA', 'positive', '2026-01-18 07:29:50', '2026-01-18 07:30:14'),
(26, 'DEL-000009', NULL, 6, '2026-01-18', '15:46:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, 'APT test positive', 0.00, 1, 'rejected', 'Rejected', 'Basta', 'positive', '2026-01-18 07:47:06', '2026-01-18 07:47:25'),
(27, 'DEL-000010', NULL, 7, '2026-01-18', '15:47:00', 50.00, 0.00, 0.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, NULL, 1, 'pending_test', NULL, 'Basta', 'negative', '2026-01-18 07:47:43', '2026-01-18 07:47:43'),
(28, 'DEL-000011', NULL, 5, '2026-01-18', '15:50:00', 50.00, 0.00, 50.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 1475.00, NULL, 29.50, 1, 'accepted', 'B', 'Yawards', 'negative', '2026-01-18 07:50:10', '2026-01-18 08:46:39'),
(29, 'DEL-000012', NULL, 1, '2026-01-18', '18:54:50', 50.00, 0.00, 50.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 1500.00, NULL, 30.00, NULL, 'accepted', 'B', NULL, 'negative', '2026-01-18 10:54:50', '2026-01-18 10:54:50'),
(32, 'DEL-000013', NULL, 2, '2026-01-18', '19:03:00', 50.00, 0.00, 50.00, 'G-1', NULL, NULL, NULL, 0.00, 0.00, 1475.00, NULL, 29.50, 1, 'accepted', 'B', 'Basta', 'negative', '2026-01-18 11:04:01', '2026-01-18 11:04:22');

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
(1, 'MRO-SPARE', 'Spare Parts', 'Machine replacement parts', 1, '2026-01-17 09:26:04'),
(2, 'MRO-TOOL', 'Tools', 'Hand tools and equipment', 1, '2026-01-17 09:26:04'),
(3, 'MRO-CLEAN', 'Cleaning Supplies', 'Sanitation and cleaning materials', 1, '2026-01-17 09:26:04'),
(4, 'MRO-SAFETY', 'Safety Equipment', 'PPE and safety supplies', 1, '2026-01-17 09:26:04'),
(5, 'MRO-CONSUMABLE', 'Consumables', 'Lubricants, filters, etc.', 1, '2026-01-17 09:26:04');

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
(1, 'MRO-20260118-9567', 1, 22.00, 22.00, 369.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(2, 'MRO-20260118-5755', 2, 21.00, 21.00, 120.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(3, 'MRO-20260118-0662', 3, 33.00, 33.00, 398.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(4, 'MRO-20260118-2665', 4, 20.00, 20.00, 298.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(5, 'MRO-20260118-5948', 5, 34.00, 34.00, 283.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(6, 'MRO-20260118-3329', 6, 42.00, 42.00, 106.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(7, 'MRO-20260118-1056', 7, 34.00, 34.00, 324.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(8, 'MRO-20260118-2235', 8, 20.00, 20.00, 165.00, 'MRO Supplier', '2026-01-18', 2, 'available', NULL, '2026-01-18 12:46:09', '2026-01-18 12:46:09');

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
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `compatible_equipment` text DEFAULT NULL COMMENT 'List of equipment this part is for',
  `is_critical` tinyint(1) DEFAULT 0 COMMENT 'Critical spare part flag',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mro_items`
--

INSERT INTO `mro_items` (`id`, `item_code`, `item_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `current_stock`, `unit_cost`, `storage_location`, `compatible_equipment`, `is_critical`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'MRO-001', 'Pasteurizer Gasket Set', 1, 'set', 3.00, 22.00, NULL, NULL, 'Pasteurizer', 1, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(2, 'MRO-002', 'Homogenizer Valve', 1, 'pcs', 2.00, 21.00, NULL, NULL, 'Homogenizer', 1, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(3, 'MRO-003', 'Fill-Seal Machine Nozzles', 1, 'pcs', 5.00, 33.00, NULL, NULL, 'Fill-Seal Machine', 1, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(4, 'MRO-004', 'Retort Pressure Gauge', 1, 'pcs', 2.00, 20.00, NULL, NULL, 'Retort', 1, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(5, 'MRO-005', 'Food-Grade Lubricant', 5, 'L', 5.00, 34.00, NULL, NULL, 'All machinery', 0, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(6, 'MRO-006', 'CIP Cleaning Solution', 3, 'L', 20.00, 42.00, NULL, NULL, 'CIP System', 0, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(7, 'MRO-007', 'Sanitizer (Food-Safe)', 3, 'L', 10.00, 34.00, NULL, NULL, 'General use', 0, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(8, 'MRO-008', 'Nitrile Gloves (Box)', 4, 'box', 10.00, 20.00, NULL, NULL, NULL, 0, 1, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(9, 'MRO-009', 'Hair Nets (Pack)', 4, 'pack', 20.00, 0.00, NULL, NULL, NULL, 0, 1, '2026-01-17 09:26:04', '2026-01-17 09:26:04'),
(10, 'MRO-010', 'Temperature Probe', 2, 'pcs', 2.00, 0.00, NULL, NULL, 'All tanks', 1, 1, '2026-01-17 09:26:04', '2026-01-17 09:26:04');

-- --------------------------------------------------------

--
-- Table structure for table `production_batches`
--

CREATE TABLE `production_batches` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) DEFAULT NULL,
  `run_id` int(11) DEFAULT NULL,
  `batch_code` varchar(50) NOT NULL,
  `product_type` varchar(50) NOT NULL,
  `product_variant` varchar(50) DEFAULT NULL,
  `raw_milk_liters` decimal(10,2) NOT NULL,
  `batch_size_multiplier` decimal(5,2) DEFAULT 1.00,
  `manufacturing_date` date NOT NULL,
  `manufacturing_time` time DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `pasteurization_temp` decimal(5,2) DEFAULT NULL COMMENT 'Must reach 81┬░C+',
  `pasteurization_time` time DEFAULT NULL,
  `cooling_temp` decimal(5,2) DEFAULT NULL COMMENT 'Must reach Ôëñ4┬░C',
  `cooling_time` time DEFAULT NULL,
  `organoleptic_taste` tinyint(1) DEFAULT 0,
  `organoleptic_appearance` tinyint(1) DEFAULT 0,
  `organoleptic_smell` tinyint(1) DEFAULT 0,
  `qc_status` enum('pending','released','rejected','on_hold') DEFAULT 'pending',
  `qc_released_at` datetime DEFAULT NULL,
  `fg_received` tinyint(1) DEFAULT 0,
  `fg_received_at` datetime DEFAULT NULL,
  `fg_received_by` int(11) DEFAULT NULL,
  `qc_notes` text DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `expected_yield` int(11) DEFAULT NULL,
  `actual_yield` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `released_by` int(11) DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_batches`
--

INSERT INTO `production_batches` (`id`, `recipe_id`, `run_id`, `batch_code`, `product_type`, `product_variant`, `raw_milk_liters`, `batch_size_multiplier`, `manufacturing_date`, `manufacturing_time`, `expiry_date`, `pasteurization_temp`, `pasteurization_time`, `cooling_temp`, `cooling_time`, `organoleptic_taste`, `organoleptic_appearance`, `organoleptic_smell`, `qc_status`, `qc_released_at`, `fg_received`, `fg_received_at`, `fg_received_by`, `qc_notes`, `barcode`, `expected_yield`, `actual_yield`, `created_by`, `released_by`, `released_at`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 'BTH-20260113-F17E', 'bottled_milk', 'Chocolate', 100.00, 1.00, '2026-01-13', '11:40:31', '2026-01-20', 81.00, '11:46:55', NULL, NULL, 0, 0, 0, 'on_hold', NULL, 0, NULL, NULL, 'OKAY', NULL, 300, NULL, 1, NULL, NULL, '2026-01-13 10:40:31', '2026-01-13 11:11:23'),
(2, 2, NULL, 'BTH-20260113-13ED', 'bottled_milk', 'Chocolate', 100.00, 1.00, '2026-01-13', '11:40:31', '2026-01-20', 81.00, '11:41:31', 4.00, '11:41:33', 0, 0, 0, 'on_hold', NULL, 0, NULL, NULL, 'OKAY', NULL, 300, NULL, 1, NULL, NULL, '2026-01-13 10:40:31', '2026-01-13 11:11:21'),
(3, 9, NULL, 'BTH-20260113-E429', 'yogurt', 'Plain', 50.00, 1.00, '2026-01-13', '11:45:59', '2026-01-27', NULL, NULL, NULL, NULL, 0, 0, 0, 'on_hold', NULL, 0, NULL, NULL, 'Fuck You', NULL, 150, NULL, 1, NULL, NULL, '2026-01-13 10:45:59', '2026-01-13 11:11:13'),
(4, 9, NULL, 'BTH-20260113-8088', 'yogurt', 'Plain', 50.00, 1.00, '2026-01-13', '12:09:32', '2026-01-27', 50.00, '12:09:46', 81.00, '12:10:20', 1, 1, 1, 'released', '2026-01-13 19:26:30', 1, '2026-01-13 19:28:32', 1, 'Fuck You', 'HF-BTH-20260113-8088-TEST', 150, NULL, 1, NULL, '2026-01-13 11:26:30', '2026-01-13 11:09:32', '2026-01-13 11:28:32'),
(5, 1, NULL, 'BATCH-00001', 'pasteurized_milk', NULL, 0.00, 1.00, '2026-01-18', NULL, '2026-02-17', NULL, NULL, NULL, NULL, 1, 1, 1, 'released', '2026-01-18 18:54:50', 0, NULL, NULL, 'Integration test - auto released', NULL, NULL, 48, NULL, NULL, NULL, '2026-01-18 10:54:50', '2026-01-18 10:54:50'),
(10, 1, 2, 'BATCH-00002', 'bottled_milk', NULL, 50.00, 1.00, '2026-01-13', NULL, '2026-02-12', NULL, NULL, NULL, NULL, 0, 0, 0, 'pending', NULL, 0, NULL, NULL, 'Auto-generated from completed run', NULL, NULL, 100, NULL, NULL, NULL, '2026-01-18 11:23:23', '2026-01-18 11:23:23'),
(11, 4, 12, 'BATCH-00003', 'bottled_milk', NULL, 50.00, 1.00, '2026-01-13', NULL, '2026-02-12', NULL, NULL, NULL, NULL, 0, 0, 0, 'pending', NULL, 0, NULL, NULL, 'Auto-generated from completed run', NULL, NULL, 100, NULL, NULL, NULL, '2026-01-18 11:23:23', '2026-01-18 11:23:23'),
(12, 1, 13, 'BATCH-00004', 'bottled_milk', NULL, 100.00, 1.00, '2026-01-13', NULL, '2026-02-12', NULL, NULL, NULL, NULL, 0, 0, 0, 'pending', NULL, 0, NULL, NULL, 'Auto-generated from completed run', NULL, NULL, 100, NULL, NULL, NULL, '2026-01-18 11:23:23', '2026-01-18 11:23:23');

-- --------------------------------------------------------

--
-- Table structure for table `production_byproducts`
--

CREATE TABLE `production_byproducts` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `byproduct_type` enum('buttermilk','whey','cream','skim_milk','other') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'liters',
  `destination` enum('warehouse','reprocess','dispose','sale') DEFAULT NULL,
  `status` enum('pending','transferred','disposed','sold') DEFAULT 'pending',
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_byproducts`
--

INSERT INTO `production_byproducts` (`id`, `run_id`, `byproduct_type`, `quantity`, `unit`, `destination`, `status`, `recorded_by`, `notes`, `created_at`) VALUES
(1, 2, 'buttermilk', 10.00, 'liters', NULL, 'pending', 7, '', '2026-01-13 13:41:22'),
(2, 2, 'buttermilk', 10.00, 'liters', NULL, 'pending', 7, '', '2026-01-13 13:42:01');

-- --------------------------------------------------------

--
-- Table structure for table `production_ccp_logs`
--

CREATE TABLE `production_ccp_logs` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `check_type` enum('chilling','preheating','homogenization','pasteurization','cooling','storage','intermediate') NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `pressure` decimal(6,2) DEFAULT NULL,
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

INSERT INTO `production_ccp_logs` (`id`, `run_id`, `check_type`, `temperature`, `pressure`, `pressure_psi`, `hold_time_mins`, `hold_time_secs`, `target_temp`, `temp_tolerance`, `status`, `check_datetime`, `verified_by`, `notes`, `created_at`) VALUES
(1, 12, 'pasteurization', 31.50, NULL, NULL, 0, 0, 81.00, 2.00, 'fail', '2026-01-13 13:41:04', 7, 'Basta', '2026-01-13 13:41:04'),
(2, 12, 'pasteurization', 31.50, NULL, NULL, 0, 0, 81.00, 2.00, 'fail', '2026-01-13 13:41:07', 7, 'Basta', '2026-01-13 13:41:07'),
(3, 12, 'pasteurization', 31.50, NULL, NULL, 0, 0, 81.00, 2.00, 'fail', '2026-01-13 13:41:12', 7, 'Basta', '2026-01-13 13:41:12'),
(4, 12, 'pasteurization', 31.50, NULL, NULL, 0, 0, 81.00, 2.00, 'fail', '2026-01-13 13:41:13', 7, 'Basta', '2026-01-13 13:41:13'),
(5, 12, 'pasteurization', 20.00, NULL, NULL, 0, 0, 81.00, 2.00, 'fail', '2026-01-13 13:41:40', 7, 'WOW', '2026-01-13 13:41:40'),
(6, 2, 'pasteurization', 82.50, NULL, NULL, 15, 0, 81.00, 2.00, 'pass', '2026-01-13 13:42:52', 7, '', '2026-01-13 13:42:52');

-- --------------------------------------------------------

--
-- Table structure for table `production_logs`
--

CREATE TABLE `production_logs` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `log_type` enum('pasteurization','homogenization','cooling','stirring','pressing','churning','filling','other') NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL COMMENT 'Temperature in Celsius',
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `actual_value` varchar(100) DEFAULT NULL COMMENT 'For non-temperature logs',
  `logged_by` int(11) DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_logs`
--

INSERT INTO `production_logs` (`id`, `batch_id`, `log_type`, `temperature`, `start_time`, `end_time`, `duration_minutes`, `actual_value`, `logged_by`, `logged_at`, `notes`) VALUES
(1, 2, 'pasteurization', 81.00, NULL, NULL, NULL, NULL, 1, '2026-01-13 10:41:31', 'Quick logged'),
(2, 2, 'pasteurization', 81.00, NULL, NULL, NULL, NULL, 1, '2026-01-13 10:41:31', 'Quick logged'),
(3, 2, 'cooling', 4.00, NULL, NULL, NULL, NULL, 1, '2026-01-13 10:41:32', 'Quick logged'),
(4, 2, 'cooling', 4.00, NULL, NULL, NULL, NULL, 1, '2026-01-13 10:41:32', 'Quick logged'),
(5, 1, 'pasteurization', 81.00, NULL, NULL, NULL, NULL, 1, '2026-01-13 10:46:55', 'Basta'),
(6, 4, 'pasteurization', 50.00, NULL, NULL, NULL, NULL, 1, '2026-01-13 11:09:46', 'Bang'),
(7, 4, 'homogenization', 81.00, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 1, NULL, 1, '2026-01-13 11:10:06', 'Basta'),
(8, 4, 'cooling', 81.00, NULL, NULL, NULL, NULL, 1, '2026-01-13 11:10:20', 'Basta');

-- --------------------------------------------------------

--
-- Table structure for table `production_runs`
--

CREATE TABLE `production_runs` (
  `id` int(11) NOT NULL,
  `run_code` varchar(30) NOT NULL,
  `recipe_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `planned_quantity` int(11) NOT NULL,
  `actual_quantity` int(11) DEFAULT NULL,
  `milk_batch_source` text DEFAULT NULL,
  `milk_liters_used` decimal(10,2) DEFAULT NULL,
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
  `output_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Stores unit breakdown: total_pieces, secondary_count, remaining_primary, etc.' CHECK (json_valid(`output_breakdown`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_runs`
--

INSERT INTO `production_runs` (`id`, `run_code`, `recipe_id`, `batch_id`, `planned_quantity`, `actual_quantity`, `milk_batch_source`, `milk_liters_used`, `status`, `start_datetime`, `end_datetime`, `started_by`, `completed_by`, `yield_variance`, `variance_reason`, `notes`, `created_at`, `updated_at`, `output_breakdown`) VALUES
(1, 'PRD-20260113-001', 1, NULL, 100, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-01-13 13:30:51', '2026-01-13 13:37:58', NULL),
(2, 'PRD-20260113-002', 1, NULL, 100, 100, NULL, 50.00, 'completed', '2026-01-13 21:31:54', '2026-01-13 21:32:35', NULL, NULL, 0.00, 'Basta', '', '2026-01-13 13:30:57', '2026-01-13 13:32:35', NULL),
(3, 'PRD-20260113-003', 2, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:17', '2026-01-13 13:37:57', NULL),
(4, 'PRD-20260113-004', 2, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:21', '2026-01-13 13:37:55', NULL),
(5, 'PRD-20260113-005', 2, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:24', '2026-01-13 13:37:53', NULL),
(6, 'PRD-20260113-006', 2, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:25', '2026-01-13 13:37:51', NULL),
(7, 'PRD-20260113-007', 2, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:25', '2026-01-13 13:37:50', NULL),
(8, 'PRD-20260113-008', 2, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:25', '2026-01-13 13:37:48', NULL),
(9, 'PRD-20260113-009', 5, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:28', '2026-01-13 13:37:46', NULL),
(10, 'PRD-20260113-010', 5, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:28', '2026-01-13 13:37:44', NULL),
(11, 'PRD-20260113-011', 5, NULL, 50, NULL, NULL, 50.00, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'Basta', '2026-01-13 13:37:28', '2026-01-13 13:37:42', NULL),
(12, 'PRD-20260113-012', 4, NULL, 100, 100, NULL, 50.00, 'completed', '2026-01-13 21:38:21', '2026-01-13 22:45:09', NULL, 7, 0.00, 'WOW', 'Basta', '2026-01-13 13:38:10', '2026-01-13 14:45:09', NULL),
(13, 'PRD-20260113-013', 1, NULL, 100, 100, NULL, 100.00, 'completed', '2026-01-13 22:45:13', '2026-01-13 22:46:13', 7, 7, 0.00, '50', 'Yeheyy', '2026-01-13 14:44:38', '2026-01-13 14:46:13', NULL),
(14, 'PRD-20260118-001', 2, NULL, 50, NULL, '[{\"delivery_id\":28,\"delivery_code\":\"DEL-000011\",\"test_code\":\"QCT-000007\",\"liters_available\":\"50.00\"}]', 10.00, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, 'V', '2026-01-18 08:40:58', '2026-01-18 08:40:58', NULL),
(18, 'PRD-20260118-002', 1, NULL, 50, NULL, '[{\"delivery_id\":28,\"delivery_code\":\"DEL-000011\",\"test_code\":\"QCT-000007\",\"liters_available\":\"40.00\"},{\"delivery_id\":29,\"delivery_code\":\"DEL-000012\",\"test_code\":\"QCT-000008\",\"liters_available\":\"50.00\"}]', 50.00, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, '50', '2026-01-18 11:34:12', '2026-01-18 11:34:12', NULL),
(19, 'PRD-20260118-003', 3, NULL, 50, NULL, '[{\"delivery_id\":29,\"delivery_code\":\"DEL-000012\",\"test_code\":\"QCT-000008\",\"liters_available\":\"40.00\"},{\"delivery_id\":32,\"delivery_code\":\"DEL-000013\",\"test_code\":\"QCT-000025\",\"liters_available\":\"50.00\"}]', 50.00, 'planned', NULL, NULL, NULL, NULL, NULL, NULL, '50', '2026-01-18 11:34:17', '2026-01-18 11:34:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `production_run_milk_usage`
--

CREATE TABLE `production_run_milk_usage` (
  `id` int(11) NOT NULL,
  `run_id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `milk_liters_allocated` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_run_milk_usage`
--

INSERT INTO `production_run_milk_usage` (`id`, `run_id`, `delivery_id`, `milk_liters_allocated`, `created_at`) VALUES
(1, 14, 28, 10.00, '2026-01-18 08:40:58'),
(11, 18, 28, 40.00, '2026-01-18 11:34:12'),
(12, 18, 29, 10.00, '2026-01-18 11:34:12'),
(13, 19, 29, 40.00, '2026-01-18 11:34:17'),
(14, 19, 32, 10.00, '2026-01-18 11:34:17');

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
  `description` text DEFAULT NULL,
  `unit_size` decimal(10,2) DEFAULT NULL,
  `unit_measure` varchar(20) DEFAULT 'ml',
  `shelf_life_days` int(11) DEFAULT 7,
  `storage_temp_min` decimal(4,2) DEFAULT 2.00,
  `storage_temp_max` decimal(4,2) DEFAULT 6.00,
  `is_active` tinyint(1) DEFAULT 1,
  `base_unit` varchar(20) DEFAULT 'piece' COMMENT 'Smallest sellable unit: piece, bottle, pack, bar',
  `box_unit` varchar(20) DEFAULT 'box' COMMENT 'Container unit: box, crate, case, tray',
  `pieces_per_box` int(11) DEFAULT 1 COMMENT 'How many base units fit in one box unit',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `product_name`, `category`, `variant`, `description`, `unit_size`, `unit_measure`, `shelf_life_days`, `storage_temp_min`, `storage_temp_max`, `is_active`, `base_unit`, `box_unit`, `pieces_per_box`, `created_at`, `updated_at`) VALUES
(1, 'PM-1000', 'Highland Fresh Pasteurized Milk', 'pasteurized_milk', '1 Liter', NULL, 1000.00, 'ml', 7, 2.00, 6.00, 1, 'bottle', 'crate', 24, '2026-01-13 15:11:42', '2026-01-18 12:15:27'),
(2, 'PM-500', 'Highland Fresh Pasteurized Milk', 'pasteurized_milk', '500ml', NULL, 500.00, 'ml', 7, 2.00, 6.00, 1, 'bottle', 'crate', 24, '2026-01-13 15:11:42', '2026-01-18 12:15:27'),
(3, 'PM-250', 'Highland Fresh Pasteurized Milk', 'pasteurized_milk', '250ml', NULL, 250.00, 'ml', 7, 2.00, 6.00, 1, 'bottle', 'crate', 24, '2026-01-13 15:11:42', '2026-01-18 12:15:27'),
(4, 'FM-CHOC-500', 'Highland Fresh Chocolate Milk', 'flavored_milk', 'Chocolate 500ml', NULL, 500.00, 'ml', 5, 2.00, 6.00, 1, 'bottle', 'case', 24, '2026-01-13 15:11:42', '2026-01-18 12:15:28'),
(5, 'FM-STRW-500', 'Highland Fresh Strawberry Milk', 'flavored_milk', 'Strawberry 500ml', NULL, 500.00, 'ml', 5, 2.00, 6.00, 1, 'bottle', 'case', 24, '2026-01-13 15:11:42', '2026-01-18 12:15:28'),
(6, 'YG-PLAIN-150', 'Highland Fresh Plain Yogurt', 'yogurt', 'Plain 150g', NULL, 150.00, 'g', 14, 2.00, 6.00, 1, 'cup', 'tray', 12, '2026-01-13 15:11:42', '2026-01-18 12:15:28'),
(7, 'YG-STRW-150', 'Highland Fresh Strawberry Yogurt', 'yogurt', 'Strawberry 150g', NULL, 150.00, 'g', 14, 2.00, 6.00, 1, 'cup', 'tray', 12, '2026-01-13 15:11:42', '2026-01-18 12:15:28'),
(8, 'YG-MANGO-150', 'Highland Fresh Mango Yogurt', 'yogurt', 'Mango 150g', NULL, 150.00, 'g', 14, 2.00, 6.00, 1, 'cup', 'tray', 12, '2026-01-13 15:11:42', '2026-01-18 12:15:28'),
(9, 'PROD-HIGHLANDMI-382', 'Highland Milk Bar', 'flavored_milk', NULL, NULL, NULL, 'ml', 7, 2.00, 6.00, 1, 'piece', 'box', 1, '2026-01-18 12:51:37', '2026-01-18 12:51:37'),
(10, 'PROD-HIGHLANDCH-824', 'Highland Chocolate Milk', 'pasteurized_milk', NULL, NULL, 200.00, 'ml', 7, 2.00, 6.00, 1, 'piece', 'box', 1, '2026-01-18 12:51:37', '2026-01-18 12:51:37'),
(11, 'PROD-HIGHLANDBU-189', 'Highland Butter', 'butter', NULL, NULL, NULL, 'ml', 7, 2.00, 6.00, 1, 'piece', 'box', 1, '2026-01-18 12:51:37', '2026-01-18 12:51:37'),
(12, 'PROD-HIGHLANDFR-410', 'Highland Fresh Milk', 'pasteurized_milk', NULL, NULL, 1000.00, 'ml', 7, 2.00, 6.00, 1, 'piece', 'box', 1, '2026-01-18 12:51:37', '2026-01-18 12:51:37');

-- --------------------------------------------------------

--
-- Table structure for table `product_returns`
--

CREATE TABLE `product_returns` (
  `id` int(11) NOT NULL,
  `return_code` varchar(30) NOT NULL COMMENT 'e.g., RET-20260118-001',
  `dr_id` int(11) DEFAULT NULL COMMENT 'Original delivery receipt',
  `dr_number` varchar(30) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(200) NOT NULL,
  `return_date` date NOT NULL,
  `return_reason` enum('damaged_transit','expired','customer_rejection','quality_issue','overage','other') NOT NULL,
  `disposition` enum('return_to_inventory','hold_for_qc','dispose','rework') DEFAULT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `total_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','inspected','processed','closed') NOT NULL DEFAULT 'pending',
  `received_by` int(11) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_return_items`
--

CREATE TABLE `product_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `batch_code` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_boxes` int(11) NOT NULL DEFAULT 0,
  `quantity_pieces` int(11) NOT NULL DEFAULT 0,
  `unit_value` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `condition_status` enum('good','damaged','expired','questionable') NOT NULL,
  `disposition` enum('return_to_inventory','hold_for_qc','dispose','rework') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_units`
--

CREATE TABLE `product_units` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `base_unit` varchar(20) NOT NULL COMMENT 'Smallest unit: piece, bottle, pack, bar, slice',
  `box_unit` varchar(20) NOT NULL COMMENT 'Container unit: box, crate, case, tray',
  `pieces_per_box` int(11) NOT NULL COMMENT 'Conversion ratio: how many base units per box',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_units`
--

INSERT INTO `product_units` (`id`, `product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'piece', 'box', 1, 1, 'Auto-configured for Highland Fresh Pasteurized Milk', '2026-01-17 11:45:24', '2026-01-17 11:45:24'),
(2, 2, 'piece', 'box', 1, 1, 'Auto-configured for Highland Fresh Pasteurized Milk', '2026-01-17 11:45:24', '2026-01-17 11:45:24'),
(3, 3, 'piece', 'box', 1, 1, 'Auto-configured for Highland Fresh Pasteurized Milk', '2026-01-17 11:45:24', '2026-01-17 11:45:24'),
(4, 4, 'bottle', 'case', 24, 1, 'Auto-configured for Highland Fresh Chocolate Milk', '2026-01-17 11:45:24', '2026-01-17 11:45:52'),
(5, 5, 'piece', 'box', 1, 1, 'Auto-configured for Highland Fresh Strawberry Milk', '2026-01-17 11:45:24', '2026-01-17 11:45:24'),
(6, 6, 'cup', 'tray', 12, 1, 'Auto-configured for Highland Fresh Plain Yogurt', '2026-01-17 11:45:24', '2026-01-17 11:45:52'),
(7, 7, 'cup', 'tray', 12, 1, 'Auto-configured for Highland Fresh Strawberry Yogurt', '2026-01-17 11:45:24', '2026-01-17 11:45:52'),
(8, 8, 'cup', 'tray', 12, 1, 'Auto-configured for Highland Fresh Mango Yogurt', '2026-01-17 11:45:24', '2026-01-17 11:45:52');

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
(1, '5231', 1, '2025-01-04', NULL, 'received', 29750.00, 0.00, 29750.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(2, '5232', 2, '2025-01-07', NULL, 'received', 102000.00, 0.00, 102000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(3, '5233', 1, '2025-01-08', NULL, 'received', 59500.00, 0.00, 59500.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(4, '5234', 2, '2025-01-09', NULL, 'received', 83400.00, 0.00, 83400.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(5, '5235', 3, '2025-01-14', NULL, 'received', 13600.00, 1632.00, 15232.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(6, '5236', 1, '2025-01-11', NULL, 'received', 29750.00, 0.00, 29750.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(7, '5237', 2, '2025-01-15', NULL, 'received', 105000.00, 0.00, 105000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(8, '5238', 3, '2025-01-17', NULL, 'received', 40388.25, 0.00, 40388.25, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(9, '5239', 1, '2025-01-15', NULL, 'received', 59500.00, 0.00, 59500.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(10, '5240', 3, '2024-11-19', NULL, 'received', 600000.00, 0.00, 600000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(11, '5241', 4, '2025-01-17', NULL, 'received', 28000.00, 0.00, 28000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(12, '5242', 1, '2025-01-18', NULL, 'received', 64796.00, 0.00, 64796.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(13, '5243', 1, '2025-01-21', NULL, 'received', 49980.00, 0.00, 49980.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(14, '5244', 1, '2025-01-22', NULL, 'received', 17850.00, 0.00, 17850.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(15, '5245', 5, '2025-01-24', NULL, 'received', 56000.00, 0.00, 56000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:34', '2026-01-18 13:59:34'),
(16, '5246', 6, '2025-01-24', NULL, 'received', 61000.00, 0.00, 61000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:34', '2026-01-18 13:59:34'),
(17, '5247', 1, '2025-01-24', NULL, 'received', 59500.00, 0.00, 59500.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:34', '2026-01-18 13:59:34'),
(18, '5248', 2, '2025-01-24', NULL, 'received', 158500.00, 0.00, 158500.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:34', '2026-01-18 13:59:34'),
(19, '5249', 2, '2025-01-27', NULL, 'received', 87000.00, 0.00, 87000.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:34', '2026-01-18 13:59:34'),
(20, '5250', 1, '2025-01-29', NULL, 'received', 44625.00, 0.00, 44625.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:34', '2026-01-18 13:59:34'),
(21, '5251', 2, '2025-01-31', NULL, 'received', 112500.00, 0.00, 112500.00, 'unpaid', NULL, NULL, NULL, NULL, NULL, '2026-01-18 13:59:34', '2026-01-18 13:59:34');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `ingredient_id` int(11) DEFAULT NULL,
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

INSERT INTO `purchase_order_items` (`id`, `po_id`, `ingredient_id`, `item_description`, `quantity`, `unit`, `unit_price`, `total_amount`, `quantity_received`, `is_vat_item`, `notes`, `created_at`) VALUES
(1, 1, NULL, 'BOTTLES 1000ML', 5950.00, 'PCS', 4.38, 26061.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(2, 1, NULL, 'CAPS', 5950.00, 'PCS', 0.62, 3689.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(4, 2, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3400.00, 102000.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(5, 3, NULL, 'BOTTLES 1000ML', 11900.00, 'PCS', 4.38, 52122.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(6, 3, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(8, 4, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2780.00, 83400.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(9, 5, NULL, 'RIBBON ROLL', 20.00, 'ROLL', 680.00, 13600.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(10, 5, NULL, 'VAT', 1.00, 'LOT', 1632.00, 1632.00, 0.00, 1, NULL, '2026-01-18 13:59:33'),
(12, 6, NULL, 'BOTTLES 1000ML', 5950.00, 'PCS', 4.38, 26061.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(13, 6, NULL, 'CAPS', 5950.00, 'PCS', 0.62, 3689.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(15, 7, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3500.00, 105000.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(16, 8, NULL, 'LINX SOLVENT', 6.00, 'BOTS', 2315.25, 13891.50, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(17, 8, NULL, 'LINX INK', 5.00, 'BOTS', 5299.35, 26496.75, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(19, 9, NULL, 'BOTTLES 1000ML', 11900.00, 'PCS', 4.38, 52122.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(20, 9, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(22, 10, NULL, 'TT500 THERMAL', 5.00, 'UNIT', 120000.00, 600000.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(23, 11, NULL, 'BROWN SUGAR', 10.00, 'SCKS', 2800.00, 28000.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(24, 12, NULL, 'BOTTLES 1000ML', 5950.00, 'PCS', 4.38, 26061.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(25, 12, NULL, 'BOTTLES 500ML', 6570.00, 'PCS', 2.38, 15636.60, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(26, 12, NULL, 'BOTTLES 330ML', 5680.00, 'PCS', 2.08, 11814.40, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(27, 12, NULL, 'CAPS', 18200.00, 'PCS', 0.62, 11284.00, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(31, 13, NULL, 'BOTTLES 1000ML', 9996.00, 'PCS', 4.38, 43782.48, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(32, 13, NULL, 'CAPS', 9996.00, 'PCS', 0.62, 6197.52, 0.00, 0, NULL, '2026-01-18 13:59:33'),
(34, 14, NULL, 'BOTTLES 1000ML', 3570.00, 'PCS', 4.38, 15636.60, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(35, 14, NULL, 'CAPS', 3570.00, 'PCS', 0.62, 2213.40, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(37, 15, NULL, 'CAUSTIC SODA', 20.00, 'SCKS', 2800.00, 56000.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(38, 16, NULL, 'CHLORINIX', 10.00, 'BOXES', 800.00, 8000.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(39, 16, NULL, 'LINOL-LIQUID D', 10.00, 'BOXES', 1400.00, 14000.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(40, 16, NULL, 'ADVACIP 200', 10.00, 'CAR', 3900.00, 39000.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(41, 17, NULL, 'BOTTLES 1000ML', 11900.00, 'PCS', 4.38, 52122.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(42, 17, NULL, 'CAPS', 11900.00, 'PCS', 0.62, 7378.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(44, 18, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2850.00, 85500.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(45, 18, NULL, 'WHITE SUGAR', 20.00, 'SCKS', 3650.00, 73000.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(47, 19, NULL, 'BROWN SUGAR', 30.00, 'SCKS', 2900.00, 87000.00, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(48, 20, NULL, 'BOTTLES 1000ML', 8925.00, 'PCS', 4.38, 39091.50, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(49, 20, NULL, 'CAPS', 8925.00, 'PCS', 0.62, 5533.50, 0.00, 0, NULL, '2026-01-18 13:59:34'),
(51, 21, NULL, 'WHITE SUGAR', 30.00, 'SCKS', 3750.00, 112500.00, 0.00, 0, NULL, '2026-01-18 13:59:34');

-- --------------------------------------------------------

--
-- Table structure for table `qc_batch_release`
--

CREATE TABLE `qc_batch_release` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `qc_officer_id` int(11) NOT NULL,
  `verification_datetime` datetime DEFAULT NULL,
  `is_released` tinyint(1) DEFAULT 0,
  `release_datetime` datetime DEFAULT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `sensory_appearance` varchar(50) DEFAULT NULL,
  `sensory_odor` varchar(50) DEFAULT NULL,
  `sensory_taste` varchar(50) DEFAULT NULL,
  `sensory_texture` varchar(50) DEFAULT NULL,
  `sensory_notes` text DEFAULT NULL,
  `packaging_integrity` varchar(50) DEFAULT NULL,
  `label_accuracy` varchar(50) DEFAULT NULL,
  `seal_quality` varchar(50) DEFAULT NULL,
  `ccp_compliance` varchar(50) DEFAULT NULL,
  `release_decision` enum('approved','rejected','hold') DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `corrective_action` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qc_milk_tests`
--

CREATE TABLE `qc_milk_tests` (
  `id` int(11) NOT NULL,
  `test_code` varchar(30) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `test_datetime` datetime NOT NULL,
  `fat_percentage` decimal(5,2) NOT NULL,
  `titratable_acidity` decimal(5,4) DEFAULT NULL,
  `acidity_ph` decimal(4,2) DEFAULT NULL,
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `sediment_level` enum('clean','slight','moderate','heavy') DEFAULT 'clean',
  `sediment_grade` tinyint(1) DEFAULT 1,
  `density` decimal(6,4) DEFAULT NULL,
  `protein_percentage` decimal(5,2) DEFAULT NULL,
  `snf_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Solids-Not-Fat',
  `lactose_percentage` decimal(5,2) DEFAULT NULL,
  `grade` varchar(20) DEFAULT NULL,
  `is_accepted` tinyint(1) NOT NULL DEFAULT 1,
  `rejection_reason` text DEFAULT NULL,
  `base_price_per_liter` decimal(10,2) NOT NULL,
  `fat_adjustment` decimal(10,2) DEFAULT 0.00,
  `quality_adjustment` decimal(10,2) DEFAULT 0.00,
  `final_price_per_liter` decimal(10,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `tested_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `salts_percentage` decimal(5,2) DEFAULT NULL,
  `total_solids_percentage` decimal(5,2) DEFAULT NULL,
  `added_water_percentage` decimal(5,2) DEFAULT NULL,
  `freezing_point` decimal(6,4) DEFAULT NULL,
  `sample_temperature` decimal(4,1) DEFAULT NULL,
  `acidity_deduction` decimal(10,2) DEFAULT 0.00,
  `sediment_deduction` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_milk_tests`
--

INSERT INTO `qc_milk_tests` (`id`, `test_code`, `delivery_id`, `test_datetime`, `fat_percentage`, `titratable_acidity`, `acidity_ph`, `temperature_celsius`, `sediment_level`, `sediment_grade`, `density`, `protein_percentage`, `snf_percentage`, `lactose_percentage`, `grade`, `is_accepted`, `rejection_reason`, `base_price_per_liter`, `fat_adjustment`, `quality_adjustment`, `final_price_per_liter`, `total_amount`, `tested_by`, `notes`, `created_at`, `updated_at`, `salts_percentage`, `total_solids_percentage`, `added_water_percentage`, `freezing_point`, `sample_temperature`, `acidity_deduction`, `sediment_deduction`) VALUES
(3, 'QCT-000001', 21, '2026-01-13 23:19:11', 3.70, 0.1400, NULL, 4.0, 'clean', 1, 1.0000, 3.50, 8.50, 3.00, NULL, 0, 'APT test positive; Specific gravity below 1.025 (suspected adulteration)', 30.00, 0.00, 0.00, 0.00, 0.00, 1, 'BOOM!', '2026-01-13 15:19:11', '2026-01-13 15:19:11', 0.70, 12.50, 0.00, -0.5000, 25.0, 0.00, 0.00),
(4, 'QCT-000002', 20, '2026-01-13 23:25:05', 3.75, 0.1500, NULL, 4.0, 'clean', 1, 1.1000, 3.50, 8.50, 4.80, 'B', 1, NULL, 30.00, 0.00, 0.00, 30.00, 1500.00, 1, 'BOOM', '2026-01-13 15:25:05', '2026-01-18 08:46:39', 0.60, 12.50, 0.00, -0.5000, 25.0, 0.00, 0.00),
(5, 'QCT-000003', 23, '2026-01-18 15:28:25', 3.50, 0.1400, NULL, 4.0, 'clean', 2, 1.0270, NULL, NULL, NULL, NULL, 0, 'APT test positive', 30.00, 0.00, 0.00, 0.00, 0.00, 1, '', '2026-01-18 07:28:25', '2026-01-18 07:28:25', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(6, 'QCT-000004', 24, '2026-01-18 15:29:37', 3.75, 0.1800, NULL, 4.0, 'clean', 1, 1.0280, NULL, NULL, NULL, NULL, 0, 'APT test positive', 30.00, 0.00, 0.00, 0.00, 0.00, 1, 'boom', '2026-01-18 07:29:37', '2026-01-18 07:29:37', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(7, 'QCT-000005', 25, '2026-01-18 15:30:14', 3.75, 0.1600, NULL, 4.0, 'clean', 3, 1.0280, NULL, NULL, NULL, NULL, 0, 'APT test positive', 30.00, 0.00, 0.00, 0.00, 0.00, 1, '', '2026-01-18 07:30:14', '2026-01-18 07:30:14', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(8, 'QCT-000006', 26, '2026-01-18 15:47:25', 3.75, 0.1600, NULL, 4.0, 'clean', 2, 1.0280, NULL, NULL, NULL, NULL, 0, 'APT test positive', 30.00, 0.00, 0.00, 0.00, 0.00, 1, '', '2026-01-18 07:47:25', '2026-01-18 07:47:25', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(11, 'QCT-000007', 28, '2026-01-18 15:50:25', 3.75, 0.1600, NULL, 4.0, 'clean', 2, 1.0280, NULL, NULL, NULL, 'B', 1, NULL, 30.00, 0.00, 0.00, 29.50, 1475.00, 1, 'Basta', '2026-01-18 07:50:25', '2026-01-18 08:46:39', NULL, NULL, NULL, NULL, NULL, 0.00, 0.50),
(12, 'QCT-000008', 29, '2026-01-18 18:54:50', 3.80, 0.1600, NULL, 4.5, 'clean', 1, 1.0280, NULL, NULL, NULL, 'B', 1, NULL, 30.00, 0.00, 0.00, 30.00, 1500.00, 0, NULL, '2026-01-18 10:54:50', '2026-01-18 10:54:50', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(13, 'QCT-000009', 1, '2025-10-21 08:00:00', 4.30, 0.1400, NULL, 4.6, 'clean', 1, 1.0290, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 3146.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(14, 'QCT-000010', 2, '2025-10-21 08:00:00', 4.20, 0.1400, NULL, 4.5, 'clean', 1, 1.0310, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 1573.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(15, 'QCT-000011', 3, '2025-10-21 08:00:00', 3.90, 0.1400, NULL, 4.4, 'clean', 1, 1.0280, NULL, NULL, NULL, 'B', 1, NULL, 30.00, 0.00, 0.00, 30.00, 3450.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(16, 'QCT-000012', 4, '2025-10-21 08:00:00', 3.80, 0.1500, NULL, 4.8, 'clean', 1, 1.0290, NULL, NULL, NULL, 'B', 1, NULL, 30.00, 0.00, 0.00, 30.00, 3150.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(17, 'QCT-000013', 5, '2025-10-21 08:00:00', 3.90, 0.1400, NULL, 4.1, 'clean', 1, 1.0320, NULL, NULL, NULL, 'B', 1, NULL, 30.00, 0.00, 0.00, 30.00, 1500.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(18, 'QCT-000014', 6, '2025-10-21 08:00:00', 4.40, 0.1600, NULL, 4.2, 'clean', 1, 1.0300, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 3569.50, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(19, 'QCT-000015', 7, '2025-10-21 08:00:00', 4.40, 0.1600, NULL, 4.4, 'clean', 1, 1.0290, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 18089.50, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(20, 'QCT-000016', 9, '2025-10-21 08:00:00', 4.20, 0.1500, NULL, 5.0, 'clean', 1, 1.0310, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 5172.75, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(21, 'QCT-000017', 10, '2025-10-21 08:00:00', 4.60, 0.1500, NULL, 5.0, 'clean', 1, 1.0310, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.50, 0.00, 30.50, 2684.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(22, 'QCT-000018', 11, '2025-10-21 08:00:00', 4.00, 0.1500, NULL, 4.8, 'clean', 1, 1.0300, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.00, 0.00, 30.00, 660.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(23, 'QCT-000019', 12, '2025-10-21 08:00:00', 4.20, 0.1600, NULL, 4.9, 'clean', 1, 1.0320, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 3539.25, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(24, 'QCT-000020', 13, '2025-10-21 08:00:00', 4.20, 0.1400, NULL, 4.5, 'clean', 1, 1.0290, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 4446.75, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(25, 'QCT-000021', 14, '2025-10-21 08:00:00', 4.40, 0.1600, NULL, 4.6, 'clean', 1, 1.0320, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 4658.50, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(26, 'QCT-000022', 15, '2025-10-21 08:00:00', 4.20, 0.1600, NULL, 4.6, 'clean', 1, 1.0290, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 2057.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(27, 'QCT-000023', 16, '2025-10-21 08:00:00', 4.30, 0.1500, NULL, 4.1, 'clean', 1, 1.0300, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.25, 0.00, 30.25, 302.50, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(28, 'QCT-000024', 17, '2025-10-21 08:00:00', 4.00, 0.1600, NULL, 4.9, 'clean', 1, 1.0280, NULL, NULL, NULL, 'A', 1, NULL, 30.00, 0.00, 0.00, 30.00, 5820.00, 0, 'Auto-generated for data integrity fix', '2026-01-18 10:56:54', '2026-01-18 10:56:54', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00),
(31, 'QCT-000025', 32, '2026-01-18 19:04:22', 3.75, 0.1500, NULL, 4.0, 'clean', 2, 1.0280, NULL, NULL, NULL, 'B', 1, NULL, 30.00, 0.00, 0.00, 29.50, 1475.00, 1, '', '2026-01-18 11:04:22', '2026-01-18 11:04:22', NULL, NULL, NULL, NULL, NULL, 0.00, 0.50);

-- --------------------------------------------------------

--
-- Table structure for table `quality_standards`
--

CREATE TABLE `quality_standards` (
  `id` int(11) NOT NULL,
  `parameter_name` varchar(50) NOT NULL,
  `parameter_label` varchar(100) NOT NULL,
  `min_value` decimal(10,4) DEFAULT NULL,
  `max_value` decimal(10,4) DEFAULT NULL,
  `standard_value` decimal(10,4) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `rejection_threshold` decimal(10,4) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quality_standards`
--

INSERT INTO `quality_standards` (`id`, `parameter_name`, `parameter_label`, `min_value`, `max_value`, `standard_value`, `unit`, `rejection_threshold`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'titratable_acidity', 'Titratable Acidity', 0.1400, 0.1800, 0.1600, '%', 0.2500, 1, NULL, '2026-01-13 14:03:49', '2026-01-13 14:03:49'),
(2, 'fat_percentage', 'Butter Fat Content', 3.5000, 4.0000, 3.7500, '%', NULL, 1, NULL, '2026-01-13 14:03:49', '2026-01-13 14:03:49'),
(3, 'specific_gravity', 'Specific Gravity', 1.0250, 1.0320, 1.0280, '', 1.0250, 1, NULL, '2026-01-13 14:03:49', '2026-01-13 14:03:49'),
(4, 'temperature', 'Receiving Temperature', 0.0000, 8.0000, 4.0000, '°C', NULL, 1, NULL, '2026-01-13 14:03:49', '2026-01-13 14:03:49');

-- --------------------------------------------------------

--
-- Table structure for table `raw_milk_inventory`
--

CREATE TABLE `raw_milk_inventory` (
  `id` int(11) NOT NULL,
  `tank_number` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `volume_liters` decimal(10,2) NOT NULL,
  `received_date` date NOT NULL,
  `status` enum('available','in_production','depleted') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `raw_milk_inventory`
--

INSERT INTO `raw_milk_inventory` (`id`, `tank_number`, `delivery_id`, `volume_liters`, `received_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 55.00, '2025-10-21', 'in_production', '2026-01-13 10:22:56', '2026-01-13 10:40:31'),
(2, 1, 2, 17.00, '2025-10-21', 'in_production', '2026-01-13 10:22:56', '2026-01-13 11:09:32'),
(3, 1, 3, 20.00, '2025-10-21', 'in_production', '2026-01-13 10:22:56', '2026-01-13 11:09:32'),
(4, 1, 4, 80.00, '2025-10-21', 'in_production', '2026-01-13 10:22:56', '2026-01-18 12:46:09'),
(5, 1, 5, 59.00, '2025-10-21', 'in_production', '2026-01-13 10:22:56', '2026-01-18 12:46:09'),
(6, 1, 6, 40.00, '2025-10-21', 'in_production', '2026-01-13 10:22:56', '2026-01-18 12:46:09'),
(7, 1, 7, 598.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(8, 1, 8, 26.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(9, 1, 9, 124.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(10, 1, 10, 201.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(11, 1, 11, 8.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(12, 1, 12, 149.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(13, 1, 13, 42.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(14, 1, 14, 91.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(15, 1, 15, 173.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(16, 1, 16, 401.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(17, 1, 17, 102.00, '2025-10-21', 'available', '2026-01-13 10:22:56', '2026-01-13 10:22:56'),
(32, 1, 20, 50.00, '2026-01-13', 'available', '2026-01-13 15:25:05', '2026-01-13 15:25:05'),
(33, 1, 28, 50.00, '2026-01-18', 'available', '2026-01-18 07:50:25', '2026-01-18 07:50:25'),
(34, 1, 29, 50.00, '2026-01-18', 'available', '2026-01-18 10:54:50', '2026-01-18 10:54:50'),
(37, 1, 32, 50.00, '2026-01-18', 'available', '2026-01-18 11:04:22', '2026-01-18 11:04:22');

-- --------------------------------------------------------

--
-- Table structure for table `recipe_ingredients`
--

CREATE TABLE `recipe_ingredients` (
  `id` int(11) NOT NULL,
  `recipe_id` int(11) NOT NULL,
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

INSERT INTO `recipe_ingredients` (`id`, `recipe_id`, `ingredient_name`, `ingredient_category`, `quantity`, `unit`, `is_optional`, `notes`) VALUES
(1, 1, 'Raw Milk', 'milk', 100.000, 'liters', 0, NULL),
(2, 1, 'Cocoa Powder', 'powder', 2.500, 'kg', 0, NULL),
(3, 1, 'White Sugar', 'sugar', 8.000, 'kg', 0, NULL),
(4, 1, 'Milk Powder', 'powder', 1.500, 'kg', 0, NULL),
(5, 1, 'Bottles 330ml', 'packaging', 310.000, 'pcs', 0, NULL),
(6, 1, 'Bottle Caps', 'packaging', 310.000, 'pcs', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--

CREATE TABLE `requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `item_type` enum('raw_milk','ingredient','mro') DEFAULT 'ingredient',
  `item_id` int(11) DEFAULT 0,
  `item_code` varchar(30) DEFAULT '',
  `item_name` varchar(100) NOT NULL,
  `requested_quantity` decimal(10,2) DEFAULT 0.00,
  `issued_quantity` decimal(10,2) DEFAULT NULL,
  `unit_of_measure` varchar(20) DEFAULT 'units',
  `status` enum('pending','partial','fulfilled','cancelled') DEFAULT 'pending',
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'units',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_items`
--

INSERT INTO `requisition_items` (`id`, `requisition_id`, `item_type`, `item_id`, `item_code`, `item_name`, `requested_quantity`, `issued_quantity`, `unit_of_measure`, `status`, `fulfilled_by`, `fulfilled_at`, `quantity`, `unit`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'ingredient', 0, '', 'Sugar', 50.00, NULL, 'kg', 'pending', NULL, NULL, 50.000, 'kg', 'For sweetened milk production', '2026-01-18 10:26:40', '2026-01-18 10:26:41'),
(2, 1, 'ingredient', 0, '', 'Cocoa Powder', 10.00, NULL, 'kg', 'pending', NULL, NULL, 10.000, 'kg', 'For chocolate milk', '2026-01-18 10:26:40', '2026-01-18 10:26:41'),
(3, 1, 'ingredient', 0, '', 'PET Bottles 1L', 500.00, NULL, 'units', 'pending', NULL, NULL, 500.000, 'units', 'Packaging for batch', '2026-01-18 10:26:40', '2026-01-18 10:26:41'),
(10, 5, 'ingredient', 0, '', 'Basta', 10.00, NULL, 'units', 'pending', NULL, NULL, 50.000, 'liters', 'I love you', '2026-01-18 11:27:00', '2026-01-18 12:46:09'),
(11, 6, 'ingredient', 0, '', 'GATAS BOO', 10.00, NULL, 'units', 'pending', NULL, NULL, 500.000, 'liters', 'TabangLord', '2026-01-18 11:53:07', '2026-01-18 12:46:09'),
(12, 7, 'ingredient', 0, '', 'Raw Milk', 10.00, NULL, 'units', 'pending', NULL, NULL, 100.000, 'liters', 'Basta', '2026-01-18 11:53:54', '2026-01-18 12:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `sales_items`
--

CREATE TABLE `sales_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `fg_inventory_id` int(11) DEFAULT NULL COMMENT 'Link to finished_goods_inventory',
  `product_name` varchar(100) NOT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(20) DEFAULT 'pcs',
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_items`
--

INSERT INTO `sales_items` (`id`, `transaction_id`, `fg_inventory_id`, `product_name`, `variant`, `quantity`, `unit`, `unit_price`, `total_price`) VALUES
(1, 1, 1, 'Highland Yogurt', 'Plain', 1, 'cups', 30.00, 30.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) NOT NULL COMMENT 'e.g., SO-20260118-001',
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_type` enum('supermarket','school','feeding_program','restaurant','distributor','walk_in','other') NOT NULL DEFAULT 'other',
  `customer_po_number` varchar(50) DEFAULT NULL COMMENT 'Customer PO reference',
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `total_items` int(11) NOT NULL DEFAULT 0,
  `total_quantity` int(11) NOT NULL DEFAULT 0,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','pending','approved','preparing','partially_fulfilled','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  `priority` enum('normal','rush','urgent') NOT NULL DEFAULT 'normal',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `dr_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `quantity_fulfilled` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `status` enum('pending','partial','fulfilled','out_of_stock','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_transactions`
--

CREATE TABLE `sales_transactions` (
  `id` int(11) NOT NULL,
  `transaction_code` varchar(30) NOT NULL,
  `transaction_type` enum('cash','credit','csi') NOT NULL COMMENT 'cash=walk-in, credit=receivable, csi=charge sales invoice',
  `dr_id` int(11) DEFAULT NULL COMMENT 'Link to delivery_receipts for institutional',
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(200) DEFAULT NULL COMMENT 'For walk-in without customer record',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `amount_due` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('paid','partial','unpaid') DEFAULT 'unpaid',
  `payment_method` enum('cash','check','bank_transfer','gcash') DEFAULT 'cash',
  `check_bank` varchar(100) DEFAULT NULL,
  `check_number` varchar(50) DEFAULT NULL,
  `check_date` date DEFAULT NULL,
  `cashier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL COMMENT 'For credit sales',
  `paid_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_transactions`
--

INSERT INTO `sales_transactions` (`id`, `transaction_code`, `transaction_type`, `dr_id`, `customer_id`, `customer_name`, `subtotal`, `discount_percent`, `discount_amount`, `total_amount`, `amount_paid`, `amount_due`, `payment_status`, `payment_method`, `check_bank`, `check_number`, `check_date`, `cashier_id`, `created_at`, `due_date`, `paid_at`, `notes`) VALUES
(1, 'CS-20260113-3E63', 'cash', NULL, NULL, 'Walk-in Customer', 30.00, 0.00, 0.00, 30.00, 100.00, -70.00, 'paid', 'cash', NULL, NULL, NULL, 1, '2026-01-13 11:50:53', NULL, '2026-01-13 12:50:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `storage_tanks`
--

CREATE TABLE `storage_tanks` (
  `id` int(11) NOT NULL,
  `tank_code` varchar(20) NOT NULL COMMENT 'e.g., TANK-01, TANK-02',
  `tank_name` varchar(100) NOT NULL,
  `capacity_liters` decimal(10,2) NOT NULL COMMENT 'Maximum capacity',
  `current_volume` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current milk volume',
  `location` varchar(100) DEFAULT NULL COMMENT 'Physical location in facility',
  `tank_type` enum('primary','secondary','holding','chiller') NOT NULL DEFAULT 'primary',
  `temperature_celsius` decimal(4,1) DEFAULT NULL COMMENT 'Current temperature',
  `last_cleaned_at` datetime DEFAULT NULL,
  `status` enum('available','in_use','cleaning','maintenance','offline') NOT NULL DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `storage_tanks`
--

INSERT INTO `storage_tanks` (`id`, `tank_code`, `tank_name`, `capacity_liters`, `current_volume`, `location`, `tank_type`, `temperature_celsius`, `last_cleaned_at`, `status`, `is_active`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'TANK-01', 'Primary Storage Tank 1', 1000.00, 80.00, 'Receiving Area', 'primary', NULL, NULL, 'in_use', 1, NULL, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(2, 'TANK-02', 'Primary Storage Tank 2', 1000.00, 59.00, 'Receiving Area', 'primary', NULL, NULL, 'in_use', 1, NULL, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(3, 'TANK-03', 'Holding Tank', 500.00, 40.00, 'Pre-Production Area', 'holding', NULL, NULL, 'in_use', 1, NULL, '2026-01-17 09:26:04', '2026-01-18 12:46:09'),
(4, 'TANK-04', 'Chiller Tank', 500.00, 0.00, 'Cold Storage', 'chiller', NULL, NULL, 'available', 1, NULL, '2026-01-17 09:26:04', '2026-01-17 09:26:04');

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
(1, 'SUP-LPC', 'LPC', NULL, NULL, NULL, NULL, '30 days', 1, 'Bottles and Caps supplier', '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(2, 'SUP-IANGAO', 'IAN GAO', NULL, NULL, NULL, NULL, '30 days', 1, 'Sugar supplier', '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(3, 'SUP-ELIXIR', 'ELIXIR', NULL, NULL, NULL, NULL, '30 days', 1, 'Ribbons, Inks, Solvents, Equipment', '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(4, 'SUP-AYACOM', 'AYA COMMERC', NULL, NULL, NULL, NULL, '30 days', 1, 'Alternative sugar supplier', '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(5, 'SUP-ANCO', 'ANCO MERCHA', NULL, NULL, NULL, NULL, '30 days', 1, 'Caustic Soda supplier', '2026-01-18 13:59:33', '2026-01-18 13:59:33'),
(6, 'SUP-KALIN', 'KALINISAN', NULL, NULL, NULL, NULL, '30 days', 1, 'Cleaning chemicals supplier', '2026-01-18 13:59:33', '2026-01-18 13:59:33');

-- --------------------------------------------------------

--
-- Table structure for table `tank_milk_batches`
--

CREATE TABLE `tank_milk_batches` (
  `id` int(11) NOT NULL,
  `tank_id` int(11) NOT NULL,
  `raw_milk_inventory_id` int(11) NOT NULL COMMENT 'Links to raw_milk_inventory from QC',
  `volume_liters` decimal(10,2) NOT NULL,
  `remaining_liters` decimal(10,2) NOT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `received_by` int(11) NOT NULL,
  `status` enum('available','partially_used','consumed','expired','transferred') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tank_milk_batches`
--

INSERT INTO `tank_milk_batches` (`id`, `tank_id`, `raw_milk_inventory_id`, `volume_liters`, `remaining_liters`, `received_date`, `expiry_date`, `received_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 80.00, 80.00, '2026-01-18', '2026-01-21', 2, 'available', '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(2, 2, 5, 59.00, 59.00, '2026-01-18', '2026-01-21', 2, 'available', '2026-01-18 12:46:09', '2026-01-18 12:46:09'),
(3, 3, 6, 40.00, 40.00, '2026-01-18', '2026-01-21', 2, 'available', '2026-01-18 12:46:09', '2026-01-18 12:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('general_manager','qc_officer','production_staff','warehouse_raw','warehouse_fg','sales_custodian','cashier','purchaser','finance_officer','bookkeeper','maintenance_head') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `employee_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `email`, `is_active`, `created_at`, `updated_at`, `first_name`, `last_name`, `employee_id`) VALUES
(1, 'qc_officer', '$2y$12$tXvfXIcpbxo2h321DsW.Y.zAeQioBa.UhDGSXDRr9irDtRquo5/iG', 'QC Officer', 'qc_officer', NULL, 1, '2026-01-13 10:08:49', '2026-01-13 12:39:53', 'Maria', 'Santos', NULL),
(2, 'gm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'General Manager', 'general_manager', NULL, 1, '2026-01-13 10:08:49', '2026-01-13 12:39:53', 'System', 'Admin', NULL),
(3, 'production', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Production Staff', 'production_staff', NULL, 1, '2026-01-13 10:31:35', '2026-01-13 12:39:53', 'Production', 'Staff', NULL),
(4, 'warehouse_fg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Warehouse FG Custodian', 'warehouse_fg', NULL, 1, '2026-01-13 11:07:55', '2026-01-13 11:07:55', NULL, NULL, NULL),
(5, 'cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cashier', 'cashier', NULL, 1, '2026-01-13 11:44:18', '2026-01-13 11:44:18', NULL, NULL, NULL),
(6, 'sales_custodian', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sales Custodian', 'sales_custodian', NULL, 1, '2026-01-13 11:44:18', '2026-01-13 11:44:18', NULL, NULL, NULL),
(7, 'production_staff', '$2y$10$Y6ybW903K1p3K7h8ucWJFOO2kZgCV4Q.TUpHH7XbKWtanMVKZSx4e', 'Juan Dela Cruz', 'production_staff', NULL, 1, '2026-01-13 13:01:15', '2026-01-13 13:01:15', 'Juan', 'Dela Cruz', NULL),
(8, 'warehouse_raw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'warehouse_raw', 'warehouse.raw@highlandfresh.com', 1, '2026-01-17 09:26:04', '2026-01-17 09:26:04', 'Carlos', 'Mendoza', 'EMP-003');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_box_opening_history`
-- (See below for the actual view)
--
CREATE TABLE `vw_box_opening_history` (
`id` int(11)
,`opening_code` varchar(30)
,`inventory_id` int(11)
,`inventory_barcode` varchar(50)
,`product_name` varchar(100)
,`product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar')
,`product_variant` varchar(100)
,`boxes_opened` int(11)
,`pieces_from_opening` int(11)
,`box_unit` varchar(5)
,`base_unit` varchar(6)
,`reason` enum('partial_sale','sampling','quality_check','damage','other')
,`reference_type` varchar(50)
,`reference_id` int(11)
,`opened_by` int(11)
,`opened_by_name` varchar(201)
,`opened_at` datetime
,`notes` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_inventory_multi_unit`
-- (See below for the actual view)
--
CREATE TABLE `vw_inventory_multi_unit` (
`id` int(11)
,`batch_id` int(11)
,`product_name` varchar(100)
,`product_type` enum('bottled_milk','cheese','butter','yogurt','milk_bar')
,`variant` varchar(100)
,`size_ml` int(11)
,`unit` varchar(20)
,`base_unit` varchar(6)
,`box_unit` varchar(5)
,`pieces_per_box` int(2)
,`quantity_boxes` int(11)
,`quantity_pieces` int(11)
,`boxes_available` int(11)
,`pieces_available` int(11)
,`quantity_display` varchar(40)
,`available_display` varchar(40)
,`total_pieces` bigint(13)
,`total_pieces_available` bigint(13)
,`quantity` int(11)
,`quantity_available` int(11)
,`remaining_quantity` int(11)
,`manufacturing_date` date
,`expiry_date` date
,`days_until_expiry` int(7)
,`barcode` varchar(50)
,`chiller_id` int(11)
,`chiller_location` varchar(50)
,`status` enum('available','low_stock','reserved','dispatched','expired')
,`received_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_product_units_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_product_units_summary` (
`product_id` int(11)
,`product_code` varchar(50)
,`product_name` varchar(100)
,`variant` varchar(50)
,`category` enum('pasteurized_milk','flavored_milk','yogurt','cheese','butter','cream')
,`unit_size` decimal(10,2)
,`unit_measure` varchar(20)
,`base_unit` varchar(20)
,`box_unit` varchar(20)
,`pieces_per_box` int(11)
,`conversion_display` varchar(58)
);

-- --------------------------------------------------------

--
-- Table structure for table `yogurt_transformations`
--

CREATE TABLE `yogurt_transformations` (
  `id` int(11) NOT NULL,
  `transformation_code` varchar(30) NOT NULL,
  `source_inventory_id` int(11) NOT NULL,
  `source_quantity` int(11) NOT NULL,
  `source_volume_liters` decimal(10,2) DEFAULT NULL,
  `target_product` varchar(100) NOT NULL DEFAULT 'Yogurt',
  `target_quantity` int(11) NOT NULL,
  `transformation_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_datetime` datetime DEFAULT NULL,
  `safety_verified` tinyint(1) DEFAULT 0,
  `production_run_id` int(11) DEFAULT NULL,
  `target_recipe_id` int(11) DEFAULT NULL,
  `initiated_by` int(11) NOT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `vw_box_opening_history`
--
DROP TABLE IF EXISTS `vw_box_opening_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_box_opening_history`  AS SELECT `bol`.`id` AS `id`, `bol`.`opening_code` AS `opening_code`, `bol`.`inventory_id` AS `inventory_id`, `fgi`.`barcode` AS `inventory_barcode`, `fgi`.`product_name` AS `product_name`, `fgi`.`product_type` AS `product_type`, `fgi`.`product_variant` AS `product_variant`, `bol`.`boxes_opened` AS `boxes_opened`, `bol`.`pieces_from_opening` AS `pieces_from_opening`, CASE WHEN `fgi`.`product_type` = 'milk_bar' THEN 'box' WHEN `fgi`.`product_type` = 'bottled_milk' THEN 'crate' WHEN `fgi`.`product_type` = 'yogurt' THEN 'tray' WHEN `fgi`.`product_type` = 'butter' THEN 'case' WHEN `fgi`.`product_type` = 'cheese' THEN 'box' ELSE 'box' END AS `box_unit`, CASE WHEN `fgi`.`product_type` = 'milk_bar' THEN 'bar' WHEN `fgi`.`product_type` = 'bottled_milk' THEN 'bottle' WHEN `fgi`.`product_type` = 'yogurt' THEN 'cup' WHEN `fgi`.`product_type` = 'butter' THEN 'pack' WHEN `fgi`.`product_type` = 'cheese' THEN 'pack' ELSE 'piece' END AS `base_unit`, `bol`.`reason` AS `reason`, `bol`.`reference_type` AS `reference_type`, `bol`.`reference_id` AS `reference_id`, `bol`.`opened_by` AS `opened_by`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `opened_by_name`, `bol`.`opened_at` AS `opened_at`, `bol`.`notes` AS `notes`, `bol`.`created_at` AS `created_at` FROM ((`box_opening_log` `bol` left join `finished_goods_inventory` `fgi` on(`bol`.`inventory_id` = `fgi`.`id`)) left join `users` `u` on(`bol`.`opened_by` = `u`.`id`)) ORDER BY `bol`.`opened_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_inventory_multi_unit`
--
DROP TABLE IF EXISTS `vw_inventory_multi_unit`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_inventory_multi_unit`  AS SELECT `fgi`.`id` AS `id`, `fgi`.`batch_id` AS `batch_id`, `fgi`.`product_name` AS `product_name`, `fgi`.`product_type` AS `product_type`, `fgi`.`product_variant` AS `variant`, `fgi`.`size_ml` AS `size_ml`, `fgi`.`unit` AS `unit`, CASE WHEN `fgi`.`product_type` = 'milk_bar' THEN 'bar' WHEN `fgi`.`product_type` = 'bottled_milk' THEN 'bottle' WHEN `fgi`.`product_type` = 'yogurt' THEN 'cup' WHEN `fgi`.`product_type` = 'butter' THEN 'pack' WHEN `fgi`.`product_type` = 'cheese' THEN 'pack' ELSE 'piece' END AS `base_unit`, CASE WHEN `fgi`.`product_type` = 'milk_bar' THEN 'box' WHEN `fgi`.`product_type` = 'bottled_milk' THEN 'crate' WHEN `fgi`.`product_type` = 'yogurt' THEN 'tray' WHEN `fgi`.`product_type` = 'butter' THEN 'case' WHEN `fgi`.`product_type` = 'cheese' THEN 'box' ELSE 'box' END AS `box_unit`, CASE WHEN `fgi`.`product_type` = 'milk_bar' THEN 50 WHEN `fgi`.`product_type` = 'bottled_milk' THEN 24 WHEN `fgi`.`product_type` = 'yogurt' THEN 12 WHEN `fgi`.`product_type` = 'butter' THEN 20 WHEN `fgi`.`product_type` = 'cheese' THEN 10 ELSE 1 END AS `pieces_per_box`, `fgi`.`quantity_boxes` AS `quantity_boxes`, `fgi`.`quantity_pieces` AS `quantity_pieces`, `fgi`.`boxes_available` AS `boxes_available`, `fgi`.`pieces_available` AS `pieces_available`, concat(`fgi`.`quantity_boxes`,' ',case when `fgi`.`product_type` = 'milk_bar' then if(`fgi`.`quantity_boxes` <> 1,'boxes','box') when `fgi`.`product_type` = 'bottled_milk' then if(`fgi`.`quantity_boxes` <> 1,'crates','crate') when `fgi`.`product_type` = 'yogurt' then if(`fgi`.`quantity_boxes` <> 1,'trays','tray') when `fgi`.`product_type` = 'butter' then if(`fgi`.`quantity_boxes` <> 1,'cases','case') when `fgi`.`product_type` = 'cheese' then if(`fgi`.`quantity_boxes` <> 1,'boxes','box') else if(`fgi`.`quantity_boxes` <> 1,'boxes','box') end,' + ',`fgi`.`quantity_pieces`,' ',case when `fgi`.`product_type` = 'milk_bar' then if(`fgi`.`quantity_pieces` <> 1,'bars','bar') when `fgi`.`product_type` = 'bottled_milk' then if(`fgi`.`quantity_pieces` <> 1,'bottles','bottle') when `fgi`.`product_type` = 'yogurt' then if(`fgi`.`quantity_pieces` <> 1,'cups','cup') when `fgi`.`product_type` = 'butter' then if(`fgi`.`quantity_pieces` <> 1,'packs','pack') when `fgi`.`product_type` = 'cheese' then if(`fgi`.`quantity_pieces` <> 1,'packs','pack') else if(`fgi`.`quantity_pieces` <> 1,'pieces','piece') end) AS `quantity_display`, concat(`fgi`.`boxes_available`,' ',case when `fgi`.`product_type` = 'milk_bar' then if(`fgi`.`boxes_available` <> 1,'boxes','box') when `fgi`.`product_type` = 'bottled_milk' then if(`fgi`.`boxes_available` <> 1,'crates','crate') when `fgi`.`product_type` = 'yogurt' then if(`fgi`.`boxes_available` <> 1,'trays','tray') when `fgi`.`product_type` = 'butter' then if(`fgi`.`boxes_available` <> 1,'cases','case') when `fgi`.`product_type` = 'cheese' then if(`fgi`.`boxes_available` <> 1,'boxes','box') else if(`fgi`.`boxes_available` <> 1,'boxes','box') end,' + ',`fgi`.`pieces_available`,' ',case when `fgi`.`product_type` = 'milk_bar' then if(`fgi`.`pieces_available` <> 1,'bars','bar') when `fgi`.`product_type` = 'bottled_milk' then if(`fgi`.`pieces_available` <> 1,'bottles','bottle') when `fgi`.`product_type` = 'yogurt' then if(`fgi`.`pieces_available` <> 1,'cups','cup') when `fgi`.`product_type` = 'butter' then if(`fgi`.`pieces_available` <> 1,'packs','pack') when `fgi`.`product_type` = 'cheese' then if(`fgi`.`pieces_available` <> 1,'packs','pack') else if(`fgi`.`pieces_available` <> 1,'pieces','piece') end) AS `available_display`, `fgi`.`quantity_boxes`* CASE WHEN `fgi`.`product_type` = 'milk_bar' THEN 50 WHEN `fgi`.`product_type` = 'bottled_milk' THEN 24 WHEN `fgi`.`product_type` = 'yogurt' THEN 12 WHEN `fgi`.`product_type` = 'butter' THEN 20 WHEN `fgi`.`product_type` = 'cheese' THEN 10 ELSE 1 END+ `fgi`.`quantity_pieces` AS `total_pieces`, `fgi`.`boxes_available`* CASE WHEN `fgi`.`product_type` = 'milk_bar' THEN 50 WHEN `fgi`.`product_type` = 'bottled_milk' THEN 24 WHEN `fgi`.`product_type` = 'yogurt' THEN 12 WHEN `fgi`.`product_type` = 'butter' THEN 20 WHEN `fgi`.`product_type` = 'cheese' THEN 10 ELSE 1 END+ `fgi`.`pieces_available` AS `total_pieces_available`, `fgi`.`quantity` AS `quantity`, `fgi`.`quantity_available` AS `quantity_available`, `fgi`.`remaining_quantity` AS `remaining_quantity`, `fgi`.`manufacturing_date` AS `manufacturing_date`, `fgi`.`expiry_date` AS `expiry_date`, to_days(`fgi`.`expiry_date`) - to_days(curdate()) AS `days_until_expiry`, `fgi`.`barcode` AS `barcode`, `fgi`.`chiller_id` AS `chiller_id`, `fgi`.`chiller_location` AS `chiller_location`, `fgi`.`status` AS `status`, `fgi`.`received_at` AS `received_at` FROM `finished_goods_inventory` AS `fgi` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_product_units_summary`
--
DROP TABLE IF EXISTS `vw_product_units_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_product_units_summary`  AS SELECT `p`.`id` AS `product_id`, `p`.`product_code` AS `product_code`, `p`.`product_name` AS `product_name`, `p`.`variant` AS `variant`, `p`.`category` AS `category`, `p`.`unit_size` AS `unit_size`, `p`.`unit_measure` AS `unit_measure`, coalesce(`pu`.`base_unit`,`p`.`base_unit`,'piece') AS `base_unit`, coalesce(`pu`.`box_unit`,`p`.`box_unit`,'box') AS `box_unit`, coalesce(`pu`.`pieces_per_box`,`p`.`pieces_per_box`,1) AS `pieces_per_box`, concat('1 ',coalesce(`pu`.`box_unit`,`p`.`box_unit`,'box'),' = ',coalesce(`pu`.`pieces_per_box`,`p`.`pieces_per_box`,1),' ',coalesce(`pu`.`base_unit`,`p`.`base_unit`,'piece'),'s') AS `conversion_display` FROM (`products` `p` left join `product_units` `pu` on(`p`.`id` = `pu`.`product_id`)) WHERE `p`.`is_active` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `batch_ccp_logs`
--
ALTER TABLE `batch_ccp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_ccp_type` (`ccp_type`);

--
-- Indexes for table `batch_releases`
--
ALTER TABLE `batch_releases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `release_code` (`release_code`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_status` (`overall_status`);

--
-- Indexes for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `opening_code` (`opening_code`),
  ADD KEY `idx_opening_code` (`opening_code`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_opened_at` (`opened_at`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `opened_by` (`opened_by`);

--
-- Indexes for table `ccp_logs`
--
ALTER TABLE `ccp_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `log_code` (`log_code`),
  ADD KEY `idx_ccp_point` (`ccp_point`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_check_date` (`check_datetime`);

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
  ADD KEY `idx_chiller` (`chiller_id`),
  ADD KEY `idx_logged_at` (`logged_at`),
  ADD KEY `logged_by` (`logged_by`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`customer_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `delivery_receipts`
--
ALTER TABLE `delivery_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dr_number` (`dr_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `dispatched_by` (`dispatched_by`),
  ADD KEY `idx_dr_number` (`dr_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment` (`payment_status`);

--
-- Indexes for table `delivery_receipt_items`
--
ALTER TABLE `delivery_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dr` (`dr_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `dispatch_items`
--
ALTER TABLE `dispatch_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dr_id` (`dr_id`),
  ADD KEY `fg_inventory_id` (`fg_inventory_id`);

--
-- Indexes for table `employee_credits`
--
ALTER TABLE `employee_credits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `farmers`
--
ALTER TABLE `farmers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `farmer_code` (`farmer_code`);

--
-- Indexes for table `fg_dispatch_log`
--
ALTER TABLE `fg_dispatch_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dispatch_code` (`dispatch_code`),
  ADD KEY `idx_dispatch_code` (`dispatch_code`),
  ADD KEY `idx_dr` (`dr_id`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_released_at` (`released_at`),
  ADD KEY `idx_chiller` (`chiller_id`),
  ADD KEY `dr_item_id` (`dr_item_id`),
  ADD KEY `released_by` (`released_by`);

--
-- Indexes for table `fg_inventory_transactions`
--
ALTER TABLE `fg_inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `idx_transaction_code` (`transaction_code`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_inventory` (`inventory_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `from_chiller_id` (`from_chiller_id`),
  ADD KEY `to_chiller_id` (`to_chiller_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `fg_receiving`
--
ALTER TABLE `fg_receiving`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receiving_code` (`receiving_code`),
  ADD KEY `idx_receiving_code` (`receiving_code`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_chiller` (`chiller_id`),
  ADD KEY `idx_received_at` (`received_at`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `finished_goods_inventory`
--
ALTER TABLE `finished_goods_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_barcode` (`barcode`),
  ADD KEY `fk_fg_inventory_chiller` (`chiller_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `grading_standards`
--
ALTER TABLE `grading_standards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ingredient_code` (`ingredient_code`),
  ADD KEY `idx_ingredient_code` (`ingredient_code`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_low_stock` (`current_stock`,`minimum_stock`);

--
-- Indexes for table `ingredient_batches`
--
ALTER TABLE `ingredient_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `idx_batch_code` (`batch_code`),
  ADD KEY `idx_ingredient` (`ingredient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `idx_fifo` (`received_date`,`id`),
  ADD KEY `received_by` (`received_by`);

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
  ADD KEY `idx_run` (`run_id`);

--
-- Indexes for table `ingredient_requisitions`
--
ALTER TABLE `ingredient_requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requisition_code` (`requisition_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_production_run` (`production_run_id`);

--
-- Indexes for table `ingredient_requisition_items`
--
ALTER TABLE `ingredient_requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisition_id` (`requisition_id`);

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
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `master_recipes`
--
ALTER TABLE `master_recipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `recipe_code` (`recipe_code`);

--
-- Indexes for table `milk_deliveries`
--
ALTER TABLE `milk_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_code` (`delivery_code`),
  ADD KEY `idx_farmer` (`farmer_id`),
  ADD KEY `idx_date` (`delivery_date`),
  ADD KEY `idx_rmr` (`rmr_number`);

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
  ADD KEY `idx_batch_code` (`batch_code`),
  ADD KEY `idx_mro_item` (`mro_item_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_fifo` (`received_date`,`id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `mro_items`
--
ALTER TABLE `mro_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_low_stock` (`current_stock`,`minimum_stock`),
  ADD KEY `idx_critical` (`is_critical`);

--
-- Indexes for table `production_batches`
--
ALTER TABLE `production_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_code` (`batch_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `released_by` (`released_by`),
  ADD KEY `recipe_id` (`recipe_id`),
  ADD KEY `idx_run_id` (`run_id`);

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
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `production_logs`
--
ALTER TABLE `production_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `logged_by` (`logged_by`);

--
-- Indexes for table `production_runs`
--
ALTER TABLE `production_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `run_code` (`run_code`);

--
-- Indexes for table `production_run_milk_usage`
--
ALTER TABLE `production_run_milk_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_run` (`run_id`),
  ADD KEY `idx_delivery` (`delivery_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `product_returns`
--
ALTER TABLE `product_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_code` (`return_code`),
  ADD KEY `idx_return_code` (`return_code`),
  ADD KEY `idx_dr` (`dr_id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_return_date` (`return_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `product_return_items`
--
ALTER TABLE `product_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_return` (`return_id`),
  ADD KEY `idx_inventory` (`inventory_id`);

--
-- Indexes for table `product_units`
--
ALTER TABLE `product_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_product_unit` (`product_id`),
  ADD KEY `idx_base_unit` (`base_unit`),
  ADD KEY `idx_box_unit` (`box_unit`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_po_date` (`order_date`),
  ADD KEY `idx_po_status` (`status`),
  ADD KEY `idx_po_supplier` (`supplier_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ingredient_id` (`ingredient_id`),
  ADD KEY `idx_poi_po` (`po_id`);

--
-- Indexes for table `qc_batch_release`
--
ALTER TABLE `qc_batch_release`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch` (`batch_id`),
  ADD KEY `idx_qc_officer` (`qc_officer_id`),
  ADD KEY `idx_release_date` (`release_datetime`);

--
-- Indexes for table `qc_milk_tests`
--
ALTER TABLE `qc_milk_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_code` (`test_code`),
  ADD KEY `idx_delivery` (`delivery_id`),
  ADD KEY `idx_grade` (`grade`),
  ADD KEY `idx_test_date` (`test_datetime`);

--
-- Indexes for table `quality_standards`
--
ALTER TABLE `quality_standards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `raw_milk_inventory`
--
ALTER TABLE `raw_milk_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`);

--
-- Indexes for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipe` (`recipe_id`);

--
-- Indexes for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requisition` (`requisition_id`);

--
-- Indexes for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `fg_inventory_id` (`fg_inventory_id`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_delivery_date` (`delivery_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `dr_id` (`dr_id`);

--
-- Indexes for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `dr_id` (`dr_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `cashier_id` (`cashier_id`),
  ADD KEY `idx_code` (`transaction_code`),
  ADD KEY `idx_type` (`transaction_type`),
  ADD KEY `idx_status` (`payment_status`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `storage_tanks`
--
ALTER TABLE `storage_tanks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tank_code` (`tank_code`),
  ADD KEY `idx_tank_code` (`tank_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tank_type` (`tank_type`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`);

--
-- Indexes for table `tank_milk_batches`
--
ALTER TABLE `tank_milk_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tank` (`tank_id`),
  ADD KEY `idx_raw_milk` (`raw_milk_inventory_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `idx_fifo` (`received_date`,`id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `yogurt_transformations`
--
ALTER TABLE `yogurt_transformations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transformation_code` (`transformation_code`),
  ADD KEY `idx_source` (`source_inventory_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_production_run` (`production_run_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `batch_ccp_logs`
--
ALTER TABLE `batch_ccp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_releases`
--
ALTER TABLE `batch_releases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ccp_logs`
--
ALTER TABLE `ccp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chiller_locations`
--
ALTER TABLE `chiller_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chiller_temperature_logs`
--
ALTER TABLE `chiller_temperature_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `delivery_receipts`
--
ALTER TABLE `delivery_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_receipt_items`
--
ALTER TABLE `delivery_receipt_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispatch_items`
--
ALTER TABLE `dispatch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee_credits`
--
ALTER TABLE `employee_credits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `farmers`
--
ALTER TABLE `farmers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `fg_dispatch_log`
--
ALTER TABLE `fg_dispatch_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fg_inventory_transactions`
--
ALTER TABLE `fg_inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fg_receiving`
--
ALTER TABLE `fg_receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finished_goods_inventory`
--
ALTER TABLE `finished_goods_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `grading_standards`
--
ALTER TABLE `grading_standards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `ingredient_batches`
--
ALTER TABLE `ingredient_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ingredient_categories`
--
ALTER TABLE `ingredient_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ingredient_consumption`
--
ALTER TABLE `ingredient_consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredient_requisitions`
--
ALTER TABLE `ingredient_requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ingredient_requisition_items`
--
ALTER TABLE `ingredient_requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `master_recipes`
--
ALTER TABLE `master_recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `milk_deliveries`
--
ALTER TABLE `milk_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `production_batches`
--
ALTER TABLE `production_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `production_byproducts`
--
ALTER TABLE `production_byproducts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `production_ccp_logs`
--
ALTER TABLE `production_ccp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `production_logs`
--
ALTER TABLE `production_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `production_runs`
--
ALTER TABLE `production_runs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `production_run_milk_usage`
--
ALTER TABLE `production_run_milk_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_returns`
--
ALTER TABLE `product_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_return_items`
--
ALTER TABLE `product_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_units`
--
ALTER TABLE `product_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `qc_batch_release`
--
ALTER TABLE `qc_batch_release`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qc_milk_tests`
--
ALTER TABLE `qc_milk_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `quality_standards`
--
ALTER TABLE `quality_standards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `raw_milk_inventory`
--
ALTER TABLE `raw_milk_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `sales_items`
--
ALTER TABLE `sales_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `storage_tanks`
--
ALTER TABLE `storage_tanks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tank_milk_batches`
--
ALTER TABLE `tank_milk_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `yogurt_transformations`
--
ALTER TABLE `yogurt_transformations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batch_ccp_logs`
--
ALTER TABLE `batch_ccp_logs`
  ADD CONSTRAINT `batch_ccp_logs_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `box_opening_log`
--
ALTER TABLE `box_opening_log`
  ADD CONSTRAINT `box_opening_log_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `box_opening_log_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `box_opening_log_ibfk_3` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `chiller_temperature_logs`
--
ALTER TABLE `chiller_temperature_logs`
  ADD CONSTRAINT `chiller_temperature_logs_ibfk_1` FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chiller_temperature_logs_ibfk_2` FOREIGN KEY (`logged_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `delivery_receipts`
--
ALTER TABLE `delivery_receipts`
  ADD CONSTRAINT `delivery_receipts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `delivery_receipts_ibfk_2` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `delivery_receipt_items`
--
ALTER TABLE `delivery_receipt_items`
  ADD CONSTRAINT `delivery_receipt_items_ibfk_1` FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_receipt_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `delivery_receipt_items_ibfk_3` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`);

--
-- Constraints for table `dispatch_items`
--
ALTER TABLE `dispatch_items`
  ADD CONSTRAINT `dispatch_items_ibfk_1` FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispatch_items_ibfk_2` FOREIGN KEY (`fg_inventory_id`) REFERENCES `finished_goods_inventory` (`id`);

--
-- Constraints for table `employee_credits`
--
ALTER TABLE `employee_credits`
  ADD CONSTRAINT `employee_credits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `employee_credits_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`);

--
-- Constraints for table `fg_dispatch_log`
--
ALTER TABLE `fg_dispatch_log`
  ADD CONSTRAINT `fg_dispatch_log_ibfk_1` FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts` (`id`),
  ADD CONSTRAINT `fg_dispatch_log_ibfk_2` FOREIGN KEY (`dr_item_id`) REFERENCES `delivery_receipt_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fg_dispatch_log_ibfk_3` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `fg_dispatch_log_ibfk_4` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fg_dispatch_log_ibfk_5` FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fg_dispatch_log_ibfk_6` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fg_inventory_transactions`
--
ALTER TABLE `fg_inventory_transactions`
  ADD CONSTRAINT `fg_inventory_transactions_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`),
  ADD CONSTRAINT `fg_inventory_transactions_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fg_inventory_transactions_ibfk_3` FOREIGN KEY (`from_chiller_id`) REFERENCES `chiller_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fg_inventory_transactions_ibfk_4` FOREIGN KEY (`to_chiller_id`) REFERENCES `chiller_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fg_inventory_transactions_ibfk_5` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fg_receiving`
--
ALTER TABLE `fg_receiving`
  ADD CONSTRAINT `fg_receiving_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`),
  ADD CONSTRAINT `fg_receiving_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fg_receiving_ibfk_3` FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations` (`id`),
  ADD CONSTRAINT `fg_receiving_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `finished_goods_inventory`
--
ALTER TABLE `finished_goods_inventory`
  ADD CONSTRAINT `finished_goods_inventory_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`),
  ADD CONSTRAINT `finished_goods_inventory_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_fg_inventory_chiller` FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD CONSTRAINT `ingredients_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `ingredient_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ingredient_batches`
--
ALTER TABLE `ingredient_batches`
  ADD CONSTRAINT `ingredient_batches_ibfk_1` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `ingredient_batches_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `ingredient_requisition_items`
--
ALTER TABLE `ingredient_requisition_items`
  ADD CONSTRAINT `ingredient_requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `ingredient_requisitions` (`id`);

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `mro_inventory`
--
ALTER TABLE `mro_inventory`
  ADD CONSTRAINT `mro_inventory_ibfk_1` FOREIGN KEY (`mro_item_id`) REFERENCES `mro_items` (`id`),
  ADD CONSTRAINT `mro_inventory_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `mro_items`
--
ALTER TABLE `mro_items`
  ADD CONSTRAINT `mro_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `mro_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `production_batches`
--
ALTER TABLE `production_batches`
  ADD CONSTRAINT `production_batches_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `production_batches_ibfk_2` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `production_batches_ibfk_3` FOREIGN KEY (`recipe_id`) REFERENCES `master_recipes` (`id`);

--
-- Constraints for table `production_logs`
--
ALTER TABLE `production_logs`
  ADD CONSTRAINT `production_logs_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`),
  ADD CONSTRAINT `production_logs_ibfk_2` FOREIGN KEY (`logged_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `product_returns`
--
ALTER TABLE `product_returns`
  ADD CONSTRAINT `product_returns_ibfk_1` FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_returns_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_returns_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `product_returns_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_return_items`
--
ALTER TABLE `product_return_items`
  ADD CONSTRAINT `product_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `product_returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_return_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_units`
--
ALTER TABLE `product_units`
  ADD CONSTRAINT `product_units_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`);

--
-- Constraints for table `qc_batch_release`
--
ALTER TABLE `qc_batch_release`
  ADD CONSTRAINT `qc_batch_release_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `production_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qc_batch_release_ibfk_2` FOREIGN KEY (`qc_officer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `raw_milk_inventory`
--
ALTER TABLE `raw_milk_inventory`
  ADD CONSTRAINT `raw_milk_inventory_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `milk_deliveries` (`id`);

--
-- Constraints for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD CONSTRAINT `sales_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_items_ibfk_2` FOREIGN KEY (`fg_inventory_id`) REFERENCES `finished_goods_inventory` (`id`);

--
-- Constraints for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `sales_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_orders_ibfk_5` FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD CONSTRAINT `sales_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD CONSTRAINT `sales_transactions_ibfk_1` FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts` (`id`),
  ADD CONSTRAINT `sales_transactions_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_transactions_ibfk_3` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tank_milk_batches`
--
ALTER TABLE `tank_milk_batches`
  ADD CONSTRAINT `tank_milk_batches_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `storage_tanks` (`id`),
  ADD CONSTRAINT `tank_milk_batches_ibfk_2` FOREIGN KEY (`raw_milk_inventory_id`) REFERENCES `raw_milk_inventory` (`id`),
  ADD CONSTRAINT `tank_milk_batches_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
