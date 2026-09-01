<?php

$root = dirname(__DIR__);
$api = file_get_contents($root . '/api/qc/disposals.php');
$page = file_get_contents($root . '/html/qc/disposals.html');

if ($api === false || $page === false) {
    fwrite(STDERR, "Unable to load disposal sources.\n");
    exit(1);
}

require_once $root . '/api/helpers/finished_goods_disposal.php';

function disposalBalanceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// Regression for BATCH-20260203-002: stale pack mirrors said 99, while the
// authoritative on-hand ledger correctly said zero after its completed disposal.
$staleDisposedRow = [
    'quantity_available' => 0,
    'remaining_quantity' => 1,
    'boxes_available' => 4,
    'pieces_available' => 3,
    'pieces_per_box' => 24,
];
disposalBalanceAssert(
    fgDisposalAvailableBaseUnits($staleDisposedRow) === 0,
    'stale box and loose-piece mirrors must not resurrect disposed stock'
);

disposalBalanceAssert(
    fgDisposalAvailableBaseUnits(['quantity_available' => 99, 'remaining_quantity' => 99]) === 99,
    'a current on-hand balance must remain selectable'
);

disposalBalanceAssert(
    fgDisposalSplitBaseUnits(25, 24) === ['boxes' => 1, 'pieces' => 1],
    'remaining stock must be written back as synchronized packs and loose pieces'
);

disposalBalanceAssert(
    str_contains($api, "WHERE COALESCE(fgi.quantity_available, 0) > 0")
        && str_contains($api, "fgi.status IN ('available', 'low_stock', 'expired')"),
    'the source list must exclude depleted and unavailable finished-goods rows'
);

disposalBalanceAssert(
    substr_count($api, 'fgDisposalAvailableBaseUnits') >= 2,
    'submission and execution must use the same finished-goods balance rule'
);

disposalBalanceAssert(
    !str_contains($api, "((int)(\$source['boxes_available'] ?? 0) * \$piecesPerBox)"),
    'execution must not prefer stale packaging mirrors over the on-hand ledger'
);

disposalBalanceAssert(
    str_contains($page, 'qtyField.value = source.available_quantity;'),
    'changing the selected item must refresh the quantity field'
);

echo "Finished-goods disposal balance tests passed.\n";
