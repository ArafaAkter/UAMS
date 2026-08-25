<?php
// includes/auth.php
// Authentication guards and role-based access control

// Prevent caching of protected pages (stops browser back button after logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if user is not logged in
function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Please login to access this page.');
        redirect('login.php');
    }
}

// Redirect if user does not have the required role
// Usage: require_role('admin')
// Usage: require_role(['admin', 'reviewer'])
function require_role($allowed_roles) {
    require_login();

    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    if (!in_array(current_user_role(), $allowed_roles)) {
        set_flash('error', 'You do not have permission to access this page.');
        redirect('index.php');
    }
}

// Get current user data from database
function get_logged_in_user($conn) {
    if (!is_logged_in()) {
        return null;
    }

    $user = db_fetch_one($conn, 
        'SELECT user_id, email, full_name, role, phone, department, is_active FROM USERS WHERE user_id = :user_id',
        ['user_id' => current_user_id()]
    );

    return $user;
}

// Check if current user is active
function is_user_active($conn) {
    $user = get_logged_in_user($conn);
    return $user && $user['IS_ACTIVE'] === 'Y';
}
