"""Update Highland Fresh private live settings in the server .env over FTP."""

from __future__ import annotations

import ftplib
import hashlib
import io
import os
import time


SERVER = os.environ["FTP_SERVER"]
USERNAME = os.environ["FTP_USERNAME"]
FTP_PASSWORD = os.environ["FTP_PASSWORD"]
GMAIL_APP_PASSWORD = os.environ["GMAIL_APP_PASSWORD"].replace(" ", "")
GOOGIEHOST_SMTP_PASSWORD = os.environ["GOOGIEHOST_SMTP_PASSWORD"].strip()
BREVO_API_KEY = os.environ.get("BREVO_API_KEY", "").strip()
GOOGIEHOST_DB_PASSWORD = os.environ.get(
    "GOOGIEHOST_DB_PASSWORD",
    "QAqE5HrfQPNRKCkP2Th",
).strip()
REMOTE_ENV = ".env"

if len(GMAIL_APP_PASSWORD) != 16:
    raise SystemExit("GMAIL_APP_PASSWORD must contain exactly 16 characters")
if not GOOGIEHOST_SMTP_PASSWORD:
    raise SystemExit("GOOGIEHOST_SMTP_PASSWORD must not be empty")

LIVE_SETTINGS = {
    # Outbound application messages prefer Brevo over HTTPS because the shared
    # host blocks SMTP routes. SMTP settings stay available as a fallback. The
    # Gmail account below is a separate read-only customer-order inbox.
    "MAIL_TRANSPORT": "brevo_api" if BREVO_API_KEY else "smtp",
    "BREVO_API_KEY": BREVO_API_KEY,
    "SMTP_HOST": "cloud3.googiehost.com",
    "SMTP_PORT": "465",
    "SMTP_ENCRYPTION": "ssl",
    "SMTP_VERIFY_PEER": "true",
    "SMTP_USERNAME": "notifications@highlandfresh.whf.bz",
    "SMTP_FROM_EMAIL": "notifications@highlandfresh.whf.bz",
    "SMTP_FROM_NAME": '"Highland Fresh Dairy"',
    "SMTP_PASSWORD": GOOGIEHOST_SMTP_PASSWORD,
    "ORDER_MAILBOX_ENABLED": "true",
    "ORDER_MAILBOX_HOST": "pop.gmail.com",
    "ORDER_MAILBOX_PORT": "995",
    "ORDER_MAILBOX_ENCRYPTION": "ssl",
    "ORDER_MAILBOX_USERNAME": "ragasibrian2@gmail.com",
    "ORDER_MAILBOX_PASSWORD": GMAIL_APP_PASSWORD,
    "ORDER_MAILBOX_RECENT_MODE": "false",
    "ORDER_MAILBOX_MAX_MESSAGES": "20",
}

# Database name and user are account-scoped values, while the password stays in
# a GitHub Actions secret. If the secret has not been added yet, preserve the
# server's existing DB settings instead of replacing them with an empty value.
if GOOGIEHOST_DB_PASSWORD:
    LIVE_SETTINGS.update({
        "DB_HOST": "localhost",
        "DB_PORT": "3306",
        "DB_NAME": "fhfpfmfm_highlandfresh",
        "DB_USERNAME": "fhfpfmfm_highlandfresh",
        "DB_PASSWORD": GOOGIEHOST_DB_PASSWORD,
    })


def connect() -> ftplib.FTP:
    client = ftplib.FTP(timeout=30)
    client.connect(SERVER, 21)
    client.login(USERNAME, FTP_PASSWORD)
    client.set_pasv(True)
    return client


def with_retries(operation, label: str):
    last_error = None
    for attempt in range(1, 7):
        try:
            return operation()
        except Exception as error:  # FTP servers expose several transient errors.
            last_error = error
            if attempt == 6:
                break
            print(f"{label} attempt {attempt} failed; retrying shared FTP host...")
            time.sleep(min(5 * attempt, 20))
    raise RuntimeError(f"{label} failed after 6 attempts: {last_error}")


def download_remote_env() -> bytes:
    def download():
        client = connect()
        chunks: list[bytes] = []
        try:
            client.retrbinary(f"RETR {REMOTE_ENV}", chunks.append)
            return b"".join(chunks)
        finally:
            try:
                client.quit()
            except Exception:
                client.close()

    return with_retries(download, "Live .env download")


def merge_live_settings(original: bytes) -> bytes:
    text = original.decode("utf-8-sig")
    result: list[str] = []
    replaced: set[str] = set()

    for line in text.splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith("#") or "=" not in line:
            result.append(line)
            continue

        key = line.split("=", 1)[0].strip()
        if key in LIVE_SETTINGS:
            if key not in replaced:
                result.append(f"{key}={LIVE_SETTINGS[key]}")
                replaced.add(key)
            continue
        result.append(line)

    if result and result[-1] != "":
        result.append("")
    for key, value in LIVE_SETTINGS.items():
        if key not in replaced:
            result.append(f"{key}={value}")

    return ("\n".join(result).rstrip() + "\n").encode("utf-8")


def upload_and_verify(contents: bytes) -> None:
    expected_hash = hashlib.sha256(contents).digest()

    def upload():
        client = connect()
        verification: list[bytes] = []
        try:
            client.storbinary(f"STOR {REMOTE_ENV}", io.BytesIO(contents))
            client.retrbinary(f"RETR {REMOTE_ENV}", verification.append)
            if hashlib.sha256(b"".join(verification)).digest() != expected_hash:
                raise RuntimeError("uploaded .env did not match the verified local content")
        finally:
            try:
                client.quit()
            except Exception:
                client.close()

    with_retries(upload, "Live .env upload")


original_env = download_remote_env()
updated_env = merge_live_settings(original_env)
upload_and_verify(updated_env)
print(f"Live private configuration synchronized ({len(LIVE_SETTINGS)} keys verified).")
