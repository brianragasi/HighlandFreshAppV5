-- ============================================================
-- Migration: PR -> PO Supplier Consolidation
-- Purpose : Allow a single approved Purchase Request to be split
--           into multiple POs (one per distinct supplier) at
--           PO-creation time.
--
-- Changes:
--   1. purchase_request_items  + supplier_id, supplier_assigned_by,
--                              supplier_assigned_at  (line-level supplier)
--   2. purchase_requests.status + 'partially_converted' value
--   3. purchase_request_item_po (new allocation table)
--           -> which PR lines are covered by which PO,
--              and in what quantity (supports partial conversion)
--
-- Idempotent: safe to re-run. Wraps each ALTER in a column-exists
-- check, uses CREATE TABLE IF NOT EXISTS for the new table.
-- ============================================================

-- ------------------------------------------------------------
-- 1) purchase_request_items: add per-line supplier resolution
-- ------------------------------------------------------------
SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'purchase_request_items'
      AND COLUMN_NAME  = 'supplier_id'
);
SET @sql := IF(@col = 0,
    'ALTER TABLE `purchase_request_items`
        ADD COLUMN `supplier_id` INT(11) DEFAULT NULL AFTER `mro_item_id`,
        ADD COLUMN `supplier_assigned_by` INT(11) DEFAULT NULL AFTER `supplier_id`,
        ADD COLUMN `supplier_assigned_at` DATETIME DEFAULT NULL AFTER `supplier_assigned_by`',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index for grouping (only add once)
SET @idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'purchase_request_items'
      AND INDEX_NAME   = 'idx_pri_supplier'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE `purchase_request_items`
        ADD INDEX `idx_pri_supplier` (`supplier_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign key (only add if not already present)
SET @fk := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA   = DATABASE()
      AND TABLE_NAME     = 'purchase_request_items'
      AND CONSTRAINT_NAME = 'fk_pri_supplier'
);
SET @sql := IF(@fk = 0,
    'ALTER TABLE `purchase_request_items`
        ADD CONSTRAINT `fk_pri_supplier` FOREIGN KEY (`supplier_id`)
        REFERENCES `suppliers` (`id`) ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2) purchase_requests.status: add partially_converted
-- ------------------------------------------------------------
SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'purchase_requests'
      AND COLUMN_NAME  = 'status'
);
SET @sql := IF(@col > 0,
    'ALTER TABLE `purchase_requests`
        MODIFY COLUMN `status`
        ENUM(''draft'',''pending'',''approved'',''rejected'',''converted'',''partially_converted'')
        DEFAULT ''pending''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3) New allocation table: purchase_request_item_po
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_request_item_po` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_request_item_id` INT(11) NOT NULL,
    `po_id` INT(11) NOT NULL,
    `quantity` DECIMAL(12,2) NOT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pri_po` (`purchase_request_item_id`, `po_id`),
    KEY `idx_pripo_po` (`po_id`),
    KEY `idx_pripo_pri` (`purchase_request_item_id`),
    CONSTRAINT `fk_pripo_pri`
        FOREIGN KEY (`purchase_request_item_id`)
        REFERENCES `purchase_request_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pripo_po`
        FOREIGN KEY (`po_id`)
        REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Verification queries (run these manually after migration)
-- ============================================================
-- SHOW COLUMNS FROM purchase_request_items LIKE 'supplier%';
-- SHOW COLUMNS FROM purchase_requests LIKE 'status';
-- DESCRIBE purchase_request_item_po;
--
-- Expected:
--   purchase_request_items gains: supplier_id, supplier_assigned_by, supplier_assigned_at
--   purchase_requests.status enum gains: 'partially_converted'
--   purchase_request_item_po table exists with FKs to PR items and POs
