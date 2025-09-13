<?php
session_start();
$_SESSION = [];
session_destroy();
// Redirect back to admin login page
header('Location: admin-login.php');
exit;
