Based on the discussion, the **Wholesaler** is a special customer type in the Highland Fresh system—**NOT an employee or company driver**. These are independent entrepreneurs (often using motorcycles) who purchase products at wholesale prices from Highland Fresh and resell them at retail to small stores (sari-sari stores) at their own markup.

Here is the comprehensive function of the **Wholesaler** as discussed:

### 1. Role Description & Classification
The Wholesaler is fundamentally a **customer**, but with a special classification that grants them access to wholesale pricing.
*   **Customer Type:** Registered as a "Wholesaler" in the customer database, distinct from walk-in retail customers or institutional clients.
*   **Independent Business:** They are NOT Highland Fresh employees—they are independent businesspeople who buy wholesale and sell retail.
*   **Self-Managed Markup:** Their profit margin (markup to sari-sari stores) is their own business; Highland Fresh only concerns itself with the wholesale transaction.

### 2. Product Browsing & Ordering
The Wholesaler has access to a dedicated ordering interface showing wholesale prices.
*   **Product Catalog Access:** They can browse the full product catalog with **wholesale pricing** displayed (not retail prices).
*   **Order Placement:** They can place orders through the system, specifying quantities needed for their resale business.
*   **Real-Time Availability:** They can see current stock availability to plan their orders accordingly.
*   **Minimum Order Quantities:** If applicable, the system enforces minimum order requirements for wholesale purchases.

#### Box vs. Piece Ordering
Wholesalers (especially those serving sari-sari stores) need to order in flexible units:
*   **Unit Selection:** Every order line includes a unit selector:
    ```
    Product: Milk Bar
    Quantity: [ 5 ]  Unit: [ Boxes ▼ ]
                           [ Pieces  ]
    ```
*   **Mixed Orders:** A single order can include both:
    *   "2 boxes of Milk Bar" (100 pieces)
    *   "15 pieces of Milk Bar" (loose)
    *   **Total: 115 pieces**
*   **Price Display:** Shows both:
    *   Box Price: ₱500.00 (50 pcs × ₱10)
    *   Piece Price: ₱10.00 each
*   **Conversion Info:** Product cards show "1 Box = 50 Pieces" for reference.

#### Why This Matters for Wholesalers
*   **Sari-Sari Store Reality:** Small stores may only want 12 pieces, not a whole box.
*   **Inventory Flexibility:** Wholesalers can buy exactly what they need for their route.
*   **Markup Clarity:** Piece-level pricing makes markup calculation clear (₱10 wholesale → ₱12 retail = ₱2 per piece profit).

### 3. Pricing & Payment Management
Wholesalers operate on a cash/immediate payment model, keeping transactions clean.
*   **Wholesale Price Visibility:** They see only wholesale prices—their selling price to end customers is not tracked by Highland Fresh.
*   **Payment Methods:** They can pay online (via integrated payment gateway) or pay at pickup/delivery.
*   **Immediate Payment Reflection:** Once payment is made, it is immediately reflected in the system, updating their account status.
*   **No Commission Complexity:** Unlike the old "middleman" model, there is no commission calculation—they simply buy at wholesale and keep the difference.

#### Piece-Level Pricing & Markup
*   **Pricing Transparency:** Prices are shown at both Box and Piece level:
    | Product | Box Price | Pieces/Box | Per-Piece Price |
    |---------|-----------|------------|------------------|
    | Milk Bar | ₱500 | 50 | ₱10.00 |
    | Fresh Milk 200ml | ₱240 | 24 | ₱10.00 |
    | Choco Milk 330ml | ₱312 | 24 | ₱13.00 |
*   **Markup Calculation:** Wholesaler's profit is at the piece level:
    *   Wholesale: ₱10/piece
    *   Retail (to sari-sari): ₱12/piece
    *   **Markup: ₱2/piece (20%)**
*   **Order Total:** System calculates total based on mixed box/piece orders.

### 4. Credit Line Management (If Applicable)
For trusted wholesalers, Highland Fresh may extend credit terms.
*   **Credit Limit Assignment:** Qualified wholesalers may have a credit limit assigned to their account.
*   **Balance Tracking:** They can view their current credit balance and outstanding amounts.
*   **Payment Terms:** Credit terms (e.g., 7-day, 15-day) are tracked per wholesaler account.
*   **Credit Status Visibility:** They can see their credit standing before placing orders.

### 5. Pickup & Delivery Scheduling
Wholesalers coordinate how they receive their products.
*   **Pickup Scheduling:** They can schedule a pickup time at the Highland Fresh facility.
*   **Delivery Option:** If delivery is available, they can request delivery to their location.
*   **Order Confirmation:** They receive confirmation of their order with pickup/delivery details.
*   **Tracking:** They can track the status of their current orders (pending, ready for pickup, in transit, completed).

### 6. Order History & Account Management
Wholesalers have full visibility into their transaction history.
*   **Order History:** Complete record of all past orders with dates, quantities, and amounts.
*   **Invoice Access:** Ability to view and download invoices for their records.
*   **Payment History:** Record of all payments made, including method and date.
*   **Account Profile:** Manage their profile information, contact details, and business information.

### 7. UI/UX Requirements
The Wholesaler interface should be simple and mobile-friendly.
*   **Mobile-First Design:** Many wholesalers use smartphones; the interface must be responsive and easy to use on mobile devices.
*   **Simple Navigation:** Quick access to: Browse Products → Place Order → View Orders → Make Payment.
*   **Clear Pricing Display:** Wholesale prices prominently displayed with unit breakdowns.
*   **Order Status Dashboard:** At-a-glance view of pending orders, ready pickups, and outstanding payments.

### 8. Data Access & Permissions
Wholesalers have limited, customer-focused data access.
*   **Own Account Data:** Full access to their own orders, payments, and account information.
*   **Product Catalog:** Read-only access to product listings and wholesale prices.
*   **No Internal Data:** No access to production data, other customer data, or company financials.
*   **No Price Modification:** Cannot modify wholesale prices—prices are set by Highland Fresh.

### 9. Integration Points
The Wholesaler module integrates with several system components.
*   **Inventory System:** Orders pull from Finished Goods inventory via Warehouse FG.
*   **Payment Gateway:** Online payments integrate with the company's payment processing.
*   **Cashier Module:** Cash payments at pickup are processed through the Cashier.
*   **Delivery/Logistics:** If delivery is offered, integrates with dispatch scheduling.
*   **Accounting:** Wholesaler transactions feed into sales and receivables reporting.

### Summary
In the Highland Fresh system, the **Wholesaler's function is "Self-Service Wholesale Purchasing."** They are independent customers who buy products at wholesale prices, manage their own orders and payments, and resell to small retailers at their own markup. The system treats them as a special customer class—providing wholesale pricing and order management without the complexity of tracking their downstream sales or managing them as employees.
