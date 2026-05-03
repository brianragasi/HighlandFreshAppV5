-- =============================================
-- Highland Fresh - Tamper-evident audit chain
-- Adds hash-chain fields to audit_logs
-- =============================================

ALTER TABLE audit_logs
    ADD COLUMN IF NOT EXISTS prev_hash CHAR(64) NULL AFTER user_agent,
    ADD COLUMN IF NOT EXISTS entry_hash CHAR(64) NULL AFTER prev_hash;

ALTER TABLE audit_logs
    ADD UNIQUE KEY uk_audit_logs_entry_hash (entry_hash);

ALTER TABLE audit_logs
    ADD KEY idx_audit_logs_prev_hash (prev_hash);
