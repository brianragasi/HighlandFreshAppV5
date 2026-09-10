const fs = require('fs');
const path = require('path');
const picker = require('../js/ui/searchable-product-picker.js');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

const records = [
    { value: 'PM0007', label: 'WakoKabalo — 320 mL [72 printed pouches available]', group: 'Available now' },
    { value: 'BUT-250', label: 'Butter — 250 g [113 blocks available]', group: 'Available now' },
    { value: 'FMK-1L', label: 'Fresh Milk — 1 Liter [No usable stock]', group: 'Not currently available' }
].map(record => ({
    ...record,
    searchText: picker.normalizeSearchText(`${record.label} ${record.value} ${record.group}`)
}));

assert(picker.filterOptionRecords(records, 'wako 320').map(item => item.value).join() === 'PM0007', 'Name and size search failed.');
assert(picker.filterOptionRecords(records, 'BUT-250').map(item => item.value).join() === 'BUT-250', 'SKU-code search failed.');
assert(picker.filterOptionRecords(records, 'fresh 1l').map(item => item.value).join() === 'FMK-1L', 'Multi-term product search failed.');
assert(picker.filterOptionRecords(records, 'available').length === 3, 'Availability text should remain searchable.');
assert(picker.filterOptionRecords(records, 'missing').length === 0, 'Unknown text should return no products.');

const ordersPage = fs.readFileSync(path.join(__dirname, '../html/sales/orders.html'), 'utf8');
const inboxPage = fs.readFileSync(path.join(__dirname, '../html/sales/order_inbox.html'), 'utf8');
const pickerSource = fs.readFileSync(path.join(__dirname, '../js/ui/searchable-product-picker.js'), 'utf8');

assert(
    pickerSource.includes("select.closest('dialog') || document.body"),
    'Search results inside a native dialog must be mounted in that dialog so the browser top layer does not hide them.'
);
for (const [name, page] of [['direct orders', ordersPage], ['customer PO review', inboxPage]]) {
    assert(page.includes('searchable-product-picker.css'), `${name} page does not load the picker styles.`);
    assert(page.includes('searchable-product-picker.js'), `${name} page does not load the picker behavior.`);
    assert(page.includes('SearchableProductPicker.enhance'), `${name} page does not enhance its product selector.`);
    assert(page.includes('Search product name, SKU, or size...'), `${name} page lacks clear product-search guidance.`);
}

console.log('Sales searchable product picker checks passed.');
