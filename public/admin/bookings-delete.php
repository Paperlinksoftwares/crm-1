<?php
// public/admin/bookings-delete.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
    die('Invalid CSRF token');
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    header('Location: bookings-list.php?msg=' . urlencode('Invalid id'));
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock booking row
    $bq = $pdo->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
    $bq->execute([':id' => $id]);
    $booking = $bq->fetch(PDO::FETCH_ASSOC);
    if (!$booking) throw new Exception("Booking not found");

    // If already cancelled, just redirect
    if ($booking['status'] === 'cancelled') {
        $pdo->commit();
        header('Location: bookings-list.php?msg=' . urlencode('Booking already cancelled'));
        exit;
    }

    // Soft-cancel booking
    $upd = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
    $upd->execute([':id' => $id]);

    // Check other active bookings for the same plot
    $chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE plot_id = :pid AND id != :id AND status IN ('booked','partial')");
    $chk->execute([':pid' => $booking['plot_id'], ':id' => $id]);
    $others = (int)$chk->fetchColumn();

    if ($others === 0) {
        // set plot to available
        $pup = $pdo->prepare("UPDATE plots SET status = 'available', updated_at = NOW() WHERE id = :pid");
        $pup->execute([':pid' => $booking['plot_id']]);
    }

    // write booking_history
    $hist = $pdo->prepare("INSERT INTO booking_history (plot_id, from_status, to_status, changed_by, changed_at, reason, data_json) VALUES (:plot_id, :from_status, :to_status, :changed_by, NOW(), :reason, :data_json)");
    $data_json = json_encode(['booking_id' => $id, 'action' => 'soft_cancel']);
    $hist->execute([
        ':plot_id' => $booking['plot_id'],
        ':from_status' => $booking['status'],
        ':to_status' => 'cancelled',
        ':changed_by' => $_SESSION['admin_user_id'] ?? null,
        ':reason' => 'soft_cancel',
        ':data_json' => $data_json
    ]);

    $pdo->commit();
    header('Location: bookings-list.php?msg=' . urlencode('Booking cancelled'));
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: bookings-list.php?msg=' . urlencode('Cancel failed: ' . $e->getMessage()));
    exit;
}
