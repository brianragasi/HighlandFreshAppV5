# Pack-Size Preview — Test Plan

Verifies the "auto-convert recipe quantity to supplier packs" feature that
fixes the professor's complaint:
> "Dili pwede nimo siya i-manual nga ay i-compute nako..."
> (You can't make them do it manually like 'oh I need to compute this')

## Layer 1 — Algorithm (no DB needed)

```
node tests/pack_preview.test.js
```

Verifies the math + formatting logic in isolation. Headline test:
`0.11 L of cultures + 100 mL pack size → 2 packs`. 23 assertions covering
rounding, pluralization, unit families (L, mL, kg, pcs), and edge cases
(0, NaN, missing values).

## Layer 2 — SQL migration

After importing `sql/add_ingredient_pack_size.sql` into your DB:

```sql
-- Verify the 3 new columns exist
SHOW COLUMNS FROM ingredients LIKE 'pack_size%';
SHOW COLUMNS FROM ingredients LIKE 'pack_label';
```

Expected: 3 rows, all NULL allowed. No data loss — existing rows keep
working as before.

## Layer 3 — API smoke test (with XAMPP running)

Replace `<TOKEN>` with a real JWT from the login endpoint.

```bash
# 1. Login as a warehouse_raw / production_staff / GM user
TOKEN=$(curl -sk -X POST https://localhost/HighlandFreshAppV4/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"identifier":"YOUR_USER","password":"YOUR_PASS"}' \
  | python -c "import sys, json; print(json.load(sys.stdin)['data']['token'])")

# 2. Confirm the catalog returns the new pack columns
curl -sk "https://localhost/HighlandFreshAppV4/api/warehouse/raw/ingredients.php?action=list" \
  -H "Authorization: Bearer $TOKEN" \
  | python -c "import sys, json; data = json.load(sys.stdin)['data']['ingredients']; print(json.dumps([{k: i[k] for k in ('id','ingredient_name','pack_size_value','pack_size_unit','pack_label') if k in i} for i in data[:3]], indent=2))"

# 3. Set pack size on an ingredient (e.g. Cultures, id=11)
curl -sk -X PUT "https://localhost/HighlandFreshAppV4/api/warehouse/raw/ingredients.php?id=11" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pack_size_value":0.1,"pack_size_unit":"L","pack_label":"100 mL packet"}'

# 4. Get planned recipe items — should now include pack fields
curl -sk "https://localhost/HighlandFreshAppV4/api/production/requisitions.php?action=planned_recipe_items&recipe_id=12&planned_quantity=1000" \
  -H "Authorization: Bearer $TOKEN" \
  | python -m json.tool | grep -E "pack|quantity|item_name" | head -40
```

Expected: each ingredient item in the response carries
`pack_size_value`, `pack_size_unit`, `pack_label`, `suggested_packs`,
`pack_hint`. The notes field should end with something like
"Rounded up from 0.11 L -> 2 packs".

## Layer 4 — UI walkthrough (browser)

1. Open `html/admin/ingredients.html` as GM or Purchaser.
2. Edit any ingredient (e.g. Cultures).
3. Confirm the "Supplier Pack Size" section appears under Unit of Measure.
4. Fill in: Pack Value = 100, Pack Unit = ml, Display Label = "100 mL packet".
   Save.
5. Open `html/production/requisitions.html` as production staff.
6. New Requisition → pick a recipe that uses Cultures → type planned qty.
7. In the items table, the Cultures row should show:
   - Quantity: 0.11 L (or whatever the math produces)
   - Pack preview: `🟦 2 packs  from 0.11 L  ·  100 mL packet`
8. Add a manual item row, pick Cultures from the dropdown. Type 0.5.
   Preview updates live to: `🟦 5 packs  from 0.5 L  ·  100 mL packet`.
9. Change the qty to 250 → preview updates to `🟦 3 packs  from 250 ml  ·  100 mL packet`.
10. Open the requisition detail view as GM/Warehouse. Cultures row's
    "Notes" column should mention the rounded-up pack count.
11. Try an ingredient WITHOUT a pack size (e.g. Raw Milk if it has no
    pack). The preview area should be hidden, no errors.

## Layer 5 — Regression

Smoke-test that the feature doesn't break existing flows:

- Creating an ingredient WITHOUT pack fields still works (column
  accepts null).
- Updating an ingredient and clearing the pack fields clears the
  columns to null.
- The "Run Production" flow still works (`batches.html` has its own
  unit conversion for output; should be unaffected).
- The "Receive Deliveries" flow still works (separate scope, not
  touched).
