<?php
// student/payment_status.php
// View and create payment records for the student's applications

$page_title = 'Payment Status';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';
require_once '../includes/header.php';

require_role('student');

$conn = db_connect();
$student_id = current_user_id();

$errors = [];
$success = false;

// Handle payment submission (simulated - no real payment gateway)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $application_id = trim($_POST['application_id'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');

    // Validate application_id and ownership
    if (empty($application_id) || !is_numeric($application_id)) {
        $errors['application_id'] = 'Please select a valid application.';
    } else {
        $app = db_fetch_one($conn, "
            SELECT a.application_id, a.type_id, t.fee_amount, t.type_name,
                   (SELECT COUNT(*) FROM PAYMENTS p WHERE p.application_id = a.application_id) as payment_count
            FROM APPLICATIONS a
            JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
            WHERE a.application_id = :app_id AND a.student_id = :sid AND t.requires_payment = 'Y'
        ", ['app_id' => $application_id, 'sid' => $student_id]);

        if (!$app) {
            $errors['application_id'] = 'Invalid application or payment not required for this application.';
        } elseif ($app['PAYMENT_COUNT'] > 0) {
            $errors['application_id'] = 'A payment record already exists for this application.';
        }
    }

    // Validate payment method
    $allowed_methods = ['bank_transfer', 'credit_card', 'cash', 'mobile_payment'];
    if (!in_array($payment_method, $allowed_methods)) {
        $errors['payment_method'] = 'Please select a valid payment method.';
    }

    // Validate transaction reference (optional but if provided, must not be empty)
    if ($transaction_ref !== '' && strlen($transaction_ref) > 100) {
        $errors['transaction_ref'] = 'Transaction reference is too long (max 100 characters).';
    }

    if (empty($errors)) {
        // Insert payment record
        $sql = "INSERT INTO PAYMENTS (application_id, amount, payment_method, transaction_ref, payment_date, status)
                VALUES (:app_id, :amount, :method, :txn_ref, SYSDATE, :status)";

        $stid = db_query($conn, $sql, [
            'app_id' => $application_id,
            'amount' => $app['FEE_AMOUNT'],
            'method' => $payment_method,
            'txn_ref' => $transaction_ref ?: null,
            'status' => 'pending'
        ]);

        if (db_affected_rows($conn, $stid) > 0) {
            set_flash('success', 'Payment request submitted successfully! An administrator will verify your payment soon.');
            redirect('student/payment_status.php');
        } else {
            $errors['general'] = 'Failed to create payment record. Please try again.';
        }
    }
}

db_close($conn);

// Reopen for data display
$conn = db_connect();

// Fetch payments for the student's applications
$payments = db_fetch_all($conn, "
    SELECT p.payment_id, p.amount, p.payment_method, p.transaction_ref, p.payment_date, p.status, p.updated_at, p.receipt_path,
           a.application_id, a.reference_number,
           t.type_name, t.fee_amount
    FROM PAYMENTS p
    JOIN APPLICATIONS a ON p.application_id = a.application_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.student_id = :sid
    ORDER BY p.created_at DESC
", ['sid' => $student_id]);

// Fetch applications that require payment but don't have payment records
$pending_fee_apps = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number,
           t.type_name, t.fee_amount
    FROM APPLICATIONS a
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.student_id = :sid
      AND t.requires_payment = 'Y'
      AND NOT EXISTS (
          SELECT 1 FROM PAYMENTS p WHERE p.application_id = a.application_id
      )
    ORDER BY a.created_at DESC
", ['sid' => $student_id]);

// Summary stats
$total_payments = db_fetch_value($conn, "
    SELECT COUNT(*) FROM PAYMENTS p
    JOIN APPLICATIONS a ON p.application_id = a.application_id
    WHERE a.student_id = :sid
", ['sid' => $student_id]);

$pending_payments = db_fetch_value($conn, "
    SELECT COUNT(*) FROM PAYMENTS p
    JOIN APPLICATIONS a ON p.application_id = a.application_id
    WHERE a.student_id = :sid AND p.status = 'pending'
", ['sid' => $student_id]);

$paid_payments = db_fetch_value($conn, "
    SELECT COUNT(*) FROM PAYMENTS p
    JOIN APPLICATIONS a ON p.application_id = a.application_id
    WHERE a.student_id = :sid AND p.status = 'verified'
", ['sid' => $student_id]);

$total_paid = db_fetch_value($conn, "
    SELECT NVL(SUM(p.amount), 0) FROM PAYMENTS p
    JOIN APPLICATIONS a ON p.application_id = a.application_id
    WHERE a.student_id = :sid AND p.status = 'verified'
", ['sid' => $student_id]);

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

$allowed_methods = [
    'bank_transfer' => 'Bank Transfer',
    'credit_card' => 'Credit Card',
    'cash' => 'Cash',
    'mobile_payment' => 'Mobile Payment (bKash, Nagad, etc.)'
];
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Payment Status</h1>
            <p>Manage and view payment information for your applications</p>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($total_payments); ?></h3>
                <p>Total Payments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($pending_payments); ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($paid_payments); ?></h3>
                <p>Paid</p>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e(number_format($total_paid, 2)); ?> BDT</h3>
                <p>Total Paid</p>
            </div>
        </div>

        <!-- Payment Submission Form -->
        <?php if (!empty($pending_fee_apps)): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Submit Payment</h2>
                </div>

                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo base_url('student/payment_status.php'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                    <div class="form-group">
                        <label for="application_id">Select Application *</label>
                        <select id="application_id" name="application_id" required
                                class="<?php echo isset($errors['application_id']) ? 'input-error' : ''; ?>">
                            <option value="">-- Select an application --</option>
                            <?php foreach ($pending_fee_apps as $app): ?>
                                <option value="<?php echo e($app['APPLICATION_ID']); ?>"
                                    <?php echo (($_POST['application_id'] ?? '') == $app['APPLICATION_ID']) ? 'selected' : ''; ?>>
                                    <?php echo e($app['REFERENCE_NUMBER'] . ' - ' . $app['TYPE_NAME'] . ' - Fee: ' . $app['FEE_AMOUNT'] . ' BDT'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['application_id'])): ?>
                            <span class="error-text"><?php echo e($errors['application_id']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required
                                class="<?php echo isset($errors['payment_method']) ? 'input-error' : ''; ?>">
                            <option value="">-- Select payment method --</option>
                            <?php foreach ($allowed_methods as $val => $label): ?>
                                <option value="<?php echo e($val); ?>"
                                    <?php echo (($_POST['payment_method'] ?? '') === $val) ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['payment_method'])): ?>
                            <span class="error-text"><?php echo e($errors['payment_method']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="transaction_ref">Transaction Reference (Optional)</label>
                        <input type="text" id="transaction_ref" name="transaction_ref"
                               value="<?php echo e($_POST['transaction_ref'] ?? ''); ?>"
                               placeholder="e.g., TXN-2026-001"
                               class="<?php echo isset($errors['transaction_ref']) ? 'input-error' : ''; ?>">
                        <?php if (isset($errors['transaction_ref'])): ?>
                            <span class="error-text"><?php echo e($errors['transaction_ref']); ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Payment Request</button>
                </form>
            </div>
        <?php endif; ?>

        <?php $flash = get_flash(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type'] === 'error' ? 'error' : 'success'); ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Payments List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>My Payments</h2>
                <a href="<?php echo base_url('student/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
            </div>

            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <p>You have no payment records yet.</p>
                    <p>Payments are linked to applications that require fees.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Transaction Ref</th>
                                <th>Status</th>
                                <th>Proof</th>
                                <th>Paid/Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><?php echo e($pay['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($pay['TYPE_NAME']); ?></td>
                                    <td><?php echo e(number_format($pay['AMOUNT'], 2)); ?> BDT</td>
                                    <td><?php echo e($pay['PAYMENT_METHOD']); ?></td>
                                    <td><?php echo e($pay['TRANSACTION_REF'] ?? 'N/A'); ?></td>
                                    <td><?php echo payment_status_badge($pay['STATUS']); ?></td>
                                    <td>
                                        <?php if ($pay['STATUS'] === 'verified' && !empty($pay['RECEIPT_PATH'])): ?>
                                            <a href="<?php echo base_url($pay['RECEIPT_PATH']); ?>" target="_blank" class="btn btn-small">View Proof</a>
                                        <?php elseif ($pay['STATUS'] === 'pending'): ?>
                                            <span style="color: #666;">Pending verification</span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e(format_date($pay['UPDATED_AT'])); ?></td>
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
