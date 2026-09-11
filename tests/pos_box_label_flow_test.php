<?php

require_once dirname(__DIR__) . '/api/helpers/finished_goods_barcode.php';

function boxLabelAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "POS box-label flow test failed: {$message}\n");
        exit(1);
    }
}

$code = hfBuildCompactFinishedGoodsBoxLabel(70, 56, 20, 3);
boxLabelAssert($code === 'HFB-70-56-20-0003', 'outside-box codes must be compact and visibly different from individual labels');

$parsed = hfParseCompactFinishedGoodsBoxLabel("  hfb-70-56-20-0003\r\n");
boxLabelAssert(
    $parsed === [
        'label_code' => 'HFB-70-56-20-0003',
        'batch_id' => 70,
        'product_id' => 56,
        'units_per_pack' => 20,
        'sequence' => 3,
    ],
    'scanner whitespace must be removed and the exact batch, product, and box number retained'
);
boxLabelAssert(
    hfParseCompactFinishedGoodsBoxLabel('HF4-70-56-0003') === null,
    'an individual product label must never be accepted as an outside-box label'
);

$productsApi = file_get_contents(dirname(__DIR__) . '/api/pos/products.php');
$transactionsApi = file_get_contents(dirname(__DIR__) . '/api/pos/transactions.php');
$wholesaleHelper = file_get_contents(dirname(__DIR__) . '/api/helpers/pos_wholesale.php');
$salePage = file_get_contents(dirname(__DIR__) . '/html/pos/sale.html');
$posService = file_get_contents(dirname(__DIR__) . '/js/pos/pos.service.js');
$labelPage = file_get_contents(dirname(__DIR__) . '/html/qc/print-labels.html');

boxLabelAssert(
    str_contains($productsApi, "'scan_type' =") === false
        && str_contains($productsApi, "['scan_type'] = 'wholesale_box'")
        && str_contains($productsApi, 'hfParseCompactFinishedGoodsBoxLabel'),
    'Cashier lookup must recognize the outside-box format'
);
boxLabelAssert(
    str_contains($transactionsApi, 'resolveWholesaleBoxLabels')
        && str_contains($transactionsApi, 'pos_sold_box_labels')
        && str_contains($transactionsApi, 'pos_opened_box_labels')
        && str_contains($transactionsApi, 'No loose Retail stock')
        && str_contains($transactionsApi, '$inventoryId'),
    'checkout must prevent reuse and deduct from the scanned inventory row'
);
boxLabelAssert(
    str_contains($salePage, 'handleProductSearchKey(event)')
        && str_contains($salePage, 'box_labels: (item.boxLabels || []).map')
        && str_contains($salePage, 'Scan the label on the outside')
        && str_contains($salePage, 'openPosCameraScanner()')
        && str_contains($salePage, 'html5-qrcode.min.js')
        && str_contains($salePage, "classList.toggle('hidden', !wholesale)")
        && str_contains($salePage, 'scanPosBarcodePhoto(event)')
        && str_contains($salePage, 'Open a box for Retail')
        && str_contains($posService, 'openBoxForRetail'),
    'Wholesale Cashier must provide the same visible camera scan flow as Finished Goods'
);
boxLabelAssert(
    str_contains($wholesaleHelper, 'pos_opened_box_labels')
        && str_contains($productsApi, 'retail_available')
        && str_contains($productsApi, 'total_stock_changed')
        && str_contains($productsApi, "action !== 'open_box'"),
    'opening a labeled box must preserve total stock and separate sealed boxes from loose Retail items'
);
boxLabelAssert(
    str_contains($labelPage, 'Outside box labels')
        && str_contains($labelPage, 'HFB-${batchId}-${productId}-${getUnitsPerPack(sku)}')
        && str_contains($labelPage, 'Place one label on each sealed box'),
    'QC must be able to print one outside label per complete box'
);

echo "POS outside-box label flow tests passed.\n";
