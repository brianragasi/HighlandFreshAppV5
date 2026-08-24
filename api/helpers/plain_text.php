<?php
/**
 * Normalize user-entered labels, names, notes, and remarks that must remain text.
 * Output still needs HTML escaping when it is rendered in a browser.
 */

if (!function_exists('hfPlainText')) {
    function hfPlainText($value, int $maxLength = 1000, bool $preserveNewlines = true): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $text = (string)($value ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (!$preserveNewlines) {
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        }

        $text = trim($text);
        if ($maxLength > 0) {
            $text = function_exists('mb_substr')
                ? mb_substr($text, 0, $maxLength, 'UTF-8')
                : substr($text, 0, $maxLength);
        }

        return $text;
    }
}

if (!function_exists('hfPlainTextFields')) {
    function hfPlainTextFields(array $data, array $fieldLimits): array
    {
        foreach ($fieldLimits as $field => $settings) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $limit = is_array($settings) ? (int)($settings[0] ?? 1000) : (int)$settings;
            $preserveNewlines = is_array($settings) ? (bool)($settings[1] ?? true) : true;
            $data[$field] = hfPlainText($data[$field], $limit, $preserveNewlines);
        }

        return $data;
    }
}

if (!function_exists('hfPersonNameHasLetter')) {
    /**
     * Person names may legitimately contain spaces, apostrophes, hyphens,
     * suffixes, accents, and even digits. They must, however, contain at least
     * one Unicode letter so values such as "12345" are not stored as names.
     */
    function hfPersonNameHasLetter($value, bool $allowEmpty = false): bool
    {
        $name = hfPlainText($value, 1000, false);
        if ($name === '') {
            return $allowEmpty;
        }

        return preg_match('/\p{L}/u', $name) === 1;
    }
}

if (!function_exists('hfValidateBankAccountNumber')) {
    /**
     * Keep account numbers as strings so leading zeroes are preserved. Spaces
     * and hyphens may be pasted from a bank document and are normalized away;
     * other characters are rejected. Bank-specific lengths vary, so use a
     * conservative cross-bank range instead of forcing one bank's format.
     */
    function hfValidateBankAccountNumber($value, int $minimumDigits = 6, int $maximumDigits = 20): array
    {
        if (is_array($value) || is_object($value) || is_bool($value)) {
            return ['value' => '', 'error' => 'Bank account number must contain digits only'];
        }

        $raw = trim((string)($value ?? ''));
        if ($raw === '') {
            return ['value' => '', 'error' => null];
        }
        if (!preg_match('/^[0-9\s-]+$/D', $raw)) {
            return ['value' => '', 'error' => 'Bank account number must contain digits only'];
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $length = strlen($digits);
        if ($length < $minimumDigits || $length > $maximumDigits) {
            return [
                'value' => $digits,
                'error' => "Bank account number must contain {$minimumDigits} to {$maximumDigits} digits",
            ];
        }

        return ['value' => $digits, 'error' => null];
    }
}
