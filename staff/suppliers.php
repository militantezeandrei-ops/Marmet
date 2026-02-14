<?php
/**
 * Supplier Management
 */
$pageTitle = 'Suppliers';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole(['staff', 'admin']);

// Get suppliers with product counts
$suppliers = db()->fetchAll("
    SELECT s.*, COUNT(p.id) as product_count
    FROM suppliers s
    LEFT JOIN products p ON s.id = p.supplier_id AND p.is_active = 1
    WHERE s.is_active = 1
    GROUP BY s.id
    ORDER BY s.name
");

// Get low stock products by supplier
$lowStockBySupplier = db()->fetchAll("
    SELECT p.name as product_name, p.sku, i.quantity as stock, i.low_stock_threshold, s.name as supplier_name, s.id as supplier_id
    FROM products p
    JOIN inventory i ON p.id = i.product_id
    JOIN suppliers s ON p.supplier_id = s.id
    WHERE i.quantity <= i.low_stock_threshold AND p.is_active = 1
    ORDER BY s.name, i.quantity ASC
");

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Supplier Coordination</h1>
        <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="section" style="padding-top: 2rem;">
    <div class="container">
        <?php if (!empty($lowStockBySupplier)): ?>
        <div class="alert alert-warning mb-3">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?php echo count($lowStockBySupplier); ?> products</strong> need restocking. Contact suppliers below.
        </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
            <?php foreach ($suppliers as $supplier): ?>
            <?php 
            $supplierLowStock = array_filter($lowStockBySupplier, fn($p) => $p['supplier_id'] == $supplier['id']);
            ?>
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <h3><?php echo htmlspecialchars($supplier['name']); ?></h3>
                    <?php if (!empty($supplierLowStock)): ?>
                    <span class="badge badge-warning"><?php echo count($supplierLowStock); ?> low stock</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></p>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo $supplier['email']; ?>"><?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?></a></p>
                    <p><i class="fas fa-phone"></i> <a href="tel:<?php echo $supplier['phone']; ?>"><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></a></p>
                    <p style="color: var(--gray-500); font-size: 0.875rem;"><?php echo $supplier['product_count']; ?> products</p>
                    
                    <?php if (!empty($supplierLowStock)): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                        <strong style="color: var(--warning);">Items to Reorder:</strong>
                        <ul style="margin-top: 0.5rem; padding-left: 1.25rem; font-size: 0.875rem;">
                            <?php foreach ($supplierLowStock as $item): ?>
                            <li>
                                <?php echo htmlspecialchars($item['product_name']); ?> 
                                (<?php echo $item['sku']; ?>) - 
                                <span class="<?php echo $item['stock'] == 0 ? 'stock-out' : 'stock-low'; ?>">
                                    <?php echo $item['stock']; ?> left
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
