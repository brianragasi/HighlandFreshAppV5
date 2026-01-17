-- =====================================================
-- Highland Fresh System - Warehouse FG Module Migration v2
-- Purpose: Add missing tables for Sales Orders (POs from Sales Custodian)
-- Version: 4.0.1
-- Run this AFTER migration_warehouse_fg.sql
-- =====================================================

USE `highland_fresh`;

-- =====================================================
-- TABLE: sales_orders (POs from Sales Custodian to Warehouse FG)
-- These are orders that need to be fulfilled by Warehouse FG
-- =====================================================

CREATE TABLE IF NOT EXISTS `sales_orders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g., SO-20260117-001',
    `customer_id` INT(11) NULL COMMENT 'Links to customers table',
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_type` ENUM('supermarket', 'school', 'feeding_program', 'restaurant', 'distributor', 'walk_in', 'other') NOT NULL DEFAULT 'other',
    `customer_po_number` VARCHAR(50) NULL COMMENT 'Customer PO reference if applicable',
    `contact_person` VARCHAR(100) NULL,
    `contact_number` VARCHAR(20) NULL,
    `delivery_address` TEXT NULL,
    `delivery_date` DATE NULL COMMENT 'Requested delivery date',
    `total_items` INT(11) NOT NULL DEFAULT 0,
    `total_quantity` INT(11) NOT NULL DEFAULT 0,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft', 'pending', 'approved', 'preparing', 'partially_fulfilled', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
    `priority` ENUM('normal', 'rush', 'urgent') NOT NULL DEFAULT 'normal',
    `created_by` INT(11) NOT NULL COMMENT 'Sales Custodian who created',
    `approved_by` INT(11) NULL COMMENT 'Manager who approved',
    `approved_at` DATETIME NULL,
    `assigned_to` INT(11) NULL COMMENT 'Warehouse FG staff assigned',
    `dr_id` INT(11) NULL COMMENT 'Links to delivery_receipts when fulfilled',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order_number` (`order_number`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_customer_type` (`customer_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_delivery_date` (`delivery_date`),
    INDEX `idx_created_by` (`created_by`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: sales_order_items (Line Items in Sales Order)
-- Individual products requested in a sales order
-- =====================================================

CREATE TABLE IF NOT EXISTS `sales_order_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `product_name` VARCHAR(150) NOT NULL COMMENT 'Snapshot at order time',
    `variant` VARCHAR(100) NULL,
    `size_value` DECIMAL(10,2) NOT NULL,
    `size_unit` VARCHAR(10) NOT NULL,
    `quantity_ordered` INT(11) NOT NULL,
    `quantity_fulfilled` INT(11) NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `line_total` DECIMAL(12,2) NOT NULL,
    `status` ENUM('pending', 'partial', 'fulfilled', 'out_of_stock', 'cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_order` (`order_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`order_id`) REFERENCES `sales_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: dispatch_items (Aliased table for API compatibility)
-- Some APIs reference dispatch_items instead of delivery_receipt_items
-- This creates a compatibility alias via a view or identical table
-- =====================================================

-- Create view as an alias for delivery_receipt_items
-- This maintains backward compatibility with APIs that reference dispatch_items
CREATE OR REPLACE VIEW `dispatch_items` AS
SELECT 
    id,
    dr_id,
    product_id,
    inventory_id,
    product_name,
    variant,
    size_value,
    size_unit,
    quantity,
    unit_price,
    line_total,
    barcode_scanned,
    manufacturing_date,
    expiry_date,
    status,
    picked_at,
    released_at,
    notes,
    created_at,
    updated_at
FROM delivery_receipt_items;

-- =====================================================
-- ALTER: delivery_receipts
-- Add missing columns referenced in API
-- =====================================================

-- Add customer_type column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND COLUMN_NAME = 'customer_type');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipts ADD COLUMN customer_type ENUM("supermarket", "school", "feeding_program", "restaurant", "distributor", "walk_in", "other") NOT NULL DEFAULT "other" AFTER customer_id', 
    'SELECT "customer_type already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add sub_location column (for schools, specific branches, etc.)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND COLUMN_NAME = 'sub_location');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipts ADD COLUMN sub_location VARCHAR(150) NULL AFTER customer_name', 
    'SELECT "sub_location already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add delivery_address column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND COLUMN_NAME = 'delivery_address');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipts ADD COLUMN delivery_address TEXT NULL AFTER contact_number', 
    'SELECT "delivery_address already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add created_by column (API uses this instead of prepared_by)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipts ADD COLUMN created_by INT(11) NULL AFTER prepared_by', 
    'SELECT "created_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add dispatched_by column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND COLUMN_NAME = 'dispatched_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipts ADD COLUMN dispatched_by INT(11) NULL AFTER released_by', 
    'SELECT "dispatched_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add dispatched_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND COLUMN_NAME = 'dispatched_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipts ADD COLUMN dispatched_at DATETIME NULL AFTER dispatched_by', 
    'SELECT "dispatched_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add sales_order_id to link DR back to Sales Order
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND COLUMN_NAME = 'sales_order_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipts ADD COLUMN sales_order_id INT(11) NULL AFTER order_id', 
    'SELECT "sales_order_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: production_batches
-- Add 'completed' status if not in ENUM (for QC released batches)
-- =====================================================

-- Check if 'completed' status exists and add if not
-- This is needed because fg_receiving references batches with status = 'completed'
SET @has_completed = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' 
    AND TABLE_NAME = 'production_batches' 
    AND COLUMN_NAME = 'status'
    AND COLUMN_TYPE LIKE '%completed%');

-- Note: Altering ENUM requires careful handling - run manually if needed:
-- ALTER TABLE production_batches MODIFY COLUMN status 
--     ENUM('planned', 'in_progress', 'pending_qc', 'qc_approved', 'qc_rejected', 'released', 'completed', 'cancelled') 
--     NOT NULL DEFAULT 'planned';

-- =====================================================
-- CREATE VIEW: vw_sales_orders_summary
-- Summary of sales orders for Warehouse FG dashboard
-- =====================================================

CREATE OR REPLACE VIEW `vw_sales_orders_summary` AS
SELECT 
    so.id,
    so.order_number,
    so.customer_name,
    so.customer_type,
    so.customer_po_number,
    so.delivery_date,
    so.total_items,
    so.total_quantity,
    so.total_amount,
    so.status,
    so.priority,
    CONCAT(u1.first_name, ' ', u1.last_name) as created_by_name,
    so.created_at,
    CONCAT(u2.first_name, ' ', u2.last_name) as assigned_to_name,
    dr.dr_number
FROM sales_orders so
LEFT JOIN users u1 ON so.created_by = u1.id
LEFT JOIN users u2 ON so.assigned_to = u2.id
LEFT JOIN delivery_receipts dr ON so.dr_id = dr.id;

-- =====================================================
-- CREATE VIEW: vw_pending_fulfillment
-- Orders pending fulfillment by Warehouse FG
-- =====================================================

CREATE OR REPLACE VIEW `vw_pending_fulfillment` AS
SELECT 
    so.id as order_id,
    so.order_number,
    so.customer_name,
    so.customer_type,
    so.delivery_date,
    so.priority,
    soi.id as item_id,
    soi.product_id,
    p.product_code,
    p.product_name,
    p.variant,
    soi.quantity_ordered,
    soi.quantity_fulfilled,
    (soi.quantity_ordered - soi.quantity_fulfilled) as quantity_remaining,
    soi.unit_price,
    COALESCE(
        (SELECT SUM(fgi.quantity_available) 
         FROM finished_goods_inventory fgi 
         WHERE fgi.product_id = soi.product_id 
         AND fgi.status = 'available' 
         AND fgi.expiry_date > CURDATE()),
        0
    ) as available_stock
FROM sales_orders so
JOIN sales_order_items soi ON so.id = soi.order_id
JOIN products p ON soi.product_id = p.id
WHERE so.status IN ('pending', 'approved', 'preparing', 'partially_fulfilled')
AND soi.status IN ('pending', 'partial')
ORDER BY 
    CASE so.priority 
        WHEN 'urgent' THEN 1 
        WHEN 'rush' THEN 2 
        ELSE 3 
    END,
    so.delivery_date ASC,
    so.created_at ASC;

-- =====================================================
-- INSERT: Sample Sales Orders for Testing
-- =====================================================

-- Insert only if no sales orders exist yet
INSERT INTO `sales_orders` (`order_number`, `customer_id`, `customer_name`, `customer_type`, `delivery_date`, `total_items`, `total_quantity`, `total_amount`, `status`, `created_by`, `notes`)
SELECT 'SO-20260117-001', 1, 'SM Supermarket - Bukidnon', 'supermarket', '2026-01-18', 3, 100, 4500.00, 'pending', 
    (SELECT id FROM users WHERE role = 'general_manager' LIMIT 1), 
    'Weekly regular order'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM sales_orders WHERE order_number = 'SO-20260117-001');

INSERT INTO `sales_orders` (`order_number`, `customer_id`, `customer_name`, `customer_type`, `delivery_date`, `total_items`, `total_quantity`, `total_amount`, `status`, `priority`, `created_by`, `notes`)
SELECT 'SO-20260117-002', 3, 'DepEd Division of Bukidnon', 'feeding_program', '2026-01-17', 2, 500, 17500.00, 'approved', 'rush',
    (SELECT id FROM users WHERE role = 'general_manager' LIMIT 1),
    'School feeding program delivery - URGENT'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM sales_orders WHERE order_number = 'SO-20260117-002');

SELECT 'Warehouse FG v2 migration completed successfully!' as status;
