#!/usr/bin/env python3
"""Verify the catalog walk made the system enforce across the whole catalog."""
import json, urllib.request, ssl, subprocess
ctx = ssl.create_default_context(); ctx.check_hostname=False; ctx.verify_mode=ssl.CERT_NONE
BASE = "https://localhost/HighlandFreshAppV4"
GREEN = "\033[92m"; RED = "\033[91m"; RESET = "\033[0m"

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
print("Login OK\n")

# Try to submit a "weird" quantity for each configured ingredient
# e.g., 0.5 kg Sugar (pack=25) → 0.5/25 = 0.02 packs → 0.02 ≠ integer → 422
# e.g., 0.3 L Vanilla (pack=1) → 0.3/1 = 0.3 → fractional → 422
# e.g., 0.5 L Food Coloring (pack=0.5) → 0.5/0.5 = 1.0 → integer → 201

print("=== FRACTIONAL: should all 422 pack_fractional ===")
weird_qtys = [
    (9, "Sugar", 0.5, "kg", "0.5 kg = 0.02 sacks, fractional"),
    (10, "Vanilla Extract", 0.3, "liter", "0.3 L = 0.3 bottles, fractional"),
    (12, "Stabilizer", 0.3, "kg", "0.3 kg = 0.6 packets, fractional"),
    (14, "Salt", 2.5, "kg", "2.5 kg = 2.5 bags, fractional"),
    (15, "Rennet", 0.5, "liter", "0.5 L = 0.5 bottles, fractional"),
    (16, "Food Coloring", 0.25, "liter", "0.25 L = 0.5 bottles, fractional"),
]
for ing_id, name, qty, unit, reason in weird_qtys:
    status, r = req("POST", "/api/production/requisitions.php", {
        "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
        "items": [{
            "item_type": "ingredient", "item_id": ing_id, "item_name": name,
            "quantity": qty, "unit": unit,
        }]
    }, token=tok)
    if status == 422 and r.get("errors", {}).get("error_code") == "pack_fractional":
        off = r["errors"]["pack_check"]["items"][0]
        print(f"  {GREEN}{name} {qty} {unit}{RESET} -> 422, ceil to {off['ceil_packs']} packs ({off['ceil_base']} {unit})")
    else:
        print(f"  {RED}{name} {qty} {unit}{RESET} -> HTTP {status}: {r.get('message')[:80]}")

print()
print("=== WHOLE: should all 201 ===")
whole_qtys = [
    (9, "Sugar", 25, "kg", "exactly 1 sack"),
    (10, "Vanilla Extract", 2, "liter", "exactly 2 bottles"),
    (11, "Chocolate Powder X", 1, "kg", "exactly 1 bag"),
    (12, "Stabilizer", 0.5, "kg", "exactly 1 packet"),
    (13, "Cultures (Yogurt)", 1, "packet", "exactly 1 packet"),
    (14, "Salt", 1, "kg", "exactly 1 bag"),
    (15, "Rennet", 1, "liter", "exactly 1 bottle"),
    (16, "Food Coloring", 0.5, "liter", "exactly 1 bottle"),
]
for ing_id, name, qty, unit, reason in whole_qtys:
    status, r = req("POST", "/api/production/requisitions.php", {
        "planned_recipe_id": 1, "planned_quantity": 10, "planned_yield_unit": "L", "priority": "normal",
        "items": [{
            "item_type": "ingredient", "item_id": ing_id, "item_name": name,
            "quantity": qty, "unit": unit,
        }]
    }, token=tok)
    if status == 201:
        print(f"  {GREEN}{name} {qty} {unit}{RESET} -> 201, accepted")
    else:
        print(f"  {RED}{name} {qty} {unit}{RESET} -> HTTP {status}: {r.get('message')[:80]}")

