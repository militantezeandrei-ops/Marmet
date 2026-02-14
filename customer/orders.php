<?php
/**
 * Customer Orders
 */
$pageTitle = 'My Orders';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('customer');

$userId = getCurrentUserId();
$success = isset($_GET['success']) && isset($_GET['order']);
$orderNumber = $_GET['order'] ?? '';

// Get orders
$orders = db()->fetchAll("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
", [$userId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="container" style="max-width: 900px;">
        <h1 class="page-title mb-3">My Orders</h1>
        
        <?php if ($success): ?>
        <div class="alert alert-success mb-3">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Order placed successfully!</strong><br>
                Your order <strong><?php echo htmlspecialchars($orderNumber); ?></strong> has been received.
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['cancelled'])): ?>
        <div class="alert alert-success mb-3">
            <i class="fas fa-check-circle"></i>
            <div><strong>Order cancelled successfully.</strong> Your stock has been restored.</div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger mb-3">
            <i class="fas fa-exclamation-circle"></i>
            <div><strong>Error:</strong> <?php echo htmlspecialchars($_GET['error']); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>

        <div class="card text-center p-4">
            <i class="fas fa-box-open" style="font-size: 4rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
            <h3>No orders yet</h3>
            <p style="color: var(--gray-500);">Start shopping to see your orders here</p>
            <a href="<?php echo APP_URL; ?>/catalog.php" class="btn btn-primary mt-2">Browse Products</a>
        </div>
        <?php else: ?>
        
        <div class="card">
            <?php foreach ($orders as $order): ?>
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--gray-200);">
                <div class="mobile-stack" style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem;">
                    <div class="mobile-text-left">
                        <h3 style="margin-bottom: 0.5rem;">Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                        <p style="color: var(--gray-500); font-size: 0.875rem;">
                            Placed on <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                        </p>
                        <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                            <?php echo $order['item_count']; ?> item(s) • 
                            <?php echo strtoupper($order['payment_method']); ?>
                        </p>
                    </div>
                    
                    <div class="mobile-text-left" style="text-align: right;">
                        <div style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">
                            ₱<?php echo number_format($order['total_amount'], 2); ?>
                        </div>
                        
                        <?php
                        $statusColors = [
                            'pending' => 'warning',
                            'confirmed' => 'info',
                            'processing' => 'info',
                            'completed' => 'success',
                            'delivered' => 'success',
                            'cancelled' => 'error'
                        ];
                        $paymentColors = [
                            'pending' => 'warning',
                            'confirmed' => 'success',
                            'failed' => 'error'
                        ];
                        
                        $s = strtolower($order['order_status']);
                        ?>
                        
                        <span class="badge badge-<?php echo $statusColors[$s] ?? 'info'; ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                        <span class="badge badge-<?php echo $paymentColors[$order['payment_status']] ?? 'warning'; ?>">
                            Payment: <?php echo ucfirst($order['payment_status']); ?>
                        </span>

                        <?php 
                        $s = strtolower($order['order_status']);
                        if ($s === 'pending' || $s === 'confirmed'): 
                        ?>
                        <div style="margin-top: 0.75rem;">
                            <a href="cancel_order.php?id=<?php echo $order['id']; ?>" 
                               class="btn btn-sm btn-outline-danger" 
                               onclick="return confirm('Are you sure you want to cancel this order? This will restore the items to our stock.')"
                               style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                <i class="fas fa-times-circle"></i> Cancel Order
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <details style="margin-top: 1rem;">
                    <summary style="cursor: pointer; color: var(--primary); font-weight: 500;">
                        View Details
                    </summary>
                    <div style="margin-top: 1rem; padding: 1rem; background: var(--gray-100); border-radius: var(--radius);">
                        <?php
                        $items = db()->fetchAll("
                            SELECT oi.*, p.image_url 
                            FROM order_items oi 
                            LEFT JOIN products p ON oi.product_id = p.id 
                            WHERE oi.order_id = ?
                        ", [$order['id']]);
                        ?>
                        <div class="responsive-table-container">
                            <table class="order-details-table" style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                                <thead>
                                    <tr style="text-align: left; border-bottom: 1px solid var(--gray-300);">
                                        <th style="padding: 0.75rem 0;">Thumbnail</th>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th style="text-align: right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td style="padding: 1rem 0;">
                                            <div class="se-circle-img" style="width: 50px; height: 50px;">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?php echo APP_URL . '/' . htmlspecialchars($item['image_url']); ?>" alt="">
                                                <?php else: ?>
                                                    <i class="fas fa-box-open" style="font-size: 1rem; color: var(--gray-400);"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-label="Product"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td data-label="Qty"><?php echo $item['quantity']; ?></td>
                                        <td data-label="Price">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td data-label="Total" style="text-align: right; font-weight: 700;">₱<?php echo number_format($item['total_price'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-300);">
                            <strong>Shipping Address:</strong><br>
                            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                        </div>

                        <?php 
                        $s = strtolower($order['order_status']);
                        if ($s === 'pending' || $s === 'confirmed'): 
                        ?>
                        <div style="margin-top: 1.5rem; text-align: right; border-top: 1px solid var(--gray-300); padding-top: 1rem;">
                            <a href="cancel_order.php?id=<?php echo $order['id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to cancel this order? This will restore the items to our stock.')">
                                <i class="fas fa-times-circle"></i> Cancel Order
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
