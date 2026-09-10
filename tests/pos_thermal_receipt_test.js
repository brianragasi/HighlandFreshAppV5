const assert = require('assert');
const fs = require('fs');
const path = require('path');

const printer = require('../js/pos/receipt-printer.js');
const receipt = printer.buildSalesReceiptHtml({
    header: { company_name: 'Highland Fresh', document_type: 'SALES INVOICE', si_number: 'SI-TEST-1' },
    transaction: { customer_name: 'Walk-in Customer', payment_method: 'cash' },
    items: [{ product_name: 'Banana Smoothie', quantity: 11, unit_price: 50, line_total: 550 }],
    payment: { subtotal: 550, vatable_sales: 491.07, tax_amount: 58.93, vat_inclusive: true, total: 550, amount_tendered: 5000, change: 4450, method: 'cash' },
    footer: { cashier: 'Ana Reyes', date: '2026-09-08T20:49:00+08:00' }
});

assert.match(receipt, /@page \{ size: 80mm auto;/, 'receipt must target thermal paper');
assert.match(receipt, /Banana Smoothie/, 'receipt must include sold items');
assert.match(receipt, /Cash received[\s\S]*₱5,000\.00/, 'receipt must print the tendered cash');
assert.match(receipt, /CHANGE[\s\S]*₱4,450\.00/, 'receipt must print the actual change');
assert.match(receipt, /VATable Sales[\s\S]*₱491\.07/, 'receipt must disclose sales net of included VAT');
assert.match(receipt, /VAT \(12%, included\)[\s\S]*₱58\.93/, 'receipt must disclose the included 12% VAT amount');
assert.doesNotMatch(receipt, /Quick Sale \(POS\)/, 'receipt must not print the application shell');

const root = path.resolve(__dirname, '..');
const salePage = fs.readFileSync(path.join(root, 'html/pos/sale.html'), 'utf8');
const historyPage = fs.readFileSync(path.join(root, 'html/pos/history.html'), 'utf8');
const api = fs.readFileSync(path.join(root, 'api/pos/transactions.php'), 'utf8');

assert.match(salePage, /result\.change_amount \?\? result\.change/,
    'sale success must accept the API change field instead of falling back to zero');
assert.match(salePage, /POSReceiptPrinter\.printSalesReceipt/,
    'quick sale must print an isolated receipt');
assert.doesNotMatch(salePage, /function printReceipt\(\) \{[\s\S]{0,160}window\.print\(\)/,
    'quick sale must not print the whole POS page');
assert.match(historyPage, /POSService\.printReceipt\(selectedTransaction\.id\)/,
    'transaction history reprint must retrieve authoritative receipt data');
assert.match(api, /'change_amount' => \$changeAmount/,
    'create-sale response must expose a stable change_amount field');
assert.match(api, /'tax_amount' => \$taxAmount/,
    'create-sale response must expose the stored VAT amount');

console.log('POS thermal receipt tests passed.');
