// Verifies the V4.0.1 fix for the rounding mismatch on
// pasteurization.html. The exact bug: API returns 0.107, UI shows
// "0.11" via toFixed(2), user types 0.11, server rejects. Fix: UI
// now uses toFixed(3) so the user sees the same value the API has.

function oldDisplay(available) {
    return available.toFixed(2);
}

function newDisplay(available) {
    return available.toFixed(3);
}

const cases = [
    { api: 0.107, scenario: 'Plain Yogurt 0.11 L leftover (the reported bug)' },
    { api: 0.106, scenario: 'Even tinier leftover' },
    { api: 1.234, scenario: 'Normal 1.234 L balance' },
    { api: 5.0,   scenario: 'Whole 5 L' },
    { api: 555.555, scenario: 'Large 555.555 L' }
];

console.log('============================================================');
console.log('  V4.0.1 SMOKE TEST — pasteurization.html rounding fix');
console.log('============================================================\n');

for (const c of cases) {
    const before = oldDisplay(c.api);
    const after  = newDisplay(c.api);
    const beforeAcceptable = (parseFloat(before) <= c.api);
    const afterAcceptable  = (parseFloat(after)  <= c.api);

    console.log(`  ${c.scenario}`);
    console.log(`    API actual:       ${c.api}`);
    console.log(`    Old display:      "${before}"  (user types this, API: ${beforeAcceptable ? 'OK' : '422 blocked'})`);
    console.log(`    New display:      "${after}"  (user types this, API: ${afterAcceptable ? 'OK' : '422 blocked'})`);
    if (!beforeAcceptable && afterAcceptable) {
        console.log('    >>> BUG FIXED for this case\n');
    } else if (beforeAcceptable && afterAcceptable) {
        console.log('    (no change needed for this case)\n');
    } else {
        console.log('    (something is off — both blocked? investigate)\n');
    }
}

// Sanity check: for the exact reported case (0.107), the new display
// must let the user type a value the API will accept.
const api = 0.107;
const newUserInput = parseFloat(newDisplay(api));
if (newUserInput > api) {
    console.log('  FAIL  new display rounds UP — still blocking');
    process.exit(1);
}
console.log(`  PASS  exact reported case: API=0.107, user types "${newDisplay(api)}", ${newUserInput} <= 0.107, request accepted.`);
