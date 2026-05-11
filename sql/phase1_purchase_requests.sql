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
    `status` ENUM('draft','pending','approved','rejected','converted') DEFAULT 'pending',
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

-- Purchase Request Status History
CREATE TABLE IF NOT EXISTS `purchase_request_status_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_request_id` INT(11) NOT NULL,
    `from_status` VARCHAR(30) DEFAULT NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `changed_by` INT(11) DEFAULT NULL,
    `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pr_history_pr` (`purchase_request_id`),
    KEY `idx_pr_history_status` (`to_status`),
    KEY `idx_pr_history_changed_at` (`changed_at`),
    CONSTRAINT `fk_pr_history_pr` FOREIGN KEY (`purchase_request_id`)
        REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
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
    `purpose` VARCHAR(255) DEFAULT NULL,
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

-- Delivery instructions/details shown on the PO form and document views
SET @po_delivery_details_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_orders'
    AND COLUMN_NAME = 'delivery_details'
);

SET @sql = IF(@po_delivery_details_exists = 0,
    'ALTER TABLE `purchase_orders` ADD COLUMN `delivery_details` TEXT DEFAULT NULL AFTER `expected_delivery`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @po_sent_at_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_orders'
    AND COLUMN_NAME = 'sent_to_supplier_at'
);

SET @sql = IF(@po_sent_at_exists = 0,
    'ALTER TABLE `purchase_orders` ADD COLUMN `sent_to_supplier_at` DATETIME DEFAULT NULL AFTER `approved_at`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @po_sent_by_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_orders'
    AND COLUMN_NAME = 'sent_to_supplier_by'
);

SET @sql = IF(@po_sent_by_exists = 0,
    'ALTER TABLE `purchase_orders` ADD COLUMN `sent_to_supplier_by` INT(11) DEFAULT NULL AFTER `sent_to_supplier_at`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Link PO line items back to the approved PR lines they came from
SET @po_item_pr_line_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_order_items'
    AND COLUMN_NAME = 'purchase_request_item_id'
);

SET @sql = IF(@po_item_pr_line_exists = 0,
    'ALTER TABLE `purchase_order_items` ADD COLUMN `purchase_request_item_id` INT(11) DEFAULT NULL COMMENT ''FK to purchase_request_items'' AFTER `po_id`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Support GM PO rejection and approval/rejection remarks
ALTER TABLE `purchase_orders`
    MODIFY COLUMN `status` ENUM('draft','pending','approved','rejected','partial_received','received','closed','ordered','cancelled') DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS `procurement_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `target_role` VARCHAR(50) NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_procurement_notifications_role` (`target_role`, `is_read`),
    KEY `idx_procurement_notifications_reference` (`reference_type`, `reference_id`),
    KEY `idx_procurement_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @po_approval_remarks_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_orders'
    AND COLUMN_NAME = 'approval_remarks'
);

SET @sql = IF(@po_approval_remarks_exists = 0,
    'ALTER TABLE `purchase_orders` ADD COLUMN `approval_remarks` TEXT DEFAULT NULL AFTER `approved_at`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @po_rejection_reason_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'purchase_orders'
    AND COLUMN_NAME = 'rejection_reason'
);

SET @sql = IF(@po_rejection_reason_exists = 0,
    'ALTER TABLE `purchase_orders` ADD COLUMN `rejection_reason` TEXT DEFAULT NULL AFTER `approval_remarks`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
