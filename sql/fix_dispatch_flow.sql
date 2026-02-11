-- Fix Warehouse FG dispatch flow
-- Add 'picking' status to delivery_receipts
-- DR number should only be assigned AFTER picking is complete (per workflow doc)

-- Add 'picking' status to delivery_receipts
ALTER TABLE delivery_receipts 
MODIFY COLUMN status ENUM('draft', 'pending', 'picking', 'preparing', 'ready', 'dispatched', 'delivered', 'cancelled') DEFAULT 'draft';

-- Add picking_started_at column to track when picking began
ALTER TABLE delivery_receipts
ADD COLUMN picking_started_at DATETIME NULL AFTER status;

-- Update any existing 'preparing' status to 'picking' for consistency
UPDATE delivery_receipts SET status = 'picking' WHERE status = 'preparing';
