<?php
/**
 * User Management
 */
$pageTitle = 'Users';
$_GET['page'] = 'users';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('admin');

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'update_role' && $userId) {
        $newRole = $_POST['role'] ?? '';
        if (in_array($newRole, ['customer', 'staff', 'admin'])) {
            db()->execute("UPDATE users SET role = ? WHERE id = ?", [$newRole, $userId]);
            $message = 'User role updated';
        }
    } elseif ($action === 'toggle_status' && $userId) {
        db()->execute("UPDATE users SET is_active = NOT is_active WHERE id = ?", [$userId]);
        $message = 'User status updated';
    } elseif ($action === 'create') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        
        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            $error = 'All fields are required';
        } else {
            $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existing) {
                $error = 'Email already exists';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db()->insert("INSERT INTO users (email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)",
                    [$email, $hash, $firstName, $lastName, $role]);
                $message = 'User created successfully';
            }
        }
    }
}

// Get users
$roleFilter = $_GET['role'] ?? '';
$where = $roleFilter ? "WHERE role = ?" : "";
$params = $roleFilter ? [$roleFilter] : [];

$users = db()->fetchAll("
    SELECT u.*, 
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count
    FROM users u
    $where
    ORDER BY u.created_at DESC
", $params);

require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $message; ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
</script>
<?php endif; ?>

<!-- Filter & Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
        <select name="role" style="padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px; background: var(--admin-content-bg); color: var(--admin-text-primary);" onchange="this.form.submit()">
            <option value="">All Roles</option>
            <option value="customer" <?php echo $roleFilter == 'customer' ? 'selected' : ''; ?>>Customers</option>
            <option value="staff" <?php echo $roleFilter == 'staff' ? 'selected' : ''; ?>>Staff</option>
            <option value="admin" <?php echo $roleFilter == 'admin' ? 'selected' : ''; ?>>Admins</option>
        </select>
    </form>
    <button onclick="document.getElementById('addUserModal').style.display='flex'" class="admin-btn admin-btn-primary">
        <i class="fas fa-plus"></i> Add User
    </button>
</div>

<!-- Users Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">User Management</div>
    </div>
    <div class="admin-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Orders</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <select name="role" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; border: 1px solid var(--admin-border); border-radius: 4px;" onchange="this.form.submit()">
                                <option value="customer" <?php echo $user['role'] == 'customer' ? 'selected' : ''; ?>>Customer</option>
                                <option value="staff" <?php echo $user['role'] == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </form>
                    </td>
                    <td><?php echo $user['order_count']; ?></td>
                    <td>
                        <span class="admin-badge <?php echo $user['is_active'] ? 'admin-badge-published' : 'admin-badge-inactive'; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="admin-btn admin-btn-ghost" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">
                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999;">
    <div class="admin-card" style="max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div class="admin-card-header">
            <div class="admin-card-title">Add New User</div>
            <button onclick="document.getElementById('addUserModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);">&times;</button>
        </div>
        <form method="POST">
            <div class="admin-card-body">
                <input type="hidden" name="action" value="create">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">First Name</label>
                    <input type="text" name="first_name" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;" required>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Last Name</label>
                    <input type="text" name="last_name" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;" required>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Email</label>
                    <input type="email" name="email" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;" required>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Password</label>
                    <input type="password" name="password" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;" required minlength="6">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Role</label>
                    <select name="role" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" onclick="document.getElementById('addUserModal').style.display='none'" class="admin-btn admin-btn-ghost">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1;">Create User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
