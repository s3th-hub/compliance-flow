<?php
// admin/users.php — User management (admin only)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db    = db();
$user  = current_user();
$error = '';

// ---- TOGGLE ACTIVE ----
if (isset($_GET['toggle']) && (int)$_GET['toggle'] > 0) {
    $uid = (int) $_GET['toggle'];
    if ($uid !== $user['id']) { // prevent self-lock
        $db->query("UPDATE users SET is_active = 1 - is_active WHERE id = $uid");
    }
    header('Location: users.php'); exit;
}

// ---- SAVE (add/edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $role      = in_array($_POST['role'] ?? '', ['admin','officer']) ? $_POST['role'] : 'officer';
    $password  = $_POST['password'] ?? '';
    $edit_id   = (int) ($_POST['edit_id'] ?? 0);

    if (!$full_name || !$email) { $error = 'Name and email are required.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error = 'Invalid email.'; }
    elseif ($edit_id === 0 && strlen($password) < 8) { $error = 'Password must be at least 8 characters.'; }

    if (!$error) {
        if ($edit_id > 0) {
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('UPDATE users SET full_name=?, email=?, role=?, password_hash=? WHERE id=?');
                $stmt->bind_param('ssssi', $full_name, $email, $role, $hash, $edit_id);
            } else {
                $stmt = $db->prepare('UPDATE users SET full_name=?, email=?, role=? WHERE id=?');
                $stmt->bind_param('sssi', $full_name, $email, $role, $edit_id);
            }
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?,?,?,?)');
            $stmt->bind_param('ssss', $full_name, $email, $hash, $role);
        }
        if ($stmt->execute()) { header('Location: users.php?success=1'); exit; }
        $error = 'DB error: ' . $db->error;
        $stmt->close();
    }
}

$users     = $db->query('SELECT * FROM users ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$success   = isset($_GET['success']) ? 'User saved successfully.' : '';
$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Users</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
        <i class="bi bi-plus-lg me-1"></i>Add User
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark"><tr>
                <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td class="fw-semibold"><?= clean($u['full_name']) ?></td>
                <td><?= clean($u['email']) ?></td>
                <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : 'info' ?>"><?= ucfirst($u['role']) ?></span></td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary"
                        onclick='openEditUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'
                        data-bs-toggle="modal" data-bs-target="#userModal">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php if ($u['id'] !== $user['id']): ?>
                    <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>"
                       onclick="return confirm('Toggle active status?')">
                        <i class="bi bi-toggle-<?= $u['is_active'] ? 'on' : 'off' ?>"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="edit_id" id="u_edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="u_full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="u_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" id="u_role" class="form-select">
                            <option value="officer">Compliance Officer</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password <span class="text-muted small">(leave blank to keep existing when editing)</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Min 8 characters" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function resetForm() {
    document.getElementById('u_edit_id').value = '0';
    document.getElementById('userModalTitle').textContent = 'Add User';
    ['full_name','email'].forEach(f => document.getElementById('u_'+f).value = '');
    document.getElementById('u_role').value = 'officer';
}
function openEditUser(u) {
    document.getElementById('u_edit_id').value = u.id;
    document.getElementById('userModalTitle').textContent = 'Edit User';
    document.getElementById('u_full_name').value = u.full_name;
    document.getElementById('u_email').value     = u.email;
    document.getElementById('u_role').value      = u.role;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
