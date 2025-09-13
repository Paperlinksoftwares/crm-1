<?php
// public/admin/projects-list.php
require_once __DIR__ . '/../../app/admin_auth.php'; // enforces admin login
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

// Fetch projects
$stmt = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Manage Projects</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <div class="d-flex align-items-center mb-3">
            <h1 class="m-0">Manage Projects</h1>
            <a href="projects-save.php" class="btn btn-primary ml-auto">+ Add Project</a>
        </div>

        <?php if (!empty($_GET['msg'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Image</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No projects found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['location']) ?></td>
                            <td style="width:120px;">
                                <?php if (!empty($p['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="" style="max-width:100px;max-height:60px;">
                                <?php else: ?>
                                    <small class="text-muted">No image</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['created_at']) ?></td>
                            <td style="white-space:nowrap;">
                                <a class="btn btn-sm btn-info" href="projects-save.php?id=<?= (int)$p['id'] ?>">Edit</a>

                                <!-- Delete form (POST) -->
                                <form method="post" action="projects-delete.php" style="display:inline-block;margin:0 0 0 6px;"
                                    onsubmit="return confirm('Delete project? This will be blocked if it has blocks/plots.');">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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