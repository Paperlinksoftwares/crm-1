<?php
// public/admin/blocks-list.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

// Fetch blocks with project name
$sql = "SELECT b.*, p.name AS project_name
        FROM blocks b
        LEFT JOIN projects p ON p.id = b.project_id
        ORDER BY p.name, b.sort_order, b.name";
$stmt = $pdo->query($sql);
$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Manage Blocks</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <div class="d-flex align-items-center mb-3">
            <h1 class="m-0">Manage Blocks</h1>
            <a href="blocks-save.php" class="btn btn-primary ml-auto">+ Add Block</a>
        </div>

        <?php if (!empty($_GET['msg'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Block Name</th>
                    <th>Project</th>
                    <th>Sort Order</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blocks)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No blocks found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($blocks as $b): ?>
                        <tr>
                            <td><?= (int)$b['id'] ?></td>
                            <td><?= htmlspecialchars($b['name']) ?></td>
                            <td><?= htmlspecialchars($b['project_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($b['sort_order']) ?></td>
                            <td><?= htmlspecialchars($b['created_at']) ?></td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-sm btn-info" href="blocks-save.php?id=<?= (int)$b['id'] ?>">Edit</a>

                                <form method="post" action="blocks-delete.php" style="display:inline-block;margin:0 0 0 6px;"
                                    onsubmit="return confirm('Delete block? This will be blocked if it contains plots.');">
                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                    <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>