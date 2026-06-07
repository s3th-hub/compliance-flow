<?php
// documents/index.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = db();
$user = current_user();
$error = $success = '';

// ── HANDLE UPLOAD FROM THIS PAGE ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $compliance_id = (int) ($_POST['compliance_id'] ?? 0);
    $file = $_FILES['document'];
    $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_bytes = UPLOAD_MAX_MB * 1024 * 1024;

    if (!$compliance_id) {
        $error = 'Please select a compliance record.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error code: ' . $file['error'];
    } elseif (!in_array($file['type'], $allowed)) {
        $error = 'Only PDF, JPG, PNG files are allowed.';
    } elseif ($file['size'] > $max_bytes) {
        $error = 'File exceeds maximum size of ' . UPLOAD_MAX_MB . 'MB.';
    } else {
        if (!is_dir(UPLOAD_DIR))
            mkdir(UPLOAD_DIR, 0775, true);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $stored_name = uniqid('doc_', true) . '.' . $ext;
        $dest = UPLOAD_DIR . $stored_name;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $orig = basename($file['name']);
            $type = $file['type'];
            $size = $file['size'];
            $uid = $user['id'];
            $stmt = $db->prepare(
                'INSERT INTO documents
                 (compliance_id, original_name, stored_name, file_type, file_size, uploaded_by)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->bind_param('isssii', $compliance_id, $orig, $stored_name, $type, $size, $uid);
            $stmt->execute();
            $stmt->close();
            $success = 'Document uploaded successfully.';
        } else {
            $error = 'Failed to move uploaded file. Check folder permissions.';
        }
    }
}

// ── HANDLE DELETE ─────────────────────────────────────────────
if (isset($_GET['del'])) {
    $doc_id = (int) $_GET['del'];
    $stmt = $db->prepare('SELECT stored_name FROM documents WHERE id = ?');
    $stmt->bind_param('i', $doc_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($doc) {
        $path = UPLOAD_DIR . $doc['stored_name'];
        if (file_exists($path))
            unlink($path);
        $stmt2 = $db->prepare('DELETE FROM documents WHERE id = ?');
        $stmt2->bind_param('i', $doc_id);
        $stmt2->execute();
        $stmt2->close();
    }
    header('Location: ' . BASE_URL . '/documents/index.php?success=deleted');
    exit;
}

if (isset($_GET['success'])) {
    $success = $_GET['success'] === 'deleted' ? 'Document deleted.' : 'Document uploaded.';
}

// ── LOAD ALL DOCUMENTS ────────────────────────────────────────
$result = $db->query(
    "SELECT d.id, d.original_name, d.stored_name, d.file_type, d.file_size,
            d.uploaded_at, d.compliance_id,
            cr.title AS compliance_title, cr.status AS cr_status,
            b.business_name,
            u.full_name AS uploader
     FROM documents d
     JOIN compliance_records cr ON cr.id = d.compliance_id
     JOIN businesses b ON b.id = cr.business_id
     JOIN users u ON u.id = d.uploaded_by
     ORDER BY d.uploaded_at DESC"
);
$documents = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// ── LOAD BUSINESSES + COMPLIANCE RECORDS FOR UPLOAD FORM ─────
$businesses = $db->query("SELECT id, business_name FROM businesses ORDER BY business_name")->fetch_all(MYSQLI_ASSOC);
$all_records = $db->query(
    "SELECT cr.id, cr.title, b.business_name, cr.business_id
     FROM compliance_records cr
     JOIN businesses b ON b.id = cr.business_id
     ORDER BY b.business_name, cr.title"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'All Documents';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-folder2-open me-2"></i>All Documents</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-upload me-1"></i> Upload Document
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Documents table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>File</th>
                        <th>Compliance Record</th>
                        <th>Business</th>
                        <th>Size</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-folder2 d-block mb-2" style="font-size:2rem"></i>
                                No documents uploaded yet.
                                <a href="#" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload one now.</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $i => $d): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <i
                                        class="bi bi-<?= str_contains($d['file_type'], 'pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-primary' ?> me-1"></i>
                                    <span style="font-size:.87rem"><?= htmlspecialchars($d['original_name']) ?></span>
                                </td>
                                <td style="font-size:.85rem"><?= htmlspecialchars($d['compliance_title']) ?></td>
                                <td style="font-size:.85rem"><?= htmlspecialchars($d['business_name']) ?></td>
                                <td style="font-size:.83rem"><?= round($d['file_size'] / 1024) ?> KB</td>
                                <td style="font-size:.83rem"><?= htmlspecialchars($d['uploader']) ?></td>
                                <td style="font-size:.83rem"><?= date('d M Y', strtotime($d['uploaded_at'])) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/documents/view.php?id=<?= $d['id'] ?>" target="_blank"
                                        class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="?del=<?= $d['id'] ?>" onclick="return confirm('Delete this document?')"
                                        class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── UPLOAD MODAL ──────────────────────────────────────────── -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Business</label>
                        <select id="bizSelect" class="form-select" required>
                            <option value="">— Select business —</option>
                            <?php foreach ($businesses as $biz): ?>
                                <option value="<?= $biz['id'] ?>"><?= htmlspecialchars($biz['business_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Compliance Record</label>
                        <select name="compliance_id" id="recordSelect" class="form-select" required>
                            <option value="">— Select business first —</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">File</label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">PDF, JPG, PNG — max <?= UPLOAD_MAX_MB ?>MB</div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Filter records by selected business -->
<script>
    const allRecords = <?= json_encode($all_records) ?>;

    document.getElementById('bizSelect').addEventListener('change', function () {
        const bizId = parseInt(this.value);
        const select = document.getElementById('recordSelect');
        const filtered = allRecords.filter(r => r.business_id == bizId);

        select.innerHTML = '<option value="">— Select record —</option>';
        filtered.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.title;
            select.appendChild(opt);
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>