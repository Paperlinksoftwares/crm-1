<?php
require __DIR__ . '/../../app/admin_auth.php';
require __DIR__ . '/../../app/db.php';

$project_id = (int)($_GET['project_id'] ?? 0);
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = "1=1";
$params = [];
if ($project_id) {
    $where .= " AND pr.id = ?";
    $params[] = $project_id;
}
if ($from) {
    $where .= " AND b.booking_date >= ?";
    $params[] = $from . ' 00:00:00';
}
if ($to) {
    $where .= " AND b.booking_date <= ?";
    $params[] = $to . ' 23:59:59';
}

$sql = "
SELECT b.id AS booking_id, pr.name AS project, bl.name AS block, p.plot_no, b.buyer_name, b.phone, b.booking_date, b.status, b.amount_paid, b.notes, u.name AS created_by
FROM bookings b
JOIN plots p ON p.id = b.plot_id
JOIN blocks bl ON bl.id = p.block_id
JOIN projects pr ON pr.id = bl.project_id
LEFT JOIN users u ON u.id = b.created_by
WHERE {$where}
ORDER BY b.booking_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = 'bookings_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$out = fopen('php://output', 'w');
fputcsv($out, ['booking_id', 'project', 'block', 'plot_no', 'buyer_name', 'phone', 'booking_date', 'status', 'amount_paid', 'notes', 'created_by']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['booking_id'],
        $r['project'],
        $r['block'],
        $r['plot_no'],
        $r['buyer_name'],
        $r['phone'],
        $r['booking_date'],
        $r['status'],
        $r['amount_paid'],
        $r['notes'],
        $r['created_by']
    ]);
}
fclose($out);
exit;
