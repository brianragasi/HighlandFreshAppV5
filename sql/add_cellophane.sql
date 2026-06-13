-- ============================================
-- Highland Fresh - Add Cellophane (Non-Perishable)
-- Safe to run multiple times.
-- ============================================

-- Ensure Packaging Materials category exists
INSERT INTO ingredient_categories (category_code, category_name, description, is_active)
SELECT 'CAT-PACK', 'Packaging Materials', 'Bottles, caps, and labels', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM ingredient_categories
    WHERE category_code = 'CAT-PACK'
       OR category_name = 'Packaging Materials'
);

SET @pack_cat_id := (
    SELECT id
    FROM ingredient_categories
    WHERE category_code = 'CAT-PACK'
       OR category_name = 'Packaging Materials'
    LIMIT 1
);

INSERT INTO ingredients (
    ingredient_code,
    ingredient_name,
    category_id,
    unit_of_measure,
    minimum_stock,
    current_stock,
    storage_location,
    storage_requirements,
    shelf_life_days,
    is_active,
    created_at,
    updated_at
)
SELECT
    'ING-PACK-011',
    'Cellophane Wrap',
    @pack_cat_id,
    'roll',
    10,
    0,
    'Packaging Area P4',
    'Store in clean, dry area',
    NULL,
    1,
    NOW(),
    NOW()
WHERE @pack_cat_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM ingredients
      WHERE ingredient_code = 'ING-PACK-011'
         OR ingredient_name LIKE 'Cellophane%'
  );

-- Force non-perishable if column exists
SET @has_perishable := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ingredients'
      AND COLUMN_NAME = 'is_perishable'
);
SET @sql := IF(
    @has_perishable > 0,
    "UPDATE ingredients SET is_perishable = 0 WHERE ingredient_code = 'ING-PACK-011' OR ingredient_name LIKE 'Cellophane%';",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set lead time for packaging if column exists
SET @has_lead := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ingredients'
      AND COLUMN_NAME = 'lead_time_days'
);
SET @sql := IF(
    @has_lead > 0,
    "UPDATE ingredients SET lead_time_days = 5 WHERE ingredient_code = 'ING-PACK-011' OR ingredient_name LIKE 'Cellophane%';",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set a default par level (max stock) if column exists
SET @has_max := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ingredients'
      AND COLUMN_NAME = 'maximum_stock'
);
SET @sql := IF(
    @has_max > 0,
    "UPDATE ingredients SET maximum_stock = CASE WHEN maximum_stock IS NULL OR maximum_stock <= 0 THEN 20 ELSE maximum_stock END WHERE ingredient_code = 'ING-PACK-011' OR ingredient_name LIKE 'Cellophane%';",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Set a default unit cost if column exists
SET @has_cost := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ingredients'
      AND COLUMN_NAME = 'unit_cost'
);
SET @sql := IF(
    @has_cost > 0,
    "UPDATE ingredients SET unit_cost = CASE WHEN unit_cost IS NULL OR unit_cost = 0 THEN 12.50 ELSE unit_cost END WHERE ingredient_code = 'ING-PACK-011' OR ingredient_name LIKE 'Cellophane%';",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
