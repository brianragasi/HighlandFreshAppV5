<?php

/**
 * Shared rules for deciding which recipe is safe to use for a new bulk batch.
 * Packaging SKUs are intentionally not part of this rule: bulk liquid may be
 * cooked before a bottle/pack size exists, but it cannot become finished goods.
 */

function isLiquidRecipeYieldUnit($unit) {
    return in_array(
        strtolower(trim((string) $unit)),
        ['liter', 'liters', 'litre', 'litres', 'l', 'lt'],
        true
    );
}

function getStrictRecipeBulkYieldLiters(array $recipe) {
    if (isset($recipe['bulk_yield_liters'])
        && $recipe['bulk_yield_liters'] !== null
        && (float) $recipe['bulk_yield_liters'] > 0) {
        return (float) $recipe['bulk_yield_liters'];
    }

    if (isLiquidRecipeYieldUnit($recipe['yield_unit'] ?? null)
        && (float) ($recipe['expected_yield'] ?? 0) > 0) {
        return (float) $recipe['expected_yield'];
    }

    return null;
}

function isBulkProductionRecipe(array $recipe) {
    return (int) ($recipe['base_product_id'] ?? 0) > 0
        && getStrictRecipeBulkYieldLiters($recipe) !== null;
}

/** Keep only the newest active, bulk-capable recipe for each base product. */
function filterCurrentBulkProductionRecipes(array $recipes) {
    $currentByBase = [];

    foreach ($recipes as $recipe) {
        if ((int) ($recipe['is_active'] ?? 0) !== 1 || !isBulkProductionRecipe($recipe)) {
            continue;
        }

        $baseId = (int) $recipe['base_product_id'];
        if (!isset($currentByBase[$baseId])
            || (int) ($recipe['id'] ?? 0) > (int) ($currentByBase[$baseId]['id'] ?? 0)) {
            $currentByBase[$baseId] = $recipe;
        }
    }

    return array_values($currentByBase);
}

function getCurrentActiveBulkRecipeForBase(PDO $db, $baseProductId) {
    $select = 'id, recipe_code, base_product_id, expected_yield, yield_unit, is_active';
    try {
        $db->query('SELECT bulk_yield_liters FROM master_recipes LIMIT 0');
        $select .= ', bulk_yield_liters';
    } catch (Throwable $e) {
        // Legacy schema: expected_yield + liquid yield_unit remains supported.
    }

    $stmt = $db->prepare("\n        SELECT {$select}\n        FROM master_recipes\n        WHERE base_product_id = ? AND is_active = 1\n        ORDER BY id DESC\n    ");
    $stmt->execute([(int) $baseProductId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $recipe) {
        if (isBulkProductionRecipe($recipe)) {
            return $recipe;
        }
    }

    return null;
}

/** Serialize recipe replacement for one base product. Call inside a transaction. */
function lockRecipeBaseProduct(PDO $db, $baseProductId) {
    $stmt = $db->prepare('SELECT id FROM base_products WHERE id = ? FOR UPDATE');
    $stmt->execute([(int) $baseProductId]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('Base product not found while locking recipe');
    }
}

function retireOtherActiveRecipes(PDO $db, $baseProductId, $keepRecipeId = null) {
    $sql = 'UPDATE master_recipes SET is_active = 0 WHERE base_product_id = ? AND is_active = 1';
    $params = [(int) $baseProductId];
    if ($keepRecipeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = (int) $keepRecipeId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Store the explicit bulk denominator when the upgraded column is available. */
function persistRecipeBulkYield(PDO $db, $recipeId, $yield, $yieldUnit) {
    try {
        $bulkYield = isLiquidRecipeYieldUnit($yieldUnit) && (float) $yield > 0
            ? (float) $yield
            : null;
        $stmt = $db->prepare('UPDATE master_recipes SET bulk_yield_liters = ? WHERE id = ?');
        $stmt->execute([$bulkYield, (int) $recipeId]);
    } catch (Throwable $e) {
        // Legacy schema without bulk_yield_liters.
    }
}
