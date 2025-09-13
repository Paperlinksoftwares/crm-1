<?php
// public/admin/project-view.php
require __DIR__ . '/../../app/admin_auth.php';
require __DIR__ . '/../../app/db.php';

$project_id = (int)($_GET['project_id'] ?? 0);
if (!$project_id) {
    header('Location: dashboard.php');
    exit;
}

// fetch project
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
$stmt->execute([$project_id]);
$project = $stmt->fetch();
if (!$project) {
    echo "Project not found";
    exit;
}

// fetch plots for this project (join blocks)
$sql = "
SELECT pl.*, b.name AS block_name
FROM plots pl
JOIN blocks b ON b.id = pl.block_id
WHERE b.project_id = ?
ORDER BY b.sort_order ASC, pl.plot_no ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$project_id]);
$plots = $stmt->fetchAll();

// simple CSRF token (if you didn't add app/csrf.php)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Plots — <?php echo htmlspecialchars($project['name']); ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .plots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 12px;
        }

        .plot-tile {
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            user-select: none;
            min-height: 72px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .plot-no {
            font-weight: 700;
            font-size: 1.05rem;
        }

        .plot-sub {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .plot-available {
            background: #e6ffed;
            border: 1px solid #28a745;
            color: #155724;
        }

        .plot-partial {
            background: #fff7e6;
            border: 1px solid #ffc107;
            color: #856404;
        }

        .plot-booked {
            background: #ffe6ea;
            border: 1px solid #dc3545;
            color: #721c24;
        }

        .legend {
            margin-top: 16px;
        }

        .large-grid .plot-tile {
            min-height: 110px;
            font-size: 1.05rem;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0"><?php echo htmlspecialchars($project['name']); ?></h4>
                <div class="text-muted small"><?php echo htmlspecialchars($project['location'] ?? ''); ?></div>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">← Back</a>
            </div>
        </div>

        <div id="gridWrap">
            <div class="plots-grid" id="plotsGrid">
                <?php foreach ($plots as $pl):
                    $cls = 'plot-available';
                    if ($pl['status'] === 'booked') $cls = 'plot-booked';
                    if ($pl['status'] === 'partial') $cls = 'plot-partial';
                ?>
                    <div class="plot-tile <?php echo $cls; ?>" data-id="<?php echo $pl['id']; ?>" data-status="<?php echo $pl['status']; ?>">
                        <div class="plot-no"><?php echo htmlspecialchars($pl['plot_no']); ?></div>
                        <div class="plot-sub"><?php echo htmlspecialchars($pl['block_name']); ?> · <?php echo number_format((float)$pl['size'], 2); ?> sqft</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="legend text-muted small">
            <span class="me-3"><span style="display:inline-block;width:12px;height:12px;background:#28a745;border-radius:50%;margin-right:6px;"></span>Available</span>
            <span class="me-3"><span style="display:inline-block;width:12px;height:12px;background:#ffc107;border-radius:50%;margin-right:6px;"></span>Partial</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#dc3545;border-radius:50%;margin-right:6px;"></span>Booked</span>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="bookingForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Book plot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="plot_id" id="plot_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <div class="mb-2"><label class="form-label">Buyer name</label><input name="buyer_name" id="buyer_name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Phone</label><input name="phone" id="phone" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">Status</label>
                        <select name="status" class="form-select" id="status">
                            <option value="booked">Booked</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label">Amount paid (optional)</label><input name="amount_paid" id="amount_paid" class="form-control" type="number" step="0.01"></div>
                    <div class="mb-2"><label class="form-label">Notes</label><textarea name="notes" id="notes" class="form-control" rows="2"></textarea></div>
                    <div id="modalInfo" class="small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Confirm booking</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Booking Modal -->
    <div class="modal fade" id="viewBookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" id="viewBookingContent"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bookingModalEl = document.getElementById('bookingModal');
            const bookingModal = new bootstrap.Modal(bookingModalEl);
            const viewModal = new bootstrap.Modal(document.getElementById('viewBookingModal'));
            const bookingForm = document.getElementById('bookingForm');
            const plotsGrid = document.getElementById('plotsGrid');

            // click on tile
            plotsGrid.addEventListener('click', async (e) => {
                const tile = e.target.closest('.plot-tile');
                if (!tile) return;
                const plotId = tile.dataset.id;
                const status = tile.dataset.status;

                if (status === 'available') {
                    // open booking modal empty
                    document.getElementById('plot_id').value = plotId;
                    document.getElementById('buyer_name').value = '';
                    document.getElementById('phone').value = '';
                    document.getElementById('amount_paid').value = '';
                    document.getElementById('notes').value = '';
                    document.getElementById('modalTitle').textContent = 'Book plot ' + tile.querySelector('.plot-no').textContent;
                    bookingModal.show();
                } else {
                    // fetch booking details and show
                    try {
                        const res = await fetch('booking-details.php?plot_id=' + encodeURIComponent(plotId));
                        const data = await res.json();
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        let html = '<div class="modal-header"><h5 class="modal-title">Booking details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
                        html += '<div class="modal-body">';
                        html += '<p><strong>Buyer:</strong> ' + (data.buyer_name || '—') + '</p>';
                        html += '<p><strong>Phone:</strong> ' + (data.phone || '—') + '</p>';
                        html += '<p><strong>Status:</strong> ' + (data.status || '—') + '</p>';
                        html += '<p><strong>Amount paid:</strong> ' + (data.amount_paid ?? '0') + '</p>';
                        html += '<p><strong>Booked at:</strong> ' + (data.booking_date || '—') + '</p>';
                        html += '<p><strong>Notes:</strong><br>' + (data.notes || '—') + '</p>';
                        html += '</div>';
                        html += '<div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>';
                        document.getElementById('viewBookingContent').innerHTML = html;
                        viewModal.show();
                    } catch (err) {
                        console.error(err);
                        alert('Could not load booking details');
                    }
                }
            });

            // submit booking via AJAX
            bookingForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = new FormData(bookingForm);
                try {
                    const res = await fetch('book-plot.php', {
                        method: 'POST',
                        body: form
                    });
                    const data = await res.json();
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    // update tile UI
                    const tile = Array.from(document.querySelectorAll('.plot-tile')).find(t => t.dataset.id == form.get('plot_id'));
                    if (tile) {
                        tile.dataset.status = form.get('status');
                        tile.classList.remove('plot-available', 'plot-partial', 'plot-booked');
                        tile.classList.add(form.get('status') === 'booked' ? 'plot-booked' : 'plot-partial');
                    }
                    bookingModal.hide();
                    alert('Booked successfully!');
                } catch (err) {
                    console.error(err);
                    alert('Server error');
                }
            });
        });
    </script>
</body>

</html>