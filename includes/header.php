<?php require_once __DIR__.'/../config/auth.php'; require_login(); $page=basename($_SERVER['PHP_SELF']); ?>
<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>IT Asset Management</title>
<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' rel='stylesheet'>
<link href='https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css' rel='stylesheet'>
<link href='assets/css/style.css' rel='stylesheet'><script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script></head><body>
<div class='app'><nav class='topnav'>
  <div class='topnav-inner'>
    <div class='brand'><i class='bi bi-hdd-stack-fill'></i> IT Asset<br><span>Management</span></div>
    <button class='menu-toggle' id='menuBtn' type='button' aria-label='Toggle navigation menu' aria-expanded='false'><i class='bi bi-list'></i></button>
    <button class='dark-toggle' id='darkToggle' type='button' aria-label='Toggle dark mode' title='Toggle dark mode'>🌙</button>
    <a class='navlink <?= $page=="index.php"?"active":"" ?>' href='index.php'><i class='bi bi-speedometer2'></i> Dashboard</a>
    <a class='navlink <?= $page=="items.php"?"active":"" ?>' href='items.php'><i class='bi bi-box-seam'></i> Items / Stock</a>
    <?php if(is_admin()): ?><a class='navlink <?= $page=="no_serial_items.php"?"active":"" ?>' href='no_serial_items.php'><i class='bi bi-upc-scan'></i> No Serial Items</a><?php endif; ?>
    <?php if(is_admin()): ?><a class='navlink <?= $page=="import.php"?"active":"" ?>' href='import.php'><i class='bi bi-file-earmark-arrow-up'></i> Import Excel/CSV</a><?php endif; ?>
    <?php if(is_admin()): ?><a class='navlink <?= $page=="receive.php"?"active":"" ?>' href='receive.php'><i class='bi bi-box-arrow-in-down'></i> Receive</a><?php endif; ?>
    <?php if(can_transact()): ?><a class='navlink <?= $page=="issue.php"?"active":"" ?>' href='issue.php'><i class='bi bi-box-arrow-up'></i> Issue</a><?php endif; ?>
    <?php if(can_transact()): ?><a class='navlink <?= $page=="return.php"?"active":"" ?>' href='return.php'><i class='bi bi-arrow-counterclockwise'></i> Return</a><?php endif; ?>
    <a class='navlink <?= $page=="transactions.php"?"active":"" ?>' href='transactions.php'><i class='bi bi-clock-history'></i> Transaction Log</a>
    <a class='navlink <?= $page=="reports.php"?"active":"" ?>' href='reports.php'><i class='bi bi-bar-chart-line'></i> Reports</a>
    <?php if(is_admin()): ?><a class='navlink <?= $page=="users.php"?"active":"" ?>' href='users.php'><i class='bi bi-people'></i> Users</a><?php endif; ?>
    <a class='navlink text-danger' href='logout.php'><i class='bi bi-box-arrow-right'></i> Logout</a>
    <div class='topnav-user'>
      <span class='avatar-badge'><?= e(strtoupper(substr($_SESSION['name'] ?? '?', 0, 1))) ?></span>
      <span class='topnav-user-text'><b><?= e($_SESSION['name']) ?></b><small class='text-muted ms-2'><?= e(strtoupper($_SESSION['role'])) ?></small></span>
    </div>
  </div>
</nav>
<main class='content'>
<?php define('APP_HEADER_RENDERED', true); ?>
