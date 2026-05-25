<?php include 'includes/header.php';
$month = $_GET['month'] ?? date('Y-m');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$action = $_GET['action_type'] ?? '';
$item = $_GET['item'] ?? '';
$pic = $_GET['pic'] ?? '';
$serial = $_GET['serial_number'] ?? '';

$where=[]; $params=[]; $types='';
if ($from !== '') { $where[]='DATE(created_at)>=?'; $params[]=$from; $types.='s'; }
if ($to !== '') { $where[]='DATE(created_at)<=?'; $params[]=$to; $types.='s'; }
if ($from === '' && $to === '' && $month !== '') { $where[]="DATE_FORMAT(created_at,'%Y-%m')=?"; $params[]=$month; $types.='s'; }
if ($action !== '') { $where[]='action_type=?'; $params[]=$action; $types.='s'; }
if ($item !== '') { $where[]='item_description LIKE ?'; $params[]='%'.$item.'%'; $types.='s'; }
if ($pic !== '') { $where[]='pic LIKE ?'; $params[]='%'.$pic.'%'; $types.='s'; }
if ($serial !== '') { $where[]='serial_number LIKE ?'; $params[]='%'.$serial.'%'; $types.='s'; }
$whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';

$summarySql = "SELECT action_type, SUM(quantity) q FROM transactions $whereSql GROUP BY action_type";
$stmt = $conn->prepare($summarySql); if($params) $stmt->bind_param($types, ...$params); $stmt->execute();
$rs = $stmt->get_result(); $data=['Received'=>0,'Issued'=>0,'Returned'=>0,'Adjusted'=>0]; while($r=$rs->fetch_assoc()) $data[$r['action_type']] = (int)$r['q'];

$itemSql = "SELECT item_description, serial_number, location,
 SUM(CASE WHEN action_type='Received' THEN quantity ELSE 0 END) total_received,
 SUM(CASE WHEN action_type='Issued' THEN quantity ELSE 0 END) total_issued,
 SUM(CASE WHEN action_type='Returned' THEN quantity ELSE 0 END) total_returned,
 SUM(CASE WHEN action_type='Received' THEN quantity WHEN action_type='Returned' THEN quantity WHEN action_type='Issued' THEN -quantity ELSE 0 END) net_movement
 FROM transactions $whereSql GROUP BY item_description, serial_number, location ORDER BY item_description";
$stmt = $conn->prepare($itemSql); if($params) $stmt->bind_param($types, ...$params); $stmt->execute(); $itemRows = $stmt->get_result();

$detailSql = "SELECT * FROM transactions $whereSql ORDER BY created_at DESC";
$stmt = $conn->prepare($detailSql); if($params) $stmt->bind_param($types, ...$params); $stmt->execute(); $details = $stmt->get_result();
$qs = http_build_query($_GET);
?>
<div class='d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2'>
  <h3>Dynamic Reports</h3>
  <div>
    <?php if(is_admin()): ?>
    <a class='btn btn-success' href='export_excel.php?type=report&<?=$qs?>'>Download Excel</a>
    <a class='btn btn-danger' href='pdf_report.php?<?=$qs?>'>PDF Report</a>
    <?php endif; ?>
  </div>
</div>

<div class='card cardx p-3 mb-3'>
  <form class='row g-2 align-items-end'>
    <div class='col-md-2'><label>Month</label><input type='month' name='month' value='<?=e($month)?>' class='form-control'></div>
    <div class='col-md-2'><label>From</label><input type='date' name='from' value='<?=e($from)?>' class='form-control'></div>
    <div class='col-md-2'><label>To</label><input type='date' name='to' value='<?=e($to)?>' class='form-control'></div>
    <div class='col-md-2'><label>Action</label><select class='form-select' name='action_type'><option value=''>All</option><?php foreach(['Received','Issued','Returned','Adjusted'] as $a):?><option value='<?=$a?>' <?=$action===$a?'selected':''?>><?=$a?></option><?php endforeach;?></select></div>
    <div class='col-md-2'><label>Item</label><input class='form-control' name='item' value='<?=e($item)?>' placeholder='Description'></div>
    <div class='col-md-2'><label>Serial/Code</label><input class='form-control' name='serial_number' value='<?=e($serial)?>' placeholder='Code'></div>
    <div class='col-md-2'><label>PIC</label><input class='form-control' name='pic' value='<?=e($pic)?>' placeholder='PIC'></div>
    <div class='col-md-2'><button class='btn btn-primary w-100'>Apply Filter</button></div>
    <div class='col-md-2'><a class='btn btn-outline-secondary w-100' href='reports.php'>Reset</a></div>
  </form>
  <small class='text-muted d-block mt-2'>Displayed data, totals, Excel export, and PDF report change dynamically based on the selected filters.</small>
</div>

<div class='row g-3 mb-3'>
  <div class='col-md-3'><div class='stat green'><small>Total Received</small><h3><?=$data['Received']?></h3></div></div>
  <div class='col-md-3'><div class='stat orange'><small>Total Issued / Usage</small><h3><?=$data['Issued']?></h3></div></div>
  <div class='col-md-3'><div class='stat red'><small>Total Returned</small><h3><?=$data['Returned']?></h3></div></div>
  <div class='col-md-3'><div class='stat'><small>Net Stock Movement</small><h3><?=($data['Received']+$data['Returned']-$data['Issued'])?></h3></div></div>
</div>

<div class='card cardx p-3 mb-3'>
  <h5>Inventory Movement Summary</h5>
  <div class='table-responsive'><table class='table table-hover datatable'>
    <thead><tr><th>Item</th><th>Serial / Code</th><th>Location</th><th>Total Received</th><th>Total Usage / Issued</th><th>Total Return</th><th>Net Movement</th></tr></thead>
    <tbody><?php while($r=$itemRows->fetch_assoc()):?><tr>
      <td><?=e($r['item_description'])?></td><td><?=e($r['serial_number'])?></td><td><?=e($r['location'])?></td>
      <td><?=$r['total_received']?></td><td><?=$r['total_issued']?></td><td><?=$r['total_returned']?></td><td><?=$r['net_movement']?></td>
    </tr><?php endwhile;?></tbody>
  </table></div>
</div>

<div class='card cardx p-3'>
  <h5>Detailed Transaction Report</h5>
  <div class='table-responsive'><table class='table table-hover datatable'>
    <thead><tr><th>Date When</th><th>What / Action</th><th>Serial / Code</th><th>Item</th><th>Quantity</th><th>PIC</th><th>Location</th><th>Week</th></tr></thead>
    <tbody><?php while($r=$details->fetch_assoc()):?><tr>
      <td><?=e($r['created_at'])?></td><td><?=e($r['action_type'])?></td><td><?=e($r['serial_number'])?></td><td><?=e($r['item_description'])?></td><td><?=$r['quantity']?></td><td><?=e($r['pic'])?></td><td><?=e($r['location'])?></td><td><?=e($r['week_no'])?></td>
    </tr><?php endwhile;?></tbody>
  </table></div>
</div>
<?php include 'includes/footer.php'; ?>
