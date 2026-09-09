const assert = require('assert');

require('../js/utils/product-display.js');

const { ProductDisplay } = globalThis;

assert.strictEqual(
    ProductDisplay.name({ product_name: 'Special Ube Milk', unit_size: 250, unit_measure: 'ml' }),
    'Special Ube Milk — 250 mL'
);

assert.strictEqual(
    ProductDisplay.name({ product_name: 'Special Ube Milk', variant: '250ml', unit_size: 250, unit_measure: 'ml' }),
    'Special Ube Milk — 250 mL',
    'a size-only variant must not duplicate the canonical size'
);

assert.strictEqual(
    ProductDisplay.name({ product_name: 'Strawberry Smoothie', product_variant: '25ml', size_ml: 250, unit_measure: 'ml' }),
    'Strawberry Smoothie — 250 mL',
    'a legacy 250 -> 25ml snapshot must defer to the canonical package size'
);

assert.strictEqual(
    ProductDisplay.name({ product_name: 'Strawberry Smoothie', product_variant: '5ml', size_ml: 500, unit_measure: 'ml' }),
    'Strawberry Smoothie — 500 mL',
    'a legacy 500 -> 5ml snapshot must defer to the canonical package size'
);

assert.strictEqual(
    ProductDisplay.name({ product_code: 'UBE-500', product_name: 'Special Ube Milk', variant: 'Low Sugar', unit_size: 500, unit_measure: 'ml' }, { includeCode: true }),
    'UBE-500 — Special Ube Milk — Low Sugar · 500 mL'
);

assert.strictEqual(
    ProductDisplay.barcodeToken({ product_code: 'ube milk/250' }),
    'UBE-MILK-250'
);

assert.notStrictEqual(
    ProductDisplay.barcodeToken({ product_id: 12, unit_size: 250, unit_measure: 'ml' }),
    ProductDisplay.barcodeToken({ product_id: 13, unit_size: 500, unit_measure: 'ml' })
);

console.log('Product/SKU display tests passed.');
