<?php

declare(strict_types=1);

/**
 * Idempotent local/demo packaging catalog.
 *
 * Run explicitly:
 *   php scripts/seed_demo_packaging_catalog.php --confirm-demo-data
 *
 * Every created master record is prefixed with TST and labelled (Test). This
 * data is for workflow verification only and must not be presented as
 * client-supplied specifications.
 */

if (!in_array('--confirm-demo-data', $argv ?? [], true)) {
    fwrite(STDERR, "Refusing to seed without --confirm-demo-data.\n");
    exit(1);
}

define('HIGHLAND_FRESH', true);
require_once dirname(__DIR__) . '/api/config/config.php';
require_once dirname(__DIR__) . '/api/config/database.php';
require_once dirname(__DIR__) . '/api/helpers/ingredient_packaging_roles.php';
require_once dirname(__DIR__) . '/api/helpers/sku_packaging_bom.php';

$db = Database::getInstance()->getConnection();
ensureIngredientPackagingRoleSupport($db);
ensureSkuPackagingBomTable($db);
ensureProductPrimaryContainerSupport($db);

$categoryStmt = $db->query("
    SELECT id
    FROM ingredient_categories
    WHERE LOWER(category_name) LIKE '%packag%'
       OR LOWER(category_code) LIKE '%pack%'
    ORDER BY id
    LIMIT 1
");
$packagingCategoryId = (int) $categoryStmt->fetchColumn();
if ($packagingCategoryId <= 0) {
    throw new RuntimeException('Packaging Materials category was not found');
}

$actorStmt = $db->query("
    SELECT id
    FROM users
    WHERE role IN ('general_manager', 'admin') AND is_active = 1
    ORDER BY id
    LIMIT 1
");
$actorId = (int) $actorStmt->fetchColumn();
if ($actorId <= 0) {
    throw new RuntimeException('An active Admin/General Manager account is required');
}

$supplier = $db->query("
    SELECT DISTINCT s.id, s.supplier_name
    FROM suppliers s
    JOIN supplier_ingredients si ON si.supplier_id = s.id AND si.is_active = 1
    JOIN ingredients i ON i.id = si.ingredient_id
    JOIN ingredient_categories c ON c.id = i.category_id
    WHERE s.is_active = 1
      AND LOWER(c.category_name) LIKE '%packag%'
    ORDER BY s.id
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC) ?: null;

$skuStmt = $db->query("
    SELECT p.id, p.product_code, COALESCE(bp.name, p.product_name) AS base_name,
           CASE
               WHEN LOWER(p.unit_measure) IN ('l', 'lt', 'liter', 'liters', 'litre', 'litres')
                   THEN ROUND(p.unit_size * 1000)
               WHEN LOWER(p.unit_measure) IN ('ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres')
                   THEN ROUND(p.unit_size)
               ELSE NULL
           END AS size_ml
    FROM products p
    LEFT JOIN base_products bp ON bp.id = p.base_product_id
    WHERE p.is_active = 1
      AND p.base_product_id IS NOT NULL
      AND LOWER(COALESCE(p.base_unit, '')) IN ('bottle', 'bottles')
    HAVING size_ml IN (250, 500, 1000)
    ORDER BY p.id
");
$skus = $skuStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
if (!$skus) {
    throw new RuntimeException('No active 250 mL, 500 mL, or 1 L bottle SKUs were found');
}

$baseMaterials = [
    'container_250' => [
        'code' => 'TST-PKG-BTL-250', 'name' => '250 mL Food-Grade Bottle (Test)',
        'role' => 'container', 'unit' => 'pcs', 'stock' => 2500, 'minimum' => 250,
        'reorder' => 500, 'maximum' => 2500, 'cost' => 4.25, 'capacity' => 250, 'capacity_unit' => 'ml',
    ],
    'container_500' => [
        'code' => 'TST-PKG-BTL-500', 'name' => '500 mL Food-Grade Bottle (Test)',
        'role' => 'container', 'unit' => 'pcs', 'stock' => 2500, 'minimum' => 250,
        'reorder' => 500, 'maximum' => 2500, 'cost' => 5.50, 'capacity' => 500, 'capacity_unit' => 'ml',
    ],
    'container_1000' => [
        'code' => 'TST-PKG-BTL-1000', 'name' => '1000 mL Food-Grade Bottle (Test)',
        'role' => 'container', 'unit' => 'pcs', 'stock' => 2000, 'minimum' => 200,
        'reorder' => 400, 'maximum' => 2000, 'cost' => 7.00, 'capacity' => 1000, 'capacity_unit' => 'ml',
    ],
    'closure' => [
        'code' => 'TST-PKG-CAP-28', 'name' => '28 mm Tamper-Evident Bottle Cap (Test)',
        'role' => 'closure', 'unit' => 'pcs', 'stock' => 8000, 'minimum' => 800,
        'reorder' => 1600, 'maximum' => 8000, 'cost' => 0.80,
    ],
    'secondary' => [
        'code' => 'TST-PKG-FILM', 'name' => 'Clear Shrink Film Roll (Test)',
        'role' => 'secondary', 'unit' => 'roll', 'stock' => 100, 'minimum' => 10,
        'reorder' => 20, 'maximum' => 100, 'cost' => 180.00,
    ],
];

foreach ($skus as $sku) {
    $safeCode = preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $sku['product_code']));
    $safeCode = trim((string) $safeCode, '-');
    $code = substr('TST-LBL-' . $safeCode, 0, 30);
    $sizeMl = (int) $sku['size_ml'];
    $baseMaterials['label_' . (int) $sku['id']] = [
        'code' => $code,
        'name' => $sizeMl . ' mL ' . trim((string) $sku['base_name']) . ' Label [' .
            trim((string) $sku['product_code']) . '] (Test)',
        'role' => 'label', 'unit' => 'pcs', 'stock' => 2500, 'minimum' => 250,
        'reorder' => 500, 'maximum' => 2500, 'cost' => 0.65,
        'capacity' => $sizeMl, 'capacity_unit' => 'ml',
    ];
}

$selectMaterial = $db->prepare('SELECT id, current_stock FROM ingredients WHERE ingredient_code = ?');
$insertMaterial = $db->prepare("
    INSERT INTO ingredients
        (ingredient_code, ingredient_name, category_id, unit_of_measure, physical_state,
         packaging_role, packaging_capacity_value, packaging_capacity_unit,
         purchase_format, purchase_price_basis, purchase_price,
         minimum_stock, reorder_point, maximum_stock, lead_time_days, current_stock,
         initial_stock_route, onboarding_status, reserved_stock, unit_cost,
         storage_location, storage_requirements, shelf_life_days, is_perishable, is_active)
    VALUES (?, ?, ?, ?, 'count', ?, ?, ?, 'direct_unit', 'stock_unit', ?, ?, ?, ?, 7, ?,
            'opening_stock', 'completed', 0, ?, 'Packaging Store - TEST',
            'Demo-only packaging specification; replace with client-approved data.', NULL, 0, 1)
");
$updateMaterial = $db->prepare("
    UPDATE ingredients
    SET ingredient_name = ?, category_id = ?, unit_of_measure = ?, physical_state = 'count',
        packaging_role = ?, packaging_capacity_value = ?, packaging_capacity_unit = ?,
        purchase_format = 'direct_unit', purchase_price_basis = 'stock_unit',
        purchase_price = ?, minimum_stock = ?, reorder_point = ?, maximum_stock = ?,
        unit_cost = ?, storage_location = 'Packaging Store - TEST',
        storage_requirements = 'Demo-only packaging specification; replace with client-approved data.',
        shelf_life_days = NULL, is_perishable = 0, is_active = 1
    WHERE id = ?
");
$insertLedger = $db->prepare("
    INSERT IGNORE INTO inventory_transactions
        (transaction_code, transaction_type, item_type, item_id, quantity,
         unit_of_measure, quantity_before, quantity_after, reference_type,
         performed_by, approved_by, reason)
    VALUES (?, 'physical_adjust', 'packaging', ?, ?, ?, 0, ?, 'demo_seed', ?, ?, ?)
");
$linkSupplier = $db->prepare("
    INSERT INTO supplier_ingredients
        (supplier_id, ingredient_id, reference_unit_price, purchase_format,
         package_quantity_in_stock_unit, quoted_price, price_basis, offer_label,
         enforce_whole_packages, is_active, created_by)
    VALUES (?, ?, ?, 'direct_unit', 1, ?, 'stock_unit', ?, 0, 1, ?)
    ON DUPLICATE KEY UPDATE
        reference_unit_price = VALUES(reference_unit_price), quoted_price = VALUES(quoted_price),
        price_basis = 'stock_unit', offer_label = VALUES(offer_label), is_active = 1
");

$materialIds = [];
$createdCount = 0;
$db->beginTransaction();
try {
    foreach ($baseMaterials as $key => $material) {
        $selectMaterial->execute([$material['code']]);
        $existing = $selectMaterial->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $materialId = (int) $existing['id'];
            $updateMaterial->execute([
                $material['name'], $packagingCategoryId, $material['unit'], $material['role'],
                $material['capacity'] ?? null, $material['capacity_unit'] ?? null,
                $material['cost'], $material['minimum'], $material['reorder'], $material['maximum'],
                $material['cost'], $materialId,
            ]);
        } else {
            $insertMaterial->execute([
                $material['code'], $material['name'], $packagingCategoryId, $material['unit'],
                $material['role'], $material['capacity'] ?? null, $material['capacity_unit'] ?? null,
                $material['cost'], $material['minimum'], $material['reorder'],
                $material['maximum'], $material['stock'], $material['cost'],
            ]);
            $materialId = (int) $db->lastInsertId();
            $createdCount++;
            $transactionCode = substr('DEMO-PKG-OPEN-' . $materialId, 0, 30);
            $insertLedger->execute([
                $transactionCode, $materialId, $material['stock'], $material['unit'],
                $material['stock'], $actorId, $actorId,
                'Opening balance for clearly marked demo packaging data.',
            ]);
        }
        $materialIds[$key] = $materialId;

        if ($supplier) {
            $offerLabel = 'TEST quotation - ' . $material['name'];
            $linkSupplier->execute([
                (int) $supplier['id'], $materialId, $material['cost'], $material['cost'],
                substr($offerLabel, 0, 120), $actorId,
            ]);
        }
    }

    $clearRoles = $db->prepare("
        DELETE FROM sku_packaging_bom_items
        WHERE product_id = ?
          AND ingredient_id IN (
              SELECT id FROM ingredients
              WHERE packaging_role IN ('container', 'closure', 'label', 'secondary')
          )
    ");
    $insertBom = $db->prepare("
        INSERT INTO sku_packaging_bom_items
            (product_id, ingredient_id, quantity_per_unit, waste_percent, unit, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE quantity_per_unit = VALUES(quantity_per_unit),
            waste_percent = VALUES(waste_percent), unit = VALUES(unit), is_active = 1
    ");

    foreach ($skus as $sku) {
        $productId = (int) $sku['id'];
        $sizeMl = (int) $sku['size_ml'];
        $clearRoles->execute([$productId]);
        $insertBom->execute([$productId, $materialIds['container_' . $sizeMl], 1, 2, 'pcs']);
        $insertBom->execute([$productId, $materialIds['closure'], 1, 2, 'pcs']);
        $insertBom->execute([$productId, $materialIds['label_' . $productId], 1, 3, 'pcs']);
        // One test roll covers 50 finished units; the helper stores per-unit use.
        $insertBom->execute([$productId, $materialIds['secondary'], 0.02, 5, 'roll']);
        $db->prepare('UPDATE products SET primary_container_id = ? WHERE id = ?')
            ->execute([$materialIds['container_' . $sizeMl], $productId]);
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}

$notReady = [];
foreach ($skus as $sku) {
    $bom = getSkuPackagingBom($db, (int) $sku['id']);
    $readiness = assessSkuPackagingBomReadiness(
        'bottle', $bom, (int) $sku['size_ml'], 'mL'
    );
    if (!$readiness['ready']) {
        $notReady[] = $sku['product_code'] . ': ' . implode('; ', $readiness['missing']);
    }
}
if ($notReady) {
    throw new RuntimeException('Seeded BOM verification failed: ' . implode(' | ', $notReady));
}

echo 'Demo packaging seed complete.' . PHP_EOL;
echo 'Materials created: ' . $createdCount . '; catalog materials ensured: ' . count($materialIds) . PHP_EOL;
echo 'Complete bottle SKU BOMs: ' . count($skus) . PHP_EOL;
echo 'Test supplier link: ' . ($supplier ? $supplier['supplier_name'] : 'none (no existing packaging supplier found)') . PHP_EOL;
