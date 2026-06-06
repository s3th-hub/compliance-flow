<?php
// compliance/records.php — Compliance Records CRUD
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db     = db();
$user   = current_user();
$action = $_GET['action'] ?? 'list';
$id     = (int) ($_GET['id'] ?? 0);
$error  = '';

// ---- DELETE ----
if ($action === 'delete' && $id > 0) {
    $stmt = $db->prepare('DELETE FROM compliance_records WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . BASE_URL . '/compliance/records.php?success=deleted');
    exit;
}

// ---- SAVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $business_id = (int) ($_POST['business_id'] ?? 0);
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $issue_date  = trim($_POST['issue_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $edit_id     = (int) ($_POST['edit_id'] ?? 0);

    if (!$title || !$business_id || !$category_id || !$issue_date || !$expiry_date) {
        $error = 'All required fields must be filled.';
    } elseif ($expiry_date <= $issue_date) {
        $error = 'Expiry date must be after issue date.';
    }

    if (!$error) {
        $status = calculate_status($expiry_date);
        if ($edit_id > 0) {
            $stmt = $db->prepare(
                'UPDATE compliance_records SET title=?, business_id=?, category_id=?, issue_date=?,
                 expiry_date=?, status=?, description=? WHERE id=?'
            );
            $stmt->bind_param('siissssi', $title, $business_id, $category_id, $issue_date, $expiry_date, $status, $description, $edit_id);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO compliance_records (title, business_id, category_id, issue_date, expiry_date, status, description, created_by)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $uid = $user['id'];
            $stmt->bind_param('siissssi', $title, $business_id, $category_id, $issue_date, $expiry_date, $status, $description, $uid);
        }
        if ($stmt->execute()) {
            header('Location: ' . BASE_URL . '/compliance/records.php?success=saved');
            exit;
        }
        $error = 'DB error: ' . $db->error;
        $stmt->close();
    }
}

// Load dropdowns
$businesses  = $db->query('SELECT id, business_name FROM businesses ORDER BY business_name')->fetch_all(MYSQLI_ASSOC);
$categories  = $db->query('SELECT id, name FROM compliance_categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);

// Filter support
$filter_status   = $_GET['status'] ?? '';
$filter_business = (int) ($_GET['business_id'] ?? 0);
$where = '1=1';
$params = [];
$types  = '';
if ($filter_status) { $where .= ' AND cr.status = ?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_business) { $where .= ' AND cr.business_id = ?'; $params[] = $filter_business; $types .= 'i'; }

$stmt = $db->prepare(
    "SELECT cr.*, b.business_name, cc.name AS category_name
     FROM compliance_records cr
     JOIN businesses b ON b.id = cr.business_id
     JOIN compliance_categories cc ON cc.id = cr.category_id
     WHERE $where
     ORDER BY cr.expiry_date ASC"
);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Auto-sync statuses
$db->query("UPDATE compliance_records SET status='expired' WHERE expiry_date < CURDATE() AND status != 'expired'");
$db->query("UPDATE compliance_records SET status='pending_renewal' WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active'");

$success   = $_GET['success'] ?? '';
$pageTitle = 'Compliance Records';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-check me-2"></i>Compliance Records</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recModal" onclick="resetForm()">
        <i class="bi bi-plus-lg me-1"></i>Add Record
    </button>
</div>

<?php if ($success === 'saved'): ?><div class="alert alert-success py-2">Record saved.</div><?php endif; ?>
<?php if ($success === 'deleted'): ?><div class="alert alert-warning py-2">Record deleted.</div><?php endif; ?>

<!-- Filters -->
<form method="GET" class="row g-2 mb-3 no-print">
    <div class="col-md-4">
        <select name="business_id" class="form-select form-select-sm">
            <option value="">All Businesses</option>
            <?php foreach ($businesses as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $filter_business == $b['id'] ? 'selected' : '' ?>><?= clean($b['business_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="expired" <?= $filter_status === 'expired' ? 'selected' : '' ?>>Expired</option>
            <option value="pending_renewal" <?= $filter_status === 'pending_renewal' ? 'selected' : '' ?>>Pending Renewal</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button></div>
    <div class="col-auto"><a href="records.php" class="btn btn-sm btn-outline-secondary">Reset</a></div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark"><tr>
                    <th>#</th><th>Business</th><th>Title</th><th>Category</th><th>Issue Date</th><th>Expiry Date</th><th>Status</th><th>Days Left</th><th class="no-print">Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($records as $i => $r): ?>
                    <?php $days = days_until_expiry($r['expiry_date']); ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= clean($r['business_name']) ?></td>
                        <td class="fw-semibold"><?= clean($r['title']) ?></td>
                        <td><?= clean($r['category_name']) ?></td>
                        <td><?= date('d M Y', strtotime($r['issue_date'])) ?></td>
                        <td><?= date('d M Y', strtotime($r['expiry_date'])) ?></td>
                        <td>
                            <span class="badge badge-<?= $r['status'] ?>">
                                <?= str_replace('_', ' ', ucfirst($r['status'])) ?>
                            </span>
                        </td>
                        <td class="<?= $days < 0 ? 'expiry-danger' : ($days <= 30 ? 'expiry-warning' : 'expiry-ok') ?>">
                            <?= $days < 0 ? abs($days) . ' days ago' : $days . ' days' ?>
                        </td>
                        <td class="no-print">
                            <a href="<?= BASE_URL ?>/documents/upload.php?compliance_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Documents">
                                <i class="bi bi-paperclip"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-primary" title="Edit"
                                onclick='openEdit(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'
                                data-bs-toggle="modal" data-bs-target="#recModal">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="?action=delete&id=<?= $r['id'] ?>"
                               onclick="return confirm('Delete this compliance record?')"
                               class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="recModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="edit_id" id="rec_edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="recModalTitle">Add Compliance Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="r_title" class="form-control" required placeholder="e.g. Annual Business Permit 2025">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Business <span class="text-danger">*</span></label>
                            <select name="business_id" id="r_business_id" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($businesses as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= clean($b['business_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="r_category_id" class="form-select" required>
                                <option value="">Select...</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= clean($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" name="issue_date" id="r_issue_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" id="r_expiry_date" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="r_description" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('rec_edit_id').value = '0';
    document.getElementById('recModalTitle').textContent = 'Add Compliance Record';
    ['title','issue_date','expiry_date','description'].forEach(f => document.getElementById('r_'+f).value = '');
    document.getElementById('r_business_id').value = '';
    document.getElementById('r_category_id').value = '';
}
function openEdit(r) {
    document.getElementById('rec_edit_id').value    = r.id;
    document.getElementById('recModalTitle').textContent = 'Edit Compliance Record';
    document.getElementById('r_title').value         = r.title;
    document.getElementById('r_business_id').value   = r.business_id;
    document.getElementById('r_category_id').value   = r.category_id;
    document.getElementById('r_issue_date').value    = r.issue_date;
    document.getElementById('r_expiry_date').value   = r.expiry_date;
    document.getElementById('r_description').value   = r.description || '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
