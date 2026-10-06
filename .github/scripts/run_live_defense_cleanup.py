"""Run the reviewed, reversible defense cleanup once on the live database."""

from __future__ import annotations

import ftplib
import io
import json
import os
import pathlib
import secrets
import time
import urllib.error
import urllib.request


ROOT = pathlib.Path(__file__).resolve().parents[2]
HOST = os.environ["FTP_SERVER"]
USER = os.environ["FTP_USERNAME"]
PASSWORD = os.environ["FTP_PASSWORD"]
TOKEN = secrets.token_urlsafe(32)
REMOTE_RUNNER = f"hf_defense_cleanup_{secrets.token_hex(12)}.php"
LIVE_URL = f"https://highlandfresh.whf.bz/{REMOTE_RUNNER}"
LIBRARY = "scripts/defense_cleanup_lib.php"


def ftp_connect() -> ftplib.FTP:
    client = ftplib.FTP(timeout=30)
    client.connect(HOST, 21)
    client.login(USER, PASSWORD)
    client.set_pasv(True)
    return client


def upload(remote: str, content: bytes) -> None:
    client = ftp_connect()
    try:
        client.storbinary(f"STOR {remote}", io.BytesIO(content))
    finally:
        client.quit()


def call_live(apply: bool) -> dict:
    for attempt in range(1, 6):
        url = LIVE_URL + ("?apply=1" if apply else "?apply=0") + "&nonce=" + secrets.token_hex(4)
        request = urllib.request.Request(
            url,
            data=b"",
            headers={
                "X-HF-Cleanup-Token": TOKEN,
                "Content-Type": "application/octet-stream",
                "Cache-Control": "no-cache",
                "User-Agent": "HighlandFreshDefenseCleanup/1.0",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(request, timeout=60) as response:
                body = response.read()
                content_type = response.headers.get("Content-Type", "")
        except urllib.error.HTTPError as error:
            try:
                detail = json.load(error).get("error", "unknown server error")
            except (ValueError, AttributeError):
                detail = "server did not return a cleanup error"
            raise RuntimeError(f"Live cleanup HTTP {error.code}: {str(detail)[:250]}") from None
        try:
            result = json.loads(body)
        except ValueError:
            if attempt == 5:
                raise RuntimeError(
                    f"Live cleanup returned non-JSON HTTP 200 ({len(body)} bytes; {content_type})"
                ) from None
            time.sleep(attempt * 2)
            continue
        if not isinstance(result, dict) or result.get("applied") is not apply:
            raise RuntimeError("Live cleanup returned an unexpected result")
        return result
    raise RuntimeError("Live cleanup did not return a result")


php = """<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !hash_equals('__TOKEN__', $_SERVER['HTTP_X_HF_CLEANUP_TOKEN'] ?? '')) {
    http_response_code(404); exit;
}
header('Content-Type: application/json; charset=utf-8');
define('HIGHLAND_FRESH', true);
require __DIR__ . '/api/config/config.php';
require __DIR__ . '/api/config/database.php';
require __DIR__ . '/scripts/defense_cleanup_lib.php';
try {
    $apply = ($_GET['apply'] ?? '') === '1';
    $result = hfCleanDefenseCatalog(Database::getInstance()->getConnection(), $apply);
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => $error->getMessage()]);
}
""".replace("__TOKEN__", TOKEN).encode("utf-8")


try:
    upload(LIBRARY, (ROOT / LIBRARY).read_bytes())
    upload(REMOTE_RUNNER, php)
    preview = call_live(False)
    print("Live defense cleanup preview:", json.dumps(preview["changes"], sort_keys=True))
    result = call_live(True)
    print("Live defense cleanup applied:", json.dumps(result["changes"], sort_keys=True))
finally:
    try:
        client = ftp_connect()
        try:
            client.delete(REMOTE_RUNNER)
        except ftplib.error_perm as error:
            if not str(error).startswith("550"):
                raise
        finally:
            client.quit()
    except Exception as error:
        raise RuntimeError("Temporary cleanup file could not be removed") from error
