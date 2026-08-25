<?php

$page = file_get_contents(__DIR__ . '/../html/qc/milk_receiving.html');
$api = file_get_contents(__DIR__ . '/../api/qc/milk_grading.php');
if ($page === false || $api === false) {
    fwrite(STDERR, "Unable to read the QC milk grading flow.\n");
    exit(1);
}

$checks = [
    'Fat content accepts an ordinary typed decimal instead of a native number editor' =>
        str_contains($page, 'type="text" inputmode="decimal" class="input input-bordered w-full" id="testFat"')
        && str_contains($page, 'data-label="Fat content"')
        && !str_contains($page, 'type="number" class="input input-bordered w-full" id="testFat"'),
    'All milk measurements use the same decimal-entry behavior' =>
        substr_count($page, 'data-qc-decimal') >= 13
        && str_contains($page, 'id="testAcidity"')
        && str_contains($page, 'id="testDensity"')
        && str_contains($page, 'id="testFreezingPoint"'),
    'Unfinished decimals are preserved until QC finishes typing' =>
        str_contains($page, 'function qcDecimalInputError(input)')
        && str_contains($page, 'Preserve unfinished entries such as `3.`')
        && str_contains($page, "input.addEventListener('blur'"),
    'Delivery and grading forms validate measurements before saving' =>
        substr_count($page, 'validateQcDecimalFields(document.getElementById(') >= 2,
    'Server retains authoritative plausibility limits' =>
        str_contains($api, "validateQcMeasurement(\$fatPercentage, 'fat_percentage', 'Fat percentage', 0, 10")
        && str_contains($api, "validateQcMeasurement(\$density, 'density', 'Specific gravity', 1, 1.1"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "QC milk decimal-entry tests passed.\n";
