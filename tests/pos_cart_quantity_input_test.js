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
assert.match(page, /class="pos-app-shell[^"]*"/,
    'desktop POS must use a viewport-constrained application shell');
assert.match(page, /class="pos-sale-workspace[^"]*"/,
    'the product and cart workspace must have an independently constrained height');
assert.match(page, /class="pos-product-scroller[^"]*min-h-0[^"]*overflow-y-auto/,
    'the product catalog must scroll inside its own pane');
assert.match(page, /grid-template-areas:\s*"identity remove"\s*"quantity total"/,
    'cart rows must give the product identity a full row above the controls');
assert.match(page, /\.cart-item__name\s*\{[^}]*overflow-wrap:\s*anywhere;[^}]*white-space:\s*normal;/s,
    'selected product names must wrap instead of being clipped with an ellipsis');
assert.match(page, /\.pos-cart-footer\s*\{[^}]*max-height:\s*calc\(100% - 12rem\);[^}]*overflow-y:\s*auto;/s,
    'checkout must remain inside the viewport on short desktop screens');
assert.match(page, /#cartItems::\-webkit-scrollbar\s*\{\s*width:\s*12px;/,
    'the cart scrollbar must provide a practical pointer target');
assert.match(page, /scrollbar-gutter:\s*stable/,
    'cart content must keep a safety gap beside its scrollbar');
assert.match(page, /function acknowledgeCartChange\(productId, message\)/,
    'routine cart changes must use an inline row acknowledgement');
assert.match(page, /container\.replaceChildren\(\)/,
    'a new POS exception must replace an existing floating alert instead of stacking');
assert.match(page, /data-product-id="\$\{item\.id\}"/,
    'rendered cart rows must expose their product id for targeted feedback');
assert.doesNotMatch(page, /showToast\(`Added \$\{product\.name\}`/,
    'ordinary Retail additions must not create floating success notifications');
assert.match(page, /container\.scrollTop = Math\.max\(0, rowBottom - container\.clientHeight\)/,
    'the affected cart row must be revealed without scrolling the page');

const scripts = [...page.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)]
    .map(match => match[1])
    .filter(code => code.trim());
scripts.forEach(code => new Function(code));

console.log('POS keyboard quantity input tests passed.');
