<?php
if(session_status()===PHP_SESSION_NONE) session_start();
function require_login(){ if(empty($_SESSION['user_id'])){ header('Location: login.php'); exit; } }
function is_admin(){ return ($_SESSION['role'] ?? '') === 'admin'; }
?>
