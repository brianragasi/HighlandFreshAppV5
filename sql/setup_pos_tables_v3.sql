-- Highland Fresh POS Module - Database Setup v3
-- Creates missing tables and adds sample data for production-ready POS

-- ===================================
-- 1. Add price columns to products table
-- ===================================
ALTER TABLE products 
    ADD COLUMN IF NOT EXISTS unit_price DECIMAL(12,2) DEFAULT 0.00 AFTER pieces_per_box,
    ADD COLUMN IF NOT EXISTS selling_price DECIMAL(12,2) DEFAULT 0.00 AFTER unit_price,
    ADD COLUMN IF NOT EXISTS cost_price DECIMAL(12,2) DEFAULT 0.00 AFTER selling_price;

-- ===================================
-- 2. Create sales_transactions table
-- ===================================
CREATE TABLE IF NOT EXISTS sales_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(30) NOT NULL UNIQUE,
    transaction_type ENUM('cash', 'credit', 'csi') NOT NULL DEFAULT 'cash',
    customer_id INT NULL,
    customer_name VARCHAR(100) NULL,
    customer_type ENUM('walk_in', 'regular', 'wholesale') DEFAULT 'walk_in',
    subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_type ENUM('none', 'percentage', 'fixed') DEFAULT 'none',
    discount_value DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'gcash', 'check', 'bank_transfer', 'credit') NOT NULL DEFAULT 'cash',
    amount_paid DECIMAL(12,2) DEFAULT 0,
    change_amount DECIMAL(12,2) DEFAULT 0,
    payment_reference VARCHAR(100) NULL,
    payment_metadata JSON NULL,
    payment_status ENUM('paid', 'partial', 'unpaid', 'voided') NOT NULL DEFAULT 'paid',
    shift_id INT NULL,
    cashier_id INT NOT NULL,
    voided_by INT NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transaction_code (transaction_code),
    INDEX idx_cashier (cashier_id),
    INDEX idx_date (created_at),
    INDEX idx_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 3. Create sales_transaction_items table
-- ===================================
CREATE TABLE IF NOT EXISTS sales_transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    inventory_id INT NULL,
    product_code VARCHAR(50) NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    variant VARCHAR(100) NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    inventory_deductions JSON NULL,
    batch_number VARCHAR(50) NULL,
    expiry_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction (transaction_id),
    INDEX idx_product (product_id),
    FOREIGN KEY (transaction_id) REFERENCES sales_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure columns exist for older schemas
ALTER TABLE sales_transaction_items
    ADD COLUMN IF NOT EXISTS variant VARCHAR(100) NULL AFTER product_name,
    ADD COLUMN IF NOT EXISTS inventory_deductions JSON NULL AFTER line_total;

-- ===================================
-- 4. Create cashier_shifts table
-- ===================================
CREATE TABLE IF NOT EXISTS cashier_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_code VARCHAR(30) NOT NULL UNIQUE,
    cashier_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_cash DECIMAL(12,2) NULL,
    actual_cash DECIMAL(12,2) NULL,
    cash_variance DECIMAL(12,2) NULL,
    total_sales DECIMAL(12,2) DEFAULT 0,
    total_collections DECIMAL(12,2) DEFAULT 0,
    total_transactions INT DEFAULT 0,
    cash_in DECIMAL(12,2) DEFAULT 0,
    cash_out DECIMAL(12,2) DEFAULT 0,
    status ENUM('active', 'closed', 'reconciled') DEFAULT 'active',
    opening_notes TEXT,
    closing_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cashier (cashier_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 5. Create cash_adjustments table
-- ===================================
CREATE TABLE IF NOT EXISTS cash_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NULL,
    adjustment_type ENUM('in', 'out') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    reference_number VARCHAR(50) NULL,
    performed_by INT NOT NULL,
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shift (shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 6. Create fg_inventory_transactions table
-- ===================================
CREATE TABLE IF NOT EXISTS fg_inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(50) NOT NULL,
    transaction_type ENUM('receive', 'sale', 'transfer', 'adjustment', 'disposal', 'return') NOT NULL,
    inventory_id INT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    boxes_quantity INT DEFAULT 0,
    pieces_quantity INT DEFAULT 0,
    quantity_before INT NULL,
    quantity_after INT NULL,
    boxes_before INT NULL,
    pieces_before INT NULL,
    boxes_after INT NULL,
    pieces_after INT NULL,
    from_chiller_id INT NULL,
    to_chiller_id INT NULL,
    performed_by INT NOT NULL,
    reason TEXT,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (transaction_code),
    INDEX idx_type (transaction_type),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 7. Create product_prices table
-- ===================================
CREATE TABLE IF NOT EXISTS product_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    price_type ENUM('retail', 'wholesale', 'special') DEFAULT 'retail',
    unit_price DECIMAL(12,2) NOT NULL,
    selling_price DECIMAL(12,2) NOT NULL,
    min_quantity INT DEFAULT 1,
    effective_date DATE NOT NULL,
    end_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================
-- 8. Update products with realistic prices
-- ===================================
UPDATE products SET 
    unit_price = CASE 
        WHEN category = 'pasteurized_milk' AND unit_size <= 250 THEN 35.00
        WHEN category = 'pasteurized_milk' AND unit_size <= 500 THEN 55.00
        WHEN category = 'pasteurized_milk' AND unit_size <= 1000 THEN 95.00
        WHEN category = 'flavored_milk' THEN 40.00
        WHEN category = 'yogurt' THEN 45.00
        WHEN category = 'cheese' THEN 150.00
        WHEN category = 'butter' THEN 120.00
        WHEN category = 'cream' THEN 80.00
        ELSE 50.00
    END,
    selling_price = CASE 
        WHEN category = 'pasteurized_milk' AND unit_size <= 250 THEN 38.00
        WHEN category = 'pasteurized_milk' AND unit_size <= 500 THEN 60.00
        WHEN category = 'pasteurized_milk' AND unit_size <= 1000 THEN 105.00
        WHEN category = 'flavored_milk' THEN 45.00
        WHEN category = 'yogurt' THEN 50.00
        WHEN category = 'cheese' THEN 175.00
        WHEN category = 'butter' THEN 140.00
        WHEN category = 'cream' THEN 95.00
        ELSE 55.00
    END,
    cost_price = CASE 
        WHEN category = 'pasteurized_milk' AND unit_size <= 250 THEN 28.00
        WHEN category = 'pasteurized_milk' AND unit_size <= 500 THEN 45.00
        WHEN category = 'pasteurized_milk' AND unit_size <= 1000 THEN 78.00
        WHEN category = 'flavored_milk' THEN 32.00
        WHEN category = 'yogurt' THEN 35.00
        WHEN category = 'cheese' THEN 120.00
        WHEN category = 'butter' THEN 95.00
        WHEN category = 'cream' THEN 65.00
        ELSE 40.00
    END
WHERE is_active = 1;

-- ===================================
-- 9. Insert sample production batches for each product
-- ===================================
INSERT INTO production_batches (batch_code, product_id, milk_type_id, product_type, raw_milk_liters, 
    manufacturing_date, expiry_date, expected_yield, actual_yield, qc_status, created_by)
SELECT 
    CONCAT('BATCH-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(p.id, 3, '0')),
    p.id,
    COALESCE(p.milk_type_id, 1),
    p.category,
    50.00,
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL COALESCE(p.shelf_life_days, 7) DAY),
    120,
    100,
    'released',
    1
FROM products p
WHERE p.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM production_batches pb WHERE pb.product_id = p.id AND pb.qc_status = 'released'
);

-- ===================================
-- 10. Insert QC releases for batches (using actual table structure)
-- ===================================
INSERT INTO qc_batch_release (release_code, batch_id, inspection_datetime, release_decision, inspected_by, approved_by, approval_datetime)
SELECT 
    CONCAT('QCR-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(pb.id, 3, '0')),
    pb.id,
    NOW(),
    'approved',
    1,
    1,
    NOW()
FROM production_batches pb
WHERE pb.qc_status = 'released'
AND NOT EXISTS (
    SELECT 1 FROM qc_batch_release qcr WHERE qcr.batch_id = pb.id
);

-- ===================================
-- 11. Insert finished goods inventory
-- ===================================
INSERT INTO finished_goods_inventory 
    (batch_id, qc_release_id, product_id, milk_type_id, product_name, product_type,
     quantity, remaining_quantity, quantity_available, 
     boxes_available, pieces_available, unit, manufacturing_date, expiry_date, 
     chiller_id, status, received_at, received_by)
SELECT 
    pb.id as batch_id,
    qcr.id as qc_release_id,
    p.id as product_id,
    COALESCE(p.milk_type_id, 1) as milk_type_id,
    p.product_name,
    CASE 
        WHEN p.category IN ('pasteurized_milk', 'flavored_milk') THEN 'bottled_milk'
        WHEN p.category = 'cheese' THEN 'cheese'
        WHEN p.category = 'butter' THEN 'butter'
        WHEN p.category = 'yogurt' THEN 'yogurt'
        ELSE 'bottled_milk'
    END as product_type,
    pb.actual_yield as quantity,
    pb.actual_yield as remaining_quantity,
    pb.actual_yield as quantity_available,
    FLOOR(pb.actual_yield / COALESCE(p.pieces_per_box, 12)) as boxes_available,
    pb.actual_yield MOD COALESCE(p.pieces_per_box, 12) as pieces_available,
    COALESCE(p.base_unit, 'piece') as unit,
    pb.manufacturing_date,
    pb.expiry_date,
    1 as chiller_id,
    'available' as status,
    NOW() as received_at,
    1 as received_by
FROM products p
JOIN production_batches pb ON pb.product_id = p.id AND pb.qc_status = 'released'
JOIN qc_batch_release qcr ON qcr.batch_id = pb.id AND qcr.release_decision = 'approved'
WHERE p.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM finished_goods_inventory fg 
    WHERE fg.product_id = p.id AND fg.batch_id = pb.id AND fg.status = 'available'
);

-- ===================================
-- 12. Create price entries in product_prices
-- ===================================
INSERT INTO product_prices (product_id, price_type, unit_price, selling_price, effective_date, is_active)
SELECT 
    p.id,
    'retail',
    p.unit_price,
    p.selling_price,
    CURDATE(),
    1
FROM products p
WHERE p.is_active = 1
AND p.selling_price > 0
AND NOT EXISTS (
    SELECT 1 FROM product_prices pp WHERE pp.product_id = p.id AND pp.is_active = 1
);

SELECT 'POS Setup complete!' as status;
