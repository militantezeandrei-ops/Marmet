<?php
/**
 * Shopping Cart - ShopEase Premium UI
 */
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();
requireRole('customer');

$userId = getCurrentUserId();

// Get ALL cart items (active and saved)
$allCartItems = db()->fetchAll("
    SELECT c.*, p.name, p.price, p.image_url, i.quantity as stock, cat.name as category_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN inventory i ON p.id = i.product_id
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
", [$userId]);

$activeItems = array_filter($allCartItems, fn($item) => $item['is_saved_for_later'] == 0);
$savedItems = array_filter($allCartItems, fn($item) => $item['is_saved_for_later'] == 1);

$breadcrumbs = [
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'Cart']
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width: 1000px;">

    <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 2rem; color: var(--gray-900);">Shopping Cart</h1>
    
    <?php if (empty($activeItems) && empty($savedItems)): ?>
    <div class="se-card text-center py-5">
        <i class="fas fa-shopping-bag" style="font-size: 4rem; color: var(--gray-300); margin-bottom: 1.5rem;"></i>
        <h3 style="font-weight: 700;">Your cart is empty</h3>
        <p style="color: var(--gray-500); margin-bottom: 1.5rem;">Start shopping to add items to your cart</p>
        <a href="catalog.php" class="btn btn-primary px-4 Py-2" style="border-radius: 12px; font-weight: 600;">Browse Products</a>
    </div>
    <?php else: ?>
    
    <div class="se-cart-layout" style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        
        <!-- Active Items Section -->
        <div class="active-items-section">
            <?php if (!empty($activeItems)): ?>
                <div style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; padding-left: 0.5rem;">
                    <input type="checkbox" id="selectAllItems" style="transform: scale(1.3); cursor: pointer;">
                    <label for="selectAllItems" style="font-weight: 700; color: var(--gray-700); cursor: pointer; margin: 0;">Select All Items</label>
                </div>
                
                <div class="cart-items-list">
                    <?php foreach ($activeItems as $item): ?>
                    <div class="se-card se-item-card mb-3" data-cart-id="<?php echo $item['id']; ?>" data-product-id="<?php echo $item['product_id']; ?>" data-price="<?php echo $item['price']; ?>" data-quantity="<?php echo $item['quantity']; ?>">
                        <!-- Selection Checkbox -->
                        <div style="flex-shrink: 0;">
                            <input type="checkbox" class="item-checkbox" data-cart-id="<?php echo $item['id']; ?>" style="transform: scale(1.3); cursor: pointer;">
                        </div>

                        <!-- Circular Image -->
                        <div class="se-circle-img">
                            <?php if ($item['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-box-open" style="font-size: 1.5rem; color: var(--gray-400);"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div style="flex-grow: 1;">
                            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p style="color: var(--gray-500); font-size: 0.875rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($item['category_name'] ?? 'General'); ?></p>
                            <div class="se-price">₱<?php echo number_format($item['price'], 2); ?></div>
                        </div>

                        <!-- Controls -->
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 1rem;">
                            <button class="se-trash-btn save-for-later-btn" title="Save for Later" style="font-size: 0.875rem; color: var(--se-primary);">
                                <i class="fas fa-bookmark"></i> Save
                            </button>
                            <div style="display: flex; align-items: center; gap: 1.5rem;">
                                <div class="se-qty-control">
                                    <button class="se-qty-btn qty-minus"><i class="fas fa-minus"></i></button>
                                    <span class="qty-display" style="font-weight: 600; min-width: 20px; text-align: center;"><?php echo $item['quantity']; ?></span>
                                    <button class="se-qty-btn qty-plus" data-max="<?php echo $item['stock']; ?>"><i class="fas fa-plus"></i></button>
                                </div>
                                <button class="se-trash-btn remove-from-cart-btn" title="Remove">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="se-card text-center py-4 mb-4">
                    <p style="color: var(--gray-500); margin: 0;">No active items in your cart.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Saved for Later Section -->
        <?php if (!empty($savedItems)): ?>
        <div class="saved-section">
            <h2 class="se-save-later-title">Saved for Later</h2>
            <div class="se-saved-item-grid">
                <?php foreach ($savedItems as $item): ?>
                <div class="se-card se-saved-card" data-product-id="<?php echo $item['product_id']; ?>">
                    <div class="se-saved-img">
                         <?php if ($item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">
                        <?php else: ?>
                            <i class="fas fa-box-open" style="font-size: 2rem; color: var(--gray-400);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="width: 100%;">
                        <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($item['name']); ?></h4>
                        <div style="font-weight: 600; color: var(--gray-500); font-size: 0.875rem; margin-bottom: 0.75rem;">₱<?php echo number_format($item['price'], 2); ?></div>
                        <button class="btn btn-block move-to-cart-btn" style="background: #eef2ff; color: #4f46e5; border: none; font-size: 0.875rem; font-weight: 700; border-radius: 8px; padding: 0.5rem;">
                            Move to Cart
                        </button>
                    </div>
                    <button class="se-trash-btn remove-from-cart-btn" style="position: absolute; top: 1rem; right: 1rem;" data-product-id="<?php echo $item['product_id']; ?>">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sticky Summary Bar -->
        <div style="position: sticky; bottom: 2rem; margin-top: 2rem; z-index: 100;">
            <div class="se-card" style="border-radius: 24px; padding: 2rem; border: 1px solid rgba(59, 130, 246, 0.1);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; align-items: center;">
                    <div>
                        <div style="color: var(--gray-500); font-size: 0.875rem; margin-bottom: 0.25rem;">Subtotal (<span id="selectedCount">0</span> items)</div>
                        <div style="font-size: 1.25rem; font-weight: 600;" id="selectedSubtotal">₱0.00</div>
                    </div>
                    <div>
                        <div style="color: var(--gray-500); font-size: 0.875rem; margin-bottom: 0.25rem;">Shipping</div>
                        <div style="font-size: 1.25rem; font-weight: 600; color: var(--success);">Free</div>
                    </div>
                    <div>
                        <div style="color: var(--gray-500); font-size: 0.875rem; margin-bottom: 0.25rem;">Total</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--se-primary);" id="selectedTotal">₱0.00</div>
                    </div>
                    <div style="grid-column: span 1 / -1;">
                        <button id="checkoutBtn" class="se-checkout-btn" disabled>
                            Proceed to Checkout <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllItems');
    const itemCheckboxes = document.querySelectorAll('.active-items-section .item-checkbox');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const taxRate = <?php echo TAX_RATE; ?>;

    function updateSummary() {
        let subtotal = 0;
        let count = 0;
        let selectedIds = [];

        itemCheckboxes.forEach(cb => {
            if (cb.checked) {
                const item = cb.closest('.se-item-card');
                const price = parseFloat(item.dataset.price);
                const qty = parseInt(item.dataset.quantity);
                subtotal += price * qty;
                count++;
                selectedIds.push(cb.dataset.cartId);
            }
        });

        const tax = subtotal * taxRate;
        const total = subtotal + tax;

        document.getElementById('selectedSubtotal').textContent = '₱' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('selectedTotal').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('selectedCount').textContent = count;

        checkoutBtn.disabled = count === 0;
        
        if (count > 0) {
            checkoutBtn.onclick = function() {
                window.location.href = 'checkout.php?cart_item_ids=' + selectedIds.join(',');
            };
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            itemCheckboxes.forEach(cb => cb.checked = selectAll.checked);
            updateSummary();
        });
    }

    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!cb.checked) selectAll.checked = false;
            updateSummary();
        });
    });

    // Quantity Controls
    document.querySelectorAll('.qty-minus').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.se-item-card');
            const display = card.querySelector('.qty-display');
            let current = parseInt(card.dataset.quantity);
            if (current > 1) {
                current--;
                updateCartItem(card.dataset.productId, current, card, display);
            }
        });
    });

    document.querySelectorAll('.qty-plus').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.se-item-card');
            const display = card.querySelector('.qty-display');
            const max = parseInt(this.dataset.max);
            let current = parseInt(card.dataset.quantity);
            if (current < max) {
                current++;
                updateCartItem(card.dataset.productId, current, card, display);
            }
        });
    });

    function updateCartItem(productId, qty, card, display) {
        fetch('/MarMet/api/cart.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'update', product_id: productId, quantity: qty })
        }).then(res => res.json()).then(data => {
            if (data.success) {
                card.dataset.quantity = qty;
                display.textContent = qty;
                updateSummary();
            }
        });
    }

    // Actions
    document.querySelectorAll('.remove-from-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.se-card');
            const productId = card.dataset.productId || this.dataset.productId;
            Swal.fire({
                title: 'Are you sure?',
                text: "Remove this item from your cart?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, remove it'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/MarMet/api/cart.php', {
                        method: 'POST',
                        body: JSON.stringify({ action: 'remove', product_id: productId })
                    }).then(() => window.location.reload());
                }
            });
        });
    });

    document.querySelectorAll('.save-for-later-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.se-item-card');
            fetch('/MarMet/api/cart.php', {
                method: 'POST',
                body: JSON.stringify({ action: 'save_for_later', product_id: card.dataset.productId })
            }).then(() => window.location.reload());
        });
    });

    document.querySelectorAll('.move-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.se-saved-card');
            fetch('/MarMet/api/cart.php', {
                method: 'POST',
                body: JSON.stringify({ action: 'move_to_cart', product_id: card.dataset.productId || this.closest('.se-card').dataset.productId })
            }).then(() => window.location.reload());
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
