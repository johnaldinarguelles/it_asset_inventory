<?php include 'includes/header.php'; require_transactor(); $msg=''; $err='';

function process_issue_row($conn, $s, $q, $pic) {
    $s = trim($s ?? '');
    $q = max(1, (int)($q ?? 1));
    $pic = trim($pic ?? '');
    if ($s === '') return ['ok'=>false, 'message'=>'Serial / Barcode / Item Code is required.'];
    if ($pic === '') return ['ok'=>false, 'message'=>'PIC / C/O name is required.'];

    $stmt = $conn->prepare('SELECT *, (boh + total_received + total_returned - total_issued) AS stock FROM items WHERE serial_number=? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('s', $s);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();

    if (!$item) return ['ok'=>false, 'message'=>'Code '.$s.' not found in inventory.'];
    if ((int)$item['stock'] < $q) return ['ok'=>false, 'message'=>'Not enough stock for '.$item['item_description'].' (available: '.$item['stock'].', requested: '.$q.').'];

    $id = (int)$item['id'];
    $stmt = $conn->prepare('UPDATE items SET total_issued = total_issued + ?, current_co=? WHERE id=?');
    $stmt->bind_param('isi', $q, $pic, $id);
    $stmt->execute();

    $act = 'Issued';
    $stmt = $conn->prepare('INSERT INTO transactions(item_id,serial_number,item_description,action_type,quantity,pic,location,created_by) VALUES(?,?,?,?,?,?,?,?)');
    $stmt->bind_param('isssissi', $id, $s, $item['item_description'], $act, $q, $pic, $item['location'], $_SESSION['user_id']);
    $stmt->execute();
    update_item_status($conn, $id);
    return ['ok'=>true];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $serials = $_POST['serial_number'] ?? [];
    $qtys = $_POST['quantity'] ?? [];
    $pics = $_POST['pic'] ?? [];
    $saved = 0; $errors = [];

    $conn->begin_transaction();
    try {
        foreach ($serials as $i => $s) {
            if (trim($s) === '') continue;
            $res = process_issue_row($conn, $s, $qtys[$i] ?? 1, $pics[$i] ?? '');
            if ($res['ok']) $saved++; else $errors[] = 'Row '.($i+1).': '.$res['message'];
        }
        if ($errors) throw new Exception(implode('<br>', array_map('e', $errors)));
        if ($saved === 0) throw new Exception('No valid issuance entries found.');
        $conn->commit();
        $msg = $saved . ' issuance entr' . ($saved > 1 ? 'ies' : 'y') . ' saved successfully. Stock was deducted automatically.';
    } catch (Throwable $ex) {
        $conn->rollback();
        $err = $ex->getMessage();
    }
}
?>
<h3>Issuance Process</h3>
<?php if($msg):?><div class='alert alert-success'><?=$msg?></div><?php endif;?>
<?php if($err):?><div class='alert alert-danger'><?=$err?></div><?php endif;?>

<div class='card cardx p-4'>
  <div class='alert alert-info mb-3'>Issuance supports multiple entries. Unlike Receiving, new rows do not copy previous information. Scan/type the serial or general item code, then quantity. Total stock is deducted dynamically after saving.</div>
  <form method='post' id='issueForm'>
    <div class='table-responsive'>
      <table class='table table-bordered align-middle' id='issueTable'>
        <thead class='table-light'>
          <tr>
            <th style='min-width:220px'>Serial / Barcode / Item Code</th>
            <th style='min-width:220px'>Item Found</th>
            <th style='min-width:110px'>Available Stock</th>
            <th style='min-width:110px'>Quantity</th>
            <th style='min-width:180px'>C/O Name / PIC</th>
            <th style='width:80px'>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr class='issue-row'>
            <td><input class='form-control scanner serial-input' name='serial_number[]' placeholder='Scan barcode / item code' required></td>
            <td><span class='found-item text-muted'>Waiting for scan...</span></td>
            <td><span class='stock-text text-muted'>-</span></td>
            <td><input type='number' class='form-control qty-input' name='quantity[]' value='1' min='1'></td>
            <td><input class='form-control pic-input' name='pic[]' placeholder='Issued to / C/O' required></td>
            <td><button type='button' class='btn btn-outline-danger btn-sm remove-row' disabled>Remove</button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class='d-flex gap-2 justify-content-between flex-wrap'>
      <button type='button' class='btn btn-primary' id='addIssueRow'>+ Add Entry</button>
      <button class='btn btn-success'>Save All Issuance</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const tbody = document.querySelector('#issueTable tbody');
  const addBtn = document.getElementById('addIssueRow');

  function refreshRemoveButtons(){
    const rows = tbody.querySelectorAll('tr.issue-row');
    rows.forEach(row => row.querySelector('.remove-row').disabled = rows.length === 1);
  }

  function clearRow(row){
    row.querySelector('.serial-input').value = '';
    row.querySelector('.qty-input').value = 1;
    row.querySelector('.pic-input').value = '';
    row.querySelector('.found-item').textContent = 'Waiting for scan...';
    row.querySelector('.found-item').className = 'found-item text-muted';
    row.querySelector('.stock-text').textContent = '-';
  }

  function lookup(row){
    const serial = row.querySelector('.serial-input').value.trim();
    const found = row.querySelector('.found-item');
    const stock = row.querySelector('.stock-text');
    if(!serial){ found.textContent='Waiting for scan...'; stock.textContent='-'; return; }
    fetch('ajax/get_item.php?serial=' + encodeURIComponent(serial))
      .then(r => r.json())
      .then(data => {
        if(data.ok){
          found.textContent = data.item.item_description + ' | ' + (data.item.location || 'No location');
          found.className = 'found-item text-success fw-semibold';
          stock.textContent = data.item.stock;
        } else {
          found.textContent = 'Item/code not found';
          found.className = 'found-item text-danger fw-semibold';
          stock.textContent = '0';
        }
      })
      .catch(() => { found.textContent='Lookup failed'; found.className='found-item text-danger'; });
  }

  addBtn.addEventListener('click', function(){
    const last = tbody.querySelector('tr.issue-row:last-child');
    const row = last.cloneNode(true);
    clearRow(row);
    tbody.appendChild(row);
    refreshRemoveButtons();
    row.querySelector('.serial-input').focus();
  });

  tbody.addEventListener('click', function(e){
    if(e.target.classList.contains('remove-row')){
      e.target.closest('tr').remove();
      refreshRemoveButtons();
    }
  });

  tbody.addEventListener('blur', function(e){
    if(e.target.classList.contains('serial-input')) lookup(e.target.closest('tr'));
  }, true);

  tbody.addEventListener('keydown', function(e){
    if(e.key === 'Enter' && e.target.classList.contains('serial-input')){
      e.preventDefault();
      lookup(e.target.closest('tr'));
      e.target.closest('tr').querySelector('.qty-input').focus();
    }
  });
});
</script>
<?php include 'includes/footer.php'; ?>
