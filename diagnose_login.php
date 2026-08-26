<?php
// diagnose_login.php
// Diagnose why login/dashboard is failing

require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>Login/Connection Diagnostics</h2>";

// Test 1: Check if OCI8 is loaded
echo "<h3>1. OCI8 Extension</h3>";
if (extension_loaded('oci8')) {
    echo "<p style='color:green;'>✓ OCI8 loaded: " . phpversion('oci8') . "</p>";
} else {
    echo "<p style='color:red;'>✗ OCI8 NOT loaded</p>";
}

// Test 2: Try connection with current config
echo "<h3>2. Connection Test (system / a12345)</h3>";
$conn = @db_connect();
if ($conn) {
    echo "<p style='color:green;'>✓ Connection successful</p>";
    
    // Check what users exist
    $users = db_fetch_all($conn, "SELECT username, account_status, lock_date, expiry_date FROM dba_users WHERE username = 'SYSTEM'");
    echo "<h4>SYSTEM User Status</h4>";
    if ($users) {
        echo "<pre>";
        foreach ($users as $u) {
            foreach ($u as $k => $v) {
                echo htmlspecialchars($k) . ": " . htmlspecialchars($v) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p>Could not query SYSTEM user status</p>";
    }
    
    // Check password hash for student1
    echo "<h4>student1@uni.edu Password Check</h4>";
    $user = db_fetch_one($conn, 'SELECT user_id, email, password_hash FROM USERS WHERE email = :email', ['email' => 'student1@uni.edu']);
    if ($user) {
        echo "<p>User found: " . htmlspecialchars($user['EMAIL']) . "</p>";
        echo "<p>Hash length: " . strlen($user['PASSWORD_HASH']) . "</p>";
        echo "<p>Hash prefix: " . substr($user['PASSWORD_HASH'], 0, 4) . "</p>";
        $verify = password_verify('password123', $user['PASSWORD_HASH']);
        echo "<p>password_verify('password123'): " . ($verify ? '<span style="color:green;">MATCH</span>' : '<span style="color:red;">NO MATCH</span>') . "</p>";
    } else {
        echo "<p style='color:red;'>User NOT found</p>";
    }
    
    db_close($conn);
} else {
    echo "<p style='color:red;'>✗ Connection failed</p>";
    $error = oci_error();
    if ($error) {
        echo "<p><b>Error code:</b> " . htmlspecialchars($error['code']) . "</p>";
        echo "<p><b>Error message:</b> " . htmlspecialchars($error['message']) . "</p>";
    }
}

echo "<hr><p><a href='login.php'>&larr; Back to Login</a> | <a href='test_connection.php'>Connection Test</a></p>";
?>
