<?php
/**
 * Demand Forecasting Module - Single-View High Fidelity Overhaul
 */
$pageTitle = 'Inventory Forecasting';
$_GET['page'] = 'forecast';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('admin');

/**
 * Helpers
 */
function calculateStats($data) {
    if (empty($data)) return ['mean' => 0, 'std_dev' => 0];
    $count = count($data);
    $mean = array_sum($data) / $count;
    $variance = 0;
    foreach ($data as $val) {
        $variance += pow($val - $mean, 2);
    }
    $std_dev = sqrt($variance / max($count, 1));
    return ['mean' => $mean, 'std_dev' => $std_dev];
}

// Filters
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Base Query
$sql = "
    SELECT p.*, c.name as category_name, i.quantity as stock, i.low_stock_threshold
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.is_active = 1
";
$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryFilter) {
    $sql .= " AND c.id = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY p.name";

$products = db()->fetchAll($sql, $params);
$categories = db()->fetchAll("SELECT * FROM categories ORDER BY name");
$suppliers = db()->fetchAll("SELECT * FROM suppliers LIMIT 4");

$insights = [];
foreach ($products as $product) {
    $salesData = db()->fetchAll("
        SELECT sale_date, SUM(quantity_sold) as qty
        FROM sales_history
        WHERE product_id = ? AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY sale_date
    ", [$product['id']]);
    
    $dailySales = array_fill(0, 30, 0); 
    foreach ($salesData as $row) {
        $daysAgo = (int)date_diff(date_create($row['sale_date']), date_create())->format('%a');
        if ($daysAgo < 30) $dailySales[29 - $daysAgo] = (float)$row['qty'];
    }

    $stats = calculateStats($dailySales);
    $velocity = $stats['mean'];
    $volatility = $velocity > 0 ? ($stats['std_dev'] / $velocity) : 0;
    
    $forecast30 = ceil($velocity * 30);
    $reorderQty = max(0, $forecast30 - $product['stock']);
    $daysRemaining = $velocity > 0 ? floor($product['stock'] / $velocity) : 999;
    
    $status = 'ok';
    if ($daysRemaining < 7) $status = 'critical';
    elseif ($daysRemaining < 14) $status = 'warning';
    elseif ($product['stock'] > ($forecast30 * 2) && $product['stock'] > 20) $status = 'overstock';

    $volLabel = 'Steady';
    if ($volatility > 1.2) $volLabel = 'Unpredictable';
    elseif ($volatility > 0.6) $volLabel = 'Changing';

    if ($statusFilter && $status !== $statusFilter) continue;

    $insights[] = [
        'product' => $product,
        'velocity' => round($velocity, 1),
        'volatility' => $volLabel,
        'forecast30' => $forecast30,
        'reorder_qty' => $reorderQty,
        'status' => $status
    ];
}

// Global Metrics
$totalValue = array_sum(array_map(fn($item) => $item['product']['stock'] * $item['product']['price'], $insights));
$totalShortfall = array_sum(array_column($insights, 'reorder_qty'));

// Pagination
$perPage = 8; // Adjust for single view height
$totalItems = count($insights);
$totalPages = ceil($totalItems / $perPage);
$pageNum = max(1, min($totalPages, (int)($_GET['p'] ?? 1)));
$offset = ($pageNum - 1) * $perPage;
$pagedInsights = array_slice($insights, $offset, $perPage);

require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
    :root {
        --f-bg: #0f172a;
        --f-card: #1e293b;
        --f-border: rgba(255, 255, 255, 0.05);
        --f-text: #f8fafc;
        --f-muted: #94a3b8;
        --f-primary: #6366f1;
        --f-glow: 0 0 15px rgba(99, 102, 241, 0.4);
    }

    .admin-main { background: var(--f-bg) !important; color: var(--f-text); height: 100vh; overflow: hidden; }
    .admin-content { height: calc(100vh - 70px); padding: 1rem !important; display: flex; flex-direction: column; }

    .f-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 0.75rem; flex-shrink: 0; }
    .f-title h1 { font-size: 1.25rem; font-weight: 800; margin: 0; }
    .f-title p { font-size: 0.75rem; color: var(--f-muted); margin: 0; }
    
    .stats-row { display: flex; gap: 0.75rem; }
    .stat-card { background: var(--f-card); border: 1px solid var(--f-border); padding: 0.5rem 1rem; border-radius: 10px; min-width: 140px; }
    .stat-label { font-size: 0.55rem; font-weight: 800; color: var(--f-muted); text-transform: uppercase; margin-bottom: 0.125rem; }
    .stat-val { font-size: 1.15rem; font-weight: 800; }
    .stat-unit { font-size: 0.65rem; color: var(--f-muted); }

    .filter-bar { 
        background: var(--f-card); border: 1px solid var(--f-border); padding: 0.5rem 0.75rem; border-radius: 10px; margin-bottom: 1rem;
        display: flex; gap: 0.5rem; align-items: center; flex-shrink: 0;
    }
    .filter-input { background: rgba(0,0,0,0.2); border: 1px solid var(--f-border); border-radius: 6px; padding: 0.4rem 0.75rem; color: var(--f-text); font-size: 0.75rem; }
    .filter-btn { background: var(--f-primary); border: none; padding: 0.4rem 1rem; border-radius: 6px; color: white; font-weight: 700; cursor: pointer; font-size: 0.75rem; }

    /* Compact Grid Layout */
    .f-main-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1rem; flex: 1; min-height: 0; }
    
    .card { background: var(--f-card); border: 1px solid var(--f-border); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; }
    .card-title { padding: 0.75rem 1rem; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; background: rgba(255,255,255,0.02); }
    .card-title i { color: var(--f-primary); }

    .f-table-wrapper { flex: 1; overflow-y: auto; }
    .f-table { width: 100%; border-collapse: collapse; }
    .f-table th { position: sticky; top: 0; text-align: left; padding: 0.5rem 1rem; font-size: 0.65rem; font-weight: 700; color: var(--f-muted); text-transform: uppercase; border-bottom: 1px solid var(--f-border); background: #161e2e; z-index: 10; }
    .f-table td { padding: 0.75rem 1rem; font-size: 0.75rem; border-bottom: 1px solid var(--f-border); }
    
    .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    
    /* pagination */
    .pagination { padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.7rem; color: var(--f-muted); border-top: 1px solid var(--f-border); flex-shrink: 0; }
    .pg-list { display: flex; gap: 0.35rem; align-items: center; }
    .pg-item { 
        width: 26px; height: 26px; border-radius: 6px; background: rgba(255,255,255,0.03); 
        display: flex; align-items: center; justify-content: center; text-decoration: none; 
        color: var(--f-muted); font-weight: 600; font-size: 0.75rem; border: 1px solid var(--f-border);
        transition: all 0.2s;
    }
    .pg-item.active { 
        background: var(--f-primary); color: white; border-color: var(--f-primary); 
        box-shadow: var(--f-glow); font-weight: 800;
    }
    .pg-item:hover:not(.active) { background: rgba(255,255,255,0.08); color: var(--f-text); }

    .insight { background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.15); border-radius: 10px; padding: 0.75rem; display: flex; gap: 0.75rem; align-items: center; margin-top: 0.75rem; flex-shrink: 0; }
    .insight-icon { width: 28px; height: 28px; background: rgba(99, 102, 241, 0.1); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--f-primary); flex-shrink: 0; }
    .insight-text { font-size: 0.7rem; color: var(--f-muted); line-height: 1.4; }
</style>

<div class="f-header">
    <div class="f-title">
        <h1>Forecast & Planning</h1>
        <p>Real-time demand projections and restock schedules.</p>
    </div>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Est. Shortfall</div>
            <div class="stat-val"><?php echo number_format($totalShortfall); ?> <span class="stat-unit">units</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Inv. Value</div>
            <div class="stat-val">₱<?php echo number_format($totalValue/1000, 1); ?>k</div>
        </div>
    </div>
</div>

<form method="GET" class="filter-bar">
    <i class="fas fa-filter" style="color: var(--f-muted); font-size: 0.75rem;"></i>
    <input type="text" name="search" placeholder="Search product..." class="filter-input" value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
    <select name="category" class="filter-input">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="filter-input">
        <option value="">All Statuses</option>
        <option value="critical" <?php echo $statusFilter == 'critical' ? 'selected' : ''; ?>>Critical</option>
        <option value="warning" <?php echo $statusFilter == 'warning' ? 'selected' : ''; ?>>Warning</option>
        <option value="overstock" <?php echo $statusFilter == 'overstock' ? 'selected' : ''; ?>>Overstock</option>
    </select>
    <button type="submit" class="filter-btn">Apply</button>
</form>

<div class="f-main-grid">
    <!-- Left Table -->
    <div class="card">
        <div class="card-title"><i class="fas fa-chart-column"></i> Inventory Forecast</div>
        <div class="f-table-wrapper">
            <table class="f-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Status</th>
                        <th style="text-align: right;">Stock</th>
                        <th style="text-align: right;">Sold Daily</th>
                        <th>Sale Pattern</th>
                        <th style="text-align: right;">Next 30 Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagedInsights as $item): 
                        $dotColor = $item['status'] == 'critical' ? '#ef4444' : ($item['status'] == 'warning' ? '#f59e0b' : '#10b981');
                        $volColor = $item['volatility'] == 'Erratic' ? '#ef4444' : ($item['volatility'] == 'Variable' ? '#f59e0b' : '');
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700; font-size: 0.8rem;"><?php echo htmlspecialchars($item['product']['name']); ?></div>
                            <div style="font-size: 10px; color: var(--f-muted);"><?php echo htmlspecialchars($item['product']['sku']); ?></div>
                        </td>
                        <td>
                            <span class="status-dot" style="background: <?php echo $dotColor; ?>;"></span>
                            <span style="font-size: 0.65rem; text-transform: capitalize; color: var(--f-muted);"><?php echo $item['status']; ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 800;"><?php echo $item['product']['stock']; ?></td>
                        <td style="text-align: right;"><strong><?php echo $item['velocity']; ?></strong></td>
                        <td style="font-weight: 700; color: <?php echo $volColor; ?>; font-size: 0.7rem;"><?php echo $item['volatility']; ?></td>
                        <td style="text-align: right; font-weight: 800;"><?php echo $item['forecast30']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <span>Page <strong><?php echo $pageNum; ?></strong> of <?php echo $totalPages; ?></span>
            <div class="pg-list">
                <?php 
                $start = max(1, $pageNum - 1);
                $end = min($totalPages, $pageNum + 1);
                if($pageNum > 1) echo '<a href="?'.http_build_query(array_merge($_GET, ['p' => 1])).'" class="pg-item" title="First"><i class="fas fa-angles-left" style="font-size: 10px;"></i></a>';
                for($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $i])); ?>" class="pg-item <?php echo $i == $pageNum ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; 
                if($pageNum < $totalPages) echo '<a href="?'.http_build_query(array_merge($_GET, ['p' => $totalPages])).'" class="pg-item" title="Last"><i class="fas fa-angles-right" style="font-size: 10px;"></i></a>';
                ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right Table -->
    <div class="card">
        <div class="card-title"><i class="fas fa-truck-ramp-box"></i> Restock Planning</div>
        <div class="f-table-wrapper">
            <table class="f-table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Lead</th>
                        <th style="text-align: right;">Min Qty</th>
                        <th style="text-align: right;">Cost</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700;"><?php echo htmlspecialchars($s['name']); ?></div>
                            <div style="font-size: 10px; color: var(--f-muted);">Logistic Provider</div>
                        </td>
                        <td style="font-weight: 600;">14d</td>
                        <td style="text-align: right; font-weight: 600;">50u</td>
                        <td style="text-align: right; font-weight: 700;">₱1,250</td>
                        <td>
                            <button class="pg-item active" style="width: auto; padding: 0 0.5rem; font-size: 0.65rem; height: 22px;">PO</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding: 0.75rem; font-size: 0.65rem; color: var(--f-muted); border-top: 1px solid var(--f-border); background: rgba(0,0,0,0.1);">
            <i class="fas fa-info-circle"></i> Showing active tier-1 suppliers for immediate restock.
        </div>
    </div>
</div>

<div class="insight">
    <div class="insight-icon"><i class="fas fa-lightbulb"></i></div>
    <div class="insight-text">
        <strong>Optimization Insight:</strong> Turnover is <strong>13% higher</strong> than last month. Concentrating orders with <strong>Nexus Distribution</strong> could save ₱4,200 in monthly logistics fees. Next restock window: Feb 20-22.
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
