<?php include 'includes/header.php';
require_transactor();
$msg = '';
$err = '';

function process_return_row($conn, $s, $q, $pic)
{
  $s = trim($s ?? '');
  $q = max(1, (int)($q ?? 1));
  $pic = trim($pic ?? '');
  if ($s === '') return ['ok' => false, 'message' => 'Serial / Barcode / Item Code is required.'];

  $stmt = $conn->prepare('SELECT *, (total_issued - total_returned) AS outstanding_issued FROM items WHERE serial_number=? LIMIT 1 FOR UPDATE');
  $stmt->bind_param('s', $s);
  $stmt->execute();
  $item = $stmt->get_result()->fetch_assoc();

  if (!$item) return ['ok' => false, 'message' => 'Code ' . $s . ' not found in inventory.'];
  if ((int)$item['outstanding_issued'] < $q) {
    return ['ok' => false, 'message' => 'Return quantity is greater than issued balance for ' . $item['item_description'] . ' (issued balance: ' . (int)$item['outstanding_issued'] . ', return qty: ' . $q . ').'];
  }

  $id = (int)$item['id'];
  $newCurrentCo = ((int)$item['outstanding_issued'] - $q) <= 0 ? null : $item['current_co'];
  $stmt = $conn->prepare('UPDATE items SET total_returned=total_returned+?, current_co=? WHERE id=?');
  $stmt->bind_param('isi', $q, $newCurrentCo, $id);
  $stmt->execute();

  $act = 'Returned';
  $stmt = $conn->prepare('INSERT INTO transactions(item_id,serial_number,item_description,action_type,quantity,pic,location,created_by) VALUES(?,?,?,?,?,?,?,?)');
  $stmt->bind_param('isssissi', $id, $s, $item['item_description'], $act, $q, $pic, $item['location'], $_SESSION['user_id']);
  $stmt->execute();
  update_item_status($conn, $id);
  return ['ok' => true];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $serials = $_POST['serial_number'] ?? [];
  $qtys = $_POST['quantity'] ?? [];
  $pics = $_POST['pic'] ?? [];
  $saved = 0;
  $errors = [];

  $conn->begin_transaction();
  try {
    foreach ($serials as $i => $s) {
      if (trim($s) === '') continue;
      $res = process_return_row($conn, $s, $qtys[$i] ?? 1, $pics[$i] ?? '');
      if ($res['ok']) $saved++;
      else $errors[] = 'Row ' . ($i + 1) . ': ' . $res['message'];
    }
    if ($errors) throw new Exception(implode('<br>', array_map('e', $errors)));
    if ($saved === 0) throw new Exception('No valid return entries found.');
    $conn->commit();
    $msg = $saved . ' return entr' . ($saved > 1 ? 'ies' : 'y') . ' saved successfully. Stock was added back automatically.';
  } catch (Throwable $ex) {
    $conn->rollback();
    $err = $ex->getMessage();
  }
}
?>
<h3>Return Process</h3>
<?php if ($msg): ?><div class='alert alert-success'><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class='alert alert-danger'><?= $err ?></div><?php endif; ?>

<div class='card cardx p-4'>
  <div class='alert alert-info mb-3'>Return supports multiple entries. Every saved return is recorded in the transaction log with date, time, item code, quantity, PIC, and action for report accuracy.</div>
  <form method='post' id='returnForm'>
    <div class='table-responsive'>
      <table class='table table-bordered align-middle' id='returnTable'>
        <thead class='table-light'>
          <tr>
            <th style='min-width:220px'>Serial / Barcode / Item Code</th>
            <th style='min-width:220px'>Item Found</th>
            <th style='min-width:130px'>Issued Balance</th>
            <th style='min-width:110px'>Quantity</th>
            <th style='min-width:180px'>Returned By / PIC</th>
            <th style='width:80px'>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr class='return-row'>
            <td><input class='form-control scanner serial-input' name='serial_number[]' placeholder='Scan barcode / item code' required></td>
            <td><span class='found-item text-muted'>Waiting for scan...</span></td>
            <td><span class='issued-text text-muted'>-</span></td>
            <td><input type='number' class='form-control qty-input' name='quantity[]' value='1' min='1'></td>
            <td><input class='form-control pic-input' list="returnLists"name='pic[]' placeholder='Returned by'>

              <datalist id="returnLists">
                <option value="Ms. Christell">
                <option value="Sir. Gian">
                <option value="Sir. Max">
              </datalist>
            </td>
            <td><button type='button' class='btn btn-outline-danger btn-sm remove-row' disabled>Remove</button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class='d-flex gap-2 justify-content-between flex-wrap'>
      <button type='button' class='btn btn-primary' id='addReturnRow'>+ Add Entry</button>
      <button class='btn btn-warning'>Save All Returns</button>
    </div>
  </form>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.querySelector('#returnTable tbody');
    const addBtn = document.getElementById('addReturnRow');

    function refreshRemoveButtons() {
      const rows = tbody.querySelectorAll('tr.return-row');
      rows.forEach(row => row.querySelector('.remove-row').disabled = rows.length === 1);
    }

    function clearRow(row) {
      row.querySelector('.serial-input').value = '';
      row.querySelector('.qty-input').value = 1;
      row.querySelector('.pic-input').value = '';
      row.querySelector('.found-item').textContent = 'Waiting for scan...';
      row.querySelector('.found-item').className = 'found-item text-muted';
      row.querySelector('.issued-text').textContent = '-';
    }

    function lookup(row) {
      const serial = row.querySelector('.serial-input').value.trim();
      const found = row.querySelector('.found-item');
      const issued = row.querySelector('.issued-text');
      if (!serial) {
        found.textContent = 'Waiting for scan...';
        issued.textContent = '-';
        return;
      }
      fetch('ajax/get_item.php?serial=' + encodeURIComponent(serial)).then(r => r.json()).then(data => {
        if (data.ok) {
          found.textContent = data.item.item_description + ' | ' + (data.item.location || 'No location');
          found.className = 'found-item text-success fw-semibold';
          issued.textContent = data.item.outstanding_issued ?? 0;
        } else {
          found.textContent = 'Item/code not found';
          found.className = 'found-item text-danger fw-semibold';
          issued.textContent = '0';
        }
      }).catch(() => {
        found.textContent = 'Lookup failed';
        found.className = 'found-item text-danger';
      });
    }
    addBtn.addEventListener('click', function() {
      const last = tbody.querySelector('tr.return-row:last-child');
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