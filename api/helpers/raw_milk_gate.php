<?php

/**
 * Raw milk has a three-hour handling window from the physical gate arrival.
 * Keep this rule in one place so Receiving, QC, and Warehouse cannot disagree.
 */
const RAW_MILK_GATE_SECONDS = 3 * 60 * 60;

function sqlRawMilkGateArrivalExpr($rmiAlias = 'rmi', $mrAlias = 'mr') {
    return "COALESCE(
        TIMESTAMP({$mrAlias}.receiving_date, COALESCE(NULLIF({$mrAlias}.receiving_time, ''), TIME({$mrAlias}.created_at), '00:00:00')),
        TIMESTAMP({$rmiAlias}.received_date, '00:00:00'),
        {$mrAlias}.created_at
    )";
}

function sqlRawMilkExpiresAtExpr($rmiAlias = 'rmi', $mrAlias = 'mr') {
    return 'DATE_ADD(' . sqlRawMilkGateArrivalExpr($rmiAlias, $mrAlias) . ', INTERVAL 3 HOUR)';
}

function rawMilkGateStatus($receivingDate, $receivingTime, $createdAt = null, ?DateTimeImmutable $now = null) {
    $timezone = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila');
    $date = trim((string) $receivingDate);
    $time = trim((string) $receivingTime);

    if ($time === '' && $createdAt) {
        try {
            $time = (new DateTimeImmutable((string) $createdAt, $timezone))->format('H:i:s');
        } catch (Throwable $ignored) {
            $time = '';
        }
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
        || !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/D', $time)) {
        return ['valid' => false, 'message' => 'Arrival date and time are invalid'];
    }

    if (strlen($time) === 5) {
        $time .= ':00';
    }

    $arrival = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time, $timezone);
    $parseErrors = DateTimeImmutable::getLastErrors();
    if (!$arrival || ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0))) {
        return ['valid' => false, 'message' => 'Arrival date and time are invalid'];
    }

    $now = $now ?: new DateTimeImmutable('now', $timezone);
    $deadline = $arrival->modify('+3 hours');
    $ageSeconds = $now->getTimestamp() - $arrival->getTimestamp();

    return [
        'valid' => true,
        'arrival' => $arrival,
        'deadline' => $deadline,
        'age_seconds' => $ageSeconds,
        'remaining_seconds' => $deadline->getTimestamp() - $now->getTimestamp(),
        'is_future' => $ageSeconds < -300,
        'is_expired' => $now >= $deadline,
    ];
}
