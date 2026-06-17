<?php
// Standalone calc mirroring the actual API: getRequisitionRecipeItemsForPlan()
// Shows what the system returns for each recipe at different planned_quantities

$db = new PDO("mysql:host=127.0.0.1;dbname=highland_fresh;charset=utf8mb4", "root", "");

$sql = "
SELECT mr.id, mr.recipe_code, mr.product_name, mr.variant, mr.base_milk_liters, mr.expected_yield, mr.yield_unit,
       ri.ingredient_name, ri.quantity as recipe_qty, ri.unit,
       i.pack_size_value, i.pack_size_unit, i.pack_label
FROM master_recipes mr
JOIN recipe_ingredients ri ON ri.recipe_id = mr.id
LEFT JOIN ingredients i ON ri.ingredient_id = i.id
WHERE mr.is_active = 1
ORDER BY mr.id, ri.ingredient_name";

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$grouped = [];
foreach ($rows as $r) {
    $key = $r['recipe_code'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'name' => $r['product_name'] . ($r['variant'] ? " ({$r['variant']})" : ''),
            'base_milk' => (float)$r['base_milk_liters'],
            'expected_yield' => (float)$r['expected_yield'],
            'yield_unit' => $r['yield_unit'],
            'ings' => []
        ];
    }
    $grouped[$key]['ings'][] = [
        'name' => $r['ingredient_name'],
        'qty' => (float)$r['recipe_qty'],
        'unit' => $r['unit'],
        'pack_size' => $r['pack_size_value'] !== null ? (float)$r['pack_size_value'] : null,
        'pack_unit' => $r['pack_size_unit'],
        'pack_label' => $r['pack_label']
    ];
}

foreach ($grouped as $code => $g) {
    echo "\n=== {$code} — {$g['name']} ===\n";
    echo "Base recipe: {$g['base_milk']} L milk -> {$g['expected_yield']} {$g['yield_unit']}\n\n";
    foreach ($g['ings'] as $ing) {
        $packSize = $ing['pack_size'];
        echo "  {$ing['name']}: {$ing['qty']} {$ing['unit']}/batch";
        if ($packSize !== null) {
            echo "  (pack: 1 {$ing['pack_label']} = {$packSize} {$ing['pack_unit']})";
        }
        echo "\n";
        // Show scaling at various planned_quantities
        $pqs = [1, $g['expected_yield'], $g['expected_yield'] * 5, $g['expected_yield'] * 10, $g['expected_yield'] * 50, $g['expected_yield'] * 100];
        foreach ($pqs as $pq) {
            $scale = $g['expected_yield'] > 0 ? $pq / $g['expected_yield'] : 1;
            $base = round($ing['qty'] * $scale, 4);
            $packs = $packSize && $packSize > 0 ? (int) ceil($base / $packSize) : null;
            printf("    planned=%-6.0f  base=%.4f %-6s  packs=%s\n", $pq, $base, $ing['unit'], $packs ?? 'n/a');
        }
        echo "\n";
    }
}
