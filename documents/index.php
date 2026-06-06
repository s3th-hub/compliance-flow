<?php
// documents/index.php — List all documents across all compliance records
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = db();

$documents = $db->query(
    "SELECT d.*, cr.title AS compliance_title, b.business_name, u.full_name AS uploader
     FROM documents d
     JOIN compliance_records cr ON cr.id = d.compliance_id
     JOIN businesses b ON b.id = cr.business_id
     JOIN users u ON u.id = d.uploaded_by
     ORDER BY d.uploaded_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'All Documents';
require_once __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-folder2-open me-2"></i>All Documents</h4>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark"><tr>
                    <th>#</th><th>File</th><th>Compliance Item</th><th>Business</th><th>Size</th><th>Uploaded By</th><th>Date</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($documents)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No documents uploaded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($documents as $i => $d): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <i class="bi bi-<?= str_contains($d['file_type'], 'pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-primary' ?> me-1"></i>
                        <?= clean($d['original_name']) ?>
                    </td>
                    <td><?= clean($d['compliance_title']) ?></td>
                    <td><?= clean($d['business_name']) ?></td>
                    <td><?= round($d['file_size'] / 1024) ?>KB</td>
                    <td><?= clean($d['uploader']) ?></td>
                    <td><?= date('d M Y', strtotime($d['uploaded_at'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/documents/view.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/documents/upload.php?compliance_id=<?= $d['compliance_id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-folder2"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
