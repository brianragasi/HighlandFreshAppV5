#!/bin/bash
# Stock validation smoke test
set -e
BASE="https://localhost/HighlandFreshAppV4"

# Login
LOGIN=$(curl -sk --max-time 5 -X POST "$BASE/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"production_staff","password":"test1234"}')
TOKEN=$(echo "$LOGIN" | grep -oE '"token":"[^"]+"' | sed 's/"token":"//;s/"$//')
echo "Got token: ${#TOKEN} chars"

# Get a real ingredient with its current stock
ING=$(curl -sk --max-time 5 "$BASE/api/warehouse/raw/ingredients.php?action=list&limit=3" \
  -H "Authorization: Bearer $TOKEN")
echo "---ingredients sample---"
echo "$ING" | grep -oE '"id":[0-9]+|"ingredient_name":"[^"]+"|"current_stock":"?[0-9.]+"?|"unit_of_measure":"[^"]+"' | head -12

# Pick first ingredient id
ING_ID=$(echo "$ING" | grep -oE '"id":[0-9]+' | head -1 | sed 's/"id"://')
ING_NAME=$(echo "$ING" | grep -oE '"ingredient_name":"[^"]+"' | head -1 | sed 's/"ingredient_name":"//;s/"$//')
ING_UNIT=$(echo "$ING" | grep -oE '"unit_of_measure":"[^"]+"' | head -1 | sed 's/"unit_of_measure":"//;s/"$//')
ING_STOCK=$(echo "$ING" | grep -oE '"current_stock":"?[0-9.]+"?|stock_status":"[a-z_]+"' | head -2)
echo ""
echo "===> Using: id=$ING_ID name=$ING_NAME unit=$ING_UNIT"
echo "===> Stock: $ING_STOCK"

echo ""
echo "---TEST 1: POST with quantity way over stock, NO override flag → expect 422---"
RESP1=$(curl -sk --max-time 5 -X POST "$BASE/api/production/requisitions.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"planned_recipe_id\":1,\"planned_quantity\":10,\"planned_yield_unit\":\"$ING_UNIT\",\"priority\":\"normal\",\"items\":[{\"item_type\":\"ingredient\",\"item_id\":$ING_ID,\"item_name\":\"$ING_NAME\",\"quantity\":99999,\"unit\":\"$ING_UNIT\"}]}")
echo "$RESP1" | head -c 700
echo ""

echo ""
echo "---TEST 2: POST with same qty, WITH override flag → expect 201 + audit row---"
RESP2=$(curl -sk --max-time 5 -X POST "$BASE/api/production/requisitions.php" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"planned_recipe_id\":1,\"planned_quantity\":10,\"planned_yield_unit\":\"$ING_UNIT\",\"priority\":\"normal\",\"stock_override_acknowledged\":true,\"stock_override_reason\":\"Smoke test - PO-2026-001 incoming\",\"items\":[{\"item_type\":\"ingredient\",\"item_id\":$ING_ID,\"item_name\":\"$ING_NAME\",\"quantity\":99999,\"unit\":\"$ING_UNIT\"}]}")
echo "$RESP2" | head -c 700
echo ""

# Verify audit row was created
echo ""
echo "---TEST 3: Verify audit row in requisition_stock_warnings---"
cd /c/xampp/mysql/bin && ./mysql.exe -u root highland_fresh -e "SELECT id, requisition_id, item_name, requested_qty, available_qty, shortage, decision, decided_role, override_reason FROM requisition_stock_warnings ORDER BY id DESC LIMIT 3;" 2>&1
