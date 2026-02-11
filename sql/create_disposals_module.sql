-- Highland Fresh System - Disposals Module Database Setup
-- Version: 4.0
-- Date: 2026-02-08
-- Description: Creates tables for QC Disposal/Write-Off tracking with GM approval workflow

-- ===================================
-- 1. Create disposals table
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
        'qc_failed',          -- Failed QC test (milk or batch)
        'expired',            -- Past expiry date
        'spoiled',            -- Spoiled during storage
        'contaminated',       -- Contamination detected
        'damaged',            -- Physical damage
        'rejected_receipt',   -- Rejected at receiving
        'production_waste',   -- Production line waste
        'other'               -- Other reasons
    ) NOT NULL,
    
    disposal_reason TEXT NOT NULL COMMENT 'Detailed reason for disposal',
    
    -- Disposal method
    disposal_method ENUM(
        'drain',              -- Liquid disposal (milk)
        'incinerate',         -- Burn (contaminated items)
        'animal_feed',        -- Convert to animal feed
        'compost',            -- Organic composting
        'special_waste',      -- Special waste contractor
        'other'               -- Other method
    ) NOT NULL DEFAULT 'drain',
    
    -- Approval workflow
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    
    -- Initiator (QC Officer)
    initiated_by INT NOT NULL,
    initiated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Approver (GM or designated approver)
    approved_by INT NULL,
    approved_at DATETIME NULL,
    approval_notes TEXT NULL,
    
    -- Execution
    disposed_by INT NULL,
    disposed_at DATETIME NULL,
    disposal_location VARCHAR(100) NULL,
    
    -- Documentation
    documentation_path VARCHAR(255) NULL COMMENT 'Path to photos/documents',
    witness_name VARCHAR(100) NULL COMMENT 'Witness during disposal',
    
    -- Audit trail
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_disposal_code (disposal_code),
    INDEX idx_source (source_type, source_id),
    INDEX idx_status (status),
    INDEX idx_category (disposal_category),
    INDEX idx_date (created_at),
    INDEX idx_approved_by (approved_by),
    INDEX idx_initiated_by (initiated_by),
    
    -- Foreign keys
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (initiated_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (disposed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 2. Create disposal_items table (for batch disposals)
-- ===================================
CREATE TABLE IF NOT EXISTS disposal_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disposal_id INT NOT NULL,
    
    -- Item details
    source_type ENUM('raw_milk', 'finished_goods', 'ingredients', 'production_batch') NOT NULL,
    source_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(100) NULL,
    batch_code VARCHAR(50) NULL,
    
    -- Quantity
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'pcs',
    
    -- Value
    unit_cost DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(14,2) DEFAULT 0,
    
    expiry_date DATE NULL,
    reason TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_disposal_id (disposal_id),
    FOREIGN KEY (disposal_id) REFERENCES disposals(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 3. Add disposal tracking fields to related tables
-- ===================================

-- Add disposal reference to finished_goods_inventory
ALTER TABLE finished_goods_inventory
    ADD COLUMN IF NOT EXISTS disposed_quantity INT DEFAULT 0 AFTER quantity_available,
    ADD COLUMN IF NOT EXISTS disposal_id INT NULL,
    ADD COLUMN IF NOT EXISTS disposed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS disposal_reason VARCHAR(255) NULL;

-- Add disposal reference to raw_milk_inventory  
ALTER TABLE raw_milk_inventory
    ADD COLUMN IF NOT EXISTS disposed_liters DECIMAL(10,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS disposal_id INT NULL,
    ADD COLUMN IF NOT EXISTS disposed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS disposal_reason VARCHAR(255) NULL;

-- ===================================
-- 4. Create disposal summary view
-- ===================================
CREATE OR REPLACE VIEW vw_disposal_summary AS
SELECT 
    d.id,
    d.disposal_code,
    d.source_type,
    d.source_reference,
    d.product_name,
    d.quantity,
    d.unit,
    d.total_value,
    d.disposal_category,
    d.status,
    d.initiated_at,
    d.approved_at,
    d.disposed_at,
    ui.first_name as initiated_by_name,
    ua.first_name as approved_by_name,
    CASE 
        WHEN d.status = 'pending' THEN 'Awaiting Approval'
        WHEN d.status = 'approved' THEN 'Approved - Ready for Disposal'
        WHEN d.status = 'completed' THEN 'Disposed'
        WHEN d.status = 'rejected' THEN 'Rejected'
        ELSE d.status
    END as status_label
FROM disposals d
LEFT JOIN users ui ON d.initiated_by = ui.id
LEFT JOIN users ua ON d.approved_by = ua.id;

-- ===================================
-- 5. Insert sample disposal categories into system_settings (optional)
-- ===================================
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, created_at)
VALUES 
    ('disposal_requires_gm_approval', 'true', 'boolean', 'Whether disposals require GM approval', NOW()),
    ('disposal_photo_required', 'true', 'boolean', 'Whether photo documentation is required for disposals', NOW()),
    ('disposal_witness_required', 'true', 'boolean', 'Whether a witness is required for disposals', NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

SELECT 'Disposals Module Setup Complete!' as status;
