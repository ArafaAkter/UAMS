<?php
// student/profile.php
// View student profile information

$page_title = 'Profile';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('student');

$conn = db_connect();
$student_id = current_user_id();

// Fetch full profile from database
$profile = db_fetch_one($conn, "
    SELECT user_id, email, full_name, phone, department, role, is_active, created_at, updated_at
    FROM USERS
    WHERE user_id = :user_id
", ['user_id' => $student_id]);

db_close($conn);
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>My Profile</h1>
            <p>View and manage your personal information</p>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h2>Profile Information</h2>
                <a href="<?php echo base_url('student/dashboard.php'); ?>" class="btn btn-small">Back to Dashboard</a>
            </div>

            <?php if (!$profile): ?>
                <div class="empty-state">
                    <p>Unable to load profile information.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Full Name</td>
                                <td><?php echo e($profile['FULL_NAME']); ?></td>
                            </tr>
                            <tr>
                                <td>Email Address</td>
                                <td><?php echo e($profile['EMAIL']); ?></td>
                            </tr>
                            <tr>
                                <td>Phone Number</td>
                                <td><?php echo e($profile['PHONE'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>Department</td>
                                <td><?php echo e($profile['DEPARTMENT'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>Role</td>
                                <td><?php echo e(ucfirst($profile['ROLE'])); ?></td>
                            </tr>
                            <tr>
                                <td>Account Status</td>
                                <td>
                                    <?php if ($profile['IS_ACTIVE'] === 'Y'): ?>
                                        <span class="status-badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Member Since</td>
                                <td><?php echo e(format_date($profile['CREATED_AT'])); ?></td>
                            </tr>
                            <tr>
                                <td>Last Updated</td>
                                <td><?php echo e(format_date($profile['UPDATED_AT'])); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; border: 1px solid #e9ecef;">
                    <p><strong>Note:</strong></p>
                    <p>Profile editing functionality is not yet available in this phase.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
