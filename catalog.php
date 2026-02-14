<?php
/**
 * Product Catalog
 */
$pageTitle = 'Shop';
require_once __DIR__ . '/includes/db.php';

// Get filter parameters
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build query — only products with images
$where = ["p.is_active = 1", "p.image_url IS NOT NULL", "p.image_url != ''"];
$params = [];

if ($categoryId) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}

if ($search) {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Sort options
$orderBy = match($sort) {
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name' => 'p.name ASC',
    default => 'p.created_at DESC'
};

// Get total count
$totalProducts = db()->count("SELECT COUNT(*) FROM products p WHERE $whereClause", $params);
$totalPages = ceil($totalProducts / $perPage);

// Get products
$products = db()->fetchAll("
    SELECT p.*, c.name as category_name, i.quantity as stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
", $params);

// Get categories for filter (only ones with products that have images)
$categories = db()->fetchAll("
    SELECT c.* FROM categories c 
    WHERE c.is_active = 1 
    AND EXISTS (
        SELECT 1 FROM products p 
        WHERE p.category_id = c.id AND p.is_active = 1 
        AND p.image_url IS NOT NULL AND p.image_url != ''
    )
    ORDER BY c.name
");

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            Shop
            <?php if ($categoryId): 
                $currentCat = array_filter($categories, fn($c) => $c['id'] == $categoryId);
                $currentCat = reset($currentCat);
            ?>
            <span>/ <?php echo htmlspecialchars($currentCat['name'] ?? ''); ?></span>
            <?php endif; ?>
        </h1>
        <span><?php echo $totalProducts; ?> products</span>
    </div>
</div>

<div class="section">
    <div class="container">
        <!-- Compact Filter Bar -->
        <form id="filterForm" method="GET" class="catalog-filter-bar">
            <div class="catalog-filter-search">
                <i class="fas fa-search"></i>
                <input type="text" name="search" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="category" id="categoryFilter" class="catalog-filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="sort" id="sortFilter" class="catalog-filter-select">
                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest</option>
                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low → High</option>
                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High → Low</option>
                <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name A-Z</option>
            </select>
            <button type="submit" class="catalog-filter-btn">
                <i class="fas fa-filter"></i> Filter
            </button>
        </form>
        
        <!-- Products Grid -->
        <?php if (empty($products)): ?>
        <div class="card text-center p-4">
            <i class="fas fa-box-open" style="font-size: 4rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
            <h3>No products found</h3>
            <p style="color: var(--gray-500);">Try adjusting your filters or search term</p>
            <a href="catalog.php" class="btn btn-primary mt-2">Clear Filters</a>
        </div>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    <a href="product.php?id=<?php echo $product['id']; ?>" style="display: block; width: 100%; height: 100%; text-decoration: none;">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </a>
                    <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                    <span class="product-badge">Low Stock</span>
                    <?php elseif ($product['stock'] == 0): ?>
                    <span class="product-badge" style="background: var(--error);">Out of Stock</span>
                    <?php endif; ?>
                    <div class="product-actions">
                        <?php if ($product['stock'] > 0): ?>
                        <button class="product-action-btn add-to-cart" data-product-id="<?php echo $product['id']; ?>" title="Add to Cart">
                            <i class="fas fa-shopping-bag"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="product-info">
                    <span class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                    <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <div class="product-price">
                        <span class="price-current">₱<?php echo number_format($product['price'], 2); ?></span>
                    </div>
                    <div class="stock-status <?php echo $product['stock'] > 10 ? 'stock-in' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out'); ?>">
                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                        <?php echo $product['stock'] > 0 ? $product['stock'] . ' in stock' : 'Out of stock'; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav style="margin-top: 2rem; display: flex; justify-content: center; gap: 0.5rem;">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-ghost">
                <i class="fas fa-chevron-left"></i> Prev
            </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
               class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-ghost'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-ghost">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
