const fs = require('fs');
const path = require('path');

const pagePath = path.join(__dirname, '..', 'html', 'sales', 'order_inbox.html');
const html = fs.readFileSync(pagePath, 'utf8');
const inlineScripts = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)]
    .map(match => match[1])
    .filter(script => script.trim());

inlineScripts.forEach(script => new Function(script));

const ids = [...html.matchAll(/\sid="([^"]+)"/g)].map(match => match[1]);
const duplicates = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
if (duplicates.length) {
    throw new Error(`Duplicate static element IDs: ${duplicates.join(', ')}`);
}

for (const expectedText of [
    'Identify the request',
    'Enter the requested items',
    'Compare with the customer request',
    'Review what will be created',
    'Price is per',
    'Create Draft &amp; Continue',
    'Record Clarifying Call',
    'installNumericEntryGuards',
]) {
    if (!html.includes(expectedText)) {
        throw new Error(`Missing Customer PO guidance: ${expectedText}`);
    }
}

console.log(`Customer PO page checks passed (${inlineScripts.length} inline script, ${ids.length} static IDs).`);
