<?php

/**
 * Parse an ordinary business decimal without accepting PHP's scientific
 * notation shortcuts (for example 2e10) or silently truncating invalid text.
 *
 * @throws InvalidArgumentException
 */
function hfParseBusinessDecimal(
    $value,
    string $label,
    float $minimum,
    float $maximum,
    int $decimalPlaces = 2
): float {
    if (is_array($value) || is_object($value) || is_bool($value) || $value === null) {
        throw new InvalidArgumentException("{$label} must be an ordinary number");
    }

    $raw = trim((string) $value);
    $scale = max(0, min(6, $decimalPlaces));
    $sign = $minimum < 0 ? '-?' : '';
    $pattern = $scale === 0
        ? '/^' . $sign . '\d+$/'
        : '/^' . $sign . '(?:\d+|\d*\.\d{1,' . $scale . '})$/';

    if ($raw === '' || !preg_match($pattern, $raw)) {
        $precision = $scale === 0
            ? 'a whole number'
            : "an ordinary number with no more than {$scale} decimal places";
        $signHint = $minimum < 0 ? 'do not use exponent notation or a plus sign' : 'do not use e, E, +, or -';
        throw new InvalidArgumentException("{$label} must be {$precision}; {$signHint}");
    }

    $number = (float) $raw;
    if (!is_finite($number) || $number < $minimum || $number > $maximum) {
        $minLabel = number_format($minimum, $scale, '.', ',');
        $maxLabel = number_format($maximum, $scale, '.', ',');
        throw new InvalidArgumentException("{$label} must be between {$minLabel} and {$maxLabel}");
    }

    return round($number, $scale);
}

/** @throws InvalidArgumentException */
function hfParseBusinessInteger($value, string $label, int $minimum, int $maximum): int {
    return (int) hfParseBusinessDecimal($value, $label, (float) $minimum, (float) $maximum, 0);
}
