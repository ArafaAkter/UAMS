<?php
// reviewer/applications.php
// List all applications assigned to the logged-in reviewer

$page_title = 'My Applications';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('reviewer');

$conn = db_connect();
$reviewer_id = current_user_id();
$user = get_logged_in_user($conn);

$status_filter = $_GET['status'] ?? '';
$status_filter_clause = '';
$params = ['rid' => $reviewer_id];

if ($status_filter !== '' && is_numeric($status_filter)) {
    $status_filter_clause = ' AND a.current_status_id = :status_id';
    $params['status_id'] = $status_filter;
}

// Stats scoped to this reviewer
$stats = [];
$stats['total'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS a JOIN USERS u ON a.student_id = u.user_id WHERE a.reviewer_id = :rid AND u.department = :reviewer_dept', ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);
$stats['pending_review'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS a JOIN USERS u ON a.student_id = u.user_id WHERE a.reviewer_id = :rid AND u.department = :reviewer_dept AND a.current_status_id IN (2, 3, 4, 7)', ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);
$stats['under_review'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS a JOIN USERS u ON a.student_id = u.user_id WHERE a.reviewer_id = :rid AND u.department = :reviewer_dept AND a.current_status_id = 3', ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);
$stats['approved'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS a JOIN USERS u ON a.student_id = u.user_id WHERE a.reviewer_id = :rid AND u.department = :reviewer_dept AND a.current_status_id IN (5, 9)', ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);
$stats['rejected'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS a JOIN USERS u ON a.student_id = u.user_id WHERE a.reviewer_id = :rid AND u.department = :reviewer_dept AND a.current_status_id IN (6, 10)', ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);

// Fetch applications assigned to this reviewer
$applications = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at, a.current_status_id,
           s.status_code, s.status_name,
           t.type_name, t.fee_amount,
           u.full_name as student_name, u.email as student_email
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE a.reviewer_id = :rid
    AND u.department = :reviewer_dept
    {$status_filter_clause}
    ORDER BY a.submitted_at DESC
", ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);

db_close($conn);

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
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>My Applications</h1>
            <p>Applications assigned to you for review</p>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($stats['total']); ?></h3>
                <p>Total Assigned</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['pending_review']); ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['under_review']); ?></h3>
                <p>Under Review</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['approved']); ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e($stats['rejected']); ?></h3>
                <p>Rejected</p>
            </div>
        </div>

        <!-- Applications List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Assigned Applications</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="<?php echo base_url('reviewer/applications.php'); ?>" class="btn btn-small <?php echo empty($status_filter) ? 'btn-primary' : ''; ?>">All (<?php echo e($stats['total']); ?>)</a>
                    <a href="?status=2" class="btn btn-small">Submitted</a>
                    <a href="?status=3" class="btn btn-small">Under Review</a>
                    <a href="?status=4" class="btn btn-small">Reviewed</a>
                    <a href="?status=7" class="btn btn-small">Needs Info</a>
                    <a href="?status=5" class="btn btn-small">Approved</a>
                    <a href="?status=9" class="btn btn-small">Completed</a>
                    <a href="?status=6" class="btn btn-small">Rejected</a>
                </div>
            </div>

            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <p>No applications found matching your filter.</p>
                    <?php if (!empty($status_filter)): ?>
                        <a href="<?php echo base_url('reviewer/applications.php'); ?>" class="btn btn-primary">View All Applications</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Student Email</th>
                                <th>Type</th>
                                <th>Fee</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($app['STUDENT_NAME']); ?></td>
                                    <td><?php echo e($app['STUDENT_EMAIL']); ?></td>
                                    <td><?php echo e($app['TYPE_NAME']); ?></td>
                                    <td><?php echo e(number_format($app['FEE_AMOUNT'], 2)); ?> BDT</td>
                                    <td><?php echo status_badge($app['STATUS_CODE'], $app['STATUS_NAME']); ?></td>
                                    <td><?php echo e(format_date($app['SUBMITTED_AT'])); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('reviewer/application_details.php?id=' . $app['APPLICATION_ID']); ?>" class="btn btn-small btn-primary">View Details</a>
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
