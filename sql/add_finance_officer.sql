-- ================================================================
-- Highland Fresh - Add Finance Officer User
-- Run this SQL in phpMyAdmin or MySQL CLI
-- ================================================================

USE highland_fresh;

-- Insert Finance Officer user
-- Password: 'password' hashed with PASSWORD_DEFAULT
INSERT INTO users (username, password, full_name, first_name, last_name, email, employee_id, role, is_active, created_at)
VALUES (
    'finance_officer',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: 'password'
    'Maria Santos',
    'Maria',
    'Santos',
    'finance@highlandfresh.com',
    'EMP-FIN-001',
    'finance_officer',
    1,
    NOW()
)
ON DUPLICATE KEY UPDATE
    role = 'finance_officer',
    is_active = 1;

-- Verify
SELECT id, username, full_name, role, is_active FROM users WHERE role = 'finance_officer';
