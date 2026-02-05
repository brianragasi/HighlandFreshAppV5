-- Highland Fresh System - Sample Data Population
-- Run this script to populate sample data for ingredients, MRO items, and categories
-- Date: February 2026

-- =====================================================
-- INGREDIENT CATEGORIES
-- =====================================================
INSERT INTO `ingredient_categories` (`id`, `category_code`, `category_name`, `description`, `is_active`) VALUES
(1, 'CAT-DAIRY', 'Dairy Additives', 'Dairy-based ingredients and starters', 1),
(2, 'CAT-SWEET', 'Sweeteners', 'Sugar and other sweetening agents', 1),
(3, 'CAT-FLAV', 'Flavorings', 'Natural and artificial flavorings', 1),
(4, 'CAT-PROC', 'Processing Agents', 'Enzymes and processing aids', 1),
(5, 'CAT-PACK', 'Packaging Materials', 'Bottles, caps, and labels', 1)
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- =====================================================
-- INGREDIENTS
-- =====================================================
INSERT INTO `ingredients` (`id`, `ingredient_code`, `ingredient_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `current_stock`, `storage_location`, `storage_requirements`, `shelf_life_days`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ING-001', 'White Sugar', 2, 'kg', 50.00, 68.00, 'Dry Storage A1', 'Store in dry, cool place', 365, 1, NOW(), NOW()),
(2, 'ING-002', 'Milk Powder', 1, 'kg', 25.00, 85.00, 'Dry Storage A2', 'Store in dry, cool place. Keep sealed.', 180, 1, NOW(), NOW()),
(3, 'ING-003', 'Chocolate Flavoring', 3, 'L', 10.00, 50.00, 'Flavoring Shelf B1', 'Store at room temperature', 365, 1, NOW(), NOW()),
(4, 'ING-004', 'Melon Flavoring', 3, 'L', 10.00, 40.00, 'Flavoring Shelf B2', 'Store at room temperature', 365, 1, NOW(), NOW()),
(5, 'ING-005', 'Rennet (Liquid)', 4, 'L', 5.00, 20.00, 'Cold Storage C1', 'Refrigerate at 4°C', 90, 1, NOW(), NOW()),
(6, 'ING-006', 'Salt (Iodized)', 4, 'kg', 20.00, 90.00, 'Dry Storage A3', 'Store in dry place', 730, 1, NOW(), NOW()),
(7, 'ING-007', 'Yogurt Culture Starter', 1, 'g', 500.00, 800.00, 'Freezer D1', 'Keep frozen at -18°C', 365, 1, NOW(), NOW()),
(8, 'ING-008', '330ml Bottles (PET)', 5, 'pcs', 1000.00, 5000.00, 'Packaging Area P1', 'Store in clean, dry area', NULL, 1, NOW(), NOW()),
(9, 'ING-009', 'Bottle Caps (Blue)', 5, 'pcs', 1000.00, 5000.00, 'Packaging Area P2', 'Store in clean, dry area', NULL, 1, NOW(), NOW()),
(10, 'ING-010', 'Product Labels (Milk)', 5, 'pcs', 1000.00, 3000.00, 'Packaging Area P3', 'Store away from heat', NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    ingredient_name = VALUES(ingredient_name),
    current_stock = VALUES(current_stock),
    storage_location = VALUES(storage_location);

-- =====================================================
-- INGREDIENT BATCHES (Sample batches for existing ingredients)
-- =====================================================
INSERT INTO `ingredient_batches` (`batch_code`, `ingredient_id`, `quantity`, `remaining_quantity`, `unit_cost`, `received_date`, `expiry_date`, `qc_status`, `status`, `received_by`) 
SELECT 
    CONCAT('BATCH-', LPAD(i.id, 3, '0'), '-001'),
    i.id,
    i.current_stock,
    i.current_stock,
    CASE 
        WHEN i.category_id = 5 THEN 0.50
        WHEN i.category_id = 3 THEN 150.00
        ELSE 50.00
    END,
    DATE_SUB(CURDATE(), INTERVAL 7 DAY),
    CASE 
        WHEN i.shelf_life_days IS NOT NULL THEN DATE_ADD(CURDATE(), INTERVAL i.shelf_life_days DAY)
        ELSE NULL
    END,
    'approved',
    'available',
    (SELECT id FROM users WHERE role = 'warehouse_raw' LIMIT 1)
FROM ingredients i
WHERE NOT EXISTS (
    SELECT 1 FROM ingredient_batches ib WHERE ib.ingredient_id = i.id
);

-- =====================================================
-- MRO CATEGORIES
-- =====================================================
INSERT INTO `mro_categories` (`id`, `category_code`, `category_name`, `description`, `is_active`) VALUES
(1, 'MRO-SPARE', 'Spare Parts', 'Machine spare parts and components', 1),
(2, 'MRO-TOOL', 'Tools', 'Hand tools and equipment', 1),
(3, 'MRO-CLEAN', 'Cleaning Supplies', 'Sanitizers and cleaning agents', 1),
(4, 'MRO-SAFETY', 'Safety Equipment', 'PPE and safety gear', 1),
(5, 'MRO-LUBRIC', 'Lubricants', 'Food-grade oils and lubricants', 1)
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- =====================================================
-- MRO ITEMS
-- =====================================================
INSERT INTO `mro_items` (`id`, `item_code`, `item_name`, `category_id`, `unit_of_measure`, `minimum_stock`, `current_stock`, `storage_location`, `compatible_equipment`, `is_critical`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'MRO-001', 'Pasteurizer Gasket Set', 1, 'set', 5, 12, 'MRO Storage M1', 'Pasteurizer HTST-100', 1, 1, NOW(), NOW()),
(2, 'MRO-002', 'Homogenizer Valve', 1, 'pcs', 3, 8, 'MRO Storage M1', 'Homogenizer HG-200', 1, 1, NOW(), NOW()),
(3, 'MRO-003', 'Conveyor Belt 2m', 1, 'pcs', 2, 4, 'MRO Storage M2', 'Bottling Line BL-1', 0, 1, NOW(), NOW()),
(4, 'MRO-004', 'Wrench Set (Metric)', 2, 'set', 2, 5, 'Tool Cabinet T1', NULL, 0, 1, NOW(), NOW()),
(5, 'MRO-005', 'Screwdriver Set', 2, 'set', 2, 6, 'Tool Cabinet T1', NULL, 0, 1, NOW(), NOW()),
(6, 'MRO-006', 'CIP Cleaning Agent', 3, 'L', 50, 120, 'Chemical Storage C1', 'All dairy equipment', 1, 1, NOW(), NOW()),
(7, 'MRO-007', 'Sanitizer Concentrate', 3, 'L', 25, 60, 'Chemical Storage C1', 'Food contact surfaces', 1, 1, NOW(), NOW()),
(8, 'MRO-008', 'Safety Goggles', 4, 'pcs', 10, 25, 'Safety Cabinet S1', NULL, 0, 1, NOW(), NOW()),
(9, 'MRO-009', 'Nitrile Gloves (Box)', 4, 'box', 20, 50, 'Safety Cabinet S1', NULL, 0, 1, NOW(), NOW()),
(10, 'MRO-010', 'Food Grade Lubricant', 5, 'L', 5, 15, 'Chemical Storage C2', 'All machinery', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    item_name = VALUES(item_name),
    current_stock = VALUES(current_stock),
    storage_location = VALUES(storage_location);

-- =====================================================
-- MRO INVENTORY (Sample batches for existing MRO items)
-- =====================================================
INSERT INTO `mro_inventory` (`batch_code`, `mro_item_id`, `quantity`, `remaining_quantity`, `unit_cost`, `supplier_name`, `received_date`, `status`, `received_by`) 
SELECT 
    CONCAT('MRO-BATCH-', LPAD(m.id, 3, '0'), '-001'),
    m.id,
    m.current_stock,
    m.current_stock,
    CASE 
        WHEN m.category_id = 1 THEN 500.00
        WHEN m.category_id = 2 THEN 250.00
        WHEN m.category_id = 3 THEN 100.00
        ELSE 50.00
    END,
    'General MRO Supplier',
    DATE_SUB(CURDATE(), INTERVAL 14 DAY),
    'available',
    (SELECT id FROM users WHERE role = 'warehouse_raw' LIMIT 1)
FROM mro_items m
WHERE NOT EXISTS (
    SELECT 1 FROM mro_inventory mi WHERE mi.mro_item_id = m.id
);

-- =====================================================
-- MILK TYPES (If not already exists)
-- =====================================================
INSERT INTO `milk_types` (`id`, `type_code`, `type_name`, `description`, `is_active`) VALUES
(1, 'CARABAO', 'Carabao Milk', 'Fresh carabao milk from local farms', 1),
(2, 'COW', 'Cow Milk', 'Fresh cow milk', 1),
(3, 'GOAT', 'Goat Milk', 'Fresh goat milk', 1)
ON DUPLICATE KEY UPDATE type_name = VALUES(type_name);

-- =====================================================
-- Update ingredients current_stock to match batch totals
-- =====================================================
UPDATE ingredients i
SET current_stock = (
    SELECT COALESCE(SUM(remaining_quantity), 0)
    FROM ingredient_batches ib
    WHERE ib.ingredient_id = i.id
    AND ib.status IN ('available', 'partially_used')
)
WHERE EXISTS (SELECT 1 FROM ingredient_batches ib WHERE ib.ingredient_id = i.id);

-- =====================================================
-- Update MRO items current_stock to match inventory totals
-- =====================================================
UPDATE mro_items m
SET current_stock = (
    SELECT COALESCE(SUM(remaining_quantity), 0)
    FROM mro_inventory mi
    WHERE mi.mro_item_id = m.id
    AND mi.status IN ('available', 'partially_used')
)
WHERE EXISTS (SELECT 1 FROM mro_inventory mi WHERE mi.mro_item_id = m.id);

-- =====================================================
-- Success message
-- =====================================================
SELECT 'Sample data population completed successfully!' AS message;
SELECT COUNT(*) AS ingredient_count FROM ingredients WHERE is_active = 1;
SELECT COUNT(*) AS mro_item_count FROM mro_items WHERE is_active = 1;
SELECT COUNT(*) AS ingredient_batch_count FROM ingredient_batches;
SELECT COUNT(*) AS mro_inventory_count FROM mro_inventory;
