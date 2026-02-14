<?php
/**
 * Payment Confirmation
 */
$pageTitle = 'Confirm Payments';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole(['staff', 'admin']);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reference = trim($_POST['reference'] ?? '');
    
    if ($paymentId && in_array($action, ['confirmed', 'failed'])) {
        db()->execute("
            UPDATE payments 
            SET status = ?, reference_number = ?, confirmed_by = ?, confirmed_at = NOW()
            WHERE id = ?
        ", [$action, $reference, getCurrentUserId(), $paymentId]);
        
        // Update order payment status
        $payment = db()->fetch("SELECT order_id FROM payments WHERE id = ?", [$paymentId]);
        if ($payment) {
            db()->execute("UPDATE orders SET payment_status = ? WHERE id = ?", [$action, $payment['order_id']]);
        }
        
        $message = 'Payment ' . ($action == 'confirmed' ? 'confirmed' : 'marked as failed');
    }
}

// Get pending payments
$pendingPayments = db()->fetchAll("
    SELECT p.*, o.order_number, o.total_amount as order_total, 
           u.first_name, u.last_name, u.email
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at ASC
");

// Get recent confirmed payments
$confirmedPayments = db()->fetchAll("
    SELECT p.*, o.order_number, u.first_name, u.last_name,
           c.first_name as confirmed_first, c.last_name as confirmed_last
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    LEFT JOIN users c ON p.confirmed_by = c.id
    WHERE p.status != 'pending'
    ORDER BY p.confirmed_at DESC
    LIMIT 20
");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Payment Confirmation</h1>
        <a href="dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="section" style="padding-top: 2rem;">
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-success mb-3"><i class="fas fa-check"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Pending Payments -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-clock" style="color: var(--warning);"></i> Pending Payments (<?php echo count($pendingPayments); ?>)</h3>
            </div>
            <?php if (empty($pendingPayments)): ?>
            <div class="card-body text-center" style="padding: 3rem;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success);"></i>
                <p style="margin-top: 1rem; color: var(--gray-500);">No pending payments</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPayments as $payment): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($payment['order_number']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                <br><small><?php echo $payment['email']; ?></small>
                            </td>
                            <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $payment['payment_method'] == 'gcash' ? 'primary' : 'info'; ?>">
                                    <?php echo strtoupper($payment['payment_method']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y h:i A', strtotime($payment['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <?php if ($payment['payment_method'] == 'gcash'): ?>
                                    <input type="text" name="reference" class="form-input" placeholder="GCash Ref #" style="width: 120px; padding: 0.5rem;">
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="confirmed" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="submit" name="action" value="failed" class="btn btn-sm btn-danger" onclick="return confirm('Mark as failed?')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Payment History</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Confirmed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($confirmedPayments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                            <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $payment['status'] == 'confirmed' ? 'success' : 'error'; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                            <td>
                                <?php echo htmlspecialchars(($payment['confirmed_first'] ?? '') . ' ' . ($payment['confirmed_last'] ?? '')); ?>
                                <br><small><?php echo $payment['confirmed_at'] ? date('M d, h:i A', strtotime($payment['confirmed_at'])) : '-'; ?></small>
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
