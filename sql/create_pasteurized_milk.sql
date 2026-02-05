-- ============================================================
-- Highland Fresh - Pasteurized Milk Inventory
-- Created: January 21, 2026
-- Purpose: Track pasteurized milk as intermediate product for yogurt production
-- ============================================================

-- Create pasteurized milk inventory table
CREATE TABLE IF NOT EXISTS pasteurized_milk_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_code VARCHAR(50) NOT NULL UNIQUE,
    source_type ENUM('raw_milk', 'requisition') DEFAULT 'requisition',
    source_run_id INT DEFAULT NULL COMMENT 'Production run that created this batch',
    quantity_liters DECIMAL(10,3) NOT NULL,
    remaining_liters DECIMAL(10,3) NOT NULL,
    pasteurization_temp DECIMAL(5,2) DEFAULT 75.0 COMMENT 'Celsius',
    pasteurization_duration_mins INT DEFAULT 15,
    pasteurized_at DATETIME NOT NULL,
    pasteurized_by INT DEFAULT NULL,
    expiry_date DATE NOT NULL COMMENT 'Pasteurized milk expires quickly (2-3 days)',
    status ENUM('available', 'reserved', 'exhausted', 'expired') DEFAULT 'available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pasteurized_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_expiry (expiry_date),
    INDEX idx_batch_code (batch_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create pasteurization runs table (tracks the pasteurization process itself)
CREATE TABLE IF NOT EXISTS pasteurization_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_code VARCHAR(50) NOT NULL UNIQUE,
    input_milk_liters DECIMAL(10,3) NOT NULL COMMENT 'Raw milk input',
    output_milk_liters DECIMAL(10,3) NOT NULL COMMENT 'Pasteurized output (accounts for shrinkage)',
    shrinkage_percent DECIMAL(5,2) DEFAULT 1.0 COMMENT 'Typical 0.5-1% shrinkage',
    temperature DECIMAL(5,2) NOT NULL COMMENT 'Pasteurization temp in Celsius',
    duration_mins INT NOT NULL COMMENT 'Duration in minutes',
    started_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    performed_by INT NOT NULL,
    status ENUM('in_progress', 'completed', 'failed') DEFAULT 'in_progress',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add pasteurized_milk_batch_id to production_runs for yogurt traceability
ALTER TABLE production_runs 
ADD COLUMN IF NOT EXISTS pasteurized_milk_batch_id INT DEFAULT NULL COMMENT 'For yogurt: which pasteurized batch was used',
ADD COLUMN IF NOT EXISTS milk_source_type ENUM('raw', 'pasteurized') DEFAULT 'raw' COMMENT 'Indicates milk source type';

-- Create index for milk source tracking
CREATE INDEX IF NOT EXISTS idx_milk_source ON production_runs(milk_source_type);

-- Insert sample pasteurized milk for testing
INSERT INTO pasteurized_milk_inventory (batch_code, source_type, quantity_liters, remaining_liters, pasteurization_temp, pasteurization_duration_mins, pasteurized_at, expiry_date, status, notes)
VALUES 
('PAST-20260121-001', 'requisition', 200.00, 200.00, 75.0, 15, NOW(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'available', 'Sample pasteurized milk batch for testing'),
('PAST-20260121-002', 'requisition', 150.00, 150.00, 75.0, 15, NOW(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'available', 'Second sample batch');

-- View for available pasteurized milk
CREATE OR REPLACE VIEW v_available_pasteurized_milk AS
SELECT 
    id,
    batch_code,
    remaining_liters,
    pasteurization_temp,
    pasteurized_at,
    expiry_date,
    DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
FROM pasteurized_milk_inventory
WHERE status = 'available' 
  AND remaining_liters > 0
  AND expiry_date >= CURDATE()
ORDER BY pasteurized_at ASC;  -- FIFO: oldest first
