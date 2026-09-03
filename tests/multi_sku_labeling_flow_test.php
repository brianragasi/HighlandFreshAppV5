<?php

$root = dirname(__DIR__);
$checks = [
    'QC API exposes effective released packaging lines' => [
        'file' => $root . '/api/qc/batch_release.php',
        'needles' => ["\$batch['released_packaging_lines'] = \$effectiveLines;"],
    ],
    'QC packaging lines carry product code and size identity' => [
        'file' => $root . '/api/helpers/qc_count_discrepancy.php',
        'needles' => ['p.product_code', 'COALESCE(pri.size_ml, p.unit_size) AS size_ml'],
    ],
    'label screen requires SKU selection and SKU-aware serials' => [
        'file' => $root . '/html/qc/print-labels.html',
        'needles' => ['id="skuSelect"', 'currentSku', 'ProductDisplay.barcodeToken(sku)', 'ProductDisplay.name(sku)'],
    ],
    'Finished Goods displays canonical SKU names' => [
        'file' => $root . '/html/warehouse/fg/inventory.html',
        'needles' => ['ProductDisplay.name(item)', 'product-display.js'],
    ],
    'Sales order entry displays canonical SKU names' => [
        'file' => $root . '/html/sales/orders.html',
        'needles' => ['ProductDisplay.name(product, { includeCode: true })', 'ProductDisplay.name(item)'],
    ],
    'legacy FG receiving refuses ambiguous SKU matching' => [
        'file' => $root . '/api/warehouse/fg/inventory.php',
        'needles' => ["count(\$matches) !== 1", 'Link the packaging line to a product SKU'],
    ],
];

$failures = [];
foreach ($checks as $label => $check) {
    $contents = file_get_contents($check['file']);
    foreach ($check['needles'] as $needle) {
        if ($contents === false || !str_contains($contents, $needle)) {
            $failures[] = "{$label}: missing {$needle}";
        }
    }
}

if ($failures) {
    fwrite(STDERR, "Multi-SKU labeling checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Multi-SKU labeling flow tests passed.\n";
