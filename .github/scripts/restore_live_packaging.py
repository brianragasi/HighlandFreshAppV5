"""Restore only the just-archived butter wrapper, with audit verification."""

import ftplib
import io
import json
import os
import secrets
import time
import urllib.error
import urllib.request


HOST = os.environ["FTP_SERVER"]
USER = os.environ["FTP_USERNAME"]
PASSWORD = os.environ["FTP_PASSWORD"]
TOKEN = secrets.token_urlsafe(32)
REMOTE = "hf_restore_wrapper_" + secrets.token_hex(12) + ".php"
URL = "https://highlandfresh.whf.bz/" + REMOTE


def connect():
    ftp = ftplib.FTP(timeout=30)
    ftp.connect(HOST, 21)
    ftp.login(USER, PASSWORD)
    ftp.set_pasv(True)
    return ftp


php = """<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    !hash_equals('__TOKEN__', $_SERVER['HTTP_X_HF_RECOVERY_TOKEN'] ?? '')) {
    http_response_code(404); exit;
}
require __DIR__ . '/api/bootstrap.php';
try {
    $db = Database::getInstance()->getConnection();
    $apply = ($_GET['apply'] ?? '') === '1';
    $db->beginTransaction();
    $stmt = $db->query("SELECT i.*, c.category_name FROM ingredients i
        JOIN ingredient_categories c ON c.id = i.category_id
        WHERE i.id = 105 FOR UPDATE");
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    $audit = $db->query("SELECT id, user_id, old_values, new_values
        FROM audit_logs WHERE id = 6778 AND table_name = 'ingredients'
          AND record_id = 105 AND action = 'UPDATE' FOR UPDATE")
        ->fetch(PDO::FETCH_ASSOC);
    if (!$ingredient || $ingredient['ingredient_code'] !== 'DEMO-WRAP-BUT-250'
        || $ingredient['ingredient_name'] !== '250 g Butter Wrapper (Demo, unverified)'
        || $ingredient['category_name'] !== 'Packaging Materials'
        || (int) $ingredient['is_active'] !== 0 || !$audit) {
        throw new RuntimeException('Reviewed archive identity/status no longer matches');
    }
    $old = json_decode($audit['old_values'] ?? '', true);
    $new = json_decode($audit['new_values'] ?? '', true);
    if ((int) ($old['is_active'] ?? -1) !== 1
        || (int) ($new['is_active'] ?? -1) !== 0) {
        throw new RuntimeException('Archive audit transition does not match');
    }
    $later = $db->query("SELECT old_values, new_values FROM audit_logs
        WHERE table_name = 'ingredients' AND record_id = 105 AND id > 6778
        ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($later as $entry) {
        $before = json_decode($entry['old_values'] ?? '', true) ?: [];
        $after = json_decode($entry['new_values'] ?? '', true) ?: [];
        if (isset($before['is_active'], $after['is_active'])
            && (int) $before['is_active'] !== (int) $after['is_active']) {
            throw new RuntimeException('A later status change exists; recovery stopped');
        }
    }
    $activeSkuUses = (int) $db->query("SELECT COUNT(*) FROM sku_packaging_bom_items bom
        JOIN products p ON p.id = bom.product_id AND p.is_active = 1
        WHERE bom.ingredient_id = 105 AND bom.is_active = 1")->fetchColumn();
    if ($activeSkuUses < 1) {
        throw new RuntimeException('Wrapper is no longer linked to an active SKU');
    }
    if ($apply) {
        $auditBefore = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
        $db->exec('UPDATE ingredients SET is_active = 1 WHERE id = 105 AND is_active = 0');
        $restored = $ingredient;
        $restored['is_active'] = 1;
        logAudit((int) $audit['user_id'], 'RESTORE_BY_REQUEST', 'ingredients', 105,
            $ingredient, $restored);
        $auditCheck = $db->prepare("SELECT COUNT(*) FROM audit_logs
            WHERE id > ? AND table_name = 'ingredients' AND record_id = 105
              AND action = 'RESTORE_BY_REQUEST'");
        $auditCheck->execute([$auditBefore]);
        if ((int) $auditCheck->fetchColumn() !== 1) {
            throw new RuntimeException('Recovery audit event was not recorded');
        }
        $db->commit();
    } else {
        $db->rollBack();
    }
    echo json_encode(['applied' => $apply, 'code' => 'DEMO-WRAP-BUT-250',
        'active_sku_uses' => $activeSkuUses, 'status_after' => $apply ? 1 : 0]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
""".replace("__TOKEN__", TOKEN).encode("utf-8")


def call_live(apply):
    for attempt in range(1, 6):
        request = urllib.request.Request(
            URL + ("?apply=1" if apply else "?apply=0") + "&nonce=" + secrets.token_hex(4),
            data=b"",
            method="POST",
            headers={
                "X-HF-Recovery-Token": TOKEN,
                "Cache-Control": "no-cache",
                "User-Agent": "CodexUserRequestedArchiveRecovery/1.0",
            },
        )
        try:
            with urllib.request.urlopen(request, timeout=60) as response:
                body = response.read()
        except urllib.error.HTTPError as error:
            try:
                detail = json.load(error).get("error", "unknown")
            except (ValueError, AttributeError):
                detail = "non-JSON server error"
            raise RuntimeError(f"Live recovery HTTP {error.code}: {detail}") from None
        try:
            result = json.loads(body)
        except ValueError:
            if attempt == 5:
                raise RuntimeError("Live recovery returned non-JSON") from None
            time.sleep(attempt * 2)
            continue
        if result.get("applied") is not apply:
            raise RuntimeError("Live recovery returned an unexpected result")
        return result


ftp = connect()
try:
    ftp.storbinary("STOR " + REMOTE, io.BytesIO(php))
finally:
    ftp.quit()

try:
    print("Live recovery preview:", json.dumps(call_live(False), sort_keys=True))
    print("Live recovery applied:", json.dumps(call_live(True), sort_keys=True))
finally:
    ftp = connect()
    try:
        ftp.delete(REMOTE)
    finally:
        ftp.quit()
