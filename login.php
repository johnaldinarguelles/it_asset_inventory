<?php
session_start();
require_once 'config/db.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = trim($_POST['username'] ?? '');
  $p = $_POST['password'] ?? '';
  $stmt = $conn->prepare('SELECT * FROM users WHERE username=?');
  $stmt->bind_param('s', $u);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
  if ($user && password_verify($p, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    header('Location: index.php');
    exit;
  }
  $error = 'Invalid username or password.';
}
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In | IT Asset Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
<style>
  body.login-body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .login-shell{width:100%;max-width:920px;border-radius:24px;overflow:hidden;box-shadow:0 25px 60px rgba(16,24,40,.18);display:flex;min-height:520px}
  .login-brand{flex:1 1 42%;background:linear-gradient(150deg,var(--side),var(--side2) 65%,#1d4ed8);color:#fff;padding:44px;display:flex;flex-direction:column;justify-content:space-between}
  .login-brand .icon{font-size:38px;background:rgba(255,255,255,.12);width:64px;height:64px;border-radius:16px;display:flex;align-items:center;justify-content:center}
  .login-brand h1{font-size:28px;font-weight:800;margin:20px 0 8px;letter-spacing:-.02em}
  .login-brand p{color:#c7d2fe;font-size:14.5px;line-height:1.6}
  .login-brand ul{list-style:none;padding:0;margin:22px 0 0;display:flex;flex-direction:column;gap:12px}
  .login-brand ul li{display:flex;align-items:center;gap:10px;font-size:13.5px;color:#dbeafe}
  .login-brand ul li i{color:#93c5fd}
  .login-form{flex:1 1 58%;background:var(--card);padding:44px;display:flex;flex-direction:column;justify-content:center}
  .login-form h3{font-weight:800;margin-bottom:4px}
  .login-form .sub{color:var(--muted);margin-bottom:26px;font-size:14px}
  .password-wrap{position:relative}
  .password-wrap .toggle-pw{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;padding:4px 6px}
  .login-toggle{position:absolute;top:16px;right:16px}
  @media(max-width:767.98px){.login-shell{flex-direction:column;min-height:0}.login-brand{padding:30px}.login-form{padding:30px}}
</style>
</head>
<body class="login-body">
<button class="dark-toggle login-toggle" id="darkToggle" type="button" aria-label="Toggle dark mode" title="Toggle dark mode" style="color:var(--text)">🌙</button>
<div class="login-shell cardx">
  <div class="login-brand">
    <div>
      <div class="icon"><i class="bi bi-hdd-stack-fill"></i></div>
      <h1>IT Asset<br>Management</h1>
      <p>Track receiving, issuance, and returns with real-time stock visibility across your organization.</p>
    </div>
    <ul>
      <li><i class="bi bi-speedometer2"></i> Live dashboard &amp; analytics</li>
      <li><i class="bi bi-upc-scan"></i> Barcode-ready receiving &amp; issuance</li>
      <li><i class="bi bi-shield-lock"></i> Role-based access control</li>
    </ul>
  </div>
  <div class="login-form">
    <h3>Welcome back</h3>
    <p class="sub">Sign in to continue to your dashboard.</p>
    <?php if ($error): ?><div class="alert alert-danger d-flex align-items-center gap-2"><i class="bi bi-exclamation-circle-fill"></i><span><?= e($error) ?></span></div><?php endif; ?>
    <form method="post" id="loginForm" autocomplete="off">
      <label class="form-label">Username</label>
      <input class="form-control mb-3" name="username" placeholder="Enter your username" required autofocus>
      <label class="form-label">Password</label>
      <div class="password-wrap mb-3">
        <input class="form-control" id="password" type="password" name="password" placeholder="Enter your password" required>
        <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password"><i class="bi bi-eye"></i></button>
      </div>
      <button class="btn btn-primary w-100" id="loginBtn" type="submit">
        <span id="loginBtnText">Sign In</span>
        <span id="loginBtnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
      </button>
    </form>
  </div>
</div>
<script>
  document.getElementById('togglePw').addEventListener('click', function(){
    const pw = document.getElementById('password');
    const icon = this.querySelector('i');
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
  document.getElementById('loginForm').addEventListener('submit', function(){
    document.getElementById('loginBtn').disabled = true;
    document.getElementById('loginBtnText').textContent = 'Signing in...';
    document.getElementById('loginBtnSpinner').classList.remove('d-none');
  });
  (function(){
    const btn = document.getElementById('darkToggle');
    if (document.documentElement.getAttribute('data-theme')==='dark') btn.textContent = '☀️';
    btn.addEventListener('click', function(){
      const isDark = document.documentElement.getAttribute('data-theme')==='dark';
      if (isDark) { document.documentElement.removeAttribute('data-theme'); localStorage.removeItem('theme'); btn.textContent = '🌙'; }
      else { document.documentElement.setAttribute('data-theme','dark'); localStorage.setItem('theme','dark'); btn.textContent = '☀️'; }
    });
  })();
</script>
</body>
</html>
