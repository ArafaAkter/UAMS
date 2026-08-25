<?php
// admin/users.php
// View users (read-only)

$page_title = 'Users';
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

require_role('admin');

$conn = db_connect();

// Role filter
$role_filter = $_GET['role'] ?? '';
$role_filter_clause = '';
$params = [];
if (in_array($role_filter, ['student', 'reviewer', 'admin'])) {
    $role_filter_clause = ' WHERE role = :role';
    $params['role'] = $role_filter;
}

// Fetch users
$users = db_fetch_all($conn, "
    SELECT user_id, email, full_name, role, phone, department, is_active, created_at
    FROM USERS
    {$role_filter_clause}
    ORDER BY created_at DESC
", $params);

// Stats
$stats = [];
$stats['total'] = db_fetch_value($conn, 'SELECT COUNT(*) FROM USERS');
$stats['students'] = db_fetch_value($conn, "SELECT COUNT(*) FROM USERS WHERE role = 'student'");
$stats['reviewers'] = db_fetch_value($conn, "SELECT COUNT(*) FROM USERS WHERE role = 'reviewer'");
$stats['admins'] = db_fetch_value($conn, "SELECT COUNT(*) FROM USERS WHERE role = 'admin'");
$stats['active'] = db_fetch_value($conn, "SELECT COUNT(*) FROM USERS WHERE is_active = 'Y'");
$stats['inactive'] = db_fetch_value($conn, "SELECT COUNT(*) FROM USERS WHERE is_active = 'N'");

db_close($conn);

function role_badge($role) {
    $map = [
        'student' => 'badge-info',
        'reviewer' => 'badge-warning',
        'admin' => 'badge-danger'
    ];
    $cls = $map[$role] ?? 'badge-gray';
    return '<span class="status-badge ' . $cls . '">' . ucfirst($role) . '</span>';
}
?>
<div class="dashboard-container">
    <?php require_once '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>Users</h1>
            <p>View system users</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo e($stats['total']); ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['students']); ?></h3>
                <p>Students</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['reviewers']); ?></h3>
                <p>Reviewers</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['admins']); ?></h3>
                <p>Admins</p>
            </div>
            <div class="stat-card stat-card-highlight">
                <h3><?php echo e($stats['active']); ?></h3>
                <p>Active</p>
            </div>
            <div class="stat-card">
                <h3><?php echo e($stats['inactive']); ?></h3>
                <p>Inactive</p>
            </div>
        </div>

        <!-- Users List -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>All Users</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="<?php echo base_url('admin/users.php'); ?>" class="btn btn-small <?php echo empty($role_filter) ? 'btn-primary' : ''; ?>">All</a>
                    <a href="?role=student" class="btn btn-small">Students</a>
                    <a href="?role=reviewer" class="btn btn-small">Reviewers</a>
                    <a href="?role=admin" class="btn btn-small">Admins</a>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo e($u['FULL_NAME']); ?></td>
                                <td><?php echo e($u['EMAIL']); ?></td>
                                <td><?php echo role_badge($u['ROLE']); ?></td>
                                <td><?php echo e($u['PHONE'] ?? 'N/A'); ?></td>
                                <td><?php echo e($u['DEPARTMENT'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($u['IS_ACTIVE'] === 'Y'): ?>
                                        <span class="status-badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e(format_date($u['CREATED_AT'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
