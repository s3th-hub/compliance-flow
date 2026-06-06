<?php
// reports/index.php — Compliance Reports
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db   = db();
$type = $_GET['type'] ?? 'all';
$business_id = (int) ($_GET['business_id'] ?? 0);

$businesses = $db->query('SELECT id, business_name FROM businesses ORDER BY business_name')->fetch_all(MYSQLI_ASSOC);

$where  = '1=1';
$params = [];
$types  = '';

if ($business_id) { $where .= ' AND cr.business_id = ?'; $params[] = $business_id; $types .= 'i'; }

switch ($type) {
    case 'active':
        $where .= " AND cr.status = 'active'"; $report_title = 'Active Compliance Items'; break;
    case 'expired':
        $where .= " AND cr.status = 'expired'"; $report_title = 'Expired Compliance Items'; break;
    case 'upcoming':
        $where .= " AND cr.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND cr.status != 'expired'";
        $report_title = 'Upcoming Renewals (Next 30 Days)'; break;
    default:
        $report_title = 'All Compliance Records'; break;
}

$stmt = $db->prepare(
    "SELECT cr.*, b.business_name, cc.name AS category_name
     FROM compliance_records cr
     JOIN businesses b ON b.id = cr.business_id
     JOIN compliance_categories cc ON cc.id = cr.category_id
     WHERE $where
     ORDER BY cr.expiry_date ASC"
);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-line me-2"></i>Reports</h4>
    <button onclick="window.print()" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<!-- Report type tabs -->
<div class="btn-group mb-3 no-print" role="group">
    <a href="?type=all" class="btn btn-sm <?= $type === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">All Records</a>
    <a href="?type=active" class="btn btn-sm <?= $type === 'active' ? 'btn-success' : 'btn-outline-success' ?>">Active</a>
    <a href="?type=expired" class="btn btn-sm <?= $type === 'expired' ? 'btn-danger' : 'btn-outline-danger' ?>">Expired</a>
    <a href="?type=upcoming" class="btn btn-sm <?= $type === 'upcoming' ? 'btn-warning' : 'btn-outline-warning' ?>">Upcoming (30 days)</a>
</div>

<!-- Business filter -->
<form method="GET" class="row g-2 mb-3 no-print">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <div class="col-md-4">
        <select name="business_id" class="form-select form-select-sm">
            <option value="">All Businesses</option>
            <?php foreach ($businesses as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $business_id == $b['id'] ? 'selected' : '' ?>><?= clean($b['business_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
</form>

<!-- Print header (hidden on screen) -->
<div class="d-none d-print-block mb-3">
    <h3>BizComply — <?= htmlspecialchars($report_title) ?></h3>
    <p>Generated: <?= date('d F Y, H:i') ?></p>
    <hr>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span class="fw-semibold"><?= htmlspecialchars($report_title) ?></span>
        <span class="badge bg-secondary"><?= count($records) ?> record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-dark"><tr>
                    <th>#</th><th>Business</th><th>Compliance Item</th><th>Category</th><th>Issue Date</th><th>Expiry Date</th><th>Status</th><th>Days Left</th>
                </tr></thead>
                <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No records match the selected filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($records as $i => $r): ?>
                    <?php $days = days_until_expiry($r['expiry_date']); ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= clean($r['business_name']) ?></td>
                        <td><?= clean($r['title']) ?></td>
                        <td><?= clean($r['category_name']) ?></td>
                        <td><?= date('d M Y', strtotime($r['issue_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($r['expiry_date'])) ?></td>
                        <td><span class="badge badge-<?= $r['status'] ?>"><?= str_replace('_', ' ', ucfirst($r['status'])) ?></span></td>
                        <td>
                            <?php if ($days < 0): ?>
                                <span class="expiry-danger"><?= abs($days) ?> days ago</span>
                            <?php elseif ($days <= 30): ?>
                                <span class="expiry-warning"><?= $days ?> days</span>
                            <?php else: ?>
                                <span class="expiry-ok"><?= $days ?> days</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
