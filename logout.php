<<<<<<< HEAD
<?php
session_start();
session_unset();
session_destroy();

header("Location: /login");
exit;
=======
<?php session_start(); session_destroy(); header('Location: /index.php');
>>>>>>> development
