<?php
/**
 * Checkout Page - ShopEase Premium UI
 */
$pageTitle = 'Checkout';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();
requireRole('customer');

$userId = getCurrentUserId();
$user = getCurrentUser();

// Get items to checkout
$itemsToCheckout = [];
$buyNowMode = false;

if (isset($_GET['buy_now_product_id'])) {
    $productId = (int)$_GET['buy_now_product_id'];
    $qty = (int)($_GET['buy_now_qty'] ?? 1);
    
    $product = db()->fetch("
        SELECT p.*, i.quantity as stock 
        FROM products p 
        LEFT JOIN inventory i ON p.id = i.product_id 
        WHERE p.id = ? AND p.is_active = 1
    ", [$productId]);
    
    if ($product) {
        $itemsToCheckout[] = [
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'image_url' => $product['image_url'],
            'quantity' => $qty,
            'stock' => $product['stock']
        ];
        $buyNowMode = true;
    }
} elseif (isset($_GET['cart_item_ids'])) {
    $itemIds = explode(',', $_GET['cart_item_ids']);
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $params = array_merge([$userId], $itemIds);
    
    $itemsToCheckout = db()->fetchAll("
        SELECT c.*, p.name, p.price, p.image_url, i.quantity as stock
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE c.user_id = ? AND c.id IN ($placeholders)
    ", $params);
}

if (empty($itemsToCheckout)) {
    header('Location: cart.php');
    exit;
}

// Calculate totals
$subtotal = 0;
foreach ($itemsToCheckout as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * TAX_RATE;
$total = $subtotal + $tax;

$error = '';

// Parse saved address for pre-filling
$addrParts = !empty($user['address']) ? explode(', ', $user['address']) : [];
$savedStreet = $addrParts[0] ?? '';
$savedBarangay = $addrParts[1] ?? '';
$savedCity = $addrParts[2] ?? '';
$savedProvince = $addrParts[3] ?? '';
$savedZip = $addrParts[4] ?? '';

$hasSavedAddress = !empty($user['address']);
$showFields = !$hasSavedAddress || !empty($_POST);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect structured address fields
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    
    // Concatenate for DB storage
    $address = "$street, $barangay, $city, $province, $zip";
    
    $paymentMethod = $_POST['payment_method'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($street) || empty($barangay) || empty($city) || empty($province) || empty($zip)) {
        $error = 'Please fill in all shipping address fields';
    } elseif (!in_array($paymentMethod, ['cod', 'gcash'])) {
        $error = 'Please select a payment method';
    } else {
        try {
            db()->beginTransaction();
            
            // Re-verify stock for all items before proceeding
            foreach ($itemsToCheckout as $item) {
                $currentStock = db()->fetch("SELECT quantity FROM inventory WHERE product_id = ? FOR UPDATE", [$item['product_id']])['quantity'] ?? 0;
                if ($currentStock < $item['quantity']) {
                    throw new Exception("Insufficient stock for: " . $item['name']);
                }
            }

            $orderNumber = ORDER_PREFIX . date('Ymd') . strtoupper(substr(md5(uniqid()), 0, 6));
            
            $orderId = db()->insert("
                INSERT INTO orders (user_id, order_number, subtotal, tax_amount, total_amount, payment_method, shipping_address, notes, order_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ", [$userId, $orderNumber, $subtotal, $tax, $total, $paymentMethod, $address, $notes]);
            
            foreach ($itemsToCheckout as $item) {
                // Deduct stock
                db()->execute("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?", [$item['quantity'], $item['product_id']]);
                
                // Log stock movement
                db()->insert("
                    INSERT INTO stock_movements (product_id, quantity_change, movement_type, reference_id, notes)
                    VALUES (?, ?, 'sale', ?, ?)
                ", [$item['product_id'], -$item['quantity'], $orderId, "Order #$orderNumber"]);

                db()->insert("
                    INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$orderId, $item['product_id'], $item['name'], $item['quantity'], $item['price'], $item['price'] * $item['quantity']]);
            }
            
            db()->insert("INSERT INTO payments (order_id, amount, payment_method) VALUES (?, ?, ?)", [$orderId, $total, $paymentMethod]);
            
            if (!$buyNowMode) {
                $itemIds = array_column($itemsToCheckout, 'id');
                $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
                db()->execute("DELETE FROM cart WHERE user_id = ? AND id IN ($placeholders)", array_merge([$userId], $itemIds));
            }
            
            db()->commit();
            header('Location: customer/orders.php?success=1&order=' . $orderNumber);
            exit;
        } catch (Exception $e) {
            db()->rollback();
            $error = $e->getMessage() ?: 'Failed to process order. Please try again.';
        }
    }
}

$breadcrumbs = [
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'Cart', 'url' => 'cart.php'],
    ['label' => 'Checkout']
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5" style="max-width: 1000px;">

    <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 2.5rem; color: var(--gray-900);">Checkout</h1>

    <?php if ($error): ?>
    <div class="alert alert-danger se-card mb-4" style="border: none; background: #fef2f2; color: #991b1b; display: flex; align-items: center; gap: 1rem;">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="se-checkout-layout">
            <div class="checkout-main">
                <!-- Shipping Details Card -->
                <div class="se-card mb-4">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-shipping-fast" style="color: var(--se-primary);"></i> Shipping Details
                        </h3>
                        <?php if ($hasSavedAddress): ?>
                        <button type="button" id="editAddressBtn" class="btn btn-sm" style="color: var(--se-primary); font-weight: 600; font-size: 0.875rem;" onclick="toggleAddressFields()">
                            <i class="fas fa-edit"></i> Edit Address
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Saved Address Display -->
                    <?php if ($hasSavedAddress): ?>
                    <div id="savedAddressBox" style="display: <?php echo $showFields ? 'none' : 'block'; ?>; padding: 1.25rem; background: var(--se-bg-soft); border-radius: 16px; border: 1px solid var(--gray-100); margin-bottom: 1.5rem;">
                        <div style="color: var(--gray-500); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Deliver to:</div>
                        <div style="font-weight: 700; color: var(--gray-900); font-size: 1.1rem; margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </div>
                        <div style="color: var(--gray-600); font-weight: 500; font-size: 0.9375rem; line-height: 1.5;">
                            <?php echo htmlspecialchars($user['address']); ?>
                        </div>
                        <div style="margin-top: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: var(--gray-500); font-size: 0.875rem;">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Address Fields (Shown if no saved address or editing) -->
                    <div id="addressFormFields" style="display: <?php echo $showFields ? 'block' : 'none'; ?>;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">Full Name</label>
                                <div style="padding: 0.75rem; background: var(--se-bg-soft); border-radius: 12px; font-weight: 600;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">Phone Number</label>
                                <div style="padding: 0.75rem; background: var(--se-bg-soft); border-radius: 12px; font-weight: 600;"><?php echo htmlspecialchars($user['phone'] ?? '+63 XXX XXX XXXX'); ?></div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">Street Address / House Number *</label>
                            <input type="text" name="street" class="form-control" required style="border-radius: 12px; border: 1px solid var(--gray-200); padding: 0.75rem 1rem;" placeholder="e.g. 123 Rizal St." value="<?php echo htmlspecialchars($_POST['street'] ?? $savedStreet); ?>">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">Barangay / Neighborhood *</label>
                                <input type="text" name="barangay" class="form-control" required style="border-radius: 12px; border: 1px solid var(--gray-200); padding: 0.75rem 1rem;" placeholder="e.g. Brgy. 1" value="<?php echo htmlspecialchars($_POST['barangay'] ?? $savedBarangay); ?>">
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">City / Municipality *</label>
                                <input type="text" name="city" class="form-control" required style="border-radius: 12px; border: 1px solid var(--gray-200); padding: 0.75rem 1rem;" placeholder="e.g. Makati City" value="<?php echo htmlspecialchars($_POST['city'] ?? $savedCity); ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">Province / State *</label>
                                <input type="text" name="province" class="form-control" required style="border-radius: 12px; border: 1px solid var(--gray-200); padding: 0.75rem 1rem;" placeholder="e.g. Metro Manila" value="<?php echo htmlspecialchars($_POST['province'] ?? $savedProvince); ?>">
                            </div>
                            <div class="form-group">
                                <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">Postal / Zip Code *</label>
                                <input type="text" name="zip" class="form-control" required style="border-radius: 12px; border: 1px solid var(--gray-200); padding: 0.75rem 1rem;" placeholder="e.g. 1200" value="<?php echo htmlspecialchars($_POST['zip'] ?? $savedZip); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: 0.5rem;">Order Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" style="border-radius: 12px; border: 1px solid var(--gray-200); padding: 1rem;" placeholder="e.g., Leave at the reception, Near market etc."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Payment Method Card -->
                <div class="se-card">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-credit-card" style="color: var(--se-primary);"></i> Payment Method
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <label class="se-card" style="padding: 1.25rem; border: 2px solid var(--gray-100); cursor: pointer; display: flex; align-items: center; gap: 1.5rem; position: relative; transition: border-color 0.2s;">
                            <input type="radio" name="payment_method" value="cod" <?php echo ($_POST['payment_method'] ?? '') !== 'gcash' ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <div style="width: 48px; height: 48px; background: #f0fdf4; color: #16a34a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700;">Cash on Delivery (COD)</div>
                                <div style="font-size: 0.875rem; color: var(--gray-500);">Pay in cash when your order is delivered.</div>
                            </div>
                        </label>

                        <label class="se-card" style="padding: 1.25rem; border: 2px solid var(--gray-100); cursor: pointer; display: flex; align-items: center; gap: 1.5rem; position: relative; transition: border-color 0.2s;">
                            <input type="radio" name="payment_method" value="gcash" <?php echo ($_POST['payment_method'] ?? '') === 'gcash' ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <div style="width: 48px; height: 48px; background: #eff6ff; color: #2563eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800;">
                                G
                            </div>
                            <div>
                                <div style="font-weight: 700;">GCash</div>
                                <div style="font-size: 0.875rem; color: var(--gray-500);">Scan or pay through the GCash mobile app.</div>
                            </div>
                        </label>
                    </div>

                    <div id="gcashInstructions" class="alert mt-4 se-card" style="display: none; background: #eff6ff; border: 1px dashed var(--se-primary); flex-direction: column; gap: 0.5rem;">
                        <div style="font-weight: 700; color: var(--se-primary);">GCash Payment Details:</div>
                        <div style="font-size: 0.9375rem;">Account Name: <strong><?php echo GCASH_NAME; ?></strong></div>
                        <div style="font-size: 0.9375rem;">Account Number: <strong><?php echo GCASH_NUMBER; ?></strong></div>
                        <p style="font-size: 0.8125rem; color: var(--gray-500); margin: 0.5rem 0 0;">Please follow the instructions in the app to complete the payment.</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar Order Summary -->
            <div class="checkout-sidebar">
                <div class="se-card sticky-top" style="top: 2rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem;">Order Summary</h3>
                    
                    <div style="max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem; padding-right: 0.5rem;">
                        <?php foreach ($itemsToCheckout as $item): ?>
                        <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
                            <div class="se-circle-img" style="width: 50px; height: 50px;">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-box-open" style="font-size: 1rem; color: var(--gray-400);"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 600; font-size: 0.9375rem; line-height: 1.2;"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div style="color: var(--gray-500); font-size: 0.8125rem;">Qty: <?php echo $item['quantity']; ?> × ₱<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            <div style="font-weight: 700; font-size: 0.9375rem;">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="border-top: 1px solid var(--gray-100); padding-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--gray-500);">Subtotal</span>
                            <span style="font-weight: 600;">₱<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--gray-500);">Shipping</span>
                            <span style="color: var(--success); font-weight: 600;">Free</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--gray-500);">VAT (<?php echo (TAX_RATE * 100); ?>%)</span>
                            <span style="font-weight: 600;">₱<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 1.5rem; font-weight: 800; color: var(--gray-900); margin-top: 0.5rem;">
                            <span>Total</span>
                            <span style="color: var(--se-primary);">₱<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>

                    <button type="submit" class="se-checkout-btn">
                        Place Order <i class="fas fa-check-circle"></i>
                    </button>
                    
                    <a href="cart.php" class="btn btn-block mt-3" style="color: var(--gray-500); font-weight: 600; text-decoration: none; text-align: center; font-size: 0.875rem;">
                        Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAddressFields() {
    const savedBox = document.getElementById('savedAddressBox');
    const formFields = document.getElementById('addressFormFields');
    const editBtn = document.getElementById('editAddressBtn');
    
    if (formFields.style.display === 'none') {
        formFields.style.display = 'block';
        savedBox.style.display = 'none';
        editBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Edit';
    } else {
        formFields.style.display = 'none';
        savedBox.style.display = 'block';
        editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Address';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const radioButtons = document.querySelectorAll('input[name="payment_method"]');
    const gcashInstructions = document.getElementById('gcashInstructions');

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            // Update UI for all options
            radioButtons.forEach(r => {
                r.closest('.se-card').style.borderColor = r.checked ? 'var(--se-primary)' : 'var(--gray-100)';
                r.closest('.se-card').style.backgroundColor = r.checked ? '#f8faff' : 'white';
            });

            gcashInstructions.style.display = this.value === 'gcash' ? 'flex' : 'none';
        });

        // Initialize UI
        if (radio.checked) {
            radio.closest('.se-card').style.borderColor = 'var(--se-primary)';
            radio.closest('.se-card').style.backgroundColor = '#f8faff';
            if (radio.value === 'gcash') gcashInstructions.style.display = 'flex';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
