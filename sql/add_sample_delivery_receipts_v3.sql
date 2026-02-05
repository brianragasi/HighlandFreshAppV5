-- Highland Fresh - Sample Delivery Receipts for Testing Collections
-- Compatible with actual schema

-- First, ensure we have customers for testing
INSERT INTO customers (customer_type, name, sub_location, contact_person, contact_number, email, address, credit_limit, current_balance, payment_terms_days, status, created_at)
VALUES 
    ('institutional', 'SM Supermarket', 'Tacloban Branch', 'John Manager', '09171234567', 'sm@example.com', 'SM Tacloban', 100000.00, 0.00, 30, 'active', NOW()),
    ('institutional', 'Robinson\'s Supermarket', 'Downtown', 'Maria Supervisor', '09181234567', 'robinsons@example.com', 'Robinsons Place Tacloban', 80000.00, 0.00, 30, 'active', NOW()),
    ('supermarket', 'Metro Gaisano', 'Downtown', 'Pedro Cruz', '09191234567', 'gaisano@example.com', 'Downtown Tacloban', 50000.00, 0.00, 15, 'active', NOW()),
    ('supermarket', 'PureGold', 'Real Street', 'Ana Reyes', '09201234567', 'puregold@example.com', 'Real Street Tacloban', 75000.00, 0.00, 30, 'active', NOW()),
    ('restaurant', 'Hotel 101', 'Main', 'Chris Santos', '09211234567', 'hotel101@example.com', 'Hotel 101 Tacloban', 30000.00, 0.00, 7, 'active', NOW())
ON DUPLICATE KEY UPDATE name = name;

-- Add payment tracking columns if they don't exist
ALTER TABLE delivery_receipts 
    ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid' AFTER status,
    ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(12,2) DEFAULT 0.00 AFTER total_amount;

-- Sample delivery receipts with varying payment statuses
INSERT INTO delivery_receipts 
    (dr_number, customer_id, customer_name, delivery_address, contact_number, total_items, total_amount, payment_status, amount_paid, status, created_by, dispatched_at, dispatched_by, delivered_at) 
VALUES
    (CONCAT('DR-', DATE_FORMAT(NOW(), '%Y%m%d'), '-0101'), 1, 'SM Supermarket', 'SM Tacloban', '09171234567', 50, 8500.00, 'unpaid', 0.00, 'delivered', 1, NOW(), 1, NOW()),
    (CONCAT('DR-', DATE_FORMAT(NOW(), '%Y%m%d'), '-0102'), 2, 'Robinson\'s Supermarket', 'Robinsons Tacloban', '09181234567', 30, 5250.00, 'unpaid', 0.00, 'delivered', 1, NOW(), 1, NOW()),
    (CONCAT('DR-', DATE_FORMAT(NOW(), '%Y%m%d'), '-0103'), 3, 'Metro Gaisano', 'Downtown Tacloban', '09191234567', 75, 12750.00, 'partial', 5000.00, 'delivered', 1, NOW(), 1, NOW()),
    (CONCAT('DR-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 DAY), '%Y%m%d'), '-0104'), 4, 'PureGold', 'Real Street Tacloban', '09201234567', 40, 9500.00, 'unpaid', 0.00, 'delivered', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (CONCAT('DR-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 DAY), '%Y%m%d'), '-0105'), 5, 'Hotel 101', 'Hotel 101 Tacloban', '09211234567', 25, 4500.00, 'partial', 2000.00, 'delivered', 1, DATE_SUB(NOW(), INTERVAL 5 DAY), 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
    (CONCAT('DR-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 7 DAY), '%Y%m%d'), '-0106'), 1, 'SM Supermarket', 'SM Tacloban', '09171234567', 60, 15000.00, 'partial', 7500.00, 'delivered', 1, DATE_SUB(NOW(), INTERVAL 7 DAY), 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
    (CONCAT('DR-', DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 10 DAY), '%Y%m%d'), '-0107'), 2, 'Robinson\'s Supermarket', 'Robinsons Tacloban', '09181234567', 45, 11250.00, 'unpaid', 0.00, 'delivered', 1, DATE_SUB(NOW(), INTERVAL 10 DAY), 1, DATE_SUB(NOW(), INTERVAL 10 DAY))
ON DUPLICATE KEY UPDATE total_amount = VALUES(total_amount);

-- Summary of test data
SELECT 
    'Delivery Receipts Summary' as info,
    COUNT(*) as total,
    SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid,
    SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial,
    FORMAT(SUM(total_amount - COALESCE(amount_paid, 0)), 2) as total_outstanding
FROM delivery_receipts
WHERE payment_status IN ('unpaid', 'partial');
