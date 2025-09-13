<?php
// public/plots-grid.php  — updated
session_start();
require_once __DIR__ . '/../app/db.php';

$project_id = (int)($_GET['project_id'] ?? 0);
if (!$project_id) {
    header('Location: /public/index.php');
    exit;
}

// require login: redirect with return
if (empty($_SESSION['user_id'])) {
    $cur = '/public/plots-grid.php?project_id=' . urlencode($project_id);
    header('Location: /public/login.php?return=' . urlencode($cur));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

// load project
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
$stmt->execute([':id' => $project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) {
    echo "Project not found";
    exit;
}

// load blocks
$stmt = $pdo->prepare("SELECT id, name FROM blocks WHERE project_id = :pid ORDER BY sort_order, id");
$stmt->execute([':pid' => $project_id]);
$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// for each block load plots
$blocks_with_plots = [];
$plotsStmt = $pdo->prepare("SELECT id, plot_no, size, price, status FROM plots WHERE block_id = :bid ORDER BY plot_no");
foreach ($blocks as $b) {
    $plotsStmt->execute([':bid' => $b['id']]);
    $b['plots'] = $plotsStmt->fetchAll(PDO::FETCH_ASSOC);
    $blocks_with_plots[] = $b;
}

$tv_mode = isset($_GET['tv']) && $_GET['tv'] == '1';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($project['name']) ?> — Plots</title>

    <!-- bootstrap -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- inline styles specific to grid (keeps everything in one file) -->
    <style>
        :root {
            --tile-size: 110px;
            /* base tile size, scales with viewport */
            --tile-radius: 12px;
            --available-bg: #dff0d8;
            --partial-bg: #fcf8e3;
            --booked-bg: #f2dede;
            --tile-border: #e2e2e2;
            --tile-shadow: rgba(0, 0, 0, 0.06);
        }

        body {
            background: #f8f9fa;
        }

        /* container adjustments for TV mode */
        .tv-mode body,
        .tv-mode .container {
            font-size: 1.25rem;
        }

        /* Card */
        .block-card {
            margin-bottom: 1.25rem;
        }

        .block-card .card-header {
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* grid row */
        .plot-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: stretch;
        }

        /* tile */
        .plot-tile {
            width: var(--tile-size);
            min-height: var(--tile-size);
            background: #fff;
            border: 1px solid var(--tile-border);
            border-radius: var(--tile-radius);
            box-shadow: 0 2px 6px var(--tile-shadow);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px;
            text-align: center;
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease;
            user-select: none;
        }

        .plot-tile:focus {
            outline: 3px solid #9ecb9e;
            outline-offset: 2px;
        }

        .plot-tile:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
        }

        .plot-no {
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 4px;
        }

        .plot-meta {
            font-size: 0.85rem;
            color: #222;
            opacity: 0.85;
        }

        .plot-available {
            background: var(--available-bg);
        }

        .plot-partial {
            background: var(--partial-bg);
        }

        .plot-booked {
            background: var(--booked-bg);
        }

        /* little status pill */
        .status-pill {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 0.72rem;
            margin-top: 6px;
        }

        .status-available-pill {
            background: rgba(0, 128, 0, 0.12);
            color: #167200;
        }

        .status-partial-pill {
            background: rgba(255, 165, 0, 0.12);
            color: #8a5b00;
        }

        .status-booked-pill {
            background: rgba(255, 0, 0, 0.08);
            color: #8a1b1b;
        }

        /* legend pinned */
        .legend {
            position: sticky;
            top: 12px;
            background: #fff;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #eee;
            display: inline-flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
            z-index: 2;
        }

        .legend-item {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 0.95rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .legend-available {
            background: var(--available-bg);
        }

        .legend-partial {
            background: var(--partial-bg);
        }

        .legend-booked {
            background: var(--booked-bg);
        }

        /* responsive: smaller tiles on small screens */
        @media (max-width: 768px) {
            :root {
                --tile-size: 86px;
            }
        }

        @media (max-width: 420px) {
            :root {
                --tile-size: 74px;
            }
        }

        /* TV mode: big tiles and hide interactive cursor */
        .tv-mode .plot-tile {
            width: 160px;
            min-height: 160px;
            cursor: default;
            transform: none !important;
        }

        .tv-mode .plot-tile:hover {
            transform: none;
            box-shadow: none;
        }

        .tv-mode .legend {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        /* small accessible note */
        .sr-only {
            position: absolute !important;
            height: 1px;
            width: 1px;
            overflow: hidden;
            clip: rect(1px, 1px, 1px, 1px);
            white-space: nowrap;
        }
    </style>
</head>

<body class="<?= $tv_mode ? 'tv-mode' : '' ?> p-3">
    <div class="container">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <a href="project.php?id=<?= urlencode($project_id) ?>" class="btn btn-link">&larr; Back to project</a>
                <h2 class="d-inline-block ml-2"><?= htmlspecialchars($project['name']) ?> — Blocks & Plots</h2>
                <div class="sr-only" id="project-id"><?= (int)$project_id ?></div>
                <div class="sr-only" id="csrf-token"><?= htmlspecialchars($csrf_token) ?></div>
            </div>

            <div>
                <!-- Legend -->
                <div class="legend" role="region" aria-label="Plot legend">
                    <div class="legend-item"><span class="legend-color legend-available" aria-hidden="true"></span><span>Available</span></div>
                    <div class="legend-item"><span class="legend-color legend-partial" aria-hidden="true"></span><span>Partial</span></div>
                    <div class="legend-item"><span class="legend-color legend-booked" aria-hidden="true"></span><span>Booked</span></div>
                    <?php if ($tv_mode): ?>
                        <div class="legend-item"><small class="text-muted">TV mode — read only</small></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <p class="mb-3">Hello, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?> — you are logged in.</p>

        <?php if (empty($blocks_with_plots)): ?>
            <div class="alert alert-info">No blocks found for this project.</div>
        <?php endif; ?>

        <?php foreach ($blocks_with_plots as $block): ?>
            <div class="card block-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><?= htmlspecialchars($block['name']) ?></strong>
                    <small class="text-muted"><?= count($block['plots']) ?> plots</small>
                </div>

                <div class="card-body">
                    <div class="plot-grid" aria-label="Plots for <?= htmlspecialchars($block['name']) ?>">
                        <?php foreach ($block['plots'] as $plot):
                            $status = $plot['status'] ?? 'available';
                            $cls = $status === 'available' ? 'plot-available' : ($status === 'partial' ? 'plot-partial' : 'plot-booked');
                            // prepare data attributes
                            $plotId = (int)$plot['id'];
                            $plotNo = htmlspecialchars($plot['plot_no']);
                            $plotSize = htmlspecialchars($plot['size'] ?? '');
                            $plotPrice = (float)($plot['price'] ?? 0);
                        ?>
                            <div>
                                <div
                                    role="button"
                                    tabindex="0"
                                    class="plot-tile <?= $cls ?>"
                                    data-plot-id="<?= $plotId ?>"
                                    data-plot-no="<?= $plotNo ?>"
                                    data-size="<?= $plotSize ?>"
                                    data-price="<?= htmlspecialchars(number_format($plotPrice)) ?>"
                                    data-status="<?= htmlspecialchars($status) ?>"
                                    aria-pressed="false"
                                    aria-label="Plot <?= $plotNo ?> — <?= $plotSize ?: 'size unknown' ?> — <?= $status ?>">
                                    <div class="plot-no"><?= $plotNo ?></div>
                                    <div class="plot-meta"><?= $plotSize ? htmlspecialchars($plotSize) . ' • ' : '' ?>₹<?= htmlspecialchars(number_format($plotPrice)) ?></div>
                                    <div class="status-pill <?= $status === 'available' ? 'status-available-pill' : ($status === 'partial' ? 'status-partial-pill' : 'status-booked-pill') ?>">
                                        <?= ucfirst($status) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- hidden live region for auto-refresh status -->
        <div id="live-status" class="sr-only" aria-live="polite"></div>
    </div>

    <!-- Booking detail modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="bookingModalTitle" class="modal-title">Booking details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="bookingModalBody" class="modal-body">
                    <div class="text-center text-muted">Loading...</div>
                </div>
                <div class="modal-footer">
                    <button id="bookingModalClose" type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a id="bookingModalViewBtn" class="btn btn-primary" href="#" target="_blank">Open Details Page</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const tvMode = <?= $tv_mode ? 'true' : 'false' ?>;
            const projectId = <?= (int)$project_id ?>;
            const csrfToken = document.getElementById('csrf-token').textContent;
            const modalEl = document.getElementById('bookingModal');
            const bsModal = new bootstrap.Modal(modalEl, {
                keyboard: true
            });
            const modalBody = document.getElementById('bookingModalBody');
            const modalTitle = document.getElementById('bookingModalTitle');
            const modalViewBtn = document.getElementById('bookingModalViewBtn');

            // click/keyboard handler for tiles
            function onTileAct(e) {
                const tile = e.currentTarget;
                const plotId = tile.dataset.plotId;
                const status = tile.dataset.status;
                const plotNo = tile.dataset.plotNo;
                const plotSize = tile.dataset.size;
                const plotPrice = tile.dataset.price;

                if (tvMode) return; // read-only in TV

                if (status === 'available') {
                    modalTitle.textContent = 'Book Plot ' + plotNo;
                    modalViewBtn.classList.add('d-none');
                    modalBody.innerHTML = `
                        <form id="bookingForm">
                            <input type="hidden" name="plot_id" value="${plotId}">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <div class="mb-2">
                                <label class="form-label">Buyer Name</label>
                                <input name="buyer_name" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Phone</label>
                                <input name="phone" class="form-control">
                            </div>
                            <button class="btn btn-primary">Book</button>
                        </form>`;
                    const form = modalBody.querySelector('#bookingForm');
                    form.addEventListener('submit', function(ev) {
                        ev.preventDefault();
                        const fd = new FormData(form);
                        fetch('/public/book-plot.php', {method: 'POST', body: fd})
                            .then(r => r.json())
                            .then(data => {
                                if (data.ok) {
                                    tile.dataset.status = 'booked';
                                    tile.classList.remove('plot-available', 'plot-partial');
                                    tile.classList.add('plot-booked');
                                    const pill = tile.querySelector('.status-pill');
                                    if (pill) pill.textContent = 'Booked';
                                    bsModal.hide();
                                } else {
                                    alert(data.error || 'Booking failed');
                                }
                            })
                            .catch(() => alert('Booking failed'));
                    });
                    bsModal.show();
                    return;
                }

                modalViewBtn.classList.remove('d-none');
                // open modal with booking details
                modalTitle.textContent = 'Plot ' + plotNo + ' — Booking Details';
                modalBody.innerHTML = '<div class="text-center text-muted">Loading...</div>';
                modalViewBtn.href = '/public/booking-details.php?plot_id=' + encodeURIComponent(plotId);

                // try to fetch booking-details fragment (if page returns full HTML it will still display)
                fetch('/public/booking-details.php?plot_id=' + encodeURIComponent(plotId))
                    .then(resp => resp.text())
                    .then(html => {
                        // if the returned html is large page, show only the relevant portion if present
                        // simple heuristic: look for <body> and cut it out, else show entire HTML
                        let out = html;
                        const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
                        if (bodyMatch) out = bodyMatch[1];
                        modalBody.innerHTML = out;
                    })
                    .catch(err => {
                        modalBody.innerHTML = '<div class="alert alert-danger">Failed to load booking details.</div>';
                    });

                bsModal.show();
            }

            // attach listeners to tiles
            document.querySelectorAll('.plot-tile').forEach(tile => {
                tile.addEventListener('click', onTileAct, false);
                tile.addEventListener('keydown', function(ev) {
                    if (ev.key === 'Enter' || ev.key === ' ') {
                        ev.preventDefault();
                        onTileAct.call(this, ev);
                    }
                });
            });

            // Auto-refresh for TV mode or manual polling
            function refreshData() {
                // If tv-refresh endpoint exists, fetch JSON and update statuses live
                const url = '/public/tv-refresh.php?project_id=' + encodeURIComponent(projectId);
                fetch(url, {
                    cache: 'no-store'
                }).then(r => {
                    if (!r.ok) throw new Error('Network response not ok');
                    return r.json();
                }).then(data => {
                    // expected shape: { blocks: { block_id: [ {id, status, price, plot_no, size}, ... ] } }
                    // we'll iterate tiles and update classes/status labels
                    const tiles = document.querySelectorAll('.plot-tile');
                    tiles.forEach(t => {
                        const pid = t.dataset.plotId;
                        // find in data: this may be expensive but fine for moderate sizes
                        let found = null;
                        if (data && Array.isArray(data.plots)) {
                            for (const p of data.plots) {
                                if (String(p.id) === String(pid)) {
                                    found = p;
                                    break;
                                }
                            }
                        }
                        if (found) {
                            // update status/class
                            const newStatus = found.status || 'available';
                            t.dataset.status = newStatus;
                            t.classList.remove('plot-available', 'plot-partial', 'plot-booked');
                            if (newStatus === 'available') t.classList.add('plot-available');
                            else if (newStatus === 'partial') t.classList.add('plot-partial');
                            else t.classList.add('plot-booked');

                            // update meta text if changed
                            if (found.price !== undefined) {
                                const meta = t.querySelector('.plot-meta');
                                meta.textContent = (found.size ? found.size + ' • ' : '') + '₹' + Number(found.price).toLocaleString();
                            }
                            const pill = t.querySelector('.status-pill');
                            if (pill) pill.textContent = (newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                        }
                    });

                    // announce
                    const live = document.getElementById('live-status');
                    if (live) live.textContent = 'Updated at ' + (new Date()).toLocaleTimeString();
                }).catch(err => {
                    // fallback: reload whole page (only if tv mode)
                    if (tvMode) {
                        location.reload();
                    } else {
                        console.debug('tv-refresh failed', err);
                    }
                });
            }

            if (tvMode) {
                // auto-refresh every 12s
                refreshData();
                setInterval(refreshData, 12000);
                // fullscreen prompt for TV mode
            }

            // also provide manual refresh button via keyboard: press 'r' to refresh (non-tv)
            document.addEventListener('keydown', function(ev) {
                if (ev.key === 'r' && !tvMode && (ev.ctrlKey || ev.metaKey)) {
                    ev.preventDefault();
                    refreshData();
                    alert('Refresh requested');
                }
            });
        })();
    </script>
</body>

</html>