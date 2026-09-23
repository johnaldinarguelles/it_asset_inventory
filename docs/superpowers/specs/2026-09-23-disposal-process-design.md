# Return and Disposal Process Design

## Goal

Extend the existing Return Process with the requested fields and add a Disposal Process that uses the current PHP/MySQL inventory, transaction log, authorization, UI, and dashboard architecture. Disposal must be available to Admin and Staff and must appear as a distinct dashboard trend.

## Existing workflow discovered

- `receive.php` is Admin-only and creates or updates the unique `items.serial_number` inventory master.
- `issue.php` and `return.php` are available to Admin and Staff through `require_transactor()`.
- Issue and Return lock the matching item row with `FOR UPDATE`, validate calculated stock or outstanding issued quantity, update item counters, insert one `transactions` row, refresh item status, and commit the batch in a database transaction.
- Item identity is stored in `items.serial_number`; the same field represents serial numbers, barcodes, and general item codes. Missing codes are rejected by Issue and Return and must first enter inventory through Receiving or Items / Stock.
- Usable stock is currently calculated as `boh + total_received + total_returned - total_issued`. Return balance is `total_issued - total_returned`.
- `transactions` already stores item reference, serial/code, action, quantity, PIC, location, remarks, creator, and transaction date. Its action enum currently lacks `Disposed`.
- `items` has no asset classification, disposal counter, or disposed status. There is no location master table; Receiving currently supplies the project Rack/Cabinet/Storage Room list. There is no employee table separate from `users`; existing PIC values are stored as names.
- The dashboard uses `index.php` to aggregate movement and `assets/js/dashboard.js` to render the monthly movement chart.

## Chosen architecture

Extend the current `items` and `transactions` tables. Do not create a parallel disposal table or model disposal as an issuance. This keeps one item reference and one audit trail, lets existing activity/history/report screens show disposal, and makes the dashboard trend derive from the same transaction records.

### Data model

Add to `items`:

- `asset_classification ENUM('Fixed Asset','Non Fixed-Asset') NOT NULL DEFAULT 'Non Fixed-Asset'`.
- `total_disposed INT NOT NULL DEFAULT 0`.
- Add `Disposed` to the existing `status` enum.

Add `Disposed` to `transactions.action_type`. Reuse the existing `remarks`, `pic`, `location`, `created_by`, and `created_at` columns. The selected disposal date is written to `created_at` for the disposal transaction, so existing date filters, reports, and activity history continue to work without a duplicate disposal-date field.

The new usable-stock expression is:

```text
boh + total_received + total_returned - total_issued - total_disposed
```

`Disposed` is used when disposal has removed all remaining stock. Partial disposal of a non-fixed stock item retains the normal Available, Low Stock, or Issued status based on its remaining stock/current responsible person.

### Return changes

- Keep exact item lookup by `serial_number`; do not create missing serials during Return.
- Rename the UI heading `Issued Balance` to `Variance` while preserving the existing `total_issued - total_returned` calculation and server-side quantity check.
- Add `Remarks` and a Location dropdown using the same Rack/Cabinet/Storage Room values already used by Receiving.
- Rename `Returned By / PIC` to `Return To / PIC` and populate its datalist from `users` names rather than a fixed employee list. Store the selected name in the existing transaction `pic` field.
- Store selected return location and remarks in the existing transaction row. Update the item location to the selected return location because the returned stock is physically placed there.

### Disposal flow

`disposal.php` follows the existing multiple-entry Issue/Return table pattern:

1. Admin or Staff enters/scans Serial / Barcode / Item Code.
2. The existing `ajax/get_item.php` lookup returns the matching item and calculated stock/classification.
3. The user sees the found item and current available quantity, chooses quantity, Disposed By, classification, date, location, and remarks.
4. The server re-looks up and locks the item. It validates the identifier, positive integer quantity, classification, valid `YYYY-MM-DD` date, no outstanding issued balance, and quantity not greater than current usable stock.
5. Fixed Asset disposal requires quantity `1`; non-fixed items may dispose any positive quantity within current stock.
6. The server updates `total_disposed`, classification, location, and status, inserts a `Disposed` transaction with the selected date and remarks, and commits atomically.
7. An item marked `Disposed` cannot be issued again. Disposal is rejected while any issued balance remains, so the normal workflow requires all Issue/Return state to be resolved before disposal and a disposed item cannot later become available through a return.

### Dashboard and reporting

- Update all direct stock expressions used by inventory, dashboard, exports, and activity views to subtract `total_disposed`.
- Include `Disposed` in transaction/activity/report action filters and movement calculations. Disposal is a negative stock movement.
- Add monthly disposal aggregation in `index.php`, a Disposal KPI, and a Disposal dataset in the existing Chart.js monthly chart. The chart empty-state logic includes disposal data.
- Add Disposed to report/export movement summaries so dashboard, reports, and transaction history agree.

## Security and integrity

- Use `require_transactor()` for Disposal, matching Issue/Return authorization: Admin and Staff only.
- Use prepared statements for all new item lookups and writes.
- Use `FOR UPDATE` and the existing transaction wrapper for multi-row disposal and Return batches.
- Recalculate item ID, available quantity, status, and authorization server-side; do not trust hidden/browser values.
- Validate classification against the two allowed values and date against a strict calendar date parser.
- Keep existing output escaping via `e()` and existing JSON lookup authentication.
- Existing code has no CSRF token framework, so no unrelated authentication rewrite is introduced; the new flows follow the current request pattern.

## UI and scope

Reuse the current Bootstrap 5 cards, responsive table, scanner inputs, buttons, datalists, alerts, badges, and navigation. Add one navigation link for Disposal next to Issue and Return. No new frontend framework or unrelated module redesign is planned.

## Expected affected files

- `config/db.php`: shared stock expression/status helper and shared location values.
- `return.php`: requested fields, exact existing balance behavior, location/remarks persistence, user names.
- `issue.php`: subtract disposal in stock lookup and reject disposed items.
- `disposal.php`: new transaction page and server-side disposal flow.
- `includes/header.php`: Admin/Staff Disposal navigation link.
- `ajax/get_item.php`: expose disposal-aware stock/classification for lookup.
- `items.php`, `ajax/item_activity.php`, `transactions.php`, `reports.php`, `export_excel.php`, `pdf_report.php`: display/filter/report disposal and disposal-aware stock.
- `index.php`, `assets/js/dashboard.js`: disposal totals and monthly trend.
- `database.sql`: fresh-install schema.
- `migration_v8_disposal_process.sql`: exact upgrade migration for existing installations.

Unrelated authentication, receiving, import, user-management, and the unused legacy `export.php` remain unchanged.

## Validation targets

- Existing Return still succeeds, stores Remarks/Location/Return To / PIC, and displays current return balance under Variance.
- Unknown serial/barcode/item code is rejected by Return and Disposal.
- Disposal search, quantity rules, fixed-asset quantity-one rule, user selection, classification, editable date, remarks, atomic transaction, status, and no-reissue behavior work.
- Dashboard monthly chart and KPI include Disposal; transaction log, item activity, reports, Excel, and PDF show Disposed entries.
- PHP syntax checks and available project tests pass; manual workflow verification covers Admin, Staff, Viewer, unknown code, insufficient stock, partial disposal, and fully disposed serialized item.
