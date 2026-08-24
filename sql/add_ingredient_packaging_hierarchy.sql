-- Separates material measurement, inner containers, outer purchase packages,
-- and supplier price basis. Safe to run more than once through the API's
-- runtime migration; this file documents the intended production schema.

ALTER TABLE ingredients
    ADD COLUMN IF NOT EXISTS purchase_format VARCHAR(20) NOT NULL DEFAULT 'direct_unit' AFTER physical_state,
    ADD COLUMN IF NOT EXISTS container_type VARCHAR(30) NULL AFTER purchase_format,
    ADD COLUMN IF NOT EXISTS container_size_value DECIMAL(12,3) NULL AFTER container_type,
    ADD COLUMN IF NOT EXISTS container_size_unit VARCHAR(20) NULL AFTER container_size_value,
    ADD COLUMN IF NOT EXISTS purchase_package_type VARCHAR(30) NULL AFTER container_size_unit,
    ADD COLUMN IF NOT EXISTS containers_per_purchase_package INT NULL AFTER purchase_package_type,
    ADD COLUMN IF NOT EXISTS purchase_price_basis VARCHAR(30) NOT NULL DEFAULT 'stock_unit' AFTER containers_per_purchase_package,
    ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(12,2) NULL AFTER purchase_price_basis;

-- Old records described one package level only. Preserve that as a generic
-- container when the old label was an outer-package word; do not invent a
-- bottle count that was never recorded.
UPDATE ingredients
SET container_type = CASE
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'sack' THEN 'sack'
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'bag' THEN 'bag'
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'bottle' THEN 'bottle'
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'sachet' THEN 'sachet'
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'packet' THEN 'packet'
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'roll' THEN 'roll'
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'drum' THEN 'drum'
        WHEN LOWER(COALESCE(pack_label, '')) REGEXP 'pail' THEN 'pail'
        WHEN pack_size_value IS NOT NULL THEN 'container'
        ELSE NULL
    END,
    container_size_value = COALESCE(container_size_value, pack_size_value),
    container_size_unit = COALESCE(container_size_unit, pack_size_unit, unit_of_measure),
    purchase_price_basis = COALESCE(NULLIF(purchase_price_basis, ''), 'stock_unit'),
    purchase_price = COALESCE(purchase_price, unit_cost)
WHERE pack_size_value IS NOT NULL
  AND (container_type IS NULL OR container_size_value IS NULL OR purchase_price IS NULL);

-- Repair an earlier transitional value if the runtime migration was opened
-- before this hierarchy was finalized.
UPDATE ingredients
SET container_type = 'container'
WHERE container_type IN ('box', 'case', 'crate');

-- A blank container must mean an intentional direct/bulk purchase, not a
-- forgotten field. Existing packaged rows are marked explicitly.
UPDATE ingredients
SET purchase_format = CASE
    WHEN container_type IS NULL THEN 'direct_unit'
    ELSE 'packaged'
END;
