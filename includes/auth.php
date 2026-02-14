<?php
/**
 * Authentication Functions
 * User login, registration, and access control
 */

require_once __DIR__ . '/db.php';

/**
 * Register a new user
 */
function registerUser($email, $password, $firstName, $lastName, $phone = '', $address = '') {
    $db = db();
    
    // Check if email already exists
    $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $sql = "INSERT INTO users (email, password, first_name, last_name, phone, address, role) 
            VALUES (?, ?, ?, ?, ?, ?, 'customer')";
    
    try {
        $userId = $db->insert($sql, [$email, $hashedPassword, $firstName, $lastName, $phone, $address]);
        return ['success' => true, 'user_id' => $userId];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

/**
 * Authenticate user login
 */
function loginUser($email, $password) {
    $db = db();
    
    $user = $db->fetch("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role'];
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser() {
    session_unset();
    session_destroy();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user details
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = db();
    return $db->fetch("SELECT * FROM users WHERE id = ?", [getCurrentUserId()]);
}

/**
 * Check if current user has specific role
 */
function hasRole($role) {
    $currentRole = getCurrentUserRole();
    
    if (is_array($role)) {
        return in_array($currentRole, $role);
    }
    
    return $currentRole === $role;
}

/**
 * Require authentication - redirect if not logged in
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Require specific role(s) - redirect if not authorized
 */
function requireRole($roles) {
    requireAuth();
    
    if (!hasRole($roles)) {
        header('Location: ' . APP_URL . '/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Redirect based on user role
 */
function redirectToDashboard() {
    $role = getCurrentUserRole();
    
    switch ($role) {
        case 'admin':
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            break;
        case 'staff':
            header('Location: ' . APP_URL . '/staff/dashboard.php');
            break;
        case 'customer':
            header('Location: ' . APP_URL . '/index.php');
            break;
        default:
            header('Location: ' . APP_URL . '/index.php');
    }
    exit;
}

/**
 * Update user profile
 */
function updateUserProfile($userId, $data) {
    $db = db();
    
    $sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, updated_at = NOW() 
            WHERE id = ?";
    
    try {
        $db->execute($sql, [$data['first_name'], $data['last_name'], $data['phone'], $data['address'], $userId]);
        
        // Update session name
        $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Update failed'];
    }
}

/**
 * Change user password
 */
function changePassword($userId, $currentPassword, $newPassword) {
    $db = db();
    
    $user = $db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
    
    if (!password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hashedPassword, $userId]);
    
    return ['success' => true];
}
