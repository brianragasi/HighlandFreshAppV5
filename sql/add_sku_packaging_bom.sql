-- SKU packaging BOM for bulk-first production.
-- Formula ingredients remain in recipe_ingredients. Packaging materials are
-- calculated from the actual finished-SKU allocation at run completion.

CREATE TABLE IF NOT EXISTS sku_packaging_bom_items (
    id INT NOT NULL AUTO_INCREMENT,
    product_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity_per_unit DECIMAL(12,6) NOT NULL,
    waste_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sku_packaging_material (product_id, ingredient_id),
    KEY idx_sku_packaging_ingredient (ingredient_id),
    CONSTRAINT fk_sku_packaging_product
        FOREIGN KEY (product_id) REFERENCES products (id),
    CONSTRAINT fk_sku_packaging_ingredient
        FOREIGN KEY (ingredient_id) REFERENCES ingredients (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
