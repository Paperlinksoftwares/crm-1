<?php
// public/admin/plots-delete.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
    die('Invalid token');
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    header('Location: plots-list.php?msg=' . urlencode('Invalid id'));
    exit;
}

// check bookings exist
$chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE plot_id = :pid AND status IN ('booked','partial')");
$chk->execute([':pid' => $id]);
if ($chk->fetchColumn() > 0) {
    header('Location: plots-list.php?msg=' . urlencode('Cannot delete: active bookings exist for this plot'));
    exit;
}

// safe to delete
$del = $pdo->prepare("DELETE FROM plots WHERE id = :id");
$del->execute([':id' => $id]);
header('Location: plots-list.php?msg=' . urlencode('Plot deleted'));
exit;
