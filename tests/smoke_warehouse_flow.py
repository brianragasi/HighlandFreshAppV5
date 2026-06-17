#!/usr/bin/env python3
"""Smoke test: warehouse flow + legacy action rejection."""
import json
import urllib.request
import urllib.parse
import ssl

BASE = "https://localhost/HighlandFreshAppV4"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

def post(path, body, token=None):
    data = json.dumps(body).encode()
    req = urllib.request.Request(BASE + path, data=data, method="POST",
                                 headers={"Content-Type": "application/json"})
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    try:
        with urllib.request.urlopen(req, timeout=5, context=ctx) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())

def put(path, body, token=None):
    data = json.dumps(body).encode()
    req = urllib.request.Request(BASE + path, data=data, method="PUT",
                                 headers={"Content-Type": "application/json"})
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    try:
        with urllib.request.urlopen(req, timeout=5, context=ctx) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())

def get(path, token=None):
    req = urllib.request.Request(BASE + path)
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    try:
        with urllib.request.urlopen(req, timeout=5, context=ctx) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())

# 1. Login as warehouse_raw
status, resp = post("/api/auth/login.php", {"username": "warehouse_raw", "password": "test1234"})
print(f"Warehouse login: HTTP {status} - {resp.get('success')} - token len {len(resp.get('data', {}).get('token', ''))}")
wh_token = resp["data"]["token"]

# 2. Login as production_staff for the cancel test
status, resp = post("/api/auth/login.php", {"username": "production_staff", "password": "test1234"})
prod_token = resp["data"]["token"]

# 3. Legacy approve action on production API
print()
print("=== TEST A: PUT ?action=approve on production requisitions API (should be 400) ===")
status, resp = put("/api/production/requisitions.php?id=1", {"action": "approve"}, token=prod_token)
print(f"HTTP {status}: {json.dumps(resp, indent=2)[:500]}")

# 4. Legacy approve action on warehouse API
print()
print("=== TEST B: PUT ?action=approve on warehouse requisitions API (should be 400) ===")
status, resp = put("/api/warehouse/raw/requisitions.php?id=1", {"action": "approve"}, token=wh_token)
print(f"HTTP {status}: {json.dumps(resp, indent=2)[:500]}")

# 5. List the new REQ we created
print()
print("=== TEST C: Warehouse list, check pending REQ is visible ===")
status, resp = get("/api/warehouse/raw/requisitions.php?action=list&limit=5", token=wh_token)
reqs = resp.get("data", {}).get("requisitions", [])
print(f"HTTP {status} - {len(reqs)} requisitions returned")
for r in reqs[:5]:
    code = r.get("requisition_code")
    status_v = r.get("status")
    override = r.get("stock_override_acknowledged")
    warn_count = r.get("stock_warning_count")
    print(f"  {code} | status={status_v} | override_ack={override} | stock_warnings={warn_count}")

# 6. Detail of the new REQ we created earlier
print()
print("=== TEST D: Detail of new REQ-20260614-001 (id=18) ===")
status, resp = get("/api/warehouse/raw/requisitions.php?action=detail&id=18", token=wh_token)
if resp.get("success"):
    req = resp["data"]["requisition"]
    print(f"  code: {req['requisition_code']}")
    print(f"  status: {req['status']}")
    print(f"  stock_override.acknowledged: {req['stock_override']['acknowledged']}")
    print(f"  stock_override.by: {req['stock_override']['by']}")
    print(f"  stock_override.reason: {req['stock_override']['reason']}")
    print(f"  stock_override.warnings count: {len(req['stock_override']['warnings'])}")
    if req['stock_override']['warnings']:
        w = req['stock_override']['warnings'][0]
        print(f"  first warning: {w['item_name']} requested={w['requested_qty']} available={w['available_qty']} shortage={w['shortage']} decision={w['decision']}")
    print(f"  items count: {len(resp['data']['items'])}")
    for it in resp['data']['items']:
        print(f"    - {it['item_name']} req={it['requested_quantity']} stock_sufficient={it.get('stock_sufficient')} shortage={it.get('stock_shortage')}")
else:
    print(f"  ERROR: {resp}")
