<?php
/**
 * Admin Dashboard
 */
$pageTitle = 'Dashboard';
$_GET['page'] = 'dashboard';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('admin');

// Get stats
$totalRevenue = db()->fetch("SELECT SUM(total_amount) as total FROM orders WHERE order_status = 'delivered'")['total'] ?? 0;
$monthlyRevenue = db()->fetch("SELECT SUM(total_amount) as total FROM orders WHERE order_status = 'delivered' AND MONTH(created_at) = MONTH(CURDATE())")['total'] ?? 0;
$totalOrders = db()->count("SELECT COUNT(*) FROM orders");
$totalCustomers = db()->count("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$totalProducts = db()->count("SELECT COUNT(*) FROM products WHERE is_active = 1");

// Get recent orders
$recentOrders = db()->fetchAll("
    SELECT o.*, u.first_name, u.last_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary);">Analytics & Inventory</h1>
        <p style="color: var(--admin-text-secondary);">Real-time performance metrics and predictive stock forecasting.</p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <button class="admin-btn admin-btn-primary" style="background: var(--admin-primary); border: none; border-radius: 8px; padding: 0.625rem 1.25rem;">
            <i class="fas fa-download"></i> Export Report
        </button>
    </div>
</div>

<!-- Dashboard Stats -->
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">Daily Revenue</span>
            <i class="fas fa-wallet" style="color: var(--admin-primary);"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            ₱<?php echo number_format($totalRevenue, 2); ?>
        </div>
        <div style="color: var(--admin-success); font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-chart-line"></i> +12.5% vs yesterday
        </div>
    </div>
    
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">Active Users</span>
            <i class="fas fa-users" style="color: var(--admin-info);"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            <?php echo number_format($totalCustomers); ?>
        </div>
        <div style="color: var(--admin-success); font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-chart-line"></i> +4.2% this hour
        </div>
    </div>
    
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">Inventory Health</span>
            <i class="fas fa-box" style="color: var(--admin-warning);"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            85.4%
        </div>
        <div style="color: var(--admin-error); font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-arrow-down"></i> -2.1% stock alerts
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Sales Trends & Growth -->
    <div class="admin-card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.125rem; font-weight: 700;">Sales Trends & Growth</h2>
            <div style="display: flex; gap: 0.5rem; background: var(--admin-bg); padding: 0.25rem; border-radius: 8px;">
                <button style="padding: 0.25rem 0.75rem; border-radius: 6px; border: none; background: var(--admin-primary); color: white; font-size: 0.75rem; font-weight: 600;">Weekly</button>
                <button style="padding: 0.25rem 0.75rem; border-radius: 6px; border: none; background: transparent; color: var(--admin-text-secondary); font-size: 0.75rem; font-weight: 600;">Monthly</button>
            </div>
        </div>
        
        <!-- Placeholder for Chart -->
        <div style="height: 250px; display: flex; align-items: flex-end; gap: 1rem; padding-bottom: 2rem;">
            <div style="flex: 1; background: rgba(59, 130, 246, 0.2); height: 40%; border-radius: 4px; position: relative;"><span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);">Mon</span></div>
            <div style="flex: 1; background: rgba(59, 130, 246, 0.2); height: 60%; border-radius: 4px; position: relative;"><span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);">Tue</span></div>
            <div style="flex: 1; background: rgba(59, 130, 246, 0.2); height: 50%; border-radius: 4px; position: relative;"><span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);">Wed</span></div>
            <div style="flex: 1; background: rgba(59, 130, 246, 0.2); height: 80%; border-radius: 4px; position: relative;"><span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);">Thu</span></div>
            <div style="flex: 1; background: var(--admin-primary); height: 100%; border-radius: 4px; position: relative;"><span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);">Fri</span></div>
            <div style="flex: 1; background: rgba(59, 130, 246, 0.2); height: 70%; border-radius: 4px; position: relative;"><span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);">Sat</span></div>
            <div style="flex: 1; background: rgba(59, 130, 246, 0.2); height: 85%; border-radius: 4px; position: relative;"><span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);">Sun</span></div>
        </div>
    </div>
    
    <!-- Inventory Forecast -->
    <div class="admin-card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.5rem;">Inventory Forecast</h2>
        
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--admin-border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-weight: 600; font-size: 0.875rem;">Smart Watch Series X</span>
                    <span style="color: var(--admin-error); font-size: 0.75rem; font-weight: 700;">3 days left</span>
                </div>
                <div style="height: 6px; background: rgba(239, 68, 68, 0.1); border-radius: 3px; overflow: hidden;">
                    <div style="width: 15%; height: 100%; background: var(--admin-error);"></div>
                </div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted); margin-top: 0.25rem;">Sales velocity: 42 units/day</div>
            </div>
            
            <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--admin-border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-weight: 600; font-size: 0.875rem;">Eco Headphones</span>
                    <span style="color: var(--admin-warning); font-size: 0.75rem; font-weight: 700;">12 days left</span>
                </div>
                <div style="height: 6px; background: rgba(245, 158, 11, 0.1); border-radius: 3px; overflow: hidden;">
                    <div style="width: 45%; height: 100%; background: var(--admin-warning);"></div>
                </div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted); margin-top: 0.25rem;">Sales velocity: 18 units/day</div>
            </div>
            
            <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--admin-border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-weight: 600; font-size: 0.875rem;">Wireless Charger Pad</span>
                    <span style="color: var(--admin-success); font-size: 0.75rem; font-weight: 700;">28 days left</span>
                </div>
                <div style="height: 6px; background: rgba(16, 185, 129, 0.1); border-radius: 3px; overflow: hidden;">
                    <div style="width: 75%; height: 100%; background: var(--admin-success);"></div>
                </div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted); margin-top: 0.25rem;">Sales velocity: 125 units/day</div>
            </div>
        </div>
        <button class="admin-btn admin-btn-primary" style="width: 100%; margin-top: 1.5rem; justify-content: center; border-radius: 10px;">Generate Purchase Order</button>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700;">Low Stock Alerts</h2>
    <a href="inventory.php" style="color: var(--admin-primary); font-size: 0.875rem; font-weight: 600; text-decoration: none;">View All Inventory</a>
</div>

<!-- Recent Orders -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">Recent Orders</div>
        <a href="<?php echo APP_URL; ?>/staff/verify-order.php" class="admin-btn admin-btn-ghost">View All</a>
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
                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                    <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                    <td>
                        <?php
                        $statusClass = [
                            'pending' => 'admin-badge-inactive',
                            'verified' => 'admin-badge-published',
                            'delivered' => 'admin-badge-published',
                            'cancelled' => 'admin-badge-draft'
                        ];
                        $class = $statusClass[$order['order_status']] ?? 'admin-badge-draft';
                        ?>
                        <span class="admin-badge <?php echo $class; ?>">
                            <?php echo ucfirst($order['order_status']); ?>
                        </span>
                    </td>
                    <td>
                        <button class="admin-action-btn" title="View Details">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
