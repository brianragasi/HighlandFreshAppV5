#!/usr/bin/env python3
"""Trigger the V4.0 B-step migration by hitting the API logged in."""
import json, urllib.request, ssl
ctx = ssl.create_default_context(); ctx.check_hostname=False; ctx.verify_mode=ssl.CERT_NONE
BASE = "https://localhost/HighlandFreshAppV4"

# Login
r = urllib.request.Request(BASE + "/api/auth/login.php",
    data=json.dumps({"identifier":"production@gmail.com","password":"password"}).encode(),
    headers={"Content-Type":"application/json"}, method="POST")
with urllib.request.urlopen(r, timeout=5, context=ctx) as resp:
    tok = json.loads(resp.read())["data"]["token"]
print(f"Login OK, token {len(tok)} chars")

# Hit requisitions list to trigger migration
r = urllib.request.Request(BASE + "/api/production/requisitions.php?limit=1",
    headers={"Authorization": f"Bearer {tok}"})
with urllib.request.urlopen(r, timeout=5, context=ctx) as resp:
    body = resp.read().decode()
print(f"GET requisitions: HTTP {resp.status}, {len(body)} bytes")
print(body[:120])
