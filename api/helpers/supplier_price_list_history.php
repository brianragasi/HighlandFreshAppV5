<?php

function ensureSupplierPriceListHistory(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS supplier_price_list_history (
            id INT NOT NULL AUTO_INCREMENT,
            supplier_id INT NOT NULL,
            item_type ENUM('ingredient','mro') NOT NULL,
            item_id INT NOT NULL,
            old_price DECIMAL(12,6) NOT NULL,
            new_price DECIMAL(12,6) NOT NULL,
            price_basis VARCHAR(40) NOT NULL,
            reason VARCHAR(255) NOT NULL,
            updated_by INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_supplier_price_list_supplier (supplier_id, created_at),
            KEY idx_supplier_price_list_item (item_type, item_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}
