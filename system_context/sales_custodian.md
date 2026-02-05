Based on the discussion, the **Sales Custodian** is the "Account Manager" for Highland Fresh’s large-scale and institutional clients. Their function is distinct from the Cashier because they don't focus on physical cash; instead, they focus on **Purchase Orders (POs), credit accounts, and institutional relationships.**

Here is the comprehensive function of the **Sales Custodian** as discussed:

### 1. Management of Institutional & Credit Sales
While the Cashier handles walk-in individuals, the Sales Custodian is responsible for "Big" clients. As discussed, this includes:
*   **Supermarkets & Malls:** Managing sales for outlets like SM, Robinsons, and Gaisano.
*   **Companies & Cafes:** Handling wholesale or regular credit-based orders.
*   **Government Accounts:** Managing the "Feeding Program" sales (e.g., DepEd).

### 2. PO (Purchase Order) Processing
The Sales Custodian is the primary user responsible for translating customer requests into system data.
*   **Receiving POs:** They are the "receiver" of the official Purchase Orders sent by supermarkets.
*   **Data Entry:** They input the PO details into the system—specifically "how much was ordered" vs. "how much was actually received" by the client.
*   **Order Fulfillment Coordination:** They ensure the order is recorded so that the Warehouse (Finished Goods) knows what to release.

### 3. "Aging" & Debt Monitoring
Because their primary domain is **Credit Sales (Utang)**, the Sales Custodian is responsible for monitoring the financial health of customer accounts.
*   **Balance Tracking:** Keeping track of what each institution owes.
*   **Aging Reports:** Monitoring the "Aging" of debt, specifically categorized into **30 days, 60 days, and 90+ days (Overdue).** This ensures the company knows which clients are late on payments.

### 4. Customer Hierarchy & Sub-Name Logic
The Sales Custodian manages complex customer structures, especially for government contracts.
*   **Sub-Categorization:** For a customer like **DepEd**, the Sales Custodian must be able to input "Sub-names" or "Descriptions."
*   **School-Based Tracking:** They ensure that while the "Customer" is DepEd, the system reflects which specific school (e.g., a school in Cagayan de Oro) received the delivery.

### 5. Credit Documentation (CSI & DR)
The Sales Custodian handles the documentation required for deliveries where cash is not exchanged immediately.
*   **Charge Sales Invoices (CSI):** They oversee the generation of "Charge" invoices for market deliveries.
*   **DR (Delivery Receipt) Linking:** They ensure the PO is linked to a Delivery Receipt number so the Cashier can find it later for collection.

### 6. Sales Monitoring & Fluctuation
Ma'am mentioned that she monitors sales weekly to adjust production. The Sales Custodian provides the data for this:
*   **Weekly Monitoring:** Identifying if a supermarket's sales are *"kusog"* (strong) or *"hinay"* (weak).
*   **Order Adjustments:** Based on these trends, they help decide whether to "add" more stock to a specific mall or "reduce" the delivery volume for the following week.
### 7. Multi-Unit Sales Tracking (Box vs. Piece)
The Sales Custodian must handle orders in both Box and Piece quantities, especially for smaller institutional clients and feeding programs.

#### Unit Flexibility in Orders
*   **PO Entry:** When recording a Purchase Order, the Sales Custodian can enter:
    *   "50 Boxes of Fresh Milk 200ml" (for large supermarkets)
    *   "30 Boxes + 12 Pieces of Milk Bar" (for schools with odd student counts)
*   **Unit Selector UI:**
    ```
    Quantity: [ 30 ]  Unit: [ Boxes  ▼ ]
    Add more: [ 12 ]  Unit: [ Pieces ▼ ]
    ```
*   **Conversion Display:** System shows total pieces for verification.

#### Feeding Program Piece-Level Logic
*   **School Orders:** A school with 247 students needs exactly 247 pieces, not 5 boxes (250 pieces).
*   **Waste Prevention:** Piece-level ordering prevents over-delivery and spoilage.
*   **Accurate Billing:** Invoice reflects exact pieces delivered, not rounded box counts.

#### Sales Reports
*   **Dual-Unit Reporting:** Reports show both box counts and exact piece totals.
*   **Conversion Accuracy:** "5 Boxes + 10 Pieces" displays as "130 Total Pieces" (if 1 Box = 24).
*   **Revenue Per Piece:** Financial reports can calculate revenue down to piece level.
### Summary
In the Highland Fresh system, the **Sales Custodian’s function is "Institutional Credit Management."** They ensure that big-ticket orders are properly documented as **Purchase Orders**, tracked as **Credit Sales**, and monitored via **Aging Reports** until the money is finally collected by the Cashier.