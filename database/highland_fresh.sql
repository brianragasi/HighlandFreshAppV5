-- =====================================================
-- Highland Fresh System - Database Schema
-- Module: Quality Control (Foundation)
-- Version: 4.0
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+08:00";

-- =====================================================
-- DATABASE CREATION
-- =====================================================
CREATE DATABASE IF NOT EXISTS `highland_fresh` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `highland_fresh`;

-- =====================================================
-- TABLE: users (All System Users)
-- =====================================================
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` VARCHAR(20) NOT NULL UNIQUE,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NULL,
    `role` ENUM('general_manager', 'qc_officer', 'production_staff', 'warehouse_raw', 'warehouse_fg', 'sales_custodian', 'cashier', 'purchaser', 'finance_officer', 'bookkeeper', 'maintenance_head') NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_role` (`role`),
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: farmers (Milk Suppliers)
-- =====================================================
CREATE TABLE `farmers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `farmer_code` VARCHAR(20) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `contact_number` VARCHAR(20) NULL,
    `address` TEXT NULL,
    `membership_type` ENUM('member', 'non_member') NOT NULL DEFAULT 'non_member',
    `base_price_per_liter` DECIMAL(10,2) NOT NULL DEFAULT 40.00 COMMENT 'Member=40, Non-member=38',
    `bank_name` VARCHAR(100) NULL,
    `bank_account_number` VARCHAR(50) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_farmer_code` (`farmer_code`),
    INDEX `idx_membership` (`membership_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: quality_standards (Reference Table)
-- =====================================================
CREATE TABLE `quality_standards` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `parameter_name` VARCHAR(50) NOT NULL,
    `min_value` DECIMAL(10,4) NULL,
    `max_value` DECIMAL(10,4) NULL,
    `standard_value` DECIMAL(10,4) NULL,
    `unit` VARCHAR(20) NOT NULL,
    `incentive_per_unit` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Extra per liter if exceeds standard',
    `deduction_per_unit` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Deduction per liter if below standard',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: milk_deliveries (Inbound Raw Milk)
-- =====================================================
CREATE TABLE `milk_deliveries` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `delivery_code` VARCHAR(30) NOT NULL UNIQUE,
    `farmer_id` INT(11) NOT NULL,
    `delivery_date` DATE NOT NULL,
    `delivery_time` TIME NOT NULL,
    `volume_liters` DECIMAL(10,2) NOT NULL,
    `received_by` INT(11) NOT NULL COMMENT 'QC Officer user_id',
    `status` ENUM('pending_test', 'accepted', 'rejected', 'partial') NOT NULL DEFAULT 'pending_test',
    `rejection_reason` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_delivery_code` (`delivery_code`),
    INDEX `idx_farmer` (`farmer_id`),
    INDEX `idx_date` (`delivery_date`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`farmer_id`) REFERENCES `farmers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: qc_milk_tests (Milk Analyzer Results)
-- =====================================================
CREATE TABLE `qc_milk_tests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `test_code` VARCHAR(30) NOT NULL UNIQUE,
    `delivery_id` INT(11) NOT NULL,
    `tested_by` INT(11) NOT NULL COMMENT 'QC Officer user_id',
    `test_datetime` DATETIME NOT NULL,
    
    -- Milk Analyzer Parameters
    `fat_percentage` DECIMAL(5,2) NOT NULL COMMENT 'Standard: 3.5-4.0%',
    `acidity_ph` DECIMAL(4,2) NOT NULL COMMENT 'pH level',
    `temperature_celsius` DECIMAL(4,1) NOT NULL COMMENT 'Should be cold',
    `sediment_level` ENUM('none', 'trace', 'light', 'moderate', 'heavy') NOT NULL DEFAULT 'none',
    `density` DECIMAL(6,4) NULL COMMENT 'g/ml',
    `protein_percentage` DECIMAL(5,2) NULL,
    `lactose_percentage` DECIMAL(5,2) NULL,
    `snf_percentage` DECIMAL(5,2) NULL COMMENT 'Solids-Not-Fat',
    
    -- Calculated Pricing
    `base_price_per_liter` DECIMAL(10,2) NOT NULL,
    `quality_incentive` DECIMAL(10,2) DEFAULT 0.00,
    `quality_deduction` DECIMAL(10,2) DEFAULT 0.00,
    `final_price_per_liter` DECIMAL(10,2) NOT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL,
    
    -- Grading Result
    `grade` ENUM('A', 'B', 'C', 'D', 'Rejected') NOT NULL,
    `is_accepted` TINYINT(1) NOT NULL DEFAULT 1,
    `rejection_reason` TEXT NULL,
    
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_test_code` (`test_code`),
    INDEX `idx_delivery` (`delivery_id`),
    INDEX `idx_grade` (`grade`),
    INDEX `idx_test_date` (`test_datetime`),
    FOREIGN KEY (`delivery_id`) REFERENCES `milk_deliveries`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`tested_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: raw_milk_inventory (Tank Storage)
-- =====================================================
CREATE TABLE `raw_milk_inventory` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tank_id` VARCHAR(20) NOT NULL,
    `qc_test_id` INT(11) NOT NULL,
    `volume_liters` DECIMAL(10,2) NOT NULL,
    `status` ENUM('available', 'in_production', 'consumed', 'expired', 'transformed') NOT NULL DEFAULT 'available',
    `received_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tank` (`tank_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expiry` (`expiry_date`),
    FOREIGN KEY (`qc_test_id`) REFERENCES `qc_milk_tests`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: products (Master Product List)
-- =====================================================
CREATE TABLE `products` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_code` VARCHAR(20) NOT NULL UNIQUE,
    `product_name` VARCHAR(150) NOT NULL,
    `category` ENUM('milk', 'cheese', 'butter', 'yogurt', 'other') NOT NULL,
    `variant` VARCHAR(100) NULL COMMENT 'Choco, Melon, Plain, etc.',
    `size_value` DECIMAL(10,2) NOT NULL,
    `size_unit` ENUM('ml', 'L', 'g', 'kg', 'pcs') NOT NULL,
    `shelf_life_days` INT(11) NOT NULL,
    `plant_price` DECIMAL(10,2) NOT NULL,
    `retail_price` DECIMAL(10,2) NOT NULL,
    `requires_pasteurization` TINYINT(1) DEFAULT 1,
    `pasteurization_temp` DECIMAL(4,1) DEFAULT 81.0 COMMENT 'Celsius',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_product_code` (`product_code`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: production_batches
-- =====================================================
CREATE TABLE `production_batches` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `batch_code` VARCHAR(30) NOT NULL UNIQUE,
    `product_id` INT(11) NOT NULL,
    `production_date` DATE NOT NULL,
    `production_start_time` TIME NULL,
    `production_end_time` TIME NULL,
    `target_quantity` INT(11) NOT NULL,
    `actual_quantity` INT(11) NULL,
    `status` ENUM('planned', 'in_progress', 'pending_qc', 'qc_approved', 'qc_rejected', 'released', 'cancelled') NOT NULL DEFAULT 'planned',
    `produced_by` INT(11) NULL COMMENT 'Production staff user_id',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batch_code` (`batch_code`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_production_date` (`production_date`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`produced_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: batch_ccp_logs (Critical Control Points)
-- =====================================================
CREATE TABLE `batch_ccp_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `batch_id` INT(11) NOT NULL,
    `ccp_type` ENUM('pasteurization', 'cooling', 'stirring', 'pressing', 'churning', 'aging') NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NULL,
    `target_temperature` DECIMAL(5,1) NULL,
    `actual_temperature` DECIMAL(5,1) NULL,
    `duration_minutes` INT(11) NULL,
    `logged_by` INT(11) NOT NULL,
    `is_within_standard` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batch` (`batch_id`),
    INDEX `idx_ccp_type` (`ccp_type`),
    FOREIGN KEY (`batch_id`) REFERENCES `production_batches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`logged_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: qc_batch_release (Batch Safety Verification)
-- =====================================================
CREATE TABLE `qc_batch_release` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `batch_id` INT(11) NOT NULL,
    `qc_officer_id` INT(11) NOT NULL,
    `verification_datetime` DATETIME NOT NULL,
    
    -- Safety Checklist
    `pasteurization_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `pasteurization_temp_achieved` DECIMAL(5,1) NULL,
    `cooling_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `cooling_temp_achieved` DECIMAL(5,1) NULL COMMENT 'Must be <= 4°C',
    `organoleptic_taste` ENUM('pass', 'fail', 'na') NOT NULL DEFAULT 'na',
    `organoleptic_appearance` ENUM('pass', 'fail', 'na') NOT NULL DEFAULT 'na',
    `organoleptic_smell` ENUM('pass', 'fail', 'na') NOT NULL DEFAULT 'na',
    
    -- Release Decision
    `is_released` TINYINT(1) NOT NULL DEFAULT 0,
    `release_datetime` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    
    -- Barcode & Expiry
    `barcode` VARCHAR(50) NULL,
    `manufacturing_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_batch` (`batch_id`),
    INDEX `idx_qc_officer` (`qc_officer_id`),
    INDEX `idx_release_status` (`is_released`),
    FOREIGN KEY (`batch_id`) REFERENCES `production_batches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`qc_officer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: finished_goods_inventory
-- =====================================================
CREATE TABLE `finished_goods_inventory` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `batch_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `chiller_location` VARCHAR(50) NULL,
    `quantity` INT(11) NOT NULL,
    `quantity_available` INT(11) NOT NULL,
    `manufacturing_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `barcode` VARCHAR(50) NOT NULL,
    `status` ENUM('available', 'reserved', 'sold', 'expired', 'transformed') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batch` (`batch_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_expiry` (`expiry_date`),
    INDEX `idx_barcode` (`barcode`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`batch_id`) REFERENCES `production_batches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: expiry_alerts (Near-Expiry Tracking)
-- =====================================================
CREATE TABLE `expiry_alerts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `inventory_id` INT(11) NOT NULL,
    `alert_type` ENUM('warning', 'critical', 'expired') NOT NULL,
    `days_until_expiry` INT(11) NOT NULL,
    `alert_date` DATE NOT NULL,
    `is_acknowledged` TINYINT(1) DEFAULT 0,
    `acknowledged_by` INT(11) NULL,
    `action_taken` ENUM('none', 'prioritized_sale', 'transformed', 'disposed') NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_inventory` (`inventory_id`),
    INDEX `idx_alert_type` (`alert_type`),
    INDEX `idx_alert_date` (`alert_date`),
    FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: yogurt_transformations (The Yogurt Logic)
-- =====================================================
CREATE TABLE `yogurt_transformations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transformation_code` VARCHAR(30) NOT NULL UNIQUE,
    `source_inventory_id` INT(11) NOT NULL COMMENT 'Near-expiry bottled milk',
    `source_quantity` INT(11) NOT NULL,
    `source_volume_liters` DECIMAL(10,2) NOT NULL,
    `target_batch_id` INT(11) NULL COMMENT 'New yogurt batch',
    `approved_by` INT(11) NOT NULL COMMENT 'QC Officer',
    `approval_datetime` DATETIME NOT NULL,
    `safety_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'approved', 'in_production', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_transformation_code` (`transformation_code`),
    INDEX `idx_source` (`source_inventory_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`source_inventory_id`) REFERENCES `finished_goods_inventory`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`target_batch_id`) REFERENCES `production_batches`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: farmer_payout_summary (Weekly Statements)
-- =====================================================
CREATE TABLE `farmer_payout_summary` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `farmer_id` INT(11) NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `total_deliveries` INT(11) NOT NULL DEFAULT 0,
    `total_liters_accepted` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_liters_rejected` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `average_fat_percentage` DECIMAL(5,2) NULL,
    `average_grade` VARCHAR(5) NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_incentives` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `total_deductions` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `net_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'finalized', 'paid') NOT NULL DEFAULT 'draft',
    `generated_by` INT(11) NOT NULL,
    `paid_date` DATE NULL,
    `payment_reference` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_farmer` (`farmer_id`),
    INDEX `idx_period` (`period_start`, `period_end`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`farmer_id`) REFERENCES `farmers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: audit_logs (System Traceability)
-- =====================================================
CREATE TABLE `audit_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT(11) NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_table` (`table_name`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INSERT: Default Quality Standards
-- =====================================================
INSERT INTO `quality_standards` (`parameter_name`, `min_value`, `max_value`, `standard_value`, `unit`, `incentive_per_unit`, `deduction_per_unit`) VALUES
('fat_percentage', 3.50, 4.50, 3.75, '%', 0.50, 0.25),
('acidity_ph', 6.60, 6.80, 6.70, 'pH', 0.00, 0.50),
('temperature', 0.00, 8.00, 4.00, '°C', 0.00, 0.25),
('protein_percentage', 3.20, 3.80, 3.50, '%', 0.25, 0.15);

-- =====================================================
-- INSERT: Default Admin User
-- =====================================================
INSERT INTO `users` (`employee_id`, `username`, `password`, `first_name`, `last_name`, `email`, `role`) VALUES
('EMP-001', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'admin@highlandfresh.com', 'general_manager'),
('EMP-002', 'qc_officer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria', 'Santos', 'qc@highlandfresh.com', 'qc_officer');

-- =====================================================
-- INSERT: Sample Farmers
-- =====================================================
INSERT INTO `farmers` (`farmer_code`, `first_name`, `last_name`, `contact_number`, `address`, `membership_type`, `base_price_per_liter`) VALUES
('FRM-001', 'Juan', 'Dela Cruz', '09171234567', 'Brgy. San Jose, Bukidnon', 'member', 40.00),
('FRM-002', 'Pedro', 'Reyes', '09181234567', 'Brgy. Poblacion, Bukidnon', 'member', 40.00),
('FRM-003', 'Jose', 'Garcia', '09191234567', 'Brgy. Mangga, Bukidnon', 'non_member', 38.00);

-- =====================================================
-- INSERT: Sample Products
-- =====================================================
INSERT INTO `products` (`product_code`, `product_name`, `category`, `variant`, `size_value`, `size_unit`, `shelf_life_days`, `plant_price`, `retail_price`) VALUES
('PRD-001', 'Fresh Milk Plain', 'milk', 'Plain', 330, 'ml', 7, 35.00, 45.00),
('PRD-002', 'Fresh Milk Choco', 'milk', 'Chocolate', 330, 'ml', 7, 38.00, 48.00),
('PRD-003', 'Fresh Milk Melon', 'milk', 'Melon', 330, 'ml', 7, 38.00, 48.00),
('PRD-004', 'Highland Yogurt', 'yogurt', 'Plain', 200, 'ml', 14, 40.00, 55.00),
('PRD-005', 'Highland Cheese', 'cheese', 'Fresh', 250, 'g', 30, 150.00, 200.00),
('PRD-006', 'Highland Butter', 'butter', 'Salted', 200, 'g', 60, 120.00, 160.00);

COMMIT;
