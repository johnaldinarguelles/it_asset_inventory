<?php include 'includes/header.php';
$stats=$conn->query("SELECT COUNT(*) items, COALESCE(SUM(boh+total_received+total_returned-total_issued),0) stock, COALESCE(SUM(total_issued),0) issued, COALESCE(SUM(total_returned),0) returned, COALESCE(SUM(total_received),0) received, SUM(CASE WHEN (boh+total_received+total_returned-total_issued)<=reorder_level AND (boh+total_received+total_returned-total_issued)>0 THEN 1 ELSE 0 END) low, SUM(CASE WHEN (boh+total_received+total_returned-total_issued)<=0 THEN 1 ELSE 0 END) `out` FROM items")->fetch_assoc();
$statuses=$conn->query("SELECT CASE WHEN (boh+total_received+total_returned-total_issued)<=0 THEN 'Out of Stock' WHEN (boh+total_received+total_returned-total_issued)<=reorder_level THEN 'Low Stock' WHEN status='Issued' THEN 'Issued' ELSE 'Available' END s, COUNT(*) c FROM items GROUP BY s")->fetch_all(MYSQLI_ASSOC);
$months=$conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') m, action_type, SUM(quantity) q FROM transactions GROUP BY m, action_type ORDER BY m DESC LIMIT 18");
$labels=[];$rec=[];$iss=[];$ret=[]; while($r=$months->fetch_assoc()){ if(!in_array($r['m'],$labels))$labels[]=$r['m']; if($r['action_type']=='Received')$rec[$r['m']]=$r['q']; if($r['action_type']=='Issued')$iss[$r['m']]=$r['q']; if($r['action_type']=='Returned')$ret[$r['m']]=$r['q']; }
$labels=array_reverse($labels);
$topIssued=$conn->query("SELECT item_description, SUM(quantity) q FROM transactions WHERE action_type='Issued' GROUP BY item_description ORDER BY q DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$topReceived=$conn->query("SELECT item_description, SUM(quantity) q FROM transactions WHERE action_type='Received' GROUP BY item_description ORDER BY q DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$low=$conn->query("SELECT *,(boh+total_received+total_returned-total_issued) stock FROM items WHERE (boh+total_received+total_returned-total_issued)<=reorder_level ORDER BY stock ASC LIMIT 8");
$recent=$conn->query("SELECT t.*, u.name u_name FROM transactions t LEFT JOIN users u ON u.id=t.created_by ORDER BY t.created_at DESC, t.id DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
?>
<div class='row g-3 mb-4'>
  <div class='col-6 col-md-4 col-xl-2'><div class='stat'><small>Total Items</small><h3><?=$stats['items']?></h3></div></div>
  <div class='col-6 col-md-4 col-xl-2'><div class='stat'><small>Total Stock</small><h3><?=$stats['stock']?></h3></div></div>
  <div class='col-6 col-md-4 col-xl-2'><div class='stat green'><small>Received</small><h3><?=$stats['received']?></h3></div></div>
  <div class='col-6 col-md-4 col-xl-2'><div class='stat orange'><small>Issued / Usage</small><h3><?=$stats['issued']?></h3></div></div>
  <div class='col-6 col-md-4 col-xl-2'><div class='stat red'><small>Returned</small><h3><?=$stats['returned']?></h3></div></div>
  <div class='col-6 col-md-4 col-xl-2'><div class='stat purple'><small>Low / Out of Stock</small><h3><?=(int)$stats['low']+(int)$stats['out']?></h3></div></div>
</div>

<div class='row g-3 mb-4'>
  <div class='col-lg-8'><div class='card cardx p-4 h-100'><h5 class='mb-1'>Monthly Analytics</h5><p class='text-muted small mb-3'>Received / Issued / Returned quantities over time</p><div class='chart-box chart-lg'><canvas id='monthlyChart'></canvas></div></div></div>
  <div class='col-lg-4'><div class='card cardx p-4 h-100'><h5 class='mb-1'>Stock Status</h5><p class='text-muted small mb-3'>Distribution of items by availability</p><div class='chart-box'><canvas id='statusChart'></canvas></div></div></div>
</div>

<div class='row g-3 mb-4'>
  <div class='col-lg-6'><div class='card cardx p-4 h-100'><h5 class='mb-1'>Top Issued Items</h5><p class='text-muted small mb-3'>Most frequently issued this period</p><div class='chart-box chart-md'><canvas id='issuedChart'></canvas></div></div></div>
  <div class='col-lg-6'><div class='card cardx p-4 h-100'><h5 class='mb-1'>Top Received Items</h5><p class='text-muted small mb-3'>Most frequently received this period</p><div class='chart-box chart-md'><canvas id='receivedChart'></canvas></div></div></div>
</div>

<div class='row g-3 mb-4'>
  <div class='col-lg-5'><div class='card cardx p-4 h-100'><h5 class='mb-3'>Low Stock Alert</h5><div class='table-responsive'><table class='table table-sm align-middle'><thead><tr><th>Item</th><th style='width:120px'>Stock Level</th></tr></thead><tbody><?php while($i=$low->fetch_assoc()): $p=min(100,(int)$i['stock']/(max(1,(int)$i['reorder_level']*2))*100); ?><tr><td class='text-wrap'><?=e($i['item_description'])?></td><td><div class='d-flex align-items-center gap-2'><div class='progress flex-grow-1' style='height:8px'><div class='progress-bar <?= $i['stock']<=0?'bg-danger':($i['stock']<=$i['reorder_level']?'bg-warning':'') ?>' style='width:<?=$p?>%'></div></div><span class='small <?= $i['stock']<=0?'text-danger fw-bold':'text-warning fw-bold' ?>'><?=$i['stock']?></span></div></td></tr><?php endwhile; ?></tbody></table></div></div></div>
  <div class='col-lg-7'><div class='card cardx p-4 h-100'><h5 class='mb-3'>Recent Transactions</h5><div class='table-responsive'><table class='table table-sm align-middle'><thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Qty</th><th>PIC</th><th>By</th></tr></thead><tbody><?php foreach($recent as $t): $badge=['Received'=>'bg-success','Issued'=>'bg-warning text-dark','Returned'=>'bg-info text-dark','Adjusted'=>'bg-secondary'][$t['action_type']]??'bg-secondary'; ?><tr><td class='text-nowrap'><?=date('M d, H:i',strtotime($t['created_at']))?></td><td class='text-wrap'><?=e($t['item_description'])?></td><td><span class='badge <?=$badge?>'><?=$t['action_type']?></span></td><td><?=(int)$t['quantity']?></td><td><?=e($t['pic'])?></td><td><?=e($t['u_name'])?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
<script>
window.chartData={labels:<?=json_encode($labels)?>,received:<?=json_encode(array_map(fn($m)=>(int)($rec[$m]??0),$labels))?>,issued:<?=json_encode(array_map(fn($m)=>(int)($iss[$m]??0),$labels))?>,returned:<?=json_encode(array_map(fn($m)=>(int)($ret[$m]??0),$labels))?>,status:<?=json_encode($statuses)?>,topIssued:<?=json_encode($topIssued)?>,topReceived:<?=json_encode($topReceived)?>};
</script>
<script src='assets/js/dashboard.js'></script>
<?php include 'includes/footer.php'; ?>
