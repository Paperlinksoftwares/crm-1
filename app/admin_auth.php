<?php
// app/admin_auth.php
session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: /realestate/public/admin-login.php');
    exit;
}
function is_admin_role($roles = [])
{
    return in_array($_SESSION['admin_role'] ?? '', (array)$roles, true);
}
