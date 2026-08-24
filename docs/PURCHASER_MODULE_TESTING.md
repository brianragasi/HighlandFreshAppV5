# Purchaser Module Manual Testing Guide

This guide follows the current purchasing rule: routine partner prices are recorded during supplier accreditation. The Purchaser reviews those saved prices instead of typing three new quotes for every request.

## People Needed

- General Manager
- Warehouse Raw custodian
- Purchaser
- Finance Officer

## Test Data Setup

Log in as the General Manager before testing a purchase request.

1. Open **Suppliers**.
2. Register or edit an accredited supplier.
3. Choose every ingredient that the supplier is allowed to provide.
4. Choose whether this supplier sells each item directly or in a package.
5. For packaged items, enter the package type, amount inside it, unit, and quoted package price. For direct items, enter the price per Warehouse stock unit.
6. Confirm the preview shows the fair comparison price per Warehouse stock unit, then save.
7. Repeat only when another real approved supplier also provides the ingredient.

Expected result:

- One supplier is allowed.
- Two suppliers are allowed.
- Three or more suppliers are allowed.
- A chosen ingredient cannot be saved without an agreed price.

## Complete Purchase Flow

### 1. Warehouse Raw Creates the PRS

1. Log in as **Warehouse Raw**.
2. Open **Purchase Requests**.
3. Open **Build PR from low stock**.
4. Select every item found during the same stock check.
5. Click **Create one PRS**.
6. Enter the physical quantity counted for every selected item.
7. Check the requested quantities and submit the PRS.

Expected result:

- The PRS appears in the Purchaser inbox.
- Several items from the same stock check appear in one PRS instead of several one-item slips.
- The original system balance and the physical count remain visible.
- A second pending PRS for the same item is blocked.

### 2. Purchaser Reviews Registered Suppliers

1. Log in as **Purchaser**.
2. Open **Supplier Review**.
3. Select the related Warehouse PRSs. Use **Select all** when the whole queue belongs to the same review.
4. Choose **Review selected** once.
5. Check the source PRS number beside every requested item, then review the one compact table.

Expected result:

- Every requested item appears once.
- The system shows only suppliers accredited for that item.
- Saved agreed prices appear automatically.
- The lowest valid price is recommended automatically.
- The Purchaser does not type routine supplier prices again.
- The Purchaser does not open each selected PRS separately.

### 3. Check the Single-Supplier Case

Use an ingredient that has only one accredited supplier with an agreed price.

Expected result:

- The item is marked ready.
- The page states that one approved partner is available.
- The Purchaser is not forced to invent two more quotes.
- The limited market is recorded automatically for GM review.

### 4. Check the Multiple-Supplier Case

Use an ingredient supplied by two or more accredited suppliers with saved prices.

Expected result:

- All saved partner prices can be viewed.
- The cheapest supplier is recommended.
- If two suppliers have the same price, the faster delivery is recommended.

To test an exception:

1. Choose a supplier other than the recommendation.
2. Enter the business reason, such as faster delivery or better availability.

Expected result:

- The new supplier is saved only after a reason is entered.
- The reason is sent to the GM with the PO.

### 5. Create and Send the POs

1. Confirm that every requested item says **Ready**.
2. Enter the expected delivery date.
3. Click **Create & Send to GM** once.

Expected result:

- Items for the same supplier are placed on one PO.
- Items for different suppliers create separate POs.
- All created POs are sent to the GM in the same action.
- The Purchaser does not submit each draft PO one by one.

### 6. GM Reviews the POs

1. Log in as **General Manager**.
2. Open **Pending Approvals**.
3. Review each PO, its linked PRS, selected supplier, agreed price, and any exception reason.
4. Approve or reject the PO.

Expected result after approval:

- The PO is locked as approved.
- Finance is notified.
- The approved PO can be printed or emailed to the supplier.

### 7. Warehouse Raw Receives the Delivery

1. Log in as **Warehouse Raw**.
2. Open **Receive Deliveries**.
3. Select the approved PO.
4. Check the physical delivery against the PO.
5. Record accepted quantities, rejected quantities, invoice details, and batch information.
6. Generate the Receiving Report.

Expected result:

- Accepted stock is added to inventory.
- The Receiving Report remains linked to the PO.
- Missing or damaged quantities are recorded instead of silently accepted.

### 8. Purchaser Verifies the Receiving Report

1. Log back in as **Purchaser**.
2. Open **Purchase Orders**.
3. Open the received PO.
4. Click **Verify RR**.
5. Compare ordered and received quantities, then confirm or report a mismatch.

Expected result:

- A matching RR closes the purchasing transaction.
- A mismatch remains open for correction.
- Finance can see the PO, RR, invoice, and payment state.

## Important Failure Tests

### Missing Agreed Price

Remove the agreed price from one selected supplier-item agreement, then open a PRS for that ingredient.

Expected result: PO creation is blocked and the page tells the user that the GM must complete the supplier agreement.

### Supplier Not Accredited for the Item

Try to submit a supplier that is not linked to the requested ingredient.

Expected result: the system rejects the choice.

### Changed Price in the Browser

Try to change the PO price using browser tools before submission.

Expected result: the server rejects the changed price because it does not match the reviewed supplier agreement.

### Missing Exception Reason

Choose a non-recommended supplier and leave the reason empty.

Expected result: the supplier change is not saved.

## Plain-English Panel Explanation

The General Manager controls which suppliers are accredited, which ingredients they provide, and their agreed prices. Warehouse Raw puts items found during one stock check into one Purchase Request Slip. If several slips are waiting, Purchasing reviews all of them together while every item keeps its original PRS number. The system compares the registered partner prices and recommends the best valid supplier. One or two suppliers are acceptable when that is the real market. One action creates the required Purchase Orders and sends them to the General Manager for approval.

## Complete Multi-Item PO Demonstration

Use this walkthrough when you need to show the whole process from low stock to completed receiving.

### Prepared Demo Data

The following items are intentionally below their minimum stock:

| Item | Current | Minimum | Status |
|---|---:|---:|---|
| Chocolate Powder X | 42 kg | 50 kg | Critical |
| Chocolate Syrup | 16 liters | 30 liters | Critical |
| Food-Grade Alcohol | 12 liters | 25 liters | Critical |

Ian Gao Trading and Elixir Industries are both approved to supply all three items. Their saved prices are different so the supplier decision is easy to explain.

### 1. Purchaser Creates One PO With Several Items

1. Log in as **Purchaser**.
2. Open **Dashboard** and point out the three items in **Critical Stocks**.
3. Open **Purchase Orders**.
4. Click **New PO**.
5. Choose **Ian Gao Trading**.
6. Add Chocolate Powder X, Chocolate Syrup, and Food-Grade Alcohol as three rows in the same PO.
7. Enter the quantities. The unit, saved price, each row total, and full PO total are shown automatically.
8. Click **Submit for GM Approval**.

Expected result:

- Only one PO is created.
- The PO contains all three items.
- Its status is **Pending GM Approval**.
- Warehouse cannot receive it yet.

### 2. GM Approves and Sends the PO

1. Log out and log in as **General Manager**.
2. Open **Pending Approvals**.
3. Open the new PO and check the supplier, all three items, quantities, prices, and total.
4. Click **Approve**.

Expected result:

- The PO becomes **Approved / Sent**.
- The approved PDF is emailed automatically to the supplier's registered email address.
- The approved quantities and prices are locked.

### 3. Warehouse Records a Partial Delivery

1. Log out and log in as **Warehouse Raw**.
2. Open **Receive Deliveries**.
3. Open the approved PO.
4. For the first delivery, enter less than the ordered quantity for one or more items.
5. Enter the supplier invoice information and expiry date for perishable batches.
6. Click **Confirm Receiving**.

Expected result:

- A Receiving Report is created.
- Only the accepted quantities are added to inventory.
- The PO becomes **Partially Received**.
- The remaining quantities stay open for another delivery.

### 4. Warehouse Completes the Delivery

1. Open the same PO again in **Receive Deliveries**.
2. Enter the remaining quantities and their batch details.
3. Click **Confirm Receiving**.

Expected result:

- A second Receiving Report is created.
- Inventory increases by the second accepted delivery only.
- The PO becomes **Fully Received**.

### 5. Purchaser Performs the Final Check

1. Log back in as **Purchaser**.
2. Open **Purchase Orders** and open the received PO.
3. Click **Verify RR**.
4. Compare the PO with all Receiving Reports, then confirm the match.

Expected result:

- The transaction becomes **Completed**.
- Both Receiving Reports remain linked to the PO.
- The final received total equals the ordered total.
- Finance can see the approved PO, receiving records, invoice, and unpaid balance.

### Verified Example

The complete walkthrough was run with PO **5316**. It contained three items, was approved by the GM, emailed to the supplier, received in two deliveries under **RR-202608-0006** and **RR-202608-0007**, and completed only after the Purchaser verified the final Receiving Report.
