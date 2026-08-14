<?php

/**
 * Small read-only POP3 client for the institutional customer order inbox.
 * It retrieves messages without deleting or moving them.
 */

function hfPop3FetchNewMessages(array $knownUids = []): array
{
    if (!defined('ORDER_MAILBOX_ENABLED') || !ORDER_MAILBOX_ENABLED) {
        throw new RuntimeException('Customer order mailbox is not configured.');
    }
    if (ORDER_MAILBOX_USERNAME === '' || ORDER_MAILBOX_PASSWORD === '') {
        throw new RuntimeException('Customer order mailbox username or password is missing.');
    }

    $scheme = strtolower((string) ORDER_MAILBOX_ENCRYPTION) === 'ssl' ? 'ssl' : 'tcp';
    $target = $scheme . '://' . ORDER_MAILBOX_HOST . ':' . ORDER_MAILBOX_PORT;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);

    $socket = @stream_socket_client(
        $target,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) {
        throw new RuntimeException("Customer order mailbox connection failed: {$errstr} ({$errno})");
    }

    stream_set_timeout($socket, 15);

    try {
        hfPop3ReadStatus($socket);
        $loginUsername = ORDER_MAILBOX_USERNAME;
        if (
            defined('ORDER_MAILBOX_RECENT_MODE')
            && ORDER_MAILBOX_RECENT_MODE
            && !str_starts_with(strtolower($loginUsername), 'recent:')
        ) {
            $loginUsername = 'recent:' . $loginUsername;
        }
        hfPop3Command($socket, 'USER ' . $loginUsername);
        hfPop3Command($socket, 'PASS ' . ORDER_MAILBOX_PASSWORD);
        $uidLines = hfPop3Command($socket, 'UIDL', true);

        $known = array_fill_keys(array_map('strval', $knownUids), true);
        $messages = [];
        $available = [];
        foreach ($uidLines as $line) {
            if (preg_match('/^(\d+)\s+(.+)$/', $line, $match)) {
                $available[] = [
                    'number' => (int) $match[1],
                    'uid' => trim($match[2]),
                ];
            }
        }

        $available = array_slice($available, -ORDER_MAILBOX_MAX_MESSAGES);
        foreach ($available as $entry) {
            $storedSourceUid = 'mailbox:' . hash('sha256', $entry['uid']);
            if (isset($known[$entry['uid']]) || isset($known[$storedSourceUid])) {
                continue;
            }
            $rawLines = hfPop3Command($socket, 'RETR ' . $entry['number'], true);
            $raw = implode("\r\n", $rawLines);
            if (strlen($raw) > 12 * 1024 * 1024) {
                $messages[] = [
                    'uid' => $entry['uid'],
                    'parse_error' => 'Email is larger than the 12 MB safety limit.',
                    'attachments' => [],
                ];
                continue;
            }
            $messages[] = array_merge(
                ['uid' => $entry['uid']],
                hfParseRawEmail($raw)
            );
        }

        hfPop3Command($socket, 'QUIT');
        return $messages;
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }
}

function hfPop3Command($socket, string $command, bool $multiline = false): array
{
    fwrite($socket, $command . "\r\n");
    hfPop3ReadStatus($socket);
    if (!$multiline) {
        return [];
    }

    $lines = [];
    while (($line = fgets($socket, 1024 * 1024)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($line === '.') {
            return $lines;
        }
        if (str_starts_with($line, '..')) {
            $line = substr($line, 1);
        }
        $lines[] = $line;
    }

    $meta = stream_get_meta_data($socket);
    if (!empty($meta['timed_out'])) {
        throw new RuntimeException('Customer order mailbox read timed out.');
    }
    throw new RuntimeException('Customer order mailbox closed the connection unexpectedly.');
}

function hfPop3ReadStatus($socket): string
{
    $line = fgets($socket, 4096);
    if ($line === false) {
        $meta = stream_get_meta_data($socket);
        throw new RuntimeException(!empty($meta['timed_out'])
            ? 'Customer order mailbox timed out.'
            : 'Customer order mailbox returned no response.');
    }

    $line = trim($line);
    if (!str_starts_with($line, '+OK')) {
        $safe = preg_replace('/(PASS\s+).*/i', '$1[hidden]', $line);
        throw new RuntimeException('Customer order mailbox rejected the request: ' . $safe);
    }
    return $line;
}

function hfParseRawEmail(string $raw): array
{
    [$headers, $body] = hfSplitMimeEntity($raw);
    $from = hfExtractEmailAddress($headers['from'] ?? '');
    $subject = hfDecodeMimeHeader($headers['subject'] ?? '');
    $messageId = trim((string) ($headers['message-id'] ?? ''), "<> \t\r\n");
    $receivedAt = null;
    if (!empty($headers['date'])) {
        $timestamp = strtotime($headers['date']);
        if ($timestamp !== false) {
            $receivedAt = date('Y-m-d H:i:s', $timestamp);
        }
    }

    $attachments = [];
    hfCollectMimeAttachments($headers, $body, $attachments);

    return [
        'message_id' => $messageId,
        'sender_email' => strtolower($from),
        'subject' => $subject,
        'received_at' => $receivedAt,
        'body' => hfExtractMimeTextBody($headers, $body),
        'attachments' => $attachments,
    ];
}

function hfExtractMimeTextBody(array $headers, string $body): string
{
    $plainParts = [];
    $htmlParts = [];
    hfCollectMimeTextParts($headers, $body, $plainParts, $htmlParts);
    $text = $plainParts[0] ?? ($htmlParts[0] ?? '');
    $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
    $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
    return trim(substr($text, 0, 50000));
}

function hfCollectMimeTextParts(array $headers, string $body, array &$plainParts, array &$htmlParts): void
{
    $contentTypeHeader = (string) ($headers['content-type'] ?? 'text/plain');
    $contentType = strtolower(trim(strtok($contentTypeHeader, ';')));
    $disposition = strtolower((string) ($headers['content-disposition'] ?? ''));
    if (str_contains($disposition, 'attachment') || hfMimeParameter($disposition, 'filename') !== '') {
        return;
    }

    if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', $contentTypeHeader, $match)) {
        $boundary = $match[1] !== '' ? $match[1] : $match[2];
        $chunks = preg_split(
            '/(?:\A|\r?\n)--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*(?:\r?\n|\z)/',
            $body
        );
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk, "\r\n");
            if ($chunk === '') {
                continue;
            }
            [$childHeaders, $childBody] = hfSplitMimeEntity($chunk);
            hfCollectMimeTextParts($childHeaders, $childBody, $plainParts, $htmlParts);
        }
        return;
    }

    if (!in_array($contentType, ['text/plain', 'text/html'], true)) {
        return;
    }

    $encoding = strtolower(trim((string) ($headers['content-transfer-encoding'] ?? '')));
    if ($encoding === 'base64') {
        $decoded = base64_decode(preg_replace('/\s+/', '', $body), true);
    } elseif ($encoding === 'quoted-printable') {
        $decoded = quoted_printable_decode($body);
    } else {
        $decoded = $body;
    }
    if (!is_string($decoded) || trim($decoded) === '') {
        return;
    }

    if ($contentType === 'text/html') {
        $decoded = html_entity_decode(strip_tags($decoded), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $htmlParts[] = trim($decoded);
        return;
    }
    $plainParts[] = trim($decoded);
}

function hfSplitMimeEntity(string $raw): array
{
    $parts = preg_split("/\r?\n\r?\n/", $raw, 2);
    $headerText = $parts[0] ?? '';
    $body = $parts[1] ?? '';
    $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $headerText);
    $headers = [];
    foreach (preg_split("/\r?\n/", $unfolded) as $line) {
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $pos)));
        $value = trim(substr($line, $pos + 1));
        $headers[$name] = isset($headers[$name])
            ? $headers[$name] . ', ' . $value
            : $value;
    }
    return [$headers, $body];
}

function hfCollectMimeAttachments(array $headers, string $body, array &$attachments): void
{
    $contentType = $headers['content-type'] ?? 'text/plain';
    if (preg_match('/boundary=(?:"([^"]+)"|([^;\s]+))/i', $contentType, $match)) {
        $boundary = $match[1] !== '' ? $match[1] : $match[2];
        $chunks = preg_split(
            '/(?:\A|\r?\n)--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*(?:\r?\n|\z)/',
            $body
        );
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk, "\r\n");
            if ($chunk === '') {
                continue;
            }
            [$childHeaders, $childBody] = hfSplitMimeEntity($chunk);
            hfCollectMimeAttachments($childHeaders, $childBody, $attachments);
        }
        return;
    }

    $disposition = $headers['content-disposition'] ?? '';
    $filename = hfMimeParameter($disposition, 'filename')
        ?: hfMimeParameter($contentType, 'name');
    if ($filename === '') {
        return;
    }

    $encoding = strtolower(trim((string) ($headers['content-transfer-encoding'] ?? '')));
    if ($encoding === 'base64') {
        $content = base64_decode(preg_replace('/\s+/', '', $body), true);
    } elseif ($encoding === 'quoted-printable') {
        $content = quoted_printable_decode($body);
    } else {
        $content = $body;
    }
    if (!is_string($content)) {
        return;
    }

    $attachments[] = [
        'filename' => basename(hfDecodeMimeHeader($filename)),
        'content_type' => strtolower(trim(strtok($contentType, ';'))),
        'content' => $content,
    ];
}

function hfMimeParameter(string $header, string $name): string
{
    if (preg_match('/(?:^|;)\s*' . preg_quote($name, '/') . '\*?=(?:"([^"]*)"|([^;]*))/i', $header, $match)) {
        $value = trim($match[1] !== '' ? $match[1] : $match[2]);
        if (str_contains($value, "''")) {
            [, $value] = explode("''", $value, 2);
            $value = rawurldecode($value);
        }
        return trim($value, "\"' ");
    }
    return '';
}

function hfDecodeMimeHeader(string $value): string
{
    if ($value === '') {
        return '';
    }
    if (function_exists('iconv_mime_decode')) {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if (is_string($decoded)) {
            return $decoded;
        }
    }
    return $value;
}

function hfExtractEmailAddress(string $from): string
{
    if (preg_match('/<([^>]+)>/', $from, $match)) {
        return filter_var(trim($match[1]), FILTER_VALIDATE_EMAIL) ? trim($match[1]) : '';
    }
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $from, $match)) {
        return strtolower($match[0]);
    }
    return '';
}
