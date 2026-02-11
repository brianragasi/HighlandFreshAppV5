-- =====================================================
-- BATCH RECALL MODULE
-- Highland Fresh Quality Control System
-- Created: 2026-02-08
-- =====================================================
-- This module enables tracking and management of 
-- product recalls for contaminated or defective batches
-- =====================================================

-- Main recalls table
CREATE TABLE IF NOT EXISTS batch_recalls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recall_code VARCHAR(30) NOT NULL UNIQUE,
    
    -- Batch identification
    batch_id INT NOT NULL,
    batch_code VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    
    -- Recall classification (FDA standard)
    -- class_i: Dangerous, could cause serious health problems or death
    -- class_ii: May cause temporary health problems  
    -- class_iii: Unlikely to cause health problems
    recall_class ENUM('class_i', 'class_ii', 'class_iii') NOT NULL,
    
    -- Reason and evidence
    reason TEXT NOT NULL,
    evidence_notes TEXT NULL,
    evidence_files JSON NULL,
    
    -- Quantities (populated when recall is created)
    total_produced INT NOT NULL DEFAULT 0,
    total_dispatched INT NOT NULL DEFAULT 0,
    total_in_warehouse INT NOT NULL DEFAULT 0,
    total_recovered INT NOT NULL DEFAULT 0,
    
    -- Status workflow
    -- initiated: QC creates recall request
    -- pending_approval: Awaiting GM approval
    -- approved: GM approved, notifications being sent
    -- in_progress: Returns being collected
    -- completed: Recall finished
    -- cancelled: Recall cancelled
    status ENUM('initiated', 'pending_approval', 'approved', 'in_progress', 
                'completed', 'cancelled') NOT NULL DEFAULT 'initiated',
    
    -- Workflow tracking
    initiated_by INT NOT NULL,
    initiated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    approval_notes TEXT NULL,
    rejection_reason TEXT NULL,
    completed_by INT NULL,
    completed_at DATETIME NULL,
    completion_notes TEXT NULL,
    
    -- Audit timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_batch_id (batch_id),
    INDEX idx_status (status),
    INDEX idx_recall_class (recall_class),
    INDEX idx_initiated_at (initiated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Affected locations table - stores which stores/customers received recalled products
CREATE TABLE IF NOT EXISTS recall_affected_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recall_id INT NOT NULL,
    
    -- Location details (from dispatch/sales records)
    location_type ENUM('store', 'distributor', 'direct_customer', 'internal') NOT NULL,
    location_id INT NULL,
    location_name VARCHAR(255) NOT NULL,
    location_address TEXT NULL,
    contact_person VARCHAR(255) NULL,
    contact_phone VARCHAR(50) NULL,
    contact_email VARCHAR(255) NULL,
    
    -- Dispatch details
    dispatch_date DATE NULL,
    dispatch_reference VARCHAR(100) NULL,
    
    -- Quantities
    units_dispatched INT NOT NULL DEFAULT 0,
    units_returned INT NOT NULL DEFAULT 0,
    units_destroyed_onsite INT NOT NULL DEFAULT 0,
    units_consumed INT NOT NULL DEFAULT 0,
    units_unaccounted INT GENERATED ALWAYS AS 
        (units_dispatched - units_returned - units_destroyed_onsite - units_consumed) STORED,
    
    -- Notification tracking
    notification_sent BOOLEAN NOT NULL DEFAULT FALSE,
    notification_sent_at DATETIME NULL,
    notification_method ENUM('sms', 'email', 'phone', 'in_person') NULL,
    notification_sent_by INT NULL,
    
    -- Acknowledgment tracking
    acknowledged BOOLEAN NOT NULL DEFAULT FALSE,
    acknowledged_at DATETIME NULL,
    acknowledged_by_name VARCHAR(255) NULL,
    
    -- Return status
    return_status ENUM('pending', 'partial', 'complete', 'none', 'destroyed_onsite') 
        NOT NULL DEFAULT 'pending',
    
    notes TEXT NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recall_id) REFERENCES batch_recalls(id) ON DELETE CASCADE,
    INDEX idx_recall_id (recall_id),
    INDEX idx_return_status (return_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Return tracking table - logs individual returns from locations
CREATE TABLE IF NOT EXISTS recall_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recall_id INT NOT NULL,
    affected_location_id INT NOT NULL,
    
    -- Return details
    return_date DATE NOT NULL,
    units_returned INT NOT NULL,
    condition_status ENUM('good', 'damaged', 'spoiled', 'unknown') NOT NULL DEFAULT 'unknown',
    condition_notes TEXT NULL,
    
    -- Receiving
    received_by INT NOT NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Link to disposal (once items are disposed)
    disposal_id INT NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recall_id) REFERENCES batch_recalls(id) ON DELETE CASCADE,
    FOREIGN KEY (affected_location_id) REFERENCES recall_affected_locations(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_recall_id (recall_id),
    INDEX idx_return_date (return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recall activity log for audit trail
CREATE TABLE IF NOT EXISTS recall_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recall_id INT NOT NULL,
    
    action ENUM('created', 'updated', 'approved', 'rejected', 'notification_sent',
                'return_logged', 'completed', 'cancelled', 'note_added') NOT NULL,
    action_by INT NOT NULL,
    action_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    details JSON NULL,
    notes TEXT NULL,
    
    FOREIGN KEY (recall_id) REFERENCES batch_recalls(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(id),
    INDEX idx_recall_id (recall_id),
    INDEX idx_action_at (action_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- View: Recall Summary with statistics
CREATE OR REPLACE VIEW vw_recall_summary AS
SELECT 
    br.id,
    br.recall_code,
    br.batch_code,
    br.product_name,
    br.recall_class,
    br.status,
    br.total_produced,
    br.total_dispatched,
    br.total_recovered,
    CASE 
        WHEN br.total_dispatched > 0 
        THEN ROUND((br.total_recovered / br.total_dispatched) * 100, 2)
        ELSE 0 
    END as recovery_rate,
    br.initiated_at,
    br.approved_at,
    br.completed_at,
    CONCAT(ui.first_name, ' ', ui.last_name) as initiated_by_name,
    CONCAT(ua.first_name, ' ', ua.last_name) as approved_by_name,
    COUNT(DISTINCT ral.id) as affected_locations_count,
    SUM(CASE WHEN ral.notification_sent = 1 THEN 1 ELSE 0 END) as notifications_sent,
    SUM(CASE WHEN ral.acknowledged = 1 THEN 1 ELSE 0 END) as acknowledgments_received
FROM batch_recalls br
LEFT JOIN users ui ON br.initiated_by = ui.id
LEFT JOIN users ua ON br.approved_by = ua.id
LEFT JOIN recall_affected_locations ral ON br.id = ral.recall_id
GROUP BY br.id;

-- Add foreign key to production_batches if it doesn't exist
-- (Skip if table doesn't exist)
-- ALTER TABLE batch_recalls 
--     ADD CONSTRAINT fk_batch_recalls_batch 
--     FOREIGN KEY (batch_id) REFERENCES production_batches(id);

DELIMITER //

-- Trigger: Auto-update total_recovered when returns are logged
CREATE TRIGGER IF NOT EXISTS tr_recall_return_update
AFTER INSERT ON recall_returns
FOR EACH ROW
BEGIN
    -- Update affected location
    UPDATE recall_affected_locations 
    SET units_returned = units_returned + NEW.units_returned,
        return_status = CASE 
            WHEN (units_returned + NEW.units_returned) >= units_dispatched THEN 'complete'
            WHEN (units_returned + NEW.units_returned) > 0 THEN 'partial'
            ELSE 'pending'
        END
    WHERE id = NEW.affected_location_id;
    
    -- Update recall total
    UPDATE batch_recalls 
    SET total_recovered = (
        SELECT COALESCE(SUM(units_returned), 0) 
        FROM recall_affected_locations 
        WHERE recall_id = NEW.recall_id
    )
    WHERE id = NEW.recall_id;
END//

-- Trigger: Log activity when recall status changes
CREATE TRIGGER IF NOT EXISTS tr_recall_status_change
AFTER UPDATE ON batch_recalls
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO recall_activity_log (recall_id, action, action_by, details)
        VALUES (
            NEW.id,
            CASE NEW.status
                WHEN 'approved' THEN 'approved'
                WHEN 'completed' THEN 'completed'
                WHEN 'cancelled' THEN 'cancelled'
                ELSE 'updated'
            END,
            COALESCE(NEW.approved_by, NEW.completed_by, NEW.initiated_by),
            JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status)
        );
    END IF;
END//

DELIMITER ;

-- Insert sample recall classes description (for reference)
-- This can be used for UI dropdowns
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_group, description) VALUES
('recall_class_i_desc', 'Dangerous - Could cause serious health problems or death', 'recall', 'Class I recall description'),
('recall_class_ii_desc', 'May cause temporary or medically reversible health problems', 'recall', 'Class II recall description'),
('recall_class_iii_desc', 'Not likely to cause adverse health consequences', 'recall', 'Class III recall description'),
('recall_notification_template', 'URGENT RECALL NOTICE: Product {product_name} Batch {batch_code} has been recalled. Reason: {reason}. Please remove from inventory immediately and contact Highland Fresh.', 'recall', 'Default recall notification template');
