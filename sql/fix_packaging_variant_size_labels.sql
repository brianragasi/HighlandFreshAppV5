-- Repair packaging snapshots produced by the former integer formatter.
-- The old code formatted a whole number with zero decimal places and then
-- trimmed all trailing zeroes, turning 250ml into 25ml and 500ml into 5ml.
-- This predicate targets only that exact transformation.

START TRANSACTION;

UPDATE packaging_run_items
SET product_variant = CONCAT(
    CAST(CAST(ROUND(size_ml) AS UNSIGNED) AS CHAR),
    COALESCE(NULLIF(TRIM(unit_measure), ''), 'ml')
)
WHERE size_ml > 0
  AND size_ml = ROUND(size_ml)
  AND MOD(ROUND(size_ml), 10) = 0
  AND LOWER(TRIM(product_variant)) = LOWER(CONCAT(
      TRIM(TRAILING '0' FROM CAST(CAST(ROUND(size_ml) AS UNSIGNED) AS CHAR)),
      COALESCE(NULLIF(TRIM(unit_measure), ''), 'ml')
  ));

COMMIT;
