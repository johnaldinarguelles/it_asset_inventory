<?php
require_once '../config/auth.php';
require_login();
header('Content-Type: application/json');
$serial = trim($_GET['serial'] ?? '');
if ($serial === '') { echo json_encode(['ok'=>false]); exit; }
$stmt = $conn->prepare('SELECT id,item_description,serial_number,location,uom,boh,total_received,total_issued,total_returned,actual_stock,reorder_level,status,current_co,(boh+total_received+total_returned-total_issued) AS stock,(total_issued-total_returned) AS outstanding_issued FROM items WHERE serial_number=? LIMIT 1');
$stmt->bind_param('s', $serial);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
echo json_encode($item ? ['ok'=>true,'item'=>$item] : ['ok'=>false]);
