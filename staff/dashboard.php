<?php
/**
 * Staff Dashboard
 */
$pageTitle = 'Dashboard';
$_GET['page'] = 'dashboard';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole(['staff', 'admin']);

// Get stats
$pendingOrders = db()->count("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'");
$todayOrders = db()->count("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$verifiedOrders = db()->count("SELECT COUNT(*) FROM orders WHERE order_status = 'verified'");
$lowStockProducts = db()->count("SELECT COUNT(*) FROM inventory i JOIN products p ON i.product_id = p.id WHERE i.quantity <= i.low_stock_threshold AND p.is_active = 1");

// Get recent orders
$recentOrders = db()->fetchAll("
    SELECT o.*, u.first_name, u.last_name, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");

// Staff should not have access to shop features from their dashboard
require_once __DIR__ . '/../includes/admin_header.php';
?>


<!-- Dashboard Stats -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="glass-card hover-lift" style="padding: 2rem; border-radius: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div style="width: 44px; height: 44px; background: rgba(79, 70, 229, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--admin-primary);">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <span class="admin-badge admin-badge-primary" style="padding: 0.4rem 0.8rem; border-radius: 10px; font-size: 0.7rem;">LIVE ORDERS</span>
        </div>
        <div style="font-size: 3rem; font-weight: 900; color: var(--admin-text-primary); margin-bottom: 0.25rem;">
            <?php echo $pendingOrders; ?>
        </div>
        <div style="color: var(--admin-text-secondary); font-size: 0.95rem; font-weight: 500;">
            Awaiting Verification
        </div>
    </div>
    
    <div class="glass-card hover-lift" style="padding: 2rem; border-radius: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div style="width: 44px; height: 44px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--admin-warning);">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <span class="admin-badge admin-badge-warning pulse-warning" style="padding: 0.4rem 0.8rem; border-radius: 10px; font-size: 0.7rem;">IN PROGRESS</span>
        </div>
        <div style="font-size: 3rem; font-weight: 900; color: var(--admin-text-primary); margin-bottom: 0.25rem;">
            <?php echo $todayOrders; ?>
        </div>
        <div style="color: var(--admin-text-secondary); font-size: 0.95rem; font-weight: 500;">
            Deliveries Active Today
        </div>
    </div>
    
    <div class="glass-card hover-lift" style="padding: 2rem; border-radius: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <div style="width: 44px; height: 44px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--admin-success);">
                <i class="fas fa-check-double"></i>
            </div>
            <span class="admin-badge admin-badge-success" style="padding: 0.4rem 0.8rem; border-radius: 10px; font-size: 0.7rem;">EFFICIENCY</span>
        </div>
        <div style="font-size: 3rem; font-weight: 900; color: var(--admin-text-primary); margin-bottom: 0.25rem;">
            98%
        </div>
        <div style="color: var(--admin-text-secondary); font-size: 0.95rem; font-weight: 500;">
            Fulfillment Accuracy
        </div>
    </div>
</div>

<!-- Pending Fulfillment -->
<div class="glass-card" style="border-radius: 32px; overflow: hidden;">
    <div class="admin-card-header" style="padding: 2rem; background: var(--admin-gradient-surface); border-bottom: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.5rem; font-weight: 900; color: var(--admin-text-primary); letter-spacing: -0.02em;">Pending Fulfillment</h2>
        <div style="display: flex; gap: 0.5rem;">
            <select style="padding: 0.5rem; border-radius: 8px; border: 1px solid var(--admin-border); background: var(--admin-bg);">
                <option>All Statuses</option>
            </select>
            <button style="padding: 0.5rem; border-radius: 8px; border: 1px solid var(--admin-border); background: var(--admin-bg);"><i class="fas fa-filter"></i></button>
        </div>
    </div>
    <div class="admin-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order Number</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                    <td>
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-weight: 600;"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                            <small style="color: var(--admin-text-muted); font-size: 0.75rem;"><?php echo $order['email']; ?></small>
                        </div>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                    <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    <td>
                        <span class="admin-badge admin-badge-inactive">Pending</span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="verify-order.php?id=<?php echo $order['id']; ?>" class="admin-btn admin-btn-primary admin-btn-sm" style="padding: 0.25rem 0.75rem;">
                                Process
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
