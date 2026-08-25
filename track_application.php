<?php
// track_application.php
// Public application tracking page (shows only non-sensitive information)

$page_title = 'Track Application';
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';
require_once 'includes/header.php';

$error = '';
$tracked_app = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $reference_number = trim($_POST['reference_number'] ?? '');

    if (empty($reference_number)) {
        $error = 'Please enter a reference number.';
    } else {
        $conn = db_connect();

        $tracked_app = db_fetch_one($conn, "
            SELECT a.reference_number, a.submitted_at, a.updated_at,
                   s.status_code, s.status_name, s.description as status_description,
                   t.type_name
            FROM APPLICATIONS a
            JOIN APPLICATION_STATUS s ON a.current_status_id = s.status_id
            JOIN APPLICATION_TYPES t ON a.type_id = t.type_id
            WHERE a.reference_number = :ref
        ", ['ref' => $reference_number]);

        db_close($conn);

        if (!$tracked_app) {
            $error = 'No application found with that reference number.';
        }
    }
}
?>
<div class="auth-container">
    <div class="auth-card">
        <h1>Track Application</h1>
        <p class="auth-subtitle">Enter your reference number to check application status.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($tracked_app): ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <tbody>
                        <tr>
                            <th>Reference Number</th>
                            <td><?php echo e($tracked_app['REFERENCE_NUMBER']); ?></td>
                        </tr>
                        <tr>
                            <th>Application Type</th>
                            <td><?php echo e($tracked_app['TYPE_NAME']); ?></td>
                        </tr>
                        <tr>
                            <th>Current Status</th>
                            <td><span class="status-badge <?php echo status_badge_class($tracked_app['STATUS_CODE']); ?>"><?php echo e($tracked_app['STATUS_NAME']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Status Description</th>
                            <td><?php echo e($tracked_app['STATUS_DESCRIPTION'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Submitted</th>
                            <td><?php echo e(format_date($tracked_app['SUBMITTED_AT'])); ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated</th>
                            <td><?php echo e(format_date($tracked_app['UPDATED_AT'])); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (is_logged_in() && current_user_role() === 'student'): ?>
                <p style="margin-top: 20px; text-align: center;">
                    <a href="<?php echo base_url('student/track_application.php?ref=' . $tracked_app['REFERENCE_NUMBER']); ?>" class="btn btn-primary">View Full Tracking Details</a>
                </p>
            <?php endif; ?>

            <p style="margin-top: 20px; text-align: center;">
                <a href="<?php echo base_url('track_application.php'); ?>" class="btn btn-small">Track Another Application</a>
            </p>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <div class="form-group">
                    <label for="reference_number">Reference Number *</label>
                    <input type="text" id="reference_number" name="reference_number" required
                           value="<?php echo e($_POST['reference_number'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Track Application</button>
            </form>
        <?php endif; ?>

        <?php if (is_logged_in()): ?>
            <p class="auth-footer">
                <a href="<?php echo base_url('student/dashboard.php'); ?>">Back to My Dashboard</a>
            </p>
        <?php else: ?>
            <p class="auth-footer">
                <a href="<?php echo base_url('login.php'); ?>">Login to view full application details</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
