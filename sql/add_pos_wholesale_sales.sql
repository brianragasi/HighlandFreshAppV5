ALTER TABLE products
    ADD COLUMN IF NOT EXISTS wholesale_box_price DECIMAL(12,2) NULL AFTER selling_price;

ALTER TABLE sales_transactions
    ADD COLUMN IF NOT EXISTS sale_mode ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER transaction_type;

ALTER TABLE sales_transaction_items
    ADD COLUMN IF NOT EXISTS sale_unit ENUM('piece','box') NOT NULL DEFAULT 'piece' AFTER quantity,
    ADD COLUMN IF NOT EXISTS sale_quantity INT NOT NULL DEFAULT 0 AFTER sale_unit,
    ADD COLUMN IF NOT EXISTS pieces_per_box_snapshot INT NOT NULL DEFAULT 1 AFTER sale_quantity;

UPDATE sales_transaction_items
SET sale_quantity = quantity
WHERE sale_quantity = 0;

CREATE TABLE IF NOT EXISTS pos_sold_box_labels (
    label_code VARCHAR(80) NOT NULL,
    batch_id INT NOT NULL,
    product_id INT NOT NULL,
    inventory_id INT NOT NULL,
    transaction_id INT NOT NULL,
    sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (label_code),
    KEY idx_pos_box_label_transaction (transaction_id),
    KEY idx_pos_box_label_batch_product (batch_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_opened_box_labels (
    label_code VARCHAR(80) NOT NULL,
    batch_id INT NOT NULL,
    product_id INT NOT NULL,
    inventory_id INT NOT NULL,
    opened_by INT NOT NULL,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (label_code),
    KEY idx_pos_opened_box_inventory (inventory_id),
    KEY idx_pos_opened_box_batch_product (batch_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
