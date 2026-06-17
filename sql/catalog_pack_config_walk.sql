-- V4.0 — Catalog pack-config walk.
-- Sets pack_size_value, pack_size_unit, pack_label, and enforce_whole_packs=1
-- for every ingredient that physically ships from the supplier in a sealed
-- pack that cannot be partially issued without inventory loss. Leaves
-- ingredients alone when they ship in bulk (e.g., raw milk from a tank).
--
-- IMPORTANT: these pack sizes are my best guesses for a typical Highland
-- Fresh supplier. The warehouse_raw user should review and adjust to
-- match the actual physical packaging received. The values are
-- intentionally easy to update later via a simple UPDATE.

-- Sugar: 25 kg sealed sack
UPDATE ingredients
SET pack_size_value = 25, pack_size_unit = 'kg', pack_label = '25 kg sack',
    enforce_whole_packs = 1
WHERE id = 9;

-- Vanilla Extract: 1 L sealed bottle
UPDATE ingredients
SET pack_size_value = 1, pack_size_unit = 'liter', pack_label = '1 L bottle',
    enforce_whole_packs = 1
WHERE id = 10;

-- Chocolate Powder X: 1 kg sealed bag (already configured, no-op idempotent)
UPDATE ingredients
SET pack_size_value = 1, pack_size_unit = 'kg', pack_label = '1 kg bag',
    enforce_whole_packs = 1
WHERE id = 11;

-- Stabilizer: 0.5 kg sealed packet (food-grade, small)
UPDATE ingredients
SET pack_size_value = 0.5, pack_size_unit = 'kg', pack_label = '500 g packet',
    enforce_whole_packs = 1
WHERE id = 12;

-- Cultures (Yogurt): 1 sealed foil packet
UPDATE ingredients
SET pack_size_value = 1, pack_size_unit = 'packet', pack_label = '1 foil packet',
    enforce_whole_packs = 1
WHERE id = 13;

-- Salt: 1 kg sealed bag
UPDATE ingredients
SET pack_size_value = 1, pack_size_unit = 'kg', pack_label = '1 kg bag',
    enforce_whole_packs = 1
WHERE id = 14;

-- Rennet: 1 L sealed bottle (small volumes, expensive)
UPDATE ingredients
SET pack_size_value = 1, pack_size_unit = 'liter', pack_label = '1 L bottle',
    enforce_whole_packs = 1
WHERE id = 15;

-- Food Coloring: 0.5 L sealed bottle
UPDATE ingredients
SET pack_size_value = 0.5, pack_size_unit = 'liter', pack_label = '500 mL bottle',
    enforce_whole_packs = 1
WHERE id = 16;

-- Cellophane Wrap: unit is "L" which is unusual for a wrap (should be
-- meters or rolls). Leaving as bulk — no pack config until someone
-- confirms the actual physical packaging.
-- (No UPDATE for id=17)
