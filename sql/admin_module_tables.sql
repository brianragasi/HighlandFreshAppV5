-- =============================================
-- Highland Fresh Admin Module - Database Tables
-- Add tables for chillers, QC standards, CCP monitoring
-- =============================================

-- =============================================
-- CHILLERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS chillers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chiller_name VARCHAR(100) NOT NULL,
    chiller_code VARCHAR(50) NOT NULL UNIQUE,
    chiller_type ENUM('walk_in', 'reach_in', 'blast', 'display') NOT NULL DEFAULT 'walk_in',
    capacity_liters DECIMAL(10, 2) NOT NULL,
    location VARCHAR(200),
    target_temperature DECIMAL(5, 2) DEFAULT 4.00,
    temperature_tolerance DECIMAL(5, 2) DEFAULT 2.00,
    current_temperature DECIMAL(5, 2),
    status ENUM('running', 'stopped', 'maintenance', 'fault', 'decommissioned') DEFAULT 'running',
    last_maintenance DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- CHILLER TEMPERATURE LOGS
-- =============================================
CREATE TABLE IF NOT EXISTS chiller_temp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chiller_id INT NOT NULL,
    temperature DECIMAL(5, 2) NOT NULL,
    log_date DATE NOT NULL,
    log_time TIME NOT NULL,
    recorded_by INT,
    status ENUM('normal', 'warning', 'critical') DEFAULT 'normal',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chiller_id) REFERENCES chillers(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- MILK GRADING STANDARDS
-- =============================================
CREATE TABLE IF NOT EXISTS milk_grading_standards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    fat_min DECIMAL(5, 2),
    fat_max DECIMAL(5, 2),
    snf_min DECIMAL(5, 2),
    snf_max DECIMAL(5, 2),
    temperature_max DECIMAL(5, 2),
    price_per_liter DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default grading standards
INSERT INTO milk_grading_standards (grade_name, description, fat_min, fat_max, snf_min, snf_max, temperature_max, price_per_liter) VALUES
('Grade A', 'Premium quality milk with high fat content', 3.5, NULL, 8.5, NULL, 8.0, 55.00),
('Grade B', 'Standard quality milk', 3.0, 3.49, 8.0, 8.49, 10.0, 50.00),
('Grade C', 'Below standard quality', 2.5, 2.99, 7.5, 7.99, 12.0, 45.00),
('Rejected', 'Does not meet minimum standards', NULL, 2.49, NULL, 7.49, NULL, 0.00)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =============================================
-- QC TEST PARAMETERS
-- =============================================
CREATE TABLE IF NOT EXISTS qc_test_parameters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parameter_name VARCHAR(100) NOT NULL,
    category ENUM('raw_milk', 'pasteurized', 'finished_goods', 'packaging') NOT NULL DEFAULT 'raw_milk',
    unit VARCHAR(50),
    min_value DECIMAL(10, 4),
    max_value DECIMAL(10, 4),
    target_value DECIMAL(10, 4),
    test_method VARCHAR(200),
    description TEXT,
    is_mandatory BOOLEAN DEFAULT TRUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default QC parameters
INSERT INTO qc_test_parameters (parameter_name, category, unit, min_value, max_value, target_value, test_method, description) VALUES
('Fat Content', 'raw_milk', '%', 3.0, NULL, 3.5, 'Gerber Method', 'Percentage of milk fat'),
('SNF (Solids-Not-Fat)', 'raw_milk', '%', 8.0, NULL, 8.5, 'Lactometer Reading', 'Non-fat solids content'),
('Temperature', 'raw_milk', '°C', NULL, 10.0, 4.0, 'Digital Thermometer', 'Milk temperature at collection'),
('Acidity', 'raw_milk', '% LA', 0.12, 0.16, 0.14, 'Titration Method', 'Lactic acid percentage'),
('Density', 'raw_milk', 'g/ml', 1.026, 1.032, 1.029, 'Lactometer', 'Specific gravity of milk'),
('Alcohol Test', 'raw_milk', 'Result', NULL, NULL, NULL, '68% Alcohol Test', 'Clot on Boiling / Alcohol Test'),
('Organoleptic', 'raw_milk', 'Result', NULL, NULL, NULL, 'Sensory Evaluation', 'Color, smell, taste, appearance'),
('MBRT', 'raw_milk', 'hours', 5.0, NULL, 6.0, 'Methylene Blue Reduction', 'Bacterial load indicator'),
('Pasteurization Temp', 'pasteurized', '°C', 72.0, 75.0, 73.0, 'Inline Temperature Sensor', 'Heat treatment temperature'),
('Pasteurization Time', 'pasteurized', 'seconds', 15.0, NULL, 15.0, 'Timer', 'Holding time at pasteurization temp'),
('Phosphatase Test', 'pasteurized', 'Result', NULL, NULL, NULL, 'Phosphatase Test Kit', 'Verify complete pasteurization'),
('Coliform Count', 'finished_goods', 'CFU/ml', NULL, 10.0, 0.0, 'Plate Count Method', 'Coliform bacteria count'),
('TPC', 'finished_goods', 'CFU/ml', NULL, 30000.0, NULL, 'Standard Plate Count', 'Total Plate Count')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =============================================
-- CCP (CRITICAL CONTROL POINT) STANDARDS
-- =============================================
CREATE TABLE IF NOT EXISTS ccp_standards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ccp_name VARCHAR(100) NOT NULL,
    category ENUM('receiving', 'storage', 'pasteurization', 'cooling', 'packaging', 'distribution') NOT NULL,
    critical_limit VARCHAR(200) NOT NULL,
    target_value VARCHAR(200),
    monitoring_frequency VARCHAR(100),
    corrective_action TEXT,
    hazard_description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default CCP standards
INSERT INTO ccp_standards (ccp_name, category, critical_limit, target_value, monitoring_frequency, corrective_action, hazard_description) VALUES
('Raw Milk Reception Temperature', 'receiving', '≤10°C', '4-8°C', 'Every delivery', 'Reject milk if >10°C or chill immediately', 'Biological: Bacterial growth if milk too warm'),
('Pasteurization Temperature', 'pasteurization', '≥72°C for 15 seconds', '73°C for 15-20 seconds', 'Continuous', 'Divert flow to reprocess, do not release batch', 'Biological: Pathogen survival if underprocessed'),
('Pasteurization Time', 'pasteurization', '≥15 seconds at 72°C', '15-20 seconds', 'Continuous', 'Divert flow, extend holding time', 'Biological: Insufficient heat treatment'),
('Post-Pasteurization Cooling', 'cooling', '≤4°C within 2 hours', '4°C within 1 hour', 'After each batch', 'Accelerate cooling, check refrigeration', 'Biological: Bacterial regrowth if cooling delayed'),
('Cold Storage Temperature', 'storage', '2-8°C', '4°C', 'Every 4 hours', 'Transfer to functioning unit, investigate cause', 'Biological: Bacterial growth in warm storage'),
('Packaging Seal Integrity', 'packaging', 'Complete seal, no leaks', 'Airtight seal', 'Every batch / random sampling', 'Reject and repackage affected units', 'Biological: Contamination through seal failure'),
('Distribution Vehicle Temperature', 'distribution', '≤8°C', '4-6°C', 'Before dispatch and on arrival', 'Do not dispatch if >8°C, repair refrigeration', 'Biological: Temperature abuse during transport')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =============================================
-- CCP LOGS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS ccp_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ccp_id INT NOT NULL,
    batch_id INT,
    production_run_id INT,
    measured_value VARCHAR(100),
    is_within_limit BOOLEAN DEFAULT TRUE,
    deviation_action TEXT,
    verified_by INT,
    log_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (ccp_id) REFERENCES ccp_standards(id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- STORAGE TANKS (if not exists)
-- =============================================
CREATE TABLE IF NOT EXISTS storage_tanks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tank_name VARCHAR(100) NOT NULL,
    tank_code VARCHAR(50) NOT NULL UNIQUE,
    tank_type ENUM('raw_milk', 'pasteurized', 'processing', 'storage') NOT NULL DEFAULT 'raw_milk',
    capacity_liters DECIMAL(10, 2) NOT NULL,
    current_volume DECIMAL(10, 2) DEFAULT 0.00,
    location VARCHAR(200),
    temperature_min DECIMAL(5, 2) DEFAULT 2.00,
    temperature_max DECIMAL(5, 2) DEFAULT 8.00,
    current_temperature DECIMAL(5, 2),
    status ENUM('available', 'in_use', 'maintenance', 'decommissioned') DEFAULT 'available',
    last_cleaned DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add fill_percentage as a generated column
ALTER TABLE storage_tanks 
ADD COLUMN IF NOT EXISTS fill_percentage DECIMAL(5, 2) 
GENERATED ALWAYS AS (
    CASE 
        WHEN capacity_liters > 0 THEN (current_volume / capacity_liters) * 100
        ELSE 0 
    END
) STORED;

-- Insert sample storage tanks
INSERT INTO storage_tanks (tank_name, tank_code, tank_type, capacity_liters, current_volume, location, status) VALUES
('Raw Milk Tank 1', 'RMT-001', 'raw_milk', 5000, 2500, 'Receiving Area A', 'in_use'),
('Raw Milk Tank 2', 'RMT-002', 'raw_milk', 5000, 0, 'Receiving Area A', 'available'),
('Pasteurized Tank 1', 'PT-001', 'pasteurized', 3000, 1800, 'Processing Area', 'in_use'),
('Processing Tank 1', 'PRT-001', 'processing', 2000, 500, 'Processing Area', 'in_use'),
('Storage Tank 1', 'ST-001', 'storage', 10000, 4500, 'Cold Storage Room', 'in_use')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Insert sample chillers
INSERT INTO chillers (chiller_name, chiller_code, chiller_type, capacity_liters, location, target_temperature, status) VALUES
('Cold Room 1', 'CH-001', 'walk_in', 50000, 'Main Storage Building', 4.0, 'running'),
('Cold Room 2', 'CH-002', 'walk_in', 30000, 'Distribution Center', 4.0, 'running'),
('Display Chiller 1', 'CH-003', 'display', 500, 'Retail Store', 6.0, 'running'),
('Blast Chiller 1', 'CH-004', 'blast', 1000, 'Processing Area', 2.0, 'stopped')
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- =============================================
-- ADD INDEXES FOR BETTER PERFORMANCE
-- =============================================
CREATE INDEX IF NOT EXISTS idx_chillers_status ON chillers(status);
CREATE INDEX IF NOT EXISTS idx_chiller_logs_date ON chiller_temp_logs(log_date);
CREATE INDEX IF NOT EXISTS idx_qc_params_category ON qc_test_parameters(category);
CREATE INDEX IF NOT EXISTS idx_ccp_category ON ccp_standards(category);
CREATE INDEX IF NOT EXISTS idx_ccp_logs_datetime ON ccp_logs(log_datetime);
CREATE INDEX IF NOT EXISTS idx_tanks_status ON storage_tanks(status);
CREATE INDEX IF NOT EXISTS idx_grading_status ON milk_grading_standards(status);
