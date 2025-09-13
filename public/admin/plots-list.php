<?php
// public/admin/plots-list.php (UPDATED with bulk status update + pagination)
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// filters
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$block_id = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;

// CSRF + bulk update handler
$allowed_statuses = ['available', 'partial', 'booked'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
        die('Invalid CSRF token');
    }
    $statuses = $_POST['status'] ?? [];
    if (!is_array($statuses)) $statuses = [];

    // Filter and validate incoming IDs
    $updates = [];
    foreach ($statuses as $pid => $s) {
        $pid = (int)$pid;
        $s = trim($s);
        if ($pid > 0 && in_array($s, $allowed_statuses, true)) {
            $updates[$pid] = $s;
        }
    }

    if (!empty($updates)) {
        try {
            $pdo->beginTransaction();
            // fetch current statuses for these plots
            $in = implode(',', array_fill(0, count($updates), '?'));
            $stmt = $pdo->prepare("SELECT id, status FROM plots WHERE id IN ($in) FOR UPDATE");
            $stmt->execute(array_keys($updates));
            $currentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $currMap = [];
            foreach ($currentRows as $r) $currMap[(int)$r['id']] = $r['status'];

            $updateStmt = $pdo->prepare("UPDATE plots SET status = :status, updated_at = NOW() WHERE id = :id");
            $histStmt = $pdo->prepare("INSERT INTO booking_history (plot_id, from_status, to_status, changed_by, changed_at, reason, data_json) VALUES (:plot_id, :from_status, :to_status, :changed_by, NOW(), :reason, :data_json)");
            $cancelBookingsStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE plot_id = :pid AND status IN ('booked','partial')");

            foreach ($updates as $pid => $newStatus) {
                $from = $currMap[$pid] ?? null;
                if ($from === null) continue; // row not found
                if ($from === $newStatus) continue; // no change

                // Update plot status
                $updateStmt->execute([':status' => $newStatus, ':id' => $pid]);

                // If marking available -> cancel bookings
                if ($newStatus === 'available') {
                    $cancelBookingsStmt->execute([':pid' => $pid]);
                }
                // Note: If marking to partial/booked via bulk update, we DO NOT create bookings here
                // because buyer details are missing. Admin should open the individual plot to add booking info.

                // Write history
                $data_json = json_encode(['bulk_update' => true]);
                $histStmt->execute([
                    ':plot_id' => $pid,
                    ':from_status' => $from,
                    ':to_status' => $newStatus,
                    ':changed_by' => $_SESSION['admin_user_id'] ?? null,
                    ':reason' => 'bulk_status_update',
                    ':data_json' => $data_json
                ]);
            }

            $pdo->commit();
            // redirect back to list preserving filters & page
            $qs = [];
            if ($project_id) $qs['project_id'] = $project_id;
            if ($block_id) $qs['block_id'] = $block_id;
            $qs['page'] = $page;
            $redirect = 'plots-list.php' . (empty($qs) ? '' : '?' . http_build_query($qs));
            header('Location: ' . $redirect . '&msg=' . urlencode('Statuses updated'));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Bulk update failed: " . $e->getMessage();
        }
    } else {
        $error = "No valid updates provided.";
    }
}

// Fetch projects for filter dropdown
$projStmt = $pdo->query("SELECT id, name FROM projects ORDER BY name");
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch blocks for selected project (for dependent dropdown)
$blocks = [];
if ($project_id) {
    $bst = $pdo->prepare("SELECT id, name FROM blocks WHERE project_id = :pid ORDER BY sort_order, name");
    $bst->execute([':pid' => $project_id]);
    $blocks = $bst->fetchAll(PDO::FETCH_ASSOC);
}

// Build where clause for list & count
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

// Count total
$countSql = "SELECT COUNT(*) FROM plots pl JOIN blocks b ON b.id = pl.block_id JOIN projects p ON p.id = b.project_id " . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$total_pages = (int)ceil($total / $per_page);

// Fetch page of plots
$sql = "SELECT pl.*, b.name AS block_name, p.name AS project_name, b.id AS block_id, p.id AS project_id
        FROM plots pl
        JOIN blocks b ON b.id = pl.block_id
        JOIN projects p ON p.id = b.project_id
        $where
        ORDER BY p.name, b.sort_order, b.name, pl.plot_no
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$plots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for preserving querystring in links
function qs_preserve($extra = [])
{
    $q = $_GET;
    foreach ($extra as $k => $v) {
        $q[$k] = $v;
    }
    return '?' . http_build_query($q);
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Manage Plots</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-available {
            background: #dff0d8;
        }

        .status-partial {
            background: #fcf8e3;
        }

        .status-booked {
            background: #f2dede;
        }

        .small-select {
            width: 140px;
        }
    </style>
</head>

<body class="p-4">
    <div class="container">
        <div class="d-flex align-items-center mb-3">
            <h1 class="m-0">Manage Plots</h1>
            <a href="plots-save.php" class="btn btn-primary ml-auto">+ Add Plot</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['msg'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <form class="form-inline mb-3" method="get">
            <label class="mr-2">Project</label>
            <select name="project_id" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">-- All Projects --</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $project_id === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="mr-2 ml-3">Block</label>
            <select name="block_id" class="form-control mr-2" onchange="this.form.submit()">
                <option value="">-- All Blocks --</option>
                <?php foreach ($blocks as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $block_id === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <a class="btn btn-secondary ml-2" href="plots-list.php">Reset</a>
        </form>

        <!-- Bulk status form -->
        <form method="post">
            <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">
            <input type="hidden" name="bulk_update" value="1">

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Plot No</th>
                        <th>Project</th>
                        <th>Block</th>
                        <th>Size</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plots)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No plots found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($plots as $pl): ?>
                            <tr class="status-<?= htmlspecialchars($pl['status']) ?>">
                                <td><?= (int)$pl['id'] ?></td>
                                <td><?= htmlspecialchars($pl['plot_no']) ?></td>
                                <td><?= htmlspecialchars($pl['project_name']) ?></td>
                                <td><?= htmlspecialchars($pl['block_name']) ?></td>
                                <td><?= htmlspecialchars($pl['size']) ?></td>
                                <td><?= htmlspecialchars($pl['price']) ?></td>
                                <td>
                                    <select name="status[<?= (int)$pl['id'] ?>]" class="form-control small-select">
                                        <?php $cur = $pl['status']; ?>
                                        <option value="available" <?= $cur === 'available' ? 'selected' : '' ?>>Available</option>
                                        <option value="partial" <?= $cur === 'partial' ? 'selected' : '' ?>>Partial</option>
                                        <option value="booked" <?= $cur === 'booked' ? 'selected' : '' ?>>Booked</option>
                                    </select>
                                </td>
                                <td style="white-space:nowrap;">
                                    <a class="btn btn-sm btn-info" href="plots-save.php?id=<?= (int)$pl['id'] ?>">Edit</a>
                                    <form method="post" action="plots-delete.php" style="display:inline-block;margin:0 0 0 6px;" onsubmit="return confirm('Delete plot? This will be blocked if bookings exist.');">
                                        <input type="hidden" name="id" value="<?= (int)$pl['id'] ?>">
                                        <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">
                                        <button class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                    <a class="btn btn-sm btn-secondary" href="plots-grid.php?block_id=<?= (int)$pl['block_id'] ?>">Grid</a>
                                    <a class="btn btn-sm btn-warning" href="plots-change-status.php?id=<?= (int)$pl['id'] ?>">Status</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="d-flex align-items-center">
                <button class="btn btn-success" onclick="return confirm('Apply status changes to selected plots?')">Save Changes</button>
                <div class="ml-auto">
                    <!-- pagination links -->
                    <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination mb-0">
                                <?php
                                $qsbase = $_GET;
                                for ($i = 1; $i <= $total_pages; $i++):
                                    $qsbase['page'] = $i;
                                    $link = 'plots-list.php?' . http_build_query($qsbase);
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= $link ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</body>

</html>