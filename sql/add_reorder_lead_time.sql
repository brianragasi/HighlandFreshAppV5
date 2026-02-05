-- ============================================
-- Highland Fresh - Reorder Point Enhancement
-- Add Lead Time to Ingredients and MRO Items
-- ============================================

-- Add lead_time_days to ingredients table
ALTER TABLE `ingredients` 
ADD COLUMN `lead_time_days` INT(11) DEFAULT 7 COMMENT 'Supplier lead time in days' 
AFTER `minimum_stock`;

-- Add lead_time_days to mro_items table  
ALTER TABLE `mro_items`
ADD COLUMN `lead_time_days` INT(11) DEFAULT 7 COMMENT 'Supplier lead time in days'
AFTER `minimum_stock`;

-- Add reorder_point (explicit) to ingredients - calculated as minimum_stock * 1.5
ALTER TABLE `ingredients`
ADD COLUMN `reorder_point` DECIMAL(10,2) DEFAULT NULL COMMENT 'Alert threshold (if NULL, uses minimum_stock * 1.5)'
AFTER `lead_time_days`;

-- Add reorder_point to mro_items
ALTER TABLE `mro_items`
ADD COLUMN `reorder_point` DECIMAL(10,2) DEFAULT NULL COMMENT 'Alert threshold (if NULL, uses minimum_stock * 1.5)'
AFTER `lead_time_days`;

-- ============================================
-- Create view for Reorder Alerts
-- ============================================

DROP VIEW IF EXISTS `v_reorder_alerts`;

CREATE VIEW `v_reorder_alerts` AS
SELECT 
    'ingredient' AS item_type,
    i.id AS item_id,
    i.ingredient_code AS item_code,
    i.ingredient_name AS item_name,
    ic.category_name,
    i.unit_of_measure,
    i.current_stock,
    i.minimum_stock,
    COALESCE(i.reorder_point, i.minimum_stock * 1.5) AS reorder_point,
    i.lead_time_days,
    i.unit_cost,
    CASE 
        WHEN i.current_stock <= 0 THEN 'OUT_OF_STOCK'
        WHEN i.current_stock <= i.minimum_stock THEN 'CRITICAL'
        WHEN i.current_stock <= COALESCE(i.reorder_point, i.minimum_stock * 1.5) THEN 'LOW'
        ELSE 'OK'
    END AS stock_status,
    CASE 
        WHEN i.current_stock <= 0 THEN 0
        ELSE ROUND((i.current_stock / NULLIF(i.minimum_stock, 0)) * 100, 1)
    END AS stock_percentage,
    -- Estimated days until stockout based on average daily usage (placeholder - would need usage tracking)
    i.current_stock AS qty_available,
    (COALESCE(i.reorder_point, i.minimum_stock * 1.5) - i.current_stock) AS qty_to_order
FROM ingredients i
LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
WHERE i.is_active = 1
  AND i.current_stock <= COALESCE(i.reorder_point, i.minimum_stock * 1.5)

UNION ALL

SELECT 
    'mro' AS item_type,
    m.id AS item_id,
    m.item_code AS item_code,
    m.item_name AS item_name,
    mc.category_name,
    m.unit_of_measure,
    m.current_stock,
    m.minimum_stock,
    COALESCE(m.reorder_point, m.minimum_stock * 1.5) AS reorder_point,
    m.lead_time_days,
    m.unit_cost,
    CASE 
        WHEN m.current_stock <= 0 THEN 'OUT_OF_STOCK'
        WHEN m.current_stock <= m.minimum_stock THEN 'CRITICAL'
        WHEN m.current_stock <= COALESCE(m.reorder_point, m.minimum_stock * 1.5) THEN 'LOW'
        ELSE 'OK'
    END AS stock_status,
    CASE 
        WHEN m.current_stock <= 0 THEN 0
        ELSE ROUND((m.current_stock / NULLIF(m.minimum_stock, 0)) * 100, 1)
    END AS stock_percentage,
    m.current_stock AS qty_available,
    (COALESCE(m.reorder_point, m.minimum_stock * 1.5) - m.current_stock) AS qty_to_order
FROM mro_items m
LEFT JOIN mro_categories mc ON m.category_id = mc.id
WHERE m.is_active = 1
  AND m.current_stock <= COALESCE(m.reorder_point, m.minimum_stock * 1.5);

-- ============================================
-- Update existing ingredients with default lead times
-- ============================================

-- Packaging materials typically have shorter lead times (3-5 days)
UPDATE ingredients SET lead_time_days = 5 WHERE category_id = 5;

-- Flavorings and additives (1-2 weeks)
UPDATE ingredients SET lead_time_days = 10 WHERE category_id IN (3, 4);

-- Dairy cultures and specialized items (2-4 weeks)
UPDATE ingredients SET lead_time_days = 21 WHERE category_id = 1;

-- Sweeteners typically available locally (1 week)
UPDATE ingredients SET lead_time_days = 7 WHERE category_id = 2;

-- MRO items - critical spare parts need longer lead time
UPDATE mro_items SET lead_time_days = 14 WHERE is_critical = 1;
UPDATE mro_items SET lead_time_days = 7 WHERE is_critical = 0;
