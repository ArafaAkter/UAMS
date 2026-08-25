<?php
// includes/sidebar.php
// Role-based sidebar navigation
// Requires: $page_title set, current_user_role() available

$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2>Navigation</h2>
    </div>
    <ul class="sidebar-nav">
        <?php if (current_user_role() === 'student'): ?>
            <li class="nav-header">Student Menu</li>
            <li><a href="<?php echo base_url('student/dashboard.php'); ?>" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="<?php echo base_url('student/apply.php'); ?>" class="<?php echo ($current_page == 'apply.php') ? 'active' : ''; ?>">Apply New Application</a></li>
            <li><a href="<?php echo base_url('student/my_applications.php'); ?>" class="<?php echo ($current_page == 'my_applications.php') ? 'active' : ''; ?>">My Applications</a></li>
            <li><a href="<?php echo base_url('student/track_application.php'); ?>" class="<?php echo ($current_page == 'track_application.php') ? 'active' : ''; ?>">Track Application</a></li>
            <li><a href="<?php echo base_url('student/documents.php'); ?>" class="<?php echo ($current_page == 'documents.php') ? 'active' : ''; ?>">Documents</a></li>
            <li><a href="<?php echo base_url('student/payment_status.php'); ?>" class="<?php echo ($current_page == 'payment_status.php') ? 'active' : ''; ?>">Payment Status</a></li>
            <li><a href="<?php echo base_url('profile.php'); ?>" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">Profile</a></li>
        <?php elseif (current_user_role() === 'reviewer'): ?>
            <li class="nav-header">Reviewer Menu</li>
            <li><a href="<?php echo base_url('reviewer/dashboard.php'); ?>" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="<?php echo base_url('reviewer/applications.php'); ?>" class="<?php echo ($current_page == 'applications.php') ? 'active' : ''; ?>">My Applications</a></li>
            <li><a href="<?php echo base_url('reviewer/review_history.php'); ?>" class="<?php echo ($current_page == 'review_history.php') ? 'active' : ''; ?>">Review History</a></li>
            <li><a href="<?php echo base_url('profile.php'); ?>" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">Profile</a></li>
        <?php elseif (current_user_role() === 'admin'): ?>
            <li class="nav-header">Admin Menu</li>
            <li><a href="<?php echo base_url('admin/dashboard.php'); ?>" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
            <li><a href="<?php echo base_url('admin/users.php'); ?>" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">Users</a></li>
            <li><a href="<?php echo base_url('admin/all_applications.php'); ?>" class="<?php echo ($current_page == 'all_applications.php') ? 'active' : ''; ?>">Applications</a></li>
            <li><a href="<?php echo base_url('admin/payments.php'); ?>" class="<?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>">Payments</a></li>
            <li><a href="<?php echo base_url('admin/settings.php'); ?>" class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">Settings</a></li>
            <li><a href="<?php echo base_url('profile.php'); ?>" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">Profile</a></li>
        <?php endif; ?>
    </ul>
</aside>
