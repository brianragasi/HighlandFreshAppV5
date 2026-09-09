<?php
/**
 * Normalize human-entered lookup values at the API boundary.
 * Frontend cleanup improves UX; these helpers make the server authoritative.
 */

if (!function_exists('hfNormalizeLookupText')) {
    function hfNormalizeLookupText($value): string
    {
        $raw = (string)($value ?? '');
        $normalized = preg_replace('/[\p{Z}\s]+/u', ' ', $raw);

        return trim($normalized === null ? $raw : $normalized);
    }
}

if (!function_exists('hfNormalizeBarcodeLookup')) {
    function hfNormalizeBarcodeLookup($value): string
    {
        $raw = (string)($value ?? '');
        // Product, batch, inventory, and DR identifiers do not contain spaces.
        $normalized = preg_replace('/[\p{Z}\p{C}\s]+/u', '', $raw);

        return trim($normalized === null ? $raw : $normalized);
    }
}
