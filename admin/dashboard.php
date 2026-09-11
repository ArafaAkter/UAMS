<?php
// admin/dashboard.php
// Admin dashboard with real data

$page_title = 'Admin Dashboard';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('admin');

$conn = db_connect();
$user = get_logged_in_user($conn);

// Stats
$stats = [];
$stats['total_users'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM USERS');
$stats['total_students'] = db_fetch_value($conn, "SELECT COUNT(*) FROM USERS WHERE role = 'student'");
$stats['total_reviewers'] = db_fetch_value($conn, "SELECT COUNT(*) FROM USERS WHERE role = 'reviewer'");
$stats['total_apps'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS');
$stats['pending_review'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE current_status_id IN (2, 3, 7)');
$stats['approved'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE current_status_id IN (5, 8, 9)');
$stats['rejected'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE current_status_id IN (6, 10)');
$stats['pending_payments'] = db_fetch_value($conn, "SELECT COUNT(*) FROM PAYMENTS WHERE status = 'pending'");
$stats['verified_payments'] = db_fetch_value($conn, "SELECT COUNT(*) FROM PAYMENTS WHERE status = 'verified'");
 $stats['total_revenue'] = db_fetch_value($conn, "SELECT NVL(SUM(amount), 0) FROM PAYMENTS WHERE status = 'verified'");
$stats['pending_verification_docs'] = db_fetch_value($conn, "SELECT COUNT(*) FROM DOCUMENTS WHERE verification_status = 'pending'");
$stats['needs_correction_docs'] = db_fetch_value($conn, "SELECT COUNT(*) FROM DOCUMENTS WHERE verification_status = 'needs_correction'");

// Recent applications
$recent_apps = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at,
           s.status_code, s.status_name,
           t.type_name,
           u.full_name as student_name
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    ORDER BY a.created_at DESC
");

// Limit to 10 rows in PHP instead of FETCH FIRST
$recent_apps = array_slice($recent_apps, 0, 10);

// Recent users
$recent_users = db_fetch_all($conn, "
    SELECT user_id, email, full_name, role, is_active, created_at
    FROM USERS
    ORDER BY created_at DESC
");

// Limit to 5 rows in PHP instead of FETCH FIRST
$recent_users = array_slice($recent_users, 0, 5);

// Recent payments pending verification
$recent_payments = db_fetch_all($conn, "
    SELECT p.payment_id, p.amount, p.payment_method, p.transaction_ref, p.status, p.created_at,
           a.application_id, a.reference_number,
           t.type_name,
           u.full_name as student_name, u.email as student_email
    FROM PAYMENTS p
    JOIN APPLICATIONS a ON p.application_id = a.application_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");

// Documents pending verification
$pending_docs = db_fetch_all($conn, "
    SELECT d.document_id, d.original_filename, d.file_path, d.verification_status, d.uploaded_at,
           a.application_id, a.reference_number,
           t.type_name,
           u.full_name as student_name
    FROM DOCUMENTS d
    JOIN APPLICATIONS a ON d.application_id = a.application_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE d.verification_status IN ('pending', 'needs_correction')
    ORDER BY d.uploaded_at DESC
");

db_close($conn);

// Status badge helper
function status_badge($code, $name) {
    $color_map = [
        'draft' => 'gray',
        'submitted' => 'info',
        'under_review' => 'warning',
        'reviewed' => 'info',
        'approved' => 'success',
        'rejected' => 'danger',
        'needs_review' => 'warning',
        'payment_pending' => 'warning',
        'completed' => 'success',
        'closed' => 'gray'
    ];
    $color = $color_map[$code] ?? 'gray';
    $badge_class = 'badge-' . $color;
    return '<span class="status-badge ' . $badge_class . '">' . e($name) . '</span>';
}

// Role badge helper
function role_badge($role) {
    $map = [
        'student' => 'badge-info',
        'reviewer' => 'badge-warning',
        'admin' => 'badge-danger'
    ];
    $cls = $map[$role] ?? 'badge-gray';
    return '<span class="status-badge ' . $cls . '">' . ucfirst($role) . '</span>';
}
?>

<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>
    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo e($user['FULL_NAME']); ?>!</h1>
            <p>Administrator Dashboard</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($stats['total_users'] ?? 0); ?></h3>
                <p>Total Users</p>
                <small><?php echo e($stats['total_students'] ?? 0); ?> students, <?php echo e($stats['total_reviewers'] ?? 0); ?> reviewers</small>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['total_apps'] ?? 0); ?></h3>
                <p>Total Applications</p>
                <small><?php echo e($stats['pending_review'] ?? 0); ?> pending review</small>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['approved'] ?? 0); ?></h3>
                <p>Approved</p>
                <small><?php echo e($stats['rejected'] ?? 0); ?> rejected</small>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e(number_format($stats['total_revenue'] ?? 0, 2)); ?></h3>
                <p>Total Revenue</p>
                <small><?php echo e($stats['pending_payments'] ?? 0); ?> pending verification</small>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['pending_verification_docs'] ?? 0); ?></h3>
                <p>Docs Pending Verification</p>
                <small><?php echo e($stats['needs_correction_docs'] ?? 0); ?> need correction</small>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="action-grid">
            <a href="<?php echo base_url('admin/users.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Manage Users</h4>
                    <p>Add, edit, or deactivate users</p>
                </div>
            </a>
            <a href="<?php echo base_url('admin/all_applications.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>All Applications</h4>
                    <p>Review and manage applications</p>
                </div>
            </a>
            <a href="<?php echo base_url('admin/payments.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Payments</h4>
                    <p>Verify payment receipts</p>
                </div>
            </a>
            <a href="<?php echo base_url('admin/settings.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Settings</h4>
                    <p>Configure application types</p>
                </div>
            </a>
        </div>

        <!-- Recent Applications -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Applications</h2>
                <a href="<?php echo base_url('admin/all_applications.php'); ?>" class="btn btn-small">View All</a>
            </div>

            <?php if (empty($recent_apps)): ?>
                <div class="empty-state">
                    <p>No applications have been submitted yet.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_apps as $app): ?>
                                <tr>
                                    <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($app['STUDENT_NAME']); ?></td>
                                    <td><?php echo e($app['TYPE_NAME']); ?></td>
                                    <td><?php echo status_badge($app['STATUS_CODE'], $app['STATUS_NAME']); ?></td>
                                    <td><?php echo e(format_date($app['SUBMITTED_AT'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Payments Requiring Action -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Payments Pending Verification (<?php echo e(count($recent_payments)); ?>)</h2>
                <a href="<?php echo base_url('admin/payments.php'); ?>" class="btn btn-small">View All Payments</a>
            </div>

            <?php if (empty($recent_payments)): ?>
                <div class="empty-state">
                    <p>No payments are currently pending verification.</p>
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
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $pay): ?>
                                <tr>
                                    <td><?php echo e($pay['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($pay['STUDENT_NAME']); ?> <br><small><?php echo e($pay['STUDENT_EMAIL']); ?></small></td>
                                    <td><?php echo e($pay['TYPE_NAME']); ?></td>
                                    <td><?php echo e(number_format($pay['AMOUNT'], 2)); ?> BDT</td>
                                    <td><?php echo e($pay['PAYMENT_METHOD']); ?></td>
                                    <td><?php echo e(format_date($pay['CREATED_AT'])); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('admin/payments.php?action=edit&id=' . $pay['PAYMENT_ID']); ?>" class="btn btn-small btn-primary">Verify</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Documents Pending Verification -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Documents Pending Verification (<?php echo e(count($pending_docs)); ?>)</h2>
                <a href="<?php echo base_url('admin/all_applications.php'); ?>" class="btn btn-small">View All Applications</a>
            </div>

            <?php if (empty($pending_docs)): ?>
                <div class="empty-state">
                    <p>No documents are currently awaiting verification.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Student</th>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_docs as $doc): ?>
                                <tr>
                                    <td><?php echo e($doc['ORIGINAL_FILENAME']); ?></td>
                                    <td><?php echo e($doc['STUDENT_NAME']); ?></td>
                                    <td><?php echo e($doc['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($doc['TYPE_NAME']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $doc['VERIFICATION_STATUS'] === 'needs_correction' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $doc['VERIFICATION_STATUS']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo e(format_date($doc['UPLOADED_AT'])); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('admin/application_details.php?id=' . $doc['APPLICATION_ID']); ?>" class="btn btn-small btn-primary">Verify</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Users -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Users</h2>
                <a href="<?php echo base_url('admin/users.php'); ?>" class="btn btn-small">View All</a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                            <tr>
                                <td><?php echo e($u['FULL_NAME']); ?></td>
                                <td><?php echo e($u['EMAIL']); ?></td>
                                <td><?php echo role_badge($u['ROLE']); ?></td>
                                <td>
                                    <?php if ($u['IS_ACTIVE'] === 'Y'): ?>
                                        <span class="status-badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e(format_date($u['CREATED_AT'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>