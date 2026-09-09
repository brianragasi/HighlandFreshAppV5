/**
 * Canonical normalization for human-entered searches and scanner input.
 * Keeps every warehouse lookup consistent when text is pasted with spaces,
 * non-breaking spaces, tabs, or a scanner's trailing newline.
 */
(function (global) {
    'use strict';

    function normalizedString(value) {
        const text = String(value ?? '');
        return typeof text.normalize === 'function' ? text.normalize('NFKC') : text;
    }

    function text(value) {
        return normalizedString(value).replace(/\s+/gu, ' ').trim();
    }

    function barcode(value) {
        // Highland Fresh identifiers never contain meaningful whitespace.
        return normalizedString(value).replace(/[\s\u200B-\u200D\u2060\uFEFF]+/gu, '');
    }

    function searchable(value) {
        return text(value).toLocaleLowerCase();
    }

    function matches(values, query) {
        const terms = searchable(query).split(' ').filter(Boolean);
        if (terms.length === 0) return true;

        const haystack = searchable(Array.isArray(values) ? values.join(' ') : values);
        return terms.every(term => haystack.includes(term));
    }

    const LookupNormalization = { text, barcode, searchable, matches };
    global.LookupNormalization = LookupNormalization;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = LookupNormalization;
    }
})(typeof globalThis !== 'undefined' ? globalThis : window);
