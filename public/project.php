<?php
session_start();
require_once __DIR__ . '/../app/db.php';
$project_id = (int)($_GET['id'] ?? 0);
if (!$project_id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
$stmt->execute([':id' => $project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) {
    echo "Project not found";
    exit;
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($project['name']) ?></title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-3">
    <div class="container">
        <a href="index.php" class="btn btn-link">&larr; Projects</a>
        <h1><?= htmlspecialchars($project['name']) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($project['location'] ?? '') ?></p>

        <!-- project map area -->
        <div class="row">
            <div class="col-md-8">
                <?php if (!empty($project['map_url'])): ?>
                    <img src="<?= htmlspecialchars('../' . ltrim($project['map_url'], '/')) ?>" class="img-fluid" alt="Project map">
                <?php else: ?>
                    <img src="<?= htmlspecialchars('../' . ltrim($project['image_url'] ?? '', '/')) ?>" class="img-fluid" alt="Project map">
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>Check availability</h5>
                        <p>To see blocks and book plots, click below to continue.</p>

                        <!-- Link to protected plots page. plots-grid.php will require login -->
                        <a href="plots-grid.php?project_id=<?= urlencode($project['id']) ?>" class="btn btn-success btn-lg w-100">Check Availability</a>

                        <hr>
                        <small class="text-muted">Already an admin? <a href="admin-login.php">Admin login</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>