<?php
/**
 * Homepage
 */
$pageTitle = 'Fashion & Lifestyle';
require_once __DIR__ . '/includes/db.php';

// Get featured products
$featuredProducts = db()->fetchAll("
    SELECT p.*, c.name as category_name, i.quantity as stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC
    LIMIT 8
");

// Get categories with a sample product image for each
$categories = db()->fetchAll("
    SELECT c.*, 
           (SELECT p.image_url FROM products p WHERE p.category_id = c.id AND p.is_active = 1 AND p.image_url IS NOT NULL AND p.image_url != '' LIMIT 1) as sample_image
    FROM categories c 
    WHERE c.is_active = 1 
    ORDER BY c.name
");

require_once __DIR__ . '/includes/header.php';
?>



<section class="section" id="categories">
    <div class="container">
        <h2 class="section-title">Shop by Category</h2>
        <p class="section-subtitle">Find exactly what you're looking for</p>
        
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
            <a href="catalog.php?category=<?php echo $cat['id']; ?>" class="category-card">
                <?php if (!empty($cat['sample_image'])): ?>
                <img src="<?php echo htmlspecialchars($cat['sample_image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--primary), var(--secondary));"></div>
                <?php endif; ?>
                <div class="category-overlay">
                    <span class="category-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="background: var(--white);">
    <div class="container">
        <h2 class="section-title">Featured Products</h2>
        <p class="section-subtitle">Our top picks for you</p>
        
        <div class="product-grid">
            <?php foreach ($featuredProducts as $product): ?>
            <div class="product-card" style="background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s ease;">
                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-image" style="height: 280px; position: relative; display: block; text-decoration: none;">
                    <?php if (!empty($product['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                    <div class="product-image-placeholder">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <?php endif; ?>
                </a>
                <div class="product-info" style="padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                        <h3 style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <span style="font-size: 1.125rem; font-weight: 700; color: #3b82f6;">₱<?php echo number_format($product['price'], 2); ?></span>
                    </div>
                    <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($product['category_name'] ?? 'General'); ?> • High Quality</p>
                    <button class="btn btn-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>" style="width: 100%; border-radius: 12px; padding: 0.75rem; background: #f1f5f9; color: #0f172a; border: none; font-weight: 600;">
                        <i class="fas fa-shopping-cart" style="margin-right: 0.5rem;"></i> Add to Cart
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="catalog.php" class="btn btn-secondary btn-lg">
                View All Products <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 800;">Active Orders</h2>
            <a href="customer/orders.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">View All History <i class="fas fa-external-link-alt" style="font-size: 0.75rem;"></i></a>
        </div>
        
        <div class="active-orders-grid">
            <div class="admin-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.5rem; background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                <div style="width: 60px; height: 60px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                    <i class="fas fa-truck" style="font-size: 1.5rem;"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 700; font-size: 1.125rem;">Order #ORD-8821</span>
                        <span style="background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.625rem; font-weight: 800;">IN TRANSIT</span>
                    </div>
                    <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">Estimated Delivery: Tomorrow</p>
                    <div style="height: 4px; width: 100%; background: #f1f5f9; border-radius: 2px;">
                        <div style="height: 100%; width: 65%; background: var(--primary); border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
            
            <div class="admin-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.5rem; background: #ffffff; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                <div style="width: 60px; height: 60px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #64748b;">
                    <i class="fas fa-box" style="font-size: 1.5rem;"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 700; font-size: 1.125rem;">Order #ORD-7752</span>
                        <span style="background: rgba(245, 158, 11, 0.1); color: var(--warning); padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.625rem; font-weight: 800;">PROCESSING</span>
                    </div>
                    <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">Warehouse Confirmation</p>
                    <div style="height: 4px; width: 100%; background: #f1f5f9; border-radius: 2px;">
                        <div style="height: 100%; width: 30%; background: var(--warning); border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">

<?php require_once __DIR__ . '/includes/footer.php'; ?>
