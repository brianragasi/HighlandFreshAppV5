"""Upload and verify the small set of files that must change together for email."""

from __future__ import annotations

import ftplib
import hashlib
import io
import os
import pathlib
import time


SERVER = os.environ["FTP_SERVER"]
USERNAME = os.environ["FTP_USERNAME"]
FTP_PASSWORD = os.environ["FTP_PASSWORD"]
PROJECT_ROOT = pathlib.Path(__file__).resolve().parents[2]
EMAIL_FILES = (
    "api/config/config.php",
    "api/config/mailer.php",
    "api/admin/smtp_diagnostics.php",
    "html/admin/dashboard.html",
)


def connect() -> ftplib.FTP:
    client = ftplib.FTP(timeout=30)
    client.connect(SERVER, 21)
    client.login(USERNAME, FTP_PASSWORD)
    client.set_pasv(True)
    return client


def upload_and_verify(relative_path: str) -> None:
    contents = (PROJECT_ROOT / relative_path).read_bytes()
    expected_hash = hashlib.sha256(contents).digest()
    last_error: Exception | None = None

    for attempt in range(1, 7):
        client = None
        try:
            client = connect()
            client.storbinary(f"STOR {relative_path}", io.BytesIO(contents))
            downloaded: list[bytes] = []
            client.retrbinary(f"RETR {relative_path}", downloaded.append)
            if hashlib.sha256(b"".join(downloaded)).digest() != expected_hash:
                raise RuntimeError("uploaded file did not match the repository copy")
            print(f"Verified {relative_path}")
            return
        except Exception as error:
            last_error = error
            if attempt < 6:
                print(f"{relative_path} attempt {attempt} failed; retrying shared FTP host...")
                time.sleep(min(5 * attempt, 20))
        finally:
            if client is not None:
                try:
                    client.quit()
                except Exception:
                    client.close()

    raise RuntimeError(f"Could not verify {relative_path}: {last_error}")


for email_file in EMAIL_FILES:
    upload_and_verify(email_file)

