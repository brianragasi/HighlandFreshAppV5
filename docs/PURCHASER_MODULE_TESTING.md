# Purchaser Module - Testing Guide

## Prerequisites

1. **Run the SQL migration** to add new tables and columns:
   ```sql
   -- Execute: sql/purchaser_enhancements.sql
   SOURCE c:/xampp/htdocs/HighlandFreshAppV4/sql/purchaser_enhancements.sql;
   ```

2. **User accounts required:**
   - Purchaser role: For creating POs, canvassing
   - General Manager role: For approvals

---

## Complete Workflow Test

### Phase 1: Trigger (Low Stock → Automatic Alert)

**Currently in system:** Material requisitions are created manually or from production runs when ingredient stock is low.

**Test Steps:**
1. Login as **Production Staff**
2. Navigate to Production → Material Requisitions
3. Create a new requisition with:
   - Select ingredient (e.g., Fresh Milk)
   - Quantity needed
   - Priority: High
   - Purpose: "Running low for production"
4. Submit requisition → Status becomes "pending"

*The Purchaser will see this in their dashboard.*

---

### Phase 2: Validate (Canvassing - Rule of 3 Quotes)

**Test Steps:**
1. Login as **Purchaser**
2. Navigate to Purchasing → Canvassing (if page exists) or use API directly

**API Test - Create Canvass:**
```javascript
// Browser console (on login page after auth)
fetch('/api/purchasing/canvassing.php?action=create', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + localStorage.getItem('token')
    },
    body: JSON.stringify({
        item_type: 'ingredient',
        item_id: 1,            // ID of ingredient
        quantity: 100,
        unit: 'liters',
        remarks: 'Need quotes for fresh milk'
    })
}).then(r => r.json()).then(console.log);
```

**API Test - Add Quotes (Rule of 3):**
```javascript
// Add 3 quotes from different suppliers
fetch('/api/purchasing/canvassing.php?action=add_quote', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + localStorage.getItem('token')
    },
    body: JSON.stringify({
        canvass_id: 1,         // From create response
        supplier_id: 1,
        unit_price: 55.00,
        delivery_days: 2,
        remarks: 'Best price, quick delivery'
    })
}).then(r => r.json()).then(console.log);

// Repeat for 2 more suppliers with different prices
```

**API Test - Select Winner:**
```javascript
fetch('/api/purchasing/canvassing.php?action=select_quote', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + localStorage.getItem('token')
    },
    body: JSON.stringify({
        canvass_id: 1,
        quote_id: 2    // The winning quote ID
    })
}).then(r => r.json()).then(console.log);
```

---

### Phase 3: Draft PO (With Payment Terms)

**Test Steps:**
1. Login as **Purchaser**
2. Navigate to Purchasing → Purchase Orders
3. Click **New Purchase Order**
4. Fill in:
   - **Supplier**: Select from dropdown (preferably the canvass winner)
   - **Order Date**: Today
   - **Delivery Address**: Warehouse address
   - **Payment Terms**: Select one:
     - Cash (COD)
     - Credit 7 Days
     - Credit 15 Days
     - Credit 30 Days
     - Credit 45 Days
     - Credit 60 Days
   - **Linked Requisition** (optional): Select a pending requisition
5. Add items:
   - Item Type: Ingredient
   - Item: Fresh Milk
   - Quantity: 100
   - Unit: liters
   - Unit Price: 55.00 (from canvass)
6. Click **Create PO**
7. **Expected**: PO created with status "draft"

**Verify in Database:**
```sql
SELECT po_number, payment_terms, due_date, requisition_id, status 
FROM purchase_orders 
ORDER BY id DESC LIMIT 1;
```

---

### Phase 4: Submit for Approval

**Test Steps:**
1. Still as **Purchaser**
2. In PO list, find the draft PO
3. Click **Submit** button
4. **Expected**: Status changes to "pending"

---

### Phase 5: GM Approval

**Test Steps:**
1. Login as **General Manager**
2. Navigate to Admin → Approvals Dashboard (`/html/admin/gm_approvals.html`)
3. You should see:
   - Stats: Pending POs count, Pending Requisitions
   - List of pending POs with details
   - Approve/Reject buttons
4. Click **Approve** on the test PO
5. **Expected**: PO status becomes "approved"

**Alternative - Reject:**
1. Click **Reject**
2. Enter reason: "Price too high, renegotiate"
3. **Expected**: PO status becomes "rejected"

---

### Phase 6: Mark Ordered / Received (with Price Updates)

**Test Steps:**
1. Login as **Purchaser**
2. Navigate to Purchase Orders
3. Find the approved PO
4. Click **Mark as Ordered** → Status becomes "ordered"
5. When goods arrive, click **Mark as Received**
6. **NEW: Receive Modal appears** with:
   - List of items
   - Current price (from PO)
   - Actual price paid (editable)
7. If prices changed, update the actual amounts
8. Click **Confirm Receive**
9. **Expected**:
   - PO status becomes "received"
   - If prices differ, new record in `ingredient_price_history`
   - Ingredient `market_price` and `last_price_update` updated

**Verify Price History:**
```sql
SELECT iph.*, i.name as ingredient_name, s.name as supplier_name
FROM ingredient_price_history iph
JOIN ingredients i ON iph.ingredient_id = i.id
LEFT JOIN suppliers s ON iph.supplier_id = s.id
ORDER BY iph.created_at DESC LIMIT 5;
```

---

### Phase 7: Trend Monitoring (Price Alerts)

**Test Steps:**
1. Login as **General Manager**
2. Navigate to Approvals Dashboard
3. Scroll to **Recent Price Changes** section
4. If prices changed significantly (±10%), alerts will appear

**API Test - Get Price Alerts:**
```javascript
fetch('/api/admin/gm_approvals.php?action=price_alerts', {
    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
}).then(r => r.json()).then(console.log);
```

**API Test - Get Price History for Item:**
```javascript
fetch('/api/purchasing/canvassing.php?action=price_history&item_type=ingredient&item_id=1', {
    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
}).then(r => r.json()).then(console.log);
```

---

## Quick Test Checklist

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create PO with payment terms "credit_30" | PO has payment_terms and due_date calculated |
| 2 | Link a requisition to PO | requisition_id populated |
| 3 | Submit PO for approval | Status = "pending" |
| 4 | GM approves | Status = "approved" |
| 5 | Purchaser marks received with new price | Price history logged |
| 6 | GM views price alerts | Shows significant changes |

---

## Troubleshooting

### Error: Table doesn't exist
Run the SQL migration:
```bash
mysql -u root highland_fresh < sql/purchaser_enhancements.sql
```

### Error: Column payment_terms unknown
Check if ALTER TABLE ran:
```sql
DESCRIBE purchase_orders;
-- Should show: payment_terms, due_date, requisition_id columns
```

### GM Approval page shows "Access denied"
- Ensure logged-in user has `role = 'general_manager'`
- Check users table:
```sql
SELECT id, username, role FROM users WHERE role = 'general_manager';
```

### Price history not recording
- Check that ingredient_price_history table exists
- Verify the receive_with_prices action is being called (check network tab)

---

## Sample Data for Testing

**Create a test supplier:**
```sql
INSERT INTO suppliers (name, contact_person, phone, email, status, created_at)
VALUES ('Test Supplier A', 'Juan Dela Cruz', '09171234567', 'supplier@test.com', 'active', NOW());
```

**Create test ingredient:**
```sql
INSERT INTO ingredients (code, name, unit, current_stock, reorder_level, market_price, status)
VALUES ('ING-TEST', 'Test Ingredient', 'kg', 50, 100, 45.00, 'active');
```

**Quick PO via API:**
```javascript
const createPO = async () => {
    const resp = await fetch('/api/purchasing/purchase_orders.php?action=create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        },
        body: JSON.stringify({
            supplier_id: 1,
            order_date: new Date().toISOString().split('T')[0],
            delivery_address: 'Highland Fresh Warehouse',
            payment_terms: 'credit_30',
            items: [{
                item_type: 'ingredient',
                item_id: 1,
                item_code: 'ING-001',
                item_description: 'Fresh Milk',
                quantity: 100,
                unit: 'liters',
                unit_price: 55.00,
                total_amount: 5500.00
            }]
        })
    });
    console.log(await resp.json());
};
createPO();
```

---

## Files Modified/Created

| File | Type | Description |
|------|------|-------------|
| `sql/purchaser_enhancements.sql` | New | Migration script |
| `api/purchasing/purchase_orders.php` | Modified | Payment terms, receive_with_prices |
| `api/purchasing/canvassing.php` | New | Canvass/quote management |
| `api/admin/gm_approvals.php` | New | GM approval dashboard API |
| `js/purchasing/purchasing.service.js` | Modified | New service methods |
| `html/purchasing/purchase_orders.html` | Modified | Payment terms UI, receive modal |
| `html/admin/gm_approvals.html` | New | GM approval dashboard page |
