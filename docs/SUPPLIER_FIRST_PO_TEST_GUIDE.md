# Complete Warehouse-to-Supplier Purchase Order Test Guide

## The finished flow

1. The system marks a raw material as low stock.
2. Warehouse counts the actual quantity on the shelf and confirms it in **Stock Validation**.
3. Purchasing reviews each confirmed shortage and chooses to order it now, defer it to a chosen date, or close it without ordering and record why.
4. For an item being ordered, Purchasing chooses a supplier first.
5. The page automatically shows only confirmed shortages linked to that supplier.
6. **Add fast-moving item** also shows only items linked to the chosen supplier.
7. The order date is today and cannot be changed. Expected delivery is calculated from the supplier's saved delivery lead time.
8. Purchasing may update the saved supplier price, but must write a reason.
9. Purchasing submits the PO directly to the General Manager.
10. GM approves or rejects it. Approval immediately tries to email the final PO to the supplier. A successful email changes the PO to **Approved / Sent to Supplier**.
11. Warehouse receives the delivered items against that PO.

Fresh milk from farmers is the exception. QC records farmer milk directly in **Milk Receiving**. It does not use the supplier PO or GM approval route.

Old PRS records remain available only as history. Warehouse cannot create a new PRS.

## Before testing

Use two active suppliers and two raw materials so the filtering is easy to see.

1. Sign in as General Manager or Admin and open **Suppliers**.
2. Edit Supplier A. Set **Expected Delivery Lead Time** to `3` days and connect Raw Material A with a saved price.
3. Edit Supplier B. Set its lead time to `7` days and connect Raw Material B.
4. Do not connect Raw Material B to Supplier A.
5. Make sure both suppliers have valid email addresses if you want to test automatic PO email delivery.

## Test 1: Warehouse confirms actual stock

1. Sign in as Warehouse Raw.
2. Open **Stock Validation**.
3. Select Raw Material A and enter its real shelf count.
4. If the physical count is lower than the system count, write the reason.
5. Click **Confirm Stock Check**.

Expected result:

- The item says it is waiting for Purchasing.
- Warehouse is not asked to choose a supplier.
- Several selected items can be confirmed together as one Warehouse stock check.
- Purchasing sees the confirmed shortages automatically; Warehouse is not creating a request form.
- No new PRS is created.
- Trying to confirm the same item again is blocked.

## Test 2: Purchasing chooses what happens next

1. Sign in as Purchaser and open **New PO**.
2. At the top, find **Confirmed items needing your decision**.
3. For one test item, click **Defer**, choose tomorrow or a later date, enter a reason of at least 10 characters, and save.
4. Return to the Purchasing dashboard. The deferred item must no longer count as needing a decision today.
5. Return to Warehouse **Stock Validation**. The same item must say **Deferred** and show the saved date and reason when clicked.
6. Confirm a second low-stock item in Warehouse, then return to Purchasing and click **Close without order**. Enter a reason and save.
7. Return to Warehouse. The item must say **Closed by Purchasing** and must not offer another shelf confirmation until its stock balance changes.
8. Return to Purchasing **New PO**, open **Deferred or closed items**, and click **Reopen**. Enter why it is being reconsidered.
9. Confirm the item returns to **Confirmed items needing your decision** and can be ordered again through supplier selection.

Expected result:

- Purchasing has three clear choices: order it, defer it, or close it without ordering.
- Deferring and closing require an explanation.
- Closing does not erase or reject Warehouse's physical count. It records Purchasing's business decision separately.
- Purchasing can reopen a deferred or closed item when circumstances change.
- A deferred or closed item cannot be forced into a PO through an old browser tab.

## Test 3: Supplier-first filtering

1. Sign in as Purchaser.
2. Open **New PO**.
3. Confirm there is no **Warehouse PRS** field.
4. Click **Order now** on Raw Material A.
5. If only one accredited supplier provides it, confirm that supplier is selected automatically and Raw Material A is ticked in the PO lines.
6. If several accredited suppliers provide it, confirm the page shows only those suppliers. Choose one and confirm the item is then ticked automatically.
7. If no supplier provides the item, confirm the page explains that the GM or Admin must create the supplier-to-product link.
8. Manually select Supplier B. Raw Material A must not appear if Supplier B is not linked to it.
9. Select Supplier A. Raw Material A should appear automatically.
10. Confirm its Warehouse-verified shortage, supplier package, and saved price are visible.

Expected result: **Order now** performs the supplier lookup. One valid supplier is selected automatically; several valid suppliers produce a short choice. Items from other suppliers are not mixed into the list.

## Test 4: Automatic order and delivery dates

1. Keep Supplier A selected with its 3-day lead time.
2. Confirm **Order Date** shows today's date and cannot be edited.
3. Confirm **Expected Delivery** is three calendar days after today and cannot be edited.
4. Select Supplier B.
5. Confirm Expected Delivery changes to seven calendar days after today.

Expected result: Purchasing cannot type arbitrary dates. The saved supplier lead time controls delivery.

## Test 5: Add an extra item before it becomes low

1. Select Supplier A.
2. Click **Add extra item**.
3. Confirm a simple window opens asking for the item, quantity, and reason.
4. Confirm it contains only raw materials connected to Supplier A. Raw Material B must not appear.
5. Choose an item. If recent usage exists, confirm the page suggests an amount and explains that the supplier delivery time was considered.
6. Enter or adjust the quantity, write a reason of at least 10 characters, and click **Add to PO**.
7. Confirm the item appears as a clean row labeled **Added early by Purchasing**.
8. Submit the PO.

Expected result:

- The form is separate from the main table, so the item, reason, and quantity are not mixed across columns.
- The extra line is labeled **Added early by Purchasing**.
- The reason is saved for GM review.
- The extra quantity does not change the Warehouse-confirmed shortage.
- A short or missing reason is blocked.

## Test 6: Supplier price update

1. Select Supplier A and find a confirmed shortage.
2. Click **Update saved price**.
3. Try `1e3`, `+100`, or `-100`. Each must be rejected.
4. For a whole-package price, try more than two decimal places. It must be rejected.
5. Enter a valid price such as `1000.25` and a reason of at least 10 characters.
6. Save it.

Expected result: the line refreshes with the new price, and GM can see the old price, new price, purchaser, date, and reason.

## Test 7: Create and submit the PO

1. Tick the confirmed shortage you want to order.
2. Confirm or adjust the recommended supplier package quantity.
3. Add delivery instructions if needed.
4. Click **Submit for GM Approval** once.

Expected result:

- One PO is created for the selected supplier.
- Its status becomes **Pending GM Approval**.
- Repeated clicking does not create a second PO.
- Submitting the same supplier, item, quantity, terms, and delivery date again is blocked as a duplicate.

## Test 8: GM final approval and supplier transmission

1. Sign in as General Manager.
2. Open **GM Approvals** and select the pending PO.
3. Confirm the supplier, confirmed shortage, forecast reason, price change, automatic dates, and total.
4. Approve the PO and complete the approval confirmation.

Expected result when email is working:

- The system immediately emails the final PO to the supplier.
- The PO becomes **Approved / Sent to Supplier**.
- Purchasing does not need to approve it again or send it back through Warehouse.

Expected result when email is not configured or fails:

- GM approval remains recorded.
- The PO shows **Approved - Email Retry Needed**.
- Purchasing can use **Email Final PO** after the email address or email service is fixed. The PO does not return for another GM approval.

## Test 9: Farmer milk bypass

1. Sign in as QC.
2. Open **Milk Receiving**.
3. Click **Record New Milk Delivery**.
4. Select an active farmer, enter the delivered liters and receiving details, and save.

Expected result:

- A milk-receiving number is created directly.
- No supplier PO is created.
- No GM PO approval is requested.
- The delivery continues through QC grading and Warehouse tank receiving.

## Test 10: Receiving supplier goods

1. After an emailed PO is delivered, sign in as Warehouse Raw.
2. Open the PO receiving screen.
3. Record accepted, rejected, or short quantities and required evidence.
4. Finish receiving.

Expected result: accepted stock increases once, rejected stock is not added, and short deliveries remain explained and reviewable.

## Test 11: Counts and duplicate questions for the defense

- The Purchasing summary shows how many confirmed lines exist, how many the selected supplier can provide, and how many belong to other or unlinked suppliers.
- The same active product cannot be confirmed twice while it is waiting for Purchasing.
- Old repeated PRS lines are hidden from the active work list but preserved in **Old PRS History**.
- Supplier-to-product links are stored directly, so selecting a supplier does not search the entire product list.

## Automated checks

Run from the project folder:

```powershell
php tests/professor_procurement_flow_test.php
php tests/stock_validation_direct_flow_test.php
php tests/purchasing_stock_decision_test.php
php tests/supplier_first_po_flow_test.php
php tests/supplier_forecast_po_test.php
php tests/supplier_mro_po_flow_test.php
php tests/purchaser_price_list_test.php
php tests/purchasing_choice_eligibility_test.php
php tests/po_duplicate_submission_guard_test.php
php tests/receiving_resolution_test.php
```
