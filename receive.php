<?php include 'includes/header.php'; require_admin();
$msg=''; $err='';
$locations = ['Rack 1','Rack 2','Rack 3','Rack 4','Rack 5','Rack 6','Rack 7','Rack 8','Cabinet 1','Cabinet 2','Storage Room'];
$uoms = ['Unit','Pc','Pack'];

function process_receive_row($conn, $d, $s, $loc, $u, $q, $pic) {
    $d = trim($d ?? '');
    $s = trim($s ?? '');
    $s = $s === '' ? null : $s;
    $loc = trim($loc ?? '');
    $u = trim($u ?? 'Pc');
    $q = max(1, (int)($q ?? 1));
    $pic = trim($pic ?? '');
    if ($d === '') return ['ok'=>false,'message'=>'Description is required.'];

    if ($s) {
        $stmt = $conn->prepare('SELECT * FROM items WHERE serial_number=? LIMIT 1');
        $stmt->bind_param('s', $s);
    } else {
        $stmt = $conn->prepare('SELECT * FROM items WHERE serial_number IS NULL AND item_description=? AND location=? AND uom=? LIMIT 1');
        $stmt->bind_param('sss', $d, $loc, $u);
    }
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();

    if ($item) {
        $id = (int)$item['id'];
        $stmt = $conn->prepare('UPDATE items SET total_received=total_received+?, actual_stock=actual_stock+?, location=?, uom=? WHERE id=?');
        $stmt->bind_param('iissi', $q, $q, $loc, $u, $id);
        // Use the existing master description for general barcodes/item codes to avoid duplicate names.
        $d = $item['item_description'];
        $stmt->execute();
    } else {
        $stmt = $conn->prepare('INSERT INTO items(item_description,serial_number,location,uom,total_received,actual_stock) VALUES(?,?,?,?,?,?)');
        $stmt->bind_param('ssssii', $d, $s, $loc, $u, $q, $q);
        $stmt->execute();
        $id = $conn->insert_id;
    }

    $act = 'Received';
    $stmt = $conn->prepare('INSERT INTO transactions(item_id,serial_number,item_description,action_type,quantity,pic,location,created_by) VALUES(?,?,?,?,?,?,?,?)');
    $stmt->bind_param('isssissi', $id, $s, $d, $act, $q, $pic, $loc, $_SESSION['user_id']);
    $stmt->execute();
    update_item_status($conn, $id);
    return ['ok'=>true];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descs = $_POST['item_description'] ?? [];
    $serials = $_POST['serial_number'] ?? [];
    $locs = $_POST['location'] ?? [];
    $uomList = $_POST['uom'] ?? [];
    $qtys = $_POST['quantity'] ?? [];
    $pics = $_POST['pic'] ?? [];
    $saved = 0; $errors = [];

    $conn->begin_transaction();
    try {
        foreach ($descs as $i => $d) {
            if (trim($d) === '' && trim($serials[$i] ?? '') === '') continue;
            $res = process_receive_row($conn, $d, $serials[$i] ?? '', $locs[$i] ?? '', $uomList[$i] ?? 'Pc', $qtys[$i] ?? 1, $pics[$i] ?? '');
            if ($res['ok']) $saved++; else $errors[] = 'Row '.($i+1).': '.$res['message'];
        }
        if ($saved === 0) throw new Exception('No valid receiving entries found.');
        $conn->commit();
        $msg = $saved . ' receiving entr' . ($saved > 1 ? 'ies' : 'y') . ' saved successfully.';
        if ($errors) $err = implode('<br>', array_map('e', $errors));
    } catch (Throwable $ex) {
        $conn->rollback();
        $err = $ex->getMessage();
    }
}
?>
<h3>Receiving Process</h3>
<?php if($msg):?><div class='alert alert-success'><?=$msg?></div><?php endif;?>
<?php if($err):?><div class='alert alert-danger'><?=$err?></div><?php endif;?>

<div class='card cardx p-4'>
  <div class='alert alert-info mb-3'>Use <b>+ Add Entry</b> for multiple receiving. For non-unique items like mouse or office supplies, use one general barcode/item code, example <b>5718185</b>. Every receive using the same code will add to the same stock record. The previous Description, Location, UOM, Quantity, and PIC/Receiver will remain on the next row. Serial/Barcode is cleared for easy scanning.</div>
  <form method='post' id='receiveForm'>
    <div class='table-responsive'>
      <table class='table table-bordered align-middle' id='receiveTable'>
        <thead class='table-light'>
          <tr>
            <th style='min-width:220px'>Description</th>
            <th style='min-width:180px'>Serial / Barcode / Item Code</th>
            <th style='min-width:170px'>Location</th>
            <th style='min-width:110px'>UOM</th>
            <th style='min-width:110px'>Quantity</th>
            <th style='min-width:180px'>PIC / Receiver</th>
            <th style='width:80px'>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr class='receive-row'>
            <td><input class='form-control desc-input' name='item_description[]' required></td>
            <td><input class='form-control scanner serial-input' name='serial_number[]' placeholder='Scan barcode / item code'></td>
            <td>
              <select class='form-select loc-input' name='location[]'>
                <?php foreach($locations as $l): ?><option value='<?=e($l)?>'><?=e($l)?></option><?php endforeach; ?>
              </select>
            </td>
            <td>
              <select class='form-select uom-input' name='uom[]'>
                <?php foreach($uoms as $u): ?><option value='<?=e($u)?>'><?=e($u)?></option><?php endforeach; ?>
              </select>
            </td>
            <td><input type='number' class='form-control qty-input' name='quantity[]' value='1' min='1'></td>
            <td><input class='form-control pic-input' name='pic[]' placeholder='Receiver name'></td>
            <td><button type='button' class='btn btn-outline-danger btn-sm remove-row' disabled>Remove</button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class='d-flex gap-2 justify-content-between flex-wrap'>
      <button type='button' class='btn btn-primary' id='addReceiveRow'>+ Add Entry</button>
      <button class='btn btn-success'>Save All Receiving</button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const tbody = document.querySelector('#receiveTable tbody');
  const addBtn = document.getElementById('addReceiveRow');

  function refreshRemoveButtons(){
    const rows = tbody.querySelectorAll('tr.receive-row');
    rows.forEach(row => row.querySelector('.remove-row').disabled = rows.length === 1);
  }

  addBtn.addEventListener('click', function(){
    const last = tbody.querySelector('tr.receive-row:last-child');
    const row = last.cloneNode(true);

    row.querySelector('.desc-input').value = last.querySelector('.desc-input').value;
    row.querySelector('.serial-input').value = '';
    row.querySelector('.loc-input').value = last.querySelector('.loc-input').value;
    row.querySelector('.uom-input').value = last.querySelector('.uom-input').value;
    row.querySelector('.qty-input').value = last.querySelector('.qty-input').value || 1;
    row.querySelector('.pic-input').value = last.querySelector('.pic-input').value;

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

  tbody.addEventListener('keydown', function(e){
    if(e.key === 'Enter' && e.target.classList.contains('serial-input')){
      e.preventDefault();
      addBtn.click();
    }
  });
});
</script>
<?php include 'includes/footer.php'; ?>
