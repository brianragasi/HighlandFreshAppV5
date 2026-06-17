#!/usr/bin/env python3
"""Step 4 smoke: simulate the new form payload that the locked-qty UI would build."""
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
print("Login OK")

# Ensure fixture: Chocolate Powder (id=11) has pack_size=1, enforce_whole_packs=1
q("UPDATE ingredients SET pack_size_value=1, pack_size_unit='kg', pack_label='1 kg bag', enforce_whole_packs=1 WHERE id=11;")

# Get the recipe items to know what pack_count the locked UI would render
status, r = req("GET", "/api/production/requisitions.php?action=planned_recipe_items&recipe_id=14&planned_quantity=92", token=tok)
recipe_items = r["data"]["items"]
cho = next((it for it in recipe_items if it.get("item_id") == 11), None)
print(f"\nRecipe auto-fill would render Chocolate Powder as:")
print(f"  qty = pack_count = {cho['pack_count']} (LOCKED)")
print(f"  unit = {cho['pack_size_unit']}")
print(f"  enforce_whole_packs = {cho['enforce_whole_packs']}")

# TEST A: Submit the recipe-locked payload as the form would build it.
# The qty is the pack_count, quantity_in_packs=1, unit=kg.
print("\n=== TEST A: Locked form submit (qty = 1 pack) → expect 201 ===")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 14, "planned_quantity": 92, "planned_yield_unit": "bottles", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder",
        "quantity": cho["pack_count"],            # the locked pack count
        "quantity_in_packs": cho["pack_count"],   # sent as pack because data-locked=1
        "unit": cho["pack_size_unit"],
    }]
}, token=tok)
print(f"HTTP {status}: {r.get('message')}")
req_id = r.get("data", {}).get("id")
print(q(f"SELECT id, requested_quantity, requested_quantity_in_packs, pack_size_at_submit, break_pack_acknowledged FROM requisition_items WHERE requisition_id={req_id};"))

# TEST B: User clicks Override, types 1.5 packs manually. Form sends
# {quantity: 1.5, quantity_in_packs: 1.5, unit: kg} (because the radio
# is now visible in unlocked mode but the user is still typing in pack
# units). Server should 422 with pack_fractional.
print()
print("=== TEST B: Override + manual 1.5 → expect 422 pack_fractional ===")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 14, "planned_quantity": 92, "planned_yield_unit": "bottles", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder",
        "quantity": 1.5,
        "quantity_in_packs": 1.5,
        "unit": "kg",
    }]
}, token=tok)
print(f"HTTP {status}: {r.get('message')}")
if r.get("errors", {}).get("error_code") == "pack_fractional":
    pc = r["errors"]["pack_check"]
    print(f"  fractional_count: {pc['fractional_count']}, unacked_count: {pc['unacked_count']}")
    for off in pc["items"]:
        print(f"  - {off['item_name']}: {off['effective_packs']} packs → ceil to {off['ceil_packs']}")

# TEST C: User clicks "Acknowledge pack break" + provides reason. Same
# 1.5 packs but with break_pack_acknowledged=true. Server accepts.
print()
print("=== TEST C: Override + manual 1.5 + break_pack_acknowledged → expect 201 ===")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 14, "planned_quantity": 92, "planned_yield_unit": "bottles", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder",
        "quantity": 1.5,
        "quantity_in_packs": 1.5,
        "unit": "kg",
        "break_pack_acknowledged": True,
        "break_pack_acknowledged_reason": "Override flow test — UI smoke",
    }]
}, token=tok)
print(f"HTTP {status}: {r.get('message')}")
req_id = r.get("data", {}).get("id")
print(q(f"SELECT id, requested_quantity, requested_quantity_in_packs, break_pack_acknowledged, break_pack_acknowledged_reason FROM requisition_items WHERE requisition_id={req_id};"))

# TEST D: Round-up path from the modal. User picks "Round up to 2" in
# the modal, so the form rewrites quantity to 2.0. Server accepts as
# whole pack.
print()
print("=== TEST D: Round up to 2 packs → expect 201, stored as 2 ===")
status, r = req("POST", "/api/production/requisitions.php", {
    "planned_recipe_id": 14, "planned_quantity": 92, "planned_yield_unit": "bottles", "priority": "normal",
    "items": [{
        "item_type": "ingredient", "item_id": 11, "item_name": "Chocolate Powder",
        "quantity": 2,                            # the ceil value
        "quantity_in_packs": 2,
        "unit": "kg",
    }]
}, token=tok)
print(f"HTTP {status}: {r.get('message')}")
req_id = r.get("data", {}).get("id")
print(q(f"SELECT id, requested_quantity, requested_quantity_in_packs, break_pack_acknowledged FROM requisition_items WHERE requisition_id={req_id};"))

print("\n=== ALL DONE ===")
