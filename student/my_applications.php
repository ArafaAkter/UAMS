<?php
// student/my_applications.php
// List all applications belonging to the logged-in student with action buttons

$page_title = 'My Applications';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('student');

$conn = db_connect();
$student_id = current_user_id();

// Filter by status if requested
$status_filter = $_GET['status'] ?? '';
$status_filter_clause = '';
$params = ['sid' => $student_id];

if ($status_filter !== '' && is_numeric($status_filter)) {
    $status_filter_clause = ' AND a.current_status_id = :status_id';
    $params['status_id'] = $status_filter;
}

// Fetch all applications for this student
$applications = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at, a.current_status_id,
           s.status_code, s.status_name,
           t.type_name, t.fee_amount
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.student_id = :sid
    {$status_filter_clause}
    ORDER BY a.created_at DESC
", $params);

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
            <p>All your submitted and draft applications</p>
        </div>

        <!-- Applications List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>All Applications</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="<?php echo base_url('student/my_applications.php'); ?>" class="btn btn-small <?php echo empty($status_filter) ? 'btn-primary' : ''; ?>">All</a>
                    <!-- <a href="?status=2" class="btn btn-small">Submitted</a>
                    <a href="?status=3" class="btn btn-small">Under Review</a>
                    <a href="?status=4" class="btn btn-small">Reviewed</a>
                    <a href="?status=5" class="btn btn-small">Approved</a>
                    <a href="?status=6" class="btn btn-small">Rejected</a>
                    <a href="?status=9" class="btn btn-small">Completed</a> -->
                </div>
            </div>

            <?php if (empty($applications)): ?>
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
                                <th>Status</th>
                                <th>Fee</th>
                                <th>Submitted</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($app['TYPE_NAME']); ?></td>
                                    <td><?php echo status_badge($app['STATUS_CODE'], $app['STATUS_NAME']); ?></td>
                                    <td><?php echo e(number_format($app['FEE_AMOUNT'], 2)); ?> BDT</td>
                                    <td><?php echo e(format_date($app['SUBMITTED_AT'])); ?></td>
                                    <td><?php echo e(format_date($app['UPDATED_AT'])); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('student/application_details.php?id=' . $app['APPLICATION_ID']); ?>" class="btn btn-small btn-primary">Details</a>
                                        <a href="<?php echo base_url('student/track_application.php?ref=' . $app['REFERENCE_NUMBER']); ?>" class="btn btn-small">Track</a>
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
