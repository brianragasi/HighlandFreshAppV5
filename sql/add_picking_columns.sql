-- Add columns needed for picking flow
-- Run this script to enable the new picking workflow

-- Add quantity_picked column to track how many pieces were actually picked
ALTER TABLE delivery_receipt_items 
ADD COLUMN IF NOT EXISTS quantity_picked INT DEFAULT 0 AFTER quantity_ordered;

-- Add inventory_id to track which inventory batch was used
ALTER TABLE delivery_receipt_items 
ADD COLUMN IF NOT EXISTS inventory_id INT DEFAULT NULL AFTER quantity_picked;

-- Add picked_at timestamp
ALTER TABLE delivery_receipt_items 
ADD COLUMN IF NOT EXISTS picked_at DATETIME DEFAULT NULL AFTER inventory_id;

-- Add foreign key constraint for inventory_id (optional, may fail if data exists)
-- ALTER TABLE delivery_receipt_items 
-- ADD CONSTRAINT fk_dri_inventory 
-- FOREIGN KEY (inventory_id) REFERENCES finished_goods_inventory(id);

-- Also ensure delivery_receipts has picking_started_at column
ALTER TABLE delivery_receipts 
ADD COLUMN IF NOT EXISTS picking_started_at DATETIME DEFAULT NULL AFTER status;

-- Update existing records to have quantity_picked = 0 if NULL
UPDATE delivery_receipt_items SET quantity_picked = 0 WHERE quantity_picked IS NULL;

SELECT 'Picking columns added successfully' as status;
