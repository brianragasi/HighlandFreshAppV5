<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$adminPage = file_get_contents($root . '/html/admin/ingredients.html');
$adminApi = file_get_contents($root . '/api/admin/ingredients.php');
$supplierPage = file_get_contents($root . '/html/admin/suppliers.html');

$checks = [
    'Archived materials are discoverable through the status filter' =>
        str_contains($adminPage, '<option value="0">Archived</option>'),
    'Active rows offer Archive while archived rows offer Restore' =>
        str_contains($adminPage, "Number(i.is_active) === 1")
        && str_contains($adminPage, 'onclick="deleteRecord(${i.id})"')
        && str_contains($adminPage, 'onclick="restoreRecord(${i.id})"'),
    'Restore reactivates the existing ingredient instead of creating a duplicate' =>
        str_contains($adminPage, 'async function restoreRecord(id)')
        && str_contains($adminPage, 'AdminService.updateIngredient(id, { is_active: 1 })'),
    'The ingredient update API accepts the active-state field' =>
        str_contains($adminApi, "'is_active'")
        && str_contains($adminApi, 'UPDATE ingredients SET '),
    'Supplier linking remains limited to active materials' =>
        str_contains($supplierPage, 'AdminService.getIngredients({ is_active: 1, limit: 100 })'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "Ingredient archive/restore checks failed.\n");
    exit(1);
}

echo "Ingredient archive/restore checks passed.\n";
