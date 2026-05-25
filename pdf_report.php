<?php
require_once 'config/auth.php'; require_admin();
$month=$_GET['month']??''; $from=$_GET['from']??''; $to=$_GET['to']??''; $action=$_GET['action_type']??''; $item=$_GET['item']??''; $pic=$_GET['pic']??''; $serial=$_GET['serial_number']??'';
$where=[]; $params=[]; $types='';
if($from!==''){ $where[]='DATE(created_at)>=?'; $params[]=$from; $types.='s'; }
if($to!==''){ $where[]='DATE(created_at)<=?'; $params[]=$to; $types.='s'; }
if($from==='' && $to==='' && $month!==''){ $where[]="DATE_FORMAT(created_at,'%Y-%m')=?"; $params[]=$month; $types.='s'; }
if($action!==''){ $where[]='action_type=?'; $params[]=$action; $types.='s'; }
if($item!==''){ $where[]='item_description LIKE ?'; $params[]='%'.$item.'%'; $types.='s'; }
if($pic!==''){ $where[]='pic LIKE ?'; $params[]='%'.$pic.'%'; $types.='s'; }
if($serial!==''){ $where[]='serial_number LIKE ?'; $params[]='%'.$serial.'%'; $types.='s'; }
$whereSql=$where?' WHERE '.implode(' AND ',$where):'';
$stmt=$conn->prepare("SELECT * FROM transactions $whereSql ORDER BY created_at DESC"); if($params)$stmt->bind_param($types,...$params); $stmt->execute(); $res=$stmt->get_result();
?>
<!doctype html><html><head><meta charset='utf-8'><title>IT Asset Report</title><style>body{font-family:Arial,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:6px}th{background:#eee}.no-print{margin-bottom:15px}@media print{.no-print{display:none}}</style></head><body>
<div class='no-print'><button onclick='window.print()'>Print / Save as PDF</button></div>
<h2>IT Asset Transaction Report</h2>
<p>Generated: <?=date('Y-m-d H:i:s')?> | Filter: <?=e(http_build_query($_GET) ?: 'All records')?></p>
<table><thead><tr><th>Date When</th><th>What / Action</th><th>Serial / Code</th><th>Item</th><th>Qty</th><th>PIC</th><th>Location</th><th>Week</th></tr></thead><tbody>
<?php while($r=$res->fetch_assoc()):?><tr><td><?=e($r['created_at'])?></td><td><?=e($r['action_type'])?></td><td><?=e($r['serial_number'])?></td><td><?=e($r['item_description'])?></td><td><?=$r['quantity']?></td><td><?=e($r['pic'])?></td><td><?=e($r['location'])?></td><td><?=e($r['week_no'])?></td></tr><?php endwhile;?>
</tbody></table></body></html>
