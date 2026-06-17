-- =============================================================
-- V4.0.1 — Recipe catalog walk: fix Sugar per-batch amounts
-- =============================================================
-- Issue (2026-06-15, reported on Plain Yogurt):
--   Recipe `recipe_ingredients.quantity` values for Sugar on the sweetened
--   products were placeholder-low (0.1-0.8 kg per 100 L milk). The
--   ceiling-to-pack rounding then hid the scaling: for any normal
--   planned_quantity (10, 100, 1000 cups), the system always returned
--   "1 sack" because the recipe's per-batch need (e.g. 0.556 kg at
--   1000 cups) was a tiny fraction of one 25 kg sack. The user could
--   not see the scaling in the lock preview, and had to assume the
--   system was not scaling at all.
--
-- What this script changes:
--   The per-batch Sugar amount on each sweetened recipe. Values
--   chosen from typical commercial dairy ranges:
--     - Plain Yogurt: 5.0% sugar in milk (sweetened yogurt standard)
--     - Chocolate Milk: 7.0% sugar in milk (chocolate milk standard)
--     - Fresh Milk (vanilla-flavored): 2.5% sugar in milk (lightly
--       sweetened — lower than flavored-milk to keep "fresh" feel)
--   The 25 kg sack pack-size doesn't change, so the math at 100 L
--   milk is now: ceil(5.0/25) = 1 sack (was already 1), and the math
--   at 1000 cups = 555.6 L milk is: ceil(27.8/25) = 2 sacks (was 1).
--   Scaling is now visible.
--
-- What this script does NOT change:
--   - Sugar for test recipes (RCP-0019..RCP-0023) — left as-is
--   - Other ingredients (Cultures, Stabilizer, Vanilla Extract,
--     Chocolate Powder) — values are within typical ranges
--   - Schema columns or any cached aggregates (current_stock, etc.)
--   - enforce_whole_packs flags — pack rounding is intentional
--
-- Verification after running:
--   php tests/verify_recipe_scaling.php
--   and look for the per-recipe scaling table. Plain Yogurt Sugar
--   should now show:
--     planned=180   base=5.0000 kg   packs=1
--     planned=1000  base=27.7778 kg  packs=2
--     planned=5000  base=138.8889 kg packs=6
-- =============================================================

START TRANSACTION;

-- Plain Yogurt (id=15) — Sugar 0.100 -> 5.0 kg per 100 L (5% in milk)
UPDATE recipe_ingredients
   SET quantity = 5.000
 WHERE recipe_id = 15
   AND ingredient_name = 'Sugar'
   AND ABS(quantity - 0.100) < 0.001;

-- Chocolate Milk (id=14) — Sugar 0.8 -> 7.0 kg per 100 L (7% in milk)
UPDATE recipe_ingredients
   SET quantity = 7.000
 WHERE recipe_id = 14
   AND ingredient_name = 'Sugar'
   AND ABS(quantity - 0.800) < 0.001;

-- Fresh Milk 1L (id=12) — Sugar 0.5 -> 2.5 kg per 100 L (2.5% — lightly sweetened vanilla)
UPDATE recipe_ingredients
   SET quantity = 2.500
 WHERE recipe_id = 12
   AND ingredient_name = 'Sugar'
   AND ABS(quantity - 0.500) < 0.001;

-- Fresh Milk 500ml (id=13) — Sugar 0.25 -> 2.5 kg per 100 L (2.5% — same product line)
UPDATE recipe_ingredients
   SET quantity = 2.500
 WHERE recipe_id = 13
   AND ingredient_name = 'Sugar'
   AND ABS(quantity - 0.250) < 0.001;

COMMIT;

-- Verification SELECT — should show the new per-batch amounts
SELECT mr.recipe_code,
       mr.product_name,
       mr.variant,
       ri.ingredient_name,
       ri.quantity AS new_qty_per_batch,
       ri.unit,
       i.pack_size_value,
       i.pack_size_unit
  FROM master_recipes mr
  JOIN recipe_ingredients ri ON ri.recipe_id = mr.id
  LEFT JOIN ingredients i ON ri.ingredient_id = i.id
 WHERE mr.is_active = 1
   AND ri.ingredient_name = 'Sugar'
 ORDER BY mr.id;
