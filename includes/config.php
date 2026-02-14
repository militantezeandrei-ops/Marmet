<?php
/**
 * MARMET Configuration File
 * Database and application settings
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'marmet_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'MARMET');
define('APP_URL', 'http://localhost/MarMet');
define('APP_VERSION', '1.0.0');

// Tax Configuration (12% VAT for Philippines)
define('TAX_RATE', 0.12);

// Pagination
define('ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// File Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../assets/images/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Order Settings
define('ORDER_PREFIX', 'MRM');

// GCash Payment Info (for display to customers)
define('GCASH_NUMBER', '09XX-XXX-XXXX');
define('GCASH_NAME', 'Marmen Marketing');

// Low Stock Alert Threshold (default)
define('DEFAULT_LOW_STOCK_THRESHOLD', 10);

// Timezone
date_default_timezone_set('Asia/Manila');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
