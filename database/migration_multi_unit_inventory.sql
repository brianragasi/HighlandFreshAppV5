-- =====================================================
-- Highland Fresh System - Multi-Unit Inventory Migration
-- Purpose: Implement Box vs. Piece inventory tracking
-- Version: 4.0.2
-- Date: 2026-01-17
-- 
-- Requirements from warehouse_finished_goods.md Section 6A:
-- - Every product tracked in both Boxes and Pieces
-- - Product conversions: Milk Bar (1 Box = 50 Pieces), etc.
-- - "Box Opening" process for partial box sales
-- - Display as "8 Boxes + 14 Pieces" not "8.58 Boxes"
-- 
-- Run this AFTER migration_warehouse_fg_v2.sql
-- =====================================================

USE `highland_fresh`;

-- =====================================================
-- TABLE: product_units (Unit Conversion Definitions)
-- Defines the unit structure for each product
-- =====================================================

CREATE TABLE IF NOT EXISTS `product_units` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `base_unit` VARCHAR(20) NOT NULL COMMENT 'Smallest unit: piece, bottle, pack, bar, slice',
    `box_unit` VARCHAR(20) NOT NULL COMMENT 'Container unit: box, crate, case, tray',
    `pieces_per_box` INT(11) NOT NULL COMMENT 'Conversion ratio: how many base units per box',
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_product_unit` (`product_id`),
    INDEX `idx_base_unit` (`base_unit`),
    INDEX `idx_box_unit` (`box_unit`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ALTER: products table
-- Add columns for multi-unit configuration
-- =====================================================

-- Add base_unit column (smallest sellable unit)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'base_unit');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE products ADD COLUMN base_unit VARCHAR(20) NULL DEFAULT "piece" COMMENT "Smallest sellable unit: piece, bottle, pack, bar" AFTER is_active', 
    'SELECT "base_unit already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add box_unit column (container/bulk unit)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'box_unit');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE products ADD COLUMN box_unit VARCHAR(20) NULL DEFAULT "box" COMMENT "Container unit: box, crate, case, tray" AFTER base_unit', 
    'SELECT "box_unit already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pieces_per_box column (conversion ratio)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'products' AND COLUMN_NAME = 'pieces_per_box');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE products ADD COLUMN pieces_per_box INT(11) NULL DEFAULT 1 COMMENT "How many base units fit in one box unit" AFTER box_unit', 
    'SELECT "pieces_per_box already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: finished_goods_inventory table
-- Add columns for multi-unit tracking
-- =====================================================

-- Add quantity_boxes column (whole boxes in stock)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'quantity_boxes');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN quantity_boxes INT(11) NOT NULL DEFAULT 0 COMMENT "Number of full/sealed boxes" AFTER quantity_available', 
    'SELECT "quantity_boxes already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add quantity_pieces column (loose pieces from opened boxes)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'quantity_pieces');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN quantity_pieces INT(11) NOT NULL DEFAULT 0 COMMENT "Number of loose pieces from opened boxes" AFTER quantity_boxes', 
    'SELECT "quantity_pieces already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add boxes_available column (available full boxes for dispatch)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'boxes_available');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN boxes_available INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes available (not reserved)" AFTER quantity_pieces', 
    'SELECT "boxes_available already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pieces_available column (available loose pieces)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'pieces_available');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN pieces_available INT(11) NOT NULL DEFAULT 0 COMMENT "Pieces available (not reserved)" AFTER boxes_available', 
    'SELECT "pieces_available already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- TABLE: box_opening_log (Tracks when boxes are "opened")
-- When selling partial boxes, system opens a box digitally
-- =====================================================

CREATE TABLE IF NOT EXISTS `box_opening_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `opening_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g., BOX-20260117-001',
    `inventory_id` INT(11) NOT NULL COMMENT 'Links to finished_goods_inventory',
    `product_id` INT(11) NOT NULL,
    `boxes_opened` INT(11) NOT NULL DEFAULT 1 COMMENT 'Number of boxes opened',
    `pieces_from_opening` INT(11) NOT NULL COMMENT 'Pieces added to inventory from opened boxes',
    `reason` ENUM('partial_sale', 'sampling', 'quality_check', 'damage', 'other') NOT NULL DEFAULT 'partial_sale',
    `reference_type` VARCHAR(50) NULL COMMENT 'e.g., delivery_receipt, walk_in_sale',
    `reference_id` INT(11) NULL COMMENT 'Links to DR or sale record',
    `opened_by` INT(11) NOT NULL COMMENT 'Staff who opened the box',
    `opened_at` DATETIME NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_opening_code` (`opening_code`),
    INDEX `idx_inventory` (`inventory_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_opened_at` (`opened_at`),
    INDEX `idx_reference` (`reference_type`, `reference_id`),
    FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`opened_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ALTER: delivery_receipt_items table
-- Add columns for multi-unit order tracking
-- =====================================================

-- Add quantity_boxes column (boxes in line item)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipt_items' AND COLUMN_NAME = 'quantity_boxes');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipt_items ADD COLUMN quantity_boxes INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes ordered in this line" AFTER quantity', 
    'SELECT "quantity_boxes already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add quantity_pieces column (loose pieces in line item)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipt_items' AND COLUMN_NAME = 'quantity_pieces');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE delivery_receipt_items ADD COLUMN quantity_pieces INT(11) NOT NULL DEFAULT 0 COMMENT "Loose pieces ordered in this line" AFTER quantity_boxes', 
    'SELECT "quantity_pieces already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: sales_order_items table (IF EXISTS)
-- Add columns for multi-unit order tracking
-- =====================================================

-- Check if table exists first
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'sales_order_items');

-- Add quantity_boxes_ordered column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'sales_order_items' AND COLUMN_NAME = 'quantity_boxes_ordered');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE sales_order_items ADD COLUMN quantity_boxes_ordered INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes ordered"', 
    'SELECT "sales_order_items table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add quantity_pieces_ordered column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'sales_order_items' AND COLUMN_NAME = 'quantity_pieces_ordered');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE sales_order_items ADD COLUMN quantity_pieces_ordered INT(11) NOT NULL DEFAULT 0 COMMENT "Loose pieces ordered"', 
    'SELECT "sales_order_items table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add quantity_boxes_fulfilled column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'sales_order_items' AND COLUMN_NAME = 'quantity_boxes_fulfilled');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE sales_order_items ADD COLUMN quantity_boxes_fulfilled INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes fulfilled"', 
    'SELECT "sales_order_items table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add quantity_pieces_fulfilled column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'sales_order_items' AND COLUMN_NAME = 'quantity_pieces_fulfilled');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE sales_order_items ADD COLUMN quantity_pieces_fulfilled INT(11) NOT NULL DEFAULT 0 COMMENT "Loose pieces fulfilled"', 
    'SELECT "sales_order_items table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: fg_inventory_transactions table (IF EXISTS)
-- Add columns for multi-unit transaction tracking
-- =====================================================

-- Check if table exists first
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_inventory_transactions');

-- Add boxes_quantity column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_inventory_transactions' AND COLUMN_NAME = 'boxes_quantity');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_inventory_transactions ADD COLUMN boxes_quantity INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes involved in transaction"', 
    'SELECT "fg_inventory_transactions table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pieces_quantity column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_inventory_transactions' AND COLUMN_NAME = 'pieces_quantity');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_inventory_transactions ADD COLUMN pieces_quantity INT(11) NOT NULL DEFAULT 0 COMMENT "Loose pieces involved in transaction"', 
    'SELECT "fg_inventory_transactions table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add boxes_before column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_inventory_transactions' AND COLUMN_NAME = 'boxes_before');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_inventory_transactions ADD COLUMN boxes_before INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes count before transaction"', 
    'SELECT "fg_inventory_transactions table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pieces_before column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_inventory_transactions' AND COLUMN_NAME = 'pieces_before');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_inventory_transactions ADD COLUMN pieces_before INT(11) NOT NULL DEFAULT 0 COMMENT "Pieces count before transaction"', 
    'SELECT "fg_inventory_transactions table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add boxes_after column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_inventory_transactions' AND COLUMN_NAME = 'boxes_after');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_inventory_transactions ADD COLUMN boxes_after INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes count after transaction"', 
    'SELECT "fg_inventory_transactions table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pieces_after column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_inventory_transactions' AND COLUMN_NAME = 'pieces_after');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_inventory_transactions ADD COLUMN pieces_after INT(11) NOT NULL DEFAULT 0 COMMENT "Pieces count after transaction"', 
    'SELECT "fg_inventory_transactions table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: fg_dispatch_log table (IF EXISTS)
-- Add columns for multi-unit dispatch tracking
-- =====================================================

-- Check if table exists first
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_dispatch_log');

-- Add boxes_released column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_dispatch_log' AND COLUMN_NAME = 'boxes_released');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_dispatch_log ADD COLUMN boxes_released INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes released in this dispatch"', 
    'SELECT "fg_dispatch_log table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pieces_released column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_dispatch_log' AND COLUMN_NAME = 'pieces_released');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_dispatch_log ADD COLUMN pieces_released INT(11) NOT NULL DEFAULT 0 COMMENT "Loose pieces released in this dispatch"', 
    'SELECT "fg_dispatch_log table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: fg_receiving table (IF EXISTS)
-- Add columns for multi-unit receiving
-- =====================================================

-- Check if table exists first
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_receiving');

-- Add boxes_received column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_receiving' AND COLUMN_NAME = 'boxes_received');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_receiving ADD COLUMN boxes_received INT(11) NOT NULL DEFAULT 0 COMMENT "Boxes received from production"', 
    'SELECT "fg_receiving table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add pieces_received column (only if table exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'fg_receiving' AND COLUMN_NAME = 'pieces_received');
SET @sql = IF(@table_exists > 0 AND @col_exists = 0, 
    'ALTER TABLE fg_receiving ADD COLUMN pieces_received INT(11) NOT NULL DEFAULT 0 COMMENT "Loose pieces received (if any)"', 
    'SELECT "fg_receiving table not found or column exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- INSERT: Default Product Units Configuration
-- Based on warehouse_finished_goods.md Section 6A
-- =====================================================

-- First, update existing products with unit configurations
UPDATE `products` SET 
    `base_unit` = 'bottle', 
    `box_unit` = 'crate', 
    `pieces_per_box` = 24 
WHERE `category` = 'milk' AND `pieces_per_box` IS NULL;

UPDATE `products` SET 
    `base_unit` = 'cup', 
    `box_unit` = 'tray', 
    `pieces_per_box` = 12 
WHERE `category` = 'yogurt' AND `pieces_per_box` IS NULL;

UPDATE `products` SET 
    `base_unit` = 'piece', 
    `box_unit` = 'box', 
    `pieces_per_box` = 10 
WHERE `category` = 'cheese' AND `pieces_per_box` IS NULL;

UPDATE `products` SET 
    `base_unit` = 'pack', 
    `box_unit` = 'case', 
    `pieces_per_box` = 20 
WHERE `category` = 'butter' AND `pieces_per_box` IS NULL;

-- Insert into product_units table for existing products
INSERT IGNORE INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT 
    id,
    COALESCE(base_unit, 'piece'),
    COALESCE(box_unit, 'box'),
    COALESCE(pieces_per_box, 1),
    CONCAT('Auto-configured for ', product_name)
FROM products
WHERE id NOT IN (SELECT product_id FROM product_units);

-- =====================================================
-- Sample Product Unit Configurations (as requested)
-- =====================================================

-- Insert specific unit configurations for common products
-- Note: These use INSERT ... ON DUPLICATE KEY UPDATE to handle existing records

-- Milk Bar: 1 Box = 50 Pieces
INSERT INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT id, 'piece', 'box', 50, 'Milk Bar - 1 Box = 50 Pieces'
FROM products WHERE product_name LIKE '%Milk Bar%'
ON DUPLICATE KEY UPDATE 
    `base_unit` = VALUES(`base_unit`),
    `box_unit` = VALUES(`box_unit`),
    `pieces_per_box` = VALUES(`pieces_per_box`);

-- Fresh Milk 200ml: 1 Crate = 24 Bottles
INSERT INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT id, 'bottle', 'crate', 24, 'Fresh Milk 200ml - 1 Crate = 24 Bottles'
FROM products WHERE product_name LIKE '%Fresh Milk%' AND unit_size = 200
ON DUPLICATE KEY UPDATE 
    `base_unit` = VALUES(`base_unit`),
    `box_unit` = VALUES(`box_unit`),
    `pieces_per_box` = VALUES(`pieces_per_box`);

-- Fresh Milk 330ml: 1 Crate = 24 Bottles
INSERT INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT id, 'bottle', 'crate', 24, 'Fresh Milk 330ml - 1 Crate = 24 Bottles'
FROM products WHERE product_name LIKE '%Fresh Milk%' AND unit_size = 330
ON DUPLICATE KEY UPDATE 
    `base_unit` = VALUES(`base_unit`),
    `box_unit` = VALUES(`box_unit`),
    `pieces_per_box` = VALUES(`pieces_per_box`);

-- Choco Milk 330ml: 1 Case = 24 Bottles
INSERT INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT id, 'bottle', 'case', 24, 'Choco Milk 330ml - 1 Case = 24 Bottles'
FROM products WHERE product_name LIKE '%Choco%' AND category = 'flavored_milk'
ON DUPLICATE KEY UPDATE 
    `base_unit` = VALUES(`base_unit`),
    `box_unit` = VALUES(`box_unit`),
    `pieces_per_box` = VALUES(`pieces_per_box`);

-- Butter 250g: 1 Case = 20 Packs
INSERT INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT id, 'pack', 'case', 20, 'Butter 250g - 1 Case = 20 Packs'
FROM products WHERE category = 'butter'
ON DUPLICATE KEY UPDATE 
    `base_unit` = VALUES(`base_unit`),
    `box_unit` = VALUES(`box_unit`),
    `pieces_per_box` = VALUES(`pieces_per_box`);

-- Yogurt: 1 Tray = 12 Cups
INSERT INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT id, 'cup', 'tray', 12, 'Yogurt - 1 Tray = 12 Cups'
FROM products WHERE category = 'yogurt'
ON DUPLICATE KEY UPDATE 
    `base_unit` = VALUES(`base_unit`),
    `box_unit` = VALUES(`box_unit`),
    `pieces_per_box` = VALUES(`pieces_per_box`);

-- Cheese: 1 Box = 10 Pieces
INSERT INTO `product_units` (`product_id`, `base_unit`, `box_unit`, `pieces_per_box`, `notes`)
SELECT id, 'piece', 'box', 10, 'Cheese - 1 Box = 10 Pieces'
FROM products WHERE category = 'cheese'
ON DUPLICATE KEY UPDATE 
    `base_unit` = VALUES(`base_unit`),
    `box_unit` = VALUES(`box_unit`),
    `pieces_per_box` = VALUES(`pieces_per_box`);

-- =====================================================
-- MIGRATE: Existing inventory to multi-unit format
-- Convert existing quantity to boxes + pieces
-- Note: finished_goods_inventory links via batch_id to production_batches
-- We'll use product_type mapping for now since there's no direct product_id
-- =====================================================

-- Set default pieces_per_box based on product_type
-- (Simple migration: assume 24 pieces per box for bottles, 50 for milk bars, etc.)
UPDATE finished_goods_inventory fgi
SET 
    fgi.quantity_boxes = CASE 
        WHEN fgi.product_type = 'milk_bar' THEN FLOOR(fgi.quantity / 50)
        WHEN fgi.product_type = 'bottled_milk' THEN FLOOR(fgi.quantity / 24)
        WHEN fgi.product_type = 'yogurt' THEN FLOOR(fgi.quantity / 12)
        WHEN fgi.product_type = 'butter' THEN FLOOR(fgi.quantity / 20)
        WHEN fgi.product_type = 'cheese' THEN FLOOR(fgi.quantity / 10)
        ELSE FLOOR(fgi.quantity / 1)
    END,
    fgi.quantity_pieces = CASE 
        WHEN fgi.product_type = 'milk_bar' THEN fgi.quantity MOD 50
        WHEN fgi.product_type = 'bottled_milk' THEN fgi.quantity MOD 24
        WHEN fgi.product_type = 'yogurt' THEN fgi.quantity MOD 12
        WHEN fgi.product_type = 'butter' THEN fgi.quantity MOD 20
        WHEN fgi.product_type = 'cheese' THEN fgi.quantity MOD 10
        ELSE fgi.quantity MOD 1
    END,
    fgi.boxes_available = CASE 
        WHEN fgi.product_type = 'milk_bar' THEN FLOOR(fgi.quantity_available / 50)
        WHEN fgi.product_type = 'bottled_milk' THEN FLOOR(fgi.quantity_available / 24)
        WHEN fgi.product_type = 'yogurt' THEN FLOOR(fgi.quantity_available / 12)
        WHEN fgi.product_type = 'butter' THEN FLOOR(fgi.quantity_available / 20)
        WHEN fgi.product_type = 'cheese' THEN FLOOR(fgi.quantity_available / 10)
        ELSE FLOOR(fgi.quantity_available / 1)
    END,
    fgi.pieces_available = CASE 
        WHEN fgi.product_type = 'milk_bar' THEN fgi.quantity_available MOD 50
        WHEN fgi.product_type = 'bottled_milk' THEN fgi.quantity_available MOD 24
        WHEN fgi.product_type = 'yogurt' THEN fgi.quantity_available MOD 12
        WHEN fgi.product_type = 'butter' THEN fgi.quantity_available MOD 20
        WHEN fgi.product_type = 'cheese' THEN fgi.quantity_available MOD 10
        ELSE fgi.quantity_available MOD 1
    END
WHERE fgi.quantity_boxes = 0 AND fgi.quantity_pieces = 0;

-- =====================================================
-- CREATE VIEW: vw_inventory_multi_unit
-- Shows inventory in "X Boxes + Y Pieces" format
-- Note: finished_goods_inventory uses product_name, product_type, not product_id
-- =====================================================

CREATE OR REPLACE VIEW `vw_inventory_multi_unit` AS
SELECT 
    fgi.id,
    fgi.batch_id,
    fgi.product_name,
    fgi.product_type,
    fgi.product_variant as variant,
    fgi.size_ml,
    fgi.unit,
    
    -- Unit configuration based on product_type
    CASE 
        WHEN fgi.product_type = 'milk_bar' THEN 'bar'
        WHEN fgi.product_type = 'bottled_milk' THEN 'bottle'
        WHEN fgi.product_type = 'yogurt' THEN 'cup'
        WHEN fgi.product_type = 'butter' THEN 'pack'
        WHEN fgi.product_type = 'cheese' THEN 'pack'
        ELSE 'piece'
    END as base_unit,
    CASE 
        WHEN fgi.product_type = 'milk_bar' THEN 'box'
        WHEN fgi.product_type = 'bottled_milk' THEN 'crate'
        WHEN fgi.product_type = 'yogurt' THEN 'tray'
        WHEN fgi.product_type = 'butter' THEN 'case'
        WHEN fgi.product_type = 'cheese' THEN 'box'
        ELSE 'box'
    END as box_unit,
    CASE 
        WHEN fgi.product_type = 'milk_bar' THEN 50
        WHEN fgi.product_type = 'bottled_milk' THEN 24
        WHEN fgi.product_type = 'yogurt' THEN 12
        WHEN fgi.product_type = 'butter' THEN 20
        WHEN fgi.product_type = 'cheese' THEN 10
        ELSE 1
    END as pieces_per_box,
    
    -- Multi-unit quantities
    fgi.quantity_boxes,
    fgi.quantity_pieces,
    fgi.boxes_available,
    fgi.pieces_available,
    
    -- Formatted display: "8 Boxes + 14 Pieces"
    CONCAT(
        fgi.quantity_boxes, ' ',
        CASE 
            WHEN fgi.product_type = 'milk_bar' THEN IF(fgi.quantity_boxes != 1, 'boxes', 'box')
            WHEN fgi.product_type = 'bottled_milk' THEN IF(fgi.quantity_boxes != 1, 'crates', 'crate')
            WHEN fgi.product_type = 'yogurt' THEN IF(fgi.quantity_boxes != 1, 'trays', 'tray')
            WHEN fgi.product_type = 'butter' THEN IF(fgi.quantity_boxes != 1, 'cases', 'case')
            WHEN fgi.product_type = 'cheese' THEN IF(fgi.quantity_boxes != 1, 'boxes', 'box')
            ELSE IF(fgi.quantity_boxes != 1, 'boxes', 'box')
        END,
        ' + ',
        fgi.quantity_pieces, ' ',
        CASE 
            WHEN fgi.product_type = 'milk_bar' THEN IF(fgi.quantity_pieces != 1, 'bars', 'bar')
            WHEN fgi.product_type = 'bottled_milk' THEN IF(fgi.quantity_pieces != 1, 'bottles', 'bottle')
            WHEN fgi.product_type = 'yogurt' THEN IF(fgi.quantity_pieces != 1, 'cups', 'cup')
            WHEN fgi.product_type = 'butter' THEN IF(fgi.quantity_pieces != 1, 'packs', 'pack')
            WHEN fgi.product_type = 'cheese' THEN IF(fgi.quantity_pieces != 1, 'packs', 'pack')
            ELSE IF(fgi.quantity_pieces != 1, 'pieces', 'piece')
        END
    ) as quantity_display,
    
    CONCAT(
        fgi.boxes_available, ' ',
        CASE 
            WHEN fgi.product_type = 'milk_bar' THEN IF(fgi.boxes_available != 1, 'boxes', 'box')
            WHEN fgi.product_type = 'bottled_milk' THEN IF(fgi.boxes_available != 1, 'crates', 'crate')
            WHEN fgi.product_type = 'yogurt' THEN IF(fgi.boxes_available != 1, 'trays', 'tray')
            WHEN fgi.product_type = 'butter' THEN IF(fgi.boxes_available != 1, 'cases', 'case')
            WHEN fgi.product_type = 'cheese' THEN IF(fgi.boxes_available != 1, 'boxes', 'box')
            ELSE IF(fgi.boxes_available != 1, 'boxes', 'box')
        END,
        ' + ',
        fgi.pieces_available, ' ',
        CASE 
            WHEN fgi.product_type = 'milk_bar' THEN IF(fgi.pieces_available != 1, 'bars', 'bar')
            WHEN fgi.product_type = 'bottled_milk' THEN IF(fgi.pieces_available != 1, 'bottles', 'bottle')
            WHEN fgi.product_type = 'yogurt' THEN IF(fgi.pieces_available != 1, 'cups', 'cup')
            WHEN fgi.product_type = 'butter' THEN IF(fgi.pieces_available != 1, 'packs', 'pack')
            WHEN fgi.product_type = 'cheese' THEN IF(fgi.pieces_available != 1, 'packs', 'pack')
            ELSE IF(fgi.pieces_available != 1, 'pieces', 'piece')
        END
    ) as available_display,
    
    -- Total pieces calculation (for compatibility)
    (fgi.quantity_boxes * CASE 
        WHEN fgi.product_type = 'milk_bar' THEN 50
        WHEN fgi.product_type = 'bottled_milk' THEN 24
        WHEN fgi.product_type = 'yogurt' THEN 12
        WHEN fgi.product_type = 'butter' THEN 20
        WHEN fgi.product_type = 'cheese' THEN 10
        ELSE 1
    END) + fgi.quantity_pieces as total_pieces,
    (fgi.boxes_available * CASE 
        WHEN fgi.product_type = 'milk_bar' THEN 50
        WHEN fgi.product_type = 'bottled_milk' THEN 24
        WHEN fgi.product_type = 'yogurt' THEN 12
        WHEN fgi.product_type = 'butter' THEN 20
        WHEN fgi.product_type = 'cheese' THEN 10
        ELSE 1
    END) + fgi.pieces_available as total_pieces_available,
    
    -- Legacy fields
    fgi.quantity,
    fgi.quantity_available,
    fgi.remaining_quantity,
    
    -- Other inventory data
    fgi.manufacturing_date,
    fgi.expiry_date,
    DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
    fgi.barcode,
    fgi.chiller_id,
    fgi.chiller_location,
    fgi.status,
    fgi.received_at
FROM finished_goods_inventory fgi;

-- =====================================================
-- CREATE VIEW: vw_product_units_summary
-- Quick reference for product unit configurations
-- =====================================================

CREATE OR REPLACE VIEW `vw_product_units_summary` AS
SELECT 
    p.id as product_id,
    p.product_code,
    p.product_name,
    p.variant,
    p.category,
    p.unit_size,
    p.unit_measure,
    COALESCE(pu.base_unit, p.base_unit, 'piece') as base_unit,
    COALESCE(pu.box_unit, p.box_unit, 'box') as box_unit,
    COALESCE(pu.pieces_per_box, p.pieces_per_box, 1) as pieces_per_box,
    CONCAT('1 ', COALESCE(pu.box_unit, p.box_unit, 'box'), ' = ', 
           COALESCE(pu.pieces_per_box, p.pieces_per_box, 1), ' ', 
           COALESCE(pu.base_unit, p.base_unit, 'piece'), 's') as conversion_display
FROM products p
LEFT JOIN product_units pu ON p.id = pu.product_id
WHERE p.is_active = 1;

-- =====================================================
-- CREATE VIEW: vw_box_opening_history
-- History of box openings for audit
-- Uses finished_goods_inventory fields directly
-- =====================================================

CREATE OR REPLACE VIEW `vw_box_opening_history` AS
SELECT 
    bol.id,
    bol.opening_code,
    bol.inventory_id,
    fgi.barcode as inventory_barcode,
    fgi.product_name,
    fgi.product_type,
    fgi.product_variant,
    bol.boxes_opened,
    bol.pieces_from_opening,
    CASE 
        WHEN fgi.product_type = 'milk_bar' THEN 'box'
        WHEN fgi.product_type = 'bottled_milk' THEN 'crate'
        WHEN fgi.product_type = 'yogurt' THEN 'tray'
        WHEN fgi.product_type = 'butter' THEN 'case'
        WHEN fgi.product_type = 'cheese' THEN 'box'
        ELSE 'box'
    END as box_unit,
    CASE 
        WHEN fgi.product_type = 'milk_bar' THEN 'bar'
        WHEN fgi.product_type = 'bottled_milk' THEN 'bottle'
        WHEN fgi.product_type = 'yogurt' THEN 'cup'
        WHEN fgi.product_type = 'butter' THEN 'pack'
        WHEN fgi.product_type = 'cheese' THEN 'pack'
        ELSE 'piece'
    END as base_unit,
    bol.reason,
    bol.reference_type,
    bol.reference_id,
    bol.opened_by,
    CONCAT(u.first_name, ' ', u.last_name) as opened_by_name,
    bol.opened_at,
    bol.notes,
    bol.created_at
FROM box_opening_log bol
LEFT JOIN finished_goods_inventory fgi ON bol.inventory_id = fgi.id
LEFT JOIN users u ON bol.opened_by = u.id
ORDER BY bol.opened_at DESC;

-- =====================================================
-- STORED PROCEDURE: sp_open_box
-- Opens a box and converts to pieces for partial sales
-- Note: Uses product_type mapping since finished_goods_inventory has no product_id
-- =====================================================

DELIMITER //

DROP PROCEDURE IF EXISTS `sp_open_box` //

CREATE PROCEDURE `sp_open_box`(
    IN p_inventory_id INT,
    IN p_boxes_to_open INT,
    IN p_reason VARCHAR(50),
    IN p_reference_type VARCHAR(50),
    IN p_reference_id INT,
    IN p_opened_by INT,
    IN p_notes TEXT,
    OUT p_opening_code VARCHAR(30),
    OUT p_pieces_added INT,
    OUT p_success TINYINT
)
BEGIN
    DECLARE v_product_type VARCHAR(50);
    DECLARE v_current_boxes INT;
    DECLARE v_pieces_per_box INT;
    DECLARE v_new_pieces INT;
    DECLARE v_opening_count INT;
    
    -- Initialize
    SET p_success = 0;
    SET p_pieces_added = 0;
    
    -- Get inventory info using product_type
    SELECT fgi.product_type, fgi.boxes_available, 
           CASE 
               WHEN fgi.product_type = 'milk_bar' THEN 50
               WHEN fgi.product_type = 'bottled_milk' THEN 24
               WHEN fgi.product_type = 'yogurt' THEN 12
               WHEN fgi.product_type = 'butter' THEN 20
               WHEN fgi.product_type = 'cheese' THEN 10
               ELSE 1
           END
    INTO v_product_type, v_current_boxes, v_pieces_per_box
    FROM finished_goods_inventory fgi
    WHERE fgi.id = p_inventory_id AND fgi.status = 'available';
    
    -- Validate
    IF v_product_type IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid inventory ID or inventory not available';
    END IF;
    
    IF v_current_boxes < p_boxes_to_open THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Not enough boxes available to open';
    END IF;
    
    -- Calculate pieces
    SET v_new_pieces = p_boxes_to_open * v_pieces_per_box;
    SET p_pieces_added = v_new_pieces;
    
    -- Generate opening code
    SELECT COUNT(*) + 1 INTO v_opening_count 
    FROM box_opening_log 
    WHERE DATE(opened_at) = CURDATE();
    
    SET p_opening_code = CONCAT('BOX-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(v_opening_count, 3, '0'));
    
    -- Update inventory: reduce boxes, increase pieces
    UPDATE finished_goods_inventory
    SET 
        quantity_boxes = quantity_boxes - p_boxes_to_open,
        quantity_pieces = quantity_pieces + v_new_pieces,
        boxes_available = boxes_available - p_boxes_to_open,
        pieces_available = pieces_available + v_new_pieces
    WHERE id = p_inventory_id;
    
    -- Log the box opening (product_id is NULL since we use product_type)
    INSERT INTO box_opening_log (
        opening_code, inventory_id, product_id, boxes_opened, pieces_from_opening,
        reason, reference_type, reference_id, opened_by, opened_at, notes
    ) VALUES (
        p_opening_code, p_inventory_id, NULL, p_boxes_to_open, v_new_pieces,
        p_reason, p_reference_type, p_reference_id, p_opened_by, NOW(), p_notes
    );
    
    SET p_success = 1;
END //

DELIMITER ;

-- =====================================================
-- STORED PROCEDURE: sp_calculate_boxes_pieces
-- Helper to convert total pieces to boxes + pieces
-- =====================================================

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS `sp_calculate_boxes_pieces`(
    IN p_product_id INT,
    IN p_total_pieces INT,
    OUT p_boxes INT,
    OUT p_pieces INT
)
BEGIN
    DECLARE v_pieces_per_box INT DEFAULT 1;
    
    -- Get pieces per box for product
    SELECT COALESCE(pu.pieces_per_box, p.pieces_per_box, 1)
    INTO v_pieces_per_box
    FROM products p
    LEFT JOIN product_units pu ON p.id = pu.product_id
    WHERE p.id = p_product_id;
    
    -- Calculate
    SET p_boxes = FLOOR(p_total_pieces / v_pieces_per_box);
    SET p_pieces = p_total_pieces MOD v_pieces_per_box;
END //

DELIMITER ;

-- =====================================================
-- FUNCTION: fn_format_multi_unit
-- Returns formatted string like "8 Boxes + 14 Pieces"
-- =====================================================

DELIMITER //

CREATE FUNCTION IF NOT EXISTS `fn_format_multi_unit`(
    p_boxes INT,
    p_pieces INT,
    p_box_unit VARCHAR(20),
    p_base_unit VARCHAR(20)
) RETURNS VARCHAR(100)
DETERMINISTIC
BEGIN
    DECLARE v_result VARCHAR(100);
    
    SET v_result = CONCAT(
        p_boxes, ' ', COALESCE(p_box_unit, 'box'), IF(p_boxes != 1, 's', ''),
        ' + ',
        p_pieces, ' ', COALESCE(p_base_unit, 'piece'), IF(p_pieces != 1, 's', '')
    );
    
    RETURN v_result;
END //

DELIMITER ;

-- =====================================================
-- FUNCTION: fn_total_pieces
-- Converts boxes + pieces to total pieces
-- =====================================================

DELIMITER //

CREATE FUNCTION IF NOT EXISTS `fn_total_pieces`(
    p_product_id INT,
    p_boxes INT,
    p_pieces INT
) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_pieces_per_box INT DEFAULT 1;
    
    SELECT COALESCE(pu.pieces_per_box, p.pieces_per_box, 1)
    INTO v_pieces_per_box
    FROM products p
    LEFT JOIN product_units pu ON p.id = pu.product_id
    WHERE p.id = p_product_id;
    
    RETURN (p_boxes * v_pieces_per_box) + p_pieces;
END //

DELIMITER ;

-- =====================================================
-- TRIGGER: trg_fg_inventory_multi_unit_sync
-- Keeps quantity and quantity_available in sync with multi-unit
-- Uses product_type mapping since finished_goods_inventory has no product_id
-- =====================================================

DELIMITER //

DROP TRIGGER IF EXISTS `trg_fg_inventory_before_update` //

CREATE TRIGGER `trg_fg_inventory_before_update`
BEFORE UPDATE ON `finished_goods_inventory`
FOR EACH ROW
BEGIN
    DECLARE v_pieces_per_box INT DEFAULT 1;
    
    -- Get pieces per box based on product_type
    SET v_pieces_per_box = CASE 
        WHEN NEW.product_type = 'milk_bar' THEN 50
        WHEN NEW.product_type = 'bottled_milk' THEN 24
        WHEN NEW.product_type = 'yogurt' THEN 12
        WHEN NEW.product_type = 'butter' THEN 20
        WHEN NEW.product_type = 'cheese' THEN 10
        ELSE 1
    END;
    
    -- Sync quantity with boxes + pieces
    IF NEW.quantity_boxes != OLD.quantity_boxes OR NEW.quantity_pieces != OLD.quantity_pieces THEN
        SET NEW.quantity = (NEW.quantity_boxes * v_pieces_per_box) + NEW.quantity_pieces;
    END IF;
    
    -- Sync quantity_available with boxes_available + pieces_available
    IF NEW.boxes_available != OLD.boxes_available OR NEW.pieces_available != OLD.pieces_available THEN
        SET NEW.quantity_available = (NEW.boxes_available * v_pieces_per_box) + NEW.pieces_available;
    END IF;
END //

DELIMITER ;

-- =====================================================
-- Completion Message
-- =====================================================

SELECT 'Multi-Unit Inventory migration completed successfully!' as status;
SELECT 'Tables modified: products, finished_goods_inventory, delivery_receipt_items, sales_order_items, fg_inventory_transactions, fg_dispatch_log, fg_receiving' as modified_tables;
SELECT 'New table created: product_units, box_opening_log' as new_tables;
SELECT 'New views: vw_inventory_multi_unit, vw_product_units_summary, vw_box_opening_history' as new_views;
SELECT 'New procedures: sp_open_box, sp_calculate_boxes_pieces' as new_procedures;
SELECT 'New functions: fn_format_multi_unit, fn_total_pieces' as new_functions;
