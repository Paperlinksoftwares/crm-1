<?php
// public/admin/projects-save.php
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';

// Ensure uploads dir exists
$uploadDir = __DIR__ . '/../../uploads/projects/';
$publicUploadBase = '/uploads/projects/'; // public path used in DB image_url
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// initialize
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$project = [
    'name' => '',
    'description' => '',
    'location' => '',
    'image_url' => ''
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$project) {
        die('Project not found');
    }
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // simple CSRF check (optional)
    if (empty($_POST['token']) || ($_POST['token'] !== ($_SESSION['admin_csrf'] ?? ''))) {
        $errors[] = "Invalid token";
    }

    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') $errors[] = "Project name is required";

    // handle image upload if provided
    $new_image_url = $project['image_url'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $f = $_FILES['image'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
            $safeExt = preg_replace('/[^a-z0-9]/i', '', $ext);
            $filename = uniqid('proj_', true) . '.' . ($safeExt ?: 'jpg');
            $target = $uploadDir . $filename;
            if (move_uploaded_file($f['tmp_name'], $target)) {
                $new_image_url = $publicUploadBase . $filename;
                // optional: remove old file if replacing
                if (!empty($project['image_url'])) {
                    $old = __DIR__ . '/../../' . ltrim($project['image_url'], '/');
                    if (is_file($old)) @unlink($old);
                }
            } else {
                $errors[] = "Failed to move uploaded file.";
            }
        } else {
            $errors[] = "Image upload error code: " . (int)$f['error'];
        }
    }

    if (empty($errors)) {
        if ($id) {
            // update
            $sql = "UPDATE projects SET name = :name, description = :description, location = :location, image_url = :image_url, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':location' => $location,
                ':image_url' => $new_image_url,
                ':id' => $id
            ]);
            header('Location: projects-list.php?msg=' . urlencode('Project updated'));
            exit;
        } else {
            // insert
            $sql = "INSERT INTO projects (name, description, location, image_url, created_at, updated_at) VALUES (:name, :description, :location, :image_url, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':location' => $location,
                ':image_url' => $new_image_url
            ]);
            header('Location: projects-list.php?msg=' . urlencode('Project created'));
            exit;
        }
    }
}

// render form (either GET or POST with errors)
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title><?= $id ? 'Edit' : 'Add' ?> Project</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <div class="container">
        <h1><?= $id ? 'Edit' : 'Add' ?> Project</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e) echo '<div>' . htmlspecialchars($e) . '</div>'; ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <div class="form-group">
                <label>Name</label>
                <input name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? $project['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Location</label>
                <input name="location" class="form-control" value="<?= htmlspecialchars($_POST['location'] ?? $project['location'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($_POST['description'] ?? $project['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Image (optional)</label>
                <?php if (!empty($project['image_url'])): ?>
                    <div class="mb-2">
                        <img src="<?= htmlspecialchars($project['image_url']) ?>" style="max-width:200px;max-height:120px;">
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*" class="form-control-file">
                <small class="form-text text-muted">If you upload a new image it will replace the old one.</small>
            </div>

            <button class="btn btn-primary"><?= $id ? 'Update' : 'Create' ?></button>
            <a class="btn btn-secondary" href="projects-list.php">Back</a>
        </form>
    </div>
</body>

</html>