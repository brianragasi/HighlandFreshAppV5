-- ============================================================================
-- Fix the 10 requisitions that were fulfilled without a planned recipe/quantity.
-- This was possible because the warehouse raw fulfill endpoint did not enforce
-- the plan guard. That guard has now been added in code; this script just
-- cleans up the historical data.
--
-- Run the SELECTs first to audit the current state. Only run the UPDATE once
-- you've confirmed which rows you want to reset.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. AUDIT: show all fulfilled requisitions with a missing plan + what they
--    were trying to issue. Use this to decide whether each row needs a plan
--    set manually or should be un-fulfilled.
-- ----------------------------------------------------------------------------
SELECT
    ir.id,
    ir.requisition_code,
    ir.status,
    ir.planned_recipe_id,
    ir.planned_quantity,
    ir.production_run_id,
    ir.fulfilled_by,
    ir.fulfilled_at,
    ir.requested_by,
    u.first_name AS requested_by_first,
    u.last_name  AS requested_by_last,
    (SELECT COUNT(*) FROM requisition_items ri WHERE ri.requisition_id = ir.id) AS item_count,
    (SELECT GROUP_CONCAT(CONCAT(ri.item_name, ' (', ri.requested_quantity, ' ', ri.unit_of_measure, ')') SEPARATOR '; ')
       FROM requisition_items ri WHERE ri.requisition_id = ir.id) AS items_summary
FROM material_requisitions ir
LEFT JOIN users u ON ir.requested_by = u.id
WHERE ir.status = 'fulfilled'
  AND (ir.planned_recipe_id IS NULL OR ir.planned_quantity IS NULL OR ir.planned_quantity = 0)
ORDER BY ir.id;

-- ----------------------------------------------------------------------------
-- 2. AUDIT (deeper): for the rows that have a production_run_id set, show
--    the linked run too. This is the data-integrity issue where a run was
--    started without a plan. Decide per row whether to:
--      (a) clear production_run_id + re-fulfill after setting a plan, OR
--      (b) leave the run as-is and just set a plan retroactively on the req.
-- ----------------------------------------------------------------------------
SELECT
    ir.id              AS req_id,
    ir.requisition_code,
    ir.production_run_id,
    pr.run_code        AS production_run_code,
    pr.status          AS run_status,
    pr.recipe_id       AS run_recipe_id,
    pr.planned_quantity AS run_planned_quantity
FROM material_requisitions ir
LEFT JOIN production_runs pr ON pr.id = ir.production_run_id
WHERE ir.status = 'fulfilled'
  AND (ir.planned_recipe_id IS NULL OR ir.planned_quantity IS NULL OR ir.planned_quantity = 0)
  AND ir.production_run_id IS NOT NULL
ORDER BY ir.id;

-- ----------------------------------------------------------------------------
-- 3. UNDO FULFILLMENT for the broken rows (the safe default).
--    This reverts the requisition status back to 'approved' and zeros the
--    issued quantities on the items, so warehouse raw can re-fulfill after
--    the requester (or GM) sets the plan via the UI.
--
--    IMPORTANT: this does NOT clear production_run_id on the 3 broken rows
--    that have one. The runs themselves are still real production runs; only
--    the link is broken. If you want to break that link, see query 4 below.
-- ----------------------------------------------------------------------------
START TRANSACTION;

UPDATE material_requisitions ir
SET status        = 'approved',
    fulfilled_by  = NULL,
    fulfilled_at  = NULL,
    updated_at    = NOW()
WHERE ir.status = 'fulfilled'
  AND (ir.planned_recipe_id IS NULL OR ir.planned_quantity IS NULL OR ir.planned_quantity = 0);

-- Reset the items on those requisitions so warehouse raw can re-issue.
UPDATE requisition_items ri
JOIN material_requisitions ir ON ir.id = ri.requisition_id
SET ri.issued_quantity = 0,
    ri.status          = 'pending',
    ri.fulfilled_by    = NULL,
    ri.fulfilled_at    = NULL,
    ri.updated_at      = NOW()
WHERE ir.status = 'approved'
  AND (ir.planned_recipe_id IS NULL OR ir.planned_quantity IS NULL OR ir.planned_quantity = 0)
  AND ri.status IN ('fulfilled', 'partial');

-- Verify the reset (should be 0 rows now)
SELECT COUNT(*) AS still_broken_rows
FROM material_requisitions
WHERE status = 'fulfilled'
  AND (planned_recipe_id IS NULL OR planned_quantity IS NULL OR planned_quantity = 0);

-- If the count above is 0, COMMIT. Otherwise, ROLLBACK and investigate.
-- COMMIT;
-- ROLLBACK;

-- ----------------------------------------------------------------------------
-- 4. (OPTIONAL) For the 3 rows that already have a production_run_id, you can
--    also break the link so the run is no longer tied to a "fulfilled" req.
--    This is destructive — only run it if you decide the runs are bogus.
-- ----------------------------------------------------------------------------
-- UPDATE material_requisitions
-- SET production_run_id = NULL
-- WHERE id IN (9, 10, 11);  -- REQ-20260209-001, REQ-20260211-001, REQ-20260211-002

-- ============================================================================
-- End of cleanup script. After running query 3, reload the requisitions page
-- and each broken row will show the yellow "Needs plan" badge. Open each,
-- set the planned recipe + planned quantity, then have warehouse raw re-fulfill.
-- ============================================================================
