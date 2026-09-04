-- Explicitly identify how each packaging material is used by a SKU BOM.
-- The CASE expression only migrates legacy rows; new records are classified
-- through Admin -> Ingredients and validated by the API.

ALTER TABLE ingredients
    ADD COLUMN IF NOT EXISTS packaging_role VARCHAR(30) NULL AFTER physical_state,
    ADD COLUMN IF NOT EXISTS packaging_capacity_value DECIMAL(12,3) NULL AFTER packaging_role,
    ADD COLUMN IF NOT EXISTS packaging_capacity_unit VARCHAR(20) NULL AFTER packaging_capacity_value;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS primary_container_id INT NULL AFTER unit_measure,
    ADD INDEX IF NOT EXISTS idx_products_primary_container (primary_container_id);

UPDATE ingredients i
JOIN ingredient_categories c ON c.id = i.category_id
SET i.packaging_role = CASE
    WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
         REGEXP 'label|sticker' THEN 'label'
    WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
         REGEXP 'cap|lid|closure|stopper|seal' THEN 'closure'
    WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
         REGEXP 'bottle|container|jar|cup|tub|pouch|carton' THEN 'container'
    WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
         REGEXP 'wrap|film|cellophane|box|case|crate|tray|bundle' THEN 'secondary'
    ELSE 'other'
END
WHERE (LOWER(COALESCE(c.category_name, '')) LIKE '%packag%'
       OR LOWER(COALESCE(c.category_code, '')) LIKE '%pack%'
       OR LOWER(COALESCE(c.category_name, '')) LIKE '%container%')
  AND (i.packaging_role IS NULL
       OR i.packaging_role NOT IN ('container', 'closure', 'label', 'secondary', 'other'));
