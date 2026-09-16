<?php
// admin/payments.php
// Admin can view and manage all payment statuses

$page_title = 'Payments';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/header.php';

require_role('admin');

$conn = db_connect();
$user = get_logged_in_user($conn);
$admin_id = current_user_id();

$errors = [];
$success = false;

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    validate_csrf();

    $payment_id = trim($_POST['payment_id'] ?? '');
    $new_status = trim($_POST['status'] ?? '');
    $admin_comments = trim($_POST['admin_comments'] ?? '');

    if (!is_numeric($payment_id)) {
        $errors['general'] = 'Invalid payment ID.';
    } elseif (!in_array($new_status, ['pending', 'verified', 'failed'])) {
        $errors['general'] = 'Invalid payment status.';
    }

    if (empty($errors)) {
        // Update payment status (simulated - no real payment gateway involved)
        $stid = db_query($conn, "
            UPDATE PAYMENTS
            SET status = :status, verified_by = :admin_id, updated_at = SYSDATE
            WHERE payment_id = :pay_id
        ", [
            'status' => $new_status,
            'admin_id' => $admin_id,
            'pay_id' => $payment_id
        ]);

        oci_commit($conn);

        if (db_affected_rows($conn, $stid) > 0) {
            set_flash('success', 'Payment status updated successfully.');
        } else {
            set_flash('error', 'No payment record found or no changes made.');
        }
        redirect('admin/payments.php');
    }
}

// Fetch all payments with student and application info
$payments = db_fetch_all($conn, "
    SELECT p.payment_id, p.amount, p.payment_method, p.transaction_ref, p.payment_date, p.status, p.created_at, p.updated_at,
           a.application_id, a.reference_number,
           t.type_name, t.fee_amount,
           u.full_name as student_name, u.email as student_email, u.department,
           v.full_name as verified_by_name
    FROM PAYMENTS p
    JOIN APPLICATIONS a ON p.application_id = a.application_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    LEFT JOIN USERS v ON p.verified_by = v.user_id
    ORDER BY p.created_at DESC
");

db_close($conn);

function payment_status_badge($status) {
    $map = [
        'pending' => 'badge-warning',
        'verified' => 'badge-success',
        'failed' => 'badge-danger'
    ];
    $labels = [
        'pending' => 'Pending',
        'verified' => 'Paid',
        'failed' => 'Failed'
    ];
    $cls = $map[$status] ?? 'badge-gray';
    $label = $labels[$status] ?? ucfirst($status);
    return '<span class="status-badge ' . $cls . '">' . e($label) . '</span>';
}

// Status counts
$total_payments = count($payments);
$pending_count = 0;
$verified_count = 0;
$failed_count = 0;
$total_amount = 0;

foreach ($payments as $p) {
    if ($p['STATUS'] === 'pending') $pending_count++;
    if ($p['STATUS'] === 'verified') $verified_count++;
    if ($p['STATUS'] === 'failed') $failed_count++;
    if ($p['STATUS'] === 'verified') $total_amount += $p['AMOUNT'];
}
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Payments</h1>
            <p>Manage all payment records and verify student payments</p>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($total_payments); ?></h3>
                <p>Total Payments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($pending_count); ?></h3>
                <p>Pending Verification</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($verified_count); ?></h3>
                <p>Paid</p>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e(number_format($total_amount, 2)); ?> BDT</h3>
                <p>Total Collected</p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type'] === 'error' ? 'error' : 'success'); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
        <?php endif; ?>

        <!-- Payment Status Update Form (for selected payment) -->
        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])): ?>
            <?php
            $edit_payment = null;
            foreach ($payments as $p) {
                if ($p['PAYMENT_ID'] == $_GET['id']) {
                    $edit_payment = $p;
                    break;
                }
            }
            if ($edit_payment):
            ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Update Payment: <?php echo e($edit_payment['REFERENCE_NUMBER']); ?></h2>
                        <a href="<?php echo base_url('admin/payments.php'); ?>" class="btn btn-small">Cancel</a>
                    </div>

                    <form method="POST" action="<?php echo base_url('admin/payments.php'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="update_payment" value="1">
                        <input type="hidden" name="payment_id" value="<?php echo e($edit_payment['PAYMENT_ID']); ?>">

                        <div class="form-group">
                            <label>Application Reference</label>
                            <p style="padding: 8px 12px; background: #f8f9fa; border-radius: 4px;"><?php echo e($edit_payment['REFERENCE_NUMBER']); ?> (<?php echo e($edit_payment['TYPE_NAME']); ?>)</p>
                        </div>

                        <div class="form-group">
                            <label>Student</label>
                            <p style="padding: 8px 12px; background: #f8f9fa; border-radius: 4px;"><?php echo e($edit_payment['STUDENT_NAME'] . ' (' . $edit_payment['STUDENT_EMAIL'] . ')'); ?></p>
                        </div>

                        <div class="form-group">
                            <label>Amount</label>
                            <p style="padding: 8px 12px; background: #f8f9fa; border-radius: 4px;"><?php echo e(number_format($edit_payment['AMOUNT'], 2)); ?> BDT</p>
                        </div>

                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <p style="padding: 8px 12px; background: #f8f9fa; border-radius: 4px;"><?php echo e($edit_payment['PAYMENT_METHOD']); ?>
                                <?php if ($edit_payment['TRANSACTION_REF']): ?>
                                    (Ref: <?php echo e($edit_payment['TRANSACTION_REF']); ?>)
                                <?php endif; ?></p>
                        </div>

                        <div class="form-group">
                            <label for="status">Payment Status *</label>
                            <select id="status" name="status" required>
                                <option value="pending" <?php echo (($edit_payment['STATUS'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pending Verification</option>
                                <option value="verified" <?php echo (($edit_payment['STATUS'] ?? '') === 'verified') ? 'selected' : ''; ?>>Paid (Verified)</option>
                                <option value="failed" <?php echo (($edit_payment['STATUS'] ?? '') === 'failed') ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="admin_comments">Admin Comments (Optional)</label>
                            <textarea id="admin_comments" name="admin_comments" rows="3"
                                      placeholder="Add verification notes..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Update Payment Status</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Payments List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>All Payments</h2>
                <a href="<?php echo base_url('admin/payments.php'); ?>" class="btn btn-small">Refresh</a>
            </div>

            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <p>No payment records found.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Transaction Ref</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Date</th>
                                <th>Proof</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><?php echo e($pay['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($pay['STUDENT_NAME']); ?> <br><small><?php echo e($pay['STUDENT_EMAIL']); ?></small></td>
                                    <td><?php echo e($pay['TYPE_NAME']); ?></td>
                                    <td><?php echo e(number_format($pay['AMOUNT'], 2)); ?> BDT</td>
                                    <td><?php echo e($pay['PAYMENT_METHOD']); ?></td>
                                    <td><?php echo e($pay['TRANSACTION_REF'] ?? 'N/A'); ?></td>
                                    <td><?php echo payment_status_badge($pay['STATUS']); ?></td>
                                    <td><?php echo e($pay['VERIFIED_BY_NAME'] ?? 'Not verified'); ?></td>
                                    <td><?php echo e(format_date($pay['CREATED_AT'])); ?></td>
                                    <td>
                                        <?php if (!empty($pay['RECEIPT_PATH'])): ?>
                                            <a href="<?php echo base_url($pay['RECEIPT_PATH']); ?>" target="_blank" class="btn btn-small">View Proof</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo base_url('admin/payments.php?action=edit&id=' . $pay['PAYMENT_ID']); ?>" class="btn btn-small btn-primary">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
