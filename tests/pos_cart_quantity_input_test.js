const assert = require('assert');
const fs = require('fs');
const path = require('path');

const page = fs.readFileSync(path.join(__dirname, '..', 'html/pos/sale.html'), 'utf8');

assert.match(page, /<input type="number" class="qty-value"/,
    'cart quantity must be directly editable from the keyboard');
assert.match(page, /min="1" max="\$\{item\.maxStock\}" step="1" inputmode="numeric"/,
    'quantity input must expose stock and integer constraints');
assert.match(page, /onchange="setCartQuantity\(\$\{index\}, this\.value, this\)"/,
    'typed quantities must update the cart');
assert.match(page, /oninput="previewCartQuantity\(\$\{index\}, this\.value, this\)"/,
    'multi-digit quantities must update live without waiting for blur');
assert.match(page, /function previewCartQuantity\(index, rawValue, input\)/,
    'live quantity updates must preserve the focused input');
assert.match(page, /id="cartQtyError\$\{index\}" aria-live="polite"/,
    'stock-limit errors must be explained beside the quantity field');
assert.match(page, /error\.textContent = parsed\.valid \? '' : parsed\.message/,
    'invalid live input must explain why the value was refused');
assert.match(page, /Re-rendering the cart here would[\s\S]*destroy the focused input/,
    'the implementation must guard against replacing the input after its first digit');
assert.match(page, /function setCartQuantity\(index, rawValue, input\)/,
    'cart must validate typed quantities centrally');
assert.match(page, /\^\\d\+\$\/\.test\(text\) && Number\.isSafeInteger\(quantity\)/,
    'decimal and malformed quantities must be rejected');
assert.match(page, /quantity > item\.maxStock/,
    'typed quantity must not exceed available inventory');
assert.match(page, /event\.key === 'Enter'/,
    'Enter must commit a typed quantity');
assert.match(page, /event\.key === 'Escape'/,
    'Escape must restore the saved cart quantity');
assert.match(page, /onclick="updateQuantity\(\$\{index\}, -1\)"/,
    'minus control must remain available for touch users');
assert.match(page, /onclick="updateQuantity\(\$\{index\}, 1\)"/,
    'plus control must remain available for touch users');

const scripts = [...page.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)]
    .map(match => match[1])
    .filter(code => code.trim());
scripts.forEach(code => new Function(code));

console.log('POS keyboard quantity input tests passed.');
