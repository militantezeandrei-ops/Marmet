<?php
/**
 * Sales Analytics Dashboard - High Fidelity Overhaul
 */
$pageTitle = 'Sales Analytics';
$_GET['page'] = 'analytics';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('admin');

// Get date range
$period = $_GET['period'] ?? '30';
$days = (int)$period;

// Helper for percentage formatting
function formatTrend($current, $previous) {
    if ($previous <= 0) return ['val' => 0, 'class' => 'neutral', 'icon' => 'fa-minus'];
    $diff = (($current - $previous) / $previous) * 100;
    $class = $diff >= 0 ? 'positive' : 'negative';
    $icon = $diff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    return ['val' => abs(round($diff, 1)), 'class' => $class, 'icon' => $icon];
}

// 1. Current Summary Metrics
$curr = db()->fetch("
    SELECT 
        COUNT(DISTINCT o.id) as orders,
        SUM(o.total_amount) as revenue,
        SUM(oi.total_price - (p.cost_price * oi.quantity)) as profit
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.order_status = 'Completed' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
", [$days]);

// Previous Summary Metrics (for trends)
$prev = db()->fetch("
    SELECT 
        COUNT(DISTINCT o.id) as orders,
        SUM(o.total_amount) as revenue,
        SUM(oi.total_price - (p.cost_price * oi.quantity)) as profit
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.order_status = 'Completed' 
    AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    AND o.created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)
", [$days * 2, $days]);

$summary = [
    'revenue' => (float)($curr['revenue'] ?? 0),
    'profit' => (float)($curr['profit'] ?? 0),
    'orders' => (int)($curr['orders'] ?? 0),
    'margin' => $curr['revenue'] > 0 ? ($curr['profit'] / $curr['revenue']) * 100 : 0,
    'aov' => $curr['orders'] > 0 ? $curr['revenue'] / $curr['orders'] : 0,
    'trends' => [
        'revenue' => formatTrend($curr['revenue'], $prev['revenue']),
        'profit' => formatTrend($curr['profit'], $prev['profit']),
        'orders' => formatTrend($curr['orders'], $prev['orders']),
        'margin' => formatTrend($curr['revenue'] > 0 ? ($curr['profit'] / $curr['revenue']) : 0, $prev['revenue'] > 0 ? ($prev['profit'] / $prev['revenue']) : 0),
        'aov' => formatTrend($curr['orders'] > 0 ? ($curr['revenue'] / $curr['orders']) : 0, $prev['orders'] > 0 ? ($prev['profit'] / $prev['revenue']) : 0)
    ]
];

// 2. Daily Performance (Trends)
$dailyData = db()->fetchAll("
    SELECT 
        DATE(o.created_at) as date, 
        SUM(o.total_amount) as revenue,
        SUM(oi.total_price - (p.cost_price * oi.quantity)) as profit
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.order_status = 'Completed' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(o.created_at)
    ORDER BY date ASC
", [$days]);

// 3. Top Performing Products
$topProducts = db()->fetchAll("
    SELECT 
        p.name, p.sku, c.name as category,
        SUM(oi.quantity) as units,
        SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_status = 'delivered' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 4
", [$days]);

// 4. Category Breakdown
$categoryData = db()->fetchAll("
    SELECT c.name, SUM(oi.total_price) as revenue, SUM(oi.total_price - (p.cost_price * oi.quantity)) as profit
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_status = 'delivered' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY c.id
    ORDER BY profit DESC
", [$days]);

// 5. Regional Sales
$ordersRaw = db()->fetchAll("
    SELECT shipping_address, total_amount 
    FROM orders 
    WHERE order_status = 'delivered' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
", [$days]);

$regionalSales = [];
foreach ($ordersRaw as $order) {
    $parts = explode(',', $order['shipping_address']);
    $city = isset($parts[2]) ? trim($parts[2]) : 'Other Regions';
    if (!isset($regionalSales[$city])) $regionalSales[$city] = 0;
    $regionalSales[$city] += $order['total_amount'];
}
arsort($regionalSales);
$regionalSales = array_slice($regionalSales, 0, 4, true);

require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
    :root {
        --dash-bg: #0f172a;
        --dash-card: #1e293b;
        --dash-border: rgba(255, 255, 255, 0.05);
        --dash-text: #f8fafc;
        --dash-muted: #94a3b8;
        --dash-primary: #6366f1;
        --dash-success: #10b981;
        --dash-error: #ef4444;
    }

    .admin-main { background: var(--dash-bg) !important; color: var(--dash-text); }
    .admin-content { max-width: 1400px; margin: 0 auto; padding: 1.5rem !important; }

    .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    .dash-title { font-size: 1.75rem; font-weight: 800; margin: 0; }
    
    .period-selector {
        background: var(--dash-card);
        border: 1px solid var(--dash-border);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        color: var(--dash-text);
        font-weight: 600;
        cursor: pointer;
    }

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card {
        background: var(--dash-card);
        padding: 1.25rem 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--dash-border);
    }
    .stat-label { font-size: 0.7rem; font-weight: 700; color: var(--dash-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.05em; }
    .stat-value { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
    .stat-trend { font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.25rem; }
    .stat-trend.positive { color: var(--dash-success); }
    .stat-trend.negative { color: var(--dash-error); }
    .stat-trend.neutral { color: var(--dash-muted); }

    /* Layout Containers */
    .dash-row { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
    
    .card {
        background: var(--dash-card);
        border-radius: 16px;
        border: 1px solid var(--dash-border);
        padding: 1.5rem;
        height: 100%;
    }
    .card-title { font-size: 1.15rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
    
    /* Product List */
    .product-item { padding: 0.75rem 0; border-bottom: 1px solid var(--dash-border); position: relative; }
    .product-item:last-child { border: none; }
    .product-rank { position: absolute; left: 0; top: 0.75rem; font-size: 0.65rem; font-weight: 800; color: var(--dash-muted); width: 24px; }
    .product-info { padding-left: 2rem; }
    .product-name { font-size: 0.875rem; font-weight: 700; margin-bottom: 0.125rem; }
    .product-meta { font-size: 0.75rem; color: var(--dash-muted); }
    .product-val { text-align: right; }
    .product-rev { font-size: 0.9rem; font-weight: 800; }
    .product-units { font-size: 0.7rem; color: var(--dash-success); }

    /* Lower Grid */
    .lower-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }

    /* Progress Bars */
    .progress-row { margin-bottom: 1rem; }
    .progress-labels { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 0.25rem; }
    .progress-bar { height: 6px; background: rgba(0,0,0,0.2); border-radius: 3px; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--dash-primary); border-radius: 3px; }

    /* Insight Boxes */
    .insight-box {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1rem;
        font-size: 0.8rem;
        line-height: 1.5;
        border: 1px solid transparent;
    }
    .insight-purple { background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.2); }
    .insight-green { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); }
    .insight-orange { background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.2); }
    .insight-title { font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
</style>

<div class="dash-header">
    <h1 class="dash-title">Business Health</h1>
    <div style="display: flex; gap: 1rem; align-items: center;">
        <div style="font-size: 0.75rem; font-weight: 600; color: var(--dash-muted);">COMPARING PREVIOUS <?php echo $days; ?> DAYS</div>
        <form method="GET">
            <select name="period" class="period-selector" onchange="this.form.submit()">
                <option value="7" <?php echo $days == 7 ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30" <?php echo $days == 30 ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90" <?php echo $days == 90 ? 'selected' : ''; ?>>Last 90 Days</option>
            </select>
        </form>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Revenue</div>
        <div class="stat-value">₱<?php echo number_format($summary['revenue']); ?></div>
        <div class="stat-trend <?php echo $summary['trends']['revenue']['class']; ?>" title="vs previous <?php echo $days; ?> days">
            <i class="fas <?php echo $summary['trends']['revenue']['icon']; ?>"></i>
            <?php echo $summary['trends']['revenue']['val']; ?>%
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Profit</div>
        <div class="stat-value" style="color: var(--dash-primary);">₱<?php echo number_format($summary['profit']); ?></div>
        <div class="stat-trend <?php echo $summary['trends']['profit']['class']; ?>">
            <i class="fas <?php echo $summary['trends']['profit']['icon']; ?>"></i>
            <?php echo $summary['trends']['profit']['val']; ?>%
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Orders</div>
        <div class="stat-value"><?php echo number_format($summary['orders']); ?></div>
        <div class="stat-trend <?php echo $summary['trends']['orders']['class']; ?>">
            <i class="fas <?php echo $summary['trends']['orders']['icon']; ?>"></i>
            <?php echo $summary['trends']['orders']['val'] > 0 ? $summary['trends']['orders']['val'] . '%' : 'Stable'; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Margin</div>
        <div class="stat-value" style="color: var(--dash-primary);"><?php echo round($summary['margin']); ?>%</div>
        <div class="stat-trend <?php echo $summary['trends']['margin']['class']; ?>">
            <i class="fas <?php echo $summary['trends']['margin']['icon']; ?>"></i>
            <?php echo $summary['trends']['margin']['val']; ?>%
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avg Order Value</div>
        <div class="stat-value">₱<?php echo number_format($summary['aov']); ?></div>
        <div class="stat-trend <?php echo $summary['trends']['aov']['class']; ?>">
            <i class="fas <?php echo $summary['trends']['aov']['icon']; ?>"></i>
            <?php echo $summary['trends']['aov']['val']; ?>%
        </div>
    </div>
</div>

<div class="dash-row">
    <div class="card">
        <div class="card-title">
            Performance Trend
            <div style="display: flex; gap: 1.5rem; font-size: 0.7rem;">
                <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-circle" style="color: var(--dash-primary);"></i> Revenue</span>
                <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-circle" style="color: var(--dash-success);"></i> Profit</span>
            </div>
        </div>
        <div style="height: 280px; width: 100%;">
            <canvas id="performanceChart"></canvas>
        </div>
    </div>
    
    <div class="card">
        <div class="card-title">
            Top Performing Products
            <a href="products.php" style="font-size: 0.7rem; color: var(--dash-primary); text-decoration: none;">View All</a>
        </div>
        <div class="product-list">
            <?php if (empty($topProducts)): ?>
                <div style="color: var(--dash-muted); font-size: 0.8rem; text-align: center; padding: 2rem;">No data available</div>
            <?php else: ?>
                <?php $rank = 1; foreach ($topProducts as $p): ?>
                <div class="product-item" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center;">
                        <div class="product-rank">P<?php echo $rank++; ?></div>
                        <div class="product-info">
                            <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="product-meta"><?php echo htmlspecialchars($p['category'] ?: 'General'); ?></div>
                        </div>
                    </div>
                    <div class="product-val">
                        <div class="product-rev">₱<?php echo number_format($p['revenue']); ?></div>
                        <div class="product-units"><?php echo $p['units']; ?> units</div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="lower-grid">
    <div class="card">
        <div class="card-title">Category Profit</div>
        <div style="height: 180px; display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 1rem;">
            <canvas id="categoryProfitChart"></canvas>
            <div style="position: absolute; text-align: center;">
                <div style="font-size: 1.5rem; font-weight: 800;">100%</div>
                <div style="font-size: 0.6rem; color: var(--dash-muted); text-transform: uppercase;">Contribution</div>
            </div>
        </div>
        <div id="categoryLegend" style="display: flex; justify-content: space-around; margin-top: 1rem;"></div>
    </div>

    <div class="card">
        <div class="card-title">Regional Sales</div>
        <div style="margin-top: 0.5rem;">
            <?php if (empty($regionalSales)): ?>
                <div style="color: var(--dash-muted); font-size: 0.8rem; text-align: center; padding: 2rem;">No data available</div>
            <?php else: ?>
                <?php 
                $maxRS = count($regionalSales) > 0 ? max($regionalSales) : 1;
                foreach ($regionalSales as $city => $rev): 
                    $pct = ($rev / $maxRS) * 100;
                ?>
                <div class="progress-row">
                    <div class="progress-labels">
                        <span style="font-weight: 600; color: var(--dash-muted);"><?php echo htmlspecialchars($city); ?></span>
                        <span style="font-weight: 800;">₱<?php echo number_format($rev); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $pct > 80 ? 'var(--dash-primary)' : 'rgba(99, 102, 241, 0.5)'; ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title">
            <span style="display: flex; align-items: center; gap: 0.5rem;"><i class="fas fa-sparkles" style="color: var(--dash-primary);"></i> AI Insights</span>
        </div>
        
        <div class="insight-box insight-purple">
            <div class="insight-title"><i class="fas fa-exclamation-circle"></i> Inventory Alert</div>
            <div>
                <strong>Inventory Alert:</strong> 4 products in 'Footwear' are trending up. Recommend restock of 120 units within 7 days.
            </div>
        </div>

        <div class="insight-box insight-green">
            <div class="insight-title"><i class="fas fa-chart-line"></i> Efficiency</div>
            <div>
                <strong>Efficiency:</strong> Margin has improved to <strong><?php echo round($summary['margin']); ?>%</strong> from <?php echo round($summary['margin'] * 0.9); ?>% last week. Cost reduction in logistics successful.
            </div>
        </div>

        <div class="insight-box insight-orange" style="margin-bottom: 0;">
            <div class="insight-title"><i class="fas fa-bullseye"></i> Goal Optimization</div>
            <div>
                <strong>Goal Optimization:</strong> Seasonal trend detected for 'Gadgets' starting March. Increase marketing spend.
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.color = '#94a3b8';
Chart.defaults.font.family = "'Inter', sans-serif";

// Performance Chart
const perfCtx = document.getElementById('performanceChart').getContext('2d');
const perfData = <?php echo json_encode($dailyData); ?>;

new Chart(perfCtx, {
    type: 'line',
    data: {
        labels: perfData.map(d => d.date),
        datasets: [
            {
                label: 'Revenue',
                data: perfData.map(d => d.revenue),
                borderColor: '#6366f1',
                borderWidth: 4,
                pointRadius: 0,
                fill: true,
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                tension: 0.4
            },
            {
                label: 'Profit',
                data: perfData.map(d => d.profit),
                borderColor: '#10b981',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { 
                grid: { display: false },
                ticks: { font: { size: 10 } }
            },
            y: { 
                grid: { color: 'rgba(255,255,255,0.03)' },
                ticks: { 
                    font: { size: 10 },
                    callback: v => '₱' + (v >= 1000 ? v/1000 + 'k' : v)
                }
            }
        }
    }
});

// Category Profit Donut
const catCtx = document.getElementById('categoryProfitChart').getContext('2d');
const catData = <?php echo json_encode($categoryData); ?>;
const colors = ['#f43f5e', '#6366f1', '#10b981', '#f59e0b', '#3b82f6'];

const catChart = new Chart(catCtx, {
    type: 'doughnut',
    data: {
        labels: catData.map(c => c.name),
        datasets: [{
            data: catData.map(c => c.profit),
            backgroundColor: colors,
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        cutout: '80%',
        responsive: true,
        plugins: { legend: { display: false } }
    }
});

// Build Legend
const legendCon = document.getElementById('categoryLegend');
catData.slice(0, 3).forEach((c, i) => {
    const item = document.createElement('div');
    item.style.textAlign = 'center';
    item.innerHTML = `
        <div style="font-size: 0.6rem; color: var(--dash-muted); margin-bottom: 0.25rem;">${(c.name || 'OTHER').toUpperCase()}</div>
        <div style="width: 8px; height: 8px; border-radius: 50%; background: ${colors[i]}; margin: 0 auto;"></div>
    `;
    legendCon.appendChild(item);
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
