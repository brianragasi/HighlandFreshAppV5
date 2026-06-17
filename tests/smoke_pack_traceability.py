#!/usr/bin/env python3
"""Smoke test: pack-traceability flow (V4.0 Option A)."""
import json
import urllib.request
import urllib.error
import ssl

BASE = "https://localhost/HighlandFreshAppV4"
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

def req(method, path, body=None, token=None):
    headers = {"Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    data = json.dumps(body).encode() if body is not None else None
    r = urllib.request.Request(BASE + path, data=data, method=method, headers=headers)
    try:
        with urllib.request.urlopen(r, timeout=5, context=ctx) as resp:
            return resp.status, json.loads(resp.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())

# 1. Login as production
status, resp = req("POST", "/api/auth/login.php",
                   {"identifier": "production@gmail.com", "password": "password"})
assert status == 200 and resp.get("success"), f"Login failed: {resp}"
tok = resp["data"]["token"]
print(f"=== LOGIN OK (token {len(tok)} chars) ===")

# 2. TEST A: Submit in PACKS mode, packs=10, pack_size=1 → expect 201, quantity stored as 10
print()
print("=== TEST A: 10 packs of Chocolate Powder X (pack_size=1 kg, stock=45 kg) ===")
print("    Expected: 201, quantity=10.000, quantity_in_packs=10, pack_size_at_submit=1")
status, resp = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1,
    "planned_quantity": 10,
    "planned_yield_unit": "L",
    "priority": "normal",
    "items": [{
        "item_type": "ingredient",
        "item_id": 11,
        "item_name": "Chocolate Powder X",
        "quantity": 10,            # raw qty (ignored because packs is set)
        "quantity_in_packs": 10,   # the meaningful value
        "unit": "kg"
    }]
}, token=tok)
print(f"HTTP {status}: {json.dumps(resp, indent=2)[:600]}")
req_a_id = resp.get("data", {}).get("id")
print(f"--- created req id: {req_a_id} ---")

# 3. Verify DB stored the right values
import subprocess
def query(sql):
    r = subprocess.run(
        ["C:\\xampp\\mysql\\bin\\mysql.exe", "-u", "root", "highland_fresh", "-e", sql],
        capture_output=True, text=True
    )
    return r.stdout

print()
print("=== DB check on the new item ===")
print(query(f"SELECT id, item_id, item_name, requested_quantity, requested_quantity_in_packs, pack_size_at_submit, unit_of_measure FROM requisition_items WHERE requisition_id={req_a_id};"))

# 4. TEST B: Submit in PACKS mode that exceeds available. packs=50, stock=45, pack=1 → expect 422
print()
print("=== TEST B: 50 packs of Chocolate Powder X (stock=45 kg) → expect 422 ===")
status, resp = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1,
    "planned_quantity": 10,
    "planned_yield_unit": "L",
    "priority": "normal",
    "items": [{
        "item_type": "ingredient",
        "item_id": 11,
        "item_name": "Chocolate Powder X",
        "quantity": 50,
        "quantity_in_packs": 50,
        "unit": "kg"
    }]
}, token=tok)
print(f"HTTP {status}: {json.dumps(resp, indent=2)[:600]}")
assert status == 422, f"Expected 422, got {status}"
assert resp.get("errors", {}).get("error_code") == "insufficient_stock"
shortages = resp["errors"]["stock_check"]["items"]
print(f"--- shortages returned: {len(shortages)} item(s) ---")
for s in shortages:
    print(f"    requested_packs={s.get('requested_packs')} pack_size={s.get('pack_size')} requested_base={s.get('requested')} available={s.get('available')} shortage={s.get('shortage')}")

# 5. TEST C: Submit in BASE mode (legacy), no quantity_in_packs → expect 201
print()
print("=== TEST C: 5 kg of Chocolate Powder X in BASE mode (no quantity_in_packs) ===")
print("    Expected: 201, quantity=5, quantity_in_packs=NULL, pack_size_at_submit=NULL")
status, resp = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1,
    "planned_quantity": 10,
    "planned_yield_unit": "L",
    "priority": "normal",
    "items": [{
        "item_type": "ingredient",
        "item_id": 11,
        "item_name": "Chocolate Powder X",
        "quantity": 5,
        "unit": "kg"
    }]
}, token=tok)
print(f"HTTP {status}: {json.dumps(resp, indent=2)[:500]}")
req_c_id = resp.get("data", {}).get("id")
print()
print("=== DB check on base-mode req ===")
print(query(f"SELECT id, requested_quantity, requested_quantity_in_packs, pack_size_at_submit FROM requisition_items WHERE requisition_id={req_c_id};"))

# 6. TEST D: GET detail of pack-mode req to verify the warehouse sees the new fields
print()
print("=== TEST D: GET detail of pack-mode req (warehouse view) ===")
status, resp = req("GET", f"/api/warehouse/raw/requisitions.php?action=detail&id={req_a_id}", token=tok)
print(f"HTTP {status}: {resp.get('data', {}).get('requisition', {}).get('requisition_code')}")
for item in resp.get("data", {}).get("items", []):
    print(f"  - {item['item_name']}: requested_quantity={item.get('requested_quantity')} requested_quantity_in_packs={item.get('requested_quantity_in_packs')} pack_size_at_submit={item.get('pack_size_at_submit')}")

print()
print("=== ALL TESTS DONE ===")
