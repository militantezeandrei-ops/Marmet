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

<div style="margin-bottom: 2.5rem; padding: 2.5rem; background: var(--admin-gradient-primary); border-radius: 30px; color: white; box-shadow: var(--admin-shadow-premium); position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center;">
    <div style="position: absolute; top: 0; left: 0; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0) 70%); transform: translate(-20%, -20%);"></div>
    <div style="position: relative; z-index: 1;">
        <h1 style="font-size: 2.75rem; font-weight: 900; letter-spacing: -0.03em; margin: 0;">Supplier Hub</h1>
        <p style="opacity: 0.9; font-size: 1.2rem; font-weight: 500; margin-top: 0.25rem;">Supply chain management & inventory coordination</p>
    </div>
    <div style="position: relative; z-index: 1;">
        <a href="dashboard.php" class="admin-btn hover-lift" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 16px; padding: 0.75rem 1.5rem; font-weight: 600; text-decoration: none;">
            <i class="fas fa-th-large"></i> &nbsp; Dashboard
        </a>
    </div>
</div>

<div class="section" style="padding-top: 2rem;">
    <div class="container">
        <?php if (!empty($lowStockBySupplier)): ?>
        <div class="glass-card pulse-warning" style="margin-bottom: 2.5rem; padding: 1.5rem 2rem; background: rgba(245, 158, 11, 0.05); border-color: rgba(245, 158, 11, 0.2); border-radius: 18px; display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 48px; height: 48px; background: var(--admin-warning); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">
                <i class="fas fa-exclamation-triangle" style="font-size: 1.25rem;"></i>
            </div>
            <div>
                <h4 style="color: var(--admin-text-primary); font-weight: 700; margin: 0; font-size: 1.1rem;">Supply Alert</h4>
                <p style="color: var(--admin-text-secondary); margin: 0; font-size: 0.95rem;">You have <strong><?php echo count($lowStockBySupplier); ?> products</strong> reaching critical stock levels. Direct contact links are available below.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
            <?php foreach ($suppliers as $supplier): ?>
            <?php 
            $supplierLowStock = array_filter($lowStockBySupplier, fn($p) => $p['supplier_id'] == $supplier['id']);
            ?>
            <div class="glass-card hover-lift" style="border-radius: 24px; padding: 0; overflow: hidden; background: var(--admin-content-bg);">
                <div class="admin-card-header" style="padding: 1.75rem 2rem; background: var(--admin-gradient-surface); border-bottom: 1px solid var(--admin-border); display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <span style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--admin-text-muted); display: block; margin-bottom: 0.25rem;">Trusted Partner</span>
                        <h3 style="font-size: 1.4rem; font-weight: 800; color: var(--admin-text-primary); margin: 0;"><?php echo htmlspecialchars($supplier['name']); ?></h3>
                    </div>
                    <?php if (!empty($supplierLowStock)): ?>
                    <span class="admin-badge admin-badge-warning pulse-warning" style="padding: 0.4rem 0.8rem; border-radius: 10px;">
                        <i class="fas fa-dolly-flatbed" style="margin-right: 0.4rem;"></i>
                        <?php echo count($supplierLowStock); ?> Shortage
                    </span>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding: 2rem;">
                    <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.875rem;">
                            <div style="width: 32px; height: 32px; background: var(--admin-bg); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--admin-text-muted);">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <span style="font-weight: 600; color: var(--admin-text-primary);"><?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.875rem;">
                            <div style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--admin-primary);">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <a href="mailto:<?php echo $supplier['email']; ?>" style="color: var(--admin-primary); text-decoration: none; font-weight: 500;"><?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?></a>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.875rem;">
                            <div style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--admin-success);">
                                <i class="fas fa-phone"></i>
                            </div>
                            <a href="tel:<?php echo $supplier['phone']; ?>" style="color: var(--admin-text-primary); text-decoration: none; font-weight: 600;"><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></a>
                        </div>
                    </div>
                    <div style="padding: 1rem; background: var(--admin-bg); border-radius: 12px; font-size: 0.85rem; color: var(--admin-text-secondary); display: flex; justify-content: space-between; align-items: center;">
                        <span>Integrated Inventory:</span>
                        <strong style="color: var(--admin-text-primary);"><?php echo $supplier['product_count']; ?> active products</strong>
                    </div>
                    
                    <?php if (!empty($supplierLowStock)): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--admin-border);">
                        <strong style="color: var(--admin-warning); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.75rem;">Restock Pipeline Needed:</strong>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php foreach ($supplierLowStock as $item): ?>
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; padding: 0.5rem 0;">
                                <span style="color: var(--admin-text-secondary);"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                <span style="font-weight: 700; color: <?php echo $item['stock'] == 0 ? 'var(--admin-error)' : 'var(--admin-warning)'; ?>;">
                                    <?php echo $item['stock']; ?> left
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
