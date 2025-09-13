<?php
// public/admin/plots-save.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

// fetch projects
$projStmt = $pdo->query("SELECT id, name FROM projects ORDER BY name");
$projects = $projStmt->fetchAll(PDO::FETCH_ASSOC);

// determine selected project (via GET or POST)
$selected_project_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
} else {
    // if editing, infer project via block
    if ($id) {
        $r = $pdo->prepare("SELECT pl.*, b.project_id FROM plots pl JOIN blocks b ON b.id = pl.block_id WHERE pl.id = :id");
        $r->execute([':id' => $id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $selected_project_id = (int)$row['project_id'];
        }
    } else {
        $selected_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    }
}

// fetch blocks for project dropdown
$blocks = [];
if ($selected_project_id) {
    $bst = $pdo->prepare("SELECT id, name FROM blocks WHERE project_id = :pid ORDER BY sort_order, name");
    $bst->execute([':pid' => $selected_project_id]);
    $blocks = $bst->fetchAll(PDO::FETCH_ASSOC);
}

// load existing plot if editing
$plot = ['block_id' => '', 'plot_no' => '', 'size' => '', 'price' => '', 'status' => 'available', 'notes' => ''];
if ($id && empty($_POST)) {
    $st = $pdo->prepare("SELECT pl.*, b.project_id FROM plots pl JOIN blocks b ON b.id = pl.block_id WHERE pl.id = :id");
    $st->execute([':id' => $id]);
    $plot = $st->fetch(PDO::FETCH_ASSOC);
    if (!$plot) die('Plot not found');
    $selected_project_id = (int)$plot['project_id'];
    // refresh blocks
    $bst = $pdo->prepare("SELECT id, name FROM blocks WHERE project_id = :pid ORDER BY sort_order, name");
    $bst->execute([':pid' => $selected_project_id]);
    $blocks = $bst->fetchAll(PDO::FETCH_ASSOC);
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_admin_csrf($_POST['token'] ?? null)) {
        $errors[] = "Invalid token";
    }

    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $block_id   = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
    $plot_no    = trim($_POST['plot_no'] ?? '');
    $size       = trim($_POST['size'] ?? '');
    $price      = trim($_POST['price'] ?? '');
    $status     = trim($_POST['status'] ?? 'available');
    $notes      = trim($_POST['notes'] ?? '');

    if ($project_id <= 0) $errors[] = "Please select project";
    if ($block_id <= 0) $errors[] = "Please select block";
    if ($plot_no === '') $errors[] = "Plot number is required";

    // verify block belongs to project
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE id = :bid AND project_id = :pid");
        $chk->execute([':bid' => $block_id, ':pid' => $project_id]);
        if ($chk->fetchColumn() == 0) $errors[] = "Selected block does not belong to chosen project";
    }

    if (empty($errors)) {
        if ($id) {
            $sql = "UPDATE plots SET block_id = :block_id, plot_no = :plot_no, size = :size, price = :price, status = :status, notes = :notes, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':block_id' => $block_id,
                ':plot_no'  => $plot_no,
                ':size'     => $size,
                ':price'    => $price,
                ':status'   => $status,
                ':notes'    => $notes,
                ':id'       => $id
            ]);
            header('Location: plots-list.php?msg=' . urlencode('Plot updated'));
            exit;
        } else {
            $sql = "INSERT INTO plots (block_id, plot_no, size, price, status, notes, created_at, updated_at) VALUES (:block_id, :plot_no, :size, :price, :status, :notes, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':block_id' => $block_id,
                ':plot_no'  => $plot_no,
                ':size'     => $size,
                ':price'    => $price,
                ':status'   => $status,
                ':notes'    => $notes
            ]);
            header('Location: plots-list.php?msg=' . urlencode('Plot created'));
            exit;
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title><?= $id ? 'Edit' : 'Add' ?> Plot</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <h1><?= $id ? 'Edit' : 'Add' ?> Plot</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">

            <div class="form-group">
                <label>Project</label>
                <select name="project_id" class="form-control" onchange="location.href='plots-save.php?id=<?= $id ?>&project_id='+this.value">
                    <option value="">-- Select Project --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ($selected_project_id === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Selecting a project will reload to populate blocks.</small>
            </div>

            <div class="form-group">
                <label>Block</label>
                <select name="block_id" class="form-control">
                    <option value="">-- Select Block --</option>
                    <?php foreach ($blocks as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= ((int)($_POST['block_id'] ?? $plot['block_id']) === (int)$b['id']) ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Plot No</label>
                <input name="plot_no" class="form-control" value="<?= htmlspecialchars($_POST['plot_no'] ?? $plot['plot_no'] ?? '') ?>">
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Size</label>
                    <input type="text" name="size" class="form-control" value="<?= htmlspecialchars($_POST['size'] ?? $plot['size'] ?? '') ?>">
                </div>

                <div class="form-group col-md-4">
                    <label>Price</label>
                    <input name="price" class="form-control" value="<?= htmlspecialchars($_POST['price'] ?? $plot['price'] ?? '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <?php $s = $_POST['status'] ?? $plot['status'] ?? 'available'; ?>
                        <option value="available" <?= $s === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="partial" <?= $s === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="booked" <?= $s === 'booked' ? 'selected' : '' ?>>Booked</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control"><?= htmlspecialchars($_POST['notes'] ?? $plot['notes'] ?? '') ?></textarea>
            </div>

            <button class="btn btn-primary"><?= $id ? 'Update' : 'Create' ?></button>
            <a class="btn btn-secondary" href="plots-list.php">Back</a>
        </form>
    </div>
</body>

</html>