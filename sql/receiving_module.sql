-- =====================================================
-- Highland Fresh - Receiving Report Module
-- Implements proper goods receiving with rejection handling
-- =====================================================

-- 1. Create receiving_reports table
CREATE TABLE IF NOT EXISTS receiving_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rr_number VARCHAR(30) NOT NULL UNIQUE,
    po_id INT NOT NULL,
    supplier_id INT NOT NULL,
    received_by INT NOT NULL,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending_verification', 'verified', 'discrepancy', 'completed') DEFAULT 'pending_verification',
    verified_by INT NULL,
    verified_at DATETIME NULL,
    total_ordered DECIMAL(12,2) DEFAULT 0,
    total_received DECIMAL(12,2) DEFAULT 0,
    total_rejected DECIMAL(12,2) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rr_number (rr_number),
    INDEX idx_po_id (po_id),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_status (status),
    INDEX idx_received_at (received_at),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create receiving_report_items table
CREATE TABLE IF NOT EXISTS receiving_report_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rr_id INT NOT NULL,
    po_item_id INT NOT NULL,
    ingredient_id INT NULL,
    mro_item_id INT NULL,
    item_description VARCHAR(255) NOT NULL,
    quantity_ordered DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity_received DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity_rejected DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    rejection_reason ENUM('damaged', 'wrong_item', 'quality_issue', 'expired', 'shortage', 'other') NULL,
    rejection_notes TEXT NULL,
    batch_code VARCHAR(50) NULL,
    expiry_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rr_id (rr_id),
    INDEX idx_ingredient_id (ingredient_id),
    INDEX idx_mro_item_id (mro_item_id),
    FOREIGN KEY (rr_id) REFERENCES receiving_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (po_item_id) REFERENCES purchase_order_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE SET NULL,
    FOREIGN KEY (mro_item_id) REFERENCES mro_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create supplier_rejections table (for tracking rejected goods per supplier)
CREATE TABLE IF NOT EXISTS supplier_rejections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rejection_code VARCHAR(30) NOT NULL UNIQUE,
    rr_id INT NOT NULL,
    rr_item_id INT NOT NULL,
    supplier_id INT NOT NULL,
    ingredient_id INT NULL,
    mro_item_id INT NULL,
    item_description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    rejection_type ENUM('damaged', 'wrong_item', 'quality_issue', 'expired', 'shortage', 'other') NOT NULL,
    rejection_reason TEXT NULL,
    disposition ENUM('return_to_supplier', 'dispose', 'credit_memo', 'replace') DEFAULT 'return_to_supplier',
    status ENUM('pending', 'returned', 'disposed', 'credited', 'replaced') DEFAULT 'pending',
    disposal_id INT NULL,
    credit_memo_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    notes TEXT NULL,
    INDEX idx_rejection_code (rejection_code),
    INDEX idx_rr_id (rr_id),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_status (status),
    FOREIGN KEY (rr_id) REFERENCES receiving_reports(id) ON DELETE RESTRICT,
    FOREIGN KEY (rr_item_id) REFERENCES receiving_report_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (disposal_id) REFERENCES disposals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Add payment metadata to purchase_orders
ALTER TABLE purchase_orders 
ADD COLUMN IF NOT EXISTS check_number VARCHAR(50) NULL AFTER notes,
ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100) NULL AFTER check_number,
ADD COLUMN IF NOT EXISTS payment_date DATE NULL AFTER bank_name,
ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) NULL AFTER payment_date,
ADD COLUMN IF NOT EXISTS maturity_date DATE NULL AFTER payment_reference;

-- 5. Add rr_id to ingredient_batches for traceability
ALTER TABLE ingredient_batches
ADD COLUMN IF NOT EXISTS rr_id INT NULL AFTER po_id,
ADD INDEX IF NOT EXISTS idx_rr_id (rr_id),
ADD CONSTRAINT fk_batch_rr FOREIGN KEY (rr_id) REFERENCES receiving_reports(id) ON DELETE SET NULL;

-- 6. Create view for pending receiving (POs that need to be received)
CREATE OR REPLACE VIEW pending_receiving AS
SELECT 
    po.id,
    po.po_number,
    po.order_date,
    po.expected_delivery,
    po.total_amount,
    po.payment_terms,
    po.status,
    s.id as supplier_id,
    s.supplier_name,
    s.supplier_code,
    s.contact_person,
    s.phone,
    (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
    CASE 
        WHEN po.expected_delivery < CURDATE() AND po.status = 'ordered' THEN 1 
        ELSE 0 
    END as is_overdue
FROM purchase_orders po
JOIN suppliers s ON po.supplier_id = s.id
WHERE po.status IN ('approved', 'ordered', 'partial_received')
ORDER BY po.expected_delivery ASC;

-- 7. Create view for receiving history
CREATE OR REPLACE VIEW receiving_history AS
SELECT 
    rr.id,
    rr.rr_number,
    rr.received_at,
    rr.status,
    rr.total_ordered,
    rr.total_received,
    rr.total_rejected,
    rr.verified_at,
    po.po_number,
    s.supplier_name,
    u1.full_name as received_by_name,
    u2.full_name as verified_by_name
FROM receiving_reports rr
JOIN purchase_orders po ON rr.po_id = po.id
JOIN suppliers s ON rr.supplier_id = s.id
JOIN users u1 ON rr.received_by = u1.id
LEFT JOIN users u2 ON rr.verified_by = u2.id
ORDER BY rr.received_at DESC;

-- 8. Insert sample data for testing (optional - comment out in production)
-- INSERT INTO receiving_reports (rr_number, po_id, supplier_id, received_by, status, total_ordered, total_received, total_rejected)
-- SELECT 
--     CONCAT('RR-', DATE_FORMAT(NOW(), '%Y%m'), '-0001'),
--     1,
--     supplier_id,
--     1,
--     'pending_verification',
--     10,
--     9,
--     1
-- FROM purchase_orders WHERE id = 1;

SELECT 'Receiving module tables created successfully!' as result;
