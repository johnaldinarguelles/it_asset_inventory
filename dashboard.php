<?php require 'includes/auth.php';
require_login();
require 'config/db.php';
include 'includes/header.php';
$tot = $conn->query("SELECT SUM(CASE WHEN transaction_type='RECEIVED' THEN quantity ELSE 0 END) received,SUM(CASE WHEN transaction_type='ISSUED' THEN quantity ELSE 0 END) issued,SUM(CASE WHEN transaction_type='RETURNED' THEN quantity ELSE 0 END) returned FROM inventory_transactions")->fetch_assoc();
$stock = $conn->query("SELECT COALESCE(SUM(actual_stock),0) s FROM inventory_items")->fetch_assoc()['s'];
?>
<h3>Dashboard</h3>
<div class="row g-3 my-2">
    <?php foreach ([['Total Stocks', $stock], ['Received', $tot['received'] ?? 0], ['Issued / Usage', $tot['issued'] ?? 0], ['Returned', $tot['returned'] ?? 0]] as $c): ?><div class="col-md-3">
            <div class="card p-4">
                <div class="text-muted"><?= $c[0] ?></div>
                <h2><?= $c[1] ?></h2>
            </div>
        </div><?php endforeach; ?></div>
<div class="card p-4 mt-3">
    <h5>Inventory Summary</h5><canvas id="chart" height="90"></canvas>
</div>
<script>
    window.onload = () => new Chart(document.getElementById('chart'), {
        type: 'bar',
        data: {
            labels: ['Stock', 'Received', 'Issued', 'Returned'],
            datasets: [{
                label: 'Qty',
                data: [<?= $stock ?>, <?= $tot['received'] ?? 0 ?>, <?= $tot['issued'] ?? 0 ?>, <?= $tot['returned'] ?? 0 ?>]
            }]
        }
    })
</script>
<?php include 'includes/footer.php'; ?>