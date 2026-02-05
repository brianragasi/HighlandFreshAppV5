-- =====================================================
-- Highland Fresh Dairy - Data Sync Audit Fix Migration
-- Generated: 2026-02-05
-- Purpose: Fix critical issues found in 8-subagent audit
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. SALES MODULE FIXES
-- =====================================================

-- 1.1 Create missing sales_order_status_history table
CREATE TABLE IF NOT EXISTS sales_order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    changed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_changed_by (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.2 Add missing columns to sales_orders
ALTER TABLE sales_orders 
    ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(12,2) DEFAULT 0.00 AFTER discount_percent,
    ADD COLUMN IF NOT EXISTS cancellation_reason TEXT AFTER notes,
    ADD COLUMN IF NOT EXISTS cancelled_by INT AFTER cancellation_reason,
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME AFTER cancelled_by;

-- 1.3 Add missing columns to sales_invoices
ALTER TABLE sales_invoices 
    ADD COLUMN IF NOT EXISTS void_reason TEXT,
    ADD COLUMN IF NOT EXISTS voided_by INT,
    ADD COLUMN IF NOT EXISTS voided_at DATETIME,
    ADD COLUMN IF NOT EXISTS last_payment_date DATE;

-- 1.4 Add missing columns to sales_invoice_payments (for check payments)
ALTER TABLE sales_invoice_payments
    ADD COLUMN IF NOT EXISTS check_number VARCHAR(50),
    ADD COLUMN IF NOT EXISTS check_date DATE,
    ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100);
-- Note: 'notes' column may already exist, skip if error

-- =====================================================
-- 2. PRODUCTION MODULE FIXES (CRITICAL)
-- =====================================================

-- 2.1 Create missing ingredient_consumption table
CREATE TABLE IF NOT EXISTS ingredient_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id INT NOT NULL,
    ingredient_id INT,
    ingredient_name VARCHAR(100),
    quantity_used DECIMAL(10,3),
    unit VARCHAR(20),
    batch_code VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_run_id (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.2 Add missing columns to production_runs
ALTER TABLE production_runs
    ADD COLUMN IF NOT EXISTS milk_batch_source JSON DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS process_temperature DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS process_duration_mins INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ingredient_adjustments JSON DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cream_output_kg DECIMAL(10,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS skim_milk_output_liters DECIMAL(10,2) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cheese_state VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_salted TINYINT(1) DEFAULT 0;

-- 2.3 Add missing columns to pasteurized_milk_inventory
ALTER TABLE pasteurized_milk_inventory
    ADD COLUMN IF NOT EXISTS source_type VARCHAR(50) DEFAULT 'pasteurization_run',
    ADD COLUMN IF NOT EXISTS pasteurization_duration_mins INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS pasteurized_by INT DEFAULT NULL;

-- 2.4 Add skim_milk to production_byproducts byproduct_type enum
-- First check current enum values and modify
ALTER TABLE production_byproducts 
    MODIFY COLUMN byproduct_type ENUM('buttermilk','whey','cream','skim_milk','other') DEFAULT 'other';

-- =====================================================
-- 3. POS MODULE FIXES
-- =====================================================

-- 3.1 Drop unused orphan tables (backup first if needed)
-- Only run these if you confirm these tables are truly unused
-- DROP TABLE IF EXISTS pos_transaction_items;
-- DROP TABLE IF EXISTS pos_transactions;

-- 3.2 Add dr_id to sales_transactions for credit sale tracking
ALTER TABLE sales_transactions 
    ADD COLUMN IF NOT EXISTS dr_id INT AFTER shift_id,
    ADD INDEX IF NOT EXISTS idx_dr (dr_id);

-- =====================================================
-- 4. WAREHOUSE FG FIXES
-- =====================================================

-- 4.1 No schema changes needed - code fixes only for:
-- - fg_inventory → finished_goods_inventory (table name in queries)
-- - dr_id → delivery_receipt_id (column name in queries)
-- - quantity → quantity_released (column name in queries)

-- =====================================================
-- 5. QC MODULE - Add unused columns for completeness
-- =====================================================

-- The QC module works but has unused columns - no changes needed

-- =====================================================
-- 6. TEST DATA - Add sample feeding program customer
-- =====================================================

-- Insert a feeding program customer for testing
INSERT INTO customers (
    customer_code, 
    name, 
    customer_type, 
    contact_person,
    contact_number,
    address,
    credit_limit,
    status,
    created_at
) VALUES (
    'DEPED-CDO-001', 
    'DepEd Region X Feeding Program', 
    'feeding_program',
    'Maria Santos',
    '09171234567',
    'DepEd Complex, Cagayan de Oro City',
    500000.00,
    'active',
    NOW()
) ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Add sample sub-accounts (schools) for the feeding program
INSERT INTO sales_customer_sub_accounts (
    customer_id,
    sub_name,
    address,
    contact_person,
    contact_number,
    status
) 
SELECT 
    c.id,
    s.sub_name,
    s.address,
    s.contact_person,
    s.contact_number,
    'active'
FROM customers c
CROSS JOIN (
    SELECT 'Lumbia Elementary School' as sub_name, 'Lumbia, CDO' as address, 'Juan Cruz' as contact_person, '09181234567' as contact_number UNION ALL
    SELECT 'Macabalan Elementary School', 'Macabalan, CDO', 'Ana Reyes', '09191234567' UNION ALL
    SELECT 'Lapasan National High School', 'Lapasan, CDO', 'Pedro Garcia', '09201234567' UNION ALL
    SELECT 'Bulua Elementary School', 'Bulua, CDO', 'Elena Torres', '09211234567' UNION ALL
    SELECT 'Kauswagan Central School', 'Kauswagan, CDO', 'Roberto Lim', '09221234567'
) s
WHERE c.customer_code = 'DEPED-CDO-001'
ON DUPLICATE KEY UPDATE sub_name = VALUES(sub_name);

-- =====================================================
-- 7. ADD FOREIGN KEY CONSTRAINTS (Optional but recommended)
-- =====================================================

-- Add FK for sales_order_status_history
-- ALTER TABLE sales_order_status_history
--     ADD CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
--     ADD CONSTRAINT fk_order_history_user FOREIGN KEY (changed_by) REFERENCES users(id);

-- Add FK for ingredient_consumption
-- ALTER TABLE ingredient_consumption
--     ADD CONSTRAINT fk_consumption_run FOREIGN KEY (run_id) REFERENCES production_runs(id) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Check tables created
SELECT 'sales_order_status_history' as tbl, COUNT(*) as exists_check FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sales_order_status_history'
UNION ALL
SELECT 'ingredient_consumption', COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ingredient_consumption';

-- Check feeding program customer
SELECT id, customer_code, name, customer_type FROM customers WHERE customer_type = 'feeding_program';

-- Check sub-accounts
SELECT sa.id, sa.sub_name, c.name as parent_customer 
FROM sales_customer_sub_accounts sa 
JOIN customers c ON sa.customer_id = c.id 
LIMIT 10;

SELECT 'Migration completed successfully!' as status;
