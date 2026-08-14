# Advisor Feedback Assessment and Action Plan

**Verified against the current codebase:** August 3, 2026

This document separates confirmed system gaps from concerns that the current
system already addresses. It can be used when explaining the revision plan to
the instructor or panel.

## Implementation Update

Updated on August 3, 2026:

- Supplier and customer phone/email checks now run on both the screen and the
  server.
- The Purchaser's **Email Final PO** action now creates an approved PO PDF,
  emails it to the supplier address on file, and records every send attempt.
- A failed email leaves the PO approved and available for retry.
- The Sales **Customer PO Inbox** now receives the customer's own attachment,
  usually a PDF, and keeps the original email and file unchanged.
- Sales opens the attachment and manually enters the PO number, delivery date,
  product, quantity, order unit, customer price when shown, and remarks. The
  system checks those entries instead of claiming it can read every PDF layout.
- When the customer agrees to a change by phone, Sales records the change,
  reason, contact person, phone method, date/time, and notes before creating the
  Sales Order.
- Orders larger than current Finished Goods stock are retained as reviewed
  demand. Picking and Delivery Receipt creation remain locked until Production,
  QC, and Warehouse FG make the required quantity available.
- A server mailbox job can run every five minutes, so new orders can arrive
  while nobody has the Sales screen open.
- Finished Goods deliveries now keep the exact stock used for each Delivery
  Receipt under Reserved, In Transit, Delivered, Returned, or Partially
  Returned states.

The customer order mailbox is configured to use the Highland Fresh Gmail test
account. Gmail POP access must be enabled on that account before the scheduled
mailbox job can receive live customer messages. The Excel test upload and
customer PO template are no longer part of the inbox. Direct Order remains
available for registered wholesalers and small customers who order in person
or by phone.

## Executive Decision

| Concern | Current Finding | Decision |
|---|---|---|
| Final PO still has to be sent outside the system | Confirmed gap | Automate PDF creation and supplier email after GM approval |
| Institutional orders must be retyped from phone calls or emailed POs | Confirmed gap | Receive the emailed PO as evidence, then provide a checked manual entry screen so Sales can encode the final agreed order |
| Finished goods disappear from inventory before customer acceptance | Confirmed design gap | Track stock as Available, Reserved, In Transit, Delivered, or Returned |
| Forgot Password is missing | No longer true | Keep and demonstrate the existing secure reset flow |
| Phone and email fields accept invalid values | Confirmed gap | Enforce the same checks in every screen and server endpoint |

## 1. Supplier Purchase Order Delivery

### What the system does now

The purchasing flow already provides:

1. Warehouse Raw creates a Purchase Request Slip (PRS).
2. Purchasing canvasses at least three suppliers and prepares the PO.
3. The General Manager approves or rejects the PO.
4. Purchasing can open a printable approved PO.
5. Purchasing clicks **Send Final PO**.

However, **Send Final PO** currently changes the database status from
`approved` to `ordered` and records who clicked it. It does not generate a PDF,
send an email, or prove that the supplier received anything.

### Assessment

This concern is real. The screen wording currently promises more than the
server actually performs.

### Required correction

The final approved PO must be sent by the system, not drafted again in a
personal email.

1. Require an active supplier email address before the PO can be sent.
2. Generate the PDF from the approved PO stored in the database.
3. Send the PDF from the server when the Purchaser clicks **Send Final PO**.
4. Record the recipient, send time, sender, and result.
5. Change the PO to `ordered` only after the email succeeds.
6. If sending fails, keep the PO approved, show **Delivery Failed**, and allow
   the Purchaser to retry.
7. Keep a printable/downloadable copy for audit and face-to-face delivery.
8. The Purchaser may make a short follow-up call, but should not recreate or
   manually email the document.

The PO must never be emailed before GM approval.

### Proof for the instructor

Use a test supplier email. Approve one PO, click **Send Final PO**, open the
received email, show the attached PDF, and then show the sent time and recipient
inside the PO record.

## 2. Institutional and Credit Customer Orders

### What the system does now

The Sales module now separates the two real customer situations:

- large supermarkets, feeding programs, and institutions send an official PO
  through the **Customer PO Inbox**; and
- registered wholesalers and small businesses that order in person or by phone
  use **Direct Order**.

The inbox accepts an order written in the customer's email or supplied as an
attachment, connects it to the customer using the sender email when possible,
and shows the unchanged source for manual entry. Sales enters the order details
after reading the original request. Direct Order
uses released stock and official prices, then sends the order to the GM. It
cannot be used for Gaisano or another large PO customer.

### Assessment

This was a confirmed gap under the instructor's revised direction. The inbox
now follows an evidence-first process: the system saves the original email and
any source file, and Sales enters the final order details after checking the
customer request and, when needed, calling the customer.

### Implemented behavior

1. Configure a dedicated company order email address for institutional customers.
2. Save the sender, subject, received time, email message, and any attached PO.
3. Match the sender to a customer account.
4. Match the sender to a customer account when possible, while allowing Sales
   to choose the correct customer when the sender is new or unclear.
5. Let Sales read the original email or open the attachment and enter products, quantities, units,
   prices, delivery date, and the customer PO number.
6. Flag unknown products, missing quantities, price differences, unavailable
   stock, and credit-limit problems instead of silently guessing.
7. Keep the entered order as a draft until Sales saves it. If a change was
   agreed by phone, require the phone confirmation record before creation.
8. Keep the original email, attachment, original requested values, and final
   saved values linked to the Sales Order.
9. Use the email message ID and file fingerprint, plus the customer PO number,
   to prevent the same request from creating two orders.
10. After confirmation, continue through the existing approval, warehouse,
    delivery, invoice, aging, and collection flow.

The customer may write the order in the email or send its own PDF or another
supported attachment. The system does not claim to understand every customer's
wording or document layout. Sales reads the original request and enters the order in the checked entry table.
The original request is never overwritten.

The target is a clear, auditable hand-off from the customer's document to the
final Sales Order, with checks and a recorded customer confirmation when the
order changes.

### Proof for the instructor

Email a test Gaisano PO containing many order lines. Show that:

1. the order appears in the Sales inbox as **For Encoding**;
2. Sales opens the original PDF and enters one or more order lines;
3. the saved draft shows product, unit, price, and stock checks;
4. a shortage requires a phone record before the changed order can be created;
5. the final Sales Order shows the agreed quantity while the original PDF and
   original requested quantity remain visible; and
6. sending the same email or using the same customer PO number does not create
   a duplicate order.

## 3. Finished Goods During Delivery

### What the system does now

The picking step removes the selected quantity from stock available to new
orders and records the exact Finished Goods lot against the Delivery Receipt.
Dispatch changes that record to In Transit. Customer acceptance and returns
then split the same tracked quantity into Delivered and Returned amounts.

### Assessment

This concern was real and is now addressed for newly prepared deliveries.
Historical Delivery Receipts remain as historical records because their exact
old lot choices cannot be safely reconstructed.

### Implemented states

Use clear stock states:

1. **Available:** Can be promised to a new order.
2. **Reserved:** Picked for a Delivery Receipt but still inside the warehouse.
3. **In Transit:** Loaded and dispatched; still owned and traceable by
   Highland Fresh, but unavailable for another order.
4. **Delivered:** Accepted by the customer and removed from company-owned
   inventory.
5. **Returned or Rejected:** Sent back to Warehouse FG for put-away, QC review,
   or disposal.

The inventory report should show both:

- **Available stock**, used for new orders; and
- **Company-owned stock**, which includes Available, Reserved, and In Transit.

### Correct flow

Sales Order approved -> Warehouse FG reserves and picks by FIFO -> DR printed ->
truck dispatched and stock moves to In Transit -> signed DR and returns are
recorded -> accepted quantity becomes Delivered -> rejected quantity returns to
Warehouse FG.

### Proof for the instructor

Dispatch ten bottles. Show that available stock is lower while In Transit shows
ten. Confirm eight accepted and two returned. The final record should show eight
Delivered and two routed back to Warehouse FG without losing or duplicating
stock.

## 4. Account Recovery and Input Validation

### Forgot Password

This feature is already implemented:

- the login screen links to **Forgot Password**;
- a random reset link is emailed;
- only a hash of the reset token is stored;
- the link expires after one hour;
- old links are invalidated; and
- a used link cannot be used again.

This item should be demonstrated and tested, not listed as missing.

### Contact validation

This concern is real. Some screens use an email input field, but the supplier
and customer server endpoints do not consistently reject malformed phone
numbers or email addresses. Browser-only checks are not sufficient.

### Required correction

Create one shared rule used by all supplier, customer, farmer, user, and
delivery forms:

- Email is optional where allowed, but must be a valid address when supplied.
- Philippine mobile numbers must be exactly 11 digits and start with `09`.
- Landline numbers must follow an approved local format.
- Spaces and hyphens may be accepted for display, but the saved value should be
  normalized.
- The server must repeat every important check even if the screen already
  checks it.
- Error messages must identify the exact field and expected format.

## Next Work

1. Connect the real Highland Fresh order mailbox and schedule the included
   mailbox job to run every five minutes.
2. Perform one live email test using a customer-owned PDF and enter its order
   details in the inbox.
3. Run the full PRS-to-RR purchasing test.
4. Run the complete emailed-order-to-return delivery test.
5. Keep additional PDF layouts as a future enhancement only if the business
   later wants automatic reading; the current workflow intentionally uses Sales
   entry after the original attachment is reviewed.
