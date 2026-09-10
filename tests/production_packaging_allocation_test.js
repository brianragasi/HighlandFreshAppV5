'use strict';

const assert = require('assert');
const allocation = require('../js/production/packaging-allocation.js');

const oneSize = allocation.distributeEvenlyByVolume(52630, [
    { key: 54, size_ml: 320 },
]);
assert.deepStrictEqual(
    oneSize.allocations.map(item => item.quantity),
    [164],
    'one selected 320 mL SKU should fill the available run volume automatically'
);
assert.strictEqual(oneSize.remainder_ml, 150);

const mixedSizes = allocation.distributeEvenlyByVolume(52630, [
    { key: 55, size_ml: 370 },
    { key: 54, size_ml: 320 },
]);
assert.deepStrictEqual(
    mixedSizes.allocations.map(item => item.quantity),
    [71, 82],
    'multiple selected SKUs should split the liquid volume without manual arithmetic'
);
assert.strictEqual(mixedSizes.used_ml, 52510);
assert.strictEqual(mixedSizes.remainder_ml, 120);

const invalidManualPlan = allocation.summarize(52630, [
    { size_ml: 370, quantity: 5 },
    { size_ml: 320, quantity: 164 },
]);
assert.strictEqual(invalidManualPlan.over_ml, 1700);
assert.strictEqual(invalidManualPlan.remaining_ml, 0);

console.log('Production packaging allocation tests passed.');
