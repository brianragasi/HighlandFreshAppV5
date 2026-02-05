-- =====================================================
-- Update Sales Test Users with Complete Information
-- Run this in phpMyAdmin or MySQL CLI
-- =====================================================

-- Update Cashier user with proper details
UPDATE users 
SET 
    first_name = 'Ana',
    last_name = 'Reyes',
    full_name = 'Ana Reyes',
    email = 'cashier@highlandfresh.com',
    employee_id = 'EMP-CASH-001'
WHERE username = 'cashier';

-- Update Sales Custodian user with proper details
UPDATE users 
SET 
    first_name = 'Miguel',
    last_name = 'Torres',
    full_name = 'Miguel Torres',
    email = 'sales.custodian@highlandfresh.com',
    employee_id = 'EMP-SALES-001'
WHERE username = 'sales_custodian';

-- Verify the updates
SELECT id, username, full_name, first_name, last_name, role, email, employee_id, is_active 
FROM users 
WHERE role IN ('cashier', 'sales_custodian');
