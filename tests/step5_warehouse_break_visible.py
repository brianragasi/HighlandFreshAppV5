#!/usr/bin/env python3
"""Step 5 smoke: verify break_pack_acknowledged + reason are in the warehouse detail response."""
import json, urllib.request, ssl, subprocess
ctx = ssl.create_default_context(); ctx.check_hostname=False; ctx.verify_mode=ssl.CERT_NONE
BASE = "https://localhost/HighlandFreshAppV4"

def req(method, path, body=None, token=None):
    h = {"Content-Type": "application/json"}
    if token: h["Authorization"] = f"Bearer {token}"
    d = json.dumps(body).encode() if body is not None else None
    r = urllib.request.Request(BASE + path, data=d, method=method, headers=h)
    try:
        with urllib.request.urlopen(r, timeout=5, context=ctx) as resp:
            return resp.status, json.loads(resp.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())

def q(sql):
    r = subprocess.run(["C:\\xampp\\mysql\\bin\\mysql.exe", "-u", "root", "highland_fresh", "-e", sql],
                       capture_output=True, text=True)
    return r.stdout

# Login as production
_, r = req("POST", "/api/auth/login.php",
           {"identifier":"production@gmail.com","password":"password"})
prod_tok = r["data"]["token"]
print("Login (production) OK")

# Login as warehouse
_, r = req("POST", "/api/auth/login.php",
           {"username":"warehouse_raw","password":"test1234"})
wh_tok = r["data"]["token"]
print("Login (warehouse_raw) OK")

# Ensure fixture, then create one with a break + one without
q("UPDATE ingredients SET pack_size_value=1, pack_size_unit='kg', pack_label='1 kg bag', enforce_whole_packs=1 WHERE id=11;")

# Req A: with pack break
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder X",
        "quantity": 1.5, "quantity_in_packs": 1.5, "unit": "kg",
        "break_pack_acknowledged": True,
        "break_pack_acknowledged_reason": "Half-batch test, OK to open bag"
    }]
}, token=prod_tok)
print(f"\n=== Req A: pack break with reason ===")
print(f"  HTTP {status}: created REQ {r['data'].get('requisition_code')}, id={r['data'].get('id')}")
req_a = r['data']['id']

# Req B: clean whole-pack
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder X",
        "quantity": 2, "quantity_in_packs": 2, "unit": "kg"
    }]
}, token=prod_tok)
print(f"\n=== Req B: clean whole-pack ===")
print(f"  HTTP {status}: created REQ {r['data'].get('requisition_code')}, id={r['data'].get('id')}")
req_b = r['data']['id']

# Warehouse fetches the detail of each
print()
print("=== Warehouse GET detail of Req A (with break) ===")
status, r = req("GET", f"/api/warehouse/raw/requisitions.php?action=detail&id={req_a}", token=wh_tok)
items = r.get("data", {}).get("items", [])
for it in items:
    print(f"  {it['item_name']}:")
    print(f"    requested_quantity = {it.get('requested_quantity')}")
    print(f"    requested_quantity_in_packs = {it.get('requested_quantity_in_packs')}")
    print(f"    pack_size_at_submit = {it.get('pack_size_at_submit')}")
    print(f"    break_pack_acknowledged = {it.get('break_pack_acknowledged')}")
    print(f"    break_pack_acknowledged_reason = {it.get('break_pack_acknowledged_reason')!r}")

print()
print("=== Warehouse GET detail of Req B (clean) ===")
status, r = req("GET", f"/api/warehouse/raw/requisitions.php?action=detail&id={req_b}", token=wh_tok)
items = r.get("data", {}).get("items", [])
for it in items:
    print(f"  {it['item_name']}:")
    print(f"    break_pack_acknowledged = {it.get('break_pack_acknowledged')}")
    print(f"    break_pack_acknowledged_reason = {it.get('break_pack_acknowledged_reason')!r}")

print("\n=== ALL DONE ===")
