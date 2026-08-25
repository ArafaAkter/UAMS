<?php
// admin/all_applications.php
// View all applications across all students

$page_title = 'All Applications';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('admin');

$conn = db_connect();

// Stats
$stats = [];
$stats['total'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS');
$stats['submitted'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE current_status_id IN (2, 3, 7)');
$stats['reviewed'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE current_status_id = 4');
$stats['approved'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE current_status_id IN (5, 8, 9)');
$stats['rejected'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM APPLICATIONS WHERE current_status_id IN (6, 10)');

// Fetch all applications
$applications = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at, a.current_status_id,
           s.status_code, s.status_name,
           t.type_name, t.fee_amount,
           u.full_name as student_name, u.email as student_email,
           r.full_name as reviewer_name
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    JOIN USERS u ON a.student_id = u.user_id
    LEFT JOIN USERS r ON a.reviewer_id = r.user_id
    ORDER BY a.created_at DESC
");

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
            <h1>All Applications</h1>
            <p>Manage and review all submitted applications</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($stats['total']); ?></h3>
                <p>Total Applications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['submitted']); ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['reviewed']); ?></h3>
                <p>Reviewed</p>
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
                <h2>Applications (<?php echo e(count($applications)); ?>)</h2>
            </div>

            <?php if (empty($applications)): ?>
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
                                <th>Reviewer</th>
                                <th>Status</th>
                                <th>Fee</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                    <td>
                                        <?php echo e($app['STUDENT_NAME']); ?><br>
                                        <small><?php echo e($app['STUDENT_EMAIL']); ?></small>
                                    </td>
                                    <td><?php echo e($app['TYPE_NAME']); ?></td>
                                    <td><?php echo e($app['REVIEWER_NAME'] ?? 'Not assigned'); ?></td>
                                    <td><?php echo status_badge($app['STATUS_CODE'], $app['STATUS_NAME']); ?></td>
                                    <td><?php echo e(number_format($app['FEE_AMOUNT'], 2)); ?> BDT</td>
                                    <td><?php echo e(format_date($app['SUBMITTED_AT'])); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('admin/application_details.php?id=' . $app['APPLICATION_ID']); ?>" class="btn btn-small btn-primary">View Details</a>
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
