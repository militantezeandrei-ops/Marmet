<?php
/**
 * Header Template
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$currentUser = getCurrentUser();
$cartCount = 0;

if (isLoggedIn() && hasRole('customer')) {
    require_once __DIR__ . '/db.php';
    $cartCount = db()->count("SELECT SUM(quantity) FROM cart WHERE user_id = ?", [getCurrentUserId()]) ?? 0;
}

// Global Category Navigation Logic
require_once __DIR__ . '/db.php';
$globalCategories = db()->fetchAll("
    SELECT c.* FROM categories c 
    WHERE c.is_active = 1 
    AND EXISTS (
        SELECT 1 FROM products p 
        WHERE p.category_id = c.id AND p.is_active = 1 
        AND p.image_url IS NOT NULL AND p.image_url != ''
    )
    ORDER BY c.name
");

$categoryIconMap = [
    "Women's Fashion" => 'fa-female',
    "Men's Fashion" => 'fa-male',
    "Footwear" => 'fa-shoe-prints',
    "Accessories" => 'fa-gem',
    "Lifestyle" => 'fa-heart',
    "Appliances" => 'fa-plug',
    "Bags" => 'fa-shopping-bag',
    "Bottomwear" => 'fa-socks',
    "Gadgets" => 'fa-mobile-alt',
    "Skincare" => 'fa-magic',
    "Topwear" => 'fa-tshirt',
    "Underwear" => 'fa-user-ninja',
    "Food & Drink" => 'fa-utensils',
    "Others" => 'fa-ellipsis-h'
];

$isCheckoutPage = (basename($_SERVER['PHP_SELF']) == 'checkout.php');
$currentCategoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MARMET - Your Premier Fashion & Lifestyle Destination">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <?php if (isset($extraCss)): ?>
    <link rel="stylesheet" href="<?php echo $extraCss; ?>">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="<?php echo !$isCheckoutPage ? 'has-global-filter' : ''; ?>">
    <nav class="navbar">
        <div class="nav-container">
            <button class="nav-toggle" id="navToggle" style="background: none; border: none; font-size: 1.5rem; color: var(--dark); cursor: pointer;">
                <i class="fas fa-bars"></i>
            </button>
            <a href="<?php echo APP_URL; ?>" class="nav-logo">
                <i class="fas fa-shopping-bag" style="color: var(--primary); font-size: 1.5rem;"></i>
                <span style="font-weight: 700; font-size: 1.25rem;">Marmen</span>
            </a>
            
            <div class="nav-search-container">
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" placeholder="Search products..." class="search-input">
                </div>
            </div>

            <div class="nav-menu" id="navMenu">
                <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                    <div class="nav-breadcrumbs" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                        <?php foreach ($breadcrumbs as $index => $crumb): ?>
                            <?php if ($index > 0): ?>
                                <i class="fas fa-chevron-right" style="font-size: 0.625rem; color: var(--gray-400);"></i>
                            <?php endif; ?>
                            
                            <?php if (isset($crumb['url'])): ?>
                                <a href="<?php echo $crumb['url']; ?>" class="nav-link" style="padding: 0; font-weight: 500; color: var(--gray-500);"><?php echo htmlspecialchars($crumb['label']); ?></a>
                            <?php else: ?>
                                <span style="font-weight: 600; color: var(--gray-900);"><?php echo htmlspecialchars($crumb['label']); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a href="<?php echo APP_URL; ?>/catalog.php?cat=electronics" class="nav-link">Electronics</a>
                    <a href="<?php echo APP_URL; ?>/catalog.php?cat=fashion" class="nav-link">Fashion</a>
                    <a href="<?php echo APP_URL; ?>" class="nav-link">Home</a>
                <?php endif; ?>
            </div>
            
            <div class="nav-actions">
                <a href="<?php echo APP_URL; ?>/cart.php" class="nav-cart" style="background: var(--gray-100); color: var(--dark); padding: 0.5rem; border-radius: 8px; position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cartCount > 0): ?>
                    <span style="position: absolute; top: -5px; right: -5px; background: var(--primary); color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 0.625rem;"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <div class="nav-user" style="position: relative;">
                        <button class="user-btn" id="userBtn" style="background: var(--gray-100); border-radius: 8px; padding: 0.5rem 0.75rem; border: none; cursor: pointer; color: var(--dark);">
                            <i class="fas fa-user"></i>
                        </button>
                        <div class="user-dropdown" id="userDropdown" style="position: absolute; top: 100%; right: 0; background: white; border-radius: 12px; box-shadow: var(--shadow-lg); width: 200px; margin-top: 0.5rem; z-index: 1000; overflow: hidden; border: 1px solid var(--gray-200);">
                            <a href="<?php echo APP_URL; ?>/profile.php" style="display: block; padding: 0.75rem 1rem; color: var(--dark); text-decoration: none; border-bottom: 1px solid var(--gray-100);"><i class="fas fa-user-circle" style="margin-right: 0.5rem;"></i> Profile</a>
                            <a href="<?php echo APP_URL; ?>/customer/orders.php" style="display: block; padding: 0.75rem 1rem; color: var(--dark); text-decoration: none; border-bottom: 1px solid var(--gray-100);"><i class="fas fa-box" style="margin-right: 0.5rem;"></i> My Orders</a>
                            <a href="<?php echo APP_URL; ?>/auth/logout.php" style="display: block; padding: 0.75rem 1rem; color: var(--error); text-decoration: none;"><i class="fas fa-sign-out-alt" style="margin-right: 0.5rem;"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo APP_URL; ?>/auth/login.php" style="color: var(--gray-600); font-weight: 600;">Log In</a>
                    <a href="<?php echo APP_URL; ?>/auth/register.php" class="btn btn-primary" style="padding: 0.5rem 1rem; border-radius: 8px;">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php if (!$isCheckoutPage): ?>
    <div class="global-category-bar">
        <div class="nav-container category-scroll-container">
            <a href="<?php echo APP_URL; ?>/catalog.php" class="category-chip <?php echo !$currentCategoryId ? 'active' : ''; ?>">
                <i class="fas fa-grid-2"></i>
                <span>All</span>
            </a>
            <?php foreach ($globalCategories as $cat): 
                $icon = $categoryIconMap[$cat['name']] ?? 'fa-tag';
            ?>
            <a href="<?php echo APP_URL; ?>/catalog.php?category=<?php echo $cat['id']; ?>" class="category-chip <?php echo $currentCategoryId == $cat['id'] ? 'active' : ''; ?>">
                <i class="fas <?php echo $icon; ?>"></i>
                <span><?php echo htmlspecialchars($cat['name']); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <main class="main-content">
