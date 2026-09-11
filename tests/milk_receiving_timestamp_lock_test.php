<?php

function receivingTimestampAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$api = file_get_contents(dirname(__DIR__) . '/api/qc/deliveries.php');
$page = file_get_contents(dirname(__DIR__) . '/html/qc/milk_receiving.html');

receivingTimestampAssert(
    str_contains($api, "\$receivingDate = date('Y-m-d');")
        && str_contains($api, "\$receivingTime = date('H:i:s');")
        && !str_contains($api, "getParam('delivery_date'")
        && !str_contains($api, "getParam('delivery_time'"),
    'the API must assign and trust only the current server timestamp'
);
receivingTimestampAssert(
    preg_match('/id="deliveryDate"[^>]*disabled/', $page) === 1
        && preg_match('/id="deliveryTime"[^>]*disabled/', $page) === 1,
    'the receiving date and time controls must not be editable'
);
receivingTimestampAssert(
    !str_contains($page, "delivery_date: document.getElementById('deliveryDate').value")
        && !str_contains($page, "delivery_time: document.getElementById('deliveryTime').value"),
    'the browser must not submit a user-controlled receiving timestamp'
);
receivingTimestampAssert(
    str_contains($page, 'Date and time are automatic.')
        && str_contains($page, 'current server date and time'),
    'the interface must explain the automatic timestamp clearly'
);

echo "Milk-receiving server timestamp lock checks passed.\n";
