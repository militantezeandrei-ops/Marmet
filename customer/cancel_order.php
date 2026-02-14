<?php
/**
 * Cancel Order - Customer Action
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole('customer');

$userId = getCurrentUserId();
$orderId = (int)($_GET['id'] ?? 0);

if (!$orderId) {
    header('Location: orders.php');
    exit;
}

try {
    db()->beginTransaction();

    // Fetch order and ensure it belongs to the user and is in a cancellable state
    $order = db()->fetch("
        SELECT * FROM orders 
        WHERE id = ? AND user_id = ? 
        FOR UPDATE
    ", [$orderId, $userId]);

    if (!$order) {
        throw new Exception("Order not found.");
    }

    // Only allow cancellation for Pending or Confirmed orders
    if (!in_array($order['order_status'], ['Pending', 'Confirmed'])) {
        throw new Exception("This order cannot be cancelled anymore.");
    }

    // Update order status
    db()->execute("
        UPDATE orders 
        SET order_status = 'Cancelled', updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ", [$orderId]);

    // Restore stock
    $items = db()->fetchAll("SELECT product_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
    foreach ($items as $item) {
        db()->execute("
            UPDATE inventory 
            SET quantity = quantity + ? 
            WHERE product_id = ?
        ", [$item['quantity'], $item['product_id']]);

        // Log stock movement
        db()->insert("
            INSERT INTO stock_movements (product_id, quantity_change, movement_type, reference_id, notes)
            VALUES (?, ?, 'return', ?, ?)
        ", [$item['product_id'], $item['quantity'], $orderId, "Customer Cancellation of #" . $order['order_number']]);
    }

    db()->commit();
    header('Location: orders.php?cancelled=1');
} catch (Exception $e) {
    db()->rollback();
    header('Location: orders.php?error=' . urlencode($e->getMessage()));
}
exit;
