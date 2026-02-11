# QC Module Testing Guide

> **Purpose:** Step-by-step instructions to test the Disposal and Batch Recall workflows  
> **Last Updated:** February 9, 2026

---

## Prerequisites

### 1. Start XAMPP
```
1. Open XAMPP Control Panel
2. Start Apache
3. Start MySQL
```

### 2. Test Accounts

All passwords are: `password`

| Role | Username | Use For |
|------|----------|---------|
| QC Officer | `qc_officer` | Creating disposals, recalls, viewing QC dashboard |
| General Manager | `general_manager` | Approving disposals, escalated recalls |
| Warehouse FG | `warehouse_fg` | Executing FG disposals, logging returns |
| Warehouse Raw | `warehouse_raw` | Executing raw material disposals |
| Sales Custodian | `sales_custodian` | Viewing recalls, notifying customers |

### 3. URLs
- Login: `http://localhost/HighlandFreshAppV4/html/login.html`
- QC Dashboard: `http://localhost/HighlandFreshAppV4/html/qc/dashboard.html`

---

## Part A: Testing the DISPOSAL Workflow

This tests the full lifecycle: **Create → Approve → Execute**

### Step 1: Create a Disposal Request (QC Officer)

1. **Login** as `qc_officer` / `password`
2. Navigate to **QC > Disposals** (sidebar)
3. Click **"+ New Disposal"** button (top right)
4. Fill in the form:
   - **Source Type**: `Finished Goods`
   - **Product**: Select any product (e.g., "Fresh Milk 1L")
   - **Quantity**: `5` (or any small number)
   - **Disposal Category**: Select the appropriate category:
     - `Expired` - for already expired products
     - `Near Expiry` - for products expiring soon (auto-selected if item shows "Expiring Soon")
     - `QC Failed` - for products that failed quality tests
   - **Disposal Reason**: "Testing disposal workflow"
   - **Disposal Method**: `Drain`
   - **Notes**: "Test disposal - delete later"
5. Click **"Submit Request"**
6. **Expected Result**: 
   - Toast notification: "Disposal request submitted"
   - New row appears in the "Pending" tab with status badge
   - Disposal code generated (e.g., `DSP-20260209-0001`)

### Step 2: Approve the Disposal (General Manager)

1. **Logout** (click avatar > Logout)
2. **Login** as `general_manager` / `password`
3. Navigate to **QC > Disposals**
4. Find your disposal in the "Pending" tab
5. Click **View** to see details
6. Click **"Approve"**
7. Enter approval notes: "Approved for testing"
8. Click **Confirm**
9. **Expected Result**:
   - Status changes from `pending` to `approved`
   - Disposal moves to "Approved" tab
   - Approved by name and timestamp shown

### Step 3: Execute the Disposal (Warehouse FG)

1. **Logout**
2. **Login** as `warehouse_fg` / `password`
3. Go to **Warehouse FG Dashboard**
4. Look for the **"Approved Disposals - Ready for Execution"** section
5. Find your approved disposal
6. Click **"Complete"**
7. Fill in execution details:
   - **Witness Name**: "Test Witness" (optional)
   - **Disposal Location**: "Test Area"
   - **Execution Notes**: "Successfully executed test disposal"
8. Click **"Complete Disposal"**
9. **Expected Result**:
   - Status changes to `executed`
   - Disposal disappears from the dashboard (no longer pending)
   - Inventory is deducted

### Step 4: Verify in QC Module

1. **Logout**
2. **Login** as `qc_officer`
3. Go to **QC > Disposals**
4. Use the **Status filter dropdown** and select "Completed"
5. **Expected Result**: Your disposal appears with status badge showing "Completed" and full audit trail

---

## Part B: Testing the RECALL Workflow

This tests: **Create Recall → Notify Locations → Customer Notification → Returns**

### Step 1: Create a Batch Recall (QC Officer)

1. **Login** as `qc_officer` / `password`
2. Navigate to **QC > Recalls** (sidebar)
3. Click **"+ New Recall"** (top right)
4. Fill in the form:
   - **Batch Code**: Enter a valid batch code from production (e.g., `BATCH-20260209-001`)
     - You can find batch codes in **Warehouse FG > Inventory** table
   - **Recall Classification**: `Class I - Dangerous` (for testing)
   - **Severity Level**: `Major`
   - **Reason**: "Testing recall workflow"
   - **Evidence Notes**: "Test evidence"
5. Click **"Initiate Recall"**
6. **Expected Result**:
   - Recall code generated (e.g., `RCL-20260209-001`)
   - Status: `pending_approval`
   - Appears in the recalls list

### Step 2: Notify Affected Locations (QC Officer)

1. Still logged in as `qc_officer`
2. Find your recall in the list
3. Click **View Details**
4. In the "Affected Locations" section, you should see:
   - Location name with phone and email icons
5. Click the **phone icon** or **email icon** next to a location
6. **Expected Result**: 
   - A notification modal or confirmation appears
   - In a real system, this would send SMS/email

### Step 3: Check Sales Dashboard (Sales Custodian)

1. **Logout**
2. **Login** as `sales_custodian` / `password`
3. Go to **Sales Dashboard**
4. Look for the **"Active Recalls"** section in the sidebar or main content
5. **Expected Result**:
   - Your recall appears in the list
   - Shows product, batch, and "Notify Customer" button
6. Click **"Notify"** on a customer/recall
7. **Expected Result**: Notification confirmation

### Step 4: Log Product Returns (Warehouse FG)

1. **Logout**
2. **Login** as `warehouse_fg` / `password`
3. Go to **Warehouse FG Dashboard**
4. Look for the **"Active Recalls - Log Product Returns"** section
5. Find your recall
6. Click **"Log Return"**
7. Fill in:
   - **Quantity Returned**: `10`
   - **Condition**: Select a condition
   - **Notes**: "Customer returned 10 units"
8. Click **Submit**
9. **Expected Result**: Return logged, recall stats updated

### Step 5: Create Disposal from Recall (QC Officer)

1. **Logout**
2. **Login** as `qc_officer`
3. Go to **QC > Disposals**
4. Click **"+ New Disposal"**
5. This time, note the **"Related Recall"** dropdown
6. Select your recall from the dropdown
7. Fill in other details as before
8. Submit
9. **Expected Result**: Disposal is linked to the recall for traceability

### Step 6: Close the Recall (QC Officer)

1. Go to **QC > Recalls**
2. Find your recall
3. Click **View** then **"Close Recall"**
4. Enter close notes: "All affected products recovered/disposed"
5. Click **Confirm**
6. **Expected Result**: Status changes to `closed`

---

## Part C: Testing Error Scenarios

### C1: Warehouse Tries to Create Disposal (Should Fail)

1. **Login** as `warehouse_fg` / `password`
2. Navigate to **QC > Disposals**
3. **Expected Result**: 
   - Either no access (redirect or 403)
   - OR the page loads but **no "New Disposal" button** is visible
4. If you try API directly:
   ```bash
   # This should return 403 Forbidden
   curl -X POST http://localhost/HighlandFreshAppV4/api/qc/disposals.php \
     -H "Authorization: Bearer <warehouse_token>" \
     -d '{"source_type":"finished_goods",...}'
   ```

### C2: Reject a Disposal (General Manager)

1. Create a new disposal as QC Officer
2. Login as `general_manager`
3. Instead of Approve, click **"Reject"**
4. Enter reason: "Insufficient documentation"
5. **Expected Result**: Status becomes `rejected`, cannot be executed

### C3: Cancel a Pending Disposal (QC Officer)

1. Create a disposal, don't submit for approval yet
2. Find it in "Pending" tab
3. Click **"Cancel"**
4. **Expected Result**: 
   - Status becomes `cancelled`
   - Any reserved inventory is restored

---

## Part D: Quick Checklist

Use this to verify the system is working:

| # | Test | Expected | ✓ |
|---|------|----------|---|
| 1 | QC can create disposal | Form shows, submits successfully | |
| 2 | GM can approve disposal | Status changes to approved | |
| 3 | Warehouse can execute | Complete button works, status = executed | |
| 4 | Warehouse CANNOT create | No button or 403 error | |
| 5 | QC can create recall | Form shows, submits successfully | |
| 6 | QC can notify locations | Phone/email buttons work | |
| 7 | Sales sees active recalls | Dashboard shows recall section | |
| 8 | Warehouse can log returns | Return form works | |
| 9 | Disposal can link to recall | Dropdown shows related recalls | |
| 10 | QC can close recall | Status changes to closed | |

---

## Troubleshooting

### "Loading disposals..." stays forever
- Check browser console (F12) for errors
- Verify API is running: `http://localhost/HighlandFreshAppV4/api/qc/disposals.php?status=approved`
- Check PHP error log in XAMPP

### 403 Forbidden on API calls
- Token may be expired - logout and login again
- Verify the user has the correct role

### No data showing
- Run `setup.php` to populate sample data
- Check database tables have data: `qc_disposals`, `batch_recalls`

### Page redirects to login
- Session expired - login again
- Check localStorage has valid token: `localStorage.getItem('auth_token')`

---

## API Endpoints Reference

| Endpoint | Method | QC | GM | Warehouse |
|----------|--------|----|----|-----------|
| `/api/qc/disposals.php` | GET | ✓ | ✓ | ✓ (filtered) |
| `/api/qc/disposals.php` | POST (create) | ✓ | ✓ | ✗ |
| `/api/qc/disposals.php` | PUT (approve) | ✗ | ✓ | ✗ |
| `/api/qc/disposals.php` | PUT (execute) | ✓ | ✓ | ✓ |
| `/api/qc/recalls.php` | GET | ✓ | ✓ | ✓ (filtered) |
| `/api/qc/recalls.php` | POST (create) | ✓ | ✓ | ✗ |
| `/api/qc/recalls.php` | PUT (update) | ✓ | ✓ | ✗ |

---

## Cleaning Up Test Data

After testing, you may want to remove test records:

```sql
-- Delete test disposals (be careful!)
DELETE FROM qc_disposals WHERE disposal_reason LIKE '%testing%';

-- Delete test recalls (be careful!)
DELETE FROM batch_recalls WHERE recall_reason LIKE '%testing%';
```

Or simply re-run `setup.php` to reset the database.
