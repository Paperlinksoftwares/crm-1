<?php
// public/tv.php
require __DIR__ . '/../app/db.php';

$project_id = (int)($_GET['project_id'] ?? 0);
if (!$project_id) {
    echo "Project ID required, e.g. ?project_id=1";
    exit;
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>TV Board</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #111;
            color: #fff;
        }

        .tv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .tile {
            padding: 18px;
            border-radius: 12px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
        }

        .available {
            background: #1b5e20;
            color: #dfffe1;
        }

        .partial {
            background: #8a6d00;
            color: #fff7d6;
        }

        .booked {
            background: #7b0016;
            color: #ffd6dc;
        }

        .legend {
            position: fixed;
            top: 12px;
            right: 18px;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center py-3">
            <h2 id="projectName">Project Board</h2>
            <div class="legend small">
                <div><span style="display:inline-block;width:12px;height:12px;background:#1b5e20;margin-right:6px;border-radius:3px;"></span> Available</div>
                <div><span style="display:inline-block;width:12px;height:12px;background:#8a6d00;margin-right:6px;border-radius:3px;"></span> Partial</div>
                <div><span style="display:inline-block;width:12px;height:12px;background:#7b0016;margin-right:6px;border-radius:3px;"></span> Booked</div>
            </div>
        </div>

        <div id="tvGrid" class="tv-grid"></div>
    </div>

    <script>
        const projectId = <?php echo $project_id; ?>;
        async function refresh() {
            try {
                const res = await fetch('tv-refresh.php?project_id=' + projectId);
                const js = await res.json();
                if (js.error) {
                    console.error(js.error);
                    return;
                }
                const grid = document.getElementById('tvGrid');
                grid.innerHTML = '';
                js.plots.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'tile ' + (p.status === 'available' ? 'available' : (p.status === 'partial' ? 'partial' : 'booked'));
                    div.innerHTML = `<div style="font-size:1.2rem">${p.plot_no}</div><div style="font-size:0.8rem">${p.block_name}</div>`;
                    grid.appendChild(div);
                });
            } catch (e) {
                console.error(e);
            }
        }
        // first load + interval
        refresh();
        setInterval(refresh, 15000); // refresh every 15s
    </script>
</body>

</html>