<?php
// test_connection.php
// Simple database connection test page
// Visit: http://localhost/UAMS/test_connection.php

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$connection_status = '';
$query_result = '';
$error_message = '';

// Test 1: Connect to Oracle
$conn = null;
try {
    $conn = db_connect();
    $connection_status = 'SUCCESS';
} catch (Exception $e) {
    $connection_status = 'FAILED';
    $error_message = $e->getMessage();
}

// Test 2: Run a simple query if connected
if ($connection_status === 'SUCCESS') {
    try {
        // Simple Oracle dual table query
        $result = db_fetch_value($conn, 'SELECT 1 AS test FROM DUAL');
        $query_result = ($result == 1) ? 'SUCCESS' : 'FAILED';
    } catch (Exception $e) {
        $query_result = 'FAILED';
        $error_message = $e->getMessage();
    }

    // Test 3: Check if our tables exist (if seed.sql was run)
    try {
        $table_count = db_fetch_value($conn, "SELECT COUNT(*) FROM user_tables WHERE table_name = 'USERS'");
        $tables_exist = ($table_count > 0) ? 'YES' : 'NO';
    } catch (Exception $e) {
        $tables_exist = 'ERROR: ' . $e->getMessage();
    }

    // Test 4: Count users if table exists
    if ($tables_exist === 'YES') {
        try {
            $user_count = db_fetch_value($conn, 'SELECT COUNT(*) FROM USERS');
            $query_result .= ' | Users in DB: ' . $user_count;
        } catch (Exception $e) {
            $query_result .= ' | Count query failed: ' . $e->getMessage();
        }
    }

    db_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test - UAMS</title>
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css'); ?>">
    <style>
        .test-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .failed {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        h1 {
            color: #1a237e;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3949ab;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Oracle Database Connection Test</h1>

        <div class="test-result <?php echo ($connection_status === 'SUCCESS') ? 'success' : 'failed'; ?>">
            <strong>Connection Test:</strong> <?php echo $connection_status; ?><br>
            <?php if ($error_message): ?>
                <strong>Error:</strong> <?php echo e($error_message); ?>
            <?php endif; ?>
        </div>

        <?php if ($connection_status === 'SUCCESS'): ?>
            <div class="test-result <?php echo ($query_result) ? 'success' : 'failed'; ?>">
                <strong>Query Test:</strong> <?php echo e($query_result); ?>
            </div>

            <div class="test-result info">
                <strong>Database Tables Found:</strong> <?php echo e($tables_exist); ?>
            </div>

            <div class="test-result info">
                <strong>Connection Details:</strong><br>
                Host: <?php echo e(DB_HOST); ?><br>
                Port: <?php echo e(DB_PORT); ?><br>
                Service: <?php echo e(DB_SERVICE); ?><br>
                Username: <?php echo e(DB_USERNAME); ?>
            </div>
        <?php endif; ?>

        <a href="<?php echo base_url(); ?>" class="back-link">&larr; Back to Homepage</a>
    </div>
</body>
</html>
