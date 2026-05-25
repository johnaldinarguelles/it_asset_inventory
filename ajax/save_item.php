<?php require_once '../config/auth.php'; require_admin(); header('Content-Type: application/json');
$id=(int)($_POST['id']??0); $d=trim($_POST['item_description']); $s=trim($_POST['serial_number']); if($s==='')$s=null; $loc=$_POST['location']; $u=$_POST['uom']; $boh=(int)$_POST['boh']; $actual=(int)$_POST['actual_stock']; $re=(int)$_POST['reorder_level'];
if($d===''){ echo json_encode(['ok'=>false,'message'=>'Description is required']); exit; }
if($id){ $stmt=$conn->prepare('UPDATE items SET item_description=?,serial_number=?,location=?,uom=?,boh=?,actual_stock=?,reorder_level=? WHERE id=?'); $stmt->bind_param('ssssiiii',$d,$s,$loc,$u,$boh,$actual,$re,$id); }
else{ $stmt=$conn->prepare('INSERT INTO items(item_description,serial_number,location,uom,boh,actual_stock,reorder_level) VALUES(?,?,?,?,?,?,?)'); $stmt->bind_param('ssssiii',$d,$s,$loc,$u,$boh,$actual,$re); }
$ok=$stmt->execute(); if($ok) update_item_status($conn,$id ?: $conn->insert_id); echo json_encode(['ok'=>$ok,'message'=>$ok?'Saved successfully':'Save failed. Make sure the Serial / Barcode / Item Code is not already used by another inventory master.']);
