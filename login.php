<?php
// login.php
// User authentication for Students, Reviewers, and Admins

$page_title = 'Login';
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

$errors = [];

// If already logged in, redirect to appropriate dashboard
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

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
        // Look up user by email
        $user = db_fetch_one($conn,
            'SELECT user_id, email, password_hash, full_name, role, is_active
             FROM USERS
             WHERE email = :email',
            ['email' => $email]
        );

        if ($user) {
            // Verify password
            if (password_verify($password, $user['PASSWORD_HASH'])) {
                // Check if account is active
                if ($user['IS_ACTIVE'] === 'Y') {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['user_id'] = $user['USER_ID'];
                    $_SESSION['email'] = $user['EMAIL'];
                    $_SESSION['full_name'] = $user['FULL_NAME'];
                    $_SESSION['role'] = $user['ROLE'];

                    // Redirect based on role
                    if ($user['ROLE'] === 'student') {
                        redirect('student/dashboard.php');
                    } elseif ($user['ROLE'] === 'reviewer') {
                        redirect('reviewer/dashboard.php');
                    } elseif ($user['ROLE'] === 'admin') {
                        redirect('admin/dashboard.php');
                    }
                } else {
                    $errors['general'] = 'Your account has been deactivated. Please contact the administrator.';
                }
            } else {
                $errors['general'] = 'Invalid email or password.';
            }
        } else {
            $errors['general'] = 'Invalid email or password.';
        }
    }

    db_close($conn);
}
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>Login</h1>
        <p class="auth-subtitle">Welcome back! Please login to your account.</p>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error"><?php echo e($errors['general']); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?php echo e($_POST['email'] ?? ''); ?>"
                       class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="error-text"><?php echo e($errors['email']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                <?php if (isset($errors['password'])): ?>
                    <span class="error-text"><?php echo e($errors['password']); ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="auth-footer">
            Don't have an account? <a href="<?php echo base_url('register.php'); ?>">Register here</a>
        </p>

        <!-- <div class="demo-credentials">
            <p><strong>Demo Credentials (after running seed.sql):</strong></p>
            <ul>
                <li>Admin: admin@uni.edu / password123</li>
                <li>Reviewer: reviewer1@uni.edu / password123</li>
                <li>Student: student1@uni.edu / password123</li>
            </ul>
        </div> -->
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
