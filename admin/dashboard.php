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
$totalOrders = db()->count("SELECT COUNT(*) FROM orders");
$totalCustomers = db()->count("SELECT COUNT(*) FROM users WHERE role = 'customer'");
$totalProducts = db()->count("SELECT COUNT(*) FROM products WHERE is_active = 1");

// Inventory Health (Real)
$healthyProducts = db()->count("SELECT COUNT(*) FROM inventory i JOIN products p ON i.product_id = p.id WHERE p.is_active = 1 AND i.quantity > i.low_stock_threshold");
$invHealth = $totalProducts > 0 ? (int)(($healthyProducts / $totalProducts) * 100) : 100;

// Daily Revenue Trend
$yesterday = date('Y-m-d', strtotime("-1 day"));
$yesterdayRevenue = db()->fetch("SELECT SUM(revenue) as rev FROM sales_history WHERE sale_date = ?", [$yesterday])['rev'] ?? 0;
$todayRevenue = db()->fetch("SELECT SUM(revenue) as rev FROM sales_history WHERE sale_date = CURDATE()")['rev'] ?? 0;
$revDiff = $todayRevenue - $yesterdayRevenue;
$revPercent = $yesterdayRevenue > 0 ? round(($revDiff / $yesterdayRevenue) * 100, 1) : ($todayRevenue > 0 ? 100 : 0);

// User Growth (Real - last 24h)
$newUsers = db()->count("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

// Sales Trends (Last 7 Days)
$weeklySales = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $rev = db()->fetch("SELECT SUM(revenue) as rev FROM sales_history WHERE sale_date = ?", [$date])['rev'] ?? 0;
    $weeklySales[] = ['day' => date('D', strtotime($date)), 'rev' => (float)$rev];
}
$maxRev = max(array_column($weeklySales, 'rev')) ?: 1;

// Inventory Alerts (Top 3)
$alerts = db()->fetchAll("
    SELECT p.name, i.quantity, i.low_stock_threshold, 
           (SELECT AVG(quantity_sold) FROM sales_history WHERE product_id = p.id AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as velocity
    FROM products p
    JOIN inventory i ON p.id = i.product_id
    WHERE p.is_active = 1
    ORDER BY (i.quantity / (1 + IFNULL(velocity, 0))) ASC
    LIMIT 3
");

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
            ₱<?php echo number_format($todayRevenue, 2); ?>
        </div>
        <div style="color: <?php echo $revDiff >= 0 ? 'var(--admin-success)' : 'var(--admin-error)'; ?>; font-size: 0.875rem; font-weight: 600;">
            <i class="fas <?php echo $revDiff >= 0 ? 'fa-chart-line' : 'fa-chart-bar'; ?>"></i> 
            <?php echo ($revDiff >= 0 ? '+' : '') . $revPercent; ?>% vs yesterday
        </div>
    </div>
    
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">Active Customers</span>
            <i class="fas fa-users" style="color: var(--admin-info);"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            <?php echo number_format($totalCustomers); ?>
        </div>
        <div style="color: var(--admin-success); font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-user-plus"></i> +<?php echo $newUsers; ?> new today
        </div>
    </div>
    
    <div class="admin-card" style="padding: 1.5rem; background: var(--admin-content-bg); border: 1px solid var(--admin-border); border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="color: var(--admin-text-muted); font-size: 0.875rem; font-weight: 500;">Inventory Health</span>
            <i class="fas fa-box" style="color: var(--admin-warning);"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--admin-text-primary); margin-bottom: 0.5rem;">
            <?php echo $invHealth; ?>%
        </div>
        <div style="color: <?php echo $invHealth > 80 ? 'var(--admin-success)' : 'var(--admin-error)'; ?>; font-size: 0.875rem; font-weight: 600;">
            <i class="fas <?php echo $invHealth > 80 ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> 
            <?php echo $healthyProducts; ?> / <?php echo $totalProducts; ?> items healthy
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Sales Trends & Growth -->
    <div class="admin-card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.125rem; font-weight: 700;">Sales Trends (Last 7 Days)</h2>
        </div>
        
        <div style="height: 250px; display: flex; align-items: flex-end; gap: 1rem; padding-bottom: 2rem;">
            <?php foreach ($weeklySales as $s): 
                $height = ($s['rev'] / $maxRev) * 100;
            ?>
            <div style="flex: 1; background: <?php echo $height == 100 ? 'var(--admin-primary)' : 'rgba(59, 130, 246, 0.2)'; ?>; height: <?php echo max(5, $height); ?>%; border-radius: 4px; position: relative;" title="₱<?php echo number_format($s['rev']); ?>">
                <span style="position: absolute; bottom: -1.5rem; left: 50%; transform: translateX(-50%); font-size: 0.75rem; color: var(--admin-text-muted);"><?php echo $s['day']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Inventory Forecast -->
    <div class="admin-card" style="padding: 1.5rem;">
        <h2 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.5rem;">Stock Alerts</h2>
        
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($alerts as $a): 
                $vel = (float)$a['velocity'];
                $days = $vel > 0 ? floor($a['quantity'] / $vel) : 99;
                $p = $vel > 0 ? min(100, ($a['quantity'] / ($vel * 14)) * 100) : 100;
                $color = $days < 7 ? 'var(--admin-error)' : 'var(--admin-warning)';
            ?>
            <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--admin-border);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span style="font-weight: 600; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;"><?php echo htmlspecialchars($a['name']); ?></span>
                    <span style="color: <?php echo $color; ?>; font-size: 0.75rem; font-weight: 700;"><?php echo $days; ?> days left</span>
                </div>
                <div style="height: 6px; background: rgba(255,255,255,0.05); border-radius: 3px; overflow: hidden;">
                    <div style="width: <?php echo $p; ?>%; height: 100%; background: <?php echo $color; ?>;"></div>
                </div>
                <div style="font-size: 0.75rem; color: var(--admin-text-muted); margin-top: 0.25rem;">Daily Pace: <?php echo round($vel, 1); ?> units</div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="forecast.php" class="admin-btn admin-btn-primary" style="width: 100%; margin-top: 1.5rem; justify-content: center; border-radius: 10px; text-decoration: none;">View Forecast Details</a>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.25rem; font-weight: 700;">Low Stock Alerts</h2>
    <a href="products.php" style="color: var(--admin-primary); font-size: 0.875rem; font-weight: 600; text-decoration: none;">View All Products</a>
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
