#!/bin/bash
# Test the new no-GM-approval flow: warehouse fulfills directly from 'pending'
set -e
BASE="https://localhost/HighlandFreshAppV4"

# Login as warehouse_raw
LOGIN=$(curl -sk --max-time 5 -X POST "$BASE/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"warehouse_raw","password":"test1234"}')
TOKEN=*** "$LOGIN" | grep -oE '"token":"[^"]+"' | sed 's/"token":"//;s/"$//')
echo "Warehouse token: ${#TOKEN} chars"

# Test 1: legacy approve action should return 400 with migration message
echo ""
echo "---TEST A: PUT ?action=approve on production requisitions.php → expect 400---"
curl -sk --max-time 5 -X PUT "$BASE/api/production/requisitions.php?id=1" \
  -H "Authorization: Bearer *** \
  -H "Content-Type: application/json" \
  -d '{"action":"approve"}' | head -c 400
echo ""

# Test 2: warehouse fulfill action with status=pending should now work
# (use the new REQ-20260614-001 we just created)
echo ""
echo "---TEST B: Warehouse list should include the new pending REQ---"
curl -sk --max-time 5 "$BASE/api/warehouse/raw/requisitions.php?action=list&limit=3" \
  -H "Authorization: Bearer *** | head -c 800
echo ""

# Test 3: Get the new REQ id
echo ""
echo "---TEST C: Get detail of new REQ---"
curl -sk --max-time 5 "$BASE/api/warehouse/raw/requisitions.php?action=detail&id=18" \
  -H "Authorization: Bearer *** | python -c "import sys,json; d=json.load(sys.stdin); print('Status:', d['data']['requisition']['status']); print('Stock override ack:', d['data']['requisition']['stock_override']['acknowledged']); print('Warnings count:', len(d['data']['requisition']['stock_override']['warnings'])); print('First warning:', d['data']['requisition']['stock_override']['warnings'][0] if d['data']['requisition']['stock_override']['warnings'] else 'none')")" 2>&1 | head -20
