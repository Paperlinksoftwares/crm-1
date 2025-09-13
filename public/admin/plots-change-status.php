<?php
// public/admin/plots-change-status.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if (!$id) die('Invalid plot id');

// fetch plot with block+project info
$sql = "SELECT pl.*, b.name AS block_name, b.project_id, p.name AS project_name
        FROM plots pl
        JOIN blocks b ON b.id = pl.block_id
        JOIN projects p ON p.id = b.project_id
        WHERE pl.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$plot = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plot) die('Plot not found');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
        $errors[] = "Invalid token";
    }

    $new_status = $_POST['status'] ?? '';
    $buyer_name = trim($_POST['buyer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $booking_date = trim($_POST['booking_date'] ?? '');
    $amount_paid = trim($_POST['amount_paid'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!in_array($new_status, ['available', 'partial', 'booked'])) $errors[] = "Invalid status";

    // If setting to booked or partial, require buyer details
    if (in_array($new_status, ['partial', 'booked'])) {
        if ($buyer_name === '') $errors[] = "Buyer name required for booking";
        if ($phone === '') $errors[] = "Buyer phone required for booking";
        if ($booking_date === '') $booking_date = date('Y-m-d H:i:s');
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // lock the plot row to prevent race conditions
            $lockStmt = $pdo->prepare("SELECT status FROM plots WHERE id = :id FOR UPDATE");
            $lockStmt->execute([':id' => $id]);
            $current = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new Exception("Plot not found (during lock)");

            $from_status = $current['status'];

            // If trying to book but already booked -> fail
            if (in_array($new_status, ['partial', 'booked']) && $from_status === 'booked') {
                throw new Exception("Cannot change: plot already booked");
            }

            // Update plot status
            $upd = $pdo->prepare("UPDATE plots SET status = :status, updated_at = NOW() WHERE id = :id");
            $upd->execute([':status' => $new_status, ':id' => $id]);

            // If booking or partial: create a booking record
            $booking_id = null;
            if (in_array($new_status, ['partial', 'booked'])) {
                $ins = $pdo->prepare("INSERT INTO bookings (plot_id, user_id, buyer_name, phone, booking_date, status, amount_paid, notes, created_by, created_at, updated_at)
                              VALUES (:plot_id, NULL, :buyer_name, :phone, :booking_date, :status, :amount_paid, :notes, :created_by, NOW(), NOW())");
                $ins->execute([
                    ':plot_id' => $id,
                    ':buyer_name' => $buyer_name,
                    ':phone' => $phone,
                    ':booking_date' => $booking_date,
                    ':status' => $new_status,
                    ':amount_paid' => $amount_paid ?: 0,
                    ':notes' => $notes,
                    ':created_by' => $_SESSION['admin_user_id'] ?? null
                ]);
                $booking_id = $pdo->lastInsertId();
            } else {
                // marking available: mark existing bookings as cancelled (soft)
                $cancel = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE plot_id = :pid AND status IN ('booked','partial')");
                $cancel->execute([':pid' => $id]);
            }

            // write booking_history
            $hist = $pdo->prepare("INSERT INTO booking_history (plot_id, from_status, to_status, changed_by, changed_at, reason, data_json)
                             VALUES (:plot_id, :from_status, :to_status, :changed_by, NOW(), :reason, :data_json)");
            $data_json = json_encode([
                'booking_id' => $booking_id,
                'buyer_name' => $buyer_name,
                'phone' => $phone,
                'amount_paid' => $amount_paid,
                'notes' => $notes
            ]);
            $hist->execute([
                ':plot_id' => $id,
                ':from_status' => $from_status,
                ':to_status' => $new_status,
                ':changed_by' => $_SESSION['admin_user_id'] ?? null,
                ':reason' => null,
                ':data_json' => $data_json
            ]);

            $pdo->commit();
            header('Location: plots-list.php?msg=' . urlencode('Status changed'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Status change failed: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Change Plot Status</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <h1>Change Status — Plot <?= htmlspecialchars($plot['plot_no']) ?> (<?= htmlspecialchars($plot['project_name']) ?> / <?= htmlspecialchars($plot['block_name']) ?>)</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">

            <div class="form-group">
                <label>Current Status</label>
                <input class="form-control" readonly value="<?= htmlspecialchars($plot['status']) ?>">
            </div>

            <div class="form-group">
                <label>New Status</label>
                <select name="status" class="form-control" required onchange="document.getElementById('booking-data').style.display=(this.value=='booked'||this.value=='partial')?'block':'none'">
                    <option value="available">Available</option>
                    <option value="partial">Partial</option>
                    <option value="booked">Booked</option>
                </select>
            </div>

            <div id="booking-data" style="display:none; border:1px solid #eee; padding:12px; margin-bottom:12px;">
                <h5>Buyer details (required for Partial/Booked)</h5>
                <div class="form-group">
                    <label>Buyer Name</label>
                    <input name="buyer_name" class="form-control" value="<?= htmlspecialchars($_POST['buyer_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Booking Date</label>
                    <input type="datetime-local" name="booking_date" class="form-control" value="<?= htmlspecialchars($_POST['booking_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Amount Paid (optional)</label>
                    <input name="amount_paid" class="form-control" value="<?= htmlspecialchars($_POST['amount_paid'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <button class="btn btn-primary">Change Status</button>
            <a class="btn btn-secondary" href="plots-list.php">Back</a>
        </form>
    </div>

    <script>
        // if POST had booking fields, show booking section on load
        (function() {
            var hasPost = <?= !empty($_POST) ? 'true' : 'false' ?>;
            if (hasPost && (<?= json_encode($_POST['status'] ?? '') ?> === 'partial' || <?= json_encode($_POST['status'] ?? '') ?> === 'booked')) {
                document.getElementById('booking-data').style.display = 'block';
            }
        })();
    </script>
</body>

</html>