<?php
// public/booking-details.php
session_start();
require __DIR__ . '/../app/db.php';
header('Content-Type: application/json; charset=utf-8');

$plot_id = (int)($_GET['plot_id'] ?? 0);
if (!$plot_id) {
    echo json_encode(['error' => 'Missing plot id']);
    exit;
}

$stmt = $pdo->prepare(
"  SELECT b.*, p.plot_no, bl.name AS block_name, pr.name AS project_name, u.name as created_by_name
   FROM bookings b
   JOIN plots p ON p.id = b.plot_id
   JOIN blocks bl ON bl.id = p.block_id
   JOIN projects pr ON pr.id = bl.project_id
   LEFT JOIN users u ON u.id = b.created_by
   WHERE b.plot_id = ?
   ORDER BY b.created_at DESC
   LIMIT 1"
);
$stmt->execute([$plot_id]);
$row = $stmt->fetch();
if (!$row) {
    echo json_encode(['error' => 'No booking found for this plot']);
    exit;
}

echo json_encode([
    'buyer_name' => $row['buyer_name'],
    'phone' => $row['phone'],
    'status' => $row['status'],
    'amount_paid' => $row['amount_paid'] ?? null,
    'notes' => $row['notes'],
    'booking_date' => $row['booking_date'],
    'created_by' => $row['created_by_name'],
]);
