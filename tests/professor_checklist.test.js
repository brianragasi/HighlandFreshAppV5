const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const { groups, items } = require('../js/professor-checklist-data.js');

test('Complete testing-plan registry without invented passes', () => {
    assert.equal(groups.length, 7);
    assert.equal(items.length, 30);
    assert.equal(new Set(items.map(item => item.id)).size, 30);
    assert.equal(items.filter(item => item.id.startsWith('ST-')).length, 17);
    assert.equal(items.filter(item => item.id.startsWith('IT-')).length, 3);
    assert.equal(items.filter(item => item.kind === 'auto').length, 7);
    items.forEach(item => {
        assert.ok(groups.some(group => group.id === item.group), item.id);
        assert.ok(!Object.hasOwn(item, 'status'), item.id);
        assert.ok(item.expected, item.id);
    });
});
test('Every demonstration link opens an existing operational screen', () => {
    items.forEach(item => {
        [item.page, item.extraPage].filter(Boolean).forEach(target => {
            assert.ok(target.startsWith('html/'));
            assert.ok(fs.existsSync(path.join(root, target.split('?')[0])), target);
        });
    });
});
test('Real evaluation and generic-plan differences remain explicit', () => {
    assert.equal(items.find(item => item.id === 'UAT-SURVEY').criteria.length, 17);
    assert.match(items.find(item => item.id === 'ST-102').note, /password field currently stays filled/);
    assert.match(items.find(item => item.id === 'IT-101').note, /201/);
    assert.match(items.find(item => item.id === 'UT-102').note, /12 characters/);
    assert.match(items.find(item => item.id === 'UAT-SIGNOFF').note, /Do not pre-fill/);
});
test('Export checks use the implemented sales PDF and CSV download buttons', () => {
    ['ST-402', 'ST-403'].forEach(id => assert.equal(items.find(item => item.id === id).page, 'html/sales/reports/sales.html'));
    assert.ok(items.find(item => item.id === 'ST-402').steps.includes('Wait for Ready. Click Export PDF.'));
    assert.ok(items.find(item => item.id === 'ST-403').steps.includes('Click Export CSV.'));
});
