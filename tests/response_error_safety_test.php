<?php

define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/../api/config/response.php';

$cases = [
    [
        'message' => "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'Basta' for key 'variant_name'",
        'code' => 409,
        'expected' => 'This record already exists or conflicts with existing data. Check the entered information and try again.'
    ],
    [
        'message' => "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'bad_field'",
        'code' => 500,
        'expected' => 'Something went wrong while processing the request. Please try again.'
    ],
    [
        'message' => 'The clearing date cannot be earlier than the check date',
        'code' => 400,
        'expected' => 'The clearing date cannot be earlier than the check date'
    ],
    [
        'message' => 'A packaging size of equivalent volume already exists for this product.',
        'code' => 409,
        'expected' => 'A packaging size of equivalent volume already exists for this product.'
    ]
];

$failures = [];
foreach ($cases as $index => $case) {
    $actual = Response::safeErrorMessage($case['message'], $case['code']);
    if ($actual !== $case['expected']) {
        $failures[] = sprintf(
            'Case %d failed. Expected "%s" but got "%s".',
            $index + 1,
            $case['expected'],
            $actual
        );
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Response error safety tests passed." . PHP_EOL;
