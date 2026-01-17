-- =====================================================
-- Highland Fresh System - Warehouse FG Module Migration
-- Purpose: Create tables for finished goods warehouse management
-- Version: 4.0
-- Run this ONCE on your existing database
-- =====================================================

USE `highland_fresh`;

-- =====================================================
-- TABLE: chiller_locations (Chiller/Cold Storage Management)
-- Tracks physical chillers and their capacity
-- =====================================================

CREATE TABLE IF NOT EXISTS `chiller_locations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `chiller_code` VARCHAR(20) NOT NULL UNIQUE COMMENT 'e.g., CHILLER-01, CHILLER-02',
    `chiller_name` VARCHAR(100) NOT NULL,
    `capacity` INT(11) NOT NULL COMMENT 'Maximum units capacity',
    `current_count` INT(11) NOT NULL DEFAULT 0 COMMENT 'Current inventory count',
    `temperature_celsius` DECIMAL(4,1) NULL COMMENT 'Current temperature reading',
    `min_temperature` DECIMAL(4,1) DEFAULT 2.0 COMMENT 'Minimum safe temp',
    `max_temperature` DECIMAL(4,1) DEFAULT 8.0 COMMENT 'Maximum safe temp',
    `location` VARCHAR(100) NULL COMMENT 'Physical location in facility',
    `status` ENUM('available', 'full', 'maintenance', 'offline') NOT NULL DEFAULT 'available',
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_chiller_code` (`chiller_code`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: fg_receiving (Finished Goods Receiving from Production)
-- Records intake of finished goods from production floor
-- =====================================================

CREATE TABLE IF NOT EXISTS `fg_receiving` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `receiving_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g., FGR-20260117-001',
    `batch_id` INT(11) NOT NULL COMMENT 'Production batch received',
    `product_id` INT(11) NOT NULL,
    `quantity_received` INT(11) NOT NULL COMMENT 'Number of units received',
    `chiller_id` INT(11) NOT NULL COMMENT 'Chiller where stored',
    `received_by` INT(11) NOT NULL COMMENT 'Warehouse FG staff',
    `received_at` DATETIME NOT NULL,
    `barcode` VARCHAR(50) NULL COMMENT 'Batch barcode scanned',
    `manufacturing_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `status` ENUM('received', 'verified', 'rejected') NOT NULL DEFAULT 'received',
    `rejection_reason` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_receiving_code` (`receiving_code`),
    INDEX `idx_batch` (`batch_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_chiller` (`chiller_id`),
    INDEX `idx_received_at` (`received_at`),
    INDEX `idx_expiry` (`expiry_date`),
    FOREIGN KEY (`batch_id`) REFERENCES `production_batches`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: delivery_receipts (DR Generation for Dispatch)
-- Delivery Receipt header for customer deliveries
-- =====================================================

CREATE TABLE IF NOT EXISTS `delivery_receipts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `dr_number` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g., DR-20260117-001',
    `order_id` INT(11) NULL COMMENT 'Links to PO/Sales Order if applicable',
    `order_type` ENUM('institutional', 'walk_in', 'feeding_program', 'other') NOT NULL DEFAULT 'institutional',
    `customer_id` INT(11) NULL COMMENT 'Customer reference if exists',
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_address` TEXT NULL,
    `contact_number` VARCHAR(20) NULL,
    `delivery_date` DATE NOT NULL,
    `total_items` INT(11) NOT NULL DEFAULT 0,
    `total_quantity` INT(11) NOT NULL DEFAULT 0,
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('draft', 'pending', 'released', 'in_transit', 'delivered', 'cancelled') NOT NULL DEFAULT 'draft',
    `prepared_by` INT(11) NOT NULL COMMENT 'Warehouse FG staff',
    `prepared_at` DATETIME NULL,
    `released_by` INT(11) NULL COMMENT 'Staff who released',
    `released_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `driver_name` VARCHAR(100) NULL,
    `vehicle_plate` VARCHAR(20) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_dr_number` (`dr_number`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_delivery_date` (`delivery_date`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`prepared_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`released_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: delivery_receipt_items (Line Items in DR)
-- Individual items within a delivery receipt
-- =====================================================

CREATE TABLE IF NOT EXISTS `delivery_receipt_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `dr_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `inventory_id` INT(11) NOT NULL COMMENT 'Links to finished_goods_inventory',
    `product_name` VARCHAR(150) NOT NULL,
    `variant` VARCHAR(100) NULL,
    `size_value` DECIMAL(10,2) NOT NULL,
    `size_unit` VARCHAR(10) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `line_total` DECIMAL(12,2) NOT NULL,
    `barcode_scanned` VARCHAR(50) NULL COMMENT 'Barcode validated on release',
    `manufacturing_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `status` ENUM('pending', 'picked', 'released', 'cancelled') NOT NULL DEFAULT 'pending',
    `picked_at` DATETIME NULL,
    `released_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_dr` (`dr_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_inventory` (`inventory_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expiry` (`expiry_date`),
    FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: fg_dispatch_log (Dispatch/Release Audit Trail)
-- Tracks every release action for full traceability
-- =====================================================

CREATE TABLE IF NOT EXISTS `fg_dispatch_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `dispatch_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'e.g., DSP-20260117-001',
    `dr_id` INT(11) NOT NULL,
    `dr_item_id` INT(11) NULL COMMENT 'Specific DR line item',
    `inventory_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity_released` INT(11) NOT NULL,
    `barcode_scanned` VARCHAR(50) NULL,
    `chiller_id` INT(11) NULL COMMENT 'Chiller from which released',
    `released_by` INT(11) NOT NULL,
    `released_at` DATETIME NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_dispatch_code` (`dispatch_code`),
    INDEX `idx_dr` (`dr_id`),
    INDEX `idx_inventory` (`inventory_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_released_at` (`released_at`),
    INDEX `idx_chiller` (`chiller_id`),
    FOREIGN KEY (`dr_id`) REFERENCES `delivery_receipts`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`dr_item_id`) REFERENCES `delivery_receipt_items`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`chiller_id`) REFERENCES `chiller_locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`released_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: fg_inventory_transactions (FG Movement Tracking)
-- Tracks all finished goods movements for audit trail
-- =====================================================

CREATE TABLE IF NOT EXISTS `fg_inventory_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transaction_code` VARCHAR(30) NOT NULL UNIQUE,
    `transaction_type` ENUM('receive', 'release', 'transfer', 'adjust', 'dispose', 'return') NOT NULL,
    `inventory_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `quantity_before` INT(11) NOT NULL,
    `quantity_after` INT(11) NOT NULL,
    `reference_type` VARCHAR(50) NULL COMMENT 'e.g., fg_receiving, delivery_receipt',
    `reference_id` INT(11) NULL,
    `from_chiller_id` INT(11) NULL,
    `to_chiller_id` INT(11) NULL,
    `performed_by` INT(11) NOT NULL,
    `reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_transaction_code` (`transaction_code`),
    INDEX `idx_transaction_type` (`transaction_type`),
    INDEX `idx_inventory` (`inventory_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_reference` (`reference_type`, `reference_id`),
    INDEX `idx_date` (`created_at`),
    FOREIGN KEY (`inventory_id`) REFERENCES `finished_goods_inventory`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`from_chiller_id`) REFERENCES `chiller_locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`to_chiller_id`) REFERENCES `chiller_locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: customers (Customer Master for Institutional Orders)
-- Stores customer information for deliveries
-- =====================================================

CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_code` VARCHAR(20) NOT NULL UNIQUE,
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_type` ENUM('supermarket', 'school', 'feeding_program', 'restaurant', 'distributor', 'other') NOT NULL DEFAULT 'other',
    `contact_person` VARCHAR(100) NULL,
    `contact_number` VARCHAR(20) NULL,
    `email` VARCHAR(150) NULL,
    `address` TEXT NULL,
    `city` VARCHAR(100) NULL,
    `province` VARCHAR(100) NULL,
    `payment_terms` ENUM('cod', 'net_7', 'net_15', 'net_30', 'net_45', 'net_60') NOT NULL DEFAULT 'cod',
    `credit_limit` DECIMAL(12,2) DEFAULT 0.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_customer_code` (`customer_code`),
    INDEX `idx_customer_type` (`customer_type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ALTER: finished_goods_inventory
-- Add columns for chiller tracking and warehouse FG management
-- =====================================================

-- Add chiller_id foreign key column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'chiller_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN chiller_id INT(11) NULL AFTER chiller_location', 
    'SELECT "chiller_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add received_by column (Warehouse FG staff who received)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'received_by');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN received_by INT(11) NULL AFTER chiller_id', 
    'SELECT "received_by already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add received_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'received_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN received_at DATETIME NULL AFTER received_by', 
    'SELECT "received_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add reserved_quantity column (for orders not yet released)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'reserved_quantity');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN reserved_quantity INT(11) NOT NULL DEFAULT 0 AFTER quantity_available', 
    'SELECT "reserved_quantity already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add last_movement_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND COLUMN_NAME = 'last_movement_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD COLUMN last_movement_at DATETIME NULL AFTER received_at', 
    'SELECT "last_movement_at already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for chiller_id (only if column exists and FK doesn't)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'finished_goods_inventory' AND CONSTRAINT_NAME = 'fk_fg_inventory_chiller');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE finished_goods_inventory ADD CONSTRAINT fk_fg_inventory_chiller FOREIGN KEY (chiller_id) REFERENCES chiller_locations(id) ON DELETE SET NULL', 
    'SELECT "FK fk_fg_inventory_chiller already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- ALTER: delivery_receipts
-- Add customer_id foreign key if customers table exists
-- =====================================================

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = 'highland_fresh' AND TABLE_NAME = 'delivery_receipts' AND CONSTRAINT_NAME = 'fk_dr_customer');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE delivery_receipts ADD CONSTRAINT fk_dr_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL', 
    'SELECT "FK fk_dr_customer already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- INSERT: Default Chiller Locations
-- =====================================================

INSERT IGNORE INTO `chiller_locations` (`chiller_code`, `chiller_name`, `capacity`, `temperature_celsius`, `location`, `status`) VALUES
('CHILLER-01', 'Main Chiller A', 500, 4.0, 'FG Warehouse - Section A', 'available'),
('CHILLER-02', 'Main Chiller B', 500, 4.0, 'FG Warehouse - Section B', 'available'),
('CHILLER-03', 'Dispatch Chiller', 200, 4.0, 'Loading Area', 'available'),
('CHILLER-04', 'Reserve Chiller', 300, 4.0, 'FG Warehouse - Section C', 'available');

-- =====================================================
-- INSERT: Sample Customers
-- =====================================================

INSERT IGNORE INTO `customers` (`customer_code`, `customer_name`, `customer_type`, `contact_person`, `contact_number`, `address`, `city`, `payment_terms`) VALUES
('CUST-001', 'SM Supermarket - Bukidnon', 'supermarket', 'John Santos', '09171234567', 'SM City Malaybalay', 'Malaybalay City', 'net_30'),
('CUST-002', 'Gaisano Mall - Valencia', 'supermarket', 'Maria Cruz', '09181234567', 'Gaisano Grand Valencia', 'Valencia City', 'net_30'),
('CUST-003', 'DepEd Division of Bukidnon', 'feeding_program', 'Ana Reyes', '09191234567', 'DepEd Division Office', 'Malaybalay City', 'net_45'),
('CUST-004', 'Bukidnon State University', 'school', 'Pedro Garcia', '09201234567', 'BSU Main Campus', 'Malaybalay City', 'net_15');

-- =====================================================
-- INSERT: Warehouse FG User for Testing
-- Password: password (same as other test users)
-- =====================================================

INSERT IGNORE INTO `users` (`employee_id`, `username`, `password`, `first_name`, `last_name`, `email`, `role`) VALUES
('EMP-004', 'warehouse_fg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Roberto', 'Villanueva', 'warehouse.fg@highlandfresh.com', 'warehouse_fg');

-- =====================================================
-- CREATE VIEW: vw_fg_inventory_with_chiller
-- Convenient view for FG inventory with chiller details
-- =====================================================

CREATE OR REPLACE VIEW `vw_fg_inventory_with_chiller` AS
SELECT 
    fgi.id,
    fgi.batch_id,
    pb.batch_code,
    fgi.product_id,
    p.product_code,
    p.product_name,
    p.variant,
    p.size_value,
    p.size_unit,
    fgi.quantity,
    fgi.quantity_available,
    COALESCE(fgi.reserved_quantity, 0) as reserved_quantity,
    fgi.manufacturing_date,
    fgi.expiry_date,
    DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
    fgi.barcode,
    fgi.chiller_id,
    cl.chiller_code,
    cl.chiller_name,
    cl.temperature_celsius as chiller_temp,
    fgi.status,
    fgi.received_by,
    CONCAT(u.first_name, ' ', u.last_name) as received_by_name,
    fgi.received_at,
    fgi.created_at
FROM finished_goods_inventory fgi
LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
LEFT JOIN products p ON fgi.product_id = p.id
LEFT JOIN chiller_locations cl ON fgi.chiller_id = cl.id
LEFT JOIN users u ON fgi.received_by = u.id;

-- =====================================================
-- CREATE VIEW: vw_chiller_summary
-- Summary of chiller utilization
-- =====================================================

CREATE OR REPLACE VIEW `vw_chiller_summary` AS
SELECT 
    cl.id,
    cl.chiller_code,
    cl.chiller_name,
    cl.capacity,
    cl.current_count,
    COALESCE(SUM(fgi.quantity_available), 0) as actual_inventory,
    cl.temperature_celsius,
    cl.status,
    cl.location,
    ROUND((COALESCE(SUM(fgi.quantity_available), 0) / cl.capacity) * 100, 1) as utilization_percent
FROM chiller_locations cl
LEFT JOIN finished_goods_inventory fgi ON cl.id = fgi.chiller_id AND fgi.status = 'available'
WHERE cl.is_active = 1
GROUP BY cl.id;

-- =====================================================
-- CREATE VIEW: vw_near_expiry_fg
-- Products expiring within 3 days (FIFO priority)
-- =====================================================

CREATE OR REPLACE VIEW `vw_near_expiry_fg` AS
SELECT 
    fgi.id,
    fgi.batch_id,
    pb.batch_code,
    fgi.product_id,
    p.product_code,
    p.product_name,
    p.variant,
    fgi.quantity_available,
    fgi.manufacturing_date,
    fgi.expiry_date,
    DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
    fgi.barcode,
    cl.chiller_code,
    cl.chiller_name,
    CASE 
        WHEN DATEDIFF(fgi.expiry_date, CURDATE()) <= 0 THEN 'EXPIRED'
        WHEN DATEDIFF(fgi.expiry_date, CURDATE()) <= 1 THEN 'CRITICAL'
        WHEN DATEDIFF(fgi.expiry_date, CURDATE()) <= 3 THEN 'WARNING'
        ELSE 'OK'
    END as expiry_status
FROM finished_goods_inventory fgi
LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
LEFT JOIN products p ON fgi.product_id = p.id
LEFT JOIN chiller_locations cl ON fgi.chiller_id = cl.id
WHERE fgi.status = 'available' 
    AND fgi.quantity_available > 0
    AND DATEDIFF(fgi.expiry_date, CURDATE()) <= 3
ORDER BY fgi.expiry_date ASC, fgi.manufacturing_date ASC;

-- =====================================================
-- CREATE VIEW: vw_delivery_receipt_summary
-- DR summary with totals
-- =====================================================

CREATE OR REPLACE VIEW `vw_delivery_receipt_summary` AS
SELECT 
    dr.id,
    dr.dr_number,
    dr.order_type,
    dr.customer_id,
    dr.customer_name,
    dr.delivery_date,
    dr.total_items,
    dr.total_quantity,
    dr.total_amount,
    dr.status,
    dr.prepared_by,
    CONCAT(u1.first_name, ' ', u1.last_name) as prepared_by_name,
    dr.prepared_at,
    dr.released_by,
    CONCAT(u2.first_name, ' ', u2.last_name) as released_by_name,
    dr.released_at,
    dr.driver_name,
    dr.vehicle_plate,
    dr.created_at
FROM delivery_receipts dr
LEFT JOIN users u1 ON dr.prepared_by = u1.id
LEFT JOIN users u2 ON dr.released_by = u2.id;

SELECT 'Warehouse FG module migration completed successfully!' as status;
