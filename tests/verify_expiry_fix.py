#!/usr/bin/env python3
"""Verify the expired-batch fix: create a req, try to fulfill, should fail with 0L tank stock."""
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

# Create a fresh req
status, r = req('POST', '/api/production/requisitions.php', {
    'planned_recipe_id': 14,
    'planned_quantity': 1,
    'purpose': 'Test fix for expired-batch bug',
    'priority': 'normal',
    'needed_by': None,
    'items': [{'item_type': 'raw_milk', 'item_id': None, 'item_name': 'Raw Milk', 'quantity': 10, 'unit': 'liters'}]
}, t=ptok)
print(f'Create requisition: {status}, code = {r.get("data", {}).get("requisition_code")}, id = {r.get("data", {}).get("id")}')
req_id = r.get('data', {}).get('id')

# Login as warehouse_raw
_, r = req('POST', '/api/auth/login.php', {'identifier':'warehouse_raw','password':'test1234'})
wtok = r['data']['token']

# Try to fulfill
status, r = req('POST', f'/api/warehouse/raw/requisitions.php?id={req_id}&action=fulfill',
               {'issued_quantities': {}, 'notes': 'Test fix'}, t=wtok)
print(f'Fulfill attempt: HTTP {status}')
print(f'  success: {r.get("success")}')
print(f'  message: {r.get("message")}')
if r.get('errors'):
    print(f'  errors: {r.get("errors")}')
