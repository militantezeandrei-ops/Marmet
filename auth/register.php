<?php
/**
 * Registration Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Structured address collection
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    
    // Concatenate for DB storage
    $address = "$street, $barangay, $city, $province, $zip";
    
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $result = registerUser($email, $password, $firstName, $lastName, $phone, $address);
        if ($result['success']) {
            $success = 'Registration successful! You can now login.';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | MARMET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .auth-container {
            max-width: 640px;
        }
        .auth-card {
            padding: 1.5rem; /* Further reduced */
        }
        .auth-header {
            margin-bottom: 1rem; /* Compact header */
        }
        .auth-logo {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        .auth-header h1 {
            font-size: 1.25rem;
            margin-bottom: 0.15rem;
        }
        .auth-header p {
            font-size: 0.875rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem; /* Tighter grid */
        }
        .form-group {
            margin-bottom: 0.75rem; /* Reduced spacing */
        }
        .form-label {
            font-size: 0.8125rem; /* Smaller labels */
            margin-bottom: 0.25rem;
        }
        .form-input {
            padding: 0.625rem 0.875rem; /* Compact inputs */
            font-size: 0.9375rem;
        }
        .btn-lg {
            padding: 0.75rem 1.5rem; /* Match compact design */
        }
        .auth-footer {
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .full-width {
            grid-column: span 2;
        }
        @media (max-width: 768px) {
            .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="auth-logo">
                        <i class="fas fa-gem"></i>
                        <span>MARMET</span>
                    </div>
                    <h1>Create Account</h1>
                    <p>Join us and start shopping</p>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-input" required 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-input" required
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-input" required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">Street Address / House Number *</label>
                            <input type="text" name="street" class="form-input" required
                                   placeholder="e.g. 123 Rizal St."
                                   value="<?php echo htmlspecialchars($_POST['street'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Barangay / Neighborhood *</label>
                            <input type="text" name="barangay" class="form-input" required
                                   placeholder="e.g. Brgy. 1"
                                   value="<?php echo htmlspecialchars($_POST['barangay'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">City / Municipality *</label>
                            <input type="text" name="city" class="form-input" required
                                   placeholder="e.g. Makati City"
                                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Province / State *</label>
                            <input type="text" name="province" class="form-input" required
                                   placeholder="e.g. Metro Manila"
                                   value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Postal / Zip Code *</label>
                            <input type="text" name="zip" class="form-input" required
                                   placeholder="e.g. 1200"
                                   value="<?php echo htmlspecialchars($_POST['zip'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-input" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-input" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 1rem;">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="auth-footer">
                    Already have an account? <a href="login.php">Sign In</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
