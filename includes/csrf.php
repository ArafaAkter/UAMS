<?php
// includes/csrf.php
// CSRF token generation and validation

require_once __DIR__ . '/../config/config.php';

// Generate CSRF token and store in session
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token from POST request
function validate_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // GET requests don't need CSRF check
    }

    $token = $_POST['csrf_token'] ?? '';

    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token. Please try again.');
    }

    // Token is valid, regenerate for next use
    unset($_SESSION['csrf_token']);
    return true;
}
