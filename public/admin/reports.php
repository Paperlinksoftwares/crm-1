<?php
require __DIR__ . '/../../app/admin_auth.php';
require __DIR__ . '/../../app/db.php';

$project_id = (int)($_GET['project_id'] ?? 0);
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$projects = $pdo->query("SELECT id,name FROM projects ORDER BY name")->fetchAll();

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
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h4>Booking Reports</h4>
        <form class="row g-2 align-items-end" method="get">
            <div class="col-auto">
                <label class="form-label">Project</label>
                <select name="project_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php if ($project_id == $p['id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>"></div>
            <div class="col-auto"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>"></div>
            <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
            <div class="col-auto">
                <a class="btn btn-outline-success" href="reports-export.php?project_id=<?php echo $project_id; ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">Export CSV</a>
            </div>
        </form>

        <table class="table table-sm table-striped mt-3">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project</th>
                    <th>Block</th>
                    <th>Plot</th>
                    <th>Buyer</th>
                    <th>Phone</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo $r['booking_id']; ?></td>
                        <td><?php echo htmlspecialchars($r['project']); ?></td>
                        <td><?php echo htmlspecialchars($r['block']); ?></td>
                        <td><?php echo htmlspecialchars($r['plot_no']); ?></td>
                        <td><?php echo htmlspecialchars($r['buyer_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['phone']); ?></td>
                        <td><?php echo htmlspecialchars($r['booking_date']); ?></td>
                        <td><?php echo htmlspecialchars($r['status']); ?></td>
                        <td><?php echo htmlspecialchars($r['amount_paid']); ?></td>
                        <td><?php echo htmlspecialchars($r['created_by']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>