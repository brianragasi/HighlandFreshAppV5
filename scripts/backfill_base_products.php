<?php
/**
 * Backfill base_products from existing products + link recipes/batches.
 *
 * Run once after bulk_batch_product_architecture.sql:
 *   php scripts/backfill_base_products.php
 */
define('HIGHLAND_FRESH', true);
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../api/config/config.php';
require __DIR__ . '/../api/config/database.php';

$db = Database::getInstance()->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function columnExists(PDO $db, string $table, string $column): bool {
    try {
        $db->query("SELECT `{$column}` FROM `{$table}` LIMIT 0");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($table));
    return (bool) $stmt->fetch();
}

/**
 * Strip packaging size suffixes so "Chocolate Milk 1L" and "Chocolate Milk 200ml"
 * collapse to the same base name.
 */
function deriveBaseName(string $productName): string {
    $name = trim($productName);
    // Remove common size tokens at end: 1L, 500ml, 250g, 100 g, etc.
    $name = preg_replace(
        '/[\s\-]*(?:\d+(?:[.,]\d+)?\s*(?:ml|l|g|kg|oz|pcs?|pieces?|bottles?|cups?|packs?))+$/iu',
        '',
        $name
    );
    $name = trim(preg_replace('/\s+/', ' ', $name));
    return $name !== '' ? $name : trim($productName);
}

function categoryToCodePrefix(string $category): string {
    $map = [
        'pasteurized_milk' => 'BASE-PM',
        'flavored_milk' => 'BASE-FM',
        'yogurt' => 'BASE-YG',
        'cheese' => 'BASE-CH',
        'butter' => 'BASE-BT',
        'cream' => 'BASE-CR',
    ];
    return $map[$category] ?? 'BASE-XX';
}

if (!tableExists($db, 'base_products')) {
    fwrite(STDERR, "base_products missing — run scripts/sql/bulk_batch_product_architecture.sql first\n");
    exit(1);
}
if (!columnExists($db, 'products', 'base_product_id')) {
    fwrite(STDERR, "products.base_product_id missing — run DDL first\n");
    exit(1);
}

echo "=== Backfill base_products ===\n";

// 1) Build unique base keys from products
$products = $db->query("
    SELECT id, product_code, product_name, category, variant, milk_type_id,
           description, shelf_life_days, storage_temp_min, storage_temp_max, base_product_id
    FROM products
    ORDER BY product_name, unit_size
")->fetchAll(PDO::FETCH_ASSOC);

$groups = []; // key => meta
foreach ($products as $p) {
    $baseName = deriveBaseName($p['product_name']);
    $cat = $p['category'] ?: 'pasteurized_milk';
    $key = mb_strtolower($baseName) . '||' . $cat;
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'name' => $baseName,
            'category' => $cat,
            'milk_type_id' => $p['milk_type_id'],
            'description' => $p['description'],
            'shelf_life_days' => $p['shelf_life_days'] ?? 7,
            'storage_temp_min' => $p['storage_temp_min'] ?? 2,
            'storage_temp_max' => $p['storage_temp_max'] ?? 6,
            'product_ids' => [],
        ];
    }
    $groups[$key]['product_ids'][] = (int) $p['id'];
    if (empty($groups[$key]['milk_type_id']) && !empty($p['milk_type_id'])) {
        $groups[$key]['milk_type_id'] = $p['milk_type_id'];
    }
}

// Also include recipe product names that may not match a product row name
if (columnExists($db, 'master_recipes', 'base_product_id')) {
    $recipes = $db->query("SELECT id, product_name, product_type, milk_type_id FROM master_recipes")->fetchAll(PDO::FETCH_ASSOC);
    $typeToCat = [
        'bottled_milk' => 'pasteurized_milk',
        'flavored_milk' => 'flavored_milk',
        'yogurt' => 'yogurt',
        'cheese' => 'cheese',
        'butter' => 'butter',
        'cream' => 'cream',
        'milk_bar' => 'pasteurized_milk',
    ];
    foreach ($recipes as $r) {
        $baseName = deriveBaseName($r['product_name'] ?: 'Unknown');
        $cat = $typeToCat[$r['product_type'] ?? ''] ?? 'pasteurized_milk';
        $key = mb_strtolower($baseName) . '||' . $cat;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'name' => $baseName,
                'category' => $cat,
                'milk_type_id' => $r['milk_type_id'] ?? null,
                'description' => null,
                'shelf_life_days' => 7,
                'storage_temp_min' => 2,
                'storage_temp_max' => 6,
                'product_ids' => [],
            ];
        }
    }
}

$insertBase = $db->prepare("
    INSERT INTO base_products
      (code, name, category, milk_type_id, description, default_shelf_life_days,
       storage_temp_min, storage_temp_max, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
");
$findBase = $db->prepare("SELECT id FROM base_products WHERE name = ? AND category = ? LIMIT 1");
$updateProduct = $db->prepare("UPDATE products SET base_product_id = ? WHERE id = ?");

$seq = [];
$created = 0;
$linkedSkus = 0;

foreach ($groups as $key => $g) {
    $findBase->execute([$g['name'], $g['category']]);
    $existingId = $findBase->fetchColumn();
    if ($existingId) {
        $baseId = (int) $existingId;
    } else {
        $prefix = categoryToCodePrefix($g['category']);
        $seq[$prefix] = ($seq[$prefix] ?? 0) + 1;
        // Ensure unique code
        $code = sprintf('%s-%03d', $prefix, $seq[$prefix]);
        $try = 0;
        while ($try < 50) {
            $chk = $db->prepare("SELECT id FROM base_products WHERE code = ?");
            $chk->execute([$code]);
            if (!$chk->fetch()) {
                break;
            }
            $seq[$prefix]++;
            $code = sprintf('%s-%03d', $prefix, $seq[$prefix]);
            $try++;
        }
        $insertBase->execute([
            $code,
            $g['name'],
            $g['category'],
            $g['milk_type_id'],
            $g['description'],
            $g['shelf_life_days'],
            $g['storage_temp_min'],
            $g['storage_temp_max'],
        ]);
        $baseId = (int) $db->lastInsertId();
        $created++;
        echo "  + base_product #{$baseId} {$code} — {$g['name']} ({$g['category']})\n";
    }

    foreach ($g['product_ids'] as $pid) {
        $updateProduct->execute([$baseId, $pid]);
        $linkedSkus++;
    }
    $groups[$key]['base_id'] = $baseId;
}

echo "Created {$created} base products; linked {$linkedSkus} SKU rows.\n";

// 2) Link recipes → base_product_id + bulk_yield_liters
if (columnExists($db, 'master_recipes', 'base_product_id')) {
    $recipes = $db->query("
        SELECT id, product_id, product_name, product_type, variant,
               base_milk_liters, expected_yield, yield_unit, bulk_yield_liters, base_product_id
        FROM master_recipes
    ")->fetchAll(PDO::FETCH_ASSOC);

    $updRecipe = $db->prepare("
        UPDATE master_recipes
        SET base_product_id = ?, bulk_yield_liters = ?
        WHERE id = ?
    ");
    $skuBase = $db->prepare("SELECT base_product_id FROM products WHERE id = ?");

    $typeToCat = [
        'bottled_milk' => 'pasteurized_milk',
        'flavored_milk' => 'flavored_milk',
        'yogurt' => 'yogurt',
        'cheese' => 'cheese',
        'butter' => 'butter',
        'cream' => 'cream',
        'milk_bar' => 'pasteurized_milk',
    ];

    $linkedRecipes = 0;
    foreach ($recipes as $r) {
        $baseId = null;

        // Prefer SKU → base_product
        if (!empty($r['product_id'])) {
            $skuBase->execute([(int) $r['product_id']]);
            $baseId = $skuBase->fetchColumn() ?: null;
            if ($baseId) {
                $baseId = (int) $baseId;
            }
        }

        // Fallback: match by derived name + category
        if (!$baseId) {
            $baseName = deriveBaseName($r['product_name'] ?: '');
            $cat = $typeToCat[$r['product_type'] ?? ''] ?? 'pasteurized_milk';
            $findBase->execute([$baseName, $cat]);
            $baseId = $findBase->fetchColumn() ?: null;
            if ($baseId) {
                $baseId = (int) $baseId;
            }
        }

        // Compute bulk yield liters
        $yieldUnit = strtolower(trim((string) ($r['yield_unit'] ?? '')));
        $expected = (float) ($r['expected_yield'] ?? 0);
        $milk = (float) ($r['base_milk_liters'] ?? 0);
        $bulk = $r['bulk_yield_liters'] !== null ? (float) $r['bulk_yield_liters'] : null;

        if ($bulk === null || $bulk <= 0) {
            if (in_array($yieldUnit, ['liter', 'liters', 'l', 'lt'], true) && $expected > 0) {
                $bulk = $expected;
            } elseif ($milk > 0) {
                // Ingredients scale from milk input; treat milk liters as bulk batch scale
                $bulk = $milk;
            } elseif ($expected > 0) {
                $bulk = $expected; // last resort
            } else {
                $bulk = null;
            }
        }

        if ($baseId || $bulk) {
            $updRecipe->execute([
                $baseId,
                $bulk,
                (int) $r['id'],
            ]);
            $linkedRecipes++;
        }
    }
    echo "Updated {$linkedRecipes} recipes with base_product_id / bulk_yield_liters.\n";
}

// 3) Backfill production_batches.base_product_id + bulk volume from recipe when missing
if (columnExists($db, 'production_batches', 'base_product_id')) {
    $n = $db->exec("
        UPDATE production_batches pb
        LEFT JOIN master_recipes mr ON mr.id = pb.recipe_id
        LEFT JOIN products p ON p.id = pb.product_id
        SET
          pb.base_product_id = COALESCE(pb.base_product_id, mr.base_product_id, p.base_product_id),
          pb.bulk_volume_liters = COALESCE(
            pb.bulk_volume_liters,
            mr.bulk_yield_liters,
            CASE WHEN pb.actual_yield IS NOT NULL AND p.unit_size IS NOT NULL AND LOWER(COALESCE(p.unit_measure,'ml')) IN ('ml','milliliter','milliliters')
                 THEN (pb.actual_yield * p.unit_size) / 1000
                 WHEN pb.actual_yield IS NOT NULL AND p.unit_size IS NOT NULL AND LOWER(COALESCE(p.unit_measure,'l')) IN ('l','liter','liters')
                 THEN pb.actual_yield * p.unit_size
                 ELSE mr.base_milk_liters END
          )
        WHERE pb.base_product_id IS NULL OR pb.bulk_volume_liters IS NULL
    ");
    echo "Backfilled production_batches rows (affected≈{$n}).\n";

    // Remaining = volume if never packaged
    $db->exec("
        UPDATE production_batches
        SET bulk_remaining_liters = bulk_volume_liters
        WHERE bulk_volume_liters IS NOT NULL AND bulk_remaining_liters IS NULL
    ");
}

// Summary
$bp = (int) $db->query("SELECT COUNT(*) FROM base_products")->fetchColumn();
$skusLinked = (int) $db->query("SELECT COUNT(*) FROM products WHERE base_product_id IS NOT NULL")->fetchColumn();
$recLinked = columnExists($db, 'master_recipes', 'base_product_id')
    ? (int) $db->query("SELECT COUNT(*) FROM master_recipes WHERE base_product_id IS NOT NULL")->fetchColumn()
    : 0;

echo "=== DONE ===\n";
echo "base_products={$bp}, products with base={$skusLinked}, recipes with base={$recLinked}\n";
