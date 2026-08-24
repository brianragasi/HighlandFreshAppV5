-- Keep legacy packaging quantities consistent with the catalog rule.
-- Cellophane Wrap is counted in rolls; it is not a liquid measured in liters.

ALTER TABLE ingredients
    ADD COLUMN IF NOT EXISTS physical_state VARCHAR(20) NULL AFTER unit_of_measure;

UPDATE ingredients
SET physical_state = CASE
    WHEN LOWER(TRIM(COALESCE(unit_of_measure, ''))) IN
        ('kg', 'kilogram', 'kilograms', 'g', 'gram', 'grams') THEN 'solid'
    WHEN LOWER(TRIM(COALESCE(unit_of_measure, ''))) IN
        ('l', 'lt', 'liter', 'liters', 'litre', 'litres', 'ml', 'milliliter', 'milliliters') THEN 'liquid'
    ELSE 'count'
END
WHERE physical_state IS NULL OR TRIM(physical_state) = '';

UPDATE ingredients
SET unit_of_measure = 'roll',
    pack_size_value = NULL,
    pack_size_unit = NULL,
    pack_label = NULL,
    enforce_whole_packs = 0
WHERE ingredient_code = 'ING-PACK-011'
  AND LOWER(COALESCE(unit_of_measure, '')) IN ('l', 'liter', 'liters', 'litre', 'litres');

UPDATE purchase_request_items pri
JOIN ingredients i ON i.id = pri.ingredient_id
SET pri.unit = 'roll'
WHERE i.ingredient_code = 'ING-PACK-011'
  AND LOWER(COALESCE(pri.unit, '')) IN ('l', 'liter', 'liters', 'litre', 'litres');

UPDATE purchase_order_items poi
JOIN ingredients i ON i.id = poi.ingredient_id
SET poi.unit = 'roll'
WHERE i.ingredient_code = 'ING-PACK-011'
  AND LOWER(COALESCE(poi.unit, '')) IN ('l', 'liter', 'liters', 'litre', 'litres');

UPDATE receiving_report_items rri
JOIN ingredients i ON i.id = rri.ingredient_id
SET rri.unit = 'roll'
WHERE i.ingredient_code = 'ING-PACK-011'
  AND LOWER(COALESCE(rri.unit, '')) IN ('l', 'liter', 'liters', 'litre', 'litres');
