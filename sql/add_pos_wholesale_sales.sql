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
