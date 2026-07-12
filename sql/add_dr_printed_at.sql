-- Track when a Delivery Receipt paper copy was printed.
-- Physical DR must leave with the driver (Delivered By / Received By signatures).
-- Dispatch is blocked until printed_at is set.

ALTER TABLE delivery_receipts
  ADD COLUMN IF NOT EXISTS printed_at DATETIME NULL DEFAULT NULL AFTER prepared_at;

ALTER TABLE delivery_receipts
  ADD COLUMN IF NOT EXISTS printed_by INT NULL DEFAULT NULL AFTER printed_at;

SELECT 'printed_at / printed_by columns ready' AS status;
