<?php require_once __DIR__.'/../config/auth.php'; require_login(); $page=basename($_SERVER['PHP_SELF']); ?>
<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>IT Asset Management</title>
<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
<link href='https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css' rel='stylesheet'>
<link href='assets/css/style.css' rel='stylesheet'></head><body>
<div class='app'><aside class='sidebar'><div class='brand'>IT Asset<br><span>Management</span></div>
<a class='navlink <?= $page=="index.php"?"active":"" ?>' href='index.php'>Dashboard</a>
<a class='navlink <?= $page=="items.php"?"active":"" ?>' href='items.php'>Items / Stock</a>
<?php if(is_admin()): ?><a class='navlink <?= $page=="no_serial_items.php"?"active":"" ?>' href='no_serial_items.php'>No Serial Items</a><?php endif; ?>
<?php if(is_admin()): ?><a class='navlink <?= $page=="import.php"?"active":"" ?>' href='import.php'>Import Excel/CSV</a><?php endif; ?>
<?php if(is_admin()): ?><a class='navlink <?= $page=="receive.php"?"active":"" ?>' href='receive.php'>Receive</a><?php endif; ?>
<?php if(can_transact()): ?><a class='navlink <?= $page=="issue.php"?"active":"" ?>' href='issue.php'>Issue</a><?php endif; ?>
<?php if(can_transact()): ?><a class='navlink <?= $page=="return.php"?"active":"" ?>' href='return.php'>Return</a><?php endif; ?>
<a class='navlink <?= $page=="transactions.php"?"active":"" ?>' href='transactions.php'>Transaction Log</a>
<a class='navlink <?= $page=="reports.php"?"active":"" ?>' href='reports.php'>Reports</a>
<?php if(is_admin()): ?><a class='navlink <?= $page=="users.php"?"active":"" ?>' href='users.php'>Users</a><?php endif; ?>
<a class='navlink text-danger' href='logout.php'>Logout</a></aside>
<main class='content'><div class='topbar'><button class='btn btn-outline-secondary d-md-none' id='menuBtn'>☰</button><div><b><?= e($_SESSION['name']) ?></b><small class='text-muted ms-2'><?= e(strtoupper($_SESSION['role'])) ?></small></div></div>
