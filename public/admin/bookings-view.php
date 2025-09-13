<?php
// public/admin/bookings-view.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die('Booking id required');

$sql = "SELECT bo.*, pl.plot_no, b.id AS block_id, b.name AS block_name, p.id AS project_id, p.name AS project_name, u.name AS created_by_name
        FROM bookings bo
        JOIN plots pl ON pl.id = bo.plot_id
        JOIN blocks b ON b.id = pl.block_id
        JOIN projects p ON p.id = b.project_id
        LEFT JOIN users u ON u.id = bo.created_by
        WHERE bo.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$bo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bo) die('Booking not found');

// Fetch booking_history for plot (most recent first), resolve name from users OR session
$hist = $pdo->prepare("
  SELECT bh.*, u.name AS changed_by_name
  FROM booking_history bh
  LEFT JOIN users u ON u.id = bh.changed_by
  WHERE bh.plot_id = :plot_id
  ORDER BY bh.changed_at DESC
");
$hist->execute([':plot_id' => $bo['plot_id']]);
$history = $hist->fetchAll(PDO::FETCH_ASSOC);

// Normalize changed_by_name: prefer user name from DB; if missing and changed_by matches current admin id, use session name.
// Fallback to 'id:<N>' or 'system' if changed_by is NULL/0
// Try common session keys to find admin's display name
$sessionName = null;
foreach (['admin_user_name', 'admin_name', 'user_name', 'name'] as $k) {
    if (!empty($_SESSION[$k])) {
        $sessionName = $_SESSION[$k];
        break;
    }
}
foreach ($history as &$h) {
    // if DB returned a name, keep it
    if (!empty($h['changed_by_name'])) continue;

    $changedBy = $h['changed_by'];
    if (!empty($changedBy) && isset($_SESSION['admin_user_id']) && $changedBy == $_SESSION['admin_user_id'] && $sessionName) {
        $h['changed_by_name'] = $sessionName; // current admin
    } elseif (!empty($changedBy)) {
        $h['changed_by_name'] = 'id:' . $changedBy;
    } else {
        $h['changed_by_name'] = 'system';
    }
}
unset($h); // good practice after reference loop


?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Booking #<?= (int)$bo['id'] ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <a class="btn btn-secondary mb-3" href="bookings-list.php">Back to list</a>
        <h2>Booking #<?= (int)$bo['id'] ?> — <?= htmlspecialchars($bo['buyer_name']) ?></h2>

        <div class="card mb-3">
            <div class="card-body">
                <h5>Plot</h5>
                <p><?= htmlspecialchars($bo['project_name']) ?> / <?= htmlspecialchars($bo['block_name']) ?> — <strong><?= htmlspecialchars($bo['plot_no']) ?></strong></p>

                <h5>Buyer</h5>
                <p><?= htmlspecialchars($bo['buyer_name']) ?> • <?= htmlspecialchars($bo['phone']) ?></p>

                <h5>Booking</h5>
                <p>Date: <?= htmlspecialchars($bo['booking_date']) ?></p>
                <p>Amount Paid: ₹<?= htmlspecialchars($bo['amount_paid']) ?></p>
                <p>Status: <strong><?= htmlspecialchars(ucfirst($bo['status'])) ?></strong></p>

                <h5>Notes</h5>
                <p><?= nl2br(htmlspecialchars($bo['notes'])) ?></p>

                <p class="text-muted">Created by: <?= htmlspecialchars($bo['created_by_name'] ?? '—') ?> at <?= htmlspecialchars($bo['created_at']) ?></p>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h5>Booking History (plot)</h5>
                <?php if (empty($history)): ?>
                    <p class="text-muted">No history found for this plot.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($history as $h): ?>
                            <li class="list-group-item">
                                <div><strong><?= htmlspecialchars($h['from_status']) ?></strong> → <strong><?= htmlspecialchars($h['to_status']) ?></strong>
                                    <span class="text-muted">by <?= htmlspecialchars($h['changed_by_name'] ?? '—') ?> at <?= htmlspecialchars($h['changed_at']) ?></span>
                                </div>
                                <?php if (!empty($h['reason'])): ?><div>Reason: <?= htmlspecialchars($h['reason']) ?></div><?php endif; ?>
                                <?php if (!empty($h['data_json'])): ?>
                                    <div class="small text-muted">Data: <?= htmlspecialchars($h['data_json']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <a class="btn btn-warning" href="bookings-edit.php?id=<?= (int)$bo['id'] ?>">Edit</a>

        <form method="post" action="bookings-delete.php" style="display:inline-block;margin-left:8px"
            onsubmit="return confirm('Cancel this booking? This will soft-cancel and may free the plot.')">
            <input type="hidden" name="id" value="<?= (int)$bo['id'] ?>">
            <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">
            <button class="btn btn-danger">Cancel Booking</button>
        </form>
    </div>
</body>

</html>