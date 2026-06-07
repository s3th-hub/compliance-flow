<?php
// documents/upload.php — Document upload & listing
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = db();
$user = current_user();
$compliance_id = (int) ($_GET['compliance_id'] ?? 0);
$error = '';
$success = '';

if (!$compliance_id) {
    header('Location: ' . BASE_URL . '/compliance/records.php');
    exit;
}

// Load compliance info
$stmt = $db->prepare(
    'SELECT cr.*, b.business_name FROM compliance_records cr
     JOIN businesses b ON b.id = cr.business_id WHERE cr.id = ?'
);
$stmt->bind_param('i', $compliance_id);
$stmt->execute();
$compliance = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$compliance) {
    header('Location: ' . BASE_URL . '/compliance/records.php');
    exit;
}

// ── DELETE ────────────────────────────────────────────────────
if (isset($_GET['del']) && (int) $_GET['del'] > 0) {
    $doc_id = (int) $_GET['del'];
    $stmt = $db->prepare('SELECT stored_name FROM documents WHERE id = ? AND compliance_id = ?');
    $stmt->bind_param('ii', $doc_id, $compliance_id);
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
    header("Location: upload.php?compliance_id={$compliance_id}&success=deleted");
    exit;
}

// ── UPLOAD ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
    $max_bytes = UPLOAD_MAX_MB * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Error code: ' . $file['error'];
    } elseif (!in_array($file['type'], $allowed_types)) {
        $error = 'Only PDF, JPG, PNG files allowed.';
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
            header("Location: upload.php?compliance_id={$compliance_id}&success=uploaded");
            exit;
        } else {
            $error = 'Failed to move uploaded file. Check folder permissions.';
        }
    }
}

// ── LOAD DOCUMENTS ────────────────────────────────────────────
$docs = $db->prepare(
    'SELECT d.*, u.full_name
     FROM documents d
     JOIN users u ON u.id = d.uploaded_by
     WHERE d.compliance_id = ?
     ORDER BY d.uploaded_at DESC'
);
$docs->bind_param('i', $compliance_id);
$docs->execute();
$documents = $docs->get_result()->fetch_all(MYSQLI_ASSOC);
$docs->close();

if (isset($_GET['success'])) {
    $success = $_GET['success'] === 'deleted' ? 'Document deleted.' : 'Document uploaded successfully.';
}

$pageTitle = 'Documents — ' . $compliance['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= BASE_URL ?>/compliance/records.php">Compliance</a>
        </li>
        <li class="breadcrumb-item active">Documents</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= clean($compliance['title']) ?></h4>
        <p class="text-muted mb-0">
            <?= clean($compliance['business_name']) ?> &middot;
            Expires: <?= date('d M Y', strtotime($compliance['expiry_date'])) ?>
        </p>
    </div>
    <span class="badge badge-<?= $compliance['status'] ?> fs-6">
        <?= str_replace('_', ' ', ucfirst($compliance['status'])) ?>
    </span>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- Upload form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-cloud-upload me-2"></i>Upload Document
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">PDF, JPG, PNG — max <?= UPLOAD_MAX_MB ?>MB</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Document list -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-folder2-open me-2"></i>
                Uploaded Documents (<?= count($documents) ?>)
            </div>
            <div class="card-body p-0">
                <?php if (empty($documents)): ?>
                    <p class="text-muted p-3 mb-0">No documents uploaded yet.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($documents as $doc): ?>
                            <li class="list-group-item d-flex align-items-center gap-3 py-2">
                                <i class="bi bi-<?= str_contains($doc['file_type'], 'pdf')
                                    ? 'file-earmark-pdf text-danger'
                                    : 'file-earmark-image text-primary' ?> fs-4"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small">
                                        <?= htmlspecialchars($doc['original_name']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.78rem">
                                        <?= round($doc['file_size'] / 1024) ?>KB &middot;
                                        Uploaded by <?= htmlspecialchars($doc['full_name']) ?> &middot;
                                        <?= date('d M Y H:i', strtotime($doc['uploaded_at'])) ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="<?= BASE_URL ?>/documents/view.php?id=<?= $doc['id'] ?>"
                                        class="btn btn-sm btn-outline-primary" target="_blank" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="?compliance_id=<?= $compliance_id ?>&del=<?= $doc['id'] ?>"
                                        onclick="return confirm('Delete this document?')" class="btn btn-sm btn-outline-danger"
                                        title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>