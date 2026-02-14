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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary);">Order Verification</h1>
        <p style="color: var(--admin-text-secondary);">Review and update customer order statuses</p>
    </div>
</div>

<div style="padding-top: 1rem;">
    <div class="admin-card" style="padding: 1.5rem; border-radius: 16px; border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow); background: var(--admin-content-bg);">
        <?php if ($message): ?>
        <div class="alert alert-success mb-3"><i class="fas fa-check"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($orderId && $order): ?>
        <!-- Single Order View -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-between align-center">
                <h3>Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                <a href="verify-order.php" class="btn btn-ghost btn-sm">
                    <i class="fas fa-list"></i> All Orders
                </a>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="margin-bottom: 1rem;">Customer Information</h4>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                        <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 1rem;">Order Details</h4>
                        <p><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></p>
                        <p><strong>Payment:</strong> <?php echo strtoupper($order['payment_method']); ?> 
                            <span class="badge badge-<?php echo $order['payment_status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </p>
                        <p><strong>Status:</strong> 
                            <span class="badge badge-<?php echo $order['order_status'] == 'delivered' ? 'success' : ($order['order_status'] == 'cancelled' ? 'error' : 'info'); ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </p>
                        <?php if ($order['verified_by']): ?>
                        <p><strong>Verified by:</strong> <?php echo htmlspecialchars($order['verified_first'] . ' ' . $order['verified_last']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <h4 style="margin: 2rem 0 1rem;">Order Items</h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Thumbnail</th>
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
                                <div class="se-circle-img" style="width: 40px; height: 40px; border-radius: 8px;">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-box-open" style="font-size: 0.875rem; color: var(--gray-400);"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td style="text-align: right;">₱<?php echo number_format($item['total_price'], 2); ?></td>
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
                <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <?php if ($order['order_status'] === 'pending'): ?>
                        <button type="submit" name="action" value="verified" class="btn btn-success">
                            <i class="fas fa-check"></i> Verify Order
                        </button>
                        <?php endif; ?>
                        <?php if ($order['order_status'] === 'verified'): ?>
                        <button type="submit" name="action" value="processing" class="btn btn-primary">
                            <i class="fas fa-cog"></i> Mark Processing
                        </button>
                        <?php endif; ?>
                        <?php if ($order['order_status'] === 'processing'): ?>
                        <button type="submit" name="action" value="shipped" class="btn btn-primary">
                            <i class="fas fa-truck"></i> Mark Shipped
                        </button>
                        <?php endif; ?>
                        <?php if ($order['order_status'] === 'shipped'): ?>
                        <button type="submit" name="action" value="delivered" class="btn btn-success">
                            <i class="fas fa-check-double"></i> Mark Delivered
                        </button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="cancelled" class="btn btn-danger" onclick="return confirm('Cancel this order?')">
                            <i class="fas fa-times"></i> Cancel Order
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Orders List -->
        <div class="card mb-3" style="padding: 1rem;">
            <form method="GET" class="d-flex gap-2" style="flex-wrap: wrap;">
                <select name="status" class="form-input form-select" style="max-width: 200px;" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="verified" <?php echo $status == 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $status == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </form>
        </div>
        
        <div class="card">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <?php echo strtoupper($order['payment_method']); ?>
                                <span class="badge badge-<?php echo $order['payment_status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusColors = ['pending' => 'warning', 'verified' => 'info', 'processing' => 'info', 
                                    'shipped' => 'primary', 'delivered' => 'success', 'cancelled' => 'error'];
                                ?>
                                <span class="badge badge-<?php echo $statusColors[$order['order_status']] ?? 'info'; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                            <td>
                                <a href="?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                    View
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
