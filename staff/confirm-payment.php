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

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 2.5rem; padding: 2rem; background: var(--admin-gradient-primary); border-radius: 24px; color: white; box-shadow: var(--admin-shadow-premium); position: relative; overflow: hidden;">
    <div style="position: absolute; top: 0; right: 0; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%); transform: translate(30%, -30%);"></div>
    <div style="position: relative; z-index: 1;">
        <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 0.5rem;">Payment Confirmation</h1>
        <p style="opacity: 0.9; font-size: 1.1rem; font-weight: 500;">Verify references and authorize financial transactions</p>
    </div>
</div>

<div class="section" style="padding-top: 2rem;">
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-success mb-3"><i class="fas fa-check"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Pending Payments -->
        <div class="glass-card mb-5" style="border-radius: 24px; overflow: hidden;">
            <div class="admin-card-header" style="padding: 1.5rem 2rem; background: var(--admin-gradient-surface); border-bottom: 1px solid var(--admin-border); display: flex; align-items: center; justify-content: space-between;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--admin-text-primary); margin: 0;">
                    <i class="fas fa-clock pulse-warning" style="color: var(--admin-warning); margin-right: 0.75rem;"></i>
                    Pending Approvals (<?php echo count($pendingPayments); ?>)
                </h3>
            </div>
            <?php if (empty($pendingPayments)): ?>
            <div class="card-body text-center" style="padding: 3rem;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success);"></i>
                <p style="margin-top: 1rem; color: var(--gray-500);">No pending payments</p>
            </div>
            <?php else: ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="padding-left: 2rem;">Order Reference</th>
                            <th>Customer Profile</th>
                            <th>Transaction Amount</th>
                            <th>Method</th>
                            <th>Received At</th>
                            <th style="text-align: right; padding-right: 2rem;">Verification Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPayments as $payment): ?>
                        <tr>
                            <td style="padding-left: 2rem;"><strong style="color: var(--admin-primary);"><?php echo htmlspecialchars($payment['order_number']); ?></strong></td>
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight: 600; color: var(--admin-text-primary);"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></span>
                                    <span style="font-size: 0.75rem; color: var(--admin-text-muted);"><?php echo $payment['email']; ?></span>
                                </div>
                            </td>
                            <td><span style="font-weight: 700; color: var(--admin-text-primary);">₱<?php echo number_format($payment['amount'], 2); ?></span></td>
                            <td>
                                <span class="admin-badge admin-badge-<?php echo $payment['payment_method'] == 'gcash' ? 'info' : 'primary'; ?>">
                                    <?php echo strtoupper($payment['payment_method']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--admin-text-secondary);"><?php echo date('M d, h:i A', strtotime($payment['created_at'])); ?></td>
                            <td style="text-align: right; padding-right: 1.5rem;">
                                <form method="POST" style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                    <?php if ($payment['payment_method'] == 'gcash'): ?>
                                    <input type="text" name="reference" class="admin-search" placeholder="GCash Ref #" style="width: 140px; padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.8rem;">
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="confirmed" class="admin-btn hover-lift" style="background: var(--admin-success); color: white; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 0;">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="submit" name="action" value="failed" class="admin-btn hover-lift" style="background: rgba(239, 64, 64, 0.1); color: var(--admin-error); width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 0;" onclick="return confirm('Mark as failed?')">
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
        <div class="glass-card" style="border-radius: 24px; overflow: hidden;">
            <div class="admin-card-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--admin-border);">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--admin-text-primary); margin: 0;">
                    <i class="fas fa-history" style="color: var(--admin-text-muted); margin-right: 0.75rem;"></i>
                    Settlement History
                </h3>
            </div>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr style="background: var(--admin-gradient-surface);">
                            <th style="padding-left: 2rem;">Transaction</th>
                            <th>Payer</th>
                            <th>Settled Amount</th>
                            <th>Status</th>
                            <th>Provider Ref</th>
                            <th>Authorized By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($confirmedPayments as $payment): ?>
                        <tr>
                            <td style="padding-left: 2rem;"><?php echo htmlspecialchars($payment['order_number']); ?></td>
                            <td><span style="font-weight: 500;"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></span></td>
                            <td><strong style="color: var(--admin-text-primary);">₱<?php echo number_format($payment['amount'], 2); ?></strong></td>
                            <td>
                                <span class="admin-badge admin-badge-<?php echo $payment['status'] == 'confirmed' ? 'success' : 'error'; ?>">
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

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
