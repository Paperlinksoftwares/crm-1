<?php
// public/admin/blocks-delete.php
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
    header('Location: blocks-list.php?msg=' . urlencode('Invalid id'));
    exit;
}

// Prevent delete if plots exist
$stmt = $pdo->prepare("SELECT COUNT(*) FROM plots WHERE block_id = :bid");
$stmt->execute([':bid' => $id]);
if ($stmt->fetchColumn() > 0) {
    header('Location: blocks-list.php?msg=' . urlencode('Cannot delete: plots exist for this block'));
    exit;
}

$del = $pdo->prepare("DELETE FROM blocks WHERE id = :id");
$del->execute([':id' => $id]);
header('Location: blocks-list.php?msg=' . urlencode('Block deleted'));
exit;
