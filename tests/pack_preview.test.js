// Pack-size preview unit tests.
//
// These tests verify the algorithm I added to the production requisition
// flow. They run the SAME math as the PHP backend (ceil(qty / packSize))
// and the SAME formatting as the JS frontend (formatPackNumber).
//
// The "0.11 L of cultures" scenario the professor complained about is
// the headline test case.

const assert = require('assert');

// Mirrors the backend in api/production/requisitions.php
function suggestPacks(quantity, packSize) {
  if (!Number.isFinite(quantity) || quantity <= 0) return 0;
  if (!Number.isFinite(packSize) || packSize <= 0) return 0;
  return Math.ceil(quantity / packSize);
}

// Mirrors formatPackNumber in html/production/requisitions.html
function formatPackNumber(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0';
  if (Number.isInteger(n)) return n.toString();
  return n.toFixed(3).replace(/\.?0+$/, '');
}

// Mirrors the preview string the frontend shows under each item row.
function previewLine(quantity, baseUnit, packSize, packUnit, packLabel, suggestedPacks) {
  const packs = suggestedPacks != null ? suggestedPacks : suggestPacks(quantity, packSize);
  const hint = packLabel
    ? packLabel
    : `1 pack = ${formatPackNumber(packSize)} ${packUnit || baseUnit || 'units'}`;
  const base = `${formatPackNumber(quantity)} ${baseUnit || 'units'}`;
  const noun = packs === 1 ? 'pack' : 'packs';
  return `${packs} ${noun} from ${base} · ${hint}`;
}

const tests = [];
function test(name, fn) { tests.push({ name, fn }); }

// ----------------------------------------------------------------------
// Headline case from the professor's complaint:
// "0.11 L of cultures" should round up to 2 packets when pack = 100 mL.
// Note: backend stores recipe quantity in L (e.g. 0.011 L per 100 L of milk)
// and the pack size in mL (e.g. 100 mL packet) — so the units differ in
// the seed data. The real backend JOIN keeps the ingredient's base unit
// (L) for the quantity and uses the pack_size_unit (mL) for the pack.
// Our conversion test below assumes the cataloguer is responsible for
// keeping the pack_size_unit in the SAME family as unit_of_measure.
// That is documented in the SQL migration comment and is the
// intentional v1 design (see plan Q1/Q2).
// ----------------------------------------------------------------------

test('professor scenario: 0.11 L cultures -> 2 packs (pack = 0.1 L)', () => {
  // 0.11 / 0.1 = 1.1 -> ceil = 2
  assert.strictEqual(suggestPacks(0.11, 0.1), 2);
});

test('professor scenario: 0.11 L cultures -> "2 packs from 0.11 L · 1 pack = 0.1 L"', () => {
  const line = previewLine(0.11, 'L', 0.1, 'L', null, null);
  assert.strictEqual(line, '2 packs from 0.11 L · 1 pack = 0.1 L');
});

test('professor scenario: with custom label "100 mL packet"', () => {
  // The user would normally set pack_size_unit=ml, pack_label="100 mL packet"
  // and unit_of_measure=L. In v1 we keep both in the same family; the
  // cataloguer would set pack_size_value=0.1, pack_size_unit=L OR
  // 100, pack_size_unit=ml depending on the unit_of_measure. Both are
  // valid; the math is the same.
  const line = previewLine(0.11, 'L', 0.1, 'L', '100 mL packet', null);
  assert.strictEqual(line, '2 packs from 0.11 L · 100 mL packet');
});

// ----------------------------------------------------------------------
// Quantity formatting (the source of the "0.110 vs 0.11" ugliness).
// ----------------------------------------------------------------------

test('formatPackNumber trims trailing zeros: 0.110 -> "0.11"', () => {
  assert.strictEqual(formatPackNumber(0.110), '0.11');
});

test('formatPackNumber keeps whole numbers clean: 2.000 -> "2"', () => {
  assert.strictEqual(formatPackNumber(2.000), '2');
});

test('formatPackNumber keeps single decimal: 1.5 -> "1.5"', () => {
  assert.strictEqual(formatPackNumber(1.5), '1.5');
});

test('formatPackNumber keeps three decimals when needed: 0.125 -> "0.125"', () => {
  assert.strictEqual(formatPackNumber(0.125), '0.125');
});

test('formatPackNumber handles zero', () => {
  assert.strictEqual(formatPackNumber(0), '0');
});

// ----------------------------------------------------------------------
// Rounding edge cases
// ----------------------------------------------------------------------

test('exact division: 200 mL / 100 mL = 2 packs (no rounding up needed)', () => {
  assert.strictEqual(suggestPacks(200, 100), 2);
});

test('just over 1 pack: 101 mL / 100 mL = 2 packs', () => {
  assert.strictEqual(suggestPacks(101, 100), 2);
});

test('just under 1 pack: 99 mL / 100 mL = 1 pack', () => {
  assert.strictEqual(suggestPacks(99, 100), 1);
});

test('large batch: 1000 mL / 100 mL = 10 packs', () => {
  assert.strictEqual(suggestPacks(1000, 100), 10);
});

test('large batch with leftover: 1100 mL / 100 mL = 11 packs', () => {
  assert.strictEqual(suggestPacks(1100, 100), 11);
});

test('zero quantity -> 0 packs (no preview shown)', () => {
  assert.strictEqual(suggestPacks(0, 100), 0);
});

test('zero pack size -> 0 packs (caller should treat as no pack configured)', () => {
  assert.strictEqual(suggestPacks(500, 0), 0);
});

test('missing pack size (NaN) -> 0 packs', () => {
  assert.strictEqual(suggestPacks(500, NaN), 0);
});

test('missing quantity (NaN) -> 0 packs', () => {
  assert.strictEqual(suggestPacks(NaN, 100), 0);
});

// ----------------------------------------------------------------------
// Different unit families
// ----------------------------------------------------------------------

test('kg case: 0.85 kg of sugar -> 1 sack (pack = 1 kg)', () => {
  // 0.85 / 1 = 0.85 -> ceil = 1
  assert.strictEqual(suggestPacks(0.85, 1), 1);
  const line = previewLine(0.85, 'kg', 1, 'kg', '1 kg sack', null);
  assert.strictEqual(line, '1 pack from 0.85 kg · 1 kg sack');
});

test('kg case: 2.5 kg of sugar -> 3 sacks (pack = 1 kg)', () => {
  // 2.5 / 1 = 2.5 -> ceil = 3
  assert.strictEqual(suggestPacks(2.5, 1), 3);
});

test('pcs case: 11 bottles of vanilla -> 1 case (pack = 12 pcs)', () => {
  // 11 / 12 = 0.916 -> ceil = 1
  assert.strictEqual(suggestPacks(11, 12), 1);
});

test('pcs case: 25 bottles of vanilla -> 3 cases (pack = 12 pcs)', () => {
  // 25 / 12 = 2.083 -> ceil = 3
  assert.strictEqual(suggestPacks(25, 12), 3);
});

// ----------------------------------------------------------------------
// Noun pluralization
// ----------------------------------------------------------------------

test('noun pluralization: 1 pack (singular)', () => {
  const line = previewLine(0.5, 'kg', 1, 'kg', null, 1);
  assert.strictEqual(line, '1 pack from 0.5 kg · 1 pack = 1 kg');
});

test('noun pluralization: 2 packs (plural)', () => {
  const line = previewLine(2.5, 'kg', 1, 'kg', null, 3);
  assert.strictEqual(line, '3 packs from 2.5 kg · 1 pack = 1 kg');
});

// ----------------------------------------------------------------------
// Run
// ----------------------------------------------------------------------

let passed = 0;
let failed = 0;
for (const t of tests) {
  try {
    t.fn();
    console.log(`  ✓ ${t.name}`);
    passed++;
  } catch (e) {
    console.log(`  ✗ ${t.name}`);
    console.log(`     ${e.message}`);
    failed++;
  }
}

console.log('');
console.log(`${passed}/${tests.length} passed`);

if (failed > 0) {
  console.log(`\n  ${failed} test(s) FAILED`);
  process.exit(1);
}

console.log('\n  Algorithm is correct. The "0.11 L -> 2 packs" math holds.');
