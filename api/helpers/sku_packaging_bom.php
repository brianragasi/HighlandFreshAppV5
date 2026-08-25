<?php
/**
 * SKU-specific packaging bills of materials.
 *
 * Bulk recipes describe the liquid/food formula. Bottles, caps, labels,
 * cellophane, and similar materials are calculated only after Production
 * allocates the actual yield to sellable SKUs.
 */

if (defined('SKU_PACKAGING_BOM_HELPERS_LOADED')) {
    return;
}
define('SKU_PACKAGING_BOM_HELPERS_LOADED', true);

class SkuPackagingStockException extends RuntimeException
{
    private $validationErrors;

    public function __construct(array $validationErrors)
    {
        parent::__construct('Packaging material stock is insufficient');
        $this->validationErrors = $validationErrors;
    }

    public function getValidationErrors()
    {
        return $this->validationErrors;
    }
}

function ensureSkuPackagingBomTable(PDO $db)
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    // Do not issue CREATE TABLE on every request. In MySQL, even
    // CREATE TABLE IF NOT EXISTS performs an implicit commit. Calling it
    // while a production run is being started can therefore commit the run
    // update behind the caller's back and make its later commit fail with
    // "There is no active transaction".
    try {
        $db->query('SELECT 1 FROM sku_packaging_bom_items LIMIT 0');
        $ensured = true;
        return;
    } catch (Throwable $e) {
        // The table is missing (or otherwise unavailable). It may be created
        // only before the caller opens its business transaction.
    }

    if ($db->inTransaction()) {
        throw new RuntimeException(
            'SKU packaging BOM storage must be initialized before starting a database transaction'
        );
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS sku_packaging_bom_items (
            id INT NOT NULL AUTO_INCREMENT,
            product_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            quantity_per_unit DECIMAL(12,6) NOT NULL,
            waste_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            unit VARCHAR(20) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sku_packaging_material (product_id, ingredient_id),
            KEY idx_sku_packaging_ingredient (ingredient_id),
            CONSTRAINT fk_sku_packaging_product FOREIGN KEY (product_id) REFERENCES products (id),
            CONSTRAINT fk_sku_packaging_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ensured = true;
}

function isPackagingIngredientCategory($categoryName, $categoryCode = '')
{
    $category = strtolower(trim((string) $categoryName . ' ' . (string) $categoryCode));
    return strpos($category, 'packag') !== false || strpos($category, 'container') !== false;
}

function isRollPackagingUnit($unit)
{
    return in_array(strtolower(trim((string) $unit)), ['roll', 'rolls'], true);
}

function isCountedPackagingUnit($unit)
{
    return in_array(strtolower(trim((string) $unit)), [
        'pc', 'pcs', 'piece', 'pieces', 'unit', 'units', 'set', 'sets'
    ], true);
}

/**
 * These components represent one physical part attached to one finished SKU.
 * Separate front/back labels should be separate inventory materials, each with
 * a quantity of one, rather than hiding several parts in one BOM number.
 */
function packagingMaterialMustBeOnePerFinishedUnit($name, $unit)
{
    if (!isCountedPackagingUnit($unit)) {
        return false;
    }
    return preg_match('/\b(bottle|container|jar|cup|cap|lid|closure|label)\b/i', (string) $name) === 1;
}

function assessSkuPackagingBomReadiness($baseUnit, array $items)
{
    if (empty($items)) {
        return ['ready' => false, 'missing' => ['packaging material']];
    }

    $base = strtolower(trim((string) $baseUnit));
    if (!in_array($base, ['bottle', 'bottles'], true)) {
        return ['ready' => true, 'missing' => []];
    }

    $names = strtolower(implode(' ', array_map(
        static fn($item) => (string) ($item['ingredient_name'] ?? ''),
        $items
    )));
    $required = [
        'bottle/container' => '/\b(bottle|container)\b/i',
        'cap/closure' => '/\b(cap|lid|closure)\b/i',
        'label' => '/\blabel\b/i',
    ];
    $missing = [];
    foreach ($required as $label => $pattern) {
        if (preg_match($pattern, $names) !== 1) {
            $missing[] = $label;
        }
    }
    return ['ready' => empty($missing), 'missing' => $missing];
}

function isPlainPackagingNumber($value)
{
    if (is_int($value) || is_float($value)) {
        return is_finite((float) $value);
    }
    return is_string($value)
        && preg_match('/^\d+(?:\.\d+)?$/D', trim($value)) === 1;
}

function roundPackagingRequirementForStock($quantity, $unit)
{
    $quantity = max(0.0, (float) $quantity);
    if (isRollPackagingUnit($unit)) {
        // Roll stock is recorded to hundredths. Always round usage upward so a
        // small real consumption can never disappear from the stock ledger.
        return ceil(($quantity - 1.0e-12) * 100) / 100;
    }
    return round($quantity, 3);
}

function getAvailablePackagingMaterials(PDO $db)
{
    $stmt = $db->query("
        SELECT i.id, i.ingredient_code, i.ingredient_name, i.unit_of_measure,
               i.current_stock, i.available_stock, i.is_active,
               ic.category_name, ic.category_code
        FROM ingredients i
        JOIN ingredient_categories ic ON ic.id = i.category_id
        WHERE COALESCE(i.is_active, 1) = 1
          AND COALESCE(ic.is_active, 1) = 1
          AND (LOWER(ic.category_name) LIKE '%packag%'
               OR LOWER(ic.category_code) LIKE '%pack%'
               OR LOWER(ic.category_name) LIKE '%container%')
        ORDER BY i.ingredient_name ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getSkuPackagingBom(PDO $db, $productId)
{
    ensureSkuPackagingBomTable($db);
    $stmt = $db->prepare("
        SELECT spbi.id, spbi.product_id, spbi.ingredient_id,
               spbi.quantity_per_unit, spbi.waste_percent, spbi.unit,
               i.ingredient_code, i.ingredient_name, i.current_stock,
               i.available_stock, ic.category_name
        FROM sku_packaging_bom_items spbi
        JOIN ingredients i ON i.id = spbi.ingredient_id
        LEFT JOIN ingredient_categories ic ON ic.id = i.category_id
        WHERE spbi.product_id = ? AND spbi.is_active = 1
          AND COALESCE(i.is_active, 1) = 1
          AND (LOWER(COALESCE(ic.category_name, '')) LIKE '%packag%'
               OR LOWER(COALESCE(ic.category_code, '')) LIKE '%pack%'
               OR LOWER(COALESCE(ic.category_name, '')) LIKE '%container%')
        ORDER BY i.ingredient_name ASC
    ");
    $stmt->execute([(int) $productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getSkuPackagingBomMap(PDO $db, array $productIds)
{
    ensureSkuPackagingBomTable($db);
    $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT spbi.product_id, spbi.ingredient_id, spbi.quantity_per_unit,
               spbi.waste_percent, spbi.unit, i.ingredient_code,
               i.ingredient_name, i.current_stock, i.available_stock
        FROM sku_packaging_bom_items spbi
        JOIN ingredients i ON i.id = spbi.ingredient_id
        LEFT JOIN ingredient_categories ic ON ic.id = i.category_id
        WHERE spbi.product_id IN ($placeholders)
          AND spbi.is_active = 1
          AND COALESCE(i.is_active, 1) = 1
          AND (LOWER(COALESCE(ic.category_name, '')) LIKE '%packag%'
               OR LOWER(COALESCE(ic.category_code, '')) LIKE '%pack%'
               OR LOWER(COALESCE(ic.category_name, '')) LIKE '%container%')
        ORDER BY i.ingredient_name ASC
    ");
    $stmt->execute($ids);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $productId = (int) $row['product_id'];
        if (!isset($map[$productId])) {
            $map[$productId] = [];
        }
        $map[$productId][] = $row;
    }
    return $map;
}

function normalizeSkuPackagingBomInput(PDO $db, array $items)
{
    $normalized = [];
    $errors = [];
    $seen = [];

    $lookup = $db->prepare("
        SELECT i.id, i.ingredient_name, i.unit_of_measure, i.is_active,
               ic.category_name, ic.category_code
        FROM ingredients i
        LEFT JOIN ingredient_categories ic ON ic.id = i.category_id
        WHERE i.id = ?
    ");

    foreach ($items as $idx => $item) {
        $ingredientId = (int) ($item['ingredient_id'] ?? 0);
        $quantityRaw = $item['quantity_per_unit'] ?? null;
        $coverageRaw = $item['units_per_stock_unit'] ?? null;
        $wasteRaw = $item['waste_percent'] ?? 0;

        if ($ingredientId <= 0) {
            $errors["items.$idx.ingredient_id"] = 'Select a packaging material';
            continue;
        }
        if (isset($seen[$ingredientId])) {
            $errors["items.$idx.ingredient_id"] = 'Each packaging material may appear only once';
            continue;
        }
        $seen[$ingredientId] = true;

        $lookup->execute([$ingredientId]);
        $ingredient = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$ingredient || (int) ($ingredient['is_active'] ?? 0) !== 1) {
            $errors["items.$idx.ingredient_id"] = 'Packaging material was not found or is inactive';
            continue;
        }
        if (!isPackagingIngredientCategory($ingredient['category_name'] ?? '', $ingredient['category_code'] ?? '')) {
            $errors["items.$idx.ingredient_id"] = 'Only ingredients in the Packaging Materials category are allowed';
            continue;
        }

        $isRoll = isRollPackagingUnit($ingredient['unit_of_measure'] ?? '');
        if ($isRoll) {
            if (!isPlainPackagingNumber($coverageRaw)) {
                $errors["items.$idx.units_per_stock_unit"] = 'Enter how many finished products one roll covers';
                continue;
            }
            $coverage = (float) $coverageRaw;
            if ($coverage < 1 || $coverage > 100 || abs($coverage - round($coverage)) > 0.000001) {
                $errors["items.$idx.units_per_stock_unit"] = 'Roll coverage must be a whole number from 1 to 100';
                continue;
            }
            $coverage = (int) round($coverage);
            $quantity = 1 / $coverage;
        } else {
            if (!isPlainPackagingNumber($quantityRaw)) {
                $errors["items.$idx.quantity_per_unit"] = 'Enter a normal decimal quantity without letters or scientific notation';
                continue;
            }
            $quantity = (float) $quantityRaw;
            if ($quantity < 0.000001 || $quantity > 1000) {
                $errors["items.$idx.quantity_per_unit"] = 'Quantity per finished unit must be between 0.000001 and 1,000';
                continue;
            }
            if (isCountedPackagingUnit($ingredient['unit_of_measure'] ?? '')
                && abs($quantity - round($quantity)) > 0.000001) {
                $errors["items.$idx.quantity_per_unit"] = 'Packaging counted by piece must use a whole number';
                continue;
            }
            if (packagingMaterialMustBeOnePerFinishedUnit(
                $ingredient['ingredient_name'] ?? '',
                $ingredient['unit_of_measure'] ?? ''
            ) && abs($quantity - 1.0) > 0.000001) {
                $errors["items.$idx.quantity_per_unit"] =
                    ($ingredient['ingredient_name'] ?? 'This packaging material') .
                    ' must be 1 per finished product. Use the waste allowance for extras.';
                continue;
            }
            $coverage = null;
        }

        if (!isPlainPackagingNumber($wasteRaw)) {
            $errors["items.$idx.waste_percent"] = 'Enter a normal waste percentage without letters or scientific notation';
            continue;
        }
        $waste = (float) $wasteRaw;
        if ($waste < 0 || $waste > 100) {
            $errors["items.$idx.waste_percent"] = 'Waste allowance must be between 0% and 100%';
            continue;
        }

        $normalized[] = [
            'ingredient_id' => $ingredientId,
            'ingredient_name' => $ingredient['ingredient_name'],
            'quantity_per_unit' => round($quantity, 6),
            'units_per_stock_unit' => $coverage,
            'waste_percent' => round($waste, 2),
            'unit' => $ingredient['unit_of_measure'] ?: 'piece',
        ];
    }

    return [$normalized, $errors];
}

function replaceSkuPackagingBom(PDO $db, $productId, array $items)
{
    ensureSkuPackagingBomTable($db);
    [$normalized, $errors] = normalizeSkuPackagingBomInput($db, $items);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors, 'items' => []];
    }

    if (!empty($normalized)) {
        $skuStmt = $db->prepare('SELECT base_unit FROM products WHERE id = ?');
        $skuStmt->execute([(int) $productId]);
        $baseUnit = $skuStmt->fetchColumn();
        if ($baseUnit === false) {
            return ['success' => false, 'errors' => ['product_id' => 'Packaging SKU was not found'], 'items' => []];
        }
        $readiness = assessSkuPackagingBomReadiness($baseUnit, $normalized);
        if (!$readiness['ready']) {
            return [
                'success' => false,
                'errors' => [
                    'items' => 'Complete this bottled SKU with: ' . implode(', ', $readiness['missing'])
                ],
                'items' => [],
            ];
        }
    }

    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }
    try {
        $db->prepare('DELETE FROM sku_packaging_bom_items WHERE product_id = ?')
            ->execute([(int) $productId]);
        if (!empty($normalized)) {
            $insert = $db->prepare("
                INSERT INTO sku_packaging_bom_items
                    (product_id, ingredient_id, quantity_per_unit, waste_percent, unit, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            foreach ($normalized as $item) {
                $insert->execute([
                    (int) $productId,
                    $item['ingredient_id'],
                    $item['quantity_per_unit'],
                    $item['waste_percent'],
                    $item['unit'],
                ]);
            }
        }
        if ($ownsTransaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return ['success' => true, 'errors' => [], 'items' => getSkuPackagingBom($db, $productId)];
}

function skuPackagingSizeToMl($size, $measure)
{
    $size = (float) $size;
    $measure = strtolower(trim((string) $measure));
    if (in_array($measure, ['l', 'liter', 'liters', 'litre', 'litres'], true)) {
        return round($size * 1000, 3);
    }
    if ($measure === 'kg') {
        return round($size * 1000, 3);
    }
    return round($size, 3);
}

/**
 * Validate the actual SKU allocation and calculate its packaging materials.
 * Product names and sizes are always replaced by product-master values.
 */
function calculateSkuPackagingRequirements(PDO $db, $recipeId, array $packagingItems)
{
    ensureSkuPackagingBomTable($db);
    $errors = [];
    $sanitized = [];
    $requirements = [];
    $seenProductIds = [];

    $recipeStmt = $db->prepare('SELECT base_product_id, product_id FROM master_recipes WHERE id = ?');
    $recipeStmt->execute([(int) $recipeId]);
    $recipe = $recipeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$recipe) {
        return ['success' => false, 'errors' => ['recipe_id' => 'Recipe was not found'], 'items' => [], 'requirements' => []];
    }

    $baseProductId = !empty($recipe['base_product_id']) ? (int) $recipe['base_product_id'] : null;
    $legacyProductId = !empty($recipe['product_id']) ? (int) $recipe['product_id'] : null;
    $productStmt = $db->prepare("
        SELECT id, base_product_id, product_code, product_name, variant,
               unit_size, unit_measure, base_unit, is_active
        FROM products WHERE id = ?
    ");

    foreach ($packagingItems as $idx => $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        if ($productId <= 0) {
            $errors["packaging_items.$idx.product_id"] = 'Choose a configured packaging SKU';
            continue;
        }
        if ($quantity <= 0) {
            $errors["packaging_items.$idx.quantity"] = 'Quantity must be greater than 0';
            continue;
        }
        if (isset($seenProductIds[$productId])) {
            $errors["packaging_items.$idx.product_id"] = 'The same packaging SKU was entered more than once. Keep one line and enter its total quantity.';
            continue;
        }
        $seenProductIds[$productId] = true;

        $productStmt->execute([$productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        $belongsToRecipe = $product && (
            ($baseProductId && (int) ($product['base_product_id'] ?? 0) === $baseProductId)
            || (!$baseProductId && $legacyProductId && (int) $product['id'] === $legacyProductId)
        );
        if (!$product || !$belongsToRecipe || (int) ($product['is_active'] ?? 0) !== 1) {
            $errors["packaging_items.$idx.product_id"] = 'SKU is inactive or does not belong to this bulk product';
            continue;
        }

        $bom = getSkuPackagingBom($db, $productId);
        if (empty($bom)) {
            $label = $product['product_code'] ?: $product['product_name'];
            $errors["packaging_items.$idx.packaging_bom"] = "$label has no packaging BOM. Configure it in Admin → Products.";
            continue;
        }
        $readiness = assessSkuPackagingBomReadiness($product['base_unit'] ?? '', $bom);
        if (!$readiness['ready']) {
            $label = $product['product_code'] ?: $product['product_name'];
            $errors["packaging_items.$idx.packaging_bom"] =
                "$label packaging profile is incomplete. Add: " . implode(', ', $readiness['missing']) . '.';
            continue;
        }

        $sizeMl = skuPackagingSizeToMl($product['unit_size'], $product['unit_measure']);
        $sanitized[] = [
            'product_id' => (int) $product['id'],
            'product_code' => $product['product_code'],
            'product_name' => $product['product_name'],
            'product_variant' => $product['variant'],
            'size_ml' => $sizeMl,
            'unit_measure' => 'ml',
            'quantity' => $quantity,
        ];

        foreach ($bom as $component) {
            $ingredientId = (int) $component['ingredient_id'];
            $required = $quantity * (float) $component['quantity_per_unit']
                * (1 + ((float) $component['waste_percent'] / 100));
            if (!isset($requirements[$ingredientId])) {
                $requirements[$ingredientId] = [
                    'ingredient_id' => $ingredientId,
                    'ingredient_code' => $component['ingredient_code'],
                    'ingredient_name' => $component['ingredient_name'],
                    'unit' => $component['unit'],
                    'quantity_required' => 0.0,
                ];
            }
            $requirements[$ingredientId]['quantity_required'] += $required;
        }
    }

    foreach ($requirements as &$requirement) {
        $requirement['quantity_required'] = roundPackagingRequirementForStock(
            $requirement['quantity_required'],
            $requirement['unit'] ?? ''
        );
    }
    unset($requirement);

    return [
        'success' => empty($errors),
        'errors' => $errors,
        'items' => $sanitized,
        'requirements' => array_values($requirements),
    ];
}

function validateSkuPackagingPlanVolume(array $plannedSkuItems, $availableVolumeMl)
{
    $availableVolumeMl = max(0.0, (float) $availableVolumeMl);
    $plannedVolumeMl = 0.0;
    foreach ($plannedSkuItems as $item) {
        $plannedVolumeMl += max(0.0, (float) ($item['size_ml'] ?? 0))
            * max(0, (int) ($item['quantity'] ?? 0));
    }
    return [
        'valid' => $availableVolumeMl <= 0 || $plannedVolumeMl <= $availableVolumeMl + 0.01,
        'planned_volume_ml' => $plannedVolumeMl,
        'available_volume_ml' => $availableVolumeMl,
    ];
}

/** Consume calculated packaging materials inside the caller's transaction. */
function consumeSkuPackagingRequirements(PDO $db, $runId, $batchCode, array $requirements, $performedBy)
{
    if (empty($requirements)) {
        return [];
    }

    $locked = [];
    $errors = [];
    $stockStmt = $db->prepare("
        SELECT i.id, i.ingredient_name, i.unit_of_measure, i.current_stock,
               i.available_stock,
               i.unit_cost, i.storage_location
        FROM ingredients i WHERE i.id = ? FOR UPDATE
    ");
    foreach ($requirements as $idx => $requirement) {
        $ingredientId = (int) $requirement['ingredient_id'];
        $required = round((float) $requirement['quantity_required'], 3);
        $stockStmt->execute([$ingredientId]);
        $ingredient = $stockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ingredient) {
            $errors["packaging_materials.$idx"] = 'A packaging material no longer exists';
            continue;
        }
        $available = isset($ingredient['available_stock'])
            ? (float) $ingredient['available_stock']
            : (float) $ingredient['current_stock'];
        if ($required > $available + 0.0001) {
            $errors["packaging_materials.$idx"] = sprintf(
                '%s needs %.3f %s but only %.3f is in stock',
                $ingredient['ingredient_name'],
                $required,
                $ingredient['unit_of_measure'],
                $available
            );
        }
        $locked[] = ['master' => $ingredient, 'required' => $required];
    }
    if (!empty($errors)) {
        throw new SkuPackagingStockException($errors);
    }

    $update = $db->prepare('UPDATE ingredients SET current_stock = current_stock - ? WHERE id = ?');
    $consume = $db->prepare("
        INSERT INTO ingredient_consumption
            (run_id, ingredient_id, ingredient_name, quantity_used, unit, batch_code, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $transaction = $db->prepare("
        INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, quantity,
             unit_of_measure, quantity_before, quantity_after, reference_type,
             reference_id, from_location, to_location, unit_cost, total_cost,
             performed_by, reason)
        VALUES (?, 'production_issue', 'packaging', ?, ?, ?, ?, ?,
                'production_run', ?, ?, 'Production', ?, ?, ?, ?)
    ");

    $consumed = [];
    foreach ($locked as $entry) {
        $ingredient = $entry['master'];
        $required = $entry['required'];
        $before = (float) $ingredient['current_stock'];
        $after = round($before - $required, 3);
        $unitCost = (float) ($ingredient['unit_cost'] ?? 0);

        $update->execute([$required, (int) $ingredient['id']]);
        $consume->execute([
            (int) $runId,
            (int) $ingredient['id'],
            $ingredient['ingredient_name'],
            $required,
            $ingredient['unit_of_measure'],
            $batchCode,
            'Auto-calculated from the actual SKU packaging allocation.',
        ]);
        $transaction->execute([
            'PKG-' . (int) $runId . '-' . (int) $ingredient['id'],
            (int) $ingredient['id'],
            $required,
            $ingredient['unit_of_measure'],
            $before,
            $after,
            (int) $runId,
            $ingredient['storage_location'] ?: 'Raw Materials Warehouse',
            $unitCost,
            round($required * $unitCost, 2),
            (int) $performedBy,
            'Packaging BOM consumption for completed batch ' . $batchCode,
        ]);
        $consumed[] = [
            'ingredient_id' => (int) $ingredient['id'],
            'ingredient_name' => $ingredient['ingredient_name'],
            'quantity_used' => $required,
            'unit' => $ingredient['unit_of_measure'],
            'quantity_before' => $before,
            'quantity_after' => $after,
        ];
    }

    return $consumed;
}
