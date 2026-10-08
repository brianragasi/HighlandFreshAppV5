const assert = require('node:assert/strict');
const { buildCsv } = require('../js/sales/aging-export');

const csv = buildCsv({
    asOf: '2026-10-08',
    filters: { search: 'Canteen', aging: 'pastdue' },
    bucketLabels: { pastdue: 'Any late balance' },
    typeLabels: { institutional: 'Institutional' },
    customers: [
        { id: 1, customer_code: '=HYPERLINK("x")', customer_name: 'Canteen, "North"',
            customer_type: 'institutional', current: 10.25, days_1_30: 5.5,
            days_31_60: 0, days_61_90: 0, over_90: 1.25, credit_limit: 15 },
        { id: 2, customer_code: 'CUS00002', customer_name: 'Canteen South',
            customer_type: 'institutional', current: 3, days_1_30: 0,
            days_31_60: 0, days_61_90: 0, over_90: 0, credit_limit: 0 }
    ]
});

assert.ok(csv.includes('"As of","2026-10-08"'));
assert.ok(csv.includes('"Focus","Any late balance"'));
assert.ok(csv.includes('"\'=HYPERLINK(""x"")"'), 'formula-like customer codes must be escaped');
assert.ok(csv.includes('"Canteen, ""North"""'), 'commas and quotes must survive CSV export');
assert.ok(csv.includes('"TOTAL","","",13.25,5.50,0.00,0.00,1.25,20.00'));
assert.ok(csv.includes('17.00,15.00,"Yes",2.00'));
assert.equal(csv.split('\r\n').filter(Boolean).length, 9);

const empty = buildCsv({ asOf: '2026-10-08', customers: [] });
assert.ok(empty.includes('"TOTAL","","",0.00,0.00,0.00,0.00,0.00,0.00'));
console.log('Aging CSV export tests passed');
