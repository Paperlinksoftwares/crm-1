<?php
// Admin dashboard requires authentication and shared helpers
require_once __DIR__ . '/../../app/admin_auth.php';
require_once __DIR__ . '/../../app/csrf.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/config.php';
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Admin</title>

    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h3>Admin Dashboard</h3>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? ''); ?> — Role: <?php echo htmlspecialchars($_SESSION['admin_role'] ?? ''); ?></p>
        <ul>
            <li><a href="projects-list.php">Manage Projects</a></li>
            <li><a href="blocks-list.php">Manage Blocks</a></li>
            <li><a href="plots-list.php">Manage Plots</a></li>
            <li><a href="bookings-list.php">Bookings</a></li>
            <li><a href="/realestate/public/admin-logout.php">Logout</a></li>
        </ul>
    </div>
</body>

</html>