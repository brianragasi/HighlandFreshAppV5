-- =============================================================
-- V4.0.1 — Repair requisition_items status for partial issuances
-- =============================================================
-- Issue (2026-06-15, surfaced on REQ-20260615-007 Sugar):
--   The warehouse bulk-fulfill action at api/warehouse/raw/requisitions.php
--   had a double-add bug in the CASE expression that decides item status.
--   The CASE was re-adding $issuedQty to the (newly written) issued_quantity
--   and flipping the status to 'fulfilled' even when only a partial amount
--   was issued. For example, requested=25, issued 23.94 -> CASE saw
--   23.94 + 23.94 = 47.88 >= 25 -> 'fulfilled' (wrong, should be 'partial').
--
-- This script does a one-time repair of any rows that were incorrectly
-- marked 'fulfilled' while still having issued_quantity < requested_quantity.
-- The same one-liner runs as part of the 'fulfill' handler in PHP now
-- (so this is the data cleanup for historical rows only).
--
-- Verification before:
--   SELECT id, item_name, requested_quantity, issued_quantity, status
--     FROM requisition_items
--    WHERE status = 'fulfilled' AND issued_quantity < requested_quantity;
-- =============================================================

START TRANSACTION;

-- Repair: flip status to 'partial' where the row is marked 'fulfilled'
-- but actually still has a shortfall. We leave 'pending' and 'partial'
-- alone (they're correct).
UPDATE requisition_items
   SET status = 'partial',
       notes = CONCAT(IFNULL(notes, ''),
                      IF(IFNULL(notes, '') = '', '', ' | '),
                      'V4.0.1 cleanup: status corrected from phantom-fulfilled to partial; shortfall = ',
                      ROUND(requested_quantity - issued_quantity, 3))
 WHERE status = 'fulfilled'
   AND issued_quantity < requested_quantity;

COMMIT;

-- Verification SELECT — should return zero rows
SELECT id, requisition_id, item_name, requested_quantity, issued_quantity, status
  FROM requisition_items
 WHERE status = 'fulfilled' AND issued_quantity < requested_quantity;
