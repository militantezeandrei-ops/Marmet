<?php
/**
 * User Profile Page
 */
$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();

$user = getCurrentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $result = updateUserProfile(getCurrentUserId(), [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? '')
        ]);
        
        if ($result['success']) {
            $message = 'Profile updated successfully';
            $user = getCurrentUser();
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'change_password') {
        $result = changePassword(
            getCurrentUserId(),
            $_POST['current_password'] ?? '',
            $_POST['new_password'] ?? ''
        );
        
        if ($result['success']) {
            $message = 'Password changed successfully';
        } else {
            $error = $result['message'];
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="section">
    <div class="container" style="max-width: 800px;">
        <h1 class="page-title mb-3">My Profile</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-success mb-3"><i class="fas fa-check"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error mb-3"><i class="fas fa-times"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Profile Info -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Profile Information</h3>
            </div>
            <form method="POST">
                <div class="card-body">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="grid-1-1-mobile" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small style="color: var(--gray-500);">Email cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-input" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
            </div>
            <form method="POST">
                <div class="card-body">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" required minlength="6">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
