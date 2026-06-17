#!/usr/bin/env python3
"""Verify pasteurization flow end-to-end."""
import json, urllib.request, ssl, subprocess
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

def q(sql):
    r = subprocess.run(['C:\\xampp\\mysql\\bin\\mysql.exe', '-u', 'root', 'highland_fresh', '-e', sql],
                       capture_output=True, text=True)
    return r.stdout

# Login
_, r = req('POST', '/api/auth/login.php',
           {'identifier':'production@gmail.com','password':'password'})
tok = r['data']['token']
print('Login OK\n')

# 1. available_raw_milk
print('=== 1. GET available_raw_milk ===')
status, r = req('GET', '/api/production/pasteurization.php?action=available_raw_milk', t=tok)
print(f'  HTTP {status}, available_liters = {r.get("data", {}).get("available_liters")}')

# 2. Create run
print('\n=== 2. POST create pasteurization run ===')
status, r = req('POST', '/api/production/pasteurization.php', {
    'input_liters': 50.0,
    'temperature': 75.5,
    'duration_mins': 15,
    'notes': 'smoke test - verifying pasteurization flow end-to-end'
}, t=tok)
print(f'  HTTP {status}: {r.get("message")}')
print(f'  data: {r.get("data")}')
run_id = r.get('data', {}).get('id')

# 3. List runs
print('\n=== 3. GET runs list ===')
status, r = req('GET', '/api/production/pasteurization.php?action=runs', t=tok)
runs = r.get('data', {}).get('runs', r.get('data', []))
print(f'  HTTP {status}, {len(runs)} total runs')
for run in runs[:3]:
    print(f'    {run.get("run_code")}: status={run.get("status")}, input={run.get("input_milk_liters")}L, output={run.get("output_milk_liters")}L')

# 4. Complete run
print('\n=== 4. PUT complete run ===')
status, r = req('PUT', f'/api/production/pasteurization.php?id={run_id}', {
    'action': 'complete', 'output_liters': 49.2, 'expiry_days': 2
}, t=tok)
print(f'  HTTP {status}: {r.get("message")}')
print(f'  data: {r.get("data")}')

# 5. pasteurized_milk_inventory for the new batch
print('\n=== 5. pasteurized_milk_inventory for the new batch ===')
print(q(f"SELECT batch_code, source_type, source_run_id, quantity_liters, remaining_liters, pasteurization_temp, expiry_date, status FROM pasteurized_milk_inventory WHERE source_run_id={run_id};"))

# 6. Pasteurization runs table final state
print('=== 6. pasteurization_runs final state ===')
print(q(f"SELECT id, run_code, input_milk_liters, output_milk_liters, shrinkage_percent, status FROM pasteurization_runs WHERE id={run_id};"))
