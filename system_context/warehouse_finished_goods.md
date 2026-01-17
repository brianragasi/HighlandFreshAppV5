Based on the discussion, the **Warehouse FG (Warehouse Custodian for Finished Goods)**—often referred to as the **"Releasing Officer"**—acts as the final link in the plant’s internal chain. Their function is to manage the "Chillers" and ensure that the right products reach the right customers with the correct documentation.

Here is the comprehensive function of the **Warehouse FG** as discussed:

### 1. Intake from Production (Finished Goods Receipt)
The Warehouse FG custodian is the recipient of the production floor’s hard work.
*   **Accepting the Batch:** Once Production is finished and QC has cleared the batch for release, the Warehouse FG custodian "Receives" the items into the system.
*   **Storage Management:** Physically organizing the products in the storage area (chillers) and ensuring the digital inventory reflects exactly what is sitting on the shelves.

### 2. Fulfillment of Institutional Orders
While the Sales Custodian handles the "paperwork" of the order, the Warehouse FG custodian handles the **physical fulfillment**.
*   **PO Visibility:** They monitor the system for Purchase Orders (POs) entered by the Sales Custodian for supermarkets (SM, Gaisano, etc.) or Feeding Programs (DepEd).
*   **Picking & Packing:** They pull the correct products from the chiller based on the order requirements.

### 3. Barcode-Based Dispatch & FIFO
Ma’am placed a heavy emphasis on using technology to prevent spoilage and ensure accuracy during the release process.
*   **Scanning "Per Day" Barcodes:** The custodian uses a barcode scanner to "Release" products. As discussed, these barcodes help them identify the **Manufacturing Date** and **Expiry Date**.
*   **Enforcing FIFO (First-In, First-Out):** Their function is to ensure that the "oldest" safe stock is delivered first, preventing products from expiring in the chiller.

### 4. Linking Documentation (The "DR Number" Logic)
This is a critical integration function discussed at length. The Warehouse FG custodian is the source of the data the Cashier needs for collection.
*   **DR Generation:** When products are released, the custodian inputs or generates a **DR (Delivery Receipt) Number**.
*   **System Bridge:** By tagging a release with a DR Number, they create a searchable record. As Ma'am noted, this allows the Cashier to simply type in the DR Number later to see exactly what was delivered and how much is owed.

### 5. Managing Product Variants & Descriptions
The custodian ensures that the inventory doesn't get "muddled" by similar products.
*   **Volume & Size Accuracy:** They are responsible for distinguishing between variants mentioned in the audio, such as **Choco Milk 330ml vs. 500ml**, or the **Highland Fresh Butter 250g**.
*   **Product Clarity:** Ensuring that the description in the system matches the physical item being loaded onto the delivery truck.

### 6. Loading & Logistics Coordination
The Warehouse FG custodian coordinates with the drivers to get the product out the door.
*   **Dispatch Verification:** They verify that what is loaded onto the truck matches the **Charge Sales Invoice (CSI)** or **Delivery Receipt (DR)**.
*   **Inventory Depletion:** Their "Release" action in the system is what officially "lesses" (deducts) the stock from the Finished Goods inventory, keeping the books accurate.

### 6A. Multi-Unit Inventory Management (Box vs. Piece)
The Warehouse FG custodian must manage inventory at both Box and Piece levels to support retail (tingi) sales to sari-sari stores.

#### Unit Conversion Handling
*   **Dual-Unit Tracking:** Every product in the chiller is tracked in both Boxes and Pieces.
*   **Product Conversions:** Each product has a defined conversion ratio:
    *   Milk Bar: 1 Box = 50 Pieces
    *   Fresh Milk 200ml: 1 Crate = 24 Bottles
    *   Choco Milk 330ml: 1 Case = 24 Bottles
    *   Butter 250g: 1 Case = 20 Packs
*   **Visual Inventory Display:** System shows "8 Boxes + 14 Pieces" not just "8.58 Boxes".

#### "Box Opening" Process
When releasing partial boxes, the custodian follows this workflow:
1.  **Scenario:** A Wholesaler at the gate requests 1 box and 10 pieces.
2.  **System Action:**
    *   Deduct 1 full box from inventory.
    *   Digitally "open" the next box (convert 1 box → individual pieces).
    *   Deduct 10 pieces from the opened box.
3.  **Inventory Update:** If there were 10 boxes:
    *   Before: 10 Boxes, 0 Pieces
    *   After: 8 Boxes, 14 Pieces (10 - 1 - 1 opened = 8 boxes; 24 - 10 = 14 pieces)

#### Release Entry UI Requirements
*   **Unit Toggle:** Every release must have a quantity + unit selector:
    ```
    Quantity: [ 10 ]  Unit: [ Pieces ▼ ]
                            [ Boxes   ]
    ```
*   **Mixed Releases:** Support orders like "2 boxes and 15 pieces" in a single transaction.
*   **Auto-Conversion:** System calculates total pieces for FIFO and financial calculations.

#### Inventory Accuracy Responsibility
*   **Physical Count:** Regular reconciliation of physical boxes + loose pieces against system records.
*   **Variance Tracking:** Log any piece-level discrepancies (opened boxes not matching system).
*   **No Estimation:** Never round or estimate—exact piece counts required.

### 7. Returns/Bad Orders Management
The Warehouse FG custodian handles all product returns and bad orders from delivery runs, ensuring proper tracking, disposition, and inventory reconciliation.

#### 7.1 Receiving Returned Products
*   **Return Receipt Creation:** When a driver returns with undelivered or rejected products, the custodian creates a **Return Receipt** linked to the original **DR Number**.
*   **Physical Inspection:** All returned items are physically inspected before being processed in the system.
*   **Batch Verification:** For dairy products (Milk Bar, Choco Milk, etc.), the custodian scans or records the **Batch Number** and **Expiry Date** of returned items—critical for traceability.

#### 7.2 Return Reason Categorization
Each return must be classified with a specific reason code:
*   **Damaged in Transit:** Product packaging compromised during delivery (crushed, leaked, etc.)
*   **Expired/Near-Expiry:** Product past expiry or customer rejected due to short shelf life
*   **Customer Rejection:** Customer refused delivery (wrong order, changed mind, no payment)
*   **Quality Issue:** Customer complaint about product quality (taste, appearance, contamination)
*   **Overage/Overload:** Extra items loaded that weren't part of the order

#### 7.3 Disposition Decision Workflow
Based on the return reason and product condition, the custodian determines the action:
*   **Return to Inventory:** If product is still sealed, within expiry, and passed inspection—item goes back to chiller stock with FIFO position maintained based on expiry date
*   **Hold for QC Review:** If quality is questionable, item is placed in a **QC Hold area** pending inspection by Quality Control
*   **Dispose/Write-Off:** If damaged, expired, or failed QC—item is marked for disposal with proper documentation for inventory write-off
*   **Rework/Repack:** If outer packaging damaged but product intact, may be sent for repackaging (rare for dairy)

#### 7.4 On-the-Spot Replacement Tracking
Drivers may carry extra stock to replace damaged items during delivery:
*   **Replacement Log:** When a driver replaces a damaged item on-site, this is recorded as a **Replacement Transaction** linked to the DR
*   **Net Delivery Calculation:** System tracks: Original Quantity - Returns + Replacements = **Net Delivered Quantity**
*   **Driver Accountability:** Extra stock loaded for potential replacements is tracked, and drivers must account for all items (delivered, returned, or used as replacement)

#### 7.5 Integration with Delivery Receipts
*   **DR Amendment:** Returns and replacements are reflected as amendments to the original DR, maintaining audit trail
*   **Customer Signature:** If customer rejected items, the return note should indicate reason and ideally have customer acknowledgment
*   **Cashier Visibility:** The Cashier sees the **Net Billable Amount** after returns are processed, ensuring they collect the correct amount

#### 7.6 Bad Order Reporting & Analytics
The system provides reports to identify patterns and reduce losses:
*   **Returns by Product:** Which products have the highest return rates? (May indicate quality or packaging issues)
*   **Returns by Route:** Which delivery routes have the most returns? (May indicate handling or timing issues)
*   **Returns by Driver:** Which drivers have the highest return rates? (May indicate training needs)
*   **Returns by Reason:** Breakdown of return reasons to identify root causes
*   **Returns by Customer:** Which customers frequently reject orders? (May need order confirmation improvements)
*   **Financial Impact:** Total value of returns, write-offs, and replacement costs

### Summary
In the Highland Fresh system, the **Warehouse FG custodian’s function is "Order Accuracy and Spoilage Prevention."** They ensure the chiller is organized, the oldest milk is released first, and every delivery is tied to a **DR Number** so that the company can eventually get paid.