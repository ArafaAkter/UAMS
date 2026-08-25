<?php
// profile.php
// Shared profile management for all authenticated users (student, reviewer, admin)

$page_title = 'My Profile';
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';
require_once 'includes/header.php';

if (!is_logged_in()) {
    set_flash('error', 'Please login to access this page.');
    redirect('login.php');
}

$conn = db_connect();
$user_id = current_user_id();
$user = get_logged_in_user($conn);

$errors = [];
$success = false;
$password_success = false;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    validate_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($full_name) || strlen($full_name) < 2) {
        $errors['full_name'] = 'Full name is required (min 2 characters).';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required.';
    } else {
        // Check for duplicate email (excluding current user)
        $existing = db_fetch_one($conn, "SELECT user_id FROM USERS WHERE email = :email AND user_id != :b_user_id", ['email' => $email, 'b_user_id' => $user_id]);
        if ($existing) {
            $errors['email'] = 'This email is already in use by another account.';
        }
    }

    if (empty($errors)) {
        db_query($conn, "
            UPDATE USERS
            SET full_name = :b_full_name, email = :b_email, phone = :b_phone, updated_at = SYSDATE
            WHERE user_id = :b_user_id
        ", [
            'b_full_name' => $full_name,
            'b_email' => $email,
            'b_phone' => $phone ?: null,
            'b_user_id' => $user_id
        ]);

        $success = true;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    validate_csrf();

    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($current_password)) {
        $errors['current_password'] = 'Current password is required.';
    } else {
        // Verify current password
        $user_check = db_fetch_one($conn, "SELECT password_hash FROM USERS WHERE user_id = :b_user_id", ['b_user_id' => $user_id]);
        if (!$user_check || !password_verify($current_password, $user_check['PASSWORD_HASH'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
    }

    if (empty($new_password) || strlen($new_password) < 6) {
        $errors['new_password'] = 'New password must be at least 6 characters.';
    }

    if ($new_password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        db_query($conn, "
            UPDATE USERS
            SET password_hash = :b_password_hash, updated_at = SYSDATE
            WHERE user_id = :b_user_id
        ", ['b_password_hash' => $password_hash, 'b_user_id' => $user_id]);

        $password_success = true;
    }
}

db_close($conn);
?>
<div class="dashboard-container">
    <?php require_once 'includes/sidebar.php'; ?>

    <div class="dashboard-content">
        <div class="dashboard-header">
            <h1>My Profile</h1>
            <p>View and manage your personal information</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">Profile updated successfully.</div>
        <?php endif; ?>
        <?php if ($password_success): ?>
            <div class="alert alert-success">Password changed successfully.</div>
        <?php endif; ?>
        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Profile Information</h2>
            </div>

            <form method="POST" action="<?php echo base_url('profile.php'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="update_profile" value="1">

                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required
                           value="<?php echo e($user['FULL_NAME']); ?>"
                           class="<?php echo isset($errors['full_name']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['full_name'])): ?>
                        <span class="error-text"><?php echo e($errors['full_name']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo e($user['EMAIL']); ?>"
                           class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-text"><?php echo e($errors['email']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone"
                           value="<?php echo e($user['PHONE'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <p style="padding: 8px 12px; background: #f8f9fa; border-radius: 4px;"><?php echo e(ucfirst($user['ROLE'])); ?></p>
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <p style="padding: 8px 12px; background: #f8f9fa; border-radius: 4px;"><?php echo e($user['DEPARTMENT'] ?? 'N/A'); ?></p>
                </div>

                <div class="form-group">
                    <label>Account Status</label>
                    <p style="padding: 8px 12px; background: #f8f9fa; border-radius: 4px;">
                        <?php if ($user['IS_ACTIVE'] === 'Y'): ?>
                            <span class="status-badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="status-badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </p>
                </div>

                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2>Change Password</h2>
            </div>

            <form method="POST" action="<?php echo base_url('profile.php'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="change_password" value="1">

                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" required
                           class="<?php echo isset($errors['current_password']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['current_password'])): ?>
                        <span class="error-text"><?php echo e($errors['current_password']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="<?php echo isset($errors['new_password']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['new_password'])): ?>
                        <span class="error-text"><?php echo e($errors['new_password']); ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="<?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="error-text"><?php echo e($errors['confirm_password']); ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
