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
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="margin-bottom: 0.5rem;">Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                        <p style="color: var(--gray-500); font-size: 0.875rem;">
                            Placed on <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                        </p>
                        <p style="font-size: 0.875rem; margin-top: 0.5rem;">
                            <?php echo $order['item_count']; ?> item(s) • 
                            <?php echo strtoupper($order['payment_method']); ?>
                        </p>
                    </div>
                    
                    <div style="text-align: right;">
                        <div style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">
                            ₱<?php echo number_format($order['total_amount'], 2); ?>
                        </div>
                        
                        <?php
                        $statusColors = [
                            'pending' => 'warning',
                            'verified' => 'info',
                            'processing' => 'info',
                            'shipped' => 'primary',
                            'delivered' => 'success',
                            'cancelled' => 'error'
                        ];
                        $paymentColors = [
                            'pending' => 'warning',
                            'confirmed' => 'success',
                            'failed' => 'error'
                        ];
                        ?>
                        
                        <span class="badge badge-<?php echo $statusColors[$order['order_status']] ?? 'info'; ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                        <span class="badge badge-<?php echo $paymentColors[$order['payment_status']] ?? 'warning'; ?>">
                            Payment: <?php echo ucfirst($order['payment_status']); ?>
                        </span>
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
                        <table style="width: 100%; font-size: 0.875rem;">
                            <thead>
                                <tr style="text-align: left;">
                                    <th style="padding: 0.5rem 0;">Thumbnail</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th style="text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td style="padding: 0.5rem 0;">
                                        <div class="se-circle-img" style="width: 40px; height: 40px;">
                                            <?php if (!empty($item['image_url'])): ?>
                                                <img src="<?php echo APP_URL . '/' . htmlspecialchars($item['image_url']); ?>" alt="">
                                            <?php else: ?>
                                                <i class="fas fa-box-open" style="font-size: 0.875rem; color: var(--gray-400);"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td style="text-align: right;">₱<?php echo number_format($item['total_price'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-300);">
                            <strong>Shipping Address:</strong><br>
                            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                        </div>
                    </div>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
