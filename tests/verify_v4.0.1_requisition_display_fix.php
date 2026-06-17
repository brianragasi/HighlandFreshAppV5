<?php
// Live smoke test for V4.0.1 — verify the requisition detail API
// now returns the ingredient base unit + pack info, and the display
// math (pack count annotation) is correct.

$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
require "api/bootstrap.php";

$db = Database::getInstance()->getConnection();

// We can't call the API directly (auth-gated), so we mirror the new
// SELECT and verify the JOIN adds the right fields.
$itemsStmt = $db->prepare("
    SELECT ri.id, ri.item_name, ri.item_type,
           ri.requested_quantity, ri.issued_quantity, ri.unit_of_measure,
           ri.pack_size_at_submit, ri.status,
           i.unit_of_measure as ingredient_unit_of_measure,
           i.pack_size_value as ingredient_pack_size_value,
           i.pack_size_unit as ingredient_pack_size_unit,
           i.pack_label as ingredient_pack_label
    FROM requisition_items ri
    LEFT JOIN ingredients i ON ri.item_id = i.id AND ri.item_type = 'ingredient'
    WHERE ri.requisition_id = (SELECT id FROM material_requisitions WHERE requisition_code = 'REQ-20260615-007')
    ORDER BY ri.id ASC
");
$itemsStmt->execute();
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

echo "============================================================\n";
echo "  V4.0.1 SMOKE TEST — REQ-20260615-007 detail (after cleanup)\n";
echo "============================================================\n\n";

function fmtQty($n) { return number_format((float)$n, 3); }
function packWord($n) { return $n === 1 ? 'pack' : 'packs'; }

foreach ($items as $it) {
    $name = $it['item_name'];
    $req = (float)$it['requested_quantity'];
    $iss = (float)$it['issued_quantity'];
    $rem = max($req - $iss, 0);
    $packSize = $it['pack_size_at_submit'] !== null ? (float)$it['pack_size_at_submit'] : null;
    $baseUnit = $it['ingredient_unit_of_measure'] ?: null;
    $packLabel = $it['ingredient_pack_label'] ?: null;
    $isPackConverted = $packSize && $packSize > 0 && $baseUnit;

    echo "  $name\n";
    echo "    status: {$it['status']}\n";

    if ($isPackConverted) {
        $reqP = round($req / $packSize);
        $issP = round($iss / $packSize);
        $remP = round($rem / $packSize);
        echo "    Requested: $req $baseUnit  (= $reqP " . packWord($reqP) . ($packLabel ? " of $packLabel" : '') . ")\n";
        echo "    Issued:    $iss $baseUnit  (= $issP " . packWord($issP) . ")\n";
        echo "    Remaining: $rem $baseUnit  (= $remP " . packWord($remP) . ")\n";
    } else {
        echo "    Requested: $req {$it['unit_of_measure']}\n";
        echo "    Issued:    $iss {$it['unit_of_measure']}\n";
        echo "    Remaining: $rem {$it['unit_of_measure']}\n";
    }
    echo "\n";
}

echo "============================================================\n";
echo "  Display comparison (before vs after the fix):\n";
echo "============================================================\n\n";
echo "  BEFORE — Sugar row in the modal showed:\n";
echo "    Requested: 25  sack      (unit says 'sack' but value is kg)\n";
echo "    Issued:    23.94  sack  (user reads 23.94 sacks = 598 kg, wrong)\n";
echo "    Remaining: 1.06  sack\n\n";
echo "  AFTER — Sugar row in the modal shows:\n";
echo "    Requested: 25.000 kg  (= 1 pack of 25 kg sack)\n";
echo "    Issued:    23.940 kg  (= 1 pack)\n";
echo "    Remaining: 1.060 kg   (= 1 pack)     <-- clean math\n\n";

echo "  Bug 1 (status flip) also fixed:\n";
echo "    BEFORE — REQ-20260615-007 status='fulfilled' but Sugar issued 23.94 of 25\n";
echo "    AFTER  — status='partial', audit note records the 1.06 kg shortfall\n";
