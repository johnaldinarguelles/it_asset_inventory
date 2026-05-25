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

function require_admin(){
    require_login();
    if(!is_admin()){
        http_response_code(403);
        die('Admin access only.');
    }
}

function require_transactor(){
    require_login();
    if(!can_transact()){
        http_response_code(403);
        die('This action is allowed for Admin and Staff only.');
    }
}
?>
