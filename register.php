<?php
// register.php
// User self-registration for Students and Reviewers

$page_title = 'Registration';
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';
require_once 'includes/header.php';

$errors = [];
$success = false;

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    if (current_user_role() === 'student') {
        redirect('student/dashboard.php');
    } elseif (current_user_role() === 'reviewer') {
        redirect('reviewer/dashboard.php');
    } elseif (current_user_role() === 'admin') {
        redirect('admin/dashboard.php');
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $conn = db_connect();

    // Get and trim input
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $role = trim($_POST['role'] ?? 'student');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate role
    $allowed_roles = ['student', 'reviewer'];
    if (!in_array($role, $allowed_roles)) {
        $role = 'student';
    }

    // Validation
    if (empty($full_name)) {
        $errors['full_name'] = 'Full name is required.';
    }

    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        // Check if email already exists
        $existing = db_fetch_one($conn, 
            'SELECT user_id FROM USERS WHERE email = :email',
            ['email' => $email]
        );
        if ($existing) {
            $errors['email'] = 'This email is already registered.';
        }
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($department)) {
        $errors['department'] = 'Department is required.';
    }

    // If no errors, insert user
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stid = db_query($conn,
            'INSERT INTO USERS (email, password_hash, full_name, role, phone, department, is_active)
             VALUES (:email, :password_hash, :full_name, :role, :phone, :department, :is_active)',
            [
                'email' => $email,
                'password_hash' => $password_hash,
                'full_name' => $full_name,
                'role' => $role,
                'phone' => $phone,
                'department' => $department,
                'is_active' => 'Y'
            ]
        );

        $user_id = db_last_insert_id($conn, 'seq_user_id');

        if ($user_id) {
            // Auto-login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = $role;

            set_flash('success', 'Registration successful! Welcome to UAMS.');

            // Redirect based on selected role
            if ($role === 'reviewer') {
                redirect('reviewer/dashboard.php');
            } else {
                redirect('student/dashboard.php');
            }
        } else {
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }

    db_close($conn);
}
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>Registration</h1>
        <p class="auth-subtitle">Create your account</p>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required
                       value="<?php echo e($_POST['full_name'] ?? ''); ?>"
                       class="<?php echo isset($errors['full_name']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['full_name'])): ?>
                    <span class="error-text"><?php echo e($errors['full_name']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo e($_POST['email'] ?? ''); ?>"
                       class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="error-text"><?php echo e($errors['email']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone"
                       value="<?php echo e($_POST['phone'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="department">Department</label>
                <input type="text" id="department" name="department" required
                       value="<?php echo e($_POST['department'] ?? ''); ?>"
                       class="<?php echo isset($errors['department']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['department'])): ?>
                    <span class="error-text"><?php echo e($errors['department']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="role">Register As</label>
                <select id="role" name="role" required
                        class="<?php echo isset($errors['role']) ? 'input-error' : ''; ?>">
                    <option value="student" <?php echo (($_POST['role'] ?? 'student') === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="reviewer" <?php echo (($_POST['role'] ?? 'student') === 'reviewer') ? 'selected' : ''; ?>>Reviewer</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6"
                       class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['password'])): ?>
                    <span class="error-text"><?php echo e($errors['password']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       class="<?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="error-text"><?php echo e($errors['confirm_password']); ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>

        <p class="auth-footer">
            Already have an account? <a href="<?php echo base_url('login.php'); ?>">Login here</a>
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
