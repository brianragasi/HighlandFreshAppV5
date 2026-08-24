# Highland Fresh Defense Issue and Fix Register

This document checks each concern raised during the review. It records what was actually found in the system, what was changed, and how a teammate can test the result without reading code.

## Status Guide

- **Confirmed - fixed:** The problem was reproduced and corrected.
- **Confirmed - next:** The problem exists and is queued for correction.
- **Partly protected:** The system already handles part of the concern, but the full workflow still needs checking.
- **Not confirmed:** Current behavior does not match the reported problem.
- **Policy decision:** The system can support more than one valid rule; the team must confirm the intended business rule.

## Review Register

| # | Concern | Current finding | Status |
|---|---|---|---|
| 1 | Raw database errors shown to users | All shared API errors now hide SQL, database keys, file paths, and server details while preserving useful business messages. | Confirmed - fixed |
| 2 | Screens stay stale until manually refreshed | Important GM, Purchasing, Warehouse Raw, and Finished Goods queues now refresh quietly while visible and pause while a user is editing. | Confirmed - fixed |
| 3 | Notifications sent to unrelated roles | Every procurement notice now has one allowed recipient role. Wrong role and notice combinations are blocked, and old unread misrouted notices are closed. | Confirmed - fixed |
| 4 | Inactive suppliers or unlinked items appear in dropdowns | Direct PO and canvassing choices now require an active supplier, an active ingredient, and an active supplier-to-ingredient link. The final save checks the rule again. | Confirmed - fixed |
| 5 | Partial or damaged delivery has no clear backorder | Purchaser can await a backorder, verify completed replacement stock, or use an audited Close Short action. Short quantities remain recorded, Finance pays accepted stock only, and only the undelivered PR balance reopens. | Confirmed - fixed |
| 6 | Physical inventory surplus cannot be written on | A higher physical count is deliberately blocked and staff are sent to receiving or stock correction. The correction screen and approval record need verification. | Confirmed - next |
| 7 | Unpaid stock becoming usable confuses inventory and accounting | Receiving stock before payment can be correct for credit purchases, but the UI must clearly separate “received and usable” from “still payable.” | Policy decision |
| 8 | Production assumes perfect yield | Actual yield and losses exist, but expected normal process loss and its effect on efficiency need verification by product. | Partly protected |
| 9 | Rejected PRS leaves Warehouse in limbo | Rejection notification, reason, and low-stock follow-up must be tested together. | Confirmed - next |
| 10 | Dry weight is converted to liquid volume without density | Ingredients now declare whether they are solid, liquid, or counted. Liquid entries accept only liters or milliliters; grams require a separately approved density rule. | Confirmed - fixed |
| 11 | Removed maintenance scope still leaks into the system | The Maintenance user role was removed; MRO supplies remain under Warehouse Raw because production still consumes cleaning supplies, spare parts, and safety items. | Policy decision |
| 12 | Tank capacity can be exceeded | Capacity checks exist in some production paths; every batch-start and transfer path needs the same check. | Partly protected |
| 13 | Unrealistic demo data makes valid screens look broken | Test records and old experimental names remain in the database. A repeatable, realistic defense data set is needed. | Confirmed - next |
| 14 | Physical audit compares against the wrong stock number | The PRS page compared the shelf count with usable stock while the server compared it with the saved balance. | Confirmed - fixed |

## Fix 01 - Physical Stock Audit Uses the Correct Balance

### What Was Wrong Before

An ingredient could have two different numbers:

- **Saved balance:** the quantity recorded on the ingredient master record.
- **Usable stock:** the quantity in valid, unexpired batches.

The PRS screen used usable stock for the physical-count comparison. The server used the saved balance. For example, Beer could show 0 kg usable and 60 kg saved. Entering a shelf count of 0 showed a green “matches” message, but submission then demanded an explanation because the server correctly saw a 60 kg shortage.

### How It Was Fixed

- The screen now keeps usable stock and saved balance as separate values.
- Low-stock decisions still use usable stock.
- The physical shelf count is compared with the saved balance, matching the server.
- When the numbers differ, both are shown plainly.
- Error messages now include the counted quantity, saved quantity, and unit.

### Difference After the Fix

**Before:** `Current: 0 kg` followed by a green match, then a contradictory error after submission.

**After:** `Usable: 0 kg | Saved balance: 60 kg`. Entering 0 kg immediately shows `Short by 60 kg compared with the saved balance` and asks for an explanation before submission.

### Manual Test

1. Log in as **Warehouse Raw**.
2. Open **Purchase Requests** and click **New PRS**.
3. Choose an ingredient whose usable stock differs from its saved balance.
4. Confirm the item summary shows both **Usable** and **Saved balance**.
5. Enter the actual shelf count.
6. If it is lower than the saved balance, confirm the page immediately shows the shortage and reveals the explanation field.
7. Leave the explanation empty and submit. The page must stop the submission and state the saved balance clearly.
8. Enter a truthful reason such as `Expired batch awaiting disposal` and submit again.
9. Confirm the PRS is submitted and its details retain the saved balance, shelf count, difference, reason, person, and time.

### Files Changed

- `html/warehouse/raw/purchase_requests.html`
- `api/purchasing/purchase_requests.php`

## Fix 02 - Technical Errors Stay in the Server Log

### What Was Wrong Before

The shared API response helper returned an error exactly as it received it. Some save failures could therefore show database table names, key names, SQL text, or server file paths to an employee. The API was also configured to print PHP warnings directly into the browser.

This was both confusing and unsafe. An employee needs to know what action to take, not how the database is built.

### How It Was Fixed

- The shared response helper now recognizes technical database and server errors.
- Technical details are written to the private server log for troubleshooting.
- The browser receives a short, safe message instead.
- Normal business messages remain specific. For example, a wrong check-clearing date still explains exactly what must be corrected.
- Unexpected server failures are caught by one shared safety handler.
- PHP warnings are no longer printed into API responses.

### Difference After the Fix

**Before:** A failed save could show text such as `SQLSTATE`, `Duplicate entry`, a database key name, or a server file path.

**After:** A duplicate conflict says `This record already exists or conflicts with existing data. Check the entered information and try again.` An unexpected failure says `Something went wrong while processing the request. Please try again.` The private log still keeps the real detail for the developer.

### Manual Test

1. Log in as **General Manager**.
2. Open **Products**.
3. Try to add a packaging size that already exists for the same product.
4. Confirm the page explains that the packaging size already exists.
5. Confirm the message does not contain `SQLSTATE`, `Duplicate entry`, a key name, a table name, or a file path.
6. Open the browser's **Network** panel, select the failed save, and inspect its response.
7. Confirm the response contains the same clear message and no database details.
8. Enter a unique, valid packaging size and confirm it saves normally.
9. As an additional check, open **Cashier > Collection History**, try an invalid check-clearing date, and confirm the page still tells you exactly which date rule was broken.

### Automatic Check

Run:

```text
php tests/response_error_safety_test.php
```

Expected result: `Response error safety tests passed.`

### Files Changed

- `api/config/response.php`
- `api/bootstrap.php`
- `tests/response_error_safety_test.php`

## Fix 03 - Important Work Queues Update Without Reloading

### What Was Wrong Before

The main approval and receiving pages loaded their records only once. If Warehouse submitted a request while the General Manager already had the approval page open, the GM could keep seeing the old list until manually refreshing the browser. The same delay affected Purchasing, Warehouse Raw receiving, and Finished Goods receiving.

This could make an employee think that a request was lost or that another role had not completed its work.

### How It Was Fixed

- One shared background refresh now checks important queues every 15 seconds while the page is visible.
- Returning to a browser tab also checks for new work.
- The refresh pauses while a dialog is open or while the user is typing or selecting a value.
- The page updates its table and counters without a full browser reload.
- Failed background checks stay quiet and try again later instead of interrupting the user with repeated pop-ups.

The following screens now use this behavior:

- General Manager dashboard and Pending Approvals
- Purchaser dashboard and Purchase Orders
- Warehouse Raw requisitions and Receive Deliveries
- Warehouse Finished Goods Receive from Production

### Difference After the Fix

**Before:** A Warehouse request could be submitted successfully while the GM's already-open page continued showing zero requests until the GM pressed Refresh.

**After:** The new request appears on the GM's screen within 15 seconds. If the GM is writing an approval remark, the page waits and does not disturb that work.

### Manual Test

1. Open two separate browser sessions, such as a normal window and an incognito window.
2. In the first session, log in as **General Manager** and open **Pending Approvals**. Do not refresh this page.
3. In the second session, log in as **Warehouse Raw** and submit a new PRS.
4. Return to the GM page and wait up to 15 seconds.
5. Confirm the new PRS and approval counter appear without reloading the browser.
6. Open the PRS approval dialog and type a remark, but do not submit it.
7. In the Warehouse session, create another valid request.
8. Wait at least 15 seconds. Confirm the open approval dialog stays open and the typed remark remains unchanged.
9. Close the dialog. Within the next 15 seconds, confirm the queue shows the latest work.
10. Repeat the same basic check with **Purchaser > Purchase Orders**, **Warehouse Raw > Receive Deliveries**, and **Warehouse Finished Goods > Receive from Production**.

### Automatic Check

JavaScript syntax checks passed for the shared refresher and every connected page.

### Files Changed

- `js/ui/live-refresh.js`
- `html/admin/dashboard.html`
- `html/admin/gm_approvals.html`
- `html/purchasing/dashboard.html`
- `html/purchasing/purchase_orders.html`
- `html/warehouse/raw/requisitions.html`
- `html/warehouse/raw/receive_deliveries.html`
- `html/warehouse/fg/receiving.html`

## Fix 04 - Procurement Notices Go Only to the Role That Must Act

### What Was Wrong Before

The current pages already read notices by role, and the present user list no longer contains a Security role. However, several separate backend files created notices by accepting any role name given by the programmer. A wrong role typed in one of those files could save an irrelevant notice without being stopped.

This explains why older versions could show procurement notices to an unrelated account even though the current screens mostly filter correctly.

### How It Was Fixed

- One shared rule now states which role receives each procurement event.
- A PRS submission goes only to the Purchaser.
- A PO awaiting approval goes only to the General Manager.
- An approved PO delivery notice goes only to Warehouse Raw.
- The approved PO funding notice goes only to Finance.
- A Receiving Report awaiting checking goes only to the Purchaser.
- A Finished Goods disposal report goes only to QC.
- Any code that tries to send one of these notices to another role is stopped and privately logged.
- Old unread notices with an invalid event-and-role combination are marked read when the Purchaser dashboard next loads. Their history is preserved rather than deleted.

### Difference After the Fix

**Before:** A mistaken role name in a notification call could create a valid-looking notice for an employee who had nothing to do with that task.

**After:** The system refuses the wrong recipient. For example, a PO approval request cannot be sent to QC or a removed Security role; it is allowed only for the General Manager.

### Manual Test

1. Log in as **Warehouse Raw** and submit a PRS.
2. Confirm **Purchaser** receives the PRS notice. Confirm GM, QC, and Finance do not receive it.
3. As Purchaser, prepare the PO and send it for approval.
4. Confirm **General Manager** receives the approval notice. Confirm Warehouse Raw and QC do not receive it.
5. As GM, approve the PO.
6. Confirm **Warehouse Raw** receives the delivery notice and **Finance** receives the funding notice.
7. As Warehouse Raw, receive the delivery and generate the RR.
8. Confirm **Purchaser** receives the RR verification notice.
9. As Purchaser, verify the RR.
10. Confirm **Finance** receives the transaction-closed notice.
11. As Warehouse Finished Goods, report an expired batch for disposal review.
12. Confirm **QC** receives that notice and the other roles do not.

### Automatic Check

Run:

```text
php tests/procurement_notification_recipient_test.php
```

Expected result: `Procurement notification recipient tests passed.`

### Files Changed

- `api/helpers/procurement_notifications.php`
- `api/purchasing/purchase_requests.php`
- `api/purchasing/purchase_orders.php`
- `api/admin/gm_approvals.php`
- `api/warehouse/raw/receiving.php`
- `api/warehouse/fg/inventory.php`
- `api/purchasing/dashboard.php`
- `tests/procurement_notification_recipient_test.php`

## Fix 05 - Purchasing Uses Only Approved, Active Supplier Choices

### What Was Wrong Before

The system checked supplier accreditation when a new quote was entered, but an older quote could remain on a canvass after the supplier was archived or disconnected from that ingredient. The direct PO supplier list could also show an active supplier that had no active ingredient assigned to it.

That created a stale-choice risk: a supplier could look selectable on screen even though the current supplier register no longer allowed that purchase.

### How It Was Fixed

- The direct PO supplier list now shows only active suppliers with at least one active linked ingredient.
- Choosing a supplier still limits the item list to ingredients that supplier is currently approved to provide.
- Quote counts, lowest prices, and recommended choices ignore archived suppliers and removed ingredient links.
- Adding a new quote checks the supplier, ingredient, and their active link together.
- Creating the final PO checks the selected supplier and link again, protecting against an old browser tab.
- Old invalid quotes are kept as history but cannot be selected for a new PO.

### Difference After the Fix

**Before:** A quote from a supplier that was accredited yesterday could still look usable today after the GM archived that supplier or removed the ingredient assignment.

**After:** That quote is excluded from the current comparison. The Purchaser sees only choices allowed by the latest supplier register, and a stale screen cannot bypass the rule when saving.

### Manual Test

1. Log in as **General Manager** and open **Suppliers**.
2. Register an active supplier without assigning an ingredient, if the form permits the supplier-first setup.
3. Log in as **Purchaser**, open **Purchase Orders**, and start a direct PO.
4. Confirm the supplier with no active ingredient assignment is not offered.
5. As GM, edit that supplier and assign one active ingredient.
6. Return to the Purchaser screen and reopen the form. Confirm the supplier now appears.
7. Select that supplier and confirm only its assigned ingredient is offered.
8. Open a PRS canvass that contains a quote from this supplier.
9. In the GM session, archive the supplier or remove its ingredient assignment.
10. Return to the canvass and refresh. Confirm its old quote is no longer counted or recommended.
11. Try to create the PO from an old Purchaser tab. Confirm the system refuses the stale supplier choice.
12. Restore the supplier and ingredient assignment. Confirm the valid choice becomes available again.

### Automatic Check

Run:

```text
php tests/purchasing_choice_eligibility_test.php
```

Expected result: `Purchasing supplier and ingredient eligibility tests passed.`

### Files Changed

- `api/helpers/supplier_ingredient_catalog.php`
- `api/purchasing/suppliers.php`
- `api/purchasing/canvassing.php`
- `api/purchasing/purchase_orders.php`
- `html/purchasing/purchase_orders.html`
- `tests/purchasing_choice_eligibility_test.php`

## Fix 06 - Bottles No Longer Turn Liquid Into Grams

### What Was Wrong Before

The ingredient form mixed up the material, its measurement, and its container. An employee could describe a liquid as a bottle, measure its contents in grams, and enter a cost per gram without any approved density conversion. The whole-package option then made that unclear setup look official.

A bottle is only a container. It does not tell the system whether the contents are liquid, solid powder, or counted pieces.

### How It Was Fixed

- Every ingredient now has a required **Material Form**: Solid, Liquid, or Counted Item.
- Solid ingredients can use only kilograms or grams.
- Liquid ingredients can use only liters or milliliters.
- Counted items can use only pieces, packs, packets, rolls, or bottles.
- The supplier package section now describes what one bottle, sack, case, or other container **contains** in the chosen stock unit.
- Supplier price is entered against a clear price basis. The system then keeps a comparable cost per stock unit behind the scenes.
- The server repeats the same check, so an old page or a manually changed request cannot save a liquid ingredient in grams.
- Existing ingredients receive a material form based on their current stock unit during the database update.
- No automatic gram-to-milliliter conversion is performed. Such a conversion is allowed only after a product-specific density rule is formally added and approved.

### Difference After the Fix

**Before:** An employee could save “Bottle, 5 grams inside, PHP 50 per gram” even when the contents were liquid.

**After:** Choosing **Liquid** leaves only liters and milliliters. A valid example is “1 bottle contains 500 ml.” The next fix separates whether the supplier charges per ml, bottle, or outer box.

For a solid powder sold in a bottle, grams are still valid because the employee explicitly chooses **Solid**. The container does not override the material form.

The **Release only whole packages** option now has one job: it decides whether Warehouse Raw may open the registered supplier package. Container and outer-package levels are separated in Fix 07 below.

### Manual Test

1. Log in as **General Manager**.
2. Open **Ingredients** and click **Add**.
3. Choose a process category such as **Flavorings**.
4. Choose **Liquid - measured by volume**.
5. Open **Stock Unit**. Confirm that only liters and milliliters are offered.
6. Choose milliliters, set **Quantity Inside One Package** to `500`, and choose **Bottle**.
7. Confirm the preview says **1 bottle contains 500 ml**.
8. Enter the cost for one ml. Confirm the preview calculates the bottle cost.
9. Turn on **Release only whole packages**. Confirm the message says Warehouse Raw will issue complete bottles; the measurement remains ml.
10. Change the material form to **Solid**. Confirm only kilograms and grams are offered.
11. Change it to **Counted Item**. Confirm only count units are offered.
12. Save a valid liquid ingredient, edit it, and confirm the same material form, stock unit, package, and cost return correctly.

### Automatic Check

Run:

```text
php tests/ingredient_measurement_rules_test.php
```

Expected result: `Ingredient measurement rule tests passed.`

### Files Changed

- `html/admin/ingredients.html`
- `api/admin/ingredients.php`
- `sql/normalize_ingredient_uom_rules.sql`
- `tests/ingredient_measurement_rules_test.php`

## Fix 07 - Box, Bottle, and Material Units Are Separate

### What Was Wrong Before

The ingredient form treated a stock measurement, an inner container, and an outer supplier package as one idea. That allowed confusing records such as a liquid bottle priced per gram, or a five-bottle box described as though the box itself were one bottle.

The reviewer was correct: **gram, bottle, and box answer three different questions**.

- Gram, kilogram, milliliter, or liter says how much material inventory holds.
- Bottle, sachet, bag, or drum says what one inner container is.
- Box, case, or crate says how several containers arrive from the supplier.

### How It Was Fixed

- The form now has a separate **Inner Container** and **Amount in One Container**.
- It has a separate optional **Outer Purchase Package** and **Containers in One Package**.
- **Supplier Price Is For** changes between the stock unit, one inner container, or one outer package.
- The price label changes immediately. For example, it becomes **Supplier Price per bottle** or **Supplier Price per box**.
- A live summary shows the full conversion and comparable prices.
- The server recalculates the internal cost per stock unit instead of trusting a manually typed cost per gram.
- Solid mass can convert only between kilograms and grams. Liquid volume can convert only between liters and milliliters. The system does not guess a mass-to-volume conversion.
- Existing package fields remain available behind the screen so production requests and Warehouse Raw package rounding continue to work.

### Difference After the Fix

**Before:** An employee could mix together “Box,” “Bottle,” “5,” and “Cost per Gram” without the system showing what the five meant.

**After:** The same real purchase is recorded clearly:

- Stock measurement: milliliters
- Inner container: bottle
- Amount in one bottle: 500 ml
- Outer purchase package: box
- Bottles in one box: 5
- Supplier price: PHP 500 per box

The system displays:

```text
1 box = 5 bottles = 2,500 ml
PHP 0.20 per ml | PHP 100.00 per bottle | PHP 500.00 per box
```

### Manual Test

1. Log in as **General Manager**.
2. Open **Ingredients**, then add a new ingredient or edit a safe test ingredient.
3. Choose **Liquid** as its material form and **Milliliters (ml)** as its stock unit.
4. Under **Container & Purchase Package**, choose **Bottle**.
5. Enter `500` for **Amount in One Container** and choose **Milliliters (ml)**.
6. Choose **Box** as the outer purchase package.
7. Enter `5` for **Bottles in One Package**.
8. Choose **Outer Purchase Package** under **Supplier Price Is For**.
9. Enter `500` as the supplier price.
10. Confirm the summary says `1 box = 5 bottles = 2,500 ml` and shows PHP 0.20 per ml, PHP 100.00 per bottle, and PHP 500.00 per box.
11. Save the ingredient, reopen it, and confirm all three levels return correctly.
12. Try choosing grams for a liquid. Confirm grams are not offered.
13. Choose an outer box but leave the bottle count empty. Confirm the form refuses to save and explains what is missing.

### Automatic Check

Run:

```text
php tests/ingredient_packaging_hierarchy_test.php
```

Expected result: `Ingredient packaging hierarchy tests passed.`

### Files Changed

- `html/admin/ingredients.html`
- `api/admin/ingredients.php`
- `sql/add_ingredient_packaging_hierarchy.sql`
- `tests/ingredient_packaging_hierarchy_test.php`

## Fix 08 - Purchasing Format Must Be Chosen

### What Was Wrong Before

The form called the whole container and purchase-package section optional. Leaving it blank therefore had two possible meanings: the material was intentionally purchased loose or in bulk, or the employee simply forgot to describe how it arrives. The system could not tell the difference.

The outer box, case, or crate can genuinely be optional. The **purchasing format itself must not be optional**.

### How It Was Fixed

- Every ingredient must now be marked as either **Direct or bulk** or **Packaged**.
- **Direct or bulk** means the supplier sells the material directly in its stock unit, such as kilograms, liters, pieces, or rolls. Container fields are disabled and the supplier price is entered per stock unit.
- **Packaged** requires an inner container, the amount inside one container, and a compatible amount unit. For example: one bottle contains 500 ml.
- The outer box, case, or crate remains optional because a supplier may sell individual bottles, bags, or drums.
- The server checks the same rules when saving. The rule cannot be bypassed by changing the page in the browser.
- Existing ingredient records are classified from their saved container information so older data remains usable.

### Difference After the Fix

**Before:** Blank packaging could mean either “bulk purchase” or “missing information.”

**After:** The employee must make the meaning explicit:

```text
Direct or bulk: Sugar is bought and priced per kilogram.
Packaged: Vanilla extract arrives in 1-liter bottles; an outer box is optional.
```

### Manual Test

1. Log in as **General Manager**.
2. Open **Ingredients**, then click **Add**.
3. Leave both purchasing-format choices empty and try to submit. Confirm the form refuses to continue.
4. Choose **Direct or bulk**. Confirm the container and outer-package controls become unavailable and the price is described per stock unit.
5. Choose **Packaged**. Leave the inner container empty and try to submit. Confirm the form explains that an inner container and amount are required.
6. Enter **Bottle**, `500`, and **Milliliters (ml)**. Leave the outer package empty and confirm saving is allowed.
7. Reopen the ingredient and confirm **Packaged** and the bottle information are still shown.

### Automatic Check

Run:

```text
php tests/ingredient_packaging_hierarchy_test.php
```

Expected result: `Ingredient packaging hierarchy tests passed.`

## Fix 09 - Container Choices Follow the Material Form

### What Was Wrong Before

The stock unit already changed for solids, liquids, and counted supplies, but the **Inner Container** list still showed every container. This meant an employee could see choices such as a bottle for a dry solid or a sack for a liquid. The server also accepted the same general container list.

### How It Was Fixed

- Choosing **Solid** now offers dry-goods containers: bag, sack, sachet, packet, dry-goods drum, or dry-goods pail.
- Choosing **Liquid** now offers bottle, jug, liquid drum, liquid pail, or tank.
- Choosing **Counted Item** now offers pack, packet, bundle, or roll.
- Choosing the **Packaging Materials** category automatically applies the counted-item choices.
- The server checks the same rule. Changing the page in the browser cannot be used to save a mismatched container.
- Older records with a mismatched container remain visible for correction, but they cannot be saved again until a valid choice is made.

The outer supplier package remains separate. A box, case, or crate describes how several inner containers arrive and does not replace the material measurement or inner container.

### Difference After the Fix

**Before:** Selecting liters correctly limited the stock unit to liters or milliliters, but the same form could still show bags and sacks as inner-container choices.

**After:** A liquid record can be entered as `500 ml per bottle`, while a solid record can be entered as `25 kg per sack`. A counted packaging supply can be entered as a pack or bundle. Invalid combinations are blocked on both the page and the server.

### Manual Test

1. Log in as **General Manager**.
2. Open **Ingredients**, then click **Add**.
3. Choose **Solid**. Confirm the stock units are kilograms and grams, and the inner containers do not include bottle, jug, or tank.
4. Choose **Liquid**. Confirm the stock units are liters and milliliters, and the inner containers do not include bag or sack.
5. Choose **Counted Item**. Confirm the inner containers are pack, packet, bundle, and roll.
6. Choose the **Packaging Materials** category. Confirm the material form changes to **Counted Item** and the same counted-item container list appears immediately.
7. Save a valid liquid example such as `500 ml per bottle`.
8. Edit an older ingredient with an invalid container. Confirm the page asks for a valid container before it can be saved.

### Automatic Check

Run:

```text
php tests/ingredient_measurement_rules_test.php
php tests/ingredient_packaging_hierarchy_test.php
```

Expected results:

```text
Ingredient measurement rule tests passed.
Ingredient packaging hierarchy tests passed.
```

## Fix 10 - Duplicate Products and BOM Units Are Handled Consistently

### What Was Wrong Before

Two separate problems could make a product and recipe demonstration misleading:

- A duplicate product or packaging variant could reach the database and expose a technical duplicate-key error instead of telling the employee what to do.
- The recipe balance added liters, kilograms, grams, bottles, and packaging pieces into one number. This could make an impossible liquid yield appear valid.

### How It Was Fixed

- Equivalent package sizes are checked before saving. For example, `500 ml` and `0.5 L` cannot be added twice to the same base product.
- A database duplicate that still occurs is returned as a friendly conflict telling the employee to open the existing product.
- Unexpected database relationship errors are no longer mislabeled as duplicate products.
- Every recipe component keeps the unit from the ingredient master record. The unit shown in the recipe row is locked.
- Liquid ingredients are converted to liters, solids are converted to kilograms, and bottles, caps, labels, and other counted supplies stay as counts.
- Only liquid input is compared with a liquid expected yield. Grams are never silently treated as milliliters without an approved density rule.

### Difference After the Fix

**Before:** Adding `10 L` milk, `85 kg` sugar, and `100` bottles could incorrectly look like enough input for a `95 L` liquid yield.

**After:** The balance shows the three groups separately. Only `10 L` contributes to the liquid total. The employee must add the actual liquid components, such as water, milk, alcohol base, or liquid flavoring, to support the expected liquid yield.

### Manual Test

1. Log in as **General Manager** and open **Products**.
2. Try to add a packaging size already used by the same base product. Also try its equivalent size, such as `500 ml` when `0.5 L` already exists.
3. Confirm the page explains that the packaging size already exists. No technical SQL message should appear and no duplicate row should be created.
4. Open **Recipes** and create or edit a `500 ml` product recipe.
5. Add a liquid ingredient, a dry ingredient, and a packaging item. Confirm their units come from the ingredient records and cannot be typed over.
6. Enter `10 L` base milk and `85 kg` sugar for an expected `95 L` yield. Confirm the liquid balance remains `10 L` and saving is blocked.
7. Replace the invalid shortcut with enough real liquid input. Confirm the liquid balance updates while sugar and packaging remain listed separately.

### Automatic Check

Run:

```text
php tests/product_duplicate_conflict_test.php
php tests/recipe_bom_uom_consistency_test.php
```

Expected results:

```text
Product duplicate conflict tests passed.
Recipe BOM unit consistency tests passed.
```

## Fix 11 - Buying Format Belongs to Each Supplier Offer

### What Was Wrong Before

The Ingredient form stored one buying format for the material. That incorrectly implied that every supplier sold Sugar in the same package even though one supplier may sell by kilogram, another by 25 kg sack, and another by 50 kg sack.

### How It Was Fixed

- The Ingredient form now defines only the material, its Warehouse stock unit, stock limits, shelf life, and storage rules.
- Supplier Accreditation now records Direct/Bulk or Package/Container separately for every linked ingredient.
- A packaged offer records the package type, amount inside it, amount unit, and quoted package price.
- The server converts the package amount to the ingredient's stock unit and calculates a comparison price. Mass cannot convert to volume without an approved rule.
- Canvassing compares the normalized stock-unit prices and also explains the original supplier offer.
- Legacy ingredient package records were copied to their existing supplier links, then removed from the ingredient-level Production rule. Production and Warehouse continue to request and count stock in kg, liters, or pieces.

### Example

For Sugar stocked in `kg`:

- Supplier A: direct at PHP 60/kg = PHP 60/kg.
- Supplier B: PHP 1,400 per 25 kg sack = PHP 56/kg.
- Supplier C: PHP 2,900 per 50 kg sack = PHP 58/kg.

Purchasing therefore compares `60`, `56`, and `58` per kg without pretending that Sugar itself always comes in one package.

### Manual Test

1. Create Sugar in **Ingredients** with stock unit `kg`. Confirm there is no supplier-package switch.
2. Open **Supplier Management**, edit Supplier A, select Sugar, choose **Per Warehouse unit**, and enter `60`.
3. Edit Supplier B, select Sugar, choose **Per whole package**, enter `sack`, `25 kg`, and `1400`.
4. Edit Supplier C and enter `sack`, `50 kg`, and `2900`.
5. Confirm the three previews show PHP 60/kg, PHP 56/kg, and PHP 58/kg.
6. Open Purchasing canvassing for Sugar and confirm Supplier B is the lowest comparable offer while every supplier's original selling format remains visible.

### Automatic Check

Run:

```text
php tests/ingredient_measurement_rules_test.php
php tests/ingredient_packaging_hierarchy_test.php
php tests/purchasing_choice_eligibility_test.php
```

Expected results include `Supplier-specific ingredient offer tests passed.`

This fix supersedes the ingredient-level purchasing-format ownership described in Fixes 06 and 07. Their measurement and unit-safety rules remain valid; only ownership of the supplier package changed.

## Work Order

The remaining confirmed concerns will be handled one at a time in this order:

1. ~~Complete partial, damaged, and backorder receiving through RR verification.~~ Completed: exact match, replacement completion, and audited short-close paths are separated.
2. Complete the controlled stock-surplus correction flow.
3. Clarify received stock versus unpaid supplier balance.
4. Verify expected loss, actual yield, and waste handling.
5. Close the PRS rejection loop.
6. Verify tank-capacity checks.
7. Prepare clean and realistic defense data.
