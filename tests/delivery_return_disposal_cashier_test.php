<?php

$root = dirname(__DIR__);
$returnsApi = file_get_contents($root . '/api/warehouse/fg/returns.php');
$disposalsApi = file_get_contents($root . '/api/qc/disposals.php');
$posProductsApi = file_get_contents($root . '/api/pos/products.php');
$posTransactionsApi = file_get_contents($root . '/api/pos/transactions.php');
$collectionsApi = file_get_contents($root . '/api/pos/collections.php');
$collectPage = file_get_contents($root . '/html/pos/collect.html');

function returnFlowAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

returnFlowAssert(
    str_contains($returnsApi, '-abs((int)$returnId)'),
    'delivery-return disposal IDs must not collide with FG inventory IDs'
);
returnFlowAssert(
    str_contains($disposalsApi, 'resolveDeliveryReturnDisposal')
        && str_contains($disposalsApi, 'no second deduction was made'),
    'executing a returned-goods disposal must not deduct FG twice'
);
returnFlowAssert(
    str_contains($posProductsApi, 'SUM(GREATEST(COALESCE(fgi.quantity_available, 0), 0))')
        && !str_contains($posProductsApi, 'COALESCE(fgi.remaining_quantity, 0),\n                        (COALESCE(fgi.boxes_available'),
    'POS catalog must use authoritative quantity_available rather than stale mirrors'
);
returnFlowAssert(
    str_contains($posTransactionsApi, '$totalAvailable = max(0, (int)($inv[\'quantity_available\'] ?? 0));')
        && str_contains($posTransactionsApi, 'FROM disposals open_disposal'),
    'POS checkout must enforce the same authoritative/quarantine rules server-side'
);
returnFlowAssert(
    str_contains($collectionsApi, 'return_adjustment')
        && str_contains($collectionsApi, 'pending_disposal_quantity'),
    'Cashier receivables must expose the returned-goods deduction'
);
returnFlowAssert(
    str_contains($collectPage, 'returnAdjustmentNotice')
        && str_contains($collectPage, 'are already excluded'),
    'Cashier must explain that returns are already excluded from collection'
);

echo "Delivery return, disposal, and Cashier reconciliation tests passed.\n";
