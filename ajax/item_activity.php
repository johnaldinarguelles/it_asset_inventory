<?php
require_once __DIR__.'/../config/auth.php';
require_login();

$item_id = (int)($_GET['id'] ?? 0);
if ($item_id <= 0) {
    echo "<div class='alert alert-danger'>Invalid item selected.</div>";
    exit;
}

$st = $conn->prepare('SELECT *, (boh+total_received+total_returned-total_issued) AS stock, ((boh+total_received+total_returned-total_issued)-actual_stock) AS variance FROM items WHERE id=?');
$st->bind_param('i', $item_id);
$st->execute();
$item = $st->get_result()->fetch_assoc();

if (!$item) {
    echo "<div class='alert alert-danger'>Item not found.</div>";
    exit;
}

$where = ['item_id=?'];
$params = [$item_id];
$types = 'i';

if (!empty($_GET['from'])) {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $_GET['from'];
    $types .= 's';
}
if (!empty($_GET['to'])) {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $_GET['to'];
    $types .= 's';
}
if (!empty($_GET['action_type'])) {
    $where[] = 'action_type = ?';
    $params[] = $_GET['action_type'];
    $types .= 's';
}

$sql = 'SELECT * FROM transactions WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
$tx = $conn->prepare($sql);
$tx->bind_param($types, ...$params);
$tx->execute();
$rows = $tx->get_result();
?>
<div class="row g-2 mb-3">
    <div class="col-md-3"><div class="card cardx p-3"><small>Description</small><b><?= e($item['item_description']) ?></b></div></div>
    <div class="col-md-3"><div class="card cardx p-3"><small>Serial / General Code</small><b><?= e($item['serial_number']) ?></b></div></div>
    <div class="col-md-2"><div class="card cardx p-3"><small>Received</small><h5 class="mb-0"><?= (int)$item['total_received'] ?></h5></div></div>
    <div class="col-md-2"><div class="card cardx p-3"><small>Usage</small><h5 class="mb-0"><?= (int)$item['total_issued'] ?></h5></div></div>
    <div class="col-md-2"><div class="card cardx p-3"><small>Stock</small><h5 class="mb-0"><?= (int)$item['stock'] ?></h5></div></div>
</div>

<form id="activityFilterForm" class="row g-2 mb-3">
    <input type="hidden" name="id" value="<?= $item_id ?>">
    <div class="col-md-3"><input type="date" class="form-control" name="from" value="<?= e($_GET['from'] ?? '') ?>"></div>
    <div class="col-md-3"><input type="date" class="form-control" name="to" value="<?= e($_GET['to'] ?? '') ?>"></div>
    <div class="col-md-3">
        <select class="form-select" name="action_type">
            <option value="">All Activities</option>
            <?php foreach(['Received','Issued','Returned','Adjusted'] as $a): ?>
                <option value="<?= $a ?>" <?= ($_GET['action_type'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Filter Activities</button></div>
</form>

<div class="table-responsive">
<table class="table table-hover table-sm" id="activityModalTable">
    <thead>
        <tr>
            <th>ID</th>
            <th>Date When</th>
            <th>What / Action</th>
            <th>Qty</th>
            <th>PIC</th>
            <th>Location</th>
            <th>Week</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
    <?php while($r = $rows->fetch_assoc()): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['created_at']) ?></td>
            <td><?= e($r['action_type']) ?></td>
            <td><?= (int)$r['quantity'] ?></td>
            <td><?= e($r['pic']) ?></td>
            <td><?= e($r['location']) ?></td>
            <td><?= e($r['week_no']) ?></td>
            <td><?= e($r['remarks']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>
