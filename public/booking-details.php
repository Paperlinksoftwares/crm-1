<?php
// public/booking-details.php
// Display booking details for a plot in a simple Bootstrap card.

session_start();
require __DIR__ . '/../app/db.php';

header('Content-Type: text/html; charset=utf-8');

$plot_id = (int)($_GET['plot_id'] ?? 0);
if (!$plot_id) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Missing plot id.</div>';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.*, p.plot_no, bl.name AS block_name, pr.name AS project_name, u.name AS created_by_name
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
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo '<div class="alert alert-warning">No booking found for this plot.</div>';
    exit;
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Booking Details</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>

<body class="p-3">
    <h3 class="mb-3">Plot <?= htmlspecialchars($row['plot_no']) ?> — Booking Details</h3>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Buyer</h5>
            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($row['buyer_name'] ?? '—') ?></p>
            <p class="mb-3"><strong>Phone:</strong> <?= htmlspecialchars($row['phone'] ?? '—') ?></p>

            <h5 class="card-title">Booking</h5>
            <p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($row['status']) ?></p>
            <p class="mb-1"><strong>Amount Paid:</strong> ₹<?= htmlspecialchars($row['amount_paid'] ?? '0.00') ?></p>
            <p class="mb-3"><strong>Booking Date:</strong> <?= htmlspecialchars($row['booking_date']) ?></p>

            <?php if (!empty($row['notes'])): ?>
                <p class="mb-3"><strong>Notes:</strong><br><?= nl2br(htmlspecialchars($row['notes'])) ?></p>
            <?php endif; ?>

            <p class="text-muted mb-0">Created by: <?= htmlspecialchars($row['created_by_name'] ?? '—') ?></p>
        </div>
    </div>
</body>

</html>

