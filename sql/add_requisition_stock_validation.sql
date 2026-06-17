-- =============================================================================
-- Requisition Stock Validation & Removal of GM Approval Gate
-- Version: Highland Fresh V4.0
-- Date: 2026-06-14
--
-- What this migration does
-- ------------------------
-- 1. Adds stock-override columns to material_requisitions so production staff
--    can submit a requisition that exceeds available stock by explicitly
--    acknowledging the shortage (the prof's "detected, not silently accepted"
--    requirement).
--
-- 2. Creates requisition_stock_warnings — a per-item audit trail of every
--    stock override decision (who, what req, what item, requested qty,
--    available qty, shortage, role, decision, reason, timestamp).
--    This is queryable for the prof's "show me the override history" review.
--
-- 3. The runtime ALTER / CREATE on these columns and this table is also done
--    idempotently by ensureStockValidationTables() in
--    api/production/requisitions.php so a fresh install doesn't need to run
--    this script manually. This file is here for documentation + ops runbook.
--
-- Workflow change (no SQL needed, but documented for the team)
-- ------------------------------------------------------------
-- Production → Warehouse Raw no longer routes through GM approval.
--   - Production creates requisition: status 'pending' (no 'approved' gate)
--   - Warehouse Raw fulfills directly from 'pending' (or 'partial' on top-up)
--   - GM keeps read-only access for dashboards / history (no approve/reject)
--
-- The 'approved' status remains in the enum for legacy rows but is no longer
-- set by new requisitions.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Stock-override columns on material_requisitions
-- -----------------------------------------------------------------------------
ALTER TABLE material_requisitions
    ADD COLUMN IF NOT EXISTS stock_override_acknowledged TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = requester explicitly acknowledged a stock shortage on submit',
    ADD COLUMN IF NOT EXISTS stock_override_by INT NULL
        COMMENT 'FK to users — who acknowledged the shortage',
    ADD COLUMN IF NOT EXISTS stock_override_reason VARCHAR(255) NULL
        COMMENT 'Free-text reason for the override (e.g. PO incoming, transfer en route)',
    ADD COLUMN IF NOT EXISTS stock_override_at DATETIME NULL
        COMMENT 'When the override was acknowledged';

-- -----------------------------------------------------------------------------
-- 2. Per-item stock-override audit table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS requisition_stock_warnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL
        COMMENT 'FK to material_requisitions.id',
    requisition_item_id INT NULL
        COMMENT 'FK to requisition_items.id (NULL for pre-submit checks, set on actual override)',
    ingredient_id INT NULL
        COMMENT 'FK to ingredients.id (NULL for raw_milk and MRO rows)',
    item_name VARCHAR(150) NOT NULL
        COMMENT 'Denormalized for audit readability even if ingredient is renamed',
    requested_qty DECIMAL(10,3) NOT NULL,
    available_qty DECIMAL(10,3) NOT NULL,
    shortage DECIMAL(10,3) NOT NULL
        COMMENT 'requested_qty - available_qty, always >= 0',
    decision ENUM('blocked','overridden') NOT NULL
        COMMENT 'blocked = server returned 422, overridden = requester acknowledged',
    decided_by INT NULL
        COMMENT 'FK to users',
    decided_role VARCHAR(40) NULL
        COMMENT 'Role at time of decision — production_staff, general_manager, etc.',
    override_reason VARCHAR(255) NULL
        COMMENT 'Free-text when decision = overridden',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_rsw_requisition (requisition_id),
    INDEX idx_rsw_ingredient (ingredient_id),
    INDEX idx_rsw_decision (decision),
    INDEX idx_rsw_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-item audit of stock-validation decisions; queryable for review.';

-- -----------------------------------------------------------------------------
-- 3. No status-enum change needed. 'approved' is kept for legacy rows; the
--    production POST handler now inserts with status='pending' and the
--    warehouse fulfill handler accepts 'pending' (in addition to 'partial' and
--    'in_progress').
-- -----------------------------------------------------------------------------
