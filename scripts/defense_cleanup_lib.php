<?php

/**
 * Quarantine known placeholder records from the October 2026 defense catalog.
 * This never deletes a row, changes stock, edits a balance, or rewrites history.
 */
function hfCleanDefenseCatalog(PDO $db, bool $apply): array
{
    $bases = [
        8 => 'Kesong Puti', 10 => 'MilkBAR-PANDAN', 11 => 'Milkbar-UBE',
        12 => 'MilkBarBisaya', 14 => 'Strawberry Yogurt',
        21 => 'TEST Chocolate Milk', 22 => 'dutch-milk', 23 => 'dutch-milk',
        24 => 'dutch-milk', 25 => 'ube milktea special', 28 => 'WakoKabalo',
        29 => 'Banana Smoothie', 30 => 'Milk Bar Durian (250ml)',
        31 => 'Strawberry Smoothie', 32 => 'Milk Bar Ube (250ml)',
        33 => 'Milk Bar Choco (250ml)', 34 => 'Yogurt Ube',
        37 => 'Yogurt Strawberry Flavor (250ml)',
        38 => 'Yogurt Melon Flavor (250ml)',
        39 => 'Yogurt Ube Flavor (250ml)',
        40 => 'Yogurt Vanilla Flavor (250ml)',
        41 => 'Yogurt Choco Flavor (250ml)',
        42 => 'Yogurt Mango Flavor (250ml)',
        43 => 'Yogurt Pineapple Flavor (250ml)',
        46 => 'Melon Milk', 47 => 'Gouda Cheese',
    ];
    $ingredientCodes = [
        'ING-0018', 'ING-0023', 'ING-0024', 'ING-0025', 'ING-0026',
        'ING-0027', 'ING-DEMO-001', 'ING-DEMO-002', 'ING-0030', 'ING-0037',
        'ING-008', 'ING-0041', 'ING-0042', 'ING-0043',
        'ING-0044', 'ING-0045', 'ING-0046', 'ING-0047', 'ING-0048',
        'ING-0049', 'ING-0050', 'ING-0051',
        'ING-0080', 'ING-0081', 'ING-0082', 'ING-0086', 'ING-0087', 'ING-0088',
        'ING-0067', 'ING-0077', 'ING-0078', 'ING-0079',
        'ING-0083', 'ING-0084', 'ING-0085', 'ING-0089', 'ING-0090',
        'ING-0093', 'ING-0095', 'ING-0096', 'ING-0098', 'ING-0099',
        'MOCK-PKG-BTL-500', 'MOCK-PKG-LBL-CHO500',
        'TST-LBL-FM0009', 'TST-LBL-FM0010', 'TST-LBL-FM0011',
        'TST-LBL-YG0001', 'TST-LBL-YG0002', 'TST-LBL-YG0004',
        'DEMO-ING-MELON-FLAVOR', 'DEMO-ING-CHEESE-CULTURE',
        'DEMO-PKG-GOUDA-250', 'DEMO-PKG-GOUDA-500',
        'DEMO-LBL-MEL-500', 'DEMO-LBL-MEL-1000',
        'DEMO-LBL-GOU-250', 'DEMO-LBL-GOU-500',
    ];

    $db->beginTransaction();
    try {
        if ($db->query('SELECT type_code FROM milk_types WHERE id = 1')->fetchColumn() !== 'COW') {
            throw new RuntimeException('Expected Cow Milk type is missing; cleanup stopped');
        }
        $names = array_values(array_unique(array_values($bases)));
        $nameMarks = implode(',', array_fill(0, count($names), '?'));
        $lookup = $db->prepare("SELECT id, name FROM base_products WHERE name IN ({$nameMarks}) FOR UPDATE");
        $lookup->execute($names);
        $found = $lookup->fetchAll(PDO::FETCH_ASSOC);
        $foundNames = array_column($found, 'name');
        // The local defense copy includes two unapproved prototypes that may
        // not have been seeded on the live server; archive them if present.
        $missing = array_diff($names, $foundNames, ['Melon Milk', 'Gouda Cheese']);
        if ($missing) {
            throw new RuntimeException('Expected defense catalog names missing: ' . implode(', ', $missing));
        }
        foreach ($foundNames as $name) {
            if (!in_array($name, $names, true)) {
                throw new RuntimeException('A name matched only by database collation; cleanup stopped');
            }
        }
        $ids = array_map('intval', array_column($found, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $counts = [];
        foreach (['base_products', 'products', 'master_recipes'] as $table) {
            $key = $table === 'base_products' ? 'id' : 'base_product_id';
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$key} IN ({$marks}) AND is_active = 1");
            $stmt->execute($ids);
            $counts[$table] = (int) $stmt->fetchColumn();
            $stmt = $db->prepare("UPDATE {$table} SET is_active = 0 WHERE {$key} IN ({$marks}) AND is_active = 1");
            $stmt->execute($ids);
        }

        $customer = $db->query("SELECT id, name, status FROM customers WHERE customer_code = 'CUS00008' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        if (!$customer || $customer['name'] !== 'TestLang') {
            throw new RuntimeException('Expected test customer CUS00008 was changed; cleanup stopped');
        }
        $referencesStmt = $db->prepare('SELECT COUNT(*) FROM sales_orders WHERE customer_id = ?');
        $referencesStmt->execute([(int) $customer['id']]);
        $references = (int) $referencesStmt->fetchColumn();
        if ($references !== 0) {
            throw new RuntimeException('Test customer CUS00008 has order history; cleanup stopped');
        }
        $counts['customers'] = $customer['status'] === 'active' ? 1 : 0;
        $db->exec("UPDATE customers SET status = 'inactive' WHERE customer_code = 'CUS00008'
            AND name = 'TestLang' AND status = 'active'");

        // These names identify the institution/store clearly; financial fields stay unchanged.
        $counts['customer_types'] = $db->exec("UPDATE customers SET customer_type = 'feeding_program'
            WHERE customer_code = 'DEPED-CDO-001' AND name = 'DepEd Region X Feeding Program'
              AND customer_type = 'supermarket'");
        $counts['customer_types'] += $db->exec("UPDATE customers SET customer_type = 'supermarket'
            WHERE customer_code = 'CUS00007' AND name = 'Ororama Cogon'
              AND customer_type = 'institutional'");
        $counts['customer_types'] += $db->exec("UPDATE customers SET customer_type = 'supermarket'
            WHERE name = 'SM Supermarket' AND customer_type = 'institutional'");
        $counts['customer_types'] += $db->exec("UPDATE customers SET customer_type = 'supermarket'
            WHERE name = 'Robinson''s Supermarket' AND customer_type = 'institutional'");

        $counts['duplicate_recipes'] = $db->exec("UPDATE master_recipes r
            JOIN base_products b ON b.id = r.base_product_id AND b.name = 'Fresh Milk'
            SET r.is_active = 0
            WHERE r.recipe_code = 'RCP-FM-500' AND r.is_active = 1");
        $counts['cream_milk_type'] = $db->exec("UPDATE base_products
            SET milk_type_id = 1 WHERE name = 'Fresh Cream' AND milk_type_id IS NULL");
        $counts['cream_milk_type'] += $db->exec("UPDATE products p
            JOIN base_products b ON b.id = p.base_product_id AND b.name = 'Fresh Cream'
            SET p.milk_type_id = 1 WHERE p.milk_type_id IS NULL");

        $ingredientMarks = implode(',', array_fill(0, count($ingredientCodes), '?'));
        $ingredientCount = $db->prepare("SELECT COUNT(*) FROM ingredients WHERE ingredient_code IN ({$ingredientMarks}) AND is_active = 1");
        $ingredientCount->execute($ingredientCodes);
        $counts['ingredients'] = (int) $ingredientCount->fetchColumn();
        $ingredientUpdate = $db->prepare("UPDATE ingredients SET is_active = 0
            WHERE ingredient_code IN ({$ingredientMarks}) AND is_active = 1
              AND NOT EXISTS (
                  SELECT 1 FROM recipe_ingredients ri
                  JOIN master_recipes r ON r.id = ri.recipe_id AND r.is_active = 1
                  WHERE ri.ingredient_id = ingredients.id
              )
              AND NOT EXISTS (
                  SELECT 1 FROM sku_packaging_bom_items b
                  JOIN products p ON p.id = b.product_id AND p.is_active = 1
                  WHERE b.ingredient_id = ingredients.id AND b.is_active = 1
              )");
        $ingredientUpdate->execute($ingredientCodes);
        $counts['ingredients'] = $ingredientUpdate->rowCount();

        $counts['ingredient_labels'] = $db->exec("UPDATE ingredients
            SET ingredient_name = 'Chocolate Powder'
            WHERE ingredient_code = 'ING-003'
              AND ingredient_name = 'Chocolate Powder X'");

        // These packaging links point to another product's label or a bottle cap
        // on a butter pack. Leave the SKU setup visibly incomplete for review.
        $counts['packaging_links'] = $db->exec("UPDATE sku_packaging_bom_items b
            JOIN products p ON p.id = b.product_id
            JOIN ingredients i ON i.id = b.ingredient_id
            SET b.is_active = 0
            WHERE b.is_active = 1 AND (
                (p.product_code = 'YOG-500' AND i.ingredient_code = 'TST-LBL-FM0010')
                OR (p.product_code = 'BUT-250' AND i.ingredient_code IN
                    ('MOCK-PKG-CAP-28', 'MOCK-PKG-LBL-CHO500'))
            )");

        $supplierTargets = [
            'SUP-0019' => 'Darwin Galudo',
            'SUP-0018' => 'Alexis',
            'SUP-0017' => 'Supplier B',
            'SUP-0016' => 'Supplier A',
            'SUP-0015' => 'San Mig',
            'SUP-0014' => 'Nestle',
            'SUP-0011' => 'lordneil',
        ];
        $supplierLookup = $db->prepare('SELECT id, supplier_name, is_active FROM suppliers
            WHERE supplier_code = ? FOR UPDATE');
        $openOrders = $db->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?
            AND status IN ('draft', 'approved', 'ordered', 'partial_received')");
        $archiveSupplier = $db->prepare('UPDATE suppliers SET is_active = 0
            WHERE id = ? AND is_active = 1');
        $counts['suppliers'] = 0;
        foreach ($supplierTargets as $code => $expectedName) {
            $supplierLookup->execute([$code]);
            $supplier = $supplierLookup->fetch(PDO::FETCH_ASSOC);
            if (!$supplier || $supplier['supplier_name'] !== $expectedName) {
                throw new RuntimeException("Supplier {$code} does not match the reviewed placeholder; cleanup stopped");
            }
            $openOrders->execute([(int) $supplier['id']]);
            if ((int) $openOrders->fetchColumn() > 0) {
                throw new RuntimeException("Supplier {$code} has an open purchase order; cleanup stopped");
            }
            if ((int) $supplier['is_active'] === 1) {
                $archiveSupplier->execute([(int) $supplier['id']]);
                $counts['suppliers'] += $archiveSupplier->rowCount();
            }
        }

        // The three legacy MilkBar sizes were created without packaging. Keep
        // the 250 mL bar, which has a matching stocked wrapper; retire only
        // the two sizes that have never entered sales or finished goods.
        $productByCode = $db->prepare('SELECT p.id, p.base_product_id, p.product_name, p.category,
                p.unit_size, p.unit_measure, p.base_unit, p.primary_container_id,
                p.is_active, b.name AS base_name
            FROM products p JOIN base_products b ON b.id = p.base_product_id
            WHERE p.product_code = ? FOR UPDATE');
        $productReferences = $db->prepare('SELECT
            (SELECT COUNT(*) FROM sales_order_items WHERE product_id = ?) AS sales_count,
            (SELECT COUNT(*) FROM finished_goods_inventory WHERE product_id = ?) AS fg_count');
        $counts['unused_milkbar_skus'] = 0;
        foreach (['PM0003' => 100, 'BAR-2021' => 1000] as $code => $size) {
            $productByCode->execute([$code]);
            $sku = $productByCode->fetch(PDO::FETCH_ASSOC);
            if (!$sku || $sku['base_name'] !== 'MilkBar'
                || !in_array($sku['category'], ['pasteurized_milk', 'milk_bar'], true)
                || (float) $sku['unit_size'] !== (float) $size) {
                throw new RuntimeException("MilkBar SKU {$code} changed; cleanup stopped");
            }
            $productReferences->execute([(int) $sku['id'], (int) $sku['id']]);
            $refs = $productReferences->fetch(PDO::FETCH_ASSOC);
            if ((int) $refs['sales_count'] || (int) $refs['fg_count']) {
                throw new RuntimeException("MilkBar SKU {$code} has transaction history; cleanup stopped");
            }
            $archive = $db->prepare('UPDATE products SET is_active = 0 WHERE id = ? AND is_active = 1');
            $archive->execute([(int) $sku['id']]);
            $counts['unused_milkbar_skus'] += $archive->rowCount();
        }

        $productByCode->execute(['PM0006']);
        $milkBar = $productByCode->fetch(PDO::FETCH_ASSOC);
        $wrapper = $db->query("SELECT id, packaging_role, packaging_capacity_value,
                packaging_capacity_unit, is_active FROM ingredients
            WHERE ingredient_code = 'ING-0091' FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        if (!$milkBar || $milkBar['base_name'] !== 'MilkBar'
            || !in_array($milkBar['category'], ['pasteurized_milk', 'milk_bar'], true)
            || (float) $milkBar['unit_size'] !== 250.0 || $milkBar['unit_measure'] !== 'ml'
            || !$wrapper || $wrapper['packaging_role'] !== 'container'
            || (float) $wrapper['packaging_capacity_value'] !== 250.0
            || strtolower($wrapper['packaging_capacity_unit']) !== 'ml'
            || (int) $wrapper['is_active'] !== 1) {
            throw new RuntimeException('MilkBar 250 mL wrapper differs from reviewed catalog; cleanup stopped');
        }
        $activeBom = $db->prepare('SELECT ingredient_id FROM sku_packaging_bom_items
            WHERE product_id = ? AND is_active = 1 FOR UPDATE');
        $activeBom->execute([(int) $milkBar['id']]);
        foreach ($activeBom->fetchAll(PDO::FETCH_COLUMN) as $materialId) {
            if ((int) $materialId !== (int) $wrapper['id']) {
                throw new RuntimeException('MilkBar 250 mL has another active packaging component; cleanup stopped');
            }
        }
        if (!in_array($milkBar['base_unit'], ['piece', 'wrapped_block'], true)
            || ($milkBar['primary_container_id'] !== null
                && (int) $milkBar['primary_container_id'] !== (int) $wrapper['id'])) {
            throw new RuntimeException('MilkBar 250 mL package style changed; cleanup stopped');
        }
        $stmt = $db->prepare("UPDATE products SET base_unit = 'wrapped_block',
            primary_container_id = ? WHERE id = ? AND (base_unit <> 'wrapped_block'
            OR primary_container_id IS NULL)");
        $stmt->execute([(int) $wrapper['id'], (int) $milkBar['id']]);
        $counts['milkbar_package_style'] = $stmt->rowCount();
        $stmt = $db->prepare("INSERT INTO sku_packaging_bom_items
            (product_id, ingredient_id, quantity_per_unit, waste_percent, unit, is_active)
            VALUES (?, ?, 1, 0, 'pcs', 1)
            ON DUPLICATE KEY UPDATE is_active = 1");
        $stmt->execute([(int) $milkBar['id'], (int) $wrapper['id']]);
        $counts['milkbar_wrapper_links'] = $stmt->rowCount();

        $milkBarRecipe = $db->prepare('SELECT product_type FROM master_recipes
            WHERE base_product_id = ? AND is_active = 1');
        $milkBarRecipe->execute([(int) $milkBar['base_product_id']]);
        $recipeTypes = $milkBarRecipe->fetchAll(PDO::FETCH_COLUMN);
        if (!$recipeTypes || array_diff($recipeTypes, ['', 'milk_bar'])) {
            throw new RuntimeException('MilkBar active recipe type changed; cleanup stopped');
        }
        $stmt = $db->prepare("UPDATE master_recipes SET product_type = 'milk_bar'
            WHERE base_product_id = ? AND recipe_code = 'RCP-0025'
              AND is_active = 1 AND product_type = ''");
        $stmt->execute([(int) $milkBar['base_product_id']]);
        $counts['milkbar_recipe_type'] = $stmt->rowCount();
        $stmt = $db->prepare("UPDATE base_products SET category = 'milk_bar'
            WHERE id = ? AND name = 'MilkBar' AND category = 'pasteurized_milk'");
        $stmt->execute([(int) $milkBar['base_product_id']]);
        $counts['milkbar_base_category'] = $stmt->rowCount();
        $stmt = $db->prepare("UPDATE products SET category = 'milk_bar'
            WHERE base_product_id = ? AND product_code IN ('PM0003', 'PM0006', 'BAR-2021')
              AND category = 'pasteurized_milk'");
        $stmt->execute([(int) $milkBar['base_product_id']]);
        $counts['milkbar_sku_categories'] = $stmt->rowCount();

        // An unrelated Durian Yogurt label had been attached to Plain Yogurt
        // and Butter. Remove false readiness rather than inventing stock.
        $wrongLabel = $db->prepare("UPDATE sku_packaging_bom_items b
            JOIN products p ON p.id = b.product_id
            JOIN ingredients i ON i.id = b.ingredient_id
            SET b.is_active = 0
            WHERE p.product_code = ? AND i.ingredient_code = 'ING-0097'
              AND b.is_active = 1");
        $counts['wrong_yogurt_labels'] = 0;
        foreach (['YOG-500', 'BUT-250'] as $code) {
            $wrongLabel->execute([$code]);
            $counts['wrong_yogurt_labels'] += $wrongLabel->rowCount();
        }

        // Butter is sold by mass. The 250 g SKU has no matching wrapper, and
        // its bottle/cap BOM belongs to yogurt. Keep open orders untouched.
        $productByCode->execute(['BUT-250']);
        $butter250 = $productByCode->fetch(PDO::FETCH_ASSOC);
        $productByCode->execute(['BT0001']);
        $butter500 = $productByCode->fetch(PDO::FETCH_ASSOC);
        $butterPrimaryCode = null;
        if ($butter250 && $butter250['primary_container_id'] !== null) {
            $primaryLookup = $db->prepare('SELECT ingredient_code FROM ingredients WHERE id = ?');
            $primaryLookup->execute([(int) $butter250['primary_container_id']]);
            $butterPrimaryCode = $primaryLookup->fetchColumn();
        }
        if (!$butter250 || !$butter500
            || !in_array($butter250['base_name'], ['Butter', 'Pure Butter'], true)
            || !in_array($butter500['base_name'], ['Butter', 'Pure Butter'], true)
            || $butter250['category'] !== 'butter' || $butter500['category'] !== 'butter'
            || (float) $butter250['unit_size'] !== 250.0
            || (float) $butter500['unit_size'] !== 500.0
            || !in_array($butter250['unit_measure'], ['ml', 'g'], true)
            || !in_array($butter500['unit_measure'], ['ml', 'g'], true)
            || !in_array($butter250['base_unit'], ['bottle', 'block', 'wrapped_block'], true)
            || ($butter250['primary_container_id'] !== null
                && !in_array($butterPrimaryCode,
                    ['TST-PKG-BTL-250', 'DEMO-WRAP-BUT-250'], true))
            || $butter500['base_unit'] !== 'wrapped_block') {
            throw new RuntimeException('Butter SKUs differ from reviewed catalog; cleanup stopped');
        }
        $butterWrapper = $db->prepare('SELECT ingredient_code, packaging_role,
            packaging_capacity_value, packaging_capacity_unit FROM ingredients WHERE id = ? FOR UPDATE');
        $butterWrapper->execute([(int) $butter500['primary_container_id']]);
        $butterWrapperRow = $butterWrapper->fetch(PDO::FETCH_ASSOC);
        if (!$butterWrapperRow || $butterWrapperRow['ingredient_code'] !== 'ING-0076'
            || $butterWrapperRow['packaging_role'] !== 'container'
            || (float) $butterWrapperRow['packaging_capacity_value'] !== 500.0
            || !in_array($butterWrapperRow['packaging_capacity_unit'], ['ml', 'g'], true)) {
            throw new RuntimeException('Butter 500 g wrapper differs from reviewed catalog; cleanup stopped');
        }
        $activeBom->execute([(int) $butter250['id']]);
        $butterBom = array_map('intval', $activeBom->fetchAll(PDO::FETCH_COLUMN));
        $allowedButterMaterials = $db->query("SELECT id FROM ingredients WHERE ingredient_code IN
            ('MOCK-PKG-CAP-28', 'TST-PKG-BTL-250', 'DEMO-WRAP-BUT-250')")->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($butterBom, array_map('intval', $allowedButterMaterials))) {
            throw new RuntimeException('Butter 250 g has unreviewed packaging; cleanup stopped');
        }
        $stmt = $db->prepare("UPDATE sku_packaging_bom_items b
            JOIN ingredients i ON i.id = b.ingredient_id
            SET b.is_active = 0
            WHERE b.product_id = ? AND b.is_active = 1
              AND i.ingredient_code IN ('MOCK-PKG-CAP-28', 'TST-PKG-BTL-250')");
        $stmt->execute([(int) $butter250['id']]);
        $counts['wrong_butter_packaging'] = $stmt->rowCount();
        $stmt = $db->prepare("UPDATE products SET unit_measure = 'g',
            base_unit = 'wrapped_block',
            primary_container_id = CASE WHEN primary_container_id = 52
                THEN NULL ELSE primary_container_id END
            WHERE id = ? AND (unit_measure <> 'g' OR base_unit <> 'wrapped_block'
                OR primary_container_id = 52)");
        $stmt->execute([(int) $butter250['id']]);
        $counts['butter_250_master'] = $stmt->rowCount();
        $stmt = $db->prepare("UPDATE products SET unit_measure = 'g'
            WHERE id = ? AND unit_measure <> 'g'");
        $stmt->execute([(int) $butter500['id']]);
        $counts['butter_500_master'] = $stmt->rowCount();
        $stmt = $db->prepare("UPDATE ingredients SET packaging_capacity_unit = 'g'
            WHERE id = ? AND packaging_capacity_value = 500
              AND packaging_capacity_unit = 'ml'");
        $stmt->execute([(int) $butter500['primary_container_id']]);
        $counts['butter_wrapper_unit'] = $stmt->rowCount();

        // Complete the two remaining packaging PLANS with clearly marked demo
        // materials. Zero opening stock and no supplier quote mean Production
        // still cannot consume these components until they are really received.
        $packageCategory = $db->query("SELECT id FROM ingredient_categories
            WHERE category_code = 'CAT-PACK' AND is_active = 1")->fetchColumn();
        if (!$packageCategory) {
            throw new RuntimeException('Packaging Materials category is missing; cleanup stopped');
        }
        $demoMaterials = [
            'DEMO-LBL-YOG-250' => ['Plain Yogurt 250 mL Label (Demo, unverified)', 'label', 'label', 250, 'ml'],
            'DEMO-WRAP-BUT-250' => ['250 g Butter Wrapper (Demo, unverified)', 'container', 'wrapper', 250, 'g'],
        ];
        $materialByCode = $db->prepare('SELECT id, ingredient_name, category_id,
                packaging_role, packaging_form, packaging_capacity_value,
                packaging_capacity_unit, is_active FROM ingredients
            WHERE ingredient_code = ? FOR UPDATE');
        $insertMaterial = $db->prepare("INSERT INTO ingredients
            (ingredient_code, ingredient_name, category_id, unit_of_measure,
             physical_state, packaging_role, packaging_form,
             packaging_capacity_value, packaging_capacity_unit,
             packaging_capacity_confirmed, purchase_format,
             current_stock, available_stock, reserved_stock,
             minimum_stock, reorder_point, maximum_stock,
             unit_cost, market_price, is_perishable, is_active)
            VALUES (?, ?, ?, 'pcs', 'count', ?, ?, ?, ?, 0, 'direct_unit',
                    0, 0, 0, 0, 0, 0, 0, 0, 0, 1)");
        $demoIds = [];
        $counts['demo_packaging_materials'] = 0;
        foreach ($demoMaterials as $code => [$name, $role, $form, $capacity, $unit]) {
            $materialByCode->execute([$code]);
            $material = $materialByCode->fetch(PDO::FETCH_ASSOC);
            if ($material) {
                if ($material['ingredient_name'] !== $name
                    || (int) $material['category_id'] !== (int) $packageCategory
                    || $material['packaging_role'] !== $role
                    || $material['packaging_form'] !== $form
                    || (float) $material['packaging_capacity_value'] !== (float) $capacity
                    || $material['packaging_capacity_unit'] !== $unit
                    || (int) $material['is_active'] !== 1) {
                    throw new RuntimeException("Demo packaging {$code} changed; cleanup stopped");
                }
                $demoIds[$code] = (int) $material['id'];
            } else {
                $insertMaterial->execute([$code, $name, (int) $packageCategory,
                    $role, $form, $capacity, $unit]);
                $demoIds[$code] = (int) $db->lastInsertId();
                $counts['demo_packaging_materials']++;
            }
        }

        $productByCode->execute(['YOG-500']);
        $plainYogurt = $productByCode->fetch(PDO::FETCH_ASSOC);
        if (!$plainYogurt || $plainYogurt['base_name'] !== 'Plain Yogurt'
            || $plainYogurt['category'] !== 'yogurt'
            || (float) $plainYogurt['unit_size'] !== 250.0
            || $plainYogurt['unit_measure'] !== 'ml'
            || $plainYogurt['base_unit'] !== 'bottle') {
            throw new RuntimeException('Plain Yogurt SKU changed; cleanup stopped');
        }
        $activeBom->execute([(int) $plainYogurt['id']]);
        $plainBom = array_map('intval', $activeBom->fetchAll(PDO::FETCH_COLUMN));
        $permittedPlain = $db->query("SELECT id FROM ingredients WHERE ingredient_code IN
            ('TST-PKG-BTL-250', 'MOCK-PKG-CAP-28')")->fetchAll(PDO::FETCH_COLUMN);
        $permittedPlain[] = $demoIds['DEMO-LBL-YOG-250'];
        if (array_diff($plainBom, array_map('intval', $permittedPlain))) {
            throw new RuntimeException('Plain Yogurt has unreviewed packaging; cleanup stopped');
        }
        $activeBom->execute([(int) $butter250['id']]);
        $butterBom = array_map('intval', $activeBom->fetchAll(PDO::FETCH_COLUMN));
        if (array_diff($butterBom, [$demoIds['DEMO-WRAP-BUT-250']])
            || ($butter250['primary_container_id'] !== null
                && (int) $butter250['primary_container_id'] !== $demoIds['DEMO-WRAP-BUT-250'])) {
            throw new RuntimeException('Butter 250 g has unreviewed packaging; cleanup stopped');
        }
        $insertBom = $db->prepare("INSERT INTO sku_packaging_bom_items
            (product_id, ingredient_id, quantity_per_unit, waste_percent, unit, is_active)
            VALUES (?, ?, 1, 0, 'pcs', 1)
            ON DUPLICATE KEY UPDATE is_active = 1");
        $counts['demo_packaging_links'] = 0;
        foreach ([
            [(int) $plainYogurt['id'], $demoIds['DEMO-LBL-YOG-250']],
            [(int) $butter250['id'], $demoIds['DEMO-WRAP-BUT-250']],
        ] as [$productId, $ingredientId]) {
            $insertBom->execute([$productId, $ingredientId]);
            $counts['demo_packaging_links'] += $insertBom->rowCount();
        }
        $stmt = $db->prepare('UPDATE products SET primary_container_id = ?
            WHERE id = ? AND primary_container_id IS NULL');
        $stmt->execute([$demoIds['DEMO-WRAP-BUT-250'], (int) $butter250['id']]);
        $counts['butter_250_wrapper'] = $stmt->rowCount();

        if ($apply) {
            $db->commit();
        } else {
            $db->rollBack();
        }
        return ['applied' => $apply, 'changes' => $counts, 'quarantined_base_ids' => $ids];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
