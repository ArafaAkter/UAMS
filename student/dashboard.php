<?php
// student/dashboard.php
// Student dashboard with real data

$page_title = 'Student Dashboard';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('student');

 $conn = db_connect();
$user = get_logged_in_user($conn);
$student_id = current_user_id();

// Fetch student's applications with payment, document, review, and final status info
// Uses LEFT JOIN with windowed subqueries (Oracle 11g compatible, no correlated inline views)
$recent_apps = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at, a.current_status_id,
           s.status_code, s.status_name, s.is_final,
           t.type_name, t.fee_amount, t.requires_payment,
           NVL(p.payment_status, 'none') as payment_status,
           NVL(d.doc_count, 0) as doc_count, NVL(d.pending_docs, 0) as pending_docs, NVL(d.approved_docs, 0) as approved_docs,
           r.review_recommendation
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    LEFT JOIN (
        SELECT application_id, status as payment_status
        FROM (
            SELECT application_id, status,
                   ROW_NUMBER() OVER (PARTITION BY application_id ORDER BY created_at DESC) as rn
            FROM PAYMENTS
        )
        WHERE rn = 1
    ) p ON p.application_id = a.application_id
    LEFT JOIN (
        SELECT application_id,
               COUNT(*) as doc_count,
               SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending_docs,
               SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved_docs
        FROM DOCUMENTS
        GROUP BY application_id
    ) d ON d.application_id = a.application_id
    LEFT JOIN (
        SELECT application_id, recommendation as review_recommendation
        FROM (
            SELECT application_id, recommendation,
                   ROW_NUMBER() OVER (PARTITION BY application_id ORDER BY review_date DESC) as rn
            FROM REVIEWS
        )
        WHERE rn = 1
    ) r ON r.application_id = a.application_id
    WHERE a.student_id = :sid
    ORDER BY a.created_at DESC
", ['sid' => $student_id]);

// Summary stats for payments, documents, reviews
$stats = [];
$stats['total_apps'] = count($recent_apps);
$stats['pending_payments'] = 0;
$stats['verified_payments'] = 0;
$stats['pending_docs'] = 0;
$stats['approved_docs'] = 0;
$stats['pending_review'] = 0;
$stats['reviewed'] = 0;

foreach ($recent_apps as $app) {
    if ($app['REQUIRES_PAYMENT'] === 'Y') {
        if ($app['PAYMENT_STATUS'] === 'pending') $stats['pending_payments']++;
        if ($app['PAYMENT_STATUS'] === 'verified') $stats['verified_payments']++;
    }
    $stats['pending_docs'] += $app['PENDING_DOCS'] ?? 0;
    $stats['approved_docs'] += $app['APPROVED_DOCS'] ?? 0;
    if ($app['REVIEW_RECOMMENDATION']) $stats['reviewed']++;
}

db_close($conn);

// Badge helpers
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

function payment_status_badge($status) {
    $map = ['pending' => 'badge-warning', 'verified' => 'badge-success', 'failed' => 'badge-danger'];
    $labels = ['pending' => 'Pending', 'verified' => 'Paid', 'failed' => 'Failed'];
    $cls = $map[$status ?? ''] ?? 'badge-gray';
    $label = $labels[$status ?? ''] ?? 'N/A';
    return '<span class="status-badge ' . $cls . '">' . e($label) . '</span>';
}

function doc_status_summary($count, $pending, $approved) {
    $count = $count ?? 0;
    $pending = $pending ?? 0;
    $approved = $approved ?? 0;
    if ($count == 0) return '<span class="status-badge badge-gray">No docs</span>';
    if ($pending > 0) return '<span class="status-badge badge-warning">' . e($count) . ' docs (' . e($pending) . ' pending)</span>';
    return '<span class="status-badge badge-success">' . e($approved) . ' approved</span>';
}

function rec_badge($rec) {
    $map = ['approve' => 'badge-success', 'reject' => 'badge-danger', 'request_info' => 'badge-warning'];
    $labels = ['approve' => 'Recommended', 'reject' => 'Not Recommended', 'request_info' => 'Need Info'];
    $cls = $map[$rec ?? ''] ?? 'badge-gray';
    $label = $labels[$rec ?? ''] ?? 'Not reviewed';
    return '<span class="status-badge ' . $cls . '">' . e($label) . '</span>';
}
?>

<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo e($user['FULL_NAME']); ?>!</h1>
            <p>Student Dashboard</p>
        </div>

        <!-- Quick Actions -->
        <div class="action-grid">
            <a href="<?php echo base_url('student/apply.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Apply New Application</h4>
                    <p>Submit a new application</p>
                </div>
            </a>
            <a href="<?php echo base_url('student/my_applications.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>My Applications</h4>
                    <p>View all applications</p>
                </div>
            </a>
            <a href="<?php echo base_url('student/track_application.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Track Application</h4>
                    <p>Check application status</p>
                </div>
            </a>
            <a href="<?php echo base_url('student/documents.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Documents</h4>
                    <p>View uploaded documents</p>
                </div>
            </a>
            <a href="<?php echo base_url('student/payment_status.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Payment Status</h4>
                    <p>Check payment information</p>
                </div>
            </a>
            <a href="<?php echo base_url('student/profile.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Profile</h4>
                    <p>Update your information</p>
                </div>
            </a>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($stats['total_apps']); ?></h3>
                <p>Total Applications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['verified_payments']); ?></h3>
                <p>Payments Verified</p>
                <small><?php echo e($stats['pending_payments']); ?> pending</small>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['approved_docs']); ?></h3>
                <p>Documents Approved</p>
                <small><?php echo e($stats['pending_docs']); ?> pending</small>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e($stats['reviewed']); ?></h3>
                <p>Applications Reviewed</p>
                <small>Review phase complete</small>
            </div>
        </div>

        <!-- Applications Overview -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Applications Overview</h2>
                <a href="<?php echo base_url('student/my_applications.php'); ?>" class="btn btn-small">View All</a>
            </div>

            <?php if (empty($recent_apps)): ?>
                <div class="empty-state">
                    <p>You have not submitted any applications yet.</p>
                    <a href="<?php echo base_url('student/apply.php'); ?>" class="btn btn-primary">Start Your First Application</a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Application Status</th>
                                <th>Payment Status</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_apps as $app): ?>
                                <tr>
                                    <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($app['TYPE_NAME']); ?></td>
                                    <td><?php echo status_badge($app['STATUS_CODE'], $app['STATUS_NAME']); ?></td>
                                    <td>
                                        <?php if ($app['REQUIRES_PAYMENT'] === 'Y'): ?>
                                            <?php echo payment_status_badge($app['PAYMENT_STATUS']); ?>
                                        <?php else: ?>
                                            <span class="status-badge badge-gray">No fee</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e(format_date($app['SUBMITTED_AT'])); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('student/application_details.php?id=' . $app['APPLICATION_ID']); ?>" class="btn btn-small btn-primary">Details</a>
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