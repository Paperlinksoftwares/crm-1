<?php
require __DIR__ . '/../app/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin-login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    header('Location: admin-login.php?err=' . urlencode('Missing credentials'));
    exit;
}

$stmt = $pdo->prepare('SELECT id, password_hash, role, name FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // regenerate session id to prevent fixation
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_name'] = $user['name'];
    $_SESSION['admin_role'] = $user['role'];
    header('Location: admin/dashboard.php');
    exit;
} else {
    header('Location: admin-login.php?err=' . urlencode('Invalid credentials'));
    exit;
}
