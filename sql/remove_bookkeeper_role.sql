-- Retire the Bookkeeper user role. Finance owns payment status updates.
-- Keep the main admin account usable by assigning it to General Manager.

START TRANSACTION;

UPDATE auth_sessions s
JOIN users u ON u.id = s.user_id
SET s.revoked_at = COALESCE(s.revoked_at, NOW()),
    s.revoked_reason = COALESCE(s.revoked_reason, 'role_removed'),
    s.updated_at = NOW()
WHERE u.role = 'bookkeeper'
  AND s.revoked_at IS NULL;

UPDATE users
SET role = 'general_manager',
    updated_at = NOW()
WHERE role = 'bookkeeper'
  AND username = 'admin';

-- Preserve any unexpected old account for audit history without allowing sign-in.
UPDATE users
SET role = 'finance_officer',
    is_active = 0,
    username = CONCAT('retired_bookkeeping_account_', id),
    email = NULL,
    updated_at = NOW()
WHERE role = 'bookkeeper';

COMMIT;

ALTER TABLE users
MODIFY COLUMN role ENUM(
    'general_manager',
    'qc_officer',
    'production_staff',
    'warehouse_raw',
    'warehouse_fg',
    'sales_custodian',
    'cashier',
    'purchaser',
    'finance_officer'
) NOT NULL;
