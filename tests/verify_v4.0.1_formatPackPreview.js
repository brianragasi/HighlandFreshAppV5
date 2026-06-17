// Unit test for the V4.0.1 formatPackPreview changes.
// Runs the actual function from the HTML file against the scenarios
// the user reported (Plain Yogurt at planned_quantity=10, 100, 1000).

const fs = require('fs');
const html = fs.readFileSync('html/production/requisitions.html', 'utf8');

// Extract the inline script that contains the formatPackPreview function.
const m = html.match(/<script>\s*([\s\S]*?)\s*<\/script>/g);
const bigScript = m[m.length - 1].match(/<script>([\s\S]*?)<\/script>/)[1];

// Strip the top-level awaits (handlePlanSelectionChange) — they break in
// non-module context. We only need the function definitions, not the
// DOMContentLoaded handler.
const code = bigScript.replace(/document\.addEventListener\([\s\S]*?\}\);?/g, '');

// Pull just the function definitions we want to test, in isolation.
// Eval them in a controlled scope.
const sandbox = { escapeHtml: (v) => String(v || '').replace(/&/g, '&amp;'), formatPackNumber: (n) => {
    const x = Number(n);
    if (!Number.isFinite(x)) return '0';
    if (Number.isInteger(x)) return x.toString();
    return x.toFixed(3).replace(/\.?0+$/, '');
}};

const funcSrc = `
${code.match(/function escapeHtml[\s\S]*?\n\s{8}\}/)[0]}

function formatPackNumber(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return '0';
    if (Number.isInteger(n)) return n.toString();
    const fixed = n.toFixed(3);
    return fixed.replace(/\\.?0+$/, '');
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

${code.match(/function formatPackPreview[\s\S]*?\n\s{8}\}/)[0]}
`;

eval(funcSrc);

function assertContains(label, haystack, needle) {
    if (haystack.includes(needle)) {
        console.log('  PASS  ' + label);
    } else {
        console.log('  FAIL  ' + label);
        console.log('        expected to contain: ' + needle);
        console.log('        got: ' + haystack.replace(/\s+/g, ' ').slice(0, 300));
        process.exit(1);
    }
}

function assertNotContains(label, haystack, needle) {
    if (!haystack.includes(needle)) {
        console.log('  PASS  ' + label);
    } else {
        console.log('  FAIL  ' + label);
        console.log('        expected NOT to contain: ' + needle);
        process.exit(1);
    }
}

console.log('=== formatPackPreview V4.0.1 unit tests ===\n');

// Case 1: Plain Yogurt Sugar at planned_qty=1000 cups (after catalog walk)
//   base=27.78 kg, packs=2, pack_label="25 kg sack", pack_size=25, pack_size_unit="kg"
//   pack_total = 2*25 = 50 kg. 27.78 / 50 = 0.5556 < 0.99 -> SHOW annotation
let out = formatPackPreview({
    quantity: 2,
    baseUnit: 'kg',
    packSize: 25,
    packUnit: 'kg',
    packLabel: '25 kg sack',
    suggestedPacks: 2,
    baseQuantity: 27.778,
    baseUnit: 'kg'
});
console.log('Case 1 (Plain Yogurt Sugar @ 1000 cups, base 27.78 kg, 2 packs):');
console.log('  ' + out.replace(/\s+/g, ' ').trim().slice(0, 200));
assertContains('  contains pack count "2"', out, '>2</span> packs');
assertContains('  shows base qty annotation "27.778"', out, '27.778');
assertContains('  shows base unit "kg"', out, 'kg</span>');
assertContains('  shows pack label "25 kg sack"', out, '25 kg sack');
assertContains('  marks annotation as warning (text-warning)', out, 'text-warning');
console.log('');

// Case 2: Plain Yogurt Sugar at planned_qty=18000 (rounding tight)
//   base=500 kg, packs=20, pack_total=500. 500/500 = 1.0 >= 0.99 -> NO annotation
out = formatPackPreview({
    quantity: 20,
    baseUnit: 'kg',
    packSize: 25,
    packUnit: 'kg',
    packLabel: '25 kg sack',
    suggestedPacks: 20,
    baseQuantity: 500,
    baseUnit: 'kg'
});
console.log('Case 2 (Plain Yogurt Sugar @ 18000 cups, base 500 kg, 20 packs — tight rounding):');
console.log('  ' + out.replace(/\s+/g, ' ').trim().slice(0, 200));
assertContains('  contains pack count "20"', out, '>20</span> packs');
assertNotContains('  no base qty annotation (rounding tight)', out, 'need');
console.log('');

// Case 3: Plain Yogurt Cultures at planned_qty=10 cups
//   base=0.001 kg, packs=1, pack_total=1. 0.001/1 = 0.001 < 0.99 -> SHOW annotation
out = formatPackPreview({
    quantity: 1,
    baseUnit: 'kg',
    packSize: 1,
    packUnit: 'packet',
    packLabel: '1 foil packet',
    suggestedPacks: 1,
    baseQuantity: 0.001,
    baseUnit: 'kg'
});
console.log('Case 3 (Plain Yogurt Cultures @ 10 cups, base 0.001 kg, 1 pack):');
console.log('  ' + out.replace(/\s+/g, ' ').trim().slice(0, 200));
assertContains('  contains pack count "1"', out, '>1</span> pack ');
assertContains('  shows base qty annotation "0.001"', out, '0.001');
assertContains('  shows base unit "kg"', out, 'kg</span>');
assertContains('  shows pack label "1 foil packet"', out, '1 foil packet');
console.log('');

// Case 4: WITHOUT baseQuantity (unlocked row, user-typed qty)
//   Should NOT show annotation, even if base qty << pack
out = formatPackPreview({
    quantity: 1,
    baseUnit: 'kg',
    packSize: 1,
    packUnit: 'packet',
    packLabel: '1 foil packet',
    suggestedPacks: 1
    // no baseQuantity
});
console.log('Case 4 (unlocked row, no baseQuantity — no annotation expected):');
console.log('  ' + out.replace(/\s+/g, ' ').trim().slice(0, 200));
assertNotContains('  no "need X kg" annotation (unlocked row)', out, 'need');
assertContains('  still shows pack count and label', out, '1 foil packet');
console.log('');

// Case 5: Empty / invalid input
out = formatPackPreview({ quantity: 0, packSize: 25 });
assertContains('  Case 5a empty quantity returns ""', JSON.stringify(out), '""');
out = formatPackPreview({ quantity: 5, packSize: null });
assertContains('  Case 5b missing packSize returns ""', JSON.stringify(out), '""');
console.log('  Case 5: empty/invalid inputs return empty string  PASS');
console.log('');

console.log('All assertions passed.');
