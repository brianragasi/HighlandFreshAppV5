# Warehouse Raw Panel Demo — Plain-Language Guide

## The one sentence to remember

Warehouse checks and moves physical stock. Purchasing handles suppliers and prices. QC checks food safety. The GM gives final approval when money or an unusual stock correction is involved.

## The normal flow

```text
Stock becomes low
    ↓
Warehouse counts what is really on the shelf
    ↓
Purchasing decides: order now, order later, or close with a reason
    ↓
Purchasing chooses a supplier and prepares the PO
    ↓
GM approves the PO
    ↓
Warehouse receives the delivery and records its real lot and expiry
    ↓
Stock becomes usable
```

Warehouse does not create a separate PRS. Its job is to confirm the shortage. The confirmed item appears for Purchasing automatically.

## Demo 1 — Normal low-stock flow

Use **28 mm Bottle Cap (Mock)**. It is non-perishable, low stock, and is already linked to suppliers.

1. Sign in as **Warehouse Raw**.
2. Open **Stock Validation**.
3. Find **28 mm Bottle Cap (Mock)** and click **Validate**.
4. Enter the actual shelf count. Use the number you can defend as the physical count.
5. Confirm the stock check.

Expected result: Warehouse sees that the check was sent. No PRS is created.

6. Sign in as **Purchasing**.
7. Open **New PO** or the confirmed-stock review.
8. The confirmed Bottle Cap shortage should already be waiting.
9. Choose **Order now**.
10. Choose an accredited supplier.

Expected result: only low-stock items sold by that supplier appear. The suggested order covers the shortage up to the saved target level.

11. Submit the PO for GM approval.
12. Sign in as **GM**, open **Pending Approvals**, review, and approve.

Expected result: the PO becomes approved/ordered and is ready for the supplier. Warehouse stock does not increase yet.

13. Sign in as **Warehouse Raw** and open **Receive Deliveries**.
14. Open the approved PO and receive the delivery.
15. For a perishable item, enter the real supplier lot and printed expiry.

Expected result: only the accepted quantity is added, and it becomes usable.

## Demo 2 — Stock physically found but missing from the system

Use **Raw Material A (ING-0045)** for a small test. It currently has no stock and is linked to **Supplier A**. Use details clearly labelled as panel-demo data.

1. Sign in as **Warehouse Raw**.
2. Open **Ingredients** and select **Raw Material A**.
3. Click **Record Found Stock**.
4. Enter **1 kg** as the counted amount.
5. Choose **Supplier delivery not recorded**, then choose **Supplier A**.
6. Choose a saved order if one is shown. Otherwise choose **Document not listed** and enter `PANEL-DR-RAW-A-001`.
7. Enter lot `PANEL-LOT-RAW-A-001`, today's received date, an expiry safely after today, and this explanation: `Panel demo: one sealed kilogram was physically found but its delivery was not recorded.`
8. Click **Send for Verification**.

Expected result: stock does not change yet.

9. Sign in as **Purchasing** and open the found-stock price check.
10. Verify the price from the saved PO/invoice or enter the demo-verified price and reference.
11. Sign in as **QC** and inspect the perishable found stock. Approve only when its lot, expiry, and condition are acceptable.
12. Sign in as **GM** and approve the final request.

Expected result: exactly the approved difference is added as one usable batch. Refresh Warehouse Ingredients and confirm the new total and batch.

## Demo 3 — Sugar with a missing lot

Sugar is useful for explaining the old-data safeguard.

1. Open **Sugar** in Warehouse Ingredients.

Expected result:

- **Stock on File:** 101 kg
- **Usable Stock:** 0 kg
- **Held:** 101 kg because the supplier lot is missing

Say this to the panel:

> “This was an older record created before strict lot checking. The system no longer treats it as usable. Production cannot receive it. We must find the real supplier document and pass it through Purchasing, QC, and GM review. The system corrects the old batch; it does not create a duplicate 101 kg.”

If you have a trusted package or supplier document:

2. In **Physical batches**, click **Record Lot Details** beside one held batch.
3. Enter the real source, lot, received date, and printed expiry.
4. Complete the Purchasing, QC, and GM checks.

Expected result: the same held batch becomes usable. A second 101 kg batch is not created.

If no trusted lot can be found, do not approve or use the Sugar. Keep it held and process a return or disposal. Never invent a lot number.

## What the warnings in the current list mean

- **Held - supplier lot missing:** The quantity is physically recorded, but Production cannot use it until one batch at a time receives real lot details and completes review.
- **Expired or expires today:** Use **Record Waste**. Do not correct the lot merely to make it usable.
- **On file - check balance:** The item total and physical batch list disagree. Count the shelf before changing anything.
- **Above the restocking target:** This is not an overflow warning. The target tells Purchasing how much it normally wants after restocking; it is not a tank capacity.
- **Packaging materials:** Bottles, caps, labels, and wrap do not require food expiry or supplier-lot checks. The system now enforces this automatically.

## Continue the Magic Sarap request already in progress

`OPEN-20260826-004633-59` is already waiting for review. Do not create another request for Magic Sarap.

1. Purchasing opens **Verify Found-Stock Cost**, checks the invoice or PO price, and saves the price reference.
2. QC opens **Inspect Found Perishable Stock**, checks the sealed material, lot, and expiry, then approves or rejects it.
3. GM opens **Pending Approvals** and makes the final decision.
4. Warehouse refreshes **Ingredients**. If approved, only that corrected batch becomes usable; the total stock on file is not duplicated.

## What to do with the current test data

After pressing `Ctrl+F5`, work by warning type—not by trying to force every row to “OK.”

### Safe examples you can use now

- 28 mm Bottle Cap: 398 pcs usable
- 500 mL Bottle: 699 pieces usable
- Chocolate Milk 500 mL Label: 1,199 pcs usable after the packaging fix
- Cellophane Wrap: 48.97 rolls usable; it is above its restocking target, which is allowed
- Food-Grade Alcohol: 100 liters usable
- Sugar: 101 kg usable with a clearly labelled panel-demo opening lot
- Salt (ING-006): 100 kg usable with a clearly labelled panel-demo opening lot
- Rennet: 10 liters usable across two clearly labelled panel-demo lots
- Vanilla Extract: 16 liters usable across two clearly labelled panel-demo lots
- Chocolate Powder X: 12 kg usable; the other batches remain held
- Magic Sarap: 100 kg usable; another 100 kg is already moving through review

### Record as waste because expired or expiring today

- Chocolate Syrup: 42.47 liters
- Food Supplements: 200 liters
- Raw Material B: 53 kg
- Stabilizer: 5 kg
- Tanduay: 40 kg
- Tanduayv1: 50 kg
- TanduayV2: 60 kg
- TanduayV3: 70 g

Open the item, choose the affected physical batch, and use **Record Waste**. Do not invent a newer expiry date.

### Keep held until the real lot is found

- Beer: 60 kg
- Chocolate Powder X: 68 kg across old batches
- Cultures: 8,000 packets
- Food Coloring: 0.95 liter and 200 liters on separate item records
- Magic Sarap: 100 kg; continue its existing request
- Redhorse: 120 g
- Salt (ING-0043): 200 kg
- Tanduay: 50 kg
- TanduayV2: 60 kg
- TanduayV3: 140 g

For these rows, open the item and use **Record Lot Details** beside one physical batch at a time. If the package or supplier document cannot prove a real lot, leave it held.

## Demo 4 — Production requests materials

```text
Production plans a batch
    ↓
System calculates the recipe materials
    ↓
GM approves the request
    ↓
Warehouse issues only usable batches
    ↓
Production receives the materials
```

Important checks:

- The request cannot use held Sugar or any expired batch.
- Warehouse issues the oldest safe batch first.
- A request cannot exceed usable stock.
- The production batch itself cannot exceed the saved tank or vessel limit.

## Fast panel answers

**Why does Warehouse no longer make a PRS?**  
Because Warehouse already confirmed the physical shortage. A second request form repeated the same work. Purchasing receives the confirmed shortage automatically.

**Can Purchasing refuse to order?**  
Yes. Purchasing can order now, defer it, or close it with a required reason. A closed item can be reopened later.

**Who updates prices?**  
Purchasing, because it communicates with suppliers and checks quotations or invoices. Price changes are saved with a reason and history.

**Why require a lot number?**  
So the business can identify which delivery was used and isolate it if there is a safety problem.

**What if there is no lot on the package?**  
Check the supplier document. If no trusted lot can be proven, the material stays held and cannot be used.

**When does stock increase?**  
After a normal delivery is accepted, or after the complete review of unusual found stock. Creating a product or ingredient does not create stock.

**Why is the order date automatic?**  
It records when the PO was actually made. Expected delivery is calculated from the supplier's saved delivery time.

## Final five-minute check before the panel

1. Refresh the browser with `Ctrl+F5` so it loads the newest code.
2. Confirm Sugar shows 0 usable and a missing-lot hold.
3. Confirm **28 mm Bottle Cap (Mock)** appears in Stock Validation as low stock.
4. Confirm Purchasing can see confirmed stock and choose among order, defer, or close.
5. Confirm a perishable receiving line refuses a blank lot or blank expiry.
6. Keep one Warehouse, Purchasing, QC, and GM account ready in separate browser profiles or incognito windows.
7. Do not use made-up production data unless it is clearly labelled as demo data.
