<?php

require_once dirname(__DIR__) . '/api/helpers/sellable_expiry_policy.php';

function shelfLifeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

shelfLifeAssert(HF_NEAR_EXPIRY_DAYS === 7, 'the QC handling window must remain seven days');
shelfLifeAssert(HF_MIN_FINISHED_PRODUCT_SHELF_LIFE_DAYS === 8, 'finished products must start with at least eight days');
shelfLifeAssert(hfFinishedProductShelfLifeError(6) !== null, 'six days must be rejected');
shelfLifeAssert(hfFinishedProductShelfLifeError(7) !== null, 'seven days must be rejected');
shelfLifeAssert(hfFinishedProductShelfLifeError(8) === null, 'eight days must be accepted');
shelfLifeAssert(hfFinishedProductShelfLifeError('8') === null, 'an integer form value must be accepted');
shelfLifeAssert(hfFinishedProductShelfLifeError('8.5') !== null, 'fractional shelf life must be rejected');
shelfLifeAssert(hfResolveFinishedProductShelfLifeDays(null, 8, 7) === 8, 'current base-product shelf life must override an older recipe copy');
shelfLifeAssert(hfResolveFinishedProductShelfLifeDays(30, 8, 7) === 30, 'a directly linked SKU shelf life must have first priority');
shelfLifeAssert(hfResolveFinishedProductShelfLifeDays(null, null, 14) === 14, 'legacy unlinked recipes must retain their shelf-life fallback');

$productApi = file_get_contents(dirname(__DIR__) . '/api/admin/products.php');
$productPage = file_get_contents(dirname(__DIR__) . '/html/admin/products.html');
$recipeApi = file_get_contents(dirname(__DIR__) . '/api/production/recipes.php');
$adminRecipeApi = file_get_contents(dirname(__DIR__) . '/api/admin/recipes.php');
$productionApi = file_get_contents(dirname(__DIR__) . '/api/production/runs.php');
$migration = file_get_contents(dirname(__DIR__) . '/sql/enforce_minimum_finished_product_shelf_life.sql');

shelfLifeAssert(
    str_contains($productApi, "'shelf_life_days' => ['Shelf life', HF_MIN_FINISHED_PRODUCT_SHELF_LIFE_DAYS, 3650]")
        && str_contains($productApi, "'default_shelf_life_days' => ['Shelf life', HF_MIN_FINISHED_PRODUCT_SHELF_LIFE_DAYS, 3650]")
        && str_contains($productApi, 'function updateBaseProduct')
        && substr_count($productApi, 'validateProductNumericPayload($data);') >= 3,
    'product create, SKU update, and base-product update must enforce the backend minimum'
);
shelfLifeAssert(
    str_contains($productPage, 'id="base_shelf_life_days"')
        && str_contains($productPage, 'value="8" min="8"')
        && str_contains($productPage, 'shelfLife < 8'),
    'Product Setup must reject six or seven days before making API calls'
);
shelfLifeAssert(
    str_contains($recipeApi, 'hfFinishedProductShelfLifeError($shelfLifeDays)'),
    'the legacy recipe write endpoint must enforce the same rule'
);
shelfLifeAssert(
    str_contains($productionApi, 'hfResolveFinishedProductShelfLifeDays(')
        && str_contains($productionApi, 'hfFinishedProductShelfLifeError($expiryDays)'),
    'production completion must use the current product shelf life and reject immediately near-expiry output'
);
shelfLifeAssert(
    str_contains($adminRecipeApi, 'shelf_life_days, is_active, created_by')
        && str_contains($adminRecipeApi, "(int) \$master['shelf_life_days']"),
    'new and updated recipes must keep their compatibility shelf-life copy aligned with the product master'
);
shelfLifeAssert(
    str_contains($migration, 'UPDATE base_products')
        && str_contains($migration, 'UPDATE products')
        && str_contains($migration, 'UPDATE master_recipes')
        && !str_contains($migration, 'UPDATE finished_goods_inventory'),
    'the migration must repair future shelf-life configuration without extending existing batches'
);

echo "Finished-product shelf-life validation checks passed.\n";
