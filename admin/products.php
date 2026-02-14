<?php
/**
 * Product Management
 */
$pageTitle = 'Products';
$_GET['page'] = 'products';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('admin');

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $price = (float)($_POST['price'] ?? 0);
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $sku = trim($_POST['sku'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $stock = (int)($_POST['stock'] ?? 0);
        $lowThreshold = (int)($_POST['low_threshold'] ?? 10);
        
        if (empty($name) || $price <= 0) {
            $error = 'Name and price are required';
        } else {
            if ($action === 'create') {
                $productId = db()->insert("
                    INSERT INTO products (name, category_id, supplier_id, price, cost_price, sku, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [$name, $categoryId, $supplierId, $price, $costPrice, $sku, $description]);
                
                db()->insert("INSERT INTO inventory (product_id, quantity, low_stock_threshold) VALUES (?, ?, ?)",
                    [$productId, $stock, $lowThreshold]);
                
                $message = 'Product created successfully';
            } else {
                db()->execute("
                    UPDATE products SET name = ?, category_id = ?, supplier_id = ?, price = ?, cost_price = ?, sku = ?, description = ?
                    WHERE id = ?
                ", [$name, $categoryId, $supplierId, $price, $costPrice, $sku, $description, $productId]);
                
                db()->execute("UPDATE inventory SET quantity = ?, low_stock_threshold = ? WHERE product_id = ?",
                    [$stock, $lowThreshold, $productId]);
                
                $message = 'Product updated successfully';
            }
        }
    } elseif ($action === 'delete') {
        $productId = (int)($_POST['product_id'] ?? 0);
        db()->execute("UPDATE products SET is_active = 0 WHERE id = ?", [$productId]);
        $message = 'Product deleted';
    }
}

// Get categories and suppliers for dropdowns
$categories = db()->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$suppliers = db()->fetchAll("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");

// Pagination
$perPage = 10;
$totalItems = db()->count("SELECT COUNT(*) FROM products WHERE is_active = 1");
$totalPages = ceil($totalItems / $perPage);
$pageNum = max(1, min($totalPages, (int)($_GET['p'] ?? 1)));
$offset = ($pageNum - 1) * $perPage;

// Get products
$products = db()->fetchAll("
    SELECT p.*, c.name as category_name, s.name as supplier_name, i.quantity as stock, i.low_stock_threshold
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN inventory i ON p.id = i.product_id
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");

require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $message; ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
</script>
<?php endif; ?>

<!-- Products Page -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">Most Popular Products</div>
        <button onclick="openProductModal()" class="admin-btn admin-btn-primary">
            <i class="fas fa-plus"></i> Add Product
        </button>
    </div>
    <div class="admin-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>ID</th>
                    <th>Price</th>
                    <th>Created At</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <div class="admin-table-product">
                            <div class="admin-table-product-img" style="overflow: hidden; border-radius: 8px;">
                                <?php if (!empty($product['image_url'])): ?>
                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                <i class="fas fa-box" style="font-size: 1.5rem; color: var(--admin-text-muted);"></i>
                                <?php endif; ?>
                            </div>
                            <div class="admin-table-product-info">
                                <span class="admin-table-product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                <span class="admin-table-product-count">(<?php echo $product['stock']; ?>)</span>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                    <td>P-<?php echo str_pad($product['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><strong>₱<?php echo number_format($product['price'], 0); ?></strong></td>
                    <td><?php echo date('d M Y', strtotime($product['created_at'])); ?></td>
                    <td>
                        <?php
                        $statusClass = 'admin-badge-published';
                        $statusText = 'Published';
                        if ($product['stock'] <= 0) {
                            $statusClass = 'admin-badge-inactive';
                            $statusText = 'Inactive';
                        } elseif ($product['stock'] <= $product['low_stock_threshold']) {
                            $statusClass = 'admin-badge-draft';
                            $statusText = 'Low Stock';
                        }
                        ?>
                        <span class="admin-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </td>
                    <td>
                        <button onclick='editProduct(<?php echo json_encode($product); ?>)' class="admin-action-btn">
                            <i class="fas fa-ellipsis-h"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--admin-border);">
        <span style="font-size: 0.75rem; color: var(--admin-text-muted);">
            Showing page <strong><?php echo $pageNum; ?></strong> of <?php echo $totalPages; ?>
        </span>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <?php 
            $start = max(1, $pageNum - 1);
            $end = min($totalPages, $pageNum + 1);
            
            if($pageNum > 1) echo '<a href="?p=1" class="admin-pagination-btn" title="First"><i class="fas fa-angles-left" style="font-size: 10px;"></i></a>';
            
            for($i = $start; $i <= $end; $i++): ?>
                <a href="?p=<?php echo $i; ?>" class="admin-pagination-btn <?php echo $i == $pageNum ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; 
            
            if($pageNum < $totalPages) echo '<a href="?p='.$totalPages.'" class="admin-pagination-btn" title="Last"><i class="fas fa-angles-right" style="font-size: 10px;"></i></a>';
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Product Modal -->
<div id="productModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999;">
    <div class="admin-card" style="max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div class="admin-card-header">
            <div class="admin-card-title" id="modalTitle">Add Product</div>
            <button onclick="closeProductModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-text-muted);">&times;</button>
        </div>
        <form method="POST">
            <div class="admin-card-body">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="product_id" id="productId">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Product Name *</label>
                    <input type="text" name="name" id="productName" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Category</label>
                        <select name="category_id" id="productCategory" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Price *</label>
                        <input type="number" name="price" id="productPrice" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;" step="0.01" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">Stock</label>
                        <input type="number" name="stock" id="productStock" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;" value="0">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500;">SKU</label>
                        <input type="text" name="sku" id="productSku" style="width: 100%; padding: 0.5rem; border: 1px solid var(--admin-border); border-radius: 6px;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="button" onclick="closeProductModal()" class="admin-btn admin-btn-ghost">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1;">Save Product</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openProductModal() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value = 'create';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productCategory').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productStock').value = '0';
    document.getElementById('productSku').value = '';
    document.getElementById('productModal').style.display = 'flex';
}

function editProduct(product) {
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('formAction').value = 'update';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productCategory').value = product.category_id || '';
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productStock').value = product.stock || 0;
    document.getElementById('productSku').value = product.sku || '';
    document.getElementById('productModal').style.display = 'flex';
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
