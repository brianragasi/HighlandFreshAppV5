-- Keep finished-product master data compatible with the seven-day QC window.
-- Existing manufactured batch expiry dates are intentionally not extended.

START TRANSACTION;

UPDATE base_products
SET default_shelf_life_days = 8
WHERE default_shelf_life_days BETWEEN 1 AND 7;

UPDATE products
SET shelf_life_days = 8
WHERE shelf_life_days BETWEEN 1 AND 7;

UPDATE master_recipes
SET shelf_life_days = 8
WHERE shelf_life_days BETWEEN 1 AND 7;

COMMIT;
