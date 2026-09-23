<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

function require_login(){
    if(empty($_SESSION['user_id'])){
        header('Location: login.php');
        exit;
    }
}

function current_role(){
    return $_SESSION['role'] ?? '';
}

function is_admin(){
    return current_role() === 'admin';
}

function is_staff(){
    return current_role() === 'staff';
}

function is_viewer(){
    return current_role() === 'viewer';
}

function can_transact(){
    return in_array(current_role(), ['admin','staff'], true);
}

function render_access_denied($message){
    http_response_code(403);

    // AJAX callers (jQuery sends this header by default) get a JSON error instead of an HTML page.
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $message]);
        exit;
    }

    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Called after includes/header.php: render inside the already-open app shell.
    if (defined('APP_HEADER_RENDERED')) {
        echo "<div class='d-flex flex-column align-items-center text-center py-5'>"
            ."<div class='mb-3' style='width:64px;height:64px;border-radius:50%;background:rgba(220,38,38,.12);display:flex;align-items:center;justify-content:center'><i class='bi bi-shield-lock-fill text-danger' style='font-size:28px'></i></div>"
            ."<h4 class='fw-bold mb-2'>Access Denied</h4>"
            ."<p class='text-muted mb-4'>$safe</p>"
            ."<a href='index.php' class='btn btn-primary'><i class='bi bi-arrow-left'></i> Back to Dashboard</a>"
            ."</div>";
        include __DIR__.'/../includes/footer.php';
        exit;
    }

    // Standalone fallback (called before the app shell has been rendered).
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Access Denied</title>"
        ."<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>"
        ."<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' rel='stylesheet'>"
        ."<link href='assets/css/style.css' rel='stylesheet'>"
        ."<script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>"
        ."</head><body class='d-flex align-items-center justify-content-center vh-100'>"
        ."<div class='card cardx p-5 text-center' style='max-width:420px'>"
        ."<div class='mx-auto mb-3' style='width:64px;height:64px;border-radius:50%;background:rgba(220,38,38,.12);display:flex;align-items:center;justify-content:center'><i class='bi bi-shield-lock-fill text-danger' style='font-size:28px'></i></div>"
        ."<h4 class='fw-bold mb-2'>Access Denied</h4>"
        ."<p class='text-muted mb-4'>$safe</p>"
        ."<a href='index.php' class='btn btn-primary w-100'><i class='bi bi-arrow-left'></i> Back to Dashboard</a>"
        ."</div></body></html>";
    exit;
}

function require_admin(){
    require_login();
    if(!is_admin()){
        render_access_denied('This page is only available to Admin accounts.');
    }
}

function require_transactor(){
    require_login();
    if(!can_transact()){
        render_access_denied('This action is only available to Admin and Staff accounts.');
    }
}
?>
