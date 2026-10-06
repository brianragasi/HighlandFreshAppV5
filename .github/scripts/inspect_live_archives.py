"""Read-only, token-protected inspection of recent live archive actions."""

import ftplib
import io
import json
import os
import secrets
import time
import urllib.request


HOST = os.environ["FTP_SERVER"]
USER = os.environ["FTP_USERNAME"]
PASSWORD = os.environ["FTP_PASSWORD"]
TOKEN = secrets.token_urlsafe(32)
REMOTE = "hf_archive_audit_" + secrets.token_hex(12) + ".php"
URL = "https://highlandfresh.whf.bz/" + REMOTE


def connect():
    ftp = ftplib.FTP(timeout=30)
    ftp.connect(HOST, 21)
    ftp.login(USER, PASSWORD)
    ftp.set_pasv(True)
    return ftp


php = """<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    !hash_equals('__TOKEN__', $_SERVER['HTTP_X_HF_AUDIT_TOKEN'] ?? '')) {
    http_response_code(404); exit;
}
header('Content-Type: application/json; charset=utf-8');
define('HIGHLAND_FRESH', true);
require __DIR__ . '/api/config/config.php';
require __DIR__ . '/api/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $rows = $db->query("SELECT a.id, a.record_id, a.created_at, a.old_values,
            a.new_values, i.ingredient_code, i.ingredient_name, i.is_active,
            c.category_name
        FROM audit_logs a
        LEFT JOIN ingredients i ON i.id = a.record_id
        LEFT JOIN ingredient_categories c ON c.id = i.category_id
        WHERE a.table_name = 'ingredients' AND a.action = 'UPDATE'
        ORDER BY a.id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
    $changes = [];
    foreach ($rows as $row) {
        $old = json_decode($row['old_values'] ?? '', true) ?: [];
        $new = json_decode($row['new_values'] ?? '', true) ?: [];
        if (!array_key_exists('is_active', $old) || !array_key_exists('is_active', $new)
            || (int) $old['is_active'] === (int) $new['is_active']) continue;
        $changes[] = [
            'audit_id' => (int) $row['id'], 'ingredient_id' => (int) $row['record_id'],
            'time' => $row['created_at'], 'code' => $row['ingredient_code'],
            'name' => $row['ingredient_name'], 'category' => $row['category_name'],
            'from' => (int) $old['is_active'], 'to' => (int) $new['is_active'],
            'current' => (int) $row['is_active'],
        ];
    }
    $blockers = $db->query("SELECT i.ingredient_code, i.ingredient_name
        FROM supplier_ingredients si
        JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1
        WHERE si.supplier_id = 20 AND si.is_active = 1
          AND NOT EXISTS (
              SELECT 1 FROM supplier_ingredients other
              JOIN suppliers s ON s.id = other.supplier_id AND s.is_active = 1
              WHERE other.ingredient_id = i.id AND other.is_active = 1
                AND other.supplier_id <> 20
          ) ORDER BY i.ingredient_name")->fetchAll(PDO::FETCH_ASSOC);
    $poCount = $db->query("SELECT COUNT(*) FROM purchase_orders
        WHERE supplier_id = 20 AND status IN ('draft','approved','ordered','partial_received')")
        ->fetchColumn();
    echo json_encode(['recent_status_changes' => $changes,
        'supplier_20_sole_source_items' => $blockers,
        'supplier_20_open_po_count' => (int) $poCount], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
""".replace("__TOKEN__", TOKEN).encode("utf-8")


ftp = connect()
try:
    ftp.storbinary("STOR " + REMOTE, io.BytesIO(php))
finally:
    ftp.quit()

try:
    for attempt in range(1, 6):
        request = urllib.request.Request(
            URL + "?nonce=" + secrets.token_hex(4),
            data=b"",
            method="POST",
            headers={"X-HF-Audit-Token": TOKEN, "Cache-Control": "no-cache"},
        )
        with urllib.request.urlopen(request, timeout=60) as response:
            body = response.read()
        try:
            result = json.loads(body)
        except ValueError:
            if attempt == 5:
                raise RuntimeError("Live archive audit did not return JSON") from None
            time.sleep(attempt * 2)
            continue
        print("Live archive audit:", json.dumps(result, sort_keys=True))
        break
finally:
    ftp = connect()
    try:
        ftp.delete(REMOTE)
    finally:
        ftp.quit()
