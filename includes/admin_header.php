<?php
/**
 * Admin/Staff Header Template
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? 'customer';
$userName = $currentUser['first_name'] ?? 'User';

// Define navigation items based on role
$adminNavItems = [
    'navigation' => [
        ['icon' => 'fa-home', 'text' => 'Dashboard', 'url' => APP_URL . '/admin/dashboard.php', 'id' => 'dashboard'],
        ['icon' => 'fa-chart-line', 'text' => 'Analytics', 'url' => APP_URL . '/admin/analytics.php', 'id' => 'analytics'],
        ['icon' => 'fa-box', 'text' => 'Products', 'url' => APP_URL . '/admin/products.php', 'id' => 'products'],
        ['icon' => 'fa-users', 'text' => 'Customers', 'url' => APP_URL . '/admin/users.php', 'id' => 'users'],
        ['icon' => 'fa-file-alt', 'text' => 'Order Details', 'url' => APP_URL . '/admin/orders.php', 'id' => 'orders'],
        ['icon' => 'fa-undo', 'text' => 'Refunds', 'url' => APP_URL . '/admin/refunds.php', 'id' => 'refunds'],
    ],
    'general' => [
        ['icon' => 'fa-cog', 'text' => 'Settings', 'url' => APP_URL . '/admin/settings.php', 'id' => 'settings'],
        ['icon' => 'fa-question-circle', 'text' => 'Help Center', 'url' => APP_URL . '/admin/help.php', 'id' => 'help'],
    ]
];

$staffNavItems = [
    'navigation' => [
        ['icon' => 'fa-home', 'text' => 'Dashboard', 'url' => APP_URL . '/staff/dashboard.php', 'id' => 'dashboard'],
        ['icon' => 'fa-clipboard-check', 'text' => 'Verify Orders', 'url' => APP_URL . '/staff/verify-order.php', 'id' => 'verify-order'],
        ['icon' => 'fa-box', 'text' => 'Orders', 'url' => APP_URL . '/staff/orders.php', 'id' => 'orders'],
        ['icon' => 'fa-chart-bar', 'text' => 'Reports', 'url' => APP_URL . '/staff/reports.php', 'id' => 'reports'],
    ],
    'general' => [
        ['icon' => 'fa-question-circle', 'text' => 'Help Center', 'url' => APP_URL . '/staff/help.php', 'id' => 'help'],
    ]
];

$navItems = $userRole === 'admin' ? $adminNavItems : $staffNavItems;
$currentPage = $_GET['page'] ?? basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (isset($extraCss)): ?>
    <link rel="stylesheet" href="<?php echo $extraCss; ?>">
    <?php endif; ?>
</head>
<body class="admin-body" data-role="<?php echo $userRole; ?>">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Logo -->
        <div class="admin-logo">
            <div class="admin-logo-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="admin-logo-text"><?php echo $userRole === 'admin' ? 'Admin' : 'ShopManager'; ?></div>
        </div>
        
        <!-- Navigation -->
        <nav class="admin-nav">
            <?php foreach ($navItems as $sectionKey => $section): ?>
            <div class="admin-nav-section">
                <div class="admin-nav-header"><?php echo strtoupper($sectionKey); ?></div>
                <?php foreach ($section as $item): ?>
                <a href="<?php echo $item['url']; ?>" 
                   class="admin-nav-item <?php echo $currentPage === $item['id'] ? 'active' : ''; ?>">
                    <i class="fas <?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['text']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>
        
        <!-- User Profile at Bottom -->
        <div class="admin-sidebar-profile" style="padding: 1rem; border-top: 1px solid var(--admin-border); margin-top: auto;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="admin-avatar" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: var(--admin-primary);">
                    <img src="<?php echo APP_URL; ?>/assets/images/logo.png" alt="<?php echo htmlspecialchars($userName); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div style="flex: 1; overflow: hidden;">
                    <div style="font-weight: 700; font-size: 0.875rem; color: var(--admin-text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($userName); ?></div>
                    <div style="font-size: 0.75rem; color: var(--admin-text-secondary); text-transform: capitalize;"><?php echo $userRole; ?></div>
                </div>
                <a href="<?php echo APP_URL; ?>/auth/logout.php" style="color: var(--admin-text-secondary);"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="admin-main">
        <!-- Header -->
        <header class="admin-header">
            <button class="admin-mobile-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="admin-search" style="flex: 1; max-width: 600px; margin: 0 2rem;">
                <div style="position: relative; width: 100%;">
                    <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--admin-text-muted);"></i>
                    <input type="text" placeholder="Search analytics, SKUs, or orders..." style="width: 100%; padding: 0.625rem 1rem; padding-left: 2.75rem; border: 1px solid var(--admin-border); border-radius: 8px; background: rgba(255,255,255,0.03); color: white;">
                </div>
            </div>
            
            <div class="admin-header-actions" style="display: flex; align-items: center; gap: 1rem;">
                <button class="admin-notifications" style="position: relative; background: rgba(255,255,255,0.03); border: none; color: white; padding: 0.5rem; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-bell"></i>
                    <span style="position: absolute; top: 5px; right: 5px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid var(--admin-bg);"></span>
                </button>
                
                <button class="admin-theme-toggle" id="themeToggle" style="background: rgba(255,255,255,0.03); border: none; color: white; padding: 0.5rem; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="admin-content">
