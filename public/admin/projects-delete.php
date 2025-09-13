<?php
// public/admin/projects-delete.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// basic CSRF token check
if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
    die('Invalid token');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    header('Location: projects-list.php?msg=' . urlencode('Invalid id'));
    exit;
}

// Prevent delete if blocks exist for this project (safer)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE project_id = :pid");
$stmt->execute([':pid' => $id]);
if ($stmt->fetchColumn() > 0) {
    header('Location: projects-list.php?msg=' . urlencode('Cannot delete: blocks exist for this project'));
    exit;
}

// Otherwise delete project record and its image file
$stmt = $pdo->prepare("SELECT image_url FROM projects WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$pdo->beginTransaction();
try {
    $del = $pdo->prepare("DELETE FROM projects WHERE id = :id");
    $del->execute([':id' => $id]);

    // remove file if present
    if (!empty($row['image_url'])) {
        $file = __DIR__ . '/../../' . ltrim($row['image_url'], '/');
        if (is_file($file)) @unlink($file);
    }

    $pdo->commit();
    header('Location: projects-list.php?msg=' . urlencode('Project deleted'));
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: projects-list.php?msg=' . urlencode('Delete failed'));
    exit;
}
