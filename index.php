<?php
// index.php
// Public landing page

$page_title = 'Home';
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
?>

<div class="hero-section">
    <div class="container">
        <h1>Welcome to the University Application Management System</h1>
        <p class="hero-subtitle">Submit, track, and manage your university applications online.</p>
        <div class="hero-actions">
            <?php if (!is_logged_in()): ?>
                <a href="<?php echo base_url('register.php'); ?>" class="btn btn-primary btn-large">Get Started</a>
                <a href="<?php echo base_url('login.php'); ?>" class="btn btn-secondary btn-large">Login</a>
            <?php else: ?>
                <?php if (current_user_role() === 'student'): ?>
                    <a href="<?php echo base_url('student/dashboard.php'); ?>" class="btn btn-primary btn-large">My Dashboard</a>
                <?php elseif (current_user_role() === 'reviewer'): ?>
                    <a href="<?php echo base_url('reviewer/dashboard.php'); ?>" class="btn btn-primary btn-large">Reviewer Dashboard</a>
                <?php elseif (current_user_role() === 'admin'): ?>
                    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="btn btn-primary btn-large">Admin Dashboard</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="features-section">
    <div class="container">
        <h2>Application Types</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>Admission</h3>
                <p>Apply for admission to undergraduate and postgraduate programs.</p>
            </div>
            <div class="feature-card">
                <h3>Scholarship</h3>
                <p>Submit scholarship applications with supporting documents.</p>
            </div>
            <div class="feature-card">
                <h3>Transcript Request</h3>
                <p>Request official academic transcripts online.</p>
            </div>
            <div class="feature-card">
                <h3>Certificate Request</h3>
                <p>Apply for enrollment, completion, or other certificates.</p>
            </div>
            <div class="feature-card">
                <h3>ID Card Request</h3>
                <p>Request or renew your student identification card.</p>
            </div>
            <div class="feature-card">
                <h3>Department/Course Change</h3>
                <p>Submit requests to change your department or courses.</p>
            </div>
        </div>
    </div>
</div>

<?php $current_role = is_logged_in() ? current_user_role() : null; ?>
<?php if ($current_role !== 'reviewer' && $current_role !== 'admin'): ?>
    <div class="tracking-section">
        <div class="container">
            <h2>Track Your Application</h2>
            <p>Enter your reference number to check your application status.</p>
            <a href="<?php echo base_url('track_application.php'); ?>" class="btn btn-primary">Track Now</a>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
