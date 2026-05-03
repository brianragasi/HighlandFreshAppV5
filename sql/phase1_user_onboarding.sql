-- ============================================================================
-- Phase 1: User Onboarding — Core Identity and First Login
-- Highland Fresh Dairy Production System
-- 
-- This migration adds:
--   1. login_identifier, login_type, must_change_password, password_set_at, last_login_at
--   2. Backfills existing users based on email / employee_id / username
--   3. Adds a unique index on login_identifier for fast lookups
-- ============================================================================

-- Step 1: Add new columns (safe with IF NOT EXISTS pattern via ALTER IGNORE)

-- login_identifier: the value the user types to log in (email or employee_id)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'login_identifier');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN login_identifier VARCHAR(255) NULL AFTER email',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- login_type: enum to record which kind of identifier this user has
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'login_type');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN login_type ENUM(''email'',''employee_id'',''username'') NOT NULL DEFAULT ''username'' AFTER login_identifier',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- must_change_password: forces password change on next login
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER login_type',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- password_set_at: tracks when password was last set/changed
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_set_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN password_set_at DATETIME NULL AFTER must_change_password',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- last_login_at: tracks last successful login
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_login_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER password_set_at',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Step 2: Backfill existing users
-- Priority: email > employee_id > username
UPDATE users
SET login_type = 'email',
    login_identifier = LOWER(TRIM(email))
WHERE email IS NOT NULL AND TRIM(email) != ''
  AND (login_identifier IS NULL OR login_identifier = '');

UPDATE users
SET login_type = 'employee_id',
    login_identifier = TRIM(employee_id)
WHERE (login_identifier IS NULL OR login_identifier = '')
  AND employee_id IS NOT NULL AND TRIM(employee_id) != '';

UPDATE users
SET login_type = 'username',
    login_identifier = LOWER(TRIM(username))
WHERE login_identifier IS NULL OR login_identifier = '';

-- Existing users already have passwords, so set must_change_password = 0
-- and password_set_at to their created_at date
UPDATE users
SET must_change_password = 0,
    password_set_at = COALESCE(updated_at, created_at, NOW())
WHERE password_set_at IS NULL;


-- Step 3: Add index for login lookups (idempotent)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_login_identifier');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE users ADD INDEX idx_users_login_identifier (login_identifier)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Done. All existing users now have a login_identifier and can log in
-- using their email, employee_id, or username depending on what was available.
