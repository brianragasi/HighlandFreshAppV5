<?php
// Live smoke test for the V4.0.1 fix.
// Mirrors getRequisitionRecipeItemsForPlan() logic by reading the
// master_recipes + recipe_ingredients tables directly and applying
// the same math the API does.

$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
require "api/bootstrap.php";

$db = Database::getInstance()->getConnection();

function computeItemsForPlan(PDO $db, int $recipeId, float $plannedQuantity): array {
    $stmt = $db->prepare("SELECT id, recipe_code, product_name, expected_yield, yield_unit, base_milk_liters FROM master_recipes WHERE id = ? AND is_active = 1");
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$recipe) return [];

    $expectedYield = (float)($recipe['expected_yield'] ?? 0);
    $scale = $expectedYield > 0 ? max(0, $plannedQuantity) / $expectedYield : 1;
    $requiredMilk = round(((float)$recipe['base_milk_liters']) * $scale, 3);
    $items = [];

    if ($requiredMilk > 0) {
        $items[] = [
            'item_type' => 'raw_milk',
            'name' => 'Raw Milk',
            'base' => $requiredMilk,
            'unit' => 'liters',
            'pack_size' => null,
            'pack_label' => null
        ];
    }

    $stmt = $db->prepare("SELECT ri.ingredient_name, ri.quantity, ri.unit, i.pack_size_value, i.pack_size_unit, i.pack_label FROM recipe_ingredients ri LEFT JOIN ingredients i ON ri.ingredient_id = i.id WHERE ri.recipe_id = ? ORDER BY ri.ingredient_name");
    $stmt->execute([$recipeId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ing) {
        $base = round(((float)$ing['quantity']) * $scale, 3);
        if ($base <= 0) continue;
        $packSize = $ing['pack_size_value'] !== null ? (float)$ing['pack_size_value'] : null;
        $packCount = ($packSize && $packSize > 0) ? (int) ceil($base / $packSize) : null;
        $items[] = [
            'item_type' => 'ingredient',
            'name' => $ing['ingredient_name'],
            'base' => $base,
            'unit' => $ing['unit'],
            'pack_size' => $packSize,
            'pack_count' => $packCount,
            'pack_label' => $ing['pack_label']
        ];
    }
    return $items;
}

echo "============================================================\n";
echo "  V4.0.1 SMOKE TEST — Plain Yogurt (recipe_id=15)\n";
echo "  Base recipe: 100 L milk -> 180 cups\n";
echo "============================================================\n\n";
echo "Recipe ingredients AFTER catalog walk:\n";
$stmt = $db->prepare("SELECT ri.ingredient_name, ri.quantity as recipe_qty, ri.unit, i.pack_size_value, i.pack_size_unit, i.pack_label FROM recipe_ingredients ri LEFT JOIN ingredients i ON ri.ingredient_id = i.id WHERE ri.recipe_id = 15");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("  %-22s recipe_qty=%.3f %-6s | pack=%.1f %-6s | %s\n",
        $r["ingredient_name"], $r["recipe_qty"], $r["unit"],
        $r["pack_size_value"], $r["pack_size_unit"], $r["pack_label"]
    );
}

echo "\n=== API math at each planned_quantity ===\n\n";
foreach ([10, 100, 1000, 5000, 18000] as $pq) {
    $items = computeItemsForPlan($db, 15, $pq);
    $scale = 180 > 0 ? $pq / 180 : 1;
    echo "planned_qty=$pq cups (scale=" . number_format($scale, 3) . "x)\n";
    foreach ($items as $item) {
        if ($item['item_type'] === 'raw_milk') {
            printf("  %-22s %.2f %-6s   (no pack rounding)\n",
                $item['name'], $item['base'], $item['unit']
            );
            continue;
        }
        $packTotal = $item['pack_count'] * (float)$item['pack_size'];
        $base = (float)$item['base'];
        $showAnnotation = $base < $packTotal * 0.99;

        printf("  %-22s base=%.4f %-6s | packs=%-2d  | %s\n",
            $item['name'], $base, $item['unit'], $item['pack_count'], $item['pack_label'] ?? "-"
        );

        if ($showAnnotation) {
            printf("      UI lock:  = %d pack \xC2\xB7 need %.3f %s \xC2\xB7 %s\n",
                $item['pack_count'], $base, $item['unit'], $item['pack_label'] ?? ""
            );
        } else {
            printf("      UI lock:  = %d pack \xC2\xB7 %s   (rounding tight)\n",
                $item['pack_count'], $item['pack_label'] ?? ""
            );
        }
    }
    echo "\n";
}
