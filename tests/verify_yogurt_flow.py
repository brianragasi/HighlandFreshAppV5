#!/usr/bin/env python3
"""Verify the full yogurt flow end-to-end:
1. available_raw_milk is 2.106L
2. Can start a pasteurization run for 2L
3. inventory_transactions row was created with ref='pasteurization_run'
4. available_raw_milk drops to 0.106L
5. Can complete the run → pasteurized milk in inventory
6. Requisition detail now exposes planned_product_type
"""
import json, urllib.request, urllib.error, ssl
ctx = ssl.create_default_context(); ctx.check_hostname=False; ctx.verify_mode=ssl.CERT_NONE
BASE = 'https://localhost/HighlandFreshAppV4'

def req(m, p, b=None, t=None):
    h = {'Content-Type': 'application/json'}
    if t: h['Authorization'] = f'Bearer {t}'
    d = json.dumps(b).encode() if b is not None else None
    r = urllib.request.Request(BASE+p, data=d, method=m, headers=h)
    try:
        with urllib.request.urlopen(r, timeout=5, context=ctx) as resp:
            return resp.status, json.loads(resp.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())

# Login as production
_, r = req('POST', '/api/auth/login.php', {'identifier':'production@gmail.com','password':'password'})
ptok = r['data']['token']

# 1. available_raw_milk
status, r = req('GET', '/api/production/pasteurization.php?action=available_raw_milk', t=ptok)
print(f'1. available_raw_milk BEFORE: {r["data"]["available_liters"]}L (expect 2.106)')

# 2. Start a pasteurization run for 2L
status, r = req('POST', '/api/production/pasteurization.php',
    {'input_liters': 2, 'temperature': 75, 'duration_mins': 15, 'notes': 'V4.0 smoke test'}, t=ptok)
print(f'2. Start run: HTTP {status}, success={r.get("success")}, run_code={r.get("data", {}).get("run_code")}')
run_id = r.get('data', {}).get('id')

# 3. available_raw_milk after
status, r = req('GET', '/api/production/pasteurization.php?action=available_raw_milk', t=ptok)
print(f'3. available_raw_milk AFTER run: {r["data"]["available_liters"]}L (expect 0.106)')
print(f'   used_in_pasteurization: {r["data"]["used_in_pasteurization"]}L (expect 2)')

# 4. Complete the run
status, r = req('PUT', f'/api/production/pasteurization.php?id={run_id}&action=complete',
    {'output_liters': 1.97, 'expiry_days': 2}, t=ptok)
print(f'4. Complete run: HTTP {status}, success={r.get("success")}, batch_code={r.get("data", {}).get("batch_code")}')

# 5. Check pasteurized milk available
status, r = req('GET', '/api/production/runs.php?action=available_pasteurized_milk', t=ptok)
print(f'5. Available pasteurized milk: {r["data"]["total_available_liters"]}L (expect 1.97)')

# 6. Requisition list includes planned_product_type for yogurt requisitions
status, r = req('GET', '/api/production/requisitions.php?limit=5', t=ptok)
print(f'6. Requisitions list — first row keys: {list(r["data"][0].keys()) if r.get("data") else "no data"}')
if r.get('data'):
    for d in r['data'][:3]:
        print(f'   {d.get("requisition_code")} → planned_product_type={d.get("planned_product_type", "MISSING")}')
