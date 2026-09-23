<?php include 'includes/header.php';
require_transactor();
$msg = '';
$err = '';
$locations = inventory_locations();
$classifications = inventory_asset_classifications();
$picNames = [];
$picResult = $conn->query("SELECT name FROM users WHERE name <> '' ORDER BY name");
while ($picResult && ($picRow = $picResult->fetch_assoc())) {
  $picNames[] = $picRow['name'];
}

function process_disposal_row($conn, $serial, $quantity, $disposedBy, $classification, $date, $location, $remarks): array
{
  $serial = trim($serial ?? '');
  $disposedBy = trim($disposedBy ?? '');
  $classification = trim($classification ?? '');
  $date = trim($date ?? '');
  $location = trim($location ?? '');
  $remarks = trim($remarks ?? '');

  if ($serial === '') return ['ok' => false, 'message' => 'Serial / Barcode / Item Code is required.'];
  if ($disposedBy === '') return ['ok' => false, 'message' => 'Disposed By is required.'];
  if (!in_array($location, inventory_locations(), true)) return ['ok' => false, 'message' => 'A valid disposal location is required.'];

  $quantity = filter_var((string)$quantity, FILTER_VALIDATE_INT);
  if ($quantity === false) return ['ok' => false, 'message' => 'Quantity must be a whole number.'];

  $stmt = $conn->prepare('SELECT *, (boh + total_received + total_returned - total_issued - total_disposed) AS stock, (total_issued - total_returned) AS outstanding_issued FROM items WHERE serial_number=? LIMIT 1 FOR UPDATE');
  $stmt->bind_param('s', $serial);
  $stmt->execute();
  $item = $stmt->get_result()->fetch_assoc();

  if (!$item) return ['ok' => false, 'message' => 'Code ' . $serial . ' not found in inventory. Add the item through Items / Stock or Receiving first.'];

  $validationError = inventory_disposal_error($item, (int)$quantity, $classification, $date);
  if ($validationError !== null) return ['ok' => false, 'message' => $validationError];

  $id = (int)$item['id'];
  $stmt = $conn->prepare('UPDATE items SET total_disposed=total_disposed+?, asset_classification=?, location=? WHERE id=?');
  $stmt->bind_param('issi', $quantity, $classification, $location, $id);
  if (!$stmt->execute()) return ['ok' => false, 'message' => 'Could not update the inventory item.'];

  update_item_status($conn, $id);

  $action = 'Disposed';
  $createdAt = $date . ' 00:00:00';
  $stmt = $conn->prepare('INSERT INTO transactions(item_id,serial_number,item_description,action_type,quantity,pic,location,remarks,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
  $stmt->bind_param('isssisssis', $id, $serial, $item['item_description'], $action, $quantity, $disposedBy, $location, $remarks, $_SESSION['user_id'], $createdAt);
  if (!$stmt->execute()) return ['ok' => false, 'message' => 'Could not record the disposal transaction.'];

  return ['ok' => true];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $serials = $_POST['serial_number'] ?? [];
  $qtys = $_POST['quantity'] ?? [];
  $disposedByList = $_POST['disposed_by'] ?? [];
  $classificationList = $_POST['asset_classification'] ?? [];
  $dateList = $_POST['date_of_disposal'] ?? [];
  $locationList = $_POST['location'] ?? [];
  $remarksList = $_POST['remarks'] ?? [];
  $saved = 0;
  $errors = [];

  $conn->begin_transaction();
  try {
    foreach ($serials as $i => $serial) {
      if (trim($serial) === '') continue;
      $res = process_disposal_row(
        $conn,
        $serial,
        $qtys[$i] ?? 1,
        $disposedByList[$i] ?? '',
        $classificationList[$i] ?? '',
        $dateList[$i] ?? '',
        $locationList[$i] ?? '',
        $remarksList[$i] ?? ''
      );
      if ($res['ok']) $saved++;
      else $errors[] = 'Row ' . ($i + 1) . ': ' . $res['message'];
    }
    if ($errors) throw new Exception(implode('<br>', array_map('e', $errors)));
    if ($saved === 0) throw new Exception('No valid disposal entries found.');
    $conn->commit();
    $msg = $saved . ' disposal entr' . ($saved > 1 ? 'ies' : 'y') . ' saved successfully. Stock was removed from active inventory.';
  } catch (Throwable $ex) {
    $conn->rollback();
    $err = $ex->getMessage();
  }
}
?>
<div class='page-head'><div><h3><i class='bi bi-trash3'></i> Disposal Process</h3><p class='page-sub'>Remove returned or available items from active inventory with a complete audit trail</p></div></div>
<?php if ($msg): ?><div class='alert alert-success d-flex align-items-center gap-2'><i class='bi bi-check-circle-fill'></i><span><?= $msg ?></span></div><?php endif; ?>
<?php if ($err): ?><div class='alert alert-danger d-flex align-items-center gap-2'><i class='bi bi-exclamation-triangle-fill'></i><span><?= $err ?></span></div><?php endif; ?>

<div class='card cardx p-4'>
  <div class='alert alert-info mb-3'>Search an existing Serial / Barcode / Item Code. Disposal does not create inventory records. Items with an outstanding issued balance must be returned first, and Fixed Assets are disposed one unit at a time.</div>
  <form method='post' id='disposalForm'>
    <div class='table-responsive'>
      <table class='table table-bordered align-middle' id='disposalTable'>
        <thead class='table-light'>
          <tr>
            <th style='min-width:220px'>Serial / Barcode / Item Code</th>
            <th style='min-width:220px'>Item Found</th>
            <th style='min-width:110px'>Available Stock</th>
            <th style='min-width:110px'>Quantity</th>
            <th style='min-width:170px'>Disposed By</th>
            <th style='min-width:160px'>Asset Classification</th>
            <th style='min-width:145px'>Date of Disposal</th>
            <th style='min-width:150px'>Location</th>
            <th style='min-width:220px'>Remarks</th>
            <th style='width:80px'>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr class='disposal-row'>
            <td><input class='form-control scanner serial-input' name='serial_number[]' placeholder='Scan barcode / item code' required></td>
            <td><span class='found-item text-muted'>Waiting for scan...</span></td>
            <td><span class='stock-text text-muted'>-</span></td>
            <td><input type='number' class='form-control qty-input' name='quantity[]' value='1' min='1' required></td>
            <td><input class='form-control disposed-by-input' list='disposalPicList' name='disposed_by[]' placeholder='Disposed by' required></td>
            <td>
              <select class='form-select classification-input' name='asset_classification[]' required>
                <?php foreach ($classifications as $classification): ?><option value='<?= e($classification) ?>'><?= e($classification) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td><input type='date' class='form-control date-input' name='date_of_disposal[]' value='<?= e(date('Y-m-d')) ?>' required></td>
            <td>
              <select class='form-select location-input' name='location[]' required>
                <?php foreach ($locations as $location): ?><option value='<?= e($location) ?>'><?= e($location) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td><textarea class='form-control remarks-input' name='remarks[]' rows='1' placeholder='Disposal details or reason'></textarea></td>
            <td><button type='button' class='btn btn-outline-danger btn-sm remove-row' disabled aria-label='Remove row'><i class='bi bi-trash'></i></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class='d-flex gap-2 justify-content-between flex-wrap'>
      <button type='button' class='btn btn-primary' id='addDisposalRow'><i class='bi bi-plus-lg'></i> Add Entry</button>
      <button class='btn btn-danger'><i class='bi bi-trash3'></i> Save All Disposal</button>
    </div>
  </form>
  <datalist id='disposalPicList'>
    <?php foreach ($picNames as $picName): ?><option value='<?= e($picName) ?>'><?php endforeach; ?>
  </datalist>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('#disposalTable tbody');
    const addBtn = document.getElementById('addDisposalRow');
    const defaultDate = <?= json_encode(date('Y-m-d')) ?>;
    const defaultLocation = <?= json_encode($locations[0] ?? '') ?>;
    const defaultClassification = <?= json_encode($classifications[0] ?? 'Non Fixed-Asset') ?>;

    function refreshRemoveButtons() {
      const rows = tbody.querySelectorAll('tr.disposal-row');
      rows.forEach(row => row.querySelector('.remove-row').disabled = rows.length === 1);
    }

    function clearRow(row) {
      row.querySelector('.serial-input').value = '';
      row.querySelector('.qty-input').value = 1;
      row.querySelector('.disposed-by-input').value = '';
      row.querySelector('.classification-input').value = defaultClassification;
      row.querySelector('.date-input').value = defaultDate;
      row.querySelector('.location-input').value = defaultLocation;
      row.querySelector('.remarks-input').value = '';
      row.querySelector('.found-item').textContent = 'Waiting for scan...';
      row.querySelector('.found-item').className = 'found-item text-muted';
      row.querySelector('.stock-text').textContent = '-';
    }

    function applyClassification(row) {
      const fixed = row.querySelector('.classification-input').value === 'Fixed Asset';
      const quantity = row.querySelector('.qty-input');
      if (fixed) quantity.value = 1;
      quantity.max = fixed ? 1 : '';
    }

    function lookup(row) {
      const serial = row.querySelector('.serial-input').value.trim();
      const found = row.querySelector('.found-item');
      const stock = row.querySelector('.stock-text');
      if (!serial) {
        found.textContent = 'Waiting for scan...';
        found.className = 'found-item text-muted';
        stock.textContent = '-';
        return;
      }
      fetch('ajax/get_item.php?serial=' + encodeURIComponent(serial))
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            const item = data.item;
            found.textContent = item.item_description + ' | ' + (item.location || 'No location') + ' | ' + (item.status || '');
            found.className = 'found-item text-success fw-semibold';
            stock.textContent = item.stock ?? 0;
            if (item.asset_classification) row.querySelector('.classification-input').value = item.asset_classification;
            if (item.location) row.querySelector('.location-input').value = item.location;
            applyClassification(row);
          } else {
            found.textContent = 'Item/code not found';
            found.className = 'found-item text-danger fw-semibold';
            stock.textContent = '0';
          }
        })
        .catch(() => {
          found.textContent = 'Lookup failed';
          found.className = 'found-item text-danger';
          stock.textContent = '-';
        });
    }

    addBtn.addEventListener('click', function() {
      const last = tbody.querySelector('tr.disposal-row:last-child');
      const row = last.cloneNode(true);
      clearRow(row);
      tbody.appendChild(row);
      refreshRemoveButtons();
      row.querySelector('.serial-input').focus();
    });

    tbody.addEventListener('click', function(e) {
      if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
        refreshRemoveButtons();
      }
    });

    tbody.addEventListener('change', function(e) {
      if (e.target.classList.contains('classification-input')) applyClassification(e.target.closest('tr'));
    });

    tbody.addEventListener('blur', function(e) {
      if (e.target.classList.contains('serial-input')) lookup(e.target.closest('tr'));
    }, true);

    tbody.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && e.target.classList.contains('serial-input')) {
        e.preventDefault();
        lookup(e.target.closest('tr'));
        e.target.closest('tr').querySelector('.qty-input').focus();
      }
    });
  });
</script>
<?php include 'includes/footer.php'; ?>
