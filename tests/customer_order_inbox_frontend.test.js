const fs = require('fs');
const path = require('path');

const pagePath = path.join(__dirname, '..', 'html', 'sales', 'order_inbox.html');
const html = fs.readFileSync(pagePath, 'utf8');
const helperPath = path.join(__dirname, '..', 'api', 'helpers', 'customer_order_import.php');
const helper = fs.readFileSync(helperPath, 'utf8');
const inlineScripts = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)]
    .map(match => match[1])
    .filter(script => script.trim());

inlineScripts.forEach(script => new Function(script));

const quantityNormalizerSource = html.match(/function wholeQuantityInputValue\(value\) \{[\s\S]*?\n        \}/)?.[0];
if (!quantityNormalizerSource) {
    throw new Error('Missing saved-quantity input normalizer.');
}
const wholeQuantityInputValue = new Function(`${quantityNormalizerSource}; return wholeQuantityInputValue;`)();
if (wholeQuantityInputValue('1.000') !== '1' || wholeQuantityInputValue('25.0') !== '25') {
    throw new Error('Saved whole-unit quantities must render without database decimal padding.');
}
if (wholeQuantityInputValue('1.5') !== '1.5') {
    throw new Error('The quantity normalizer must not silently round a non-whole value.');
}

const ids = [...html.matchAll(/\sid="([^"]+)"/g)].map(match => match[1]);
const duplicates = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
if (duplicates.length) {
    throw new Error(`Duplicate static element IDs: ${duplicates.join(', ')}`);
}

for (const expectedText of [
    'Order to create',
    'Requested items',
    'Review the original request',
    'Order summary',
    'Price is per',
    'Submit for GM Approval',
    '<span>Resolve issue</span>',
    "activeImport.status === 'needs_customer_confirmation'",
    'sourceDocumentReady',
    '<span>Source unavailable</span>',
    'activeImport.trusted_reference?.lines',
    'entryDirty = !saved || !activeImport.source_verified_at',
    'installNumericEntryGuards',
    'quantity: wholeQuantityInputValue(line.quantity_entered',
]) {
    if (!html.includes(expectedText)) {
        throw new Error(`Missing Customer PO guidance: ${expectedText}`);
    }
}

if (!helper.includes("'status' => 'pending'")) {
    throw new Error('Verified Customer PO orders must be returned as pending for GM approval.');
}

for (const removedText of [
    'Create Draft &amp; Continue',
    'Submit it for General Manager approval next',
    'Verify &amp; Save',
    'Create Order &amp; Send to GM',
    'Record Customer Decision',
    'id="statusFilter"',
]) {
    if (html.includes(removedText)) {
        throw new Error(`Customer PO review still exposes removed workflow text: ${removedText}`);
    }
}

console.log(`Customer PO page checks passed (${inlineScripts.length} inline script, ${ids.length} static IDs).`);
