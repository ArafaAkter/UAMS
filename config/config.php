<?php
// config/config.php
// Application constants and base settings

define('APP_NAME', 'University Application Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/UAMS');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes
define('TIMEZONE', 'Asia/Dhaka');

date_default_timezone_set(TIMEZONE);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
