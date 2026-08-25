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

// Recent applications (last 5)
$recent_apps = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at,
           s.status_code, s.status_name,
           t.type_name
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.student_id = :sid
    ORDER BY a.created_at DESC
", ['sid' => $student_id]);

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

        <!-- Recent Applications -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Recent Applications</h2>
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
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_apps as $app): ?>
                                <tr>
                                    <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                    <td><?php echo e($app['TYPE_NAME']); ?></td>
                                    <td><?php echo status_badge($app['STATUS_CODE'], $app['STATUS_NAME']); ?></td>
                                    <td><?php echo e(format_date($app['SUBMITTED_AT'])); ?></td>
                                    <td><?php echo e(format_date($app['UPDATED_AT'])); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('student/track_application.php?ref=' . $app['REFERENCE_NUMBER']); ?>" class="btn btn-small btn-primary">Track</a>
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