<?php
require __DIR__ . '/../app/db.php';

// Fetch projects with total plots
$sql = "
SELECT p.id, p.name, p.location, p.image_url, 
       COALESCE(COUNT(pl.id),0) AS total_plots
FROM projects p
LEFT JOIN blocks b ON b.project_id = p.id
LEFT JOIN plots pl ON pl.block_id = b.id
GROUP BY p.id
ORDER BY p.created_at DESC
";
$stmt = $pdo->query($sql);
$projects = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Projects - Realestate CRM</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .project-card {
            min-height: 260px;
        }

        .legend {
            margin-top: 12px;
        }

        .legend .dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
        }

        .dot-available {
            background: #28a745;
        }

        /* green */
        .dot-partial {
            background: #ffc107;
        }

        /* yellow */
        .dot-booked {
            background: #dc3545;
        }

        /* red */
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3">Projects</h1>
            <div>
                <a href="/realestate/public/admin-login.php" class="btn btn-outline-primary btn-sm">Admin Login</a>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($projects as $p): ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="card project-card h-100">
                        <?php if (!empty($p['image_url']) && file_exists(__DIR__ . '/../' . $p['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars('../' . $p['image_url']); ?>" class="card-img-top" alt="">
                        <?php else: ?>
                            <svg class="bd-placeholder-img card-img-top" width="100%" height="140" xmlns="http://www.w3.org/2000/svg" role="img">
                                <rect width="100%" height="100%" fill="#e9ecef"></rect>
                                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#6c757d">No image</text>
                            </svg>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($p['name']); ?></h5>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($p['location']); ?></p>
                            <p class="mb-2">Plots: <strong><?php echo (int)$p['total_plots']; ?></strong></p>
                            <a href="project.php?id=<?php echo $p['id']; ?>" class="btn btn-primary mt-auto">View project</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="legend text-muted small mt-4">
            <span class="dot dot-available"></span> Available &nbsp;&nbsp;
            <span class="dot dot-partial"></span> Partial &nbsp;&nbsp;
            <span class="dot dot-booked"></span> Booked
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>