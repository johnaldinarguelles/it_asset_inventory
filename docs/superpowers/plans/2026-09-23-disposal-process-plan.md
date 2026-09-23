# Return and Disposal Process Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add an architecture-compatible Disposal Process, update Return fields/behavior, and expose Disposal stock movement as a dashboard trend for Admin and Staff.

**Architecture:** Extend the existing `items` and `transactions` records instead of creating a parallel disposal subsystem. Reuse the current `FOR UPDATE` item locking, prepared statements, transaction wrapper, Bootstrap table forms, user-name datalists, item lookup endpoint, reports, and Chart.js dashboard.

**Tech Stack:** PHP 8-compatible procedural PHP, MySQL/MariaDB, mysqli prepared statements, Bootstrap 5, jQuery/DataTables, Chart.js, custom CLI PHP assertions.

**Spec:** `docs/superpowers/specs/2026-09-23-disposal-process-design.md`

## Global Constraints

- Disposal is available to Admin and Staff through `require_transactor()`.
- Item identity remains the unique `items.serial_number` used for serial numbers, barcodes, and general item codes.
- Usable stock is `boh + total_received + total_returned - total_issued - total_disposed`.
- Return variance remains `total_issued - total_returned` and is only relabeled from Issued Balance.
- Fixed Asset disposal requires quantity `1`; Non Fixed-Asset disposal allows a positive quantity up to usable stock.
- Disposal is rejected while any outstanding issued balance remains.
- Existing `transactions.remarks`, `pic`, `location`, `created_by`, and `created_at` are reused; no disposal history table is created.
- The selected disposal date is stored in the Disposal transaction's existing `created_at` value after strict server-side validation.
- Existing authentication, receiving, import, user management, and the legacy unused `export.php` remain unchanged.
- `.git` is read-only in this workspace; verify with `git status --short`, targeted diffs, PHP syntax checks, and tests instead of commit steps.

## Review Focus

- An unknown serial/barcode/item code must never create a Return or Disposal transaction; Task 2 and Task 3 test the lookup/validation path.
- A serialized Fixed Asset must never dispose quantity greater than one; Task 3 tests the quantity rule.
- Disposal must not proceed while issued stock is outstanding, and an entirely disposed item must not be issuable; Task 3 tests both conditions.
- A selected past/future calendar date must be valid and editable while malformed dates are rejected server-side; Task 3 tests date validation.
- Disposal must subtract from every stock/report/dashboard calculation, not only the Disposal page; Task 4 tests the pure aggregation helpers and runs syntax checks on all consumers.

---

### Task 1: Shared inventory logic and database migration

**Files:**
- Create: `tests/inventory_helpers_test.php`
- Create: `includes/inventory_helpers.php`
- Create: `migration_v8_disposal_process.sql`
- Modify: `config/db.php`
- Modify: `database.sql`

**Interfaces:**
- Produces `inventory_locations(): array`, `inventory_asset_classifications(): array`, `inventory_stock(array $row): int`, `inventory_outstanding_issued(array $row): int`, `inventory_status(array $row): string`, `inventory_valid_date(string $date): bool`, `inventory_disposal_error(array $item, int $quantity, string $classification, string $date): ?string`, and `inventory_monthly_quantities(array $rows, string $action): array`.
- `config/db.php` requires the helper file and uses `inventory_stock()`/`inventory_status()` in `ending_stock()` and `update_item_status()`.
- `database.sql` contains the final schema; the migration upgrades the current schema without creating a second transaction table.

- [ ] **Step 1: Write failing pure-logic tests**

Add a CLI test file with a small assertion helper and real calls into `includes/inventory_helpers.php`:

```php
<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . "; expected " . var_export($expected, true) . " got " . var_export($actual, true));
    }
}

$row = [
    'boh' => 2,
    'total_received' => 10,
    'total_returned' => 3,
    'total_issued' => 4,
    'total_disposed' => 5,
    'reorder_level' => 2,
    'current_co' => '',
];
assert_same(6, inventory_stock($row), 'disposal must reduce usable stock');
assert_same(4, inventory_outstanding_issued(['total_issued' => 7, 'total_returned' => 3]), 'return variance must remain issued minus returned');
assert_same('Disposed', inventory_status(array_merge($row, ['total_disposed' => 11])), 'fully disposed stock must have Disposed status');
assert_same('Issued', inventory_status(array_merge($row, ['total_disposed' => 0, 'current_co' => 'Ms. User'])), 'current responsible person must preserve Issued status');
assert_same(true, inventory_valid_date('2026-02-28'), 'valid calendar date accepted');
assert_same(false, inventory_valid_date('2026-02-30'), 'invalid calendar date rejected');
assert_same(false, inventory_valid_date('2026/02/28'), 'wrong date format rejected');
assert_same(null, inventory_disposal_error(array_merge($row, ['total_disposed' => 0, 'current_co' => '', 'total_issued' => 0, 'total_returned' => 0]), 1, 'Fixed Asset', '2026-09-23'), 'valid fixed-asset disposal accepted');
assert_same('Fixed Assets must be disposed with quantity 1.', inventory_disposal_error(array_merge($row, ['total_disposed' => 0, 'current_co' => '', 'total_issued' => 0, 'total_returned' => 0]), 2, 'Fixed Asset', '2026-09-23'), 'fixed asset quantity greater than one rejected');
assert_same(['2026-08' => 2, '2026-09' => 1], inventory_monthly_quantities([
    ['m' => '2026-08', 'action_type' => 'Disposed', 'q' => 2],
    ['m' => '2026-09', 'action_type' => 'Disposed', 'q' => 1],
], 'Disposed'), 'disposed dashboard quantities grouped by month');
echo "inventory_helpers_test: PASS\n";
```

- [ ] **Step 2: Run the test and verify the expected failure**

Run: `php tests/inventory_helpers_test.php`

Expected: FAIL because `includes/inventory_helpers.php` and its functions do not exist yet. Fix only test typos if the failure is a parse/error unrelated to the missing implementation.

- [ ] **Step 3: Implement the shared helpers**

Create `includes/inventory_helpers.php` with the exact constants and behavior used by all flows:

```php
<?php
function inventory_locations(): array {
    return ['Rack 1','Rack 2','Rack 3','Rack 4','Rack 5','Rack 6','Rack 7','Rack 8','Cabinet 1','Cabinet 2','Storage Room'];
}

function inventory_asset_classifications(): array {
    return ['Fixed Asset', 'Non Fixed-Asset'];
}

function inventory_stock(array $row): int {
    return (int)$row['boh'] + (int)$row['total_received'] + (int)$row['total_returned'] - (int)$row['total_issued'] - (int)($row['total_disposed'] ?? 0);
}

function inventory_outstanding_issued(array $row): int {
    return (int)$row['total_issued'] - (int)$row['total_returned'];
}

function inventory_status(array $row): string {
    $stock = inventory_stock($row);
    if ($stock <= 0 && (int)($row['total_disposed'] ?? 0) > 0) return 'Disposed';
    if (!empty($row['current_co'])) return 'Issued';
    if ($stock <= 0) return 'Out of Stock';
    if ($stock <= (int)$row['reorder_level']) return 'Low Stock';
    return 'Available';
}

function inventory_valid_date(string $date): bool {
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
}

function inventory_disposal_error(array $item, int $quantity, string $classification, string $date): ?string {
    if (!in_array($classification, inventory_asset_classifications(), true)) return 'Invalid asset classification.';
    if (!inventory_valid_date($date)) return 'Date of Disposal must be a valid date in YYYY-MM-DD format.';
    if ($quantity < 1) return 'Quantity must be at least 1.';
    if (inventory_outstanding_issued($item) > 0) return 'Return all outstanding issued quantity before disposal.';
    if ($quantity > inventory_stock($item)) return 'Disposal quantity is greater than available stock.';
    if ($classification === 'Fixed Asset' && $quantity !== 1) return 'Fixed Assets must be disposed with quantity 1.';
    return null;
}

function inventory_monthly_quantities(array $rows, string $action): array {
    $totals = [];
    foreach ($rows as $row) {
        if (($row['action_type'] ?? '') !== $action) continue;
        $month = (string)($row['m'] ?? '');
        if ($month === '') continue;
        $totals[$month] = ($totals[$month] ?? 0) + (int)($row['q'] ?? 0);
    }
    return $totals;
}
```

- [ ] **Step 4: Wire the helpers into the existing DB utility**

At the top of `config/db.php`, require `includes/inventory_helpers.php`. Change `ending_stock($row)` to return `inventory_stock($row)`, and change `update_item_status()` to use the locked row’s `inventory_status($r)` result instead of duplicating the old status calculation. Keep the existing prepared `UPDATE items SET status=?` statement.

- [ ] **Step 5: Add the exact schema migration and fresh-install schema**

Create `migration_v8_disposal_process.sql`:

```sql
USE it_asset_db;

ALTER TABLE items
  ADD COLUMN asset_classification ENUM('Fixed Asset','Non Fixed-Asset') NOT NULL DEFAULT 'Non Fixed-Asset' AFTER uom,
  ADD COLUMN total_disposed INT NOT NULL DEFAULT 0 AFTER total_returned;

ALTER TABLE items
  MODIFY status ENUM('Available','Issued','Low Stock','Out of Stock','Disposed') NOT NULL DEFAULT 'Available';

ALTER TABLE transactions
  MODIFY action_type ENUM('Received','Issued','Returned','Disposed','Adjusted') NOT NULL;
```

Update `database.sql` with the same columns/enums, classify the seed Dell laptop as `Fixed Asset`, classify the USB Mouse as `Non Fixed-Asset`, and include `total_disposed=0` in seed inserts. Do not modify existing transaction rows in the migration.

- [ ] **Step 6: Run the focused test and syntax checks**

Run: `php tests/inventory_helpers_test.php`

Expected: `inventory_helpers_test: PASS`.

Run: `php -l config/db.php` and `php -l includes/inventory_helpers.php`.

Expected: `No syntax errors detected` for both files.

---

### Task 2: Return fields, lookup, and issuance protection

**Files:**
- Create: `tests/return_logic_test.php`
- Modify: `return.php`
- Modify: `issue.php`
- Modify: `ajax/get_item.php`

**Interfaces:**
- `return.php` calls `inventory_locations()` and `inventory_outstanding_issued()` and passes `$location` and `$remarks` into `process_return_row()`.
- `ajax/get_item.php` returns `asset_classification`, `total_disposed`, disposal-aware `stock`, and `outstanding_issued` for the existing exact `serial_number` lookup.
- `issue.php` uses disposal-aware stock and rejects `status='Disposed'` before writing.

- [ ] **Step 1: Add the failing Return calculation test**

Create `tests/return_logic_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';

if (inventory_outstanding_issued(['total_issued' => 9, 'total_returned' => 4]) !== 5) {
    throw new RuntimeException('Return Variance must equal issued minus returned.');
}
if (inventory_stock(['boh'=>0,'total_received'=>6,'total_returned'=>1,'total_issued'=>2,'total_disposed'=>3]) !== 2) {
    throw new RuntimeException('Return stock calculation must include disposal deduction.');
}
echo "return_logic_test: PASS\n";
```

- [ ] **Step 2: Run the test and verify it fails for the missing disposal-aware helper behavior**

Run: `php tests/return_logic_test.php`

Expected: FAIL before Task 1’s helper implementation and PASS after Task 1. If it already passes after Task 1, retain it as the regression test and proceed to the page integration.

- [ ] **Step 3: Update Return server processing**

Change `process_return_row($conn, $s, $q, $pic)` to `process_return_row($conn, $s, $q, $pic, $location, $remarks)`. Keep exact-code lookup and `FOR UPDATE`. Validate the requested location against `inventory_locations()`; if blank, use the item’s existing location. Keep the existing outstanding-quantity rule using `total_issued - total_returned`.

Update the item with `total_returned=total_returned+?`, `current_co=?`, and `location=?`. Insert the transaction with the selected location and remarks:

```sql
INSERT INTO transactions
  (item_id,serial_number,item_description,action_type,quantity,pic,location,remarks,created_by)
VALUES (?,?,?,?,?,?,?,?,?)
```

Pass `$_POST['location']` and `$_POST['remarks']` arrays through the batch loop. Keep rollback behavior when any row fails.

- [ ] **Step 4: Update the Return UI without rewriting its table flow**

Rename the table heading to `Variance`; keep the displayed value as the existing outstanding issued quantity. Add a Location `<select>` using `inventory_locations()` and a Remarks `<textarea>`. Rename the PIC heading/input to `Return To / PIC` and populate one page-level `<datalist>` from `SELECT name FROM users WHERE name<>'' ORDER BY name`; do not hardcode employee names. When lookup succeeds, select the item’s existing location in the new dropdown. Keep Add Entry, row removal, scanner behavior, alerts, and table styling unchanged.

- [ ] **Step 5: Make Issue disposal-aware**

Change the Issue stock query to subtract `total_disposed`. If the row’s `status` is `Disposed`, return an error before the stock check. This protects against accidental re-issuance even if a future data inconsistency makes the calculated stock positive.

- [ ] **Step 6: Extend the authenticated item lookup**

Keep `require_login()` and the prepared exact-code lookup. Return these additional fields:

```sql
asset_classification,
total_disposed,
(boh+total_received+total_returned-total_issued-total_disposed) AS stock,
(total_issued-total_returned) AS outstanding_issued
```

- [ ] **Step 7: Run focused checks**

Run: `php tests/return_logic_test.php`, `php -l return.php`, `php -l issue.php`, and `php -l ajax/get_item.php`.

Expected: the test passes and all syntax checks report no errors.

---

### Task 3: Disposal transaction page

**Files:**
- Create: `tests/disposal_validation_test.php`
- Create: `disposal.php`
- Modify: `includes/header.php`

**Interfaces:**
- `disposal.php` calls `require_transactor()`, `inventory_asset_classifications()`, `inventory_locations()`, `inventory_disposal_error()`, and the existing authenticated item lookup endpoint.
- The page writes only existing `items` and `transactions` records; no new runtime table is introduced.

- [ ] **Step 1: Add failing Disposal validation tests**

Create `tests/disposal_validation_test.php` with these cases against `inventory_disposal_error()`:

```php
<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';
$item = ['boh'=>0,'total_received'=>5,'total_returned'=>0,'total_issued'=>0,'total_disposed'=>0,'reorder_level'=>1,'current_co'=>''];

function expect_error(?string $expected, ?string $actual, string $name): void {
    if ($expected !== $actual) throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
}
expect_error(null, inventory_disposal_error($item, 2, 'Non Fixed-Asset', '2026-09-23'), 'valid consumable disposal');
expect_error('Disposal quantity is greater than available stock.', inventory_disposal_error($item, 6, 'Non Fixed-Asset', '2026-09-23'), 'over-stock disposal');
expect_error('Return all outstanding issued quantity before disposal.', inventory_disposal_error(array_merge($item, ['total_issued'=>1]), 1, 'Non Fixed-Asset', '2026-09-23'), 'outstanding issue');
expect_error('Date of Disposal must be a valid date in YYYY-MM-DD format.', inventory_disposal_error($item, 1, 'Non Fixed-Asset', '2026-02-30'), 'invalid date');
expect_error('Invalid asset classification.', inventory_disposal_error($item, 1, 'Unknown', '2026-09-23'), 'invalid classification');
echo "disposal_validation_test: PASS\n";
```

- [ ] **Step 2: Run the tests and verify the expected red state**

Run: `php tests/disposal_validation_test.php`

Expected: FAIL before the helper validation exists; after Task 1 it should pass. If it passes before any page code, retain it as the validation contract.

- [ ] **Step 3: Implement server-side `process_disposal_row()`**

Add a page-local function with this exact signature:

```php
function process_disposal_row($conn, $serial, $quantity, $disposedBy, $classification, $date, $location, $remarks): array
```

Implementation requirements:

1. Trim text and reject blank serial/code and blank Disposed By.
2. Parse quantity with `FILTER_VALIDATE_INT`; reject non-integers and values below one.
3. Validate location against `inventory_locations()`.
4. Lock the item with:

```sql
SELECT *,
  (boh+total_received+total_returned-total_issued-total_disposed) AS stock,
  (total_issued-total_returned) AS outstanding_issued
FROM items
WHERE serial_number=?
LIMIT 1 FOR UPDATE
```

5. Return a not-found error without creating anything if there is no item.
6. Call `inventory_disposal_error()` using the locked row and reject any returned message.
7. Update `total_disposed`, `asset_classification`, `location`, and let `update_item_status()` derive the final status.
8. Insert a `Disposed` transaction with the selected date as `$date . ' 00:00:00'`, the selected Disposed By name in `pic`, the location, remarks, and `$_SESSION['user_id']` in `created_by`.
9. Return `['ok'=>true]`; let the outer batch transaction roll back all rows when any row fails.

- [ ] **Step 4: Build the Disposal UI from the Issue/Return table pattern**

Use `include 'includes/header.php'; require_transactor();`, the existing page head/card/alert classes, a responsive table, Add Entry, Remove, and Save All buttons. Each row contains:

- scanner input named `serial_number[]`;
- found item text and available stock text;
- quantity input named `quantity[]` with `min='1'`;
- `disposed_by[]` input using one page-level datalist populated from `users.name`;
- `asset_classification[]` select with exactly `Fixed Asset` and `Non Fixed-Asset`;
- `date_of_disposal[]` input type `date` defaulting to `date('Y-m-d')`;
- `location[]` select using `inventory_locations()`;
- `remarks[]` textarea;
- row removal button.

Lookup by `ajax/get_item.php?serial=...`. On success, show description, location, stock, status, set the classification/location fields from the response, and set quantity to `1` when the item is Fixed Asset. On lookup failure, show the same danger state used by Issue/Return. Client-side form validation requires serial, Disposed By, classification, date, and quantity, but server validation remains authoritative.

- [ ] **Step 5: Add the navigation link**

In `includes/header.php`, add a `can_transact()`-guarded Disposal link next to Issue and Return, with the current Bootstrap icon/navlink pattern and `active` state when `$page === 'disposal.php'`.

- [ ] **Step 6: Run syntax and focused validation checks**

Run: `php tests/disposal_validation_test.php`, `php -l disposal.php`, and `php -l includes/header.php`.

Expected: the validation test passes and all syntax checks report no errors.

---

### Task 4: Stock views, transaction history, reports, and dashboard trend

**Files:**
- Modify: `items.php`
- Modify: `ajax/item_activity.php`
- Modify: `transactions.php`
- Modify: `reports.php`
- Modify: `export_excel.php`
- Modify: `pdf_report.php`
- Modify: `index.php`
- Modify: `assets/js/dashboard.js`

**Interfaces:**
- Every SQL stock expression in these files uses `-total_disposed`.
- Every action filter that currently lists `Received`, `Issued`, `Returned`, `Adjusted` adds `Disposed`.
- `index.php` passes `disposed` arrays and `disposedTotal` data to `assets/js/dashboard.js`.

- [ ] **Step 1: Add the dashboard regression test against the shared production helper**

Create `tests/dashboard_disposal_test.php`:

```php
<?php
require_once __DIR__ . '/../includes/inventory_helpers.php';
$disposed = inventory_monthly_quantities([
    ['m'=>'2026-08','action_type'=>'Disposed','q'=>'2'],
    ['m'=>'2026-09','action_type'=>'Received','q'=>'3'],
    ['m'=>'2026-09','action_type'=>'Disposed','q'=>'1'],
], 'Disposed');
if (($disposed['2026-08'] ?? 0) !== 2 || ($disposed['2026-09'] ?? 0) !== 1) {
    throw new RuntimeException('Dashboard must aggregate Disposed quantities by month.');
}
echo "dashboard_disposal_test: PASS\n";
```

Run: `php tests/dashboard_disposal_test.php`

Expected: PASS after Task 1. `index.php` must use this same helper in Step 6 so the regression test exercises the production aggregation logic rather than a duplicate test implementation.

- [ ] **Step 2: Update Items and activity views**

In `items.php`, subtract `total_disposed` from stock and variance expressions, expose `total_disposed` and `asset_classification` in the table, and retain existing status badges/actions. In `ajax/item_activity.php`, subtract disposal from stock, add `Disposed` to the activity filter, and include the existing `remarks` column so disposal details are visible.

- [ ] **Step 3: Update transaction log and report filters**

Add `Disposed` to the action options in `transactions.php`, `ajax/item_activity.php`, and `reports.php`. In `reports.php`, initialize `$data['Disposed']`, subtract Disposed in `net_movement`, show a Disposal summary card, include disposal in the Chart.js report data/labels, and include it in the no-data check. Keep all existing prepared filter parameters.

- [ ] **Step 4: Update Excel and PDF output**

In `export_excel.php`, add a Disposed column to movement summary calculations, subtract it from net movement, subtract `total_disposed` from item stock/variance, and let the existing action filter accept `Disposed`. In `pdf_report.php`, include `remarks` in the transaction output and keep the existing prepared date/action/item/PIC/serial filters; `Disposed` will then render as a normal transaction action.

- [ ] **Step 5: Wire the dashboard KPI and monthly Disposal dataset**

In `index.php`:

- subtract `total_disposed` in stock/low/out queries;
- aggregate `SUM(total_disposed)` as `disposed`;
- preserve the existing Received/Issued/Returned month arrays and add `$disp` for `Disposed` rows;
- add a Disposal KPI card using the existing purple/stat styling;
- include `disposed` in `window.chartData`.

In `assets/js/dashboard.js`:

- include `data.disposed` in `monthlyHasData`;
- add a purple Disposal dataset to the existing monthly bar chart;
- keep the tooltip/legend/theme/empty-state patterns unchanged;
- include `Disposed` in the status color map so the status doughnut remains legible.

- [ ] **Step 6: Run focused checks**

Run: `php tests/dashboard_disposal_test.php`, `node --check assets/js/dashboard.js`, and PHP syntax checks for `items.php`, `ajax/item_activity.php`, `transactions.php`, `reports.php`, `export_excel.php`, `pdf_report.php`, and `index.php`.

Expected: all tests pass and every syntax check reports no errors.

---

### Task 5: Full verification and manual workflow audit

**Files:**
- Test: `tests/inventory_helpers_test.php`
- Test: `tests/return_logic_test.php`
- Test: `tests/disposal_validation_test.php`
- Test: `tests/dashboard_disposal_test.php`
- Verify: all modified PHP/JS files from Tasks 1–4

- [ ] **Step 1: Run all custom tests**

Run:

```powershell
php tests/inventory_helpers_test.php
php tests/return_logic_test.php
php tests/disposal_validation_test.php
php tests/dashboard_disposal_test.php
```

Expected: four `PASS` lines and exit code 0.

- [ ] **Step 2: Run all syntax checks**

Run:

```powershell
php -l config/db.php
php -l includes/inventory_helpers.php
php -l return.php
php -l issue.php
php -l disposal.php
php -l includes/header.php
php -l ajax/get_item.php
php -l items.php
php -l ajax/item_activity.php
php -l transactions.php
php -l reports.php
php -l export_excel.php
php -l pdf_report.php
php -l index.php
node --check assets/js/dashboard.js
```

Expected: no syntax errors and exit code 0 for every command.

- [ ] **Step 3: Apply the SQL migration in a disposable/test database**

Run `migration_v8_disposal_process.sql` against a copy of the existing `it_asset_db` schema. Verify:

```sql
SHOW COLUMNS FROM items;
SHOW COLUMNS FROM transactions;
SELECT DISTINCT status FROM items;
```

Expected: `asset_classification`, `total_disposed`, `Disposed` status, and `Disposed` action are present; existing item/transaction rows remain intact.

- [ ] **Step 4: Manual Return checklist**

As Staff, verify an existing item can be returned; selected Location, Return To / PIC, and Remarks persist in the transaction; `Variance` equals issued minus returned; an unknown code is rejected; multiple rows remain atomic when one row fails.

- [ ] **Step 5: Manual Disposal checklist**

As Staff, verify lookup by serial/barcode/general item code, item details, quantity validation, Disposed By user list, classification choices, editable valid date, location, remarks, success alert, transaction history, and item stock/status update. Verify fixed assets only accept quantity 1, over-stock and outstanding-issued items are rejected, and a fully disposed item cannot be issued. As Viewer, verify the Disposal navigation/page is inaccessible.

- [ ] **Step 6: Manual dashboard/report checklist**

After one or more disposals, verify the dashboard Disposal KPI and monthly Disposal series, low/out stock calculations, Items / Stock totals, Item Activity, Transaction Log, Dynamic Reports, Excel export, and PDF report all show consistent disposal-aware values.

- [ ] **Step 7: Verify change isolation**

Run `git status --short` and inspect the targeted diff. Confirm only the spec, plan, migration, helper/tests, Return/Issue/Disposal/lookup/navigation files, and direct stock/report/dashboard consumers changed. Do not modify or reset unrelated user changes.
