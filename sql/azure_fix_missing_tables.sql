-- =============================================
-- Highland Fresh - Azure Missing Tables Fix
-- Run this on Azure MySQL to create missing tables
-- Date: 2026-02-11
-- =============================================

-- ===================================
-- 1. Create yogurt_transformations table
-- ===================================
CREATE TABLE IF NOT EXISTS `yogurt_transformations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transformation_code` VARCHAR(30) NOT NULL,
    `source_inventory_id` INT(11) DEFAULT NULL COMMENT 'FK to finished_goods_inventory.id',
    `source_quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `source_volume_liters` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `target_product` VARCHAR(100) DEFAULT 'Yogurt',
    `target_recipe_id` INT(11) DEFAULT NULL COMMENT 'FK to master_recipes.id',
    `target_quantity` DECIMAL(10,2) DEFAULT NULL COMMENT 'Actual yield after transformation',
    `production_run_id` INT(11) DEFAULT NULL COMMENT 'FK to production_runs.id',
    `transformation_date` DATE NOT NULL,
    `initiated_by` INT(11) DEFAULT NULL COMMENT 'FK to users.id',
    `approved_by` INT(11) DEFAULT NULL COMMENT 'FK to users.id',
    `approval_datetime` DATETIME DEFAULT NULL,
    `completed_by` INT(11) DEFAULT NULL COMMENT 'FK to users.id',
    `completed_at` DATETIME DEFAULT NULL,
    `safety_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_transformation_code` (`transformation_code`),
    KEY `idx_source_inventory` (`source_inventory_id`),
    KEY `idx_status` (`status`),
    KEY `idx_transformation_date` (`transformation_date`),
    KEY `idx_production_run` (`production_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================
-- 2. Create disposals table (without FK constraints for Azure compatibility)
-- ===================================
CREATE TABLE IF NOT EXISTS disposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disposal_code VARCHAR(30) NOT NULL UNIQUE,
    
    -- Source item identification
    source_type ENUM('raw_milk', 'finished_goods', 'ingredients', 'production_batch', 'milk_receiving') NOT NULL,
    source_id INT NOT NULL,
    source_reference VARCHAR(100) NULL COMMENT 'Batch code, receiving code, etc.',
    
    -- Product details
    product_id INT NULL,
    product_name VARCHAR(100) NULL,
    
    -- Quantity disposed
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs' COMMENT 'pcs, liters, kg, boxes',
    
    -- Financial impact
    unit_cost DECIMAL(12,2) DEFAULT 0,
    total_value DECIMAL(14,2) DEFAULT 0 COMMENT 'Total loss value',
    
    -- Categorization
    disposal_category ENUM(
        'qc_failed', 'expired', 'spoiled', 'contaminated', 
        'damaged', 'rejected_receipt', 'production_waste', 'other'
    ) NOT NULL,
    
    disposal_reason TEXT NOT NULL COMMENT 'Detailed reason for disposal',
    
    -- Disposal method
    disposal_method ENUM(
        'drain', 'incinerate', 'animal_feed', 'compost', 'special_waste', 'other'
    ) NOT NULL DEFAULT 'drain',
    
    -- Approval workflow
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    
    -- Initiator (QC Officer)
    initiated_by INT NOT NULL,
    initiated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Approver (GM)
    approved_by INT NULL,
    approved_at DATETIME NULL,
    approval_notes TEXT NULL,
    
    -- Execution
    disposed_by INT NULL,
    disposed_at DATETIME NULL,
    disposal_location VARCHAR(100) NULL,
    
    -- Documentation
    documentation_path VARCHAR(255) NULL,
    witness_name VARCHAR(100) NULL,
    
    -- Audit trail
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_disposal_code (disposal_code),
    INDEX idx_source (source_type, source_id),
    INDEX idx_status (status),
    INDEX idx_category (disposal_category),
    INDEX idx_date (created_at),
    INDEX idx_product_id (product_id),
    INDEX idx_approved_by (approved_by),
    INDEX idx_initiated_by (initiated_by),
    INDEX idx_disposed_by (disposed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 3. Create disposal_items table (without FK constraints for Azure compatibility)
-- ===================================
CREATE TABLE IF NOT EXISTS disposal_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disposal_id INT NOT NULL,
    
    source_type ENUM('raw_milk', 'finished_goods', 'ingredients', 'production_batch') NOT NULL,
    source_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(100) NULL,
    batch_code VARCHAR(50) NULL,
    
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'pcs',
    
    unit_cost DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(14,2) DEFAULT 0,
    
    expiry_date DATE NULL,
    reason TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_disposal_id (disposal_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- Done!
-- ===================================
SELECT 'All missing tables created successfully!' as status;
