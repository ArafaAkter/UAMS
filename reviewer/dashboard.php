<?php
// reviewer/dashboard.php
// Reviewer dashboard with real data

$page_title = 'Reviewer Dashboard';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('reviewer');

$conn = db_connect();
$user = get_logged_in_user($conn);
$reviewer_id = current_user_id();

// Stats
$stats = [];
$stats['assigned'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE reviewer_id = :rid', ['rid' => $reviewer_id]);
$stats['pending_review'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE reviewer_id = :rid AND current_status_id IN (2, 3, 7)', ['rid' => $reviewer_id]);
$stats['under_review'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE reviewer_id = :rid AND current_status_id = 3', ['rid' => $reviewer_id]);
$stats['completed_reviews'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM REVIEWS WHERE reviewer_id = :rid', ['rid' => $reviewer_id]);

// Assigned applications for review
$assigned_apps = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at,
           s.status_code, s.status_name,
           t.type_name,
           u.full_name as student_name
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE a.reviewer_id = :rid
    AND u.department = :reviewer_dept
    AND a.current_status_id IN (2, 3, 4, 7)
    ORDER BY 
        CASE a.current_status_id 
            WHEN 3 THEN 1 
            WHEN 7 THEN 2 
            WHEN 2 THEN 3 
            WHEN 4 THEN 4 
            ELSE 5 
        END,
        a.submitted_at ASC
", ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);

// Recent reviews
$recent_reviews = db_fetch_all($conn, "
    SELECT r.review_id, r.recommendation, r.review_date, r.comments,
           a.reference_number,
           t.type_name
    FROM REVIEWS r
    JOIN APPLICATIONS a ON r.application_id = a.application_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE r.reviewer_id = :rid
    AND u.department = :reviewer_dept
    ORDER BY r.review_date DESC
", ['rid' => $reviewer_id, 'reviewer_dept' => $user['DEPARTMENT']]);

// Limit to 5 rows in PHP instead of FETCH FIRST
$recent_reviews = array_slice($recent_reviews, 0, 5);

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

// Recommendation badge helper
function rec_badge($rec) {
    $map = [
        'approve' => 'badge-success',
        'reject' => 'badge-danger',
        'request_info' => 'badge-warning'
    ];
    $labels = [
        'approve' => 'Approved',
        'reject' => 'Rejected',
        'request_info' => 'Request Info'
    ];
    $cls = $map[$rec] ?? 'badge-gray';
    $label = $labels[$rec] ?? ucfirst($rec);
    return '<span class="status-badge ' . $cls . '">' . $label . '</span>';
}
?>

<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo e($user['FULL_NAME']); ?>!</h1>
            <p>Reviewer Dashboard</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($stats['assigned'] ?? 0); ?></h3>
                <p>Assigned Applications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['pending_review'] ?? 0); ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['under_review'] ?? 0); ?></h3>
                <p>Under Review</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['completed_reviews'] ?? 0); ?></h3>
                <p>Completed Reviews</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="action-grid">
            <a href="<?php echo base_url('reviewer/applications.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>My Applications</h4>
                    <p>Review assigned applications</p>
                </div>
            </a>
            <a href="<?php echo base_url('reviewer/profile.php'); ?>" class="action-card">
                <div class="action-text">
                    <h4>Profile</h4>
                    <p>Update your information</p>
                </div>
            </a>
        </div>

        <!-- Assigned Applications -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Assigned Applications</h2>
                <a href="<?php echo base_url('reviewer/applications.php'); ?>" class="btn btn-small">View All</a>
            </div>

            <?php if (empty($assigned_apps)): ?>
                <div class="empty-state">
                    <p>No applications are currently assigned to you.</p>
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_apps as $app): ?>
                                <tr>
                                    <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($app['STUDENT_NAME']); ?></td>
                                    <td><?php echo e($app['TYPE_NAME']); ?></td>
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