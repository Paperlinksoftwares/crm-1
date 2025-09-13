<?php
// public/admin/book-plot.php
require __DIR__ . '/../../app/admin_auth.php';
require __DIR__ . '/../../app/db.php';

header('Content-Type: application/json; charset=utf-8');

$plot_id = (int)($_POST['plot_id'] ?? 0);
$buyer_name = trim($_POST['buyer_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$status = ($_POST['status'] === 'partial') ? 'partial' : 'booked';
$amount_paid = floatval($_POST['amount_paid'] ?? 0);
$notes = trim($_POST['notes'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

if (!$plot_id || !$buyer_name) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}
// CSRF quick check
if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    // start transaction
    $pdo->beginTransaction();

    // lock the plot row
    $stmt = $pdo->prepare('SELECT id, status FROM plots WHERE id = ? FOR UPDATE');
    $stmt->execute([$plot_id]);
    $plot = $stmt->fetch();

    if (!$plot) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Plot not found']);
        exit;
    }

    if ($plot['status'] === 'booked') {
        $pdo->rollBack();
        echo json_encode(['error' => 'Plot already booked']);
        exit;
    }

    // update plot status
    $stmt = $pdo->prepare('UPDATE plots SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $plot_id]);

    // insert booking
    $stmt = $pdo->prepare('INSERT INTO bookings (plot_id, buyer_name, phone, booking_date, status, amount_paid, notes, created_by) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)');
    $stmt->execute([$plot_id, $buyer_name, $phone, $status, $amount_paid, $notes, $_SESSION['admin_id'] ?? null]);
    $booking_id = $pdo->lastInsertId();

    // audit trail
    $stmt = $pdo->prepare('INSERT INTO booking_history (plot_id, from_status, to_status, changed_by, changed_at, reason, data_json) VALUES (?, ?, ?, ?, NOW(), ?, ?)');
    $from_status = $plot['status'];
    $to_status = $status;
    $reason = 'Admin booking';
    $data_json = json_encode(['booking_id' => $booking_id, 'amount_paid' => $amount_paid, 'notes' => $notes]);
    $stmt->execute([$plot_id, $from_status, $to_status, $_SESSION['admin_id'] ?? null, $reason, $data_json]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'booking_id' => $booking_id]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['error' => 'Server error']);
    exit;
}
