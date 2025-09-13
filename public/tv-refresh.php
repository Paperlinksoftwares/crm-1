<?php
// public/tv-refresh.php
require __DIR__ . '/../app/db.php';
header('Content-Type: application/json; charset=utf-8');

$project_id = (int)($_GET['project_id'] ?? 0);
if (!$project_id) {
    echo json_encode(['error' => 'missing project_id']);
    exit;
}

$sql = "
SELECT pl.id, pl.plot_no, pl.status, bl.name AS block_name
FROM plots pl
JOIN blocks bl ON bl.id = pl.block_id
WHERE bl.project_id = ?
ORDER BY bl.sort_order, pl.plot_no
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$project_id]);
$plots = $stmt->fetchAll();

echo json_encode(['plots' => $plots, 'project_id' => $project_id]);
