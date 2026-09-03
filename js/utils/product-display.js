/**
 * Highland Fresh — canonical finished-product/SKU display helpers.
 *
 * Product names remain clean in the database. Screens compose the worker-facing
 * identity from name + meaningful variant + package size instead.
 */
(function (global) {
    'use strict';

    function firstValue(item, keys) {
        for (const key of keys) {
            const value = item?.[key];
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                return value;
            }
        }
        return '';
    }

    function formatNumber(value) {
        const number = Number(value);
        if (!Number.isFinite(number) || number <= 0) return '';
        return number.toLocaleString('en-US', { maximumFractionDigits: 3 });
    }

    function formatUnit(value) {
        const raw = String(value || '').trim();
        const normalized = raw.toLowerCase().replace(/\s+/g, '');
        const known = {
            ml: 'mL', milliliter: 'mL', milliliters: 'mL',
            l: 'L', liter: 'L', liters: 'L', litre: 'L', litres: 'L',
            g: 'g', gram: 'g', grams: 'g',
            kg: 'kg', kilogram: 'kg', kilograms: 'kg'
        };
        return known[normalized] || raw;
    }

    function size(item) {
        const amount = formatNumber(firstValue(item, ['size_value', 'size_ml', 'unit_size', 'packaging_size_ml']));
        if (!amount) return '';
        const unit = formatUnit(firstValue(item, ['size_unit', 'unit_measure', 'packaging_unit']) || 'mL');
        return `${amount} ${unit}`.trim();
    }

    function normalized(value) {
        return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function name(item, options) {
        const opts = options || {};
        const base = String(firstValue(item, ['product_name', 'name', 'description']) || opts.fallback || 'Unknown product').trim();
        const variant = String(firstValue(item, ['variant', 'product_variant']) || '').trim();
        const sizeText = size(item);
        const normalizedSize = normalized(sizeText);
        const variantIsOnlySize = variant && normalized(variant) === normalizedSize;
        const parts = [];

        if (variant && !variantIsOnlySize && !normalized(base).includes(normalized(variant))) {
            parts.push(variant);
        }
        if (sizeText && !normalized(base).includes(normalizedSize)
            && !normalized(variant).includes(normalizedSize)) {
            parts.push(sizeText);
        } else if (sizeText && variantIsOnlySize) {
            parts.push(sizeText);
        }

        const display = parts.length ? `${base} — ${parts.join(' · ')}` : base;
        const code = String(firstValue(item, ['product_code', 'product_sku', 'sku']) || '').trim();
        return opts.includeCode && code ? `${code} — ${display}` : display;
    }

    function barcodeToken(item) {
        const code = String(firstValue(item, ['product_code', 'product_sku', 'sku']) || '').trim();
        const fallback = item?.product_id ? `SKU${item.product_id}` : (size(item) || 'SKU');
        return String(code || fallback)
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'SKU';
    }

    global.ProductDisplay = Object.freeze({
        name,
        size,
        barcodeToken,
        formatUnit
    });
})(typeof window !== 'undefined' ? window : globalThis);
