<?php
// public/admin/blocks-save.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

// load projects for dropdown
$projStmt = $pdo->query("SELECT id, name FROM projects ORDER BY name ASC");
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$block = ['project_id' => '', 'name' => '', 'sort_order' => 0];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM blocks WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $block = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$block) die('Block not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
        $errors[] = "Invalid token";
    }

    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($project_id <= 0) $errors[] = "Please choose a project";
    if ($name === '') $errors[] = "Block name is required";

    // Optional: verify project exists
    if (empty($errors)) {
        $pchk = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE id = :pid");
        $pchk->execute([':pid' => $project_id]);
        if ($pchk->fetchColumn() == 0) $errors[] = "Selected project does not exist";
    }

    if (empty($errors)) {
        if ($id) {
            $sql = "UPDATE blocks SET project_id = :project_id, name = :name, sort_order = :sort_order, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':project_id' => $project_id,
                ':name' => $name,
                ':sort_order' => $sort_order,
                ':id' => $id
            ]);
            header('Location: blocks-list.php?msg=' . urlencode('Block updated'));
            exit;
        } else {
            $sql = "INSERT INTO blocks (project_id, name, sort_order, created_at, updated_at) VALUES (:project_id, :name, :sort_order, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':project_id' => $project_id,
                ':name' => $name,
                ':sort_order' => $sort_order
            ]);
            header('Location: blocks-list.php?msg=' . urlencode('Block created'));
            exit;
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title><?= $id ? 'Edit' : 'Add' ?> Block</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <h1><?= $id ? 'Edit' : 'Add' ?> Block</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="token" value="<?= $_SESSION['admin_csrf'] ?? '' ?>">

            <div class="form-group">
                <label>Project</label>
                <select name="project_id" class="form-control">
                    <option value="">-- Select Project --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"
                            <?= ((isset($_POST['project_id']) ? (int)$_POST['project_id'] : (int)$block['project_id']) === (int)$p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Block Name</label>
                <input name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? $block['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Sort Order (integer)</label>
                <input type="number" name="sort_order" class="form-control" value="<?= htmlspecialchars($_POST['sort_order'] ?? $block['sort_order'] ?? 0) ?>">
                <small class="form-text text-muted">Lower numbers appear first in lists.</small>
            </div>

            <button class="btn btn-primary"><?= $id ? 'Update' : 'Create' ?></button>
            <a class="btn btn-secondary" href="blocks-list.php">Back</a>
        </form>
    </div>
</body>

</html>