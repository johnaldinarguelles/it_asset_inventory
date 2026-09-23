<?php
require_once 'config/auth.php';
require_admin();

// Must run before any HTML output (includes/header.php prints the page shell),
// otherwise the CSV headers below are sent too late and the download is corrupted.
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="items_import_template.csv"');

    $out = fopen('php://output', 'w');

    fputcsv($out, [
        'item_description',
        'serial_number',
        'location',
        'uom',
        'boh',
        'total_received',
        'total_issued',
        'total_returned',
        'actual_stock',
        'pic',
        'remarks'
    ]);

    fputcsv($out, [
        'USB Mouse',
        '5718185',
        'Cabinet 1',
        'Pc',
        '0',
        '50',
        '0',
        '0',
        '50',
        'John',
        'Initial import'
    ]);

    fclose($out);
    exit;
}

include 'includes/header.php';

function cleanText($value)
{
    $value = trim($value);

    // Convert UTF-8 non-breaking spaces
    $value = str_replace("\xC2\xA0", " ", $value);

    // Convert Windows-1252 NBSP
    $value = str_replace(chr(160), " ", $value);

    return $value;
}

$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');

    if (!$fh) {
        $errors[] = 'Unable to open uploaded CSV file.';
    } else {
        $header = fgetcsv($fh);

        if (!$header) {
            // Empty file: nothing to import, not an error.
            $msg = 'Imported 0 rows.';
        } else {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $header = array_map('trim', $header);
            $header = array_map('strtolower', $header);

            $count = 0;
            $rowNo = 1;

            // Physical count sheets often list the same serial/item code more than
            // once (e.g. one line per storage location). Merging duplicates here
            // (summing quantities) instead of processing rows one-by-one against
            // the DB is required so the last duplicate row doesn't silently
            // overwrite and discard the actual_stock/received counted on earlier
            // rows for the same serial_number - that was making imported totals
            // undercount the sheet's true grand total.
            $parsed = [];

            while (($row = fgetcsv($fh)) !== false) {
                $rowNo++;

                // Skip fully blank lines (common trailing newline from Excel/empty templates).
                if ($row === [null] || $row === ['']) {
                    continue;
                }

                if (count($row) !== count($header)) {
                    $errors[] = "Row $rowNo skipped: column count mismatch.";
                    continue;
                }

                $data = array_combine($header, $row);
                $description = cleanText($data['item_description'] ?? '');
                $serial      = cleanText($data['serial_number'] ?? '');
                $location    = cleanText($data['location'] ?? '');
                $uom         = cleanText($data['uom'] ?? 'Pc');

                $boh         = (int)($data['boh'] ?? 0);
                $received    = (int)($data['total_received'] ?? 0);
                $actual      = (int)($data['actual_stock'] ?? $received);

                // Older templates may not have these columns; null means "keep whatever is already on the item".
                $issuedIn    = array_key_exists('total_issued', $data)   ? (int)$data['total_issued']   : null;
                $returnedIn  = array_key_exists('total_returned', $data) ? (int)$data['total_returned'] : null;

                $pic         = trim($data['pic'] ?? ($_SESSION['name'] ?? 'Import'));
                $remarks     = trim($data['remarks'] ?? 'Imported from CSV');

                if ($description === '' || $serial === '') {
                    $errors[] = "Row $rowNo skipped: item_description and serial_number are required.";
                    continue;
                }

                // if ($received <= 0) {
                //     $errors[] = "Row $rowNo skipped: total_received must be greater than 0.";
                //     continue;
                // }

                if (isset($parsed[$serial])) {
                    $p = &$parsed[$serial];
                    $p['boh']       += $boh;
                    $p['received']  += $received;
                    $p['actual']    += $actual;
                    if ($issuedIn !== null)   $p['issuedIn']   = ($p['issuedIn']   ?? 0) + $issuedIn;
                    if ($returnedIn !== null) $p['returnedIn'] = ($p['returnedIn'] ?? 0) + $returnedIn;
                    if ($location !== '' && strpos($p['location'], $location) === false) {
                        $p['location'] = $p['location'] !== '' ? $p['location'] . '; ' . $location : $location;
                    }
                    $p['rows'][] = $rowNo;
                    $errors[] = "Row $rowNo: serial/code '$serial' duplicates row {$p['rows'][0]} - quantities summed into one item.";
                    unset($p);
                    continue;
                }

                $parsed[$serial] = [
                    'description' => $description,
                    'location'    => $location,
                    'uom'         => $uom,
                    'boh'         => $boh,
                    'received'    => $received,
                    'actual'      => $actual,
                    'issuedIn'    => $issuedIn,
                    'returnedIn'  => $returnedIn,
                    'pic'         => $pic,
                    'remarks'     => $remarks,
                    'rows'        => [$rowNo],
                ];
            }

            $importedTotal = 0;

            foreach ($parsed as $serial => $d) {
                $description = $d['description'];
                $location    = $d['location'];
                $uom         = $d['uom'];
                $received    = $d['received'];
                $actual      = $d['actual'];
                $issuedIn    = $d['issuedIn'];
                $returnedIn  = $d['returnedIn'];
                $pic         = $d['pic'];
                $remarks     = $d['remarks'];

                try {

                $check = $conn->prepare("SELECT id, actual_stock, reorder_level, total_issued, total_returned FROM items WHERE serial_number = ?");
                $check->bind_param("s", $serial);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();

                if ($existing) {
                    $itemId = (int)$existing['id'];
                    $reorderLevel = (int)$existing['reorder_level'];

                    // Fall back to the item's current values when the sheet doesn't carry these columns.
                    $issued   = $issuedIn   ?? (int)$existing['total_issued'];
                    $returned = $returnedIn ?? (int)$existing['total_returned'];

                    // Reconcile boh so the computed Stock (boh + received + returned - issued)
                    // matches the actual physical count from this sheet instead of drifting from it.
                    $boh = $actual - $received - $returned + $issued;

                    if ($actual <= 0) {
                        $status = 'Out of Stock';
                    } elseif ($actual <= $reorderLevel) {
                        $status = 'Low Stock';
                    } else {
                        $status = 'Available';
                    }

                    $stmt = $conn->prepare("
                        UPDATE items
                        SET
                            item_description = ?,
                            location = ?,
                            uom = ?,
                            boh = ?,
                            total_received = ?,
                            total_issued = ?,
                            total_returned = ?,
                            actual_stock = ?,
                            pic = ?,
                            remarks = ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        "sssiiiiisssi",
                        $description,
                        $location,
                        $uom,
                        $boh,
                        $received,
                        $issued,
                        $returned,
                        $actual,
                        $pic,
                        $remarks,
                        $status,
                        $itemId
                    );
                } else {
                    $reorderLevel = 5;

                    $issued   = $issuedIn   ?? 0;
                    $returned = $returnedIn ?? 0;

                    // New item: reconcile boh the same way as an update, keeping Total Stocks
                    // equal to the sheet's actual_stock.
                    $boh = $actual - $received - $returned + $issued;

                    if ($actual <= 0) {
                        $status = 'Out of Stock';
                    } elseif ($actual <= $reorderLevel) {
                        $status = 'Low Stock';
                    } else {
                        $status = 'Available';
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO items (
                            item_description,
                            serial_number,
                            location,
                            uom,
                            boh,
                            total_received,
                            total_issued,
                            total_returned,
                            actual_stock,
                            status,
                            reorder_level,
                            created_at,
                            updated_at
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");

                    $stmt->bind_param(
                        "ssssiiiiisi",
                        $description,
                        $serial,
                        $location,
                        $uom,
                        $boh,
                        $received,
                        $issued,
                        $returned,
                        $actual,
                        $status,
                        $reorderLevel
                    );
                }

                $stmt->execute();

                if (!$existing) {
                    $itemId = $conn->insert_id;
                }

                $action = 'Received';
                $weekNo = 'Week ' . ceil(date('j') / 7);
                $createdBy = $_SESSION['user_id'] ?? null;

                $log = $conn->prepare("
INSERT INTO transactions (
    item_id,
    serial_number,
    item_description,
    action_type,
    quantity,
    pic,
    location,
    remarks,
    created_by
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

                $log->bind_param(
                    "isssisssi",
                    $itemId,
                    $serial,
                    $description,
                    $action,
                    $received,
                    $pic,
                    $location,
                    $remarks,
                    $createdBy
                );

                $log->execute();

                $count++;
                $importedTotal += $actual;

                } catch (\Throwable $e) {
                    $srcRows = implode(', ', $d['rows']);
                    $errors[] = "Row(s) $srcRows failed: " . $e->getMessage();
                    continue;
                }
            }

            $msg = "Imported $count item(s), totaling $importedTotal units (Actual Stock).";
        }

        fclose($fh);
    }
}
?>

<div class='page-head'><div><h3><i class='bi bi-file-earmark-arrow-up'></i> Excel/CSV Import</h3><p class='page-sub'>Bulk-load or update the item master from a CSV file</p></div></div>

<?php if ($msg): ?>
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill"></i><span><?= htmlspecialchars($msg) ?></span></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> Import notes:</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card cardx p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-upload"></i> Upload CSV saved from Excel</h5>
            <p class="mb-0 text-muted">
                Required columns:
                <code>item_description, serial_number, location, uom, boh, total_received, total_issued, total_returned, actual_stock, pic, remarks</code>
            </p>
        </div>

        <a href="import.php?download_template=1" class="btn btn-success">
            <i class="bi bi-download"></i> Download Template
        </a>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csv" accept=".csv" class="form-control mb-3" required>
        <button class="btn btn-primary"><i class="bi bi-file-earmark-arrow-up"></i> Import</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>