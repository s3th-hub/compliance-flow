<?php
// admin/businesses.php — Business CRUD
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db     = db();
$user   = current_user();
$action = $_GET['action'] ?? 'list';
$id     = (int) ($_GET['id'] ?? 0);
$error  = '';
$success = '';

// ---- DELETE ----
if ($action === 'delete' && $id > 0) {
    require_admin();
    $stmt = $db->prepare('DELETE FROM businesses WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ' . BASE_URL . '/admin/businesses.php?success=deleted');
    exit;
}

// ---- SAVE (add/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'business_name'       => trim($_POST['business_name'] ?? ''),
        'registration_number' => trim($_POST['registration_number'] ?? ''),
        'industry_type'       => trim($_POST['industry_type'] ?? ''),
        'physical_address'    => trim($_POST['physical_address'] ?? ''),
        'contact_person'      => trim($_POST['contact_person'] ?? ''),
        'contact_email'       => trim($_POST['contact_email'] ?? ''),
        'contact_phone'       => trim($_POST['contact_phone'] ?? ''),
    ];

    foreach (['business_name','registration_number','industry_type','physical_address','contact_person'] as $req) {
        if (empty($fields[$req])) { $error = 'Please fill in all required fields.'; break; }
    }

    if (!$error && !empty($fields['contact_email']) && !filter_var($fields['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    }

    if (!$error) {
        $edit_id = (int) ($_POST['edit_id'] ?? 0);
        if ($edit_id > 0) {
            $stmt = $db->prepare(
                'UPDATE businesses SET business_name=?, registration_number=?, industry_type=?,
                 physical_address=?, contact_person=?, contact_email=?, contact_phone=? WHERE id=?'
            );
            $v = array_values($fields);
            $stmt->bind_param('sssssssi', $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $edit_id);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO businesses (business_name, registration_number, industry_type,
                 physical_address, contact_person, contact_email, contact_phone, created_by)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $uid = $user['id'];
            $v = array_values($fields);
            $stmt->bind_param('sssssssi', $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $uid);
        }
        if ($stmt->execute()) {
            header('Location: ' . BASE_URL . '/admin/businesses.php?success=saved');
            exit;
        }
        $error = 'Database error: ' . $db->error;
        $stmt->close();
    }
    $action = ($edit_id ?? 0) > 0 ? 'edit' : 'add';
}

// ---- LOAD for edit ----
$edit_data = [];
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare('SELECT * FROM businesses WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$edit_data) { header('Location: ' . BASE_URL . '/admin/businesses.php'); exit; }
}

// ---- LIST ----
$businesses = $db->query(
    "SELECT b.*, u.full_name AS created_by_name,
            COUNT(cr.id) AS record_count
     FROM businesses b
     LEFT JOIN users u ON u.id = b.created_by
     LEFT JOIN compliance_records cr ON cr.business_id = b.id
     GROUP BY b.id
     ORDER BY b.business_name"
)->fetch_all(MYSQLI_ASSOC);

$success = $_GET['success'] ?? '';
$pageTitle = 'Businesses';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-building me-2"></i>Businesses</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bizModal" onclick="resetForm()">
        <i class="bi bi-plus-lg me-1"></i>Add Business
    </button>
</div>

<?php if ($success === 'saved'): ?><div class="alert alert-success py-2">Business saved successfully.</div><?php endif; ?>
<?php if ($success === 'deleted'): ?><div class="alert alert-warning py-2">Business deleted.</div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark"><tr>
                    <th>#</th><th>Business Name</th><th>Reg. Number</th><th>Industry</th><th>Contact</th><th>Records</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($businesses)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No businesses found. Add one above.</td></tr>
                <?php endif; ?>
                <?php foreach ($businesses as $i => $b): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= clean($b['business_name']) ?></td>
                    <td><?= clean($b['registration_number']) ?></td>
                    <td><?= clean($b['industry_type']) ?></td>
                    <td><?= clean($b['contact_person']) ?></td>
                    <td><span class="badge bg-primary"><?= $b['record_count'] ?></span></td>
                    <td>
                        <a href="?action=edit&id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary"
                           onclick="openEdit(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)" data-bs-toggle="modal" data-bs-target="#bizModal">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($user['role'] === 'admin'): ?>
                        <a href="?action=delete&id=<?= $b['id'] ?>"
                           onclick="return confirm('Delete <?= clean($b['business_name']) ?>? This removes all linked compliance records.')"
                           class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="bizModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Business</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="business_name" id="f_business_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Registration Number <span class="text-danger">*</span></label>
                            <input type="text" name="registration_number" id="f_registration_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Industry Type <span class="text-danger">*</span></label>
                            <input type="text" name="industry_type" id="f_industry_type" class="form-control" required placeholder="e.g. Retail, Manufacturing">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" name="contact_person" id="f_contact_person" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Physical Address <span class="text-danger">*</span></label>
                            <textarea name="physical_address" id="f_physical_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Email</label>
                            <input type="email" name="contact_email" id="f_contact_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Phone</label>
                            <input type="text" name="contact_phone" id="f_contact_phone" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Business</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('edit_id').value = '0';
    document.getElementById('modalTitle').textContent = 'Add Business';
    ['business_name','registration_number','industry_type','physical_address','contact_person','contact_email','contact_phone'].forEach(f => {
        const el = document.getElementById('f_' + f);
        if (el) el.value = '';
    });
}
function openEdit(b) {
    document.getElementById('edit_id').value = b.id;
    document.getElementById('modalTitle').textContent = 'Edit Business';
    document.getElementById('f_business_name').value       = b.business_name;
    document.getElementById('f_registration_number').value = b.registration_number;
    document.getElementById('f_industry_type').value       = b.industry_type;
    document.getElementById('f_physical_address').value    = b.physical_address;
    document.getElementById('f_contact_person').value      = b.contact_person;
    document.getElementById('f_contact_email').value       = b.contact_email || '';
    document.getElementById('f_contact_phone').value       = b.contact_phone || '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
