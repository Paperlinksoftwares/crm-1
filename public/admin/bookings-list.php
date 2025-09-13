<?php
// public/admin/bookings-list.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// filters
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$block_id   = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
$plot_id    = isset($_GET['plot_id']) ? (int)$_GET['plot_id'] : 0;
$status     = isset($_GET['status']) ? trim($_GET['status']) : '';
$from_date  = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date    = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// fetch projects for filter
$projStmt = $pdo->query("SELECT id, name FROM projects ORDER BY name");
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// blocks depends on project
$blocks = [];
if ($project_id) {
    $bst = $pdo->prepare("SELECT id, name FROM blocks WHERE project_id = :pid ORDER BY sort_order, name");
    $bst->execute([':pid' => $project_id]);
    $blocks = $bst->fetchAll(PDO::FETCH_ASSOC);
}

// plots depends on block
$plots = [];
if ($block_id) {
    $pst = $pdo->prepare("SELECT id, plot_no FROM plots WHERE block_id = :bid ORDER BY plot_no");
    $pst->execute([':bid' => $block_id]);
    $plots = $pst->fetchAll(PDO::FETCH_ASSOC);
}

// build where clause
$where = " WHERE 1=1 ";
$params = [];

if ($project_id) {
    $where .= " AND p.id = :project_id ";
    $params[':project_id'] = $project_id;
}
if ($block_id) {
    $where .= " AND b.id = :block_id ";
    $params[':block_id'] = $block_id;
}
if ($plot_id) {
    $where .= " AND bo.plot_id = :plot_id ";
    $params[':plot_id'] = $plot_id;
}
if ($status !== '') {
    $where .= " AND bo.status = :status ";
    $params[':status'] = $status;
}
if ($from_date !== '') {
    $where .= " AND bo.booking_date >= :from_date ";
    $params[':from_date'] = $from_date;
}
if ($to_date !== '') {
    $where .= " AND bo.booking_date <= :to_date ";
    $params[':to_date'] = $to_date;
}

// count total
$countSql = "SELECT COUNT(*) FROM bookings bo
             JOIN plots pl ON pl.id = bo.plot_id
             JOIN blocks b ON b.id = pl.block_id
             JOIN projects p ON p.id = b.project_id
             $where";
$cstmt = $pdo->prepare($countSql);
$cstmt->execute($params);
$total = (int)$cstmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// fetch page
$sql = "SELECT bo.*, pl.plot_no, pl.block_id, b.name AS block_name, p.id AS project_id, p.name AS project_name,
               u.name AS created_by_name
        FROM bookings bo
        JOIN plots pl ON pl.id = bo.plot_id
        JOIN blocks b ON b.id = pl.block_id
        JOIN projects p ON p.id = b.project_id
        LEFT JOIN users u ON u.id = bo.created_by
        $where
        ORDER BY bo.created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper to preserve qs
function preserve_qs($extra = [])
{
    $q = $_GET;
    foreach ($extra as $k => $v) $q[$k] = $v;
    return '?' . http_build_query($q);
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Bookings</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <div class="d-flex align-items-center mb-3">
            <h1 class="m-0">Bookings</h1>
            <a href="bookings-list.php" class="btn btn-secondary ml-auto">Refresh</a>
        </div>

        <form class="form-inline mb-3" method="get">
            <label class="mr-2">Project</label>
            <select name="project_id" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">All projects</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $project_id === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="mr-2 ml-3">Block</label>
            <select name="block_id" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">All blocks</option>
                <?php foreach ($blocks as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $block_id === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="mr-2 ml-3">Plot</label>
            <select name="plot_id" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">All plots</option>
                <?php foreach ($plots as $pl): ?>
                    <option value="<?= (int)$pl['id'] ?>" <?= $plot_id === (int)$pl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pl['plot_no']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="mr-2 ml-3">Status</label>
            <select name="status" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">Any</option>
                <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="booked" <?= $status === 'booked'  ? 'selected' : '' ?>>Booked</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>

            <div class="ml-3">
                <label class="mr-1">From</label>
                <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="form-control mr-2">
                <label class="mr-1">To</label>
                <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="form-control mr-2">
                <button class="btn btn-primary">Filter</button>
            </div>
        </form>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Plot</th>
                    <th>Project / Block</th>
                    <th>Buyer</th>
                    <th>Phone</th>
                    <th>Booking Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="10" class="text-center">No bookings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= htmlspecialchars($r['plot_no']) ?></td>
                            <td><?= htmlspecialchars($r['project_name'] . ' / ' . $r['block_name']) ?></td>
                            <td><?= htmlspecialchars($r['buyer_name']) ?></td>
                            <td><?= htmlspecialchars($r['phone']) ?></td>
                            <td><?= htmlspecialchars($r['booking_date']) ?></td>
                            <td>₹<?= htmlspecialchars($r['amount_paid']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($r['status'])) ?></td>
                            <td><?= htmlspecialchars($r['created_by_name'] ?? '—') ?></td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-sm btn-info" href="bookings-view.php?id=<?= (int)$r['id'] ?>">View</a>
                                <a class="btn btn-sm btn-warning" href="bookings-edit.php?id=<?= (int)$r['id'] ?>">Edit</a>

                                <form method="post" action="bookings-delete.php" style="display:inline-block;margin:0 0 0 6px;"
                                    onsubmit="return confirm('Cancel this booking? This will soft-cancel the booking and may free the plot.')">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">
                                    <button class="btn btn-sm btn-danger">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php
                    $qs = $_GET;
                    for ($i = 1; $i <= $total_pages; $i++):
                        $qs['page'] = $i;
                        $link = 'bookings-list.php?' . http_build_query($qs);
                    ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $link ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</body>

</html>