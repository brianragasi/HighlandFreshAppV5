-- =====================================================
-- Highland Fresh System - Warehouse Raw Module Migration
-- Purpose: Create tables for raw materials warehouse management
-- Version: 4.0
-- Run this ONCE on your existing database
-- =====================================================

USE `highland_fresh`;

-- =====================================================
-- TABLE: storage_tanks (Raw Milk Storage Tanks)
-- Tracks physical tanks and their current contents
-- =====================================================

CREATE TABLE IF NOT EXISTS `storage_tanks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tank_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'e.g., TANK-01, TANK-02',
    `tank_name` VARCHAR(100) NOT NULL,
    `capacity_liters` DECIMAL(10,2) NOT NULL COMMENT 'Maximum capacity',
    `current_volume` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current milk volume',
    `location` VARCHAR(100) NULL COMMENT 'Physical location in facility',
    `tank_type` ENUM('primary', 'secondary', 'holding', 'chiller') NOT NULL DEFAULT 'primary',
    `temperature_celsius` DECIMAL(4,1) NULL COMMENT 'Current temperature',
    `last_cleaned_at` DATETIME NULL,
    `status` ENUM('available', 'in_use', 'cleaning', 'maintenance', 'offline') NOT NULL DEFAULT 'available',
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tank_code` (`tank_code`),
    INDEX `idx_status` (`status`),
    INDEX `idx_tank_type` (`tank_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: tank_milk_batches (Milk in Tanks - Links QC approved milk to tanks)
-- Tracks individual milk batches stored in tanks for FIFO
-- =====================================================

CREATE TABLE IF NOT EXISTS `tank_milk_batches` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tank_id` INT(11) NOT NULL,
    `raw_milk_inventory_id` INT(11) NOT NULL COMMENT 'Links to raw_milk_inventory from QC',
    `volume_liters` DECIMAL(10,2) NOT NULL,
    `remaining_liters` DECIMAL(10,2) NOT NULL,
    `received_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `received_by` INT(11) NOT NULL,
    `status` ENUM('available', 'partially_used', 'consumed', 'expired', 'transferred') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tank` (`tank_id`),
    INDEX `idx_raw_milk` (`raw_milk_inventory_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expiry` (`expiry_date`),
    INDEX `idx_fifo` (`received_date`, `id`),
    FOREIGN KEY (`tank_id`) REFERENCES `storage_tanks`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`raw_milk_inventory_id`) REFERENCES `raw_milk_inventory`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: ingredient_categories (Categories for ingredients)
-- =====================================================

CREATE TABLE IF NOT EXISTS `ingredient_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_code` VARCHAR(20) NOT NULL UNIQUE,
    `category_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: ingredients (Master list of ingredients)
-- Sugar, powder, flavors, rennet, salt, packaging materials
-- =====================================================

CREATE TABLE IF NOT EXISTS `ingredients` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ingredient_code` VARCHAR(30) NOT NULL UNIQUE,
    `ingredient_name` VARCHAR(150) NOT NULL,
    `category_id` INT(11) NULL,
    `unit_of_measure` VARCHAR(20) NOT NULL COMMENT 'kg, L, pcs, g, ml',
    `minimum_stock` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Reorder point',
    `current_stock` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(10,2) NULL COMMENT 'Average unit cost',
    `storage_location` VARCHAR(100) NULL,
    `storage_requirements` TEXT NULL COMMENT 'e.g., Keep refrigerated',
    `shelf_life_days` INT(11) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ingredient_code` (`ingredient_code`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_low_stock` (`current_stock`, `minimum_stock`),
    FOREIGN KEY (`category_id`) REFERENCES `ingredient_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: ingredient_batches (Ingredient inventory batches for FIFO)
-- =====================================================

CREATE TABLE IF NOT EXISTS `ingredient_batches` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `batch_code` VARCHAR(30) NOT NULL UNIQUE,
    `ingredient_id` INT(11) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `remaining_quantity` DECIMAL(10,2) NOT NULL,
    `unit_cost` DECIMAL(10,2) NULL,
    `supplier_name` VARCHAR(150) NULL,
    `supplier_batch_no` VARCHAR(50) NULL COMMENT 'Supplier batch/lot number',
    `received_date` DATE NOT NULL,
    `expiry_date` DATE NULL,
    `received_by` INT(11) NOT NULL,
    `status` ENUM('available', 'partially_used', 'consumed', 'expired', 'returned') NOT NULL DEFAULT 'available',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batch_code` (`batch_code`),
    INDEX `idx_ingredient` (`ingredient_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expiry` (`expiry_date`),
    INDEX `idx_fifo` (`received_date`, `id`),
    FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: mro_categories (MRO = Maintenance, Repair, Operations)
-- =====================================================

CREATE TABLE IF NOT EXISTS `mro_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_code` VARCHAR(20) NOT NULL UNIQUE,
    `category_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: mro_items (MRO Parts & Supplies)
-- Spare parts, tools, cleaning supplies, safety equipment
-- =====================================================

CREATE TABLE IF NOT EXISTS `mro_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `item_code` VARCHAR(30) NOT NULL UNIQUE,
    `item_name` VARCHAR(150) NOT NULL,
    `category_id` INT(11) NULL,
    `unit_of_measure` VARCHAR(20) NOT NULL DEFAULT 'pcs',
    `minimum_stock` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `current_stock` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(10,2) NULL,
    `storage_location` VARCHAR(100) NULL,
    `compatible_equipment` TEXT NULL COMMENT 'List of equipment this part is for',
    `is_critical` TINYINT(1) DEFAULT 0 COMMENT 'Critical spare part flag',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_item_code` (`item_code`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_low_stock` (`current_stock`, `minimum_stock`),
    INDEX `idx_critical` (`is_critical`),
    FOREIGN KEY (`category_id`) REFERENCES `mro_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: mro_inventory (MRO Stock batches)
-- =====================================================

CREATE TABLE IF NOT EXISTS `mro_inventory` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `batch_code` VARCHAR(30) NOT NULL UNIQUE,
    `mro_item_id` INT(11) NOT NULL,
    `quantity` DECIMAL(10,2) NOT NULL,
    `remaining_quantity` DECIMAL(10,2) NOT NULL,
    `unit_cost` DECIMAL(10,2) NULL,
    `supplier_name` VARCHAR(150) NULL,
    `received_date` DATE NOT NULL,
    `received_by` INT(11) NOT NULL,
    `status` ENUM('available', 'partially_used', 'consumed', 'returned') NOT NULL DEFAULT 'available',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_batch_code` (`batch_code`),
    INDEX `idx_mro_item` (`mro_item_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_fifo` (`received_date`, `id`),
    FOREIGN KEY (`mro_item_id`) REFERENCES `mro_items`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: requisition_items (Items within a requisition)
-- Links to ingredient_requisitions table
-- =====================================================

CREATE TABLE IF NOT EXISTS `requisition_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `requisition_id` INT(11) NOT NULL,
    `item_type` ENUM('raw_milk', 'ingredient', 'mro') NOT NULL,
    `item_id` INT(11) NOT NULL COMMENT 'References ingredient_id or mro_item_id based on item_type',
    `item_code` VARCHAR(30) NOT NULL,
    `item_name` VARCHAR(150) NOT NULL,
    `requested_quantity` DECIMAL(10,2) NOT NULL,
    `issued_quantity` DECIMAL(10,2) NULL COMMENT 'Actual quantity issued',
    `unit_of_measure` VARCHAR(20) NOT NULL,
    `status` ENUM('pending', 'partial', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'pending',
    `fulfilled_by` INT(11) NULL,
    `fulfilled_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_requisition` (`requisition_id`),
    INDEX `idx_item_type` (`item_type`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`requisition_id`) REFERENCES `ingredient_requisitions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`fulfilled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: inventory_transactions (Movement tracking)
-- Tracks all ins and outs for audit trail
-- =====================================================

CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transaction_code` VARCHAR(30) NOT NULL UNIQUE,
    `transaction_type` ENUM('receive', 'issue', 'adjust', 'transfer', 'return', 'dispose') NOT NULL,
    `item_type` ENUM('raw_milk', 'ingredient', 'mro') NOT NULL,
    `item_id` INT(11) NOT NULL,
    `batch_id` INT(11) NULL COMMENT 'Specific batch affected',
    `quantity` DECIMAL(10,2) NOT NULL,
    `unit_of_measure` VARCHAR(20) NOT NULL,
    `reference_type` VARCHAR(50) NULL COMMENT 'e.g., requisition, purchase_order, adjustment',
    `reference_id` INT(11) NULL,
    `from_location` VARCHAR(100) NULL,
    `to_location` VARCHAR(100) NULL,
    `performed_by` INT(11) NOT NULL,
    `reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_transaction_code` (`transaction_code`),
    INDEX `idx_transaction_type` (`transaction_type`),
    INDEX `idx_item` (`item_type`, `item_id`),
    INDEX `idx_reference` (`reference_type`, `reference_id`),
    INDEX `idx_date` (`created_at`),
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ALTER: ingredient_requisitions
-- Add columns for warehouse raw fulfillment workflow
-- =====================================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'ingredient_requisitions' AND COLUMN_NAME = 'department');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ingredient_requisitions ADD COLUMN department ENUM("production", "maintenance", "other") NOT NULL DEFAULT "production" AFTER requested_by', 
    'SELECT "department already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'ingredient_requisitions' AND COLUMN_NAME = 'needed_by_date');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ingredient_requisitions ADD COLUMN needed_by_date DATE NULL AFTER priority', 
    'SELECT "needed_by_date already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'ingredient_requisitions' AND COLUMN_NAME = 'fulfilled_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ingredient_requisitions ADD COLUMN fulfilled_by INT(11) NULL AFTER approved_at', 
    'SELECT "fulfilled_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'ingredient_requisitions' AND COLUMN_NAME = 'fulfilled_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE ingredient_requisitions ADD COLUMN fulfilled_at DATETIME NULL AFTER fulfilled_by', 
    'SELECT "fulfilled_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- INSERT: Default Ingredient Categories
-- =====================================================

INSERT IGNORE INTO `ingredient_categories` (`category_code`, `category_name`, `description`) VALUES
('CAT-DAIRY', 'Dairy Additives', 'Milk powder, cream, cultures'),
('CAT-SWEET', 'Sweeteners', 'Sugar, honey, syrups'),
('CAT-FLAVOR', 'Flavorings', 'Chocolate, melon, vanilla extracts'),
('CAT-ADDITIVE', 'Processing Additives', 'Rennet, salt, stabilizers'),
('CAT-PACKAGE', 'Packaging Materials', 'Bottles, caps, labels, cartons');

-- =====================================================
-- INSERT: Default Ingredients
-- =====================================================

INSERT IGNORE INTO `ingredients` (`ingredient_code`, `ingredient_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `storage_requirements`, `shelf_life_days`) VALUES
('ING-001', 'White Sugar', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-SWEET'), 'kg', 50, 'Store in dry, cool place', 365),
('ING-002', 'Milk Powder', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-DAIRY'), 'kg', 25, 'Store in dry, cool place. Keep sealed.', 180),
('ING-003', 'Chocolate Flavoring', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-FLAVOR'), 'L', 10, 'Store at room temperature', 365),
('ING-004', 'Melon Flavoring', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-FLAVOR'), 'L', 10, 'Store at room temperature', 365),
('ING-005', 'Rennet (Liquid)', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-ADDITIVE'), 'L', 5, 'Refrigerate at 4°C', 90),
('ING-006', 'Salt (Iodized)', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-ADDITIVE'), 'kg', 20, 'Store in dry place', 730),
('ING-007', 'Yogurt Culture Starter', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-DAIRY'), 'g', 500, 'Keep frozen at -18°C', 365),
('ING-008', '330ml Bottles (PET)', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-PACKAGE'), 'pcs', 1000, 'Store in clean, dry area', NULL),
('ING-009', 'Bottle Caps (Blue)', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-PACKAGE'), 'pcs', 1000, 'Store in clean, dry area', NULL),
('ING-010', 'Product Labels (Milk)', (SELECT id FROM ingredient_categories WHERE category_code = 'CAT-PACKAGE'), 'pcs', 1000, 'Store away from heat', NULL);

-- =====================================================
-- INSERT: Default MRO Categories
-- =====================================================

INSERT IGNORE INTO `mro_categories` (`category_code`, `category_name`, `description`) VALUES
('MRO-SPARE', 'Spare Parts', 'Machine replacement parts'),
('MRO-TOOL', 'Tools', 'Hand tools and equipment'),
('MRO-CLEAN', 'Cleaning Supplies', 'Sanitation and cleaning materials'),
('MRO-SAFETY', 'Safety Equipment', 'PPE and safety supplies'),
('MRO-CONSUMABLE', 'Consumables', 'Lubricants, filters, etc.');

-- =====================================================
-- INSERT: Default MRO Items
-- =====================================================

INSERT IGNORE INTO `mro_items` (`item_code`, `item_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `compatible_equipment`, `is_critical`) VALUES
('MRO-001', 'Pasteurizer Gasket Set', (SELECT id FROM mro_categories WHERE category_code = 'MRO-SPARE'), 'set', 3, 'Pasteurizer', 1),
('MRO-002', 'Homogenizer Valve', (SELECT id FROM mro_categories WHERE category_code = 'MRO-SPARE'), 'pcs', 2, 'Homogenizer', 1),
('MRO-003', 'Fill-Seal Machine Nozzles', (SELECT id FROM mro_categories WHERE category_code = 'MRO-SPARE'), 'pcs', 5, 'Fill-Seal Machine', 1),
('MRO-004', 'Retort Pressure Gauge', (SELECT id FROM mro_categories WHERE category_code = 'MRO-SPARE'), 'pcs', 2, 'Retort', 1),
('MRO-005', 'Food-Grade Lubricant', (SELECT id FROM mro_categories WHERE category_code = 'MRO-CONSUMABLE'), 'L', 5, 'All machinery', 0),
('MRO-006', 'CIP Cleaning Solution', (SELECT id FROM mro_categories WHERE category_code = 'MRO-CLEAN'), 'L', 20, 'CIP System', 0),
('MRO-007', 'Sanitizer (Food-Safe)', (SELECT id FROM mro_categories WHERE category_code = 'MRO-CLEAN'), 'L', 10, 'General use', 0),
('MRO-008', 'Nitrile Gloves (Box)', (SELECT id FROM mro_categories WHERE category_code = 'MRO-SAFETY'), 'box', 10, NULL, 0),
('MRO-009', 'Hair Nets (Pack)', (SELECT id FROM mro_categories WHERE category_code = 'MRO-SAFETY'), 'pack', 20, NULL, 0),
('MRO-010', 'Temperature Probe', (SELECT id FROM mro_categories WHERE category_code = 'MRO-TOOL'), 'pcs', 2, 'All tanks', 1);

-- =====================================================
-- INSERT: Default Storage Tanks
-- =====================================================

INSERT IGNORE INTO `storage_tanks` (`tank_code`, `tank_name`, `capacity_liters`, `location`, `tank_type`) VALUES
('TANK-01', 'Primary Storage Tank 1', 1000.00, 'Receiving Area', 'primary'),
('TANK-02', 'Primary Storage Tank 2', 1000.00, 'Receiving Area', 'primary'),
('TANK-03', 'Holding Tank', 500.00, 'Pre-Production Area', 'holding'),
('TANK-04', 'Chiller Tank', 500.00, 'Cold Storage', 'chiller');

-- =====================================================
-- INSERT: Warehouse Raw User for Testing
-- =====================================================

INSERT IGNORE INTO `users` (`employee_id`, `username`, `password`, `first_name`, `last_name`, `email`, `role`) VALUES
('EMP-003', 'warehouse_raw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos', 'Mendoza', 'warehouse.raw@highlandfresh.com', 'warehouse_raw');

SELECT 'Warehouse Raw module migration completed successfully!' as status;
