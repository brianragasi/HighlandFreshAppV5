-- =============================================================================
-- Highland Fresh — Product pack UOM normalization
-- =============================================================================
-- Master source of truth for multi-unit display & conversion:
--   products.base_unit      = bottle / cup / piece / …
--   products.box_unit       = box / crate / case / tray  (pack_name)
--   products.pieces_per_box = base units per pack       (units_per_pack)
--
-- Optional semantic aliases (pack_name, units_per_pack) mirror the above so
-- new code can use clearer names without breaking existing queries.
-- =============================================================================

-- 1) Ensure canonical columns exist (no-op if already present)
-- Run each statement separately if your MySQL rejects IF NOT EXISTS.

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS base_unit VARCHAR(20) NULL DEFAULT 'piece'
  COMMENT 'Sellable base unit label (bottle, cup, piece, …)';

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS box_unit VARCHAR(20) NULL DEFAULT 'box'
  COMMENT 'Pack container label (box, crate, case, tray)';

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS pieces_per_box INT NULL DEFAULT 1
  COMMENT 'How many base units fit in one pack (units_per_pack)';

-- 2) Semantic aliases (preferred names for new code)
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS pack_name VARCHAR(40) NULL
  COMMENT 'Alias of box_unit — pack display name (Box, Crate, Case)';

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS units_per_pack INT NULL
  COMMENT 'Alias of pieces_per_box — base units per pack';

-- 3) Backfill aliases from canonical columns
UPDATE products
SET
  pack_name = COALESCE(NULLIF(TRIM(pack_name), ''), NULLIF(TRIM(box_unit), ''), 'box'),
  units_per_pack = COALESCE(NULLIF(units_per_pack, 0), NULLIF(pieces_per_box, 0), 1),
  box_unit = COALESCE(NULLIF(TRIM(box_unit), ''), NULLIF(TRIM(pack_name), ''), 'box'),
  pieces_per_box = COALESCE(NULLIF(pieces_per_box, 0), NULLIF(units_per_pack, 0), 1),
  base_unit = COALESCE(NULLIF(TRIM(base_unit), ''), 'piece')
WHERE 1 = 1;

-- 4) Sanity: never leave 0 / NULL pack size
UPDATE products SET pieces_per_box = 1 WHERE pieces_per_box IS NULL OR pieces_per_box < 1;
UPDATE products SET units_per_pack = pieces_per_box WHERE units_per_pack IS NULL OR units_per_pack < 1;
UPDATE products SET pack_name = COALESCE(NULLIF(TRIM(box_unit), ''), 'box') WHERE pack_name IS NULL OR TRIM(pack_name) = '';
UPDATE products SET box_unit = pack_name WHERE box_unit IS NULL OR TRIM(box_unit) = '';

SELECT
  id,
  product_name,
  base_unit,
  box_unit AS pack_name_via_box_unit,
  pieces_per_box AS units_per_pack_via_ppb,
  pack_name,
  units_per_pack
FROM products
WHERE is_active = 1
ORDER BY product_name
LIMIT 30;
