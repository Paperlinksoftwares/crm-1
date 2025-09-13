<?php
// public/admin/bookings-edit.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die('Booking id required');

$st = $pdo->prepare("SELECT * FROM bookings WHERE id = :id");
$st->execute([':id' => $id]);
$booking = $st->fetch(PDO::FETCH_ASSOC);
if (!$booking) die('Booking not found');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
        $errors[] = "Invalid CSRF token";
    }

    $buyer_name = trim($_POST['buyer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $booking_date = trim($_POST['booking_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $amount_paid = trim($_POST['amount_paid'] ?? '0');
    $notes = trim($_POST['notes'] ?? '');

    if ($buyer_name === '') $errors[] = "Buyer name required";
    if ($phone === '') $errors[] = "Phone required";
    if ($status === '') $errors[] = "Status required";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Load current booking row FOR UPDATE
            $lock = $pdo->prepare("SELECT * FROM bookings WHERE id = :id FOR UPDATE");
            $lock->execute([':id' => $id]);
            $cur = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$cur) throw new Exception("Booking disappeared");

            $old_status = $cur['status'];
            $old_row = $cur;

            // Update booking row
            $upd = $pdo->prepare("UPDATE bookings SET buyer_name = :buyer_name, phone = :phone, booking_date = :booking_date, status = :status, amount_paid = :amount_paid, notes = :notes, updated_at = NOW() WHERE id = :id");
            $upd->execute([
                ':buyer_name' => $buyer_name,
                ':phone' => $phone,
                ':booking_date' => $booking_date ?: null,
                ':status' => $status,
                ':amount_paid' => $amount_paid ?: 0,
                ':notes' => $notes,
                ':id' => $id
            ]);

            // If status changed -> update plot status logic
            if ($old_status !== $status) {
                // If setting to booked -> set plot to booked
                if ($status === 'booked') {
                    $pupd = $pdo->prepare("UPDATE plots SET status = 'booked', updated_at = NOW() WHERE id = :pid");
                    $pupd->execute([':pid' => $cur['plot_id']]);
                } elseif ($status === 'cancelled') {
                    // If cancelling -> set plot available IF no other active bookings
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE plot_id = :pid AND id != :id AND status IN ('booked','partial')");
                    $chk->execute([':pid' => $cur['plot_id'], ':id' => $id]);
                    if ($chk->fetchColumn() == 0) {
                        $pupd = $pdo->prepare("UPDATE plots SET status = 'available', updated_at = NOW() WHERE id = :pid");
                        $pupd->execute([':pid' => $cur['plot_id']]);
                    }
                } elseif ($status === 'partial') {
                    // partial -> set plot to partial only if not booked by someone else
                    $chkBooked = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE plot_id = :pid AND status = 'booked'");
                    $chkBooked->execute([':pid' => $cur['plot_id']]);
                    if ($chkBooked->fetchColumn() == 0) {
                        $pupd = $pdo->prepare("UPDATE plots SET status = 'partial', updated_at = NOW() WHERE id = :pid");
                        $pupd->execute([':pid' => $cur['plot_id']]);
                    }
                }
            }

            // Insert booking_history
            $hist = $pdo->prepare("INSERT INTO booking_history (plot_id, from_status, to_status, changed_by, changed_at, reason, data_json) VALUES (:plot_id, :from_status, :to_status, :changed_by, NOW(), :reason, :data_json)");
            $data_json = json_encode(['old_row' => $old_row, 'new_values' => ['buyer_name' => $buyer_name, 'phone' => $phone, 'booking_date' => $booking_date, 'status' => $status, 'amount_paid' => $amount_paid, 'notes' => $notes]]);
            $hist->execute([
                ':plot_id' => $cur['plot_id'],
                ':from_status' => $old_status,
                ':to_status' => $status,
                ':changed_by' => $_SESSION['admin_user_id'] ?? null,
                ':reason' => 'booking_edit',
                ':data_json' => $data_json
            ]);

            $pdo->commit();
            header('Location: bookings-view.php?id=' . $id . '&msg=' . urlencode('Booking updated'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Update failed: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Edit Booking #<?= (int)$booking['id'] ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <a class="btn btn-secondary mb-3" href="bookings-view.php?id=<?= (int)$booking['id'] ?>">Back</a>
        <h2>Edit Booking #<?= (int)$booking['id'] ?></h2>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">

            <div class="form-group">
                <label>Buyer Name</label>
                <input name="buyer_name" class="form-control" value="<?= htmlspecialchars($_POST['buyer_name'] ?? $booking['buyer_name']) ?>">
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? $booking['phone']) ?>">
            </div>

            <div class="form-group">
                <label>Booking Date</label>
                <input type="datetime-local" name="booking_date" class="form-control" value="<?= htmlspecialchars(!empty($_POST['booking_date']) ? $_POST['booking_date'] : (strlen($booking['booking_date']) ? date('Y-m-d\TH:i', strtotime($booking['booking_date'])) : '')) ?>">
            </div>

            <div class="form-group">
                <label>Amount Paid</label>
                <input name="amount_paid" class="form-control" value="<?= htmlspecialchars($_POST['amount_paid'] ?? $booking['amount_paid']) ?>">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <?php $s = $_POST['status'] ?? $booking['status']; ?>
                    <option value="partial" <?= $s === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="booked" <?= $s === 'booked' ? 'selected' : '' ?>>Booked</option>
                    <option value="cancelled" <?= $s === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control"><?= htmlspecialchars($_POST['notes'] ?? $booking['notes']) ?></textarea>
            </div>

            <button class="btn btn-primary">Save</button>
            <a class="btn btn-secondary" href="bookings-view.php?id=<?= (int)$booking['id'] ?>">Cancel</a>
        </form>
    </div>
</body>

</html>