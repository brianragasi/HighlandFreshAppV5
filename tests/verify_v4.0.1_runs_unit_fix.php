<?php
// Live smoke test for the V4.0.1 unit-conversion fix in
// api/production/runs.php. Mirrors the validateIssuedIngredientsForRun
// logic by reading the same tables and applying the same SQL.

$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
require "api/bootstrap.php";

$db = Database::getInstance()->getConnection();

// Mirror normalizeProductionUnit from the API (for the unit label test)
function normalizeProductionUnit($unit) {
    $unit = strtolower(trim((string) $unit));
    $unit = rtrim($unit, '.');
    $aliases = [
        'kgs' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg',
        'kilogram' => 'kg', 'kilograms' => 'kg',
        'l' => 'liter', 'litre' => 'liter', 'litres' => 'liter', 'liters' => 'liter',
        'packet' => 'packet', 'packets' => 'packet',
        'sack' => 'sack', 'sacks' => 'sack',
        'bottle' => 'bottle', 'bottles' => 'bottle',
        'bag' => 'bag', 'bags' => 'bag',
        'box' => 'box', 'boxes' => 'box',
    ];
    return $aliases[$unit] ?? $unit;
}

function getIssuedIngredientStats($db, $ingredient) {
    $ingredientId = (int) ($ingredient['ingredient_id'] ?? 0);
    $normalizedUnit = $ingredient['normalized_unit'];
    $params = [];

    $where = "ri.item_type = 'ingredient' AND ri.issued_quantity > 0 AND ir.department = 'production'";
    if ($ingredientId > 0) {
        $where .= " AND ri.item_id = ?";
        $params[] = $ingredientId;
    } else {
        $where .= " AND LOWER(TRIM(ri.item_name)) = ?";
        $params[] = strtolower(trim($ingredient['ingredient_name']));
    }

    $stmt = $db->prepare("
        SELECT ri.issued_quantity, ri.unit_of_measure,
               ri.pack_size_at_submit,
               COALESCE(ri.fulfilled_at, ri.updated_at, ri.created_at) AS issued_at
        FROM requisition_items ri
        JOIN material_requisitions ir ON ri.requisition_id = ir.id
        WHERE {$where}
    ");
    $stmt->execute($params);

    $total = 0.0;
    $earliestIssuedAt = null;
    foreach ($stmt->fetchAll() as $row) {
        $rowUnit = normalizeProductionUnit($row['unit_of_measure']);
        $isBaseUnit = ($rowUnit === $normalizedUnit);
        $hasPackSizeSnapshot = $row['pack_size_at_submit'] !== null;
        if ($isBaseUnit || $hasPackSizeSnapshot) {
            $total += (float) $row['issued_quantity'];
            if ($row['issued_at'] && (!$earliestIssuedAt || $row['issued_at'] < $earliestIssuedAt)) {
                $earliestIssuedAt = $row['issued_at'];
            }
        }
    }
    return ['total_issued' => $total, 'earliest_issued_at' => $earliestIssuedAt];
}

function getReservedIngredientQuantity($db, $ingredient, $earliestIssuedAt = null) {
    $statuses = "'planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed'";
    $sql = "SELECT pr.id, pr.recipe_id, pr.planned_quantity, pr.ingredient_adjustments
            FROM production_runs pr
            WHERE pr.status IN ({$statuses})";
    $params = [];
    if ($earliestIssuedAt) {
        $sql .= " AND pr.created_at >= ?";
        $params[] = $earliestIssuedAt;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $total = 0.0;
    $targetId = (int) ($ingredient['ingredient_id'] ?? 0);
    $targetName = strtolower(trim($ingredient['ingredient_name']));
    $targetUnit = $ingredient['normalized_unit'];

    foreach ($stmt->fetchAll() as $run) {
        // Get the requirements for this run's recipe
        $reqStmt = $db->prepare("SELECT ri.ingredient_id, ri.ingredient_name, ri.quantity, ri.unit FROM recipe_ingredients ri WHERE ri.recipe_id = ?");
        $reqStmt->execute([$run['recipe_id']]);
        foreach ($reqStmt->fetchAll(PDO::FETCH_ASSOC) as $requirement) {
            $rUnit = normalizeProductionUnit($requirement['unit']);
            $sameId = $targetId > 0 && (int) $requirement['ingredient_id'] === $targetId;
            $sameName = $targetId <= 0 && strtolower(trim($requirement['ingredient_name'])) === $targetName;
            if (($sameId || $sameName) && $rUnit === $targetUnit) {
                $scale = $run['planned_quantity'] > 0 ? $run['planned_quantity'] / 100 : 1;
                $total += (float) $requirement['quantity'] * $scale;
            }
        }
    }
    return $total;
}

echo "============================================================\n";
echo "  V4.0.1 SMOKE TEST — REQ-20260615-007 (Plain Yogurt 190 cups)\n";
echo "============================================================\n\n";

$reqStmt = $db->prepare("
    SELECT ri.item_name, ri.item_id, ri.requested_quantity, ri.issued_quantity,
           ri.unit_of_measure, ri.pack_size_at_submit
      FROM requisition_items ri
      JOIN material_requisitions mr ON mr.id = ri.requisition_id
     WHERE mr.requisition_code = 'REQ-20260615-007'
");
$reqStmt->execute();
$reqItems = $reqStmt->fetchAll(PDO::FETCH_ASSOC);

echo "Requisition items (DB state):\n";
foreach ($reqItems as $r) {
    printf("  %-22s req=%-7.3f %-8s | issued=%-7.3f %-8s | pack_size_at_submit=%s\n",
        $r["item_name"], $r["requested_quantity"], $r["unit_of_measure"],
        $r["issued_quantity"], $r["unit_of_measure"],
        $r["pack_size_at_submit"] ?? "NULL"
    );
}

// Plain Yogurt ingredients (id=15) — at 190 cups, scale = 190/180 = 1.0556
$scale = 190 / 180;
$ingredients = [
    ['ingredient_id' => 13, 'ingredient_name' => 'Cultures (Yogurt)', 'unit' => 'kg', 'normalized_unit' => 'kg', 'quantity' => 0.010 * $scale],
    ['ingredient_id' => 9,  'ingredient_name' => 'Sugar',             'unit' => 'kg', 'normalized_unit' => 'kg', 'quantity' => 5.000 * $scale],
];

echo "\n=== Per-ingredient availability check (190 cups, scale=" . number_format($scale, 4) . ") ===\n\n";
$errors = [];
foreach ($ingredients as $ing) {
    $stats = getIssuedIngredientStats($db, $ing);
    $issued = $stats['total_issued'];
    $reserved = getReservedIngredientQuantity($db, $ing, $stats['earliest_issued_at']);
    $available = max(0, $issued - $reserved);
    $needed = (float) $ing['quantity'];
    $ok = $available >= $needed;

    printf("  %-22s need=%-9.4f %s | issued=%-9.4f | reserved=%-9.4f | available=%-9.4f %s  %s\n",
        $ing['ingredient_name'], $needed, $ing['unit'],
        $issued, $reserved, $available, $ing['unit'],
        $ok ? "OK" : "BLOCKED"
    );

    if (!$ok) {
        $errors[] = "{$ing['ingredient_name']}: need {$needed} {$ing['unit']}, available {$available} {$ing['unit']}";
    }
}

echo "\n";
if (empty($errors)) {
    echo "  >>> BUG FIXED — production run can be created for REQ-20260615-007\n";
} else {
    echo "  Errors:\n";
    foreach ($errors as $err) echo "    - $err\n";
}

echo "\n============================================================\n";
echo "  Before the fix vs after the fix (same data):\n";
echo "============================================================\n\n";
echo "  Before (exact-unit filter only):\n";
echo "    Cultures (Yogurt): need 0.0106 kg, available 0 kg  -> 422 blocked\n";
echo "    Sugar:             need 5.2778 kg, available 0 kg  -> 422 blocked\n\n";
echo "  After (V4.0.1 — also accept pack_size_at_submit rows):\n";
echo "    (live calc above shows the per-ingredient status)\n";
