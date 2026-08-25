<?php
// reviewer/review_history.php
// List all reviews submitted by the logged-in reviewer

$page_title = 'Review History';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('reviewer');

$conn = db_connect();
$reviewer_id = current_user_id();
$user = get_logged_in_user($conn);

// Stats
$stats = [];
$stats['total_reviews'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM REVIEWS WHERE reviewer_id = :rid', ['rid' => $reviewer_id]);
$stats['recommended'] = db_fetch_value($conn, "SELECT COUNT(*) FROM REVIEWS WHERE reviewer_id = :rid AND recommendation = 'approve'", ['rid' => $reviewer_id]);
$stats['rejected'] = db_fetch_value($conn, "SELECT COUNT(*) FROM REVIEWS WHERE reviewer_id = :rid AND recommendation = 'reject'", ['rid' => $reviewer_id]);
$stats['info_requested'] = db_fetch_value($conn, "SELECT COUNT(*) FROM REVIEWS WHERE reviewer_id = :rid AND recommendation = 'request_info'", ['rid' => $reviewer_id]);

// Fetch all reviews by this reviewer
$reviews = db_fetch_all($conn, "
    SELECT r.review_id, r.recommendation, r.comments, r.review_date,
           a.application_id, a.reference_number,
           t.type_name,
           s.status_code, s.status_name,
           u.full_name as student_name
    FROM REVIEWS r
    JOIN APPLICATIONS a ON r.application_id = a.application_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN USERS u ON a.student_id = u.user_id
    WHERE r.reviewer_id = :rid
    ORDER BY r.review_date DESC
", ['rid' => $reviewer_id]);

foreach ($reviews as &$r) {
    $r['COMMENTS'] = clob_to_string($r['COMMENTS']);
}

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

function rec_badge($rec) {
    $map = [
        'approve' => 'badge-success',
        'reject' => 'badge-danger',
        'request_info' => 'badge-warning'
    ];
    $labels = [
        'approve' => 'Recommended',
        'reject' => 'Not Recommended',
        'request_info' => 'Need More Information'
    ];
    $cls = $map[$rec] ?? 'badge-gray';
    $label = $labels[$rec] ?? ucfirst($rec);
    return '<span class="status-badge ' . $cls . '">' . e($label) . '</span>';
}
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Review History</h1>
            <p>History of all your submitted reviews</p>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($stats['total_reviews']); ?></h3>
                <p>Total Reviews</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['recommended']); ?></h3>
                <p>Recommended</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['rejected']); ?></h3>
                <p>Not Recommended</p>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e($stats['info_requested']); ?></h3>
                <p>Info Requested</p>
            </div>
        </div>

        <!-- Reviews List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>All Reviews</h2>
                <a href="<?php echo base_url('reviewer/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
            </div>

            <?php if (empty($reviews)): ?>
                <div class="empty-state">
                    <p>You have not submitted any reviews yet.</p>
                    <a href="<?php echo base_url('reviewer/applications.php'); ?>" class="btn btn-primary">View Assigned Applications</a>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Recommendation</th>
                                <th>Current Status</th>
                                <th>Comments</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $r): ?>
                                <tr>
                                    <td><?php echo e(format_date($r['REVIEW_DATE'])); ?></td>
                                    <td><?php echo e($r['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($r['STUDENT_NAME']); ?></td>
                                    <td><?php echo e($r['TYPE_NAME']); ?></td>
                                    <td><?php echo rec_badge($r['RECOMMENDATION']); ?></td>
                                    <td><?php echo status_badge($r['STATUS_CODE'], $r['STATUS_NAME']); ?></td>
                                    <td><?php echo e($r['COMMENTS'] ?: 'No comments'); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('reviewer/application_details.php?id=' . $r['APPLICATION_ID']); ?>" class="btn btn-small btn-primary">View App</a>
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
