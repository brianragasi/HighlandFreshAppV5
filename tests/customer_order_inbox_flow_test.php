<?php

require_once __DIR__ . '/../api/helpers/customer_order_import.php';

function inboxAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

inboxAssert(hfCustomerOrderParseQuantity('20') === 20, 'Whole-number quantity should be accepted.');
inboxAssert(hfCustomerOrderParseQuantity('20.000') === 20, 'Spreadsheet whole numbers should be accepted.');
inboxAssert(hfCustomerOrderParseQuantity('2e10') === null, 'Scientific notation must be rejected.');
inboxAssert(hfCustomerOrderParseQuantity('-1') === null, 'Negative quantities must be rejected.');
inboxAssert(hfCustomerOrderParseQuantity('1000001') === null, 'Oversized quantities must be rejected.');

inboxAssert(hfCustomerOrderParseMoney('2800.00') === ['valid' => true, 'value' => 2800.0], 'Normal money should be accepted.');
inboxAssert(hfCustomerOrderParseMoney('2e10')['valid'] === false, 'Scientific-notation prices must be rejected.');
inboxAssert(hfCustomerOrderParseMoney('12.345')['valid'] === false, 'Prices with more than two decimals must be rejected.');

$boxPricing = hfCustomerOrderLinePricing([
    'quantity_base' => 20,
    'pieces_per_box' => 20,
    'base_unit' => 'block',
    'box_unit' => 'box',
    'system_unit_price' => 140,
    'raw_data' => json_encode([
        'entered_unit_price' => 2800,
        'price_unit' => 'box',
    ]),
]);
inboxAssert(abs($boxPricing['base_price'] - 140.0) < 0.0001, 'A 2,800 box price must become 140 per block.');
inboxAssert(abs($boxPricing['line_total'] - 2800.0) < 0.0001, 'One 20-block box priced at 2,800 must total 2,800.');

$nonDivisibleBoxPricing = hfCustomerOrderLinePricing([
    'quantity_base' => 12,
    'pieces_per_box' => 12,
    'base_unit' => 'piece',
    'box_unit' => 'box',
    'raw_data' => json_encode([
        'entered_unit_price' => 1000,
        'price_unit' => 'box',
    ]),
]);
inboxAssert(abs($nonDivisibleBoxPricing['line_total'] - 1000.0) < 0.0001, 'Box totals must stay exact even when the per-piece price repeats.');

$approved = ['customer_id' => 3, 'customer_po_number' => 'PO-1', 'delivery_date' => '2026-08-27', 'lines' => [['row_number' => 1, 'line' => ['quantity' => 1]]]];
$laterEdit = ['customer_id' => 3, 'customer_po_number' => 'PO-1', 'delivery_date' => '2026-08-27', 'lines' => [['row_number' => 1, 'line' => ['quantity' => 2]]]];
inboxAssert(hfManualSnapshotMatches($approved, $approved), 'An approval must match the exact saved order.');
inboxAssert(!hfManualSnapshotMatches($approved, $laterEdit), 'A later quantity change must require a new customer approval.');

echo "Customer order inbox flow checks passed.\n";
