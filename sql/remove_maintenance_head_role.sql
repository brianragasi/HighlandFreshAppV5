-- Retire the standalone Maintenance Head role without breaking historical records.
-- Warehouse Raw now owns MRO requests and equipment issue logging; the GM keeps oversight.

START TRANSACTION;

UPDATE auth_sessions s
JOIN users u ON u.id = s.user_id
SET s.revoked_at = COALESCE(s.revoked_at, NOW()),
    s.revoked_reason = COALESCE(s.revoked_reason, 'role_removed'),
    s.updated_at = NOW()
WHERE u.role = 'maintenance_head'
  AND s.revoked_at IS NULL;

-- Keep the user row because old repair and requisition records may reference its ID.
UPDATE users
SET role = 'warehouse_raw',
    is_active = 0,
    username = CASE
        WHEN username = 'maintenance_head' THEN CONCAT('retired_equipment_account_', id)
        ELSE username
    END,
    email = CASE
        WHEN email IN ('maintenance@gmail.com', 'maintenance@highlandfresh.com') THEN NULL
        ELSE email
    END,
    updated_at = NOW()
WHERE role = 'maintenance_head';

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
