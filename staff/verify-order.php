<?php
/**
 * Order Verification
 */
$pageTitle = 'Verify Orders';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole(['staff', 'admin']);

$message = '';
$error = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    $validStatuses = ['Confirmed', 'Processing', 'Completed', 'Cancelled'];
    
    if ($orderId && in_array($action, $validStatuses)) {
        $updateData = ['order_status' => $action];
        
        if (strcasecmp($action, 'Confirmed') === 0) {
            db()->execute("UPDATE orders SET order_status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?", 
                ['Confirmed', getCurrentUserId(), $orderId]);
            $message = 'Order confirmed successfully';
        } elseif (strcasecmp($action, 'Completed') === 0) {
            db()->execute("UPDATE orders SET order_status = ? WHERE id = ?", ['Completed', $orderId]);
            $message = 'Order marked as Completed.';
        } elseif (strcasecmp($action, 'Cancelled') === 0) {
            try {
                db()->beginTransaction();
                
                // Update Order Status
                db()->execute("UPDATE orders SET order_status = 'Cancelled' WHERE id = ?", [$orderId]);
                
                // Restore Stock
                $items = db()->fetchAll("SELECT product_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
                foreach ($items as $item) {
                    db()->execute("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ?", [$item['quantity'], $item['product_id']]);
                    
                    db()->insert("
                        INSERT INTO stock_movements (product_id, quantity_change, movement_type, reference_id, notes, created_by)
                        VALUES (?, ?, 'return', ?, 'Staff Void/Cancellation', ?)
                    ", [$item['product_id'], $item['quantity'], $orderId, getCurrentUserId()]);
                }
                
                db()->commit();
                $message = 'Order cancelled and stock restored.';
            } catch (Exception $e) {
                db()->rollback();
                $error = 'Failed to cancel order: ' . $e->getMessage();
            }
        } else {
            db()->execute("UPDATE orders SET order_status = ? WHERE id = ?", [$action, $orderId]);
            $message = 'Order status updated to ' . $action;
        }
    }
}

// Get filter
$status = $_GET['status'] ?? '';
$where = '';
$params = [];

if ($status) {
    $where = 'WHERE o.order_status = ?';
    $params = [$status];
}

// Single order view
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId) {
    $order = db()->fetch("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone,
               v.first_name as verified_first, v.last_name as verified_last
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN users v ON o.verified_by = v.id
        WHERE o.id = ?
    ", [$orderId]);
    
    if ($order) {
        $orderItems = db()->fetchAll("
            SELECT oi.*, p.image_url 
            FROM order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ", [$orderId]);
    }
}

// Get all orders
$orders = db()->fetchAll("
    SELECT o.*, u.first_name, u.last_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $where
    ORDER BY o.created_at DESC
", $params);

require_once __DIR__ . '/../includes/admin_header.php';
?>


<div style="padding: 1rem 0;">
    <div class="glass-card" style="padding: 2rem; border-radius: 24px;">
        <?php if ($message): ?>
        <div class="alert alert-success mb-3"><i class="fas fa-check"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Orders List (Always Visible) -->
        <div class="glass-card mb-5" style="padding: 1.5rem; border-radius: 20px; border-style: none; background: rgba(59, 130, 246, 0.03);">
            <form method="GET" class="d-flex gap-3" style="flex-wrap: wrap; align-items: center;">
                <div style="width: 40px; height: 40px; background: var(--admin-primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-filter"></i>
                </div>
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--admin-text-muted); display: block;">Quick Filter</span>
                    <select name="status" class="admin-search" style="min-width: 220px; padding: 0.6rem 1rem; border-radius: 12px; border: 1px solid var(--admin-border); background: var(--admin-content-bg); color: var(--admin-text-primary); font-weight: 600; cursor: pointer; transition: all 0.2s;" onchange="this.form.submit()">
                        <option value="">All Transactions</option>
                        <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>>Pending Orders</option>
                        <option value="Confirmed" <?php echo $status == 'Confirmed' ? 'selected' : ''; ?>>Confirmed Only</option>
                        <option value="Processing" <?php echo $status == 'Processing' ? 'selected' : ''; ?>>Currently Processing</option>
                        <option value="Completed" <?php echo $status == 'Completed' ? 'selected' : ''; ?>>Completed / Delivered</option>
                        <option value="Cancelled" <?php echo $status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled / Void</option>
                    </select>
                </div>
            </form>
        </div>
        
        <div class="glass-card" style="border-radius: 24px; overflow: hidden;">
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr style="background: var(--admin-gradient-surface);">
                            <th style="padding: 1.25rem 1.5rem;">Order Number</th>
                            <th>Customer Name</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Lifecycle Status</th>
                            <th>Created Date</th>
                            <th style="text-align: right; padding-right: 2rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($o['order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($o['first_name'] . ' ' . $o['last_name']); ?></td>
                            <td>₱<?php echo number_format($o['total_amount'], 2); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-weight: 700; color: var(--admin-text-primary);"><?php echo strtoupper($o['payment_method']); ?></span>
                                    <span class="admin-badge admin-badge-<?php echo $o['payment_status'] == 'confirmed' ? 'success' : 'warning'; ?> <?php echo $o['payment_status'] != 'confirmed' ? 'pulse-warning' : ''; ?>">
                                        <?php echo ucfirst($o['payment_status']); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $statusMap = ['Pending' => 'pending', 'Confirmed' => 'verified', 'Processing' => 'processing', 
                                    'Completed' => 'delivered', 'Cancelled' => 'cancelled'];
                                ?>
                                <span class="admin-badge admin-badge-<?php echo $statusMap[$o['order_status']] ?? 'info'; ?>">
                                    <?php echo $o['order_status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                            <td style="text-align: right; padding-right: 1.5rem;">
                                <a href="?id=<?php echo $o['id']; ?>" class="admin-btn admin-btn-primary hover-lift" style="padding: 0.5rem 1rem; border-radius: 10px; font-weight: 600; font-size: 0.8rem;">
                                    Verify Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>


    </div>
</div>

<?php if ($orderId && $order): ?>
<!-- Single Order View - Compact Glassmorphic Modal -->
<div class="admin-modal-overlay">
    <div class="admin-modal-container modal-animate-in">
        <div class="admin-card" style="border: none; display: flex; flex-direction: column;">
            <div class="admin-card-header" style="padding: 1rem 1.5rem; flex-shrink: 0;">
                <div class="admin-card-title">
                    Order #<?php echo htmlspecialchars($order['order_number']); ?>
                </div>
                <a href="verify-order.php" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted); text-decoration: none;">
                    &times;
                </a>
            </div>
            
            <div class="modal-scrollable-body">
                <div class="modal-compact-grid">
                    <!-- Customer Column -->
                    <div class="detail-section-card" style="margin: 0;">
                        <div class="detail-section-title" style="margin-bottom: 0.75rem;">
                            <i class="fas fa-user-circle" style="color: var(--admin-primary);"></i>
                            Customer Identity
                        </div>
                        <div class="info-row" style="padding: 0.5rem 0;">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?></span>
                        </div>
                        <div class="info-row" style="padding: 0.5rem 0;">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['email'] ?? 'No email associated'); ?></span>
                        </div>
                        <div class="info-row" style="padding: 0.5rem 0;">
                            <span class="info-label">Phone</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div style="margin-top: 0.75rem;">
                            <span style="font-size: 0.7rem; color: var(--admin-text-muted); font-weight: 600; display: block; margin-bottom: 0.25rem;">Shipping Destination</span>
                            <div style="font-size: 0.8rem; color: var(--admin-text-primary); font-weight: 500; background: var(--admin-bg-alt); padding: 0.75rem; border-radius: 8px; line-height: 1.4;">
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'No address provided')); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Column -->
                    <div class="detail-section-card" style="margin: 0;">
                        <div class="detail-section-title" style="margin-bottom: 0.75rem;">
                            <i class="fas fa-credit-card" style="color: var(--admin-success);"></i>
                            Financial Status
                        </div>
                        <div class="info-row" style="padding: 0.5rem 0;">
                            <span class="info-label">Method</span>
                            <span class="info-value" style="text-transform: uppercase;"><?php echo $order['payment_method']; ?></span>
                        </div>
                        <div class="info-row" style="padding: 0.5rem 0;">
                            <span class="info-label">Auth</span>
                            <span class="admin-badge admin-badge-<?php echo $order['payment_status'] == 'confirmed' ? 'success' : 'warning'; ?> <?php echo $order['payment_status'] != 'confirmed' ? 'pulse-warning' : ''; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                        <div class="info-row" style="padding: 0.5rem 0;">
                            <span class="info-label">Date</span>
                            <span class="info-value"><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-row" style="padding: 0.5rem 0;">
                            <span class="info-label">Status</span>
                            <span class="admin-badge admin-badge-<?php 
                                $s = strtolower($order['order_status']);
                                echo ($s == 'completed' || $s == 'delivered') ? 'success' : (($s == 'cancelled' || $s == 'void') ? 'cancelled' : (($s == 'pending') ? 'pending' : 'info')); 
                            ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <h4 style="margin: 1rem 0 0.5rem; font-size: 0.9rem; color: var(--admin-text-primary); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Order Items</h4>
                <div class="admin-table-container" style="max-height: 250px; overflow-y: auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="padding: 0.75rem;">Product</th>
                                <th style="padding: 0.75rem;">Price</th>
                                <th style="padding: 0.75rem;">Qty</th>
                                <th style="text-align: right; padding: 0.75rem;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td style="padding: 0.75rem;">
                                <div class="admin-table-product">
                                    <div class="admin-table-product-img" style="width: 32px; height: 32px;">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--admin-bg);">
                                                <i class="fas fa-box-open" style="font-size: 0.8rem; color: var(--admin-text-muted);"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-table-product-info">
                                        <span class="admin-table-product-name" style="font-size: 0.85rem;"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 0.75rem; font-size: 0.85rem;">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td style="padding: 0.75rem; font-size: 0.85rem;"><?php echo $item['quantity']; ?></td>
                            <td style="text-align: right; font-weight: 600; padding: 0.75rem; font-size: 0.85rem;">₱<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right; padding: 0.5rem 0.75rem; font-size: 0.85rem;"><strong>Total:</strong></td>
                                <td style="text-align: right; padding: 0.5rem 0.75rem; font-size: 1rem;"><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <?php 
            $currStatus = strtolower($order['order_status']);
            if ($currStatus !== 'completed' && $currStatus !== 'delivered' && $currStatus !== 'cancelled' && $currStatus !== 'void'): 
            ?>
            <div style="padding: 1rem 1.5rem; background: var(--admin-bg-alt); border-top: 1px solid var(--admin-border); flex-shrink: 0; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h4 style="margin: 0; font-weight: 600; font-size: 0.9rem; color: var(--admin-text-primary);">Next Action</h4>
                </div>
                <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <?php if ($currStatus === 'pending'): ?>
                    <button type="submit" name="action" value="Confirmed" class="admin-btn hover-lift" style="background: var(--admin-success); color: white; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; border: none; font-size: 0.8rem;">
                        <i class="fas fa-check-circle"></i> Confirm Order
                    </button>
                    <?php endif; ?>
                    <?php if ($currStatus === 'confirmed'): ?>
                    <button type="submit" name="action" value="Processing" class="admin-btn hover-lift" style="background: var(--admin-primary); color: white; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; border: none; font-size: 0.8rem;">
                        <i class="fas fa-cog"></i> Process
                    </button>
                    <?php endif; ?>
                    <?php if ($currStatus === 'processing'): ?>
                    <button type="submit" name="action" value="Completed" class="admin-btn hover-lift" style="background: var(--admin-success); color: white; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; border: none; font-size: 0.8rem;">
                        <i class="fas fa-flag-checkered"></i> Mark as Delivered
                    </button>
                    <?php endif; ?>
                    <button type="submit" name="action" value="Cancelled" class="admin-btn admin-btn-ghost hover-lift" style="color: var(--admin-error); font-weight: 600; font-size: 0.8rem;" onclick="return confirm('Immediately cancel and void this order?')">
                        <i class="fas fa-ban"></i> Void
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.querySelector('.admin-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) {
            window.location.href = 'verify-order.php';
        }
    });
    
    // Move modal to body root to avoid stacking issues and fix full-screen blur
    document.body.appendChild(document.querySelector('.admin-modal-overlay'));
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
