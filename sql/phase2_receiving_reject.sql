-- Phase 2: PO Receiving with Accept/Reject per item
-- Adds reject tracking to purchase_order_items and a receiving log table

-- Add reject columns to purchase_order_items
ALTER TABLE purchase_order_items
    ADD COLUMN quantity_rejected DECIMAL(12,2) DEFAULT 0.00 AFTER quantity_received,
    ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER quantity_rejected;

-- Create receiving log table for full audit trail
CREATE TABLE IF NOT EXISTS po_receiving_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    po_item_id INT NOT NULL,
    quantity_accepted DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity_rejected DECIMAL(12,2) NOT NULL DEFAULT 0,
    rejection_reason VARCHAR(255) NULL,
    rejection_category ENUM('spoiled','defective','wrong_item','short_delivery','expired','other') NULL,
    received_by INT NOT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (po_item_id) REFERENCES purchase_order_items(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_po_id (po_id),
    INDEX idx_received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 1;
