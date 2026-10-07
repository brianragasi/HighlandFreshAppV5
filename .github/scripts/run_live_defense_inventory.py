"""Run and remove a one-time token-protected defense inventory job via FTP."""

import ftplib
import hashlib
import io
import json
import os
from pathlib import Path
import secrets
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime
from zoneinfo import ZoneInfo


mode = os.environ.get("DEFENSE_INVENTORY_MODE", "inspect")
if mode not in {"inspect", "validate", "apply", "inspect_locations", "validate_locations", "apply_locations", "inspect_raw", "validate_raw", "apply_raw", "inspect_gap_packaging", "validate_gap_packaging", "apply_gap_packaging", "inspect_low_stock_scenario", "validate_low_stock_scenario", "apply_low_stock_scenario", "inspect_qc_labels", "inspect_gm_demo", "validate_gm_demo", "apply_gm_demo"}:
    raise SystemExit("Invalid defense inventory mode")
if os.environ.get("DEFENSE_INVENTORY_SCHEDULED") == "true":
    if datetime.now(ZoneInfo("Asia/Manila")).date().isoformat() != "2026-10-08":
        print("Skipping one-time demo inventory refresh outside October 8, 2026.")
        raise SystemExit(0)

source = Path(__file__).with_name("live_defense_inventory.php").read_text(encoding="utf-8")
token = secrets.token_hex(32)
filename = "_defense_job_" + secrets.token_hex(12) + ".php"
content = source.replace("__DEFENSE_JOB_TOKEN__", token).encode("utf-8")
if content == source.encode("utf-8"):
    raise SystemExit("Defense job token placeholder missing")

client = ftplib.FTP(os.environ["FTP_SERVER"], timeout=30)
uploaded = False
try:
    client.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
    if mode == "inspect_qc_labels":
        root = Path(__file__).resolve().parents[2]
        deployed_files = {}
        for relative in ("api/qc/batch_release.php", "html/qc/print-labels.html"):
            remote = io.BytesIO()
            client.retrbinary("RETR " + relative, remote.write)
            local_digest = hashlib.sha256((root / relative).read_bytes()).hexdigest()
            deployed_files[relative] = {
                "matches_repository": hashlib.sha256(remote.getvalue()).hexdigest() == local_digest,
                "remote_has_demo_label_lookup": b"qcGetDemoLabelLines" in remote.getvalue(),
                "remote_has_label_selector": b"label_packaging_lines" in remote.getvalue(),
            }
        print(json.dumps({"deployed_files": deployed_files}, indent=2))
    client.storbinary("STOR api/" + filename, io.BytesIO(content))
    uploaded = True
    request = urllib.request.Request(
        "https://highlandfresh.whf.bz/api/" + filename,
        data=urllib.parse.urlencode({"mode": mode}).encode("ascii"),
        headers={"X-Defense-Job-Token": token},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            result = json.load(response)
    except urllib.error.HTTPError as error:
        try:
            result = json.load(error)
        except (ValueError, UnicodeError):
            result = {"error": "Remote job returned HTTP " + str(error.code)}
        print(json.dumps(result, indent=2))
        raise SystemExit(1)
    print(json.dumps(result, indent=2))
    if result.get("error"):
        raise SystemExit(1)
finally:
    if uploaded:
        client.delete("api/" + filename)
    client.quit()
