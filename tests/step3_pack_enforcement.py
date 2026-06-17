#!/usr/bin/env python3
"""Step 3 smoke test: pack-integrity enforcement."""
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

# Login
_, r = req("POST", "/api/auth/login.php",
           {"identifier":"production@gmail.com","password":"password"})
tok = r["data"]["token"]
print("Login OK\n")

# Setup: Chocolate Powder X (id=11) has pack_size=1, enforce_whole_packs=1
print("Test fixture: Chocolate Powder X (id=11), pack_size=1 kg, enforce_whole_packs=1, stock=45 kg")
q("UPDATE ingredients SET pack_size_value=1, pack_size_unit='kg', pack_label='1 kg bag', enforce_whole_packs=1 WHERE id=11;")
print()

# TEST A: Submit fractional packs (1.5) WITHOUT break_pack_acknowledged → expect 422
print("=== TEST A: 1.5 packs, NO break ack → expect 422 pack_fractional ===")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder X",
        "quantity": 1.5, "unit": "kg"
    }]
}, token=tok)
print(f"HTTP {status}")
print(json.dumps(r, indent=2)[:800])
assert status == 422, f"Expected 422, got {status}"
assert r.get("errors", {}).get("error_code") == "pack_fractional"
off = r["errors"]["pack_check"]["items"][0]
print(f"\n  Offender: {off['item_name']} requested {off['effective_packs']} packs → ceil to {off['ceil_packs']} ({off['ceil_base']} kg)")
print(f"  Message: {off['message']}\n")

# TEST B: Submit fractional packs WITH break_pack_acknowledged=true → expect 201
print("=== TEST B: 1.5 packs WITH break ack → expect 201, row stored with break_pack_acknowledged=1 ===")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder X",
        "quantity": 1.5, "unit": "kg",
        "break_pack_acknowledged": True,
        "break_pack_acknowledged_reason": "Half-batch test, OK to open bag"
    }]
}, token=tok)
print(f"HTTP {status}")
req_id_b = r.get("data", {}).get("id")
print(json.dumps(r, indent=2)[:600])
print(f"\n  DB row for req {req_id_b}:")
print(q(f"SELECT id, requested_quantity, requested_quantity_in_packs, pack_size_at_submit, break_pack_acknowledged, break_pack_acknowledged_reason FROM requisition_items WHERE requisition_id={req_id_b};"))

# TEST C: Submit whole packs (2) without break ack → expect 201, no break
print()
print("=== TEST C: 2 whole packs, NO break ack → expect 201, break_pack_acknowledged=0 ===")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder X",
        "quantity": 2, "unit": "kg"
    }]
}, token=tok)
print(f"HTTP {status}")
req_id_c = r.get("data", {}).get("id")
print(q(f"SELECT id, requested_quantity, requested_quantity_in_packs, break_pack_acknowledged FROM requisition_items WHERE requisition_id={req_id_c};"))

# TEST D: Turn OFF enforce_whole_packs, submit fractional → no gate
print()
print("=== TEST D: enforce_whole_packs=0, 1.5 packs → expect 201 (no gate) ===")
q("UPDATE ingredients SET enforce_whole_packs=0 WHERE id=11;")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder X",
        "quantity": 1.5, "unit": "kg"
    }]
}, token=tok)
print(f"HTTP {status}")
req_id_d = r.get("data", {}).get("id")
print(q(f"SELECT id, requested_quantity, break_pack_acknowledged FROM requisition_items WHERE requisition_id={req_id_d};"))

# Restore for any subsequent test
q("UPDATE ingredients SET enforce_whole_packs=1 WHERE id=11;")
print("\n=== restored enforce_whole_packs=1 for fixture ===")
print("\n=== ALL DONE ===")
