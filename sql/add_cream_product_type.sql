-- Add cream and flavored_milk to master_recipes product_type enum
-- This allows production staff to create recipes for cream products

ALTER TABLE master_recipes 
MODIFY COLUMN product_type ENUM('bottled_milk', 'cheese', 'butter', 'yogurt', 'milk_bar', 'cream', 'flavored_milk') NOT NULL;

-- Insert a recipe for Fresh Cream 1L
-- Cream is a byproduct from milk separation (centrifugation)
-- You need about 10L of raw milk to get ~1L of cream (10% fat content separation)
INSERT INTO master_recipes (
    recipe_code, product_id, product_name, product_type, variant, milk_type_id,
    description, base_milk_liters, expected_yield, yield_unit, shelf_life_days,
    pasteurization_temp, pasteurization_time_mins, cooling_temp,
    special_instructions, is_active, created_by
) VALUES (
    'RCP-CRM-001', 
    8,  -- Fresh Cream 1L product id
    'Fresh Cream 1L', 
    'cream', 
    NULL,
    1,  -- Cow milk
    'Fresh cream separated from whole milk through centrifugation. Pasteurized for safety.',
    10.00,  -- 10L of milk yields approximately 1L of cream
    1,  -- 1 unit of 1L cream
    'units',
    5,  -- 5 days shelf life for fresh cream
    72.00,  -- HTST pasteurization temp for cream
    15,
    4.00,
    'Separate cream at 40-50°C for best results. Adjust separator for 35-40% fat content. Cool immediately after pasteurization.',
    1,
    1  -- System/admin user
);

-- Also add flavored milk recipe for completeness if any flavored milk products exist
