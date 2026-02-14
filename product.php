<?php
/**
 * Single Product Page
 */
require_once __DIR__ . '/includes/db.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$productId) {
    header('Location: catalog.php');
    exit;
}

$product = db()->fetch("
    SELECT p.*, c.name as category_name, i.quantity as stock, s.name as supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN inventory i ON p.id = i.product_id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.id = ? AND p.is_active = 1
", [$productId]);

if (!$product) {
    header('Location: catalog.php');
    exit;
}

$pageTitle = $product['name'];

// Get related products
$relatedProducts = db()->fetchAll("
    SELECT p.*, c.name as category_name, i.quantity as stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
    LIMIT 4
", [$product['category_id'], $productId]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-bottom: 4rem;">
            <!-- Product Image -->
            <div class="card" style="height: 500px; overflow: hidden; border-radius: var(--radius-xl);">
                <?php if (!empty($product['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(45deg, var(--gray-200), var(--gray-100));">
                    <i class="fas fa-box-open" style="font-size: 8rem; color: var(--gray-400);"></i>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Info -->
            <div>
                <nav style="margin-bottom: 1rem; color: var(--gray-500); font-size: 0.875rem;">
                    <a href="catalog.php" style="color: var(--primary);">Shop</a>
                    <span style="margin: 0 0.5rem;">/</span>
                    <a href="catalog.php?category=<?php echo $product['category_id']; ?>" style="color: var(--primary);">
                        <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                    </a>
                    <span style="margin: 0 0.5rem;">/</span>
                    <span><?php echo htmlspecialchars($product['name']); ?></span>
                </nav>
                
                <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($product['name']); ?>
                </h1>
                
                <span class="badge badge-primary mb-2"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary); margin: 1.5rem 0;">
                    ₱<?php echo number_format($product['price'], 2); ?>
                </div>
                
                <div class="stock-status <?php echo $product['stock'] > 10 ? 'stock-in' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out'); ?>" style="font-size: 1rem; margin-bottom: 1.5rem;">
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                    <?php if ($product['stock'] > 0): ?>
                        <?php echo $product['stock']; ?> in stock
                    <?php else: ?>
                        Out of stock
                    <?php endif; ?>
                </div>
                
                <p style="color: var(--gray-600); line-height: 1.8; margin-bottom: 2rem;">
                    <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
                </p>
                
                <?php if ($product['stock'] > 0): ?>
                <form id="addToCartForm" style="display: flex; gap: 1rem; align-items: center; margin-bottom: 2rem;">
                    <div class="quantity-control" style="display: flex; align-items: center; gap: 0.5rem;">
                        <button type="button" class="quantity-btn qty-minus"><i class="fas fa-minus"></i></button>
                        <input type="number" class="quantity-input form-input" id="qtyInput" value="1" min="1" max="<?php echo $product['stock']; ?>" style="width: 70px; text-align: center;">
                        <button type="button" class="quantity-btn qty-plus"><i class="fas fa-plus"></i></button>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <button type="button" class="btn btn-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>" style="border-radius: 12px; font-weight: 700;">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                         <button type="button" class="btn btn-buy-now buy-now" data-product-id="<?php echo $product['id']; ?>" style="border-radius: 12px; font-weight: 700;">
                            Buy Now
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <button class="btn btn-secondary btn-lg" disabled style="opacity: 0.5;">
                    <i class="fas fa-times"></i> Out of Stock
                </button>
                <?php endif; ?>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const qtyInput = document.getElementById('qtyInput');
                    const maxStock = <?php echo $product['stock']; ?>;
                    
                    document.querySelector('.qty-minus')?.addEventListener('click', function() {
                        if (qtyInput.value > 1) qtyInput.value--;
                    });
                    document.querySelector('.qty-plus')?.addEventListener('click', function() {
                        if (parseInt(qtyInput.value) < maxStock) qtyInput.value++;
                    });

                    // Add to Cart
                    document.querySelector('.add-to-cart')?.addEventListener('click', function() {
                        const productId = this.dataset.productId;
                        const qty = qtyInput.value;
                        if (typeof addToCart === 'function') {
                            addToCart(productId, qty);
                        }
                    });

                    // Buy Now / Checkout
                    document.querySelector('.buy-now')?.addEventListener('click', function() {
                        const productId = this.dataset.productId;
                        const qty = qtyInput.value;
                        if (typeof buyNow === 'function') {
                            buyNow(productId, qty);
                        }
                    });
                });
                </script>
                
                <div style="border-top: 1px solid var(--gray-200); padding-top: 1.5rem; margin-top: 1.5rem;">
                    <p style="color: var(--gray-500); font-size: 0.875rem;">
                        <strong>SKU:</strong> <?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
        <h2 class="section-title">Related Products</h2>
        <div class="product-grid">
            <?php foreach ($relatedProducts as $related): ?>
            <div class="product-card">
                <div class="product-image">
                    <a href="product.php?id=<?php echo $related['id']; ?>" style="display: block; width: 100%; height: 100%; text-decoration: none;">
                        <?php if (!empty($related['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($related['image_url']); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                        <?php else: ?>
                        <div class="product-image-placeholder">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="product-info">
                    <span class="product-category"><?php echo htmlspecialchars($related['category_name']); ?></span>
                    <h3 class="product-name"><?php echo htmlspecialchars($related['name']); ?></h3>
                    <div class="product-price">
                        <span class="price-current">₱<?php echo number_format($related['price'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
