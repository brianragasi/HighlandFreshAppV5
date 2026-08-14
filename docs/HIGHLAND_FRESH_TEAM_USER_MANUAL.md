# Highland Fresh Dairy Operations System

## Complete Team User Manual

**For:** Highland Fresh project teammates, demonstrators, and system users  
**Version:** 1.0  
**Updated:** August 9, 2026  
**System address:** `https://localhost/HighlandFreshAppV4/html/login.html`

This guide explains how the current Highland Fresh system is used from one department to the next. It uses plain language and follows the current pages, current user roles, and revised instructor feedback.

---

# 1. How to Use This Guide

Read Sections 1 to 5 first. They explain the rules shared by everyone and the complete company flow. After that, each teammate should read the chapter for the role they will demonstrate.

| If you are... | Read this chapter first |
|---|---|
| General Manager | Chapter 6 |
| Warehouse Raw Custodian | Chapter 7 |
| Purchaser | Chapter 8 |
| Finance Officer | Chapter 9 |
| Production Staff | Chapter 10 |
| QC Officer | Chapter 11 |
| Warehouse Finished Goods Custodian | Chapter 12 |
| Sales Custodian | Chapter 13 |
| Cashier | Chapter 14 |

The final chapters contain a full manual demonstration, status meanings, common problems, and a map of the existing documentation.

## What this system is

Highland Fresh is an operations system. It records physical stock, production, food-safety checks, purchasing, deliveries, sales, collections, and company payments.

## What this system is not

It is not a complete accounting package. It does not create journal entries, general ledgers, income statements, balance sheets, or tax reports. Those belong in separate accounting software.

---

# 2. Active Users and Boundaries

The system has nine active user roles.

| User | Main responsibility | Does not do |
|---|---|---|
| General Manager | Approves controlled actions, accredits suppliers, and manages master records | Receive goods or collect payments |
| Warehouse Raw | Keeps raw milk, ingredients, packaging, and MRO supplies | Approve its own requests or create supplier POs |
| Purchaser | Selects accredited suppliers, canvasses quotations, creates POs, and verifies Receiving Reports | Register suppliers, approve its own PO, or physically receive stock |
| Finance Officer | Handles company payments and farmer payouts | Receive customer payments or perform QC |
| Production Staff | Requests materials and records actual manufacturing work | Take materials without an approved requisition |
| QC Officer | Tests milk and releases or rejects finished batches | Change Production's actual records |
| Warehouse Finished Goods | Receives QC-released products, picks, creates DRs, and dispatches | Release products still waiting for QC or short-pick an order |
| Sales Custodian | Manages institutional, credit, emailed-PO, and direct wholesaler orders | Collect customer money |
| Cashier | Handles walk-in sales and receives customer payments | Release supplier payments or edit institutional orders |

There is no Maintenance Head role and no Bookkeeper role. Warehouse Raw manages MRO supplies and equipment requests, with GM oversight. Finance manages company disbursements. Full bookkeeping stays outside the system.

## External people do not log in

Farmers, suppliers, customers, wholesalers, and drivers are records or outside participants. They do not need staff dashboards.

- A farmer delivers milk; QC and Warehouse Raw record it.
- A supplier receives an approved PO and delivers goods; Warehouse Raw receives them.
- A customer emails a PO or places an order through Sales.
- A driver receives printed delivery documents from Warehouse Finished Goods.

---

# 3. Rules Everyone Must Follow

## 3.1 Use only your assigned account

Log in using the email, employee ID, or username assigned to you. Do not share an account. The name shown on a record is part of the evidence of who performed the action.

If the account uses temporary credentials, the employee must change the password at first login. Email invitations are for employees with email. Temporary credentials are for employees without email.

## 3.2 Stay inside your assigned role

Typing another department's page address must not grant access. The system should redirect the user or show Access Denied. Report any page that opens for the wrong role.

## 3.3 Do not delete business history

Master records should normally be archived or marked inactive. Past transactions, approvals, receipts, batch records, and audit history must remain available.

## 3.4 Use the document chain

Do not move stock or money without the correct system record.

- Production material: Requisition -> GM approval -> Warehouse Raw release.
- Raw material purchase: PRS -> three supplier quotes -> PO -> GM approval -> delivery -> RR -> Purchaser verification.
- Finished product: Production output -> QC release -> FG receiving -> picking -> DR -> dispatch.
- Credit payment: DR balance -> Cashier collection -> OR. A check remains pending until bank clearing.

## 3.5 Respect physical checks

The system supports the employee; it does not replace physical inspection.

- QC physically tests milk and finished goods.
- Warehouse Raw physically counts delivered materials.
- Warehouse FG physically counts picked and returned products.
- Purchaser compares the Receiving Report with the approved PO.
- Cashier confirms that payment was actually received or cleared by the bank.

## 3.6 Use exact units

Always choose the unit shown on the physical item or document: liter, kilogram, packet, bottle, block, piece, box, case, crate, or another configured unit. Do not treat one box as one individual item.

## 3.7 Record actual results

Recipes, requested amounts, and expected yields are plans. Actual usage, actual output, actual waste, actual delivery, and actual payment must be entered truthfully.

---

# 4. Complete Company Flow

This is the full operating chain. A teammate should understand where their work starts and who receives it next.

## Flow A: Farmer milk to raw milk inventory

1. QC Officer opens **Milk Receiving** and records the farmer delivery.
2. QC Officer tests the milk in **Milk Grading** before it enters a tank.
3. Failed milk is rejected and never mixed with accepted milk.
4. Passed milk becomes available to Warehouse Raw for tank assignment and storage.
5. Warehouse Raw keeps it at the required temperature and releases it only against an approved Production requisition.
6. Finance later uses accepted volume and QC results when preparing farmer payments.

## Flow B: Low stock to a closed supplier purchase

1. The system shows a low-stock warning to Warehouse Raw. The warning is information only.
2. Warehouse Raw checks physical stock and manually creates a Purchase Request Slip, or PRS.
3. Purchaser opens the PRS in **Canvassing**.
4. Purchaser records three different supplier quotations for each requested line.
5. The system identifies the cheapest quotation. If prices tie, Purchaser must still review delivery time and terms before continuing.
6. After every line has a valid selected quotation, Purchaser selects **Create & Send to GM**.
7. The system groups items for the same supplier into one PO, creates separate POs for different suppliers, and sends all resulting POs to GM in that single action.
8. GM reviews and approves or rejects each supplier PO separately.
9. After approval, the system sends the approved PO PDF to the selected supplier's registered email and Finance can see the obligation.
10. When the supplier delivers, Warehouse Raw checks the goods, records accepted quantities, updates inventory, and generates the Receiving Report.
11. Purchaser opens the Receiving Report, compares it with the approved PO, and selects **Verify RR** only when the delivered goods match.
12. The verified RR closes the purchasing transaction. Finance handles payment according to the approved documents and terms.

## Flow C: Production request to finished goods

1. Production opens **1 Request Materials** and creates a requisition from the chosen recipe and batch volume.
2. The system calculates planned materials, but Production reviews the quantities before submitting.
3. GM approves or rejects the requisition.
4. Warehouse Raw opens **Requisitions**, physically issues the approved materials, and records the release.
5. Production sees the fulfilled request as ready and starts or opens the production run.
6. Production records actual ingredient use, processing stages, temperatures, pressure, times, and adjustments in the **Active Runs** workbench.
7. Production records waste and byproducts such as whey, skim milk, cream, or buttermilk.
8. Production records actual packaged output in exact boxes and loose pieces, then finishes and sends the batch to QC.
9. The batch becomes **Pending QC**. It is not yet saleable stock.
10. QC opens **Batch Release**, checks the safety logs, organoleptic results, packaging, and physical counts.
11. QC releases or rejects the batch. Release creates the traceable batch/barcode information.
12. Warehouse FG opens **Receive from Production** and puts the released stock into a chiller location.

## Flow D: Institutional customer order to collection

1. A supermarket or institution emails its own PO, normally as a PDF, to the company order mailbox.
2. Sales opens **Customer PO Inbox** and selects **Check Email**.
3. The email appears as **For Encoding**. The original email and attachment remain unchanged.
4. Sales opens the original PO beside the entry form and records the customer, customer PO number, requested delivery date, products, quantities, units, prices shown by the customer, and remarks.
5. The system checks the entered product, unit, price, customer, and released stock.
6. If Sales typed something incorrectly, use **Correct Encoding** and explain the correction.
7. If the customer changes the real order, Sales calls the customer and uses **Record Customer Call**. Record what changed, why, who agreed, and when.
8. Sales saves the agreed order. Only reviewed details can become a Sales Order.
9. Credit orders that require GM review wait for approval.
10. Warehouse FG sees the approved order but cannot create a DR until enough released stock exists.
11. Warehouse FG picks the full ordered quantity using earliest-expiry stock, creates the DR, prints it, and dispatches the delivery.
12. For credit delivery, the balance appears in receivables.
13. Cashier searches the DR in **Collect Payment**, receives payment, and creates the Official Receipt.
14. Cash, bank transfer, or approved digital payment reduces the balance when confirmed.
15. A check starts as **Pending for Clearing**. It does not reduce the balance or appear as cleared collection until the bank confirms it.
16. Cashier opens the pending check and records bank clearing or marks it bounced.
17. Sales and Finance can monitor the resulting balance and aging, but they do not receive the payment.

## Flow E: Direct wholesaler or small-business order

1. A registered wholesaler or small business may order in person or by phone through Sales **Direct Order**.
2. Supermarkets, feeding programs, and major institutions use the Customer PO Inbox instead.
3. Sales selects only products already released by QC and available in Finished Goods.
4. Sales records exact boxes and loose pieces using the official system price and the customer's registered payment terms.
5. Sales sends the Direct Order to the GM. Warehouse FG cannot prepare it while it is Pending GM Approval.
6. The GM reviews the customer, items, amount, stock, payment mode, and credit position, then approves or rejects it.
7. An over-limit credit order is clearly marked as a credit exception for the GM. Sales cannot approve it.
8. After approval, Warehouse FG picks the full quantity by earliest expiry, creates and prints the DR, then dispatches it.
9. Cashier records the actual customer payment. Ordinary retail walk-ins use Cashier **Quick Sale**, not Direct Order.

---

# 5. Shared Status Guide

## Purchasing

| Status | Meaning | Next person |
|---|---|---|
| Draft PRS | Warehouse Raw has not submitted it | Warehouse Raw |
| Submitted PRS | Ready for canvassing | Purchaser |
| Canvassing | Supplier quotes are being entered | Purchaser |
| Draft PO | PO exists but has not been sent for approval | Purchaser |
| Pending GM Approval | PO is locked for GM decision | General Manager |
| Approved | Supplier order is authorized | Supplier, Finance, Warehouse Raw |
| Received | Warehouse Raw recorded delivery and RR | Purchaser |
| RR Verified / Closed | Purchaser matched RR to PO | Finance / complete |

## Production and QC

| Status | Meaning | Next person |
|---|---|---|
| Pending Requisition | Waiting for GM | General Manager |
| Approved Requisition | Warehouse may issue materials | Warehouse Raw |
| Fulfilled | Materials were released; run may start | Production |
| Active Run | Manufacturing is in progress | Production |
| Pending QC | Production finished; goods cannot be sold | QC Officer |
| QC Released | Safe and counted; ready for FG receiving | Warehouse FG |
| QC Rejected | Batch is blocked | Production / QC / GM as needed |

## Customer PO Inbox

| Status | Meaning | Next action |
|---|---|---|
| New Email / For Encoding | Email is saved; order details are not complete | Enter Order Details |
| Draft Order | Sales saved partial details | Continue Editing |
| Needs Customer Confirmation | A real customer decision is needed | Record Customer Call |
| Customer Confirmed | Customer-approved change is recorded | Save agreed details |
| Ready to Create | Review is complete | Create Sales Order |
| Order Created | Sales Order already exists | View Order |
| Rejected | Email will not become an order | Read rejection reason |

## Payment by check

| Status | Meaning | Effect on customer balance |
|---|---|---|
| Pending for Clearing | Check was received but the bank has not cleared it | No reduction |
| Cleared | Bank confirmation was recorded | Balance is reduced |
| Bounced | Bank rejected the check | Balance remains unpaid |

---

# 6. General Manager Guide

## Your purpose

You are the final decision maker for controlled actions. You approve spending, production material releases, Sales Orders, credit exceptions, and disposals. You also maintain the master records used by every department.

## Main pages

- **Dashboard:** pending actions, alerts, recent activity, and quick access.
- **Pending Approvals:** production requisitions, purchase orders, Sales Orders and credit exceptions, and disposals.
- **Users & Accounts:** create, invite, deactivate, unlock, and reassign staff accounts.
- **Farmers, Suppliers, Customers:** maintain outside-party records. Only GM/Admin may register, edit, accredit, or archive suppliers.
- **Products, Recipes, Ingredients:** maintain products and standard production plans.
- **Storage & Tanks, Chillers:** maintain storage locations.
- **Orders for Approval:** review controlled customer orders.
- **Disposals, Recalls, QC Standards:** oversee loss and safety controls.

## Start-of-day checklist

- Open **Dashboard** and read Pending Actions.
- Open **Pending Approvals** and filter each queue.
- Check low stock, overdue receivables, disposal requests, and operational warnings.
- Review unusual activity before changing master records.

## Approving a production requisition

1. Open **Pending Approvals**.
2. Choose **Production Materials**.
3. Open the requisition and compare the recipe, batch size, and requested quantities.
4. Add a remark when clarification is needed.
5. Approve only when the request is reasonable. Reject with a clear reason when it is not.
6. After approval, Warehouse Raw receives the release task.

## Approving a Purchase Order

1. Open **Pending Approvals**, then **Procurement**.
2. Review the PRS reference, selected supplier, three-quote canvass result, line quantities, prices, terms, and expected delivery.
3. Approve or reject. The Purchaser cannot approve their own PO.
4. Approval locks the decision and allows the approved PO to be sent to the supplier.

## Registering and accrediting a supplier

1. Open **Suppliers** from the GM/Admin sidebar.
2. Select **Register Supplier**.
3. Enter the supplier name, contact details, address, payment terms, and email used for approved PO delivery.
4. Under **Ingredients Supplied**, choose every ingredient the supplier is approved to provide. An ingredient may have several approved suppliers.
5. Enter an optional reference price when management has one. Purchasing still records the current price during canvassing.
6. Set the supplier to **Accredited** only after the supplier has passed management review.
7. Save the record. Purchasing can now use this supplier only for the linked ingredients.
8. Use **Archive** when the company should no longer place new orders with that supplier. Old purchase records remain available.

## Linking suppliers from the Ingredients page

1. Open **Ingredients** and add or edit an ingredient.
2. Under **Accredited Suppliers**, choose every supplier allowed to provide it.
3. Save the ingredient. The same links appear in Supplier Accreditation.
4. Keep at least three approved suppliers. The system blocks an active ingredient or supplier change that would leave fewer than three because Purchasing could not complete the required canvass.

## Account management

1. Open **Users & Accounts**.
2. Enter the employee name and choose one of the nine active roles.
3. The Employee ID is generated by the system.
4. Choose **Email Invite** when the employee has email.
5. Choose **Temporary Credentials** only when the employee has no email.
6. Deactivate an employee who leaves. Do not delete their history.

## Do not

- Approve without reading the details.
- Use Finance or Cashier screens to move money yourself.
- Change a recipe while a teammate is demonstrating a running batch without coordination.
- Reuse retired Maintenance Head or Bookkeeper roles.

## Your handoff

Approval sends work back to the operating owner: Warehouse Raw, Purchaser, Sales, QC, or Finance. Your approval authorizes the next action; it does not replace that action.

---

# 7. Warehouse Raw Guide

## Your purpose

You protect raw milk, ingredients, packaging, and MRO supplies. You physically receive and issue stock and keep every movement supported by a document.

## Main pages

- **Milk Storage:** accepted milk and tank balances.
- **Ingredients:** ingredient and packaging stock by batch.
- **MRO Supplies:** maintenance, repair, and operating supplies.
- **Requisitions:** approved Production requests waiting for release.
- **Receive Deliveries:** supplier deliveries and Receiving Reports.
- **Spoilage & Waste:** expired, damaged, contaminated, or lost raw materials with evidence.
- **Low Stock Alerts:** decision support for low stock.
- **Purchase Requests:** create and track PRS records.
- **Inventory Report / Stock Movements:** review balances and movement history.

## Receive QC-approved milk

1. Wait for QC grading. Do not put untested milk into a storage tank.
2. Open **Milk Storage** and select the passed delivery.
3. Assign the accepted milk to the correct tank.
4. Confirm the physical volume and storage temperature.
5. Keep the receiving and farmer reference linked for traceability.

## Release materials to Production

1. Open **Requisitions**.
2. Select an approved request.
3. Physically prepare the requested milk, ingredients, and packaging.
4. Confirm the actual amount released. Do not issue more than available stock.
5. Submit fulfillment. Production can then start or continue the linked run.

## Create a PRS from low stock

1. Open **Low Stock Alerts**.
2. Check the physical quantity and existing batches.
3. Open **Purchase Requests** and choose **New PRS**.
4. Add low-stock ingredients, packaging, or MRO items and the quantity needed.
5. Submit the PRS to Purchaser.

The low-stock alert never creates the PRS automatically. Warehouse Raw makes the request after checking the real stock.

## Receive supplier goods and create RR

1. Open **Receive Deliveries**.
2. Select the approved PO delivered by the supplier.
3. Count and inspect each line physically.
4. Record accepted quantity, rejected quantity, lot or batch information, expiry where required, and remarks.
5. Save the delivery and generate the Receiving Report.
6. Inventory increases only for accepted quantities.
7. Purchaser must still verify the RR against the approved PO.

## Record waste

1. Open **Spoilage & Waste** or use **Record Waste** from an ingredient batch.
2. Choose the exact item and batch.
3. Enter the actual waste quantity. For a fully expired batch, the amount may be filled from the selected batch, but it must still be reviewed.
4. Choose the reason and date.
5. Add a short explanation and required photo evidence.
6. Save. Do not record usable stock as waste.

## MRO ownership

Warehouse Raw keeps MRO inventory and equipment-related supply requests. GM provides oversight. There is no separate Maintenance user account.

## Do not

- Buy directly from a supplier without a PRS and approved PO.
- Issue Production materials before GM approval.
- Receive a supplier delivery against a draft or rejected PO.
- Change the PO quantity to make the delivery appear correct.

---

# 8. Purchaser Guide

## Your purpose

You turn Warehouse Raw requests into controlled supplier purchases. You compare suppliers, prepare POs, and verify that the final Receiving Report matches what was approved.

## Main pages

- **Dashboard:** request, PO, payment, and supplier summaries.
- **Canvassing:** open PRS records and record supplier quotations.
- **Purchase Orders:** submit POs for approval and track delivery/RR status.
- **Approved Suppliers:** view GM-accredited supplier contact details and terms. This page is read-only.
- **Requisitions / PRS queue:** requests waiting for purchasing action.

## Canvass three suppliers

1. Open **Canvassing**.
2. Select the PRS and choose **Open**.
3. For each item, add a quotation from three different GM-accredited suppliers shown for that ingredient.
4. Record unit price, delivery days, and payment terms correctly.
5. The system identifies the cheapest quotation after the third valid quote.
6. Review tied prices and delivery terms. Do not assume equal prices mean equal service.
7. Repeat until every PRS line is complete.

## Create and submit a PO

1. Review supplier, quantity, price, payment terms, delivery date, and PRS reference after every line has a valid quotation result.
2. Choose **Create & Send to GM**.
3. The system creates one PO for each selected supplier and submits all of them together.
4. Open **Purchase Orders** to track the resulting POs while waiting for the GM decision. You cannot edit a PO while it is locked for approval.
5. After approval, confirm the system's supplier-email result. Use the supplier contact details to follow up if needed.

Older draft POs created before this combined action can be submitted together. Select **Send N to GM** on the first draft from that PRS; all remaining draft POs from the same PRS are sent in one action.

## Verify the Receiving Report

1. After Warehouse Raw receives the delivery, open **Purchase Orders**.
2. Open the approved PO and its Receiving Report.
3. Compare each ordered line with accepted delivery quantity and condition.
4. Select **Verify RR** only if the documents match.
5. If they do not match, record the discrepancy and return it for correction or supplier action.
6. Verification closes the purchase and provides complete evidence for Finance.

## Do not

- Create the PRS on behalf of Warehouse Raw just because stock is low.
- Use fewer than three suppliers when the three-quote rule applies.
- Ask an unregistered supplier for a quote without first asking GM to update Supplier Accreditation.
- Approve your own PO.
- Verify an RR without reading both documents.
- Manually email a changed, unofficial PO after GM approval.

---

# 9. Finance Officer Guide

## Your purpose

You release company funds for approved obligations and farmer payouts. You can see customer collection totals, but the Cashier receives customer money.

## Main pages

- **Dashboard:** disbursements, payables, farmer payments, and read-only collection summaries.
- **Payables:** approved and received supplier obligations.
- **Farmer Payments:** accepted milk deliveries and payout periods.
- **Collections:** read-only view of money recorded by Cashier.
- **Aging summary:** company visibility into unpaid customer balances.

## Why a supplier may not appear in Payables

The supplier master list is not the payable list. A supplier appears in Payables only when a real obligation exists, such as an approved PO with the required receiving documents. An active supplier with no payable transaction will not appear.

## Process a supplier payment

1. Open **Payables**.
2. Select an obligation supported by an approved PO and completed receiving evidence.
3. Confirm supplier, amount, payment terms, RR status, and due date.
4. Record payment method and required bank, check, or reference details.
5. Save the payment. Use partial payment only when the real payment is partial.

## Process farmer payouts

1. Open **Farmer Payments**.
2. Select the payout period.
3. Review accepted liters and QC-based price adjustments.
4. Check rejected deliveries are not included.
5. Record payment release and reference details.

## Collections visibility

Use **Collections** to monitor what Cashier recorded. Do not enter a second collection in Finance. Customer check clearing is completed from the Cashier collection record.

## Do not

- Receive customer cash, checks, or transfers.
- Pay a supplier only because the supplier is active in the master list.
- Release money from a draft or rejected PO.
- Perform product-quality or delivery-count checks in place of Warehouse or Purchaser.
- Claim the system is a full accounting package.

---

# 10. Production Staff Guide

## Your purpose

You turn approved materials into safe finished products and record what happened on the production floor. Your records must show actual usage, safety readings, output, waste, and byproducts.

## Main pages

- **1 Request Materials:** create and track digital requisitions.
- **2 Active Runs:** the main workbench for running batches.
- **3 CCP Logs:** temperatures, time, cooling, and pressure checks.
- **4 Product Processing:** product-specific stages.
- **5 Waste & Byproducts:** record losses and reusable outputs.
- **6 Finish & Send to QC:** reconcile and hand off the completed batch.
- **All Batches, Pasteurization History, Yield Tracking, Reconciliation, Recipes, Yogurt Conversions:** reference and history.

## Request materials

1. Open **1 Request Materials** and select **New Requisition**.
2. Select the base recipe and planned batch volume.
3. Review the calculated materials.
4. Add or correct the planned request only when the real production plan requires it.
5. Submit to GM and wait.
6. After GM approval, wait for Warehouse Raw to issue the materials.
7. When fulfilled, open the ready requisition or dashboard action to start the linked run.

## Run a batch

1. Open **2 Active Runs** and select the run.
2. Confirm the product and issued materials.
3. Record actual ingredient quantities used. These may differ from the recipe plan.
4. Record each required processing stage.
5. Log CCP readings when they happen, not at the end from memory.

## Required safety records

- Pasteurization: 75 C for 15 seconds using HTST.
- Homogenization: 1000 to 1500 psi where required.
- Cooling: 4 C or below where required.
- Product-specific steps: separation for butter, cooking/pressing for cheese, pasteurized-milk source for yogurt.

## Record yield, waste, and byproducts

1. Enter the actual packaged output using full boxes or cases plus loose pieces.
2. Review expected versus actual yield and efficiency.
3. Record spillage, failed product, samples, or other losses.
4. Record reusable byproducts such as whey, skim milk, cream, or buttermilk and their destination.
5. Check that inputs are explained by finished output, byproducts, and waste.

## Finish and send to QC

1. Open **6 Finish & Send to QC**.
2. Complete the material reconciliation.
3. Confirm required CCP logs and output counts are present.
4. Submit the batch.
5. The batch becomes Pending QC. Do not send it directly to Warehouse FG.

## Do not

- Take stock without a digital requisition.
- Use raw milk directly for yogurt.
- Treat recipe amounts as actual usage without checking the floor.
- Skip byproducts or hide loss inside the finished yield.
- Edit QC's decision.

---

# 11. QC Officer Guide

## Your purpose

You are the food-safety gatekeeper. You decide whether incoming milk may enter storage and whether a completed batch may become saleable Finished Goods.

## Main pages

- **Milk Receiving:** record incoming farmer delivery details.
- **Milk Grading:** enter test results and accept or reject milk.
- **Farmers:** review farmer status and quality history.
- **Batch Release:** review completed Production batches.
- **Print Labels:** print traceable batch labels after release.
- **Expiry Management:** monitor expiring stock and safe transformation options.
- **Disposals / Batch Recalls:** manage safety exceptions and approved actions.
- **Daily Report / Farmer Summary:** review quality activity.

## Grade incoming milk

1. Record the delivery before the milk enters a tank.
2. Perform the required physical and analyzer tests.
3. Enter actual values, including APT, acidity, specific gravity, fat, sediment, smell, and appearance as required.
4. Accept only when the sample meets the standard.
5. Reject failed milk with the real reason. Rejected milk never enters raw storage.

## Release a finished batch

1. Open **Batch Release** and choose **Verify**.
2. Review Production's CCP readings. QC reviews them; QC does not re-enter them.
3. Physically check taste, smell, appearance, labels, and packaging integrity.
4. Count full boxes or cases and loose pieces.
5. The physical total must match Production's recorded output.
6. Choose **Release for Sale** or **Reject Batch** and add notes.
7. Submit verification. Released stock becomes available for Warehouse FG receiving.

## Expiry, disposal, and recall

- Use **Expiry Management** to identify products requiring action.
- Use yogurt transformation only when the product is still safe and the approved conversion rule applies.
- When Warehouse FG reports expired or damaged stock, physically inspect it and create the request in **Disposals**. The request must identify the exact Finished Goods record, quantity, reason, and disposal method.
- Submitting the request sends it to the General Manager. It does not remove stock yet.
- After GM approval, Warehouse FG performs the physical disposal and records its completion from the **Approved Disposals** section of its Dashboard.
- Use **Batch Recalls** to trace and control a released batch when a later safety problem is found.

## Do not

- Pass milk before physical testing.
- Release a batch with missing required CCP checks.
- Ignore a physical count difference.
- Send a rejected batch to Finished Goods.

---

# 12. Warehouse Finished Goods Guide

## Your purpose

You control saleable finished stock from QC release through delivery. You receive, store, pick, document, dispatch, and handle returns using exact batch and piece counts.

## Main pages

- **FG Inventory:** released stock by product, batch, quantity, and expiry.
- **Chiller Storage:** physical storage locations.
- **Receive from Production:** receive QC-released batches.
- **Delivery Receipts:** orders ready for full picking and DR creation.
- **Dispatch:** verify and send completed deliveries.
- **Customer List:** delivery reference information.
- **Inventory Report / Dispatch History:** review past activity.
- Dashboard sections also show pending disposals and recalls.

## Receive from Production

1. Open **Receive from Production**.
2. Select a QC-released batch.
3. Physically count the products and compare with the QC release.
4. Assign the chiller location.
5. Confirm receiving. The batch becomes saleable FG inventory.

## Pick an order

1. Open **Delivery Receipts**.
2. Select an approved order marked ready for stock.
3. Choose **Start Picking**.
4. Pick earliest-expiry safe batches first.
5. Record full boxes and loose pieces exactly.
6. Complete every order line. A short pick must not create a DR.
7. Generate and print the Delivery Receipt only after the full-pick check passes.

## Dispatch

1. Open **Dispatch** and select the prepared DR.
2. Verify ordered, picked, and reserved quantities match.
3. Confirm the physical truck load and document copy.
4. Dispatch. Inventory is deducted through the controlled release.

## Handle a return

1. Open the original DR and record the returned product, batch, quantity, and reason.
2. Inspect the returned units physically.
3. Send safe, sealed items to pending restock; confirm restock before they become saleable.
4. Send questionable quality to QC Hold.
5. Send damaged or expired goods to disposal.
6. Keep the return linked to the original DR so the billable amount stays correct.

## Report and complete a Finished Goods disposal

1. Open **FG Inventory** and find the expired, damaged, or unsafe batch.
2. Physically isolate it so it cannot be picked or dispatched.
3. Select **Report to QC**. This only sends the stock record to QC; it does not remove inventory.
4. QC opens the notification or **Disposals**, verifies the physical stock, enters the exact quantity, reason, and disposal method, then submits the request.
5. The General Manager opens **Pending Approvals**, reviews the disposal, and approves or rejects it.
6. If approved, Warehouse FG opens its **Dashboard** and finds the request under **Approved Disposals**.
7. Perform the physical disposal using the approved method.
8. Select **Complete Disposal** and record the location, witness, and notes.
9. Only after completion does the system reduce the Finished Goods stock and close the disposal record.

If QC or the GM rejects the request, keep the stock isolated and follow the written decision. Do not remove it from inventory or return it to saleable stock without a new approved action.

## Do not

- Receive a batch still Pending QC.
- Pick from expired or unreleased stock.
- Create a DR from a partial pick.
- Dispatch before printing and verifying the DR.
- Return damaged stock directly to saleable inventory.
- Dispose of stock directly from the inventory list without QC review and GM approval.

---

# 13. Sales Custodian Guide

## Your purpose

You manage institutional, supermarket, feeding-program, and wholesaler orders. You keep the customer's original request, enter the reviewed order, coordinate approved changes, and monitor credit balances.

## Main pages

- **Customer List / Wholesalers:** customer records and credit details.
- **Direct Order:** phone or in-person wholesaler orders.
- **Customer PO Inbox:** institutional POs received through email.
- **Pending Orders / Order History:** order tracking.
- **Aging Report / Collections Due:** monitor balances; Cashier records payment.
- **Sales Report / Customer Performance:** sales and customer trends.

## Process an emailed customer PO

1. Open **Customer PO Inbox** and choose **Check Email**.
2. Open a For Encoding email.
3. Read the original order in the left-side viewer. It may be written in the email or supplied as an attached document. Choose **Open Full Screen** when an attachment is available.
4. Confirm the customer matched automatically from the registered sender email.
   Sales cannot change this customer. An unknown sender is rejected until an
   administrator registers the customer's official email.
5. Enter the customer PO number and requested delivery date from the request. When the email or file provides clear values, the system may suggest them, but Sales must verify them.
6. Add each product, quantity, order unit, customer price when shown, and remarks.
7. Save Order Details.
8. Resolve every warning before creating the order.

An attachment is not mandatory. A clear email order is accepted and preserved as the original customer request. An empty message with no usable order details and no supported attachment is rejected. Attachments are also rejected when their contents do not match the stated file type. The system does not automatically create product lines from free-form writing; Sales still reads and checks every item.

## Correct typing versus change the order

Use **Correct Encoding** when Sales typed the attachment incorrectly. Explain the typing correction and make the entered details match the original PO.

Use **Record Customer Call** when the customer agrees to a real change. Record:

- what changed;
- the reason;
- customer representative;
- phone call as confirmation method;
- date and time; and
- optional notes.

Save the agreed order after the call. The original email and any attachment must remain unchanged.

## Handle unavailable stock

- If the customer keeps the full quantity, record the customer's agreement to wait and create the Sales Order. After GM approval, the shortage appears under **Customer orders needing production** on the Production dashboard. Delivery remains locked.
- If the customer accepts a lower quantity, replacement, later date, or removed line, record the call before saving the changed order.
- Never quietly lower the quantity just to make the stock warning disappear.

## Produce stock for an approved customer order

1. Sales records the full customer request and the phone confirmation when the customer agrees to wait.
2. GM approves the Sales Order.
3. Production opens **Customer orders needing production** on the dashboard and chooses **Plan production**.
4. Production reviews the selected recipe, enters the correct bulk batch volume, and submits the material requisition.
5. GM approves the material request and Warehouse Raw issues the materials.
6. Production completes the run, QC releases it, and Warehouse FG receives it.
7. Warehouse FG can prepare the customer order only when the full approved quantity is available.

## Direct wholesaler order

1. Open **Direct Order**.
2. Select an eligible wholesaler or small business.
3. Select only available released products.
4. Enter full boxes and extra units.
5. Review official price, stock, payment mode, and credit information.
6. Send the order to the GM. It remains locked from Warehouse FG while approval is pending.
7. If the projected credit balance exceeds the customer's limit, explain that the GM must decide the credit exception.
8. After approval, Warehouse FG prepares the delivery using FIFO.

## Aging and collections

Use Aging Report to see current, 31-60, 61-90, and over-90-day balances. Use Collections Due to identify customers needing follow-up. Send the customer to Cashier for actual payment recording.

## Do not

- Claim the system automatically reads every PDF.
- Change a customer order without evidence.
- Create the same Sales Order twice from one PO.
- Do not use Direct Order for major institutions; use Customer PO Inbox.

---

# 14. Cashier Guide

## Your purpose

You handle walk-in sales and are the only user who receives customer payments for delivered credit sales.

## Main pages

- **Quick Sale (POS):** walk-in sales.
- **Transaction History:** completed sales.
- **Collect Payment:** search DR balances and receive payment.
- **Collection History:** Official Receipts and pending checks.
- **Daily Summary / Cash Position:** shift totals and reconciliation.

## Process a walk-in sale

1. Open **Quick Sale**.
2. Select the product and exact unit.
3. Enter quantity and confirm available released stock.
4. Choose payment method and enter required reference details.
5. Complete the sale and issue the Sales Invoice.
6. Check the transaction in History.

## Collect a credit delivery

1. Open **Collect Payment**.
2. Search the DR number.
3. Confirm customer, delivered amount, previous payments, and remaining balance.
4. Enter the amount actually received.
5. Choose cash, check, bank transfer, or supported digital method.
6. Complete the required payment details and create the Official Receipt.

## Handle a check

1. Enter bank, check number, check date, account owner, amount, and notes.
2. Submit as **Pending for Clearing**.
3. The customer balance stays unchanged while pending.
4. After the bank confirms, open the pending check in **Collection History**.
5. Enter a cleared date that is not before the check date and not in the future.
6. Enter the bank confirmation or reference.
7. Mark **Cleared**. Only then does the balance reduce and the collection enter reports.
8. If rejected by the bank, mark **Bounced**. The balance remains due.

## End-of-day check

- Compare physical cash and payment references with the system summary.
- Review pending checks separately from cleared money.
- Finish reconciliation by the company's daily cut-off.
- Report differences instead of changing transactions to force a match.

## Do not

- Clear a check before bank confirmation.
- Use a future clearing date.
- Receive supplier or farmer payments; those are company disbursements handled by Finance.
- Let Sales record a customer collection.

---

# 15. Full Manual Demonstration

Use a small quantity and one clearly available product. Keep each account in a separate browser session or log out between users.

## Demonstration 1: Raw material purchase with final RR check

1. Log in as Warehouse Raw.
2. Open **Low Stock Alerts**, confirm a low item, then open **Purchase Requests**.
3. Create and submit a PRS with one or more ingredients or packaging items.
4. Log in as Purchaser.
5. Open **Canvassing**, open the PRS, and confirm that each line shows only suppliers accredited for that ingredient.
6. Enter three supplier quotes for every line.
7. Review the cheapest result and select **Create & Send to GM**.
8. Open **Purchase Orders** and confirm that every supplier PO is waiting for GM approval.
9. Log in as GM and approve the PO.
10. Log in as Warehouse Raw after the supplier delivery.
11. Open **Receive Deliveries**, record the accepted delivery, and generate the RR.
12. Log in as Purchaser.
13. Open the PO and select **Verify RR** after comparing quantities.
14. Log in as Finance and confirm the payable is visible only because a real approved purchase exists.

## Demonstration 2: Production to Finished Goods

1. Log in as Production and create a material requisition from a recipe.
2. Log in as GM and approve it.
3. Log in as Warehouse Raw and fulfill it.
4. Return to Production and start the ready run.
5. Record actual usage, processing stages, 75 C for 15 seconds, required cooling, and any product-specific step.
6. Record actual packaged output, waste, and byproducts.
7. Finish and send the batch to QC.
8. Log in as QC and open **Batch Release**.
9. Review the CCP records and enter the physical box and loose-piece count.
10. Release the batch.
11. Log in as Warehouse FG and receive it into a chiller.
12. Confirm it now appears as saleable FG inventory.

## Demonstration 3: Emailed PO to credit collection

1. Send a clear order in the email message, or attach a customer PO document, from a customer's registered email to the configured order mailbox.
2. Log in as Sales and choose **Check Email**.
3. Open the For Encoding message and show that the original email or attachment stays visible.
4. Enter the customer PO details and one or more lines.
5. Save the order.
6. To demonstrate a customer change, enter a quantity different from the original request and record the phone call with the customer's agreement.
7. Save and create the Sales Order.
8. Complete any GM credit approval.
9. Log in as Warehouse FG and show that picking is blocked if released stock is short.
10. When stock is sufficient, pick the complete order, generate and print the DR, then dispatch.
11. Log in as Cashier and search the DR.
12. Record a check payment. Show that it stays Pending for Clearing and the balance does not reduce.
13. Open Collection History after the bank confirmation and mark the check Cleared with a valid date and reference.
14. Confirm the DR balance is reduced and the collection appears in reports.

## Demonstration 4: Role access check

1. Log in as Warehouse Raw.
2. Type the Admin dashboard address directly.
3. Confirm the system redirects or shows Access Denied.
4. Repeat by trying Warehouse Raw from a Warehouse FG account.
5. Explain that both the page and the saved action are protected by role.

---

# 16. Common Problems and What to Do

## The page keeps loading or says Network Error

- Confirm Apache and MySQL are running in XAMPP.
- Confirm the address begins with `https://localhost/HighlandFreshAppV4/`.
- Refresh once after services are running.
- If only one page fails, record the page address and visible message for the developer.

## A user sees Access Denied

Confirm the user has the correct role. Do not change the page address to work around access. GM can reassign the role in **Users & Accounts**, which ends old sessions.

## A supplier is active but missing from Finance Payables

This is normal when the supplier has no approved, received obligation. Supplier registration alone does not create a payable.

## A product is shown but cannot be ordered

The product may be active in the catalog but have no QC-released Finished Goods stock. Sales may preserve institutional demand and wait for stock, but a direct order cannot use unavailable stock.

## A batch is missing from Finished Goods

Check whether Production finished it, QC released it, and Warehouse FG received it. All three steps are required.

## An emailed PO is not visible

- Confirm the message reached the configured company mailbox.
- Confirm it contains clear order details in the email or has a supported attachment, normally PDF.
- Select **Check Email** and wait for the result.
- Read the mailbox message shown at the top. Do not repeatedly click while a check is still running.

## A check cannot be cleared

The clearing date cannot be before the check date and cannot be in the future. Use the actual bank confirmation date and reference.

## A record looks wrong

Do not delete it to hide the problem. Use the available correction, rejection, return, adjustment, or archive action and enter a clear reason. Keep the original evidence.

---

# 17. Documentation Map for Teammates

## Read these as the main rules

1. `docs/HIGHLAND_FRESH_TEAM_USER_MANUAL.md` - plain-English operating guide and demonstration flow.
2. `system_context/HighlandFresh_PRD.md` - complete product scope and business requirements.
3. `system_context/operational_rules.md` - detailed operating rules and cross-department controls.
4. `system_context/HIGHLAND_FRESH_RBAC_MATRIX.md` - which role may view or change each area.

## Read these for role detail

- `system_context/general_manager.md`
- `system_context/warehouse_raw.md`
- `system_context/purchaser.md`
- `system_context/finance_officer.md`
- `system_context/production_staff.md`
- `system_context/production_requirements.md`
- `system_context/quality_control_user.md`
- `system_context/warehouse_finished_goods.md`
- `system_context/sales_custodian.md`
- `system_context/cashier.md`

## Read these for module detail and testing

- `docs/PR_TO_PO_SUPPLIER_CONSOLIDATION.md`
- `docs/PURCHASER_MODULE_TESTING.md`
- `docs/PRODUCTION_MODULE_DOCUMENTATION.md`
- `docs/PRODUCTION_YIELD_LOSS_MODULE.md`
- `docs/QC_MODULE_DOCUMENTATION.md`
- `docs/qc/QC_WORKFLOWS.md`
- `docs/qc/TESTING_GUIDE.md`
- `docs/USER_ONBOARDING_WORKFLOW_IMPLEMENTATION.md`

## Important document rule

Some older files describe an earlier plan, an old gap assessment, or features that were later changed. For example, an older role document may mention full financial statements even though the current PRD says accounting is outside scope. When documents disagree:

1. Follow this team manual for the current demonstration.
2. Follow the latest PRD and operational rules for business policy.
3. Check the current page and backend behavior.
4. Ask the project lead before changing the system to match an older note.

---

# 18. Final Team Checklist

Before showing the system to an instructor or panel:

- Every teammate knows their role and the role immediately before and after it.
- Test accounts use only the nine active roles.
- The team can explain why customers, farmers, suppliers, and drivers do not log in.
- The team can show PRS -> three quotes -> PO -> GM -> RR -> Purchaser verification.
- The team can show requisition -> production -> QC -> Finished Goods.
- The team can explain that an emailed PDF is preserved and manually encoded beside the original.
- The team can explain when a customer phone call record is required.
- Warehouse FG cannot create a DR from a short pick.
- Cashier is the only role that receives customer payments.
- A check does not reduce the balance until bank clearing.
- Finance pays approved company obligations and does not perform full bookkeeping.
- Wrong-role URLs show Access Denied.
- No one deletes history to make a demonstration look clean.

The best way to demonstrate Highland Fresh is to narrate every handoff: **who created the record, who approved it, who physically checked it, and who receives the next task.**
