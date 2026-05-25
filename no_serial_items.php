<?php include 'includes/header.php'; require_admin();
$msg=''; $err='';
$locations = ['Rack 1','Rack 2','Rack 3','Rack 4','Rack 5','Rack 6','Rack 7','Rack 8','Cabinet 1','Cabinet 2','Storage Room'];
$uoms = ['Unit','Pc','Pack'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  $desc=trim($_POST['item_description']??''); $loc=trim($_POST['location']??'Storage Room'); $uom=trim($_POST['uom']??'Pc');
  if($desc==='') $err='Item description is required.';
  else {
    $base='NS-'.strtoupper(preg_replace('/[^A-Za-z0-9]+/','-', $desc));
    $base=trim($base,'-'); $code=$base; $n=2;
    while(true){ $st=$conn->prepare('SELECT id FROM no_serial_items WHERE item_code=? UNION SELECT id FROM items WHERE serial_number=? LIMIT 1'); $st->bind_param('ss',$code,$code); $st->execute(); if(!$st->get_result()->fetch_assoc()) break; $code=$base.'-'.$n++; }
    $conn->begin_transaction();
    try{
      $st=$conn->prepare('INSERT INTO no_serial_items(item_description,item_code,default_location,uom,created_by) VALUES(?,?,?,?,?)');
      $st->bind_param('ssssi',$desc,$code,$loc,$uom,$_SESSION['user_id']); $st->execute();
      $st=$conn->prepare('INSERT INTO items(item_description,serial_number,location,uom,boh,total_received,total_issued,total_returned,actual_stock,reorder_level,status) VALUES(?,?,?,?,0,0,0,0,0,5,\'Available\')');
      $st->bind_param('ssss',$desc,$code,$loc,$uom); $st->execute();
      $conn->commit(); $msg='No-serial item added. Use item code '.$code.' for receiving, issuance, and return.';
    }catch(Throwable $e){ $conn->rollback(); $err='Cannot add item. It may already exist.'; }
  }
}
$res=$conn->query("SELECT n.*, i.id item_id, (i.boh+i.total_received+i.total_returned-i.total_issued) stock, i.total_received, i.total_issued, i.total_returned FROM no_serial_items n LEFT JOIN items i ON i.serial_number=n.item_code ORDER BY n.created_at DESC");
?>
<div class='d-flex justify-content-between align-items-center mb-3'><h3>No Serial Items Maintenance</h3></div>
<?php if($msg):?><div class='alert alert-success'><?=e($msg)?></div><?php endif;?>
<?php if($err):?><div class='alert alert-danger'><?=e($err)?></div><?php endif;?>
<div class='alert alert-info'>For items without unique serial numbers, this page creates one <b>general item code</b>. Example: all AA Battery transactions use the same generated code. Editing is disabled to protect report accuracy; add a new item only when needed.</div>
<div class='row g-3'>
  <div class='col-lg-4'><div class='card cardx p-3'><h5>Add No-Serial Item</h5><form method='post'>
    <label class='form-label'>Item Description</label><input class='form-control mb-2' name='item_description' placeholder='Example: USB Mouse' required>
    <label class='form-label'>Default Location</label><select class='form-select mb-2' name='location'><?php foreach($locations as $l):?><option value='<?=e($l)?>'><?=e($l)?></option><?php endforeach;?></select>
    <label class='form-label'>UOM</label><select class='form-select mb-3' name='uom'><?php foreach($uoms as $u):?><option value='<?=e($u)?>'><?=e($u)?></option><?php endforeach;?></select>
    <button class='btn btn-primary w-100'>+ Add Item</button>
  </form></div></div>
  <div class='col-lg-8'><div class='card cardx p-3'><table class='table table-hover datatable'><thead><tr><th>ID</th><th>Description</th><th>General Item Code</th><th>Location</th><th>UOM</th><th>Received</th><th>Usage</th><th>Return</th><th>Stock</th><th>Action</th></tr></thead><tbody>
  <?php while($r=$res->fetch_assoc()):?><tr><td><?=$r['id']?></td><td><?=e($r['item_description'])?></td><td><code><?=e($r['item_code'])?></code></td><td><?=e($r['default_location'])?></td><td><?=e($r['uom'])?></td><td><?=e($r['total_received']??0)?></td><td><?=e($r['total_issued']??0)?></td><td><?=e($r['total_returned']??0)?></td><td><?=e($r['stock']??0)?></td><td><?php if($r['item_id']):?><a class='btn btn-sm btn-outline-info' href='view_item.php?id=<?=$r['item_id']?>'>View</a><?php endif;?></td></tr><?php endwhile;?>
  </tbody></table></div></div>
</div>
<?php include 'includes/footer.php'; ?>
