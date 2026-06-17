#!/usr/bin/env python3
"""Complete the existing in-progress pasteurization run to verify the full flow."""
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

# Complete the existing in-progress run
print('=== Complete the existing in-progress run PAST-20260220-001 ===')
status, r = req('PUT', '/api/production/pasteurization.php?id=1', {
    'action': 'complete', 'output_liters': 98.5, 'expiry_days': 2
}, t=tok)
print(f'  HTTP {status}: {r.get("message")}')
print(f'  data: {r.get("data")}')

print('\n=== pasteurized_milk_inventory for source_run_id=1 ===')
print(q("SELECT id, batch_code, source_type, source_run_id, quantity_liters, remaining_liters, pasteurization_temp, expiry_date, status FROM pasteurized_milk_inventory WHERE source_run_id=1;"))

print('=== pasteurization_runs final state ===')
print(q("SELECT id, run_code, input_milk_liters, output_milk_liters, shrinkage_percent, status FROM pasteurization_runs WHERE id=1;"))

print('=== Total available pasteurized milk ===')
print(q("SELECT COUNT(*) AS batches, SUM(remaining_liters) AS total_liters FROM pasteurized_milk_inventory WHERE status='available';"))
