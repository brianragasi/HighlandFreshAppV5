-- =====================================================
-- Highland Fresh System - Fix Requisition Items Schema
-- Purpose: Add missing columns to requisition_items table
-- Run this to fix the Warehouse Raw module errors
-- =====================================================

USE `highland_fresh`;

-- Add missing columns to requisition_items

ALTER TABLE requisition_items 
ADD COLUMN IF NOT EXISTS `item_type` ENUM('raw_milk', 'ingredient', 'mro') DEFAULT 'ingredient' AFTER `requisition_id`,
ADD COLUMN IF NOT EXISTS `item_id` INT(11) DEFAULT 0 AFTER `item_type`,
ADD COLUMN IF NOT EXISTS `item_code` VARCHAR(30) DEFAULT '' AFTER `item_id`,
ADD COLUMN IF NOT EXISTS `requested_quantity` DECIMAL(10,2) DEFAULT 0 AFTER `item_name`,
ADD COLUMN IF NOT EXISTS `issued_quantity` DECIMAL(10,2) NULL AFTER `requested_quantity`,
ADD COLUMN IF NOT EXISTS `unit_of_measure` VARCHAR(20) DEFAULT 'units' AFTER `issued_quantity`,
ADD COLUMN IF NOT EXISTS `status` ENUM('pending', 'partial', 'fulfilled', 'cancelled') DEFAULT 'pending' AFTER `unit_of_measure`,
ADD COLUMN IF NOT EXISTS `fulfilled_by` INT(11) NULL AFTER `status`,
ADD COLUMN IF NOT EXISTS `fulfilled_at` DATETIME NULL AFTER `fulfilled_by`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `notes`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Update existing records if needed
UPDATE requisition_items 
SET requested_quantity = quantity,
    unit_of_measure = unit,
    status = 'pending'
WHERE requested_quantity = 0 OR requested_quantity IS NULL;

SELECT 'Requisition items schema fixed successfully!' as status;
