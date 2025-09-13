<?php
session_start();
require_once __DIR__ . '/../app/db.php';

// After login, redirect to admin dashboard unless a specific return path is provided
$return = $_GET['return'] ?? 'admin/dashboard.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = "Enter email and password";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, password_hash, name, role FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $user['id'];
            $_SESSION['admin_name'] = $user['name'];
            $_SESSION['admin_role'] = $user['role'] ?? 'user';
            // also expose generic user_* session variables so login persists across the site
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';

            // safety: allow only internal paths within this application
            $returnUrl = (strpos($return, '/') === 0 || strpos($return, 'admin/') === 0)
                ? $return
                : 'admin/dashboard.php';
            header('Location: ' . $returnUrl);
            exit;
        } else {
            $errors[] = "Invalid credentials";
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Login</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-3">
    <div class="container col-md-4">
        <h3>Login</h3>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= htmlspecialchars(implode(', ', $errors)) ?></div>
        <?php endif; ?>
        <form method="post" action="?return=<?= urlencode($return) ?>">
            <div class="mb-2">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
            </div>
            <div class="mb-2">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Login</button>
        </form>
        <p class="mt-2 small">No account? <a href="/public/register.php?return=<?= urlencode($return) ?>">Register</a></p>
    </div>
</body>

</html>