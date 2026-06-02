<?php require 'includes/auth.php';
require_login();
require 'config/db.php';
require 'functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['item_code'] as $i => $code) {
        if (trim($code) === '') continue;
        add_tx($conn, 'RECEIVED', trim($code), trim($_POST['item_description'][$i]), (int)$_POST['quantity'][$i], trim($_POST['serial_number'][$i] ?? ''), $_POST['location'][$i], $_POST['uom'][$i], trim($_POST['pic_receiver'][$i]), '', $_SESSION['user_id']);
    }
    header('Location: receiving.php?ok=1');
    exit;
}
include 'includes/header.php'; ?>
<h3>Receiving</h3><?php if (isset($_GET['ok'])) echo '<div class="alert alert-success">Saved successfully.</div>'; ?>
<div class="card p-4">
    <form method="post">
        <div class="table-responsive">
            <table class="table" id="entry">
                <thead>
                    <tr>
                        <th>Item Code/Serial</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Location</th>
                        <th>UOM</th>
                        <th>PIC/Receiver</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input name="item_code[]" class="form-control" required></td>
                        <td><input name="item_description[]" class="form-control desc" required></td>
                        <td><input type="number" name="quantity[]" class="form-control qty" value="1" min="1"></td>
                        <td><select name="location[]" class="form-select loc"><?php foreach (['Rack 1', 'Rack 2', 'Rack 3', 'Rack 4', 'Rack 5', 'Rack 6', 'Rack 7', 'Rack 8', 'Cabinet 1', 'Cabinet 2', 'Storage Room'] as $v) echo "<option>$v</option>"; ?></select></td>
                        <td><select name="uom[]" class="form-select uom">
                                <option>Unit</option>
                                <option>Pc</option>
                                <option>Pack</option>
                            </select></td>
                        <td><input name="pic_receiver[]" class="form-control pic"></td>
                        <td><button type="button" class="btn btn-danger del">x</button></td>
                    </tr>
                </tbody>
            </table>
        </div><button type="button" id="add" class="btn btn-secondary">+ Add Entry</button><button class="btn btn-primary">Save All</button>
    </form>
</div>
<script>
    document.addEventListener('click', e => {
        if (e.target.id === 'add') {
            let tr = document.querySelector('#entry tbody tr').cloneNode(true);
            tr.querySelector('[name="item_code[]"]').value = '';
            document.querySelector('#entry tbody').appendChild(tr)
        }
        if (e.target.classList.contains('del') && document.querySelectorAll('#entry tbody tr').length > 1) e.target.closest('tr').remove();
    });
</script>
<?php include 'includes/footer.php'; ?>