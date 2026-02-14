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
    
    $validStatuses = ['verified', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if ($orderId && in_array($action, $validStatuses)) {
        $updateData = ['order_status' => $action];
        
        if ($action === 'verified') {
            db()->execute("UPDATE orders SET order_status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?", 
                [$action, getCurrentUserId(), $orderId]);
            $message = 'Order verified successfully';
        } else {
            db()->execute("UPDATE orders SET order_status = ? WHERE id = ?", [$action, $orderId]);
            $message = 'Order status updated to ' . ucfirst($action);
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

<div style="margin-bottom: 2.5rem; padding: 2.5rem; background: var(--admin-gradient-primary); border-radius: 32px; color: white; box-shadow: var(--admin-shadow-premium); position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center;">
    <div style="position: absolute; top: 0; right: 0; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%); transform: translate(30%, -30%);"></div>
    <div style="position: relative; z-index: 1;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
             <span class="pulse-primary" style="width: 10px; height: 10px; background: #4ade80; border-radius: 50%; display: inline-block;"></span>
             <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.8;">Admin Portal v2.0</span>
        </div>
        <h1 style="font-size: 3rem; font-weight: 900; letter-spacing: -0.04em; margin: 0; line-height: 1;">Order Verification</h1>
        <p style="opacity: 0.9; font-size: 1.25rem; font-weight: 500; margin-top: 0.5rem;">Real-time management system</p>
    </div>
</div>

<div style="padding: 1rem 0;">
    <div class="glass-card" style="padding: 2rem; border-radius: 24px;">
        <?php if ($message): ?>
        <div class="alert alert-success mb-3"><i class="fas fa-check"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($orderId && $order): ?>
        <!-- Single Order View -->
        <div class="glass-card mb-4" style="border-radius: 20px; overflow: hidden; background: var(--admin-content-bg);">
            <div class="admin-card-header" style="padding: 1.75rem 2rem; background: var(--admin-gradient-surface); border-bottom: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--admin-text-muted); display: block; margin-bottom: 0.25rem;">Transaction ID</span>
                    <h3 style="font-size: 1.5rem; font-weight: 800; color: var(--admin-text-primary); margin: 0;">#<?php echo htmlspecialchars($order['order_number']); ?></h3>
                </div>
                <a href="verify-order.php" class="admin-btn admin-btn-ghost hover-lift" style="background: var(--admin-bg); border-radius: 12px; padding: 0.75rem 1.25rem;">
                    <i class="fas fa-arrow-left"></i> &nbsp; Back to List
                </a>
            </div>
            <div class="admin-card-body" style="padding: 2.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="margin-bottom: 1rem;">Customer Information</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                        <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 1.25rem; font-size: 1rem; color: var(--admin-text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Order Details</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <p style="color: var(--admin-text-secondary);"><strong style="color: var(--admin-text-primary);">Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></p>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <strong style="color: var(--admin-text-primary);">Payment:</strong> 
                                <span style="font-size: 0.875rem; font-weight: 600; color: var(--admin-text-primary);"><?php echo strtoupper($order['payment_method']); ?></span>
                                <span class="admin-badge admin-badge-<?php echo $order['payment_status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <strong style="color: var(--admin-text-primary);">Status:</strong> 
                                <span class="admin-badge admin-badge-<?php echo $order['order_status'] == 'delivered' ? 'success' : ($order['order_status'] == 'cancelled' ? 'cancelled' : ($order['order_status'] == 'pending' ? 'pending' : 'info')); ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </div>
                            <?php if ($order['verified_by']): ?>
                            <p style="color: var(--admin-text-secondary);"><strong style="color: var(--admin-text-primary);">Verified by:</strong> <?php echo htmlspecialchars($order['verified_first'] . ' ' . $order['verified_last']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <h4 style="margin: 2.5rem 0 1.25rem; font-size: 1rem; color: var(--admin-text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Order Items</h4>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td>
                                <div class="admin-table-product">
                                    <div class="admin-table-product-img">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--admin-bg);">
                                                <i class="fas fa-box-open" style="color: var(--admin-text-muted);"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-table-product-info">
                                        <span class="admin-table-product-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td style="text-align: right; font-weight: 600;">₱<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                            <td style="text-align: right;">₱<?php echo number_format($order['subtotal'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right;"><strong>Tax:</strong></td>
                            <td style="text-align: right;">₱<?php echo number_format($order['tax_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                            <td style="text-align: right; font-size: 1.25rem;"><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
                
                <?php if ($order['order_status'] !== 'delivered' && $order['order_status'] !== 'cancelled'): ?>
                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--admin-border); display: flex; gap: 1rem; flex-wrap: wrap;">
                    <form method="POST" style="display: flex; gap: 1rem; align-items: center;">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <?php if ($order['order_status'] === 'pending'): ?>
                        <button type="submit" name="action" value="verified" class="admin-btn admin-btn-primary" style="background: var(--admin-success);">
                            <i class="fas fa-check"></i> Verify Order
                        </button>
                        <?php endif; ?>
                        <?php if ($order['order_status'] === 'verified'): ?>
                        <button type="submit" name="action" value="processing" class="admin-btn admin-btn-primary">
                            <i class="fas fa-cog"></i> Mark Processing
                        </button>
                        <?php endif; ?>
                        <?php if ($order['order_status'] === 'processing'): ?>
                        <button type="submit" name="action" value="shipped" class="admin-btn admin-btn-primary">
                            <i class="fas fa-truck"></i> Mark Shipped
                        </button>
                        <?php endif; ?>
                        <?php if ($order['order_status'] === 'shipped'): ?>
                        <button type="submit" name="action" value="delivered" class="admin-btn admin-btn-primary" style="background: var(--admin-success);">
                            <i class="fas fa-check-double"></i> Mark Delivered
                        </button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="cancelled" class="admin-btn admin-btn-ghost" style="color: var(--admin-error);" onclick="return confirm('Cancel this order?')">
                            <i class="fas fa-times"></i> Cancel Order
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Orders List -->
        <div class="glass-card mb-5" style="padding: 1.5rem; border-radius: 20px; border-style: none; background: rgba(59, 130, 246, 0.03);">
            <form method="GET" class="d-flex gap-3" style="flex-wrap: wrap; align-items: center;">
                <div style="width: 40px; height: 40px; background: var(--admin-primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-filter"></i>
                </div>
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--admin-text-muted); display: block;">Quick Filter</span>
                    <select name="status" class="admin-search" style="min-width: 220px; padding: 0.6rem 1rem; border-radius: 12px; border: 1px solid var(--admin-border); background: var(--admin-content-bg); color: var(--admin-text-primary); font-weight: 600; cursor: pointer; transition: all 0.2s;" onchange="this.form.submit()">
                        <option value="">All Transactions</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending Orders</option>
                        <option value="verified" <?php echo $status == 'verified' ? 'selected' : ''; ?>>Verified Only</option>
                        <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Currently Processing</option>
                        <option value="shipped" <?php echo $status == 'shipped' ? 'selected' : ''; ?>>Shipped Out</option>
                        <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Completed / Delivered</option>
                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled / Void</option>
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
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-weight: 700; color: var(--admin-text-primary);"><?php echo strtoupper($order['payment_method']); ?></span>
                                    <span class="admin-badge admin-badge-<?php echo $order['payment_status'] == 'confirmed' ? 'success' : 'warning'; ?> <?php echo $order['payment_status'] != 'confirmed' ? 'pulse-warning' : ''; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php
                                $statusMap = ['pending' => 'pending', 'verified' => 'verified', 'processing' => 'processing', 
                                    'shipped' => 'shipped', 'delivered' => 'delivered', 'cancelled' => 'cancelled'];
                                ?>
                                <span class="admin-badge admin-badge-<?php echo $statusMap[$order['order_status']] ?? 'info'; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                            <td style="text-align: right; padding-right: 1.5rem;">
                                <a href="?id=<?php echo $order['id']; ?>" class="admin-btn admin-btn-primary hover-lift" style="padding: 0.5rem 1rem; border-radius: 10px; font-weight: 600; font-size: 0.8rem;">
                                    Verify Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
