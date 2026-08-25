<?php
// includes/functions.php
// Helper functions used throughout the application

require_once __DIR__ . '/../config/config.php';

// Get base URL
function base_url($path = '') {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

// Redirect to another page
function redirect($path) {
    header('Location: ' . base_url($path));
    exit;
}

// Escape output for HTML (XSS prevention)
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Format date for display
function format_date($date, $format = 'd M Y, h:i A') {
    if (empty($date)) {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

// Set flash message
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get and clear flash message
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Get current user ID
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user role
function current_user_role() {
    return $_SESSION['role'] ?? null;
}

// Map a status code to its badge CSS class
function status_badge_class($code) {
    $color_map = [
        'draft' => 'badge-gray',
        'submitted' => 'badge-info',
        'under_review' => 'badge-warning',
        'reviewed' => 'badge-info',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'needs_review' => 'badge-warning',
        'payment_pending' => 'badge-warning',
        'completed' => 'badge-success',
        'closed' => 'badge-gray'
    ];
    return $color_map[$code] ?? 'badge-gray';
}

// Safely convert an Oracle CLOB/LOB to a string
function clob_to_string($clob) {
    if ($clob === null || $clob === false) {
        return null;
    }
    if (is_object($clob) && method_exists($clob, 'load')) {
        return $clob->load();
    }
    return (string)$clob;
}
