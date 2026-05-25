<?php include 'includes/header.php';
$id=(int)($_GET['id']??0);
$st=$conn->prepare('SELECT *, (boh+total_received+total_returned-total_issued) stock, ((boh+total_received+total_returned-total_issued)-actual_stock) variance FROM items WHERE id=?');
$st->bind_param('i',$id); $st->execute(); $item=$st->get_result()->fetch_assoc();
if(!$item){ echo "<div class='alert alert-danger'>Item not found.</div>"; include 'includes/footer.php'; exit; }
$where=['item_id=?']; $params=[$id]; $types='i';
if(!empty($_GET['from'])){ $where[]='DATE(created_at)>=?'; $params[]=$_GET['from']; $types.='s'; }
if(!empty($_GET['to'])){ $where[]='DATE(created_at)<=?'; $params[]=$_GET['to']; $types.='s'; }
if(!empty($_GET['action_type'])){ $where[]='action_type=?'; $params[]=$_GET['action_type']; $types.='s'; }
$sql='SELECT * FROM transactions WHERE '.implode(' AND ',$where).' ORDER BY created_at DESC';
$st=$conn->prepare($sql); $st->bind_param($types,...$params); $st->execute(); $tx=$st->get_result();
$qs=http_build_query(array_merge($_GET,['item_id'=>$id]));
?>
<div class='d-flex justify-content-between align-items-center mb-3'><h3>Item Activity View</h3><a href='items.php' class='btn btn-outline-secondary'>Back</a></div>
<div class='row g-3 mb-3'>
 <div class='col-md-3'><div class='card cardx p-3'><small>Description</small><b><?=e($item['item_description'])?></b></div></div>
 <div class='col-md-3'><div class='card cardx p-3'><small>Serial / General Code</small><b><?=e($item['serial_number'])?></b></div></div>
 <div class='col-md-2'><div class='card cardx p-3'><small>Total Received</small><h4><?=$item['total_received']?></h4></div></div>
 <div class='col-md-2'><div class='card cardx p-3'><small>Total Usage</small><h4><?=$item['total_issued']?></h4></div></div>
 <div class='col-md-2'><div class='card cardx p-3'><small>Total Stock</small><h4><?=$item['stock']?></h4></div></div>
</div>
<div class='card cardx p-3 mb-3'><form class='row g-2'>
 <input type='hidden' name='id' value='<?=$id?>'>
 <div class='col-md-3'><input type='date' class='form-control' name='from' value='<?=e($_GET['from']??'')?>'></div>
 <div class='col-md-3'><input type='date' class='form-control' name='to' value='<?=e($_GET['to']??'')?>'></div>
 <div class='col-md-3'><select class='form-select' name='action_type'><option value=''>All Activities</option><?php foreach(['Received','Issued','Returned','Adjusted'] as $a):?><option value='<?=$a?>' <?=($_GET['action_type']??'')===$a?'selected':''?>><?=$a?></option><?php endforeach;?></select></div>
 <div class='col-md-3'><button class='btn btn-primary w-100'>Filter Activities</button></div>
</form></div>
<div class='card cardx p-3'><h5>Activities for this item</h5><table class='table table-hover datatable'><thead><tr><th>ID</th><th>Date When</th><th>What / Action</th><th>Qty</th><th>PIC</th><th>Location</th><th>Week</th><th>Remarks</th></tr></thead><tbody>
<?php while($r=$tx->fetch_assoc()):?><tr><td><?=$r['id']?></td><td><?=e($r['created_at'])?></td><td><?=e($r['action_type'])?></td><td><?=$r['quantity']?></td><td><?=e($r['pic'])?></td><td><?=e($r['location'])?></td><td><?=e($r['week_no'])?></td><td><?=e($r['remarks'])?></td></tr><?php endwhile;?>
</tbody></table></div>
<?php include 'includes/footer.php'; ?>
