<?php

require_once dirname(__DIR__) . '/api/helpers/finished_goods_barcode.php';

function qcLabelScanAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "QC label warehouse scan test failed: {$message}\n");
        exit(1);
    }
}

$batchCode = 'BATCH-20260909-0062';
$batchBarcode = 'BATCH-20260909-0062-260909';

$compact = hfParseCompactFinishedGoodsLabel("  HF4-62-11-0007\r\n");
qcLabelScanAssert(
    $compact === ['batch_id' => 62, 'product_id' => 11, 'sequence' => 7],
    'compact CODE128 payload must identify one production batch, SKU, and unit'
);
qcLabelScanAssert(
    hfParseCompactFinishedGoodsLabel('HF4-62-11-0') === null,
    'compact labels must reject a non-positive unit sequence'
);

qcLabelScanAssert(
    hfMatchSerializedQcLabel(
        $batchBarcode . '-PM0010-0001',
        $batchBarcode,
        $batchCode,
        'PM0010',
        59
    ) === 1,
    'a current QC label must resolve to its 250 mL SKU and unit sequence'
);
qcLabelScanAssert(
    hfMatchSerializedQcLabel(
        "  {$batchBarcode}-PM0011-0042\r\n",
        $batchBarcode,
        $batchCode,
        'PM0011',
        60
    ) === 42,
    'scanner whitespace and terminators must not break a valid serialized label'
);
qcLabelScanAssert(
    hfMatchSerializedQcLabel(
        $batchBarcode . '-UBE-250-0012',
        $batchBarcode,
        $batchCode,
        'ube/250',
        1
    ) === 12,
    'server SKU token normalization must match the QC JavaScript generator'
);
qcLabelScanAssert(
    hfMatchSerializedQcLabel(
        $batchBarcode . '-PM0011-0001',
        $batchBarcode,
        $batchCode,
        'PM0010',
        59
    ) === null,
    'a serialized label must never resolve to a different SKU in the same batch'
);
qcLabelScanAssert(
    hfMatchSerializedQcLabel($batchBarcode, $batchBarcode, $batchCode, 'PM0010', 59) === null,
    'an ordinary batch barcode is not a serialized unit label'
);

$dispatchSource = file_get_contents(dirname(__DIR__) . '/api/warehouse/fg/dispatch.php');
$labelSource = file_get_contents(dirname(__DIR__) . '/html/qc/print-labels.html');
$warehouseDispatchPage = file_get_contents(dirname(__DIR__) . '/html/warehouse/fg/dispatch.html');
$warehousePickingPage = file_get_contents(dirname(__DIR__) . '/html/warehouse/fg/delivery_receipts.html');
$productionRunsSource = file_get_contents(dirname(__DIR__) . '/api/production/runs.php');
qcLabelScanAssert(
    str_contains($dispatchSource, 'hfMatchSerializedQcLabel')
        && str_contains($dispatchSource, 'hfParseCompactFinishedGoodsLabel')
        && str_contains($dispatchSource, '$compactBatchId')
        && str_contains($dispatchSource, 'serialized_unit_number')
        && str_contains($dispatchSource, '$unitSequence > $printedUnitCount'),
    'Warehouse barcode lookup must invoke both compact and legacy QC label parsers'
);
qcLabelScanAssert(
    str_contains($labelSource, 'JsBarcode(svg, code')
        && str_contains($labelSource, 'return `HF4-${batchId}-${productId}`')
        && !str_contains($labelSource, 'new QRCode('),
    'QC must print compact CODE128 values instead of the former dense long value'
);
qcLabelScanAssert(
    str_contains($warehouseDispatchPage, 'qrbox: { width: 280, height: 140 }')
        && str_contains($warehousePickingPage, 'qrbox: { width: 280, height: 140 }')
        && str_contains($warehouseDispatchPage, 'Html5QrcodeSupportedFormats.QR_CODE')
        && str_contains($warehousePickingPage, 'Html5QrcodeSupportedFormats.QR_CODE'),
    'Warehouse phone scanners must use a horizontal CODE128 scan region'
);
qcLabelScanAssert(
    str_contains($warehouseDispatchPage, 'No code detected yet.')
        && str_contains($warehousePickingPage, 'No code detected yet.')
        && str_contains($warehouseDispatchPage, 'Camera permission was denied.')
        && str_contains($warehousePickingPage, 'Phone camera access requires HTTPS.'),
    'Warehouse scanners must explain detection, permission, and secure-context failures'
);
qcLabelScanAssert(
    str_contains($warehousePickingPage, 'window.location.href = `dispatch.html?pick=${encodeURIComponent(pickingTicketId)}`')
        && str_contains($warehousePickingPage, 'goToPickItems(pickId);')
        && !str_contains($warehousePickingPage, 'await openPickingModal(pickId);'),
    'Delivery Receipts must send picking tickets to the canonical Dispatch scanner'
);
qcLabelScanAssert(
    str_contains($productionRunsSource, 'abs($pSizeMl - round($pSizeMl)) < 0.000001')
        && !str_contains($productionRunsSource, 'number_format($pSizeMl, 0'),
    'production packaging snapshots must preserve significant zeroes in whole-number sizes'
);

echo "QC serialized labels are accepted by Warehouse FG lookup.\n";
