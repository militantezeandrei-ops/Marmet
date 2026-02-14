<?php
/**
 * Sales Analytics Dashboard
 */
$pageTitle = 'Analytics';
$_GET['page'] = 'analytics';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('admin');

// Get date range
$period = $_GET['period'] ?? '30';
$days = (int)$period;

// Revenue by day
$dailyRevenue = db()->fetchAll("
    SELECT DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders
    FROM orders
    WHERE order_status = 'delivered' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
", [$days]);

// Revenue by category
$categoryRevenue = db()->fetchAll("
    SELECT c.name, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_status = 'delivered' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY c.id
    ORDER BY revenue DESC
", [$days]);

// Top selling products
$topProducts = db()->fetchAll("
    SELECT p.name, SUM(oi.quantity) as units, SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_status = 'delivered' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 10
", [$days]);

// Payment method breakdown
$paymentBreakdown = db()->fetchAll("
    SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
    FROM orders
    WHERE order_status = 'delivered' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY payment_method
", [$days]);

// Summary stats
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value,
        MAX(total_amount) as highest_order
    FROM orders
    WHERE order_status = 'delivered' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
", [$days]);

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Period Selector -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <h2 style="margin: 0; color: var(--admin-text-primary);">Sales Analytics</h2>
    <form method="GET" style="display: flex; align-items: center; gap: 0.5rem;">
        <span style="font-size: 0.875rem; color: var(--admin-text-secondary);">Period:</span>
        <select name="period" style="padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px; background: var(--admin-content-bg); color: var(--admin-text-primary);" onchange="this.form.submit()">
            <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>Last 7 days</option>
            <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>Last 30 days</option>
            <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>Last 90 days</option>
            <option value="365" <?php echo $period == '365' ? 'selected' : ''; ?>>Last year</option>
        </select>
    </form>
</div>

<!-- Summary Stats -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="admin-card">
        <div class="admin-card-body" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--admin-text-muted);">Total Revenue</span>
                <i class="fas fa-dollar-sign" style="color: var(--admin-success);"></i>
            </div>
            <div style="font-size: 1.875rem; font-weight: 700; color: var(--admin-text-primary);">
                ₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?>
            </div>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-body" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--admin-text-muted);">Orders</span>
                <i class="fas fa-shopping-cart" style="color: var(--admin-primary);"></i>
            </div>
            <div style="font-size: 1.875rem; font-weight: 700; color: var(--admin-text-primary);">
                <?php echo $stats['total_orders'] ?? 0; ?>
            </div>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-body" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--admin-text-muted);">Avg Order Value</span>
                <i class="fas fa-receipt" style="color: var(--admin-warning);"></i>
            </div>
            <div style="font-size: 1.875rem; font-weight: 700; color: var(--admin-text-primary);">
                ₱<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?>
            </div>
        </div>
    </div>
    <div class="admin-card">
        <div class="admin-card-body" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <span style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--admin-text-muted);">Highest Order</span>
                <i class="fas fa-trophy" style="color: var(--admin-info);"></i>
            </div>
            <div style="font-size: 1.875rem; font-weight: 700; color: var(--admin-text-primary);">
                ₱<?php echo number_format($stats['highest_order'] ?? 0, 2); ?>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Chart -->
<div class="admin-card" style="margin-bottom: 1.5rem;">
    <div class="admin-card-header">
        <div class="admin-card-title">Revenue Trend</div>
    </div>
    <div class="admin-card-body">
        <canvas id="revenueChart" height="100"></canvas>
    </div>
</div>

<!-- Charts Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="admin-card-title">Revenue by Category</div>
        </div>
        <div class="admin-card-body">
            <canvas id="categoryChart" height="200"></canvas>
        </div>
    </div>
    
    <div class="admin-card">
        <div class="admin-card-header">
            <div class="admin-card-title">Payment Methods</div>
        </div>
        <div class="admin-card-body">
            <?php foreach ($paymentBreakdown as $payment): ?>
            <div style="display: flex; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid var(--admin-border);">
                <div>
                    <strong><?php echo strtoupper($payment['payment_method']); ?></strong>
                    <br><small style="color: var(--admin-text-muted);"><?php echo $payment['count']; ?> orders</small>
                </div>
                <div style="text-align: right; font-weight: 600; color: var(--admin-success);">
                    ₱<?php echo number_format($payment['total'], 2); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Top Products -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">Top Selling Products</div>
    </div>
    <div class="admin-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Units Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProducts as $i => $product): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                    <td><?php echo $product['units']; ?></td>
                    <td style="color: var(--admin-success); font-weight: 600;">₱<?php echo number_format($product['revenue'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
const revenueData = <?php echo json_encode($dailyRevenue); ?>;
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: revenueData.map(d => d.date),
        datasets: [{
            label: 'Revenue',
            data: revenueData.map(d => d.revenue),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } }
        }
    }
});

// Category Chart
const categoryData = <?php echo json_encode($categoryRevenue); ?>;
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: categoryData.map(c => c.name || 'Uncategorized'),
        datasets: [{
            data: categoryData.map(c => c.revenue),
            backgroundColor: ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#3b82f6']
        }]
    },
    options: { responsive: true }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
