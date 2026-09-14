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
assert.match(page, /grid-template-areas:\s*"identity quantity remove"\s*"total quantity remove"/,
    'cart rows must use a compact horizontal POS layout');
assert.match(page, /\.cart-item__name\s*\{[^}]*overflow-wrap:\s*anywhere;[^}]*-webkit-line-clamp:\s*2;/s,
    'selected product names must remain readable without making every row tall');
assert.match(page, /<dialog id="checkoutModal" class="modal">/,
    'payment controls must open as a separate checkout step');
assert.match(page, /id="btnProceedToPay"[\s\S]*onclick="openCheckout\(\)"/,
    'the cart footer must provide one clear payment action');
assert.match(page, /<details class="tax-details">/,
    'tax lines must stay collapsed until the cashier asks for them');
assert.match(page, /\.cart-scroll-shell\s*\{[^}]*flex:\s*1 1 auto;[^}]*min-height:\s*0;/s,
    'the cart list must receive the remaining vertical workspace');
assert.match(page, /has-more-below/,
    'cart overflow must provide a non-blocking visual continuation cue');
assert.doesNotMatch(page, /cart-scroll-cue/,
    'overflow cues must not cover cart rows or quantity controls');
assert.match(page, /touch-action:\s*pan-y/,
    'the cart must support intentional vertical touch scrolling');
assert.match(page, /\.pos-cart-panel\s*\{[^}]*flex:\s*0 0 clamp\(23rem, 37%, 28rem\);[^}]*width:\s*clamp\(23rem, 37%, 28rem\);/s,
    'desktop checkout must stay usable without swallowing the product catalog');
assert.match(page, /@media \(min-width: 1024px\) and \(max-height: 900px\)/,
    'common laptop heights must use compact checkout spacing');
assert.match(page, /\.qty-stepper\s*\{[^}]*grid-template-columns:\s*2\.75rem minmax\(2\.75rem, 1fr\) 2\.75rem;[^}]*width:\s*100%;/s,
    'quantity controls must remain compact and predictable');
assert.match(page, /\.qty-btn\s*\{[^}]*width:\s*2\.75rem;[^}]*height:\s*2\.75rem;/s,
    'quantity buttons must preserve 44px touch targets');
assert.match(page, /function productRemainingStock\(product\)/,
    'catalog stock must account for quantities already placed in the cart');
assert.match(page, /\$\{available\} remaining · \$\{inCart\} in cart/,
    'catalog badges must explain remaining and in-cart quantities');
assert.match(page, /function paymentIsValid\(\)/,
    'payment readiness must be computed before completing a sale');
assert.match(page, /btnCompleteSale'\)\.disabled = !paymentIsValid\(\)/,
    'complete-sale action must stay disabled until payment is valid');
assert.match(page, /id="changeDueTitle">Balance remaining/,
    'unpaid cash sales must start with balance-remaining language');
assert.doesNotMatch(page, /'flavored_milk':\s*'mug-hot'/,
    'bottled dairy products must not use a coffee-mug placeholder');
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
