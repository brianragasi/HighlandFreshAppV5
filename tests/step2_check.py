#!/usr/bin/env python3
"""Step 2 verification: recipe endpoint now returns pack_count + enforce_whole_packs + current_stock."""
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

# Login
_, r = req("POST", "/api/auth/login.php",
           {"identifier":"production@gmail.com","password":"password"})
tok = r["data"]["token"]
print("Login OK")

# Set enforce_whole_packs=1 on Chocolate Powder X (id=11) — pack_size is 1 kg
subprocess.run(["C:\\xampp\\mysql\\bin\\mysql.exe", "-u", "root", "highland_fresh", "-e",
                "UPDATE ingredients SET enforce_whole_packs=1 WHERE id=11;"],
               capture_output=True)
print("Set enforce_whole_packs=1 on Chocolate Powder X (id=11) — pack_size is 1 kg\n")

# Use RCP-CHO-1L (id=14, Chocolate Milk, expected_yield=92 bottles)
RECIPE_ID = 14
PLANNED_QTY = 92

print(f"=== TEST: GET planned_recipe_items (recipe_id={RECIPE_ID}, planned_quantity={PLANNED_QTY}) ===")
status, r = req("GET", f"/api/production/requisitions.php?action=planned_recipe_items&recipe_id={RECIPE_ID}&planned_quantity={PLANNED_QTY}", token=tok)
print(f"HTTP {status}\n")

# Pretty-print just the items
items = r.get("data", {}).get("items", [])
print(f"Items returned: {len(items)}\n")
for it in items:
    if it.get("item_type") == "ingredient":
        print(f"  - {it['item_name']}")
        print(f"      quantity:        {it.get('quantity')} {it.get('unit')}")
        print(f"      pack_size_value: {it.get('pack_size_value')}")
        print(f"      pack_size_unit:  {it.get('pack_size_unit')}")
        print(f"      pack_label:      {it.get('pack_label')}")
        print(f"      pack_count:      {it.get('pack_count')}  <-- NEW")
        print(f"      enforce_whole_packs: {it.get('enforce_whole_packs')}  <-- NEW")
        print(f"      current_stock:   {it.get('current_stock')}  <-- NEW")
        print(f"      suggested_packs: {it.get('suggested_packs')}  (alias of pack_count)")
        print()
