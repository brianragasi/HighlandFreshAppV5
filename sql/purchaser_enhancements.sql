-- =====================================================
-- Highland Fresh - Purchaser Module Enhancements
-- Adds: Payment Terms, Canvassing/Price Quotes, Price Tracking
-- =====================================================

-- 1. Add payment_terms to purchase_orders
ALTER TABLE purchase_orders 
ADD COLUMN payment_terms ENUM('cash', 'credit_7', 'credit_15', 'credit_30', 'credit_45', 'credit_60') DEFAULT 'cash' AFTER payment_status,
ADD COLUMN due_date DATE NULL AFTER payment_terms,
ADD COLUMN requisition_id INT NULL AFTER notes,
ADD INDEX idx_payment_terms (payment_terms),
ADD INDEX idx_due_date (due_date),
ADD CONSTRAINT fk_po_requisition FOREIGN KEY (requisition_id) REFERENCES material_requisitions(id) ON DELETE SET NULL;

-- 2. Create price_canvass table for supplier quotes (Rule of 3)
CREATE TABLE IF NOT EXISTS price_canvass (
    id INT AUTO_INCREMENT PRIMARY KEY,
    canvass_code VARCHAR(30) NOT NULL UNIQUE,
    item_type ENUM('ingredient', 'mro', 'other') NOT NULL DEFAULT 'ingredient',
    ingredient_id INT NULL,
    mro_item_id INT NULL,
    item_description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    status ENUM('open', 'completed', 'cancelled') DEFAULT 'open',
    selected_quote_id INT NULL,
    created_by INT NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE SET NULL,
    FOREIGN KEY (mro_item_id) REFERENCES mro_items(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create canvass_quotes table for individual supplier quotes
CREATE TABLE IF NOT EXISTS canvass_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    canvass_id INT NOT NULL,
    supplier_id INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    delivery_days INT DEFAULT 7,
    payment_terms ENUM('cash', 'credit_7', 'credit_15', 'credit_30', 'credit_45', 'credit_60') DEFAULT 'cash',
    validity_date DATE NULL,
    is_selected TINYINT(1) DEFAULT 0,
    notes TEXT NULL,
    quoted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (canvass_id) REFERENCES price_canvass(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    INDEX idx_canvass (canvass_id),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create ingredient_price_history for market price tracking
CREATE TABLE IF NOT EXISTS ingredient_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    old_price DECIMAL(12,2) NOT NULL,
    new_price DECIMAL(12,2) NOT NULL,
    price_change DECIMAL(12,2) GENERATED ALWAYS AS (new_price - old_price) STORED,
    change_percent DECIMAL(5,2) GENERATED ALWAYS AS (((new_price - old_price) / old_price) * 100) STORED,
    po_id INT NULL,
    supplier_id INT NULL,
    reason VARCHAR(255) NULL,
    updated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_ingredient (ingredient_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create mro_price_history for MRO item price tracking
CREATE TABLE IF NOT EXISTS mro_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mro_item_id INT NOT NULL,
    old_price DECIMAL(12,2) NOT NULL,
    new_price DECIMAL(12,2) NOT NULL,
    price_change DECIMAL(12,2) GENERATED ALWAYS AS (new_price - old_price) STORED,
    change_percent DECIMAL(5,2) GENERATED ALWAYS AS (((new_price - old_price) / old_price) * 100) STORED,
    po_id INT NULL,
    supplier_id INT NULL,
    reason VARCHAR(255) NULL,
    updated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mro_item_id) REFERENCES mro_items(id),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_mro_item (mro_item_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Add market_price column to ingredients if not exists
ALTER TABLE ingredients 
ADD COLUMN IF NOT EXISTS market_price DECIMAL(12,2) NULL AFTER unit_cost,
ADD COLUMN IF NOT EXISTS last_price_update DATE NULL AFTER market_price;

-- 7. Add market_price column to mro_items if not exists
ALTER TABLE mro_items 
ADD COLUMN IF NOT EXISTS market_price DECIMAL(12,2) NULL AFTER unit_cost,
ADD COLUMN IF NOT EXISTS last_price_update DATE NULL AFTER market_price;

-- 8. Create view for GM pending approvals across all modules
CREATE OR REPLACE VIEW gm_pending_approvals AS
SELECT 
    'PO' as type,
    po.id,
    po.po_number as reference_code,
    CONCAT('Purchase Order - ', s.supplier_name) as description,
    po.total_amount as amount,
    u.full_name as requested_by,
    po.created_at as requested_at,
    'pending' as status
FROM purchase_orders po
JOIN suppliers s ON po.supplier_id = s.id
LEFT JOIN users u ON po.created_by = u.id
WHERE po.status = 'pending'

UNION ALL

SELECT 
    'REQUISITION' as type,
    mr.id,
    mr.requisition_code as reference_code,
    CONCAT('Material Requisition - ', mr.department, ': ', COALESCE(mr.purpose, 'No description')) as description,
    NULL as amount,
    u.full_name as requested_by,
    mr.created_at as requested_at,
    mr.status
FROM material_requisitions mr
LEFT JOIN users u ON mr.requested_by = u.id
WHERE mr.status = 'pending'

ORDER BY requested_at ASC;

-- 9. Insert sample canvass data for testing
INSERT INTO price_canvass (canvass_code, item_type, ingredient_id, item_description, quantity, unit, status, created_by, notes) VALUES
('CNV-2026-0001', 'ingredient', 1, 'White Sugar', 50, 'kg', 'completed', 1, 'Regular monthly order'),
('CNV-2026-0002', 'ingredient', 2, 'Milk Powder', 25, 'kg', 'open', 1, 'Need quotes for next batch');

-- Get the canvass IDs and insert quotes (sample data)
INSERT INTO canvass_quotes (canvass_id, supplier_id, unit_price, delivery_days, payment_terms, is_selected, notes)
SELECT 
    (SELECT id FROM price_canvass WHERE canvass_code = 'CNV-2026-0001'),
    id,
    CASE id 
        WHEN 1 THEN 55.00
        WHEN 2 THEN 52.50
        WHEN 3 THEN 58.00
    END,
    CASE id WHEN 1 THEN 3 WHEN 2 THEN 5 WHEN 3 THEN 2 END,
    CASE id WHEN 1 THEN 'credit_30' WHEN 2 THEN 'cash' WHEN 3 THEN 'credit_15' END,
    CASE id WHEN 2 THEN 1 ELSE 0 END,
    CASE id 
        WHEN 1 THEN 'Regular supplier'
        WHEN 2 THEN 'Best price - selected'
        WHEN 3 THEN 'Fastest delivery'
    END
FROM suppliers
WHERE id IN (1, 2, 3)
LIMIT 3;

SELECT 'Purchaser enhancements applied successfully!' as result;
