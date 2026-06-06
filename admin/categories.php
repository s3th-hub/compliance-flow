<?php
// admin/categories.php — Compliance Categories (admin only)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db    = db();
$error = '';

// DELETE
if (isset($_GET['del']) && (int)$_GET['del'] > 0) {
    $cid   = (int) $_GET['del'];
    $count = $db->query("SELECT COUNT(*) FROM compliance_records WHERE category_id = $cid")->fetch_row()[0];
    if ($count > 0) {
        $error = 'Cannot delete: category has ' . $count . ' linked records.';
    } else {
        $db->query("DELETE FROM compliance_categories WHERE id = $cid");
        header('Location: categories.php?success=deleted'); exit;
    }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $edit_id     = (int) ($_POST['edit_id'] ?? 0);

    if (!$name) { $error = 'Category name is required.'; }
    if (!$error) {
        if ($edit_id > 0) {
            $stmt = $db->prepare('UPDATE compliance_categories SET name=?, description=? WHERE id=?');
            $stmt->bind_param('ssi', $name, $description, $edit_id);
        } else {
            $stmt = $db->prepare('INSERT INTO compliance_categories (name, description) VALUES (?,?)');
            $stmt->bind_param('ss', $name, $description);
        }
        if ($stmt->execute()) { header('Location: categories.php?success=saved'); exit; }
        $error = 'DB error: ' . $db->error;
        $stmt->close();
    }
}

$categories = $db->query(
    "SELECT cc.*, COUNT(cr.id) AS record_count
     FROM compliance_categories cc
     LEFT JOIN compliance_records cr ON cr.category_id = cc.id
     GROUP BY cc.id ORDER BY cc.name"
)->fetch_all(MYSQLI_ASSOC);

$s = $_GET['success'] ?? '';
$success = $s === 'saved' ? 'Category saved.' : ($s === 'deleted' ? 'Category deleted.' : '');
$pageTitle = 'Compliance Categories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-tags me-2"></i>Compliance Categories</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#catModal" onclick="resetForm()">
        <i class="bi bi-plus-lg me-1"></i>Add Category
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark"><tr><th>#</th><th>Category Name</th><th>Description</th><th>Records</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $i => $c): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td class="fw-semibold"><?= clean($c['name']) ?></td>
                <td class="text-muted small"><?= clean($c['description'] ?? '') ?></td>
                <td><span class="badge bg-primary"><?= $c['record_count'] ?></span></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary"
                        onclick='openEdit(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'
                        data-bs-toggle="modal" data-bs-target="#catModal">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <a href="?del=<?= $c['id'] ?>" onclick="return confirm('Delete this category?')" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="edit_id" id="c_edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="catModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="c_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="c_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function resetForm() {
    document.getElementById('c_edit_id').value = '0';
    document.getElementById('catModalTitle').textContent = 'Add Category';
    document.getElementById('c_name').value        = '';
    document.getElementById('c_description').value = '';
}
function openEdit(c) {
    document.getElementById('c_edit_id').value = c.id;
    document.getElementById('catModalTitle').textContent = 'Edit Category';
    document.getElementById('c_name').value        = c.name;
    document.getElementById('c_description').value = c.description || '';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
