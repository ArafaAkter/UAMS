<?php
// student/track_application.php
// Track a specific application by reference number or view all for tracking

$page_title = 'Track Application';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('student');

$conn = db_connect();
$student_id = current_user_id();

// If a reference number is provided, fetch detailed tracking info
$ref = $_GET['ref'] ?? '';
$tracked_app = null;
$status_history = [];

if (!empty($ref)) {
    // Fetch the application and verify it belongs to the student
    $tracked_app = db_fetch_one($conn, "
        SELECT a.application_id, a.reference_number, a.submitted_at, a.updated_at, a.current_status_id, a.application_data,
               s.status_code, s.status_name, s.description as status_description,
               t.type_name, t.type_code, t.fee_amount,
               u.full_name as reviewer_name
        FROM APPLICATIONS a
        JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
        JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
        LEFT JOIN USERS u ON a.reviewer_id = u.user_id
        WHERE a.reference_number = :ref AND a.student_id = :sid
    ", ['ref' => $ref, 'sid' => $student_id]);

    if ($tracked_app) {
        // Fetch status history for this application (oldest first for timeline)
        $status_history = db_fetch_all($conn, "
            SELECT h.history_id, h.status_id, h.comments, h.changed_at,
                   s.status_name, s.status_code,
                   u2.full_name as changed_by_name
            FROM APPLICATION_STATUS_HISTORY h
            JOIN APPLICATION_STATUS s ON h.status_id = s.status_id
            LEFT JOIN USERS u2 ON h.changed_by = u2.user_id
            WHERE h.application_id = :app_id
            ORDER BY h.changed_at ASC
        ", ['app_id' => $tracked_app['APPLICATION_ID']]);
    }
}

// Fetch all of the student's applications for the tracking list
$all_apps = db_fetch_all($conn, "
    SELECT a.application_id, a.reference_number, a.submitted_at,
           s.status_code, s.status_name,
           t.type_name
    FROM APPLICATIONS a
    JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
    JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
    WHERE a.student_id = :sid
    ORDER BY a.created_at DESC
", ['sid' => $student_id]);

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
            <h1>Track Application</h1>
            <p>Track the status of your applications</p>
        </div>

        <?php if (!empty($tracked_app)): ?>
            <!-- Detailed Tracking View -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Tracking: <?php echo e($tracked_app['REFERENCE_NUMBER']); ?></h2>
                    <a href="<?php echo base_url('student/track_application.php'); ?>" class="btn btn-small">Back to List</a>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo e($tracked_app['TYPE_NAME']); ?></h3>
                        <p>Application Type</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo e(number_format($tracked_app['FEE_AMOUNT'], 2)); ?> BDT</h3>
                        <p>Fee Amount</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo status_badge($tracked_app['STATUS_CODE'], $tracked_app['STATUS_NAME']); ?></h3>
                        <p>Current Status</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo e($tracked_app['SUBMITTED_AT'] ? format_date($tracked_app['SUBMITTED_AT']) : 'Not submitted'); ?></h3>
                        <p>Submitted</p>
                    </div>
                </div>

                <p style="margin-bottom: 20px; color: #666;"><?php echo e($tracked_app['STATUS_DESCRIPTION'] ?? 'No description available.'); ?></p>

                <?php if (!empty($tracked_app['REVIEWER_NAME'])): ?>
                    <p><strong>Assigned Reviewer:</strong> <?php echo e($tracked_app['REVIEWER_NAME']); ?></p>
                <?php endif; ?>

                <!-- Status Timeline -->
                <h3 style="color: #1a237e; margin-bottom: 15px;">Status Timeline</h3>
                <?php if (empty($status_history)): ?>
                    <div class="timeline-empty">
                        <p>No status history available yet.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($status_history as $index => $h): ?>
                            <?php
                                $code = $h['STATUS_CODE'] ?? '';
                                $dotClass = 'dot-gray';
                                if (in_array($code, ['approved', 'completed'])) $dotClass = 'dot-success';
                                elseif (in_array($code, ['rejected', 'closed'])) $dotClass = 'dot-danger';
                                elseif (in_array($code, ['under_review', 'needs_review', 'payment_pending'])) $dotClass = 'dot-warning';
                                elseif (in_array($code, ['submitted', 'reviewed'])) $dotClass = 'dot-info';
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-dot <?php echo $dotClass; ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <span class="timeline-status">
                                            <?php echo status_badge($h['STATUS_CODE'], $h['STATUS_NAME']); ?>
                                        </span>
                                        <span class="timeline-date"><?php echo e(format_date($h['CHANGED_AT'])); ?></span>
                                    </div>
                                    <div class="timeline-remarks">
                                        <?php echo e(clob_to_string($h['COMMENTS']) ?: 'No remarks'); ?>
                                    </div>
                                    <div class="timeline-meta">
                                        Changed by: <?php echo e($h['CHANGED_BY_NAME'] ?? 'System'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="section-header" style="margin-top: 20px;">
                    <a href="<?php echo base_url('student/documents.php'); ?>" class="btn btn-small">View Documents</a>
                    <a href="<?php echo base_url('student/payment_status.php'); ?>" class="btn btn-small">View Payments</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Application List for Tracking -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Your Applications</h2>
                    <a href="<?php echo base_url('student/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
                </div>

                <?php if (empty($all_apps)): ?>
                    <div class="empty-state">
                        <p>You have not submitted any applications yet.</p>
                        <a href="<?php echo base_url('student/new_application.php'); ?>" class="btn btn-primary">Start Your First Application</a>
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_apps as $app): ?>
                                    <tr>
                                        <td><?php echo e($app['REFERENCE_NUMBER']); ?></td>
                                        <td><?php echo e($app['TYPE_NAME']); ?></td>
                                        <td><?php echo status_badge($app['STATUS_CODE'], $app['STATUS_NAME']); ?></td>
                                        <td><?php echo e(format_date($app['SUBMITTED_AT'])); ?></td>
                                        <td>
                                            <a href="?ref=<?php echo e($app['REFERENCE_NUMBER']); ?>" class="btn btn-small btn-primary">Track</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
