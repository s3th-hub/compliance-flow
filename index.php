<?php
// index.php — Dashboard
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$db   = db();
$user = current_user();

// --- Stats ---
$stats = [];
$stats['businesses']   = $db->query('SELECT COUNT(*) FROM businesses')->fetch_row()[0];
$stats['total']        = $db->query('SELECT COUNT(*) FROM compliance_records')->fetch_row()[0];
$stats['active']       = $db->query("SELECT COUNT(*) FROM compliance_records WHERE status='active'")->fetch_row()[0];
$stats['expired']      = $db->query("SELECT COUNT(*) FROM compliance_records WHERE status='expired'")->fetch_row()[0];
$stats['pending']      = $db->query("SELECT COUNT(*) FROM compliance_records WHERE status='pending_renewal'")->fetch_row()[0];

// --- Expiring within 30 days ---
$expiring = $db->query(
    "SELECT cr.id, cr.title, cr.expiry_date, b.business_name, cc.name AS category
     FROM compliance_records cr
     JOIN businesses b ON b.id = cr.business_id
     JOIN compliance_categories cc ON cc.id = cr.category_id
     WHERE cr.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       AND cr.status != 'expired'
     ORDER BY cr.expiry_date ASC
     LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

// --- Expired items ---
$expired_items = $db->query(
    "SELECT cr.id, cr.title, cr.expiry_date, b.business_name
     FROM compliance_records cr
     JOIN businesses b ON b.id = cr.business_id
     WHERE cr.status = 'expired'
     ORDER BY cr.expiry_date DESC
     LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// --- Chart data: status breakdown ---
$chart_labels  = ['Active', 'Expired', 'Pending Renewal'];
$chart_data    = [$stats['active'], $stats['expired'], $stats['pending']];

// --- Chart data: by category ---
$cat_rows = $db->query(
    "SELECT cc.name, COUNT(cr.id) AS total
     FROM compliance_categories cc
     LEFT JOIN compliance_records cr ON cr.category_id = cc.id
     GROUP BY cc.id ORDER BY total DESC LIMIT 7"
)->fetch_all(MYSQLI_ASSOC);
$cat_labels = array_column($cat_rows, 'name');
$cat_data   = array_column($cat_rows, 'total');

$pageTitle    = 'Dashboard';
$inlineScript = "
const ctx1 = document.getElementById('statusChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: " . json_encode($chart_labels) . ",
        datasets: [{ data: " . json_encode($chart_data) . ", backgroundColor: ['#198754','#dc3545','#fd7e14'] }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});
const ctx2 = document.getElementById('categoryChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: " . json_encode($cat_labels) . ",
        datasets: [{ label: 'Records', data: " . json_encode($cat_data) . ", backgroundColor: '#0d6efd' }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
";

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Dashboard</h4>
        <p class="text-muted small mb-0">Welcome back, <?= clean($user['name']) ?>. Today is <?= date('d M Y') ?>.</p>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card blue h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-building fs-2 text-primary"></i>
                <div>
                    <div class="fs-3 fw-bold"><?= $stats['businesses'] ?></div>
                    <div class="text-muted small">Businesses</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card green h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-check-circle fs-2 text-success"></i>
                <div>
                    <div class="fs-3 fw-bold"><?= $stats['active'] ?></div>
                    <div class="text-muted small">Active Records</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card red h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-x-circle fs-2 text-danger"></i>
                <div>
                    <div class="fs-3 fw-bold"><?= $stats['expired'] ?></div>
                    <div class="text-muted small">Expired</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card orange h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-clock-history fs-2 text-warning"></i>
                <div>
                    <div class="fs-3 fw-bold"><?= $stats['pending'] ?></div>
                    <div class="text-muted small">Pending Renewal</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">Compliance Status</div>
            <div class="card-body d-flex align-items-center justify-content-center" style="height:260px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header fw-semibold">Records by Category</div>
            <div class="card-body" style="height:260px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Alerts & Tables -->
<div class="row g-3">
    <!-- Expiring Soon -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-alarm text-warning me-2"></i>Expiring Within 30 Days</span>
                <a href="<?= BASE_URL ?>/reports/index.php?type=upcoming" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($expiring)): ?>
                    <p class="text-muted p-3 mb-0">No items expiring soon.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>Business</th><th>Compliance Item</th><th>Expires</th><th>Days Left</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($expiring as $row): ?>
                            <?php $days = days_until_expiry($row['expiry_date']); ?>
                            <tr>
                                <td><?= clean($row['business_name']) ?></td>
                                <td><?= clean($row['title']) ?></td>
                                <td><?= date('d M Y', strtotime($row['expiry_date'])) ?></td>
                                <td class="<?= $days <= 7 ? 'expiry-danger' : 'expiry-warning' ?>"><?= $days ?> days</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recently Expired -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Recently Expired</div>
            <div class="card-body p-0">
                <?php if (empty($expired_items)): ?>
                    <p class="text-muted p-3 mb-0">No expired records.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($expired_items as $row): ?>
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="fw-semibold small"><?= clean($row['title']) ?></div>
                                <div class="text-muted" style="font-size:.78rem;"><?= clean($row['business_name']) ?></div>
                            </div>
                            <span class="badge bg-danger align-self-center"><?= date('d M Y', strtotime($row['expiry_date'])) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
