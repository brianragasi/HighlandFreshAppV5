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
        'DEMO-ING-MELON-FLAVOR', 'DEMO-ING-CHEESE-CULTURE',
        'DEMO-PKG-GOUDA-250', 'DEMO-PKG-GOUDA-500',
        'DEMO-LBL-MEL-500', 'DEMO-LBL-MEL-1000',
        'DEMO-LBL-GOU-250', 'DEMO-LBL-GOU-500',
    ];

    $db->beginTransaction();
    try {
        $check = $db->prepare('SELECT name FROM base_products WHERE id = ? FOR UPDATE');
        foreach ($bases as $id => $name) {
            $check->execute([$id]);
            $actual = $check->fetchColumn();
            if ($actual !== $name) {
                throw new RuntimeException("Expected base product {$id} to be {$name}; found " . ($actual === false ? 'missing' : $actual));
            }
        }

        $ids = array_keys($bases);
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

        $customer = $db->query("SELECT name, status FROM customers WHERE id = 17 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        if (!$customer || $customer['name'] !== 'TestLang') {
            throw new RuntimeException('Expected test customer 17 was changed; cleanup stopped');
        }
        $references = (int) $db->query('SELECT COUNT(*) FROM sales_orders WHERE customer_id = 17')->fetchColumn();
        if ($references !== 0) {
            throw new RuntimeException('Test customer 17 has order history; cleanup stopped');
        }
        $counts['customers'] = $customer['status'] === 'active' ? 1 : 0;
        $db->exec("UPDATE customers SET status = 'inactive' WHERE id = 17 AND name = 'TestLang' AND status = 'active'");

        // These names identify the institution/store clearly; financial fields stay unchanged.
        $counts['customer_types'] = $db->exec("UPDATE customers SET customer_type = 'feeding_program'
            WHERE id = 6 AND name = 'DepEd Region X Feeding Program' AND customer_type = 'supermarket'");
        $counts['customer_types'] += $db->exec("UPDATE customers SET customer_type = 'supermarket'
            WHERE id = 16 AND name = 'Ororama Cogon' AND customer_type = 'institutional'");
        $counts['customer_types'] += $db->exec("UPDATE customers SET customer_type = 'supermarket'
            WHERE id = 1 AND name = 'SM Supermarket' AND customer_type = 'institutional'");
        $counts['customer_types'] += $db->exec("UPDATE customers SET customer_type = 'supermarket'
            WHERE id = 2 AND name = 'Robinson''s Supermarket' AND customer_type = 'institutional'");

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
              )");
        $ingredientUpdate->execute($ingredientCodes);
        $counts['ingredients'] = $ingredientUpdate->rowCount();

        $counts['ingredient_labels'] = $db->exec("UPDATE ingredients
            SET ingredient_name = 'Chocolate Powder'
            WHERE id = 11 AND ingredient_code = 'ING-003'
              AND ingredient_name = 'Chocolate Powder X'");

        // These packaging links point to another product's label or a bottle cap
        // on a butter pack. Leave the SKU setup visibly incomplete for review.
        $counts['packaging_links'] = $db->exec("UPDATE sku_packaging_bom_items b
            JOIN ingredients i ON i.id = b.ingredient_id
            SET b.is_active = 0
            WHERE b.is_active = 1 AND (
                (b.product_id = 4 AND i.ingredient_code = 'TST-LBL-FM0010')
                OR (b.product_id = 7 AND i.ingredient_code IN
                    ('MOCK-PKG-CAP-28', 'MOCK-PKG-LBL-CHO500'))
            )");

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
