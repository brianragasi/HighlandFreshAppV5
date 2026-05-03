-- ============================================================
-- Phase 1: Purchase Requests (PR) tables
-- Flow: Warehouse Raw creates PR → GM approves → Purchaser creates PO
-- ============================================================

-- Purchase Requests (created by Warehouse Raw)
CREATE TABLE IF NOT EXISTS `purchase_requests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `pr_number` VARCHAR(30) NOT NULL,
    `requested_by` INT(11) NOT NULL COMMENT 'Must be warehouse_raw user',
    `department` VARCHAR(50) NOT NULL DEFAULT 'warehouse_raw',
    `priority` ENUM('low','normal','high','urgent') DEFAULT 'normal',
    `needed_by_date` DATE DEFAULT NULL,
    `purpose` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
    `approved_by` INT(11) DEFAULT NULL COMMENT 'Must be general_manager',
    `approved_at` DATETIME DEFAULT NULL,
    `rejection_reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pr_number` (`pr_number`),
    KEY `idx_pr_status` (`status`),
    KEY `idx_pr_requested_by` (`requested_by`),
    KEY `idx_pr_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Purchase Request Items
CREATE TABLE IF NOT EXISTS `purchase_request_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_request_id` INT(11) NOT NULL,
    `ingredient_id` INT(11) DEFAULT NULL COMMENT 'FK to ingredients table',
    `mro_item_id` INT(11) DEFAULT NULL COMMENT 'FK to mro_items table',
    `item_description` VARCHAR(200) NOT NULL,
    `quantity` DECIMAL(12,2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'units',
    `estimated_unit_price` DECIMAL(12,2) DEFAULT NULL,
    `estimated_total` DECIMAL(12,2) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pri_pr_id` (`purchase_request_id`),
    CONSTRAINT `fk_pri_pr` FOREIGN KEY (`purchase_request_id`)
        REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add purchase_request_id column to purchase_orders if it doesn't exist
-- This links PO back to the approved PR
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'purchase_orders' 
    AND COLUMN_NAME = 'purchase_request_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `purchase_orders` ADD COLUMN `purchase_request_id` INT(11) DEFAULT NULL COMMENT ''FK to approved purchase_request'' AFTER `requisition_id`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
