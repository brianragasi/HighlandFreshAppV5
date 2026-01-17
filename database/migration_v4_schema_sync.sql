-- =====================================================
-- Highland Fresh System - Schema Migration v4
-- Purpose: Add missing columns that APIs expect
-- Run this ONCE on your existing database
-- =====================================================

USE `highland_fresh`;

-- =====================================================
-- ALTER: production_batches
-- Add columns expected by batch_release.php and related APIs
-- =====================================================

-- Add recipe_id if it doesn't exist (replaces product_id concept)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'recipe_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN recipe_id INT(11) NULL AFTER batch_code', 
    'SELECT "recipe_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add qc_status column (used by batch_release API)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'qc_status');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN qc_status ENUM("pending", "on_hold", "released", "rejected") NOT NULL DEFAULT "pending" AFTER status', 
    'SELECT "qc_status already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add product_type column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'product_type');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN product_type VARCHAR(50) NULL AFTER recipe_id', 
    'SELECT "product_type already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add product_variant column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'product_variant');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN product_variant VARCHAR(100) NULL AFTER product_type', 
    'SELECT "product_variant already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add raw_milk_liters column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'raw_milk_liters');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN raw_milk_liters DECIMAL(10,2) NULL AFTER product_variant', 
    'SELECT "raw_milk_liters already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add expected_yield column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'expected_yield');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN expected_yield DECIMAL(10,2) NULL AFTER raw_milk_liters', 
    'SELECT "expected_yield already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add actual_yield column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'actual_yield');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN actual_yield DECIMAL(10,2) NULL AFTER expected_yield', 
    'SELECT "actual_yield already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add manufacturing_date column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'manufacturing_date');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN manufacturing_date DATE NULL AFTER actual_yield', 
    'SELECT "manufacturing_date already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add expiry_date column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'expiry_date');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN expiry_date DATE NULL AFTER manufacturing_date', 
    'SELECT "expiry_date already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pasteurization_temp column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'pasteurization_temp');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN pasteurization_temp DECIMAL(5,1) NULL AFTER expiry_date', 
    'SELECT "pasteurization_temp already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add cooling_temp column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'cooling_temp');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN cooling_temp DECIMAL(5,1) NULL AFTER pasteurization_temp', 
    'SELECT "cooling_temp already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add barcode column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN barcode VARCHAR(50) NULL AFTER cooling_temp', 
    'SELECT "barcode already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add organoleptic fields
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'organoleptic_taste');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN organoleptic_taste TINYINT(1) DEFAULT 0 AFTER barcode', 
    'SELECT "organoleptic_taste already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'organoleptic_appearance');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN organoleptic_appearance TINYINT(1) DEFAULT 0 AFTER organoleptic_taste', 
    'SELECT "organoleptic_appearance already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'organoleptic_smell');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN organoleptic_smell TINYINT(1) DEFAULT 0 AFTER organoleptic_appearance', 
    'SELECT "organoleptic_smell already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add QC release fields
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'qc_notes');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN qc_notes TEXT NULL AFTER organoleptic_smell', 
    'SELECT "qc_notes already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'released_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN released_by INT(11) NULL AFTER qc_notes', 
    'SELECT "released_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'released_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN released_at DATETIME NULL AFTER released_by', 
    'SELECT "released_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'qc_released_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN qc_released_at DATETIME NULL AFTER released_at', 
    'SELECT "qc_released_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'production_batches' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE production_batches ADD COLUMN created_by INT(11) NULL AFTER qc_released_at', 
    'SELECT "created_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: qc_milk_tests
-- Add columns expected by milk_grading.php API (ANNEX B pricing)
-- =====================================================

-- Add titratable_acidity column (ANNEX B uses this instead of pH)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'titratable_acidity');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN titratable_acidity DECIMAL(5,2) NULL AFTER acidity_ph', 
    'SELECT "titratable_acidity already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add sediment_grade column (numeric 1-3 for ANNEX B)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'sediment_grade');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN sediment_grade TINYINT(1) DEFAULT 1 AFTER sediment_level', 
    'SELECT "sediment_grade already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add ANNEX B pricing columns
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'fat_adjustment');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN fat_adjustment DECIMAL(10,2) DEFAULT 0.00 AFTER base_price_per_liter', 
    'SELECT "fat_adjustment already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'acidity_deduction');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN acidity_deduction DECIMAL(10,2) DEFAULT 0.00 AFTER fat_adjustment', 
    'SELECT "acidity_deduction already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'sediment_deduction');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN sediment_deduction DECIMAL(10,2) DEFAULT 0.00 AFTER acidity_deduction', 
    'SELECT "sediment_deduction already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add Milkosonic SL50 extended columns
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'salts_percentage');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN salts_percentage DECIMAL(5,2) NULL AFTER snf_percentage', 
    'SELECT "salts_percentage already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'total_solids_percentage');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN total_solids_percentage DECIMAL(5,2) NULL AFTER salts_percentage', 
    'SELECT "total_solids_percentage already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'added_water_percentage');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN added_water_percentage DECIMAL(5,2) NULL AFTER total_solids_percentage', 
    'SELECT "added_water_percentage already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'freezing_point');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN freezing_point DECIMAL(6,4) NULL AFTER added_water_percentage', 
    'SELECT "freezing_point already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'qc_milk_tests' AND COLUMN_NAME = 'sample_temperature');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qc_milk_tests ADD COLUMN sample_temperature DECIMAL(5,2) NULL AFTER freezing_point', 
    'SELECT "sample_temperature already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: milk_deliveries 
-- Add columns expected by APIs 
-- =====================================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'milk_deliveries' AND COLUMN_NAME = 'apt_result');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE milk_deliveries ADD COLUMN apt_result ENUM("negative", "positive") DEFAULT "negative" AFTER volume_liters', 
    'SELECT "apt_result already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'milk_deliveries' AND COLUMN_NAME = 'grade');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE milk_deliveries ADD COLUMN grade VARCHAR(20) NULL AFTER apt_result', 
    'SELECT "grade already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'milk_deliveries' AND COLUMN_NAME = 'accepted_liters');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE milk_deliveries ADD COLUMN accepted_liters DECIMAL(10,2) NULL AFTER grade', 
    'SELECT "accepted_liters already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'milk_deliveries' AND COLUMN_NAME = 'unit_price');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE milk_deliveries ADD COLUMN unit_price DECIMAL(10,2) NULL AFTER accepted_liters', 
    'SELECT "unit_price already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'milk_deliveries' AND COLUMN_NAME = 'total_amount');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE milk_deliveries ADD COLUMN total_amount DECIMAL(12,2) NULL AFTER unit_price', 
    'SELECT "total_amount already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: finished_goods_inventory
-- Add remaining_quantity column if not exists (used in expiry alerts)
-- =====================================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'remaining_quantity');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN remaining_quantity INT(11) NULL AFTER quantity_available', 
    'SELECT "remaining_quantity already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add product_type and product_variant columns
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'product_type');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN product_type VARCHAR(50) NULL AFTER product_id', 
    'SELECT "product_type already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'product_variant');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN product_variant VARCHAR(100) NULL AFTER product_type', 
    'SELECT "product_variant already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Create master_recipes table if it doesn't exist
-- (Used by production_batches for recipe-based production)
-- =====================================================

CREATE TABLE IF NOT EXISTS `master_recipes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `recipe_code` VARCHAR(30) NOT NULL UNIQUE,
    `product_name` VARCHAR(150) NOT NULL,
    `product_type` ENUM('bottled_milk', 'cheese', 'butter', 'yogurt', 'milk_bar') NOT NULL,
    `variant` VARCHAR(100) NULL,
    `yield_unit` VARCHAR(20) DEFAULT 'units',
    `yield_per_liter` DECIMAL(10,4) DEFAULT 1.0000 COMMENT 'Output units per liter of raw milk',
    `shelf_life_days` INT(11) NOT NULL DEFAULT 7,
    `pasteurization_temp` DECIMAL(5,1) DEFAULT 72.0,
    `pasteurization_time_mins` INT(11) DEFAULT 15,
    `cooling_temp` DECIMAL(5,1) DEFAULT 4.0,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_recipe_code` (`recipe_code`),
    INDEX `idx_product_type` (`product_type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample recipes if table is empty
INSERT IGNORE INTO `master_recipes` (`recipe_code`, `product_name`, `product_type`, `variant`, `yield_unit`, `yield_per_liter`, `shelf_life_days`) VALUES
('RCP-001', 'Fresh Milk Plain 330ml', 'bottled_milk', 'Plain', 'bottles', 3.0000, 7),
('RCP-002', 'Fresh Milk Choco 330ml', 'bottled_milk', 'Chocolate', 'bottles', 3.0000, 7),
('RCP-003', 'Fresh Milk Melon 330ml', 'bottled_milk', 'Melon', 'bottles', 3.0000, 7),
('RCP-004', 'Highland Yogurt 200ml', 'yogurt', 'Plain', 'cups', 5.0000, 14),
('RCP-005', 'Highland Cheese 250g', 'cheese', 'Fresh', 'blocks', 0.4000, 30);

-- =====================================================
-- Create production_ccp_logs table if it doesn't exist
-- (Used by CCP logging in production)
-- =====================================================

CREATE TABLE IF NOT EXISTS `production_ccp_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `batch_id` INT(11) NOT NULL,
    `ccp_type` ENUM('pasteurization', 'cooling', 'storage') NOT NULL,
    `recorded_value` DECIMAL(6,2) NOT NULL,
    `min_value` DECIMAL(6,2) NULL,
    `max_value` DECIMAL(6,2) NULL,
    `status` ENUM('pass', 'fail', 'warning') NOT NULL DEFAULT 'pass',
    `logged_by` INT(11) NOT NULL,
    `logged_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batch` (`batch_id`),
    INDEX `idx_ccp_type` (`ccp_type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Create production_runs table if it doesn't exist
-- (Used by production dashboard)
-- =====================================================

CREATE TABLE IF NOT EXISTS `production_runs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `run_code` VARCHAR(30) NOT NULL UNIQUE,
    `recipe_id` INT(11) NOT NULL,
    `planned_quantity` DECIMAL(10,2) NOT NULL,
    `actual_quantity` DECIMAL(10,2) NULL,
    `raw_milk_liters` DECIMAL(10,2) NOT NULL,
    `yield_unit` VARCHAR(20) DEFAULT 'units',
    `yield_variance` DECIMAL(5,2) NULL COMMENT 'Percentage variance from expected',
    `status` ENUM('planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
    `start_datetime` DATETIME NULL,
    `end_datetime` DATETIME NULL,
    `created_by` INT(11) NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_run_code` (`run_code`),
    INDEX `idx_recipe` (`recipe_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Create ingredient_requisitions table if it doesn't exist
-- =====================================================

CREATE TABLE IF NOT EXISTS `ingredient_requisitions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `requisition_code` VARCHAR(30) NOT NULL UNIQUE,
    `requested_by` INT(11) NOT NULL,
    `status` ENUM('draft', 'pending', 'approved', 'rejected', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'draft',
    `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `total_items` INT(11) DEFAULT 0,
    `notes` TEXT NULL,
    `approved_by` INT(11) NULL,
    `approved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_requisition_code` (`requisition_code`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'Migration completed successfully!' as status;
