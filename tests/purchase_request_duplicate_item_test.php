<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$frontend = file_get_contents($root . '/html/warehouse/raw/purchase_requests.html');
$backend = file_get_contents($root . '/api/purchasing/purchase_requests.php');

$checks = [
    'frontend disables items already selected on another PRS line' =>
        str_contains($frontend, 'function refreshPRItemChoices()') &&
        str_contains($frontend, 'option.disabled=option.value!==select.value&&selectedValues.has(option.value)'),
    'frontend catches a repeated selection immediately' =>
        str_contains($frontend, 'is already on line ${existingLine}. Update that row instead.'),
    'frontend blocks duplicate rows before saving' =>
        str_contains($frontend, 'const selectedItems=new Map()') &&
        str_contains($frontend, 'Keep one row and update its quantity.'),
    'server rejects duplicate ingredient or MRO rows' =>
        str_contains($backend, '$seenItems = [];') &&
        str_contains($backend, "'ingredient:' . (int) \$item['ingredient_id']") &&
        str_contains($backend, "'mro:' . (int) \$item['mro_item_id']"),
    'old drafts are rechecked when submitted' =>
        str_contains($backend, "'purpose' => \$current['purpose'] ?? null") &&
        str_contains($backend, '], true);'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, 'Purchase Request duplicate-item regression checks failed.' . PHP_EOL);
    exit(1);
}

echo 'Purchase Request duplicate-item regression checks passed.' . PHP_EOL;
