'use strict';

const assert = require('node:assert/strict');
const {
    escapeHtml,
    renderToastContent,
    normalizePhilippinePhoneInput,
    numberInputAllowsNegative,
    numberInputExceedsDeclaredMaximum,
    plainNumberTextIsWithinDigitLimit,
    personNameHasLetter,
    normalizeBankAccountNumber
} = require('../js/config/api.js');

assert.equal(
    escapeHtml('<img src=x onerror="alert(1)"> Tom & Jerry'),
    '&lt;img src=x onerror=&quot;alert(1)&quot;&gt; Tom &amp; Jerry'
);

global.document = {
    createElement(tagName) {
        return {
            tagName,
            children: [],
            className: '',
            textContent: '',
            attributes: {},
            setAttribute(name, value) {
                this.attributes[name] = value;
            },
            appendChild(child) {
                this.children.push(child);
            }
        };
    }
};

const target = {
    children: [{ tagName: 'old' }],
    replaceChildren() {
        this.children = [];
    },
    appendChild(child) {
        this.children.push(child);
    }
};

const payload = '<svg/onload=globalThis.__xss=true>';
renderToastContent(target, payload, 'fas fa-circle-info invalid<script>');

assert.equal(target.children.length, 2);
assert.equal(target.children[0].className, 'fas fa-circle-info');
assert.equal(target.children[1].textContent, payload);
assert.equal(globalThis.__xss, undefined);

assert.equal(normalizePhilippinePhoneInput('0967 953 3700'), '09679533700');
assert.equal(normalizePhilippinePhoneInput('+63 967 953 3700'), '09679533700');
assert.equal(normalizePhilippinePhoneInput('08904798456456065405465045606540'), '08904798456');
assert.equal(normalizePhilippinePhoneInput('09ab67-953-3700'), '09679533700');

const makeNumberInput = (attributes = {}) => ({
    hasAttribute(name) { return Object.hasOwn(attributes, name); },
    getAttribute(name) { return attributes[name]; }
});
assert.equal(numberInputAllowsNegative(makeNumberInput({ min: '0' })), false);
assert.equal(numberInputAllowsNegative(makeNumberInput({ min: '-10' })), true);
assert.equal(numberInputAllowsNegative(makeNumberInput({ min: '0', 'data-allow-negative': '' })), true);
assert.equal(numberInputExceedsDeclaredMaximum(makeNumberInput({ max: '1000000' }), '999999'), false);
assert.equal(numberInputExceedsDeclaredMaximum(makeNumberInput({ max: '1000000' }), '1000001'), true);
assert.equal(numberInputExceedsDeclaredMaximum(makeNumberInput({}), '1000001'), false);
assert.equal(plainNumberTextIsWithinDigitLimit('123456789012'), true);
assert.equal(plainNumberTextIsWithinDigitLimit('1234567890123'), false);
assert.equal(plainNumberTextIsWithinDigitLimit('-12.345'), true);

assert.equal(personNameHasLetter('123456'), false);
assert.equal(personNameHasLetter('Anne-Marie O’Neill'), true);
assert.equal(personNameHasLetter('José'), true);
assert.equal(personNameHasLetter('Juan II'), true);

assert.equal(normalizeBankAccountNumber('0012-3456 7890'), '001234567890');
assert.equal(normalizeBankAccountNumber('1234abc5678'), '12345678');
assert.equal(normalizeBankAccountNumber('123456789012345678901234'), '12345678901234567890');

console.log('Frontend security helper tests passed.');
