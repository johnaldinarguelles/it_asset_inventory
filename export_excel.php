<?php
require_once 'config/auth.php'; require_admin();
$type=$_GET['type']??'items';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename='.$type.'_'.date('Ymd_His').'.xls');
echo "<table border='1'>";
function build_transaction_filter(&$conn){
    $month=$_GET['month']??''; $from=$_GET['from']??''; $to=$_GET['to']??''; $action=$_GET['action_type']??''; $item=$_GET['item']??''; $pic=$_GET['pic']??''; $serial=$_GET['serial_number']??'';
    $where=[]; $params=[]; $types='';
    if($from!==''){ $where[]='DATE(created_at)>=?'; $params[]=$from; $types.='s'; }
    if($to!==''){ $where[]='DATE(created_at)<=?'; $params[]=$to; $types.='s'; }
    if($from==='' && $to==='' && $month!==''){ $where[]="DATE_FORMAT(created_at,'%Y-%m')=?"; $params[]=$month; $types.='s'; }
    if($action!==''){ $where[]='action_type=?'; $params[]=$action; $types.='s'; }
    if($item!==''){ $where[]='item_description LIKE ?'; $params[]='%'.$item.'%'; $types.='s'; }
    if($pic!==''){ $where[]='pic LIKE ?'; $params[]='%'.$pic.'%'; $types.='s'; }
    if($serial!==''){ $where[]='serial_number LIKE ?'; $params[]='%'.$serial.'%'; $types.='s'; }
    return [$where ? ' WHERE '.implode(' AND ', $where) : '', $params, $types];
}
if($type==='transactions' || $type==='report'){
    [$whereSql,$params,$types] = build_transaction_filter($conn);
    if($type==='report'){
        echo '<tr><th colspan="7">Inventory Movement Summary</th></tr>';
        echo '<tr><th>Item</th><th>Serial / Code</th><th>Location</th><th>Total Received</th><th>Total Usage / Issued</th><th>Total Return</th><th>Net Movement</th></tr>';
        $sql="SELECT item_description, serial_number, location,
        SUM(CASE WHEN action_type='Received' THEN quantity ELSE 0 END) total_received,
        SUM(CASE WHEN action_type='Issued' THEN quantity ELSE 0 END) total_issued,
        SUM(CASE WHEN action_type='Returned' THEN quantity ELSE 0 END) total_returned,
        SUM(CASE WHEN action_type='Received' THEN quantity WHEN action_type='Returned' THEN quantity WHEN action_type='Issued' THEN -quantity ELSE 0 END) net_movement
        FROM transactions $whereSql GROUP BY item_description, serial_number, location ORDER BY item_description";
        $stmt=$conn->prepare($sql); if($params)$stmt->bind_param($types,...$params); $stmt->execute(); $res=$stmt->get_result();
        while($r=$res->fetch_assoc()) echo '<tr><td>'.e($r['item_description']).'</td><td>'.e($r['serial_number']).'</td><td>'.e($r['location']).'</td><td>'.$r['total_received'].'</td><td>'.$r['total_issued'].'</td><td>'.$r['total_returned'].'</td><td>'.$r['net_movement'].'</td></tr>';
        echo '<tr></tr><tr><th colspan="8">Detailed Transaction Report</th></tr>';
    }
    echo '<tr><th>ID</th><th>Date When</th><th>What / Action</th><th>Serial / Code</th><th>Item</th><th>Qty</th><th>PIC</th><th>Week</th><th>Location</th></tr>';
    $stmt=$conn->prepare("SELECT * FROM transactions $whereSql ORDER BY created_at DESC"); if($params)$stmt->bind_param($types,...$params); $stmt->execute(); $res=$stmt->get_result();
    while($r=$res->fetch_assoc()) echo '<tr><td>'.$r['id'].'</td><td>'.$r['created_at'].'</td><td>'.$r['action_type'].'</td><td>'.e($r['serial_number']).'</td><td>'.e($r['item_description']).'</td><td>'.$r['quantity'].'</td><td>'.e($r['pic']).'</td><td>'.$r['week_no'].'</td><td>'.e($r['location']).'</td></tr>';
}
elseif($type==='monthly'){
    $m=$_GET['month']??date('Y-m'); echo '<tr><th>Month</th><th>Item</th><th>Action</th><th>Total</th></tr>'; $stmt=$conn->prepare("SELECT item_description,action_type,SUM(quantity) q FROM transactions WHERE DATE_FORMAT(created_at,'%Y-%m')=? GROUP BY item_description,action_type"); $stmt->bind_param('s',$m); $stmt->execute(); $res=$stmt->get_result(); while($r=$res->fetch_assoc()) echo '<tr><td>'.$m.'</td><td>'.e($r['item_description']).'</td><td>'.$r['action_type'].'</td><td>'.$r['q'].'</td></tr>';
}
else{
    echo '<tr><th>ID</th><th>Description</th><th>Serial / Code</th><th>Location</th><th>UOM</th><th>BOH</th><th>Total Received</th><th>Total Usage / Issued</th><th>Total Returned</th><th>Total Stock</th><th>Actual</th><th>Variance</th><th>Status</th></tr>'; $res=$conn->query('SELECT *,(boh+total_received+total_returned-total_issued) stock,(actual_stock-(boh+total_received+total_returned-total_issued)) variance FROM items'); while($r=$res->fetch_assoc()) echo '<tr><td>'.$r['id'].'</td><td>'.e($r['item_description']).'</td><td>'.e($r['serial_number']).'</td><td>'.e($r['location']).'</td><td>'.e($r['uom']).'</td><td>'.$r['boh'].'</td><td>'.$r['total_received'].'</td><td>'.$r['total_issued'].'</td><td>'.$r['total_returned'].'</td><td>'.$r['stock'].'</td><td>'.$r['actual_stock'].'</td><td>'.$r['variance'].'</td><td>'.$r['status'].'</td></tr>';
}
echo '</table>';
?>
