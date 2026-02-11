-- ============================================
-- Highland Fresh - Maintenance Module Schema
-- ============================================
-- Created: 2026-02-11
-- Purpose: Tables for Maintenance Head workflow
-- - Equipment/Machine registry
-- - Repair logging with parts tracking
-- - MRO requisitions (separate from Production requisitions)
-- ============================================

-- 1. Equipment/Machines Registry
-- Tracks all plant equipment that needs maintenance
CREATE TABLE IF NOT EXISTS machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_code VARCHAR(30) NOT NULL UNIQUE,
    machine_name VARCHAR(150) NOT NULL,
    machine_type ENUM('pasteurizer', 'homogenizer', 'retort', 'fill_seal', 'tank', 'pump', 'chiller', 'other') NOT NULL,
    manufacturer VARCHAR(150) NULL,
    model_number VARCHAR(100) NULL,
    serial_number VARCHAR(100) NULL,
    location VARCHAR(150) NULL COMMENT 'Physical location in plant',
    purchase_date DATE NULL,
    warranty_expiry DATE NULL,
    status ENUM('operational', 'needs_maintenance', 'under_repair', 'offline', 'decommissioned') DEFAULT 'operational',
    last_maintenance_date DATE NULL,
    next_maintenance_due DATE NULL,
    maintenance_interval_days INT DEFAULT 30 COMMENT 'Scheduled maintenance interval',
    notes TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_machine_type (machine_type),
    INDEX idx_status (status),
    INDEX idx_next_maintenance (next_maintenance_due)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Machine Repairs Log
-- Documents every repair made to equipment
CREATE TABLE IF NOT EXISTS machine_repairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repair_code VARCHAR(30) NOT NULL UNIQUE,
    machine_id INT NOT NULL,
    repair_type ENUM('preventive', 'corrective', 'emergency', 'inspection') NOT NULL DEFAULT 'corrective',
    priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
    issue_description TEXT NOT NULL COMMENT 'What was the problem?',
    diagnosis TEXT NULL COMMENT 'Root cause analysis',
    repair_actions TEXT NULL COMMENT 'What was done to fix it?',
    status ENUM('reported', 'diagnosed', 'in_progress', 'awaiting_parts', 'completed', 'cancelled') DEFAULT 'reported',
    
    -- Tracking
    reported_by INT NOT NULL,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_to INT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    completed_by INT NULL,
    
    -- Downtime tracking
    downtime_hours DECIMAL(6,2) NULL COMMENT 'How long was machine down?',
    
    -- Cost tracking
    labor_cost DECIMAL(10,2) DEFAULT 0,
    parts_cost DECIMAL(10,2) DEFAULT 0,
    total_cost DECIMAL(10,2) GENERATED ALWAYS AS (labor_cost + parts_cost) STORED,
    
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (machine_id) REFERENCES machines(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (completed_by) REFERENCES users(id),
    
    INDEX idx_machine (machine_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_repair_type (repair_type),
    INDEX idx_reported_at (reported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Repair Parts Used
-- Links MRO items used in each repair
CREATE TABLE IF NOT EXISTS repair_parts_used (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repair_id INT NOT NULL,
    mro_item_id INT NOT NULL,
    mro_inventory_id INT NULL COMMENT 'Specific batch used',
    quantity_used DECIMAL(10,2) NOT NULL,
    unit_cost DECIMAL(10,2) NULL,
    total_cost DECIMAL(10,2) GENERATED ALWAYS AS (quantity_used * unit_cost) STORED,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (repair_id) REFERENCES machine_repairs(id) ON DELETE CASCADE,
    FOREIGN KEY (mro_item_id) REFERENCES mro_items(id),
    FOREIGN KEY (mro_inventory_id) REFERENCES mro_inventory(id),
    
    INDEX idx_repair (repair_id),
    INDEX idx_mro_item (mro_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Maintenance Requisitions
-- MRO part requests from Maintenance to Warehouse (requires GM approval)
CREATE TABLE IF NOT EXISTS maintenance_requisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_code VARCHAR(30) NOT NULL UNIQUE,
    repair_id INT NULL COMMENT 'Link to repair if requesting for specific repair',
    machine_id INT NULL COMMENT 'Link to machine if for scheduled maintenance',
    
    status ENUM('pending', 'approved', 'rejected', 'fulfilled', 'partially_fulfilled', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    purpose TEXT NULL COMMENT 'Why are these parts needed?',
    needed_by_date DATETIME NULL,
    
    -- Tracking
    requested_by INT NOT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    rejected_by INT NULL,
    rejected_at DATETIME NULL,
    rejection_reason TEXT NULL,
    fulfilled_by INT NULL,
    fulfilled_at DATETIME NULL,
    
    total_items INT DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (repair_id) REFERENCES machine_repairs(id),
    FOREIGN KEY (machine_id) REFERENCES machines(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (rejected_by) REFERENCES users(id),
    FOREIGN KEY (fulfilled_by) REFERENCES users(id),
    
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_repair (repair_id),
    INDEX idx_machine (machine_id),
    INDEX idx_requested_by (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Maintenance Requisition Items
CREATE TABLE IF NOT EXISTS maintenance_requisition_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    mro_item_id INT NOT NULL,
    
    requested_quantity DECIMAL(10,2) NOT NULL,
    approved_quantity DECIMAL(10,2) NULL,
    issued_quantity DECIMAL(10,2) NULL,
    
    unit_of_measure VARCHAR(20) DEFAULT 'pcs',
    notes TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (requisition_id) REFERENCES maintenance_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (mro_item_id) REFERENCES mro_items(id),
    
    INDEX idx_requisition (requisition_id),
    INDEX idx_mro_item (mro_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Scheduled Maintenance
-- For tracking preventive maintenance schedules
CREATE TABLE IF NOT EXISTS maintenance_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    schedule_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    frequency_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'hours_based') DEFAULT 'monthly',
    frequency_value INT DEFAULT 1 COMMENT 'Every X days/weeks/months or hours',
    last_performed DATE NULL,
    next_due DATE NULL,
    assigned_to INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (machine_id) REFERENCES machines(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    
    INDEX idx_machine (machine_id),
    INDEX idx_next_due (next_due)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SAMPLE DATA - Default Machines
-- ============================================
INSERT INTO machines (machine_code, machine_name, machine_type, location, status, maintenance_interval_days) VALUES
('MCH-PAST-001', 'Primary Pasteurizer', 'pasteurizer', 'Processing Area A', 'operational', 14),
('MCH-HOMO-001', 'Main Homogenizer', 'homogenizer', 'Processing Area A', 'operational', 30),
('MCH-RTRT-001', 'Retort Machine #1', 'retort', 'Sterilization Room', 'operational', 7),
('MCH-FILL-001', 'Fill-Seal Machine #1', 'fill_seal', 'Packaging Area', 'operational', 14),
('MCH-FILL-002', 'Fill-Seal Machine #2', 'fill_seal', 'Packaging Area', 'operational', 14),
('MCH-TANK-001', 'Storage Tank A', 'tank', 'Cold Storage', 'operational', 30),
('MCH-TANK-002', 'Storage Tank B', 'tank', 'Cold Storage', 'operational', 30),
('MCH-PUMP-001', 'Transfer Pump #1', 'pump', 'Processing Area', 'operational', 30),
('MCH-CHLR-001', 'Main Chiller Unit', 'chiller', 'Utility Room', 'operational', 30);

-- ============================================
-- Add maintenance_head user if not exists
-- ============================================
INSERT INTO users (username, password, role, first_name, last_name, email, is_active) 
SELECT 'maintenance_head', '$2y$10$YourHashedPasswordHere', 'maintenance_head', 'Juan', 'Dela Cruz', 'maintenance@highlandfresh.com', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'maintenance_head' LIMIT 1);

-- Update the password to 'password' (bcrypt hash)
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE role = 'maintenance_head' AND password = '$2y$10$YourHashedPasswordHere';

SELECT 'Maintenance Module tables created successfully!' as status;
