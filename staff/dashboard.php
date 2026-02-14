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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary);">Staff Overview</h1>
        <p style="color: var(--admin-text-secondary);">Active monitoring of customer fulfillment</p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <button class="admin-btn admin-btn-primary" style="background: var(--admin-primary); border: none; border-radius: 8px; padding: 0.625rem 1.25rem;">
            <i class="fas fa-download"></i> Export Report
        </button>
    </div>
</div>

<!-- Dashboard Stats -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px; box-shadow: var(--admin-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">New Orders</span>
            <span style="background: rgba(59, 130, 246, 0.1); color: var(--admin-primary); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">NEW</span>
        </div>
        <div style="font-size: 2.5rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            <?php echo $pendingOrders; ?>
        </div>
        <div style="color: var(--admin-success); font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-arrow-up"></i> +20% vs yesterday
        </div>
    </div>
    
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px; box-shadow: var(--admin-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">In Progress</span>
            <i class="fas fa-spinner" style="color: var(--admin-warning);"></i>
        </div>
        <div style="font-size: 2.5rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            <?php echo $todayOrders; ?>
        </div>
        <div style="color: var(--admin-error); font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-arrow-down"></i> -5% bottlenecked
        </div>
    </div>
    
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px; box-shadow: var(--admin-shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">Priority Alerts</span>
            <i class="fas fa-exclamation-triangle" style="color: var(--admin-error);"></i>
        </div>
        <div style="font-size: 2.5rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            5
        </div>
        <div style="color: var(--admin-success); font-size: 0.875rem; font-weight: 600;">
            Requires attention
        </div>
    </div>
</div>

<!-- Pending Fulfillment -->
<div class="admin-card" style="border-radius: 16px; border: 1px solid var(--admin-border); box-shadow: var(--admin-shadow);">
    <div class="admin-card-header" style="padding: 1.5rem; border-bottom: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 1.125rem; font-weight: 700;">Pending Fulfillment</h2>
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
