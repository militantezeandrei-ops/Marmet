<?php
/**
 * Demand Forecasting Module
 * Uses moving averages and trend analysis for stock predictions
 */
$pageTitle = 'Demand Forecasting';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('admin');

/**
 * Calculate moving average
 */
function calculateMovingAverage($data, $periods = 7) {
    if (count($data) < $periods) return array_sum($data) / max(count($data), 1);
    $slice = array_slice($data, -$periods);
    return array_sum($slice) / $periods;
}

/**
 * Calculate trend (simple linear regression slope)
 */
function calculateTrend($data) {
    $n = count($data);
    if ($n < 2) return 0;
    
    $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
    
    foreach ($data as $i => $y) {
        $x = $i + 1;
        $sumX += $x;
        $sumY += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    
    $denominator = ($n * $sumX2 - $sumX * $sumX);
    if ($denominator == 0) return 0;
    
    return ($n * $sumXY - $sumX * $sumY) / $denominator;
}

// Get products with sales history
$products = db()->fetchAll("
    SELECT p.*, c.name as category_name, i.quantity as stock, i.low_stock_threshold
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.is_active = 1
    ORDER BY p.name
");

$forecasts = [];

foreach ($products as $product) {
    // Get last 30 days of sales
    $salesData = db()->fetchAll("
        SELECT sale_date, SUM(quantity_sold) as qty
        FROM sales_history
        WHERE product_id = ? AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY sale_date
        ORDER BY sale_date ASC
    ", [$product['id']]);
    
    $dailySales = array_column($salesData, 'qty');
    
    if (empty($dailySales)) {
        $forecasts[$product['id']] = [
            'product' => $product,
            'avg_daily_sales' => 0,
            'trend' => 'stable',
            'forecast_7_days' => 0,
            'forecast_30_days' => 0,
            'days_of_stock' => $product['stock'] > 0 ? 999 : 0,
            'recommendation' => 'No sales data',
            'status' => 'unknown'
        ];
        continue;
    }
    
    $avgDailySales = calculateMovingAverage($dailySales, 7);
    $trend = calculateTrend($dailySales);
    
    // Forecast with trend adjustment
    $forecast7 = max(0, round($avgDailySales * 7 + ($trend * 7)));
    $forecast30 = max(0, round($avgDailySales * 30 + ($trend * 30)));
    
    // Days of stock remaining
    $daysOfStock = $avgDailySales > 0 ? floor($product['stock'] / $avgDailySales) : 999;
    
    // Determine trend direction
    $trendDirection = 'stable';
    if ($trend > 0.5) $trendDirection = 'increasing';
    elseif ($trend < -0.5) $trendDirection = 'decreasing';
    
    // Generate recommendation
    $status = 'ok';
    $recommendation = 'Stock levels adequate';
    
    if ($daysOfStock < 7) {
        $status = 'critical';
        $recommendation = "URGENT: Order " . max(0, $forecast30 - $product['stock']) . " units immediately";
    } elseif ($daysOfStock < 14) {
        $status = 'warning';
        $recommendation = "Reorder soon: Need " . max(0, $forecast30 - $product['stock']) . " units";
    } elseif ($product['stock'] > $forecast30 * 3) {
        $status = 'overstock';
        $recommendation = "Consider promotion - excess stock of " . ($product['stock'] - $forecast30 * 2) . " units";
    }
    
    $forecasts[$product['id']] = [
        'product' => $product,
        'avg_daily_sales' => round($avgDailySales, 1),
        'trend' => $trendDirection,
        'trend_value' => round($trend, 2),
        'forecast_7_days' => $forecast7,
        'forecast_30_days' => $forecast30,
        'days_of_stock' => $daysOfStock,
        'recommendation' => $recommendation,
        'status' => $status
    ];
}

// Sort by status priority
usort($forecasts, function($a, $b) {
    $priority = ['critical' => 0, 'warning' => 1, 'overstock' => 2, 'ok' => 3, 'unknown' => 4];
    return ($priority[$a['status']] ?? 5) - ($priority[$b['status']] ?? 5);
});

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Demand Forecasting</h1>
        <a href="analytics.php" class="btn btn-ghost"><i class="fas fa-chart-line"></i> Analytics</a>
    </div>
</div>

<div class="section" style="padding-top: 2rem;">
    <div class="container">
        <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>About Forecasting:</strong> Predictions are based on 30-day moving averages with trend analysis. 
                Review recommendations regularly and adjust based on seasonal factors or promotions.
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="stats-grid mb-3">
            <?php
            $criticalCount = count(array_filter($forecasts, fn($f) => $f['status'] == 'critical'));
            $warningCount = count(array_filter($forecasts, fn($f) => $f['status'] == 'warning'));
            $overstockCount = count(array_filter($forecasts, fn($f) => $f['status'] == 'overstock'));
            ?>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: #ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <h3><?php echo $criticalCount; ?></h3>
                    <p>Critical (< 7 days)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-content">
                    <h3><?php echo $warningCount; ?></h3>
                    <p>Warning (< 14 days)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-boxes"></i></div>
                <div class="stat-content">
                    <h3><?php echo $overstockCount; ?></h3>
                    <p>Overstock</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h3><?php echo count($forecasts) - $criticalCount - $warningCount - $overstockCount; ?></h3>
                    <p>Healthy Stock</p>
                </div>
            </div>
        </div>
        
        <!-- Forecast Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-brain"></i> Product Demand Forecast</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Avg Daily Sales</th>
                            <th>Trend</th>
                            <th>7-Day Forecast</th>
                            <th>30-Day Forecast</th>
                            <th>Days of Stock</th>
                            <th>Recommendation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forecasts as $f): ?>
                        <?php
                        $rowClass = match($f['status']) {
                            'critical' => 'background: rgba(239,68,68,0.05);',
                            'warning' => 'background: rgba(245,158,11,0.05);',
                            'overstock' => 'background: rgba(99,102,241,0.05);',
                            default => ''
                        };
                        $trendIcon = match($f['trend']) {
                            'increasing' => '<i class="fas fa-arrow-up" style="color: var(--success);"></i>',
                            'decreasing' => '<i class="fas fa-arrow-down" style="color: var(--error);"></i>',
                            default => '<i class="fas fa-minus" style="color: var(--gray-400);"></i>'
                        };
                        ?>
                        <tr style="<?php echo $rowClass; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($f['product']['name']); ?></strong>
                                <br><small style="color: var(--gray-500);"><?php echo $f['product']['category_name'] ?? 'Uncategorized'; ?></small>
                            </td>
                            <td>
                                <span class="<?php echo $f['product']['stock'] <= $f['product']['low_stock_threshold'] ? 'stock-low' : 'stock-in'; ?>">
                                    <?php echo $f['product']['stock']; ?>
                                </span>
                            </td>
                            <td><?php echo $f['avg_daily_sales']; ?></td>
                            <td><?php echo $trendIcon; ?> <?php echo ucfirst($f['trend']); ?></td>
                            <td><?php echo $f['forecast_7_days']; ?> units</td>
                            <td><?php echo $f['forecast_30_days']; ?> units</td>
                            <td>
                                <?php if ($f['days_of_stock'] >= 999): ?>
                                    <span style="color: var(--gray-400);">N/A</span>
                                <?php else: ?>
                                    <span class="<?php echo $f['days_of_stock'] < 7 ? 'stock-out' : ($f['days_of_stock'] < 14 ? 'stock-low' : 'stock-in'); ?>">
                                        <?php echo $f['days_of_stock']; ?> days
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match($f['status']) {
                                    'critical' => 'badge-error',
                                    'warning' => 'badge-warning',
                                    'overstock' => 'badge-info',
                                    default => 'badge-success'
                                };
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.7rem;">
                                    <?php echo $f['recommendation']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
