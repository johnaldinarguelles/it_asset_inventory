<?php
include 'includes/header.php';
require_admin();

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
        '50',
        'John',
        'Initial import'
    ]);

    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');

    if (!$fh) {
        $errors[] = 'Unable to open uploaded CSV file.';
    } else {
        $header = fgetcsv($fh);

        if (!$header) {
            $errors[] = 'CSV file is empty or invalid.';
        } else {
            $header = array_map('trim', $header);
            $header = array_map('strtolower', $header);

            $count = 0;
            $rowNo = 1;

            while (($row = fgetcsv($fh)) !== false) {
                $rowNo++;

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

                $check = $conn->prepare("SELECT id, actual_stock, reorder_level FROM items WHERE serial_number = ?");
                $check->bind_param("s", $serial);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();

                if ($existing) {
                    $itemId = (int)$existing['id'];
                    $reorderLevel = (int)$existing['reorder_level'];

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
                            actual_stock = ?,
                            pic = ?,
                            remarks = ?,
                            status = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        "sssiissssi",
                        $description,
                        $location,
                        $uom,
                        $boh,
                        $received,
                        $actual,
                        $pic,
                        $remarks,
                        $status,
                        $itemId
                    );
                } else {
                    $reorderLevel = 5;

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
                        VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, NOW(), NOW())
                    ");

                    $stmt->bind_param(
                        "ssssiiisi",
                        $description,
                        $serial,
                        $location,
                        $uom,
                        $boh,
                        $received,
                        $actual,
                        $status,
                        $reorderLevel
                    );
                }

                if (!$stmt->execute()) {
                    $errors[] = "Row $rowNo failed item save: " . $stmt->error;
                    continue;
                }

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

                if (!$log->execute()) {
                    $errors[] = "Row $rowNo failed transaction log: " . $log->error;
                    continue;
                }

                $count++;
            }

            $msg = "Imported $count rows.";
        }

        fclose($fh);
    }
}
?>

<h3>Excel/CSV Import</h3>

<?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-warning">
        <strong>Import notes:</strong>
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
            <h5 class="mb-1">Upload CSV saved from Excel</h5>
            <p class="mb-0 text-muted">
                Required columns:
                <code>item_description, serial_number, location, uom, boh, total_received, actual_stock, pic, remarks</code>
            </p>
        </div>

        <a href="import.php?download_template=1" class="btn btn-success">
            Download Template
        </a>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csv" accept=".csv" class="form-control mb-3" required>
        <button class="btn btn-primary">Import</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>