const assert = require('assert');
const fs = require('fs');
const path = require('path');

const LookupNormalization = require('../js/utils/lookup-normalization.js');

assert.strictEqual(
    LookupNormalization.text('  Metro\t Gaisano\u00a0 '),
    'Metro Gaisano',
    'customer searches should trim and collapse pasted whitespace'
);
assert.strictEqual(
    LookupNormalization.barcode('  BATCH-20260907-0059\r\n'),
    'BATCH-20260907-0059',
    'scanner terminators should not become part of a barcode'
);
assert.strictEqual(
    LookupNormalization.barcode('PKG-\u200b123 '),
    'PKG-123',
    'invisible pasted characters should not break identifier lookup'
);
assert.strictEqual(
    LookupNormalization.matches(['DR-001', 'Metro Gaisano'], ' metro   gaisano '),
    true,
    'Delivery Receipts filtering should ignore accidental edge and repeated spaces'
);

const root = path.resolve(__dirname, '..');
const receipts = fs.readFileSync(path.join(root, 'html/warehouse/fg/delivery_receipts.html'), 'utf8');
const dispatch = fs.readFileSync(path.join(root, 'html/warehouse/fg/dispatch.html'), 'utf8');
const service = fs.readFileSync(path.join(root, 'js/warehouse/fg.service.js'), 'utf8');

assert.match(receipts, /LookupNormalization\.matches\(/, 'DR list must use normalized matching');
assert.match(receipts, /Barcode could not be resolved/, 'DR scanner must show an actionable lookup failure');
assert.match(receipts, /pickCurrentScan\?\.type === 'inventory_choices'/,
    'DR scanner must handle a production batch containing several packaged SKUs');
assert.match(receipts, /function pickScanInventoryChoice\(/,
    'DR scanner must let the picker resolve an ambiguous batch to its physical SKU');
assert.match(dispatch, /LookupNormalization\.barcode\(/, 'Dispatch scanner must use the same normalization');
assert.match(service, /barcode:\s*normalizedBarcode/, 'service boundary must send the normalized barcode');

console.log('Warehouse lookup normalization frontend tests passed.');
