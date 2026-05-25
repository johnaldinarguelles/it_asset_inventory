<?php
function recompute_item($conn,$code){
 $stmt=$conn->prepare("SELECT item_description,item_code,MAX(location) location,MAX(uom) uom,
 SUM(CASE WHEN transaction_type='RECEIVED' THEN quantity ELSE 0 END) received,
 SUM(CASE WHEN transaction_type='ISSUED' THEN quantity ELSE 0 END) issued,
 SUM(CASE WHEN transaction_type='RETURNED' THEN quantity ELSE 0 END) returned
 FROM inventory_transactions WHERE item_code=? GROUP BY item_code,item_description");
 $stmt->bind_param('s',$code); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); if(!$r) return;
 $stock=(int)$r['received']+(int)$r['returned']-(int)$r['issued'];
 $stmt=$conn->prepare("INSERT INTO inventory_items(item_description,item_code,location,uom,actual_stock) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE item_description=VALUES(item_description),location=VALUES(location),uom=VALUES(uom),actual_stock=?");
 $stmt->bind_param('ssssis',$r['item_description'],$r['item_code'],$r['location'],$r['uom'],$stock,$stock); $stmt->execute();
 $id=$conn->insert_id ?: $conn->query("SELECT id FROM inventory_items WHERE item_code='".$conn->real_escape_string($code)."'")->fetch_assoc()['id'];
 $conn->query("UPDATE inventory_transactions SET item_id=".(int)$id." WHERE item_code='".$conn->real_escape_string($code)."'");
}
function add_tx($conn,$type,$code,$desc,$qty,$serial,$loc,$uom,$pic,$remarks,$uid){
 $stmt=$conn->prepare("INSERT INTO inventory_transactions(item_code,item_description,transaction_type,quantity,serial_number,location,uom,pic_receiver,remarks,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
 $stmt->bind_param('sssisssssi',$code,$desc,$type,$qty,$serial,$loc,$uom,$pic,$remarks,$uid); $stmt->execute(); recompute_item($conn,$code);
}
?>
