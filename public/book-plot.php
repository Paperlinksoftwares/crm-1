<?php
// public/book-plot.php
session_start();
require __DIR__ . '/../app/db.php';

header('Content-Type: application/json; charset=utf-8');

$plot_id = (int)($_POST['plot_id'] ?? 0);
$buyer_name = trim($_POST['buyer_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$status = 'booked';
$csrf = $_POST['csrf_token'] ?? '';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Login required']);
    exit;
}

if (!$plot_id || !$buyer_name) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    $pdo->beginTransaction();
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

    $stmt = $pdo->prepare('UPDATE plots SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $plot_id]);

    $stmt = $pdo->prepare('INSERT INTO bookings (plot_id, buyer_name, phone, booking_date, status, created_by) VALUES (?, ?, ?, NOW(), ?, ?)');
    $stmt->execute([$plot_id, $buyer_name, $phone, $status, $_SESSION['user_id']]);
    $booking_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO booking_history (plot_id, from_status, to_status, changed_by, changed_at, reason) VALUES (?, ?, ?, ?, NOW(), ?)');
    $stmt->execute([$plot_id, $plot['status'], $status, $_SESSION['user_id'], 'User booking']);

    $pdo->commit();
    echo json_encode(['ok' => true, 'booking_id' => $booking_id]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    echo json_encode(['error' => 'Server error']);
    exit;
}
