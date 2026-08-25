<?php
// config/database.php
// Oracle database connection and reusable query helpers
// Core PHP only, no frameworks

// Check if Oracle extension is loaded
if (!extension_loaded('oci8')) {
    die('Oracle PHP extension (oci8) is not loaded. Please enable it in php.ini.');
}

// ============================================
// DATABASE CREDENTIALS
// Default to Oracle XE defaults. Change after creating your app user.
// ============================================
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SERVICE', 'xe');        // Oracle XE service name (lowercase as registered with listener)
define('DB_USERNAME', 'system');   // Oracle XE default admin user
define('DB_PASSWORD', 'a12345');   // Oracle XE default password

// ============================================
// CONNECTION
// ============================================
function db_connect() {
    $username = trim(DB_USERNAME);
    $password = trim(DB_PASSWORD);

    // Try EZCONNECT format first: //host:port/service
    $connection_string = '//' . DB_HOST . ':' . DB_PORT . '/' . DB_SERVICE;

    $conn = @oci_connect($username, $password, $connection_string);

    // If EZCONNECT fails, try DESCRIPTION format
    if (!$conn) {
        $connection_string = '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=' . DB_HOST . ')(PORT=' . DB_PORT . '))(CONNECT_DATA=(SERVICE_NAME=' . DB_SERVICE . ')))';
        $conn = @oci_connect($username, $password, $connection_string);
    }

    if (!$conn) {
        $error = oci_error();
        $errmsg = $error['message'] ?? 'Unknown error';
        die('Database connection failed: ' . $errmsg . '<br><br>'
            . 'Verify your Oracle credentials in config/database.php<br>'
            . 'Default XE credentials: system / oracle<br>'
            . 'Make sure Oracle XE service is running.');
    }

    // Set date format for this session
    oci_execute(oci_parse($conn, "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"));

    return $conn;
}

// ============================================
// CLOSE CONNECTION
// ============================================
function db_close($conn) {
    if ($conn && is_resource($conn)) {
        oci_close($conn);
    }
}

// ============================================
// PARAMETERIZED QUERY EXECUTION
// Safely prepares, binds, and executes SQL
// ============================================
function db_query($conn, $sql, $params = []) {
    if (!$conn) {
        die('Database connection is not valid.');
    }

    $stid = @oci_parse($conn, $sql);

    if (!$stid) {
        $error = oci_error($conn);
        die('SQL prepare failed: ' . $error['message']);
    }

    // Bind parameters safely
    // Store values in a local array to ensure oci_bind_by_name references stay valid
    $bound_values = [];
    foreach ($params as $key => $value) {
        // Ensure bind name starts with colon
        $bind_name = (strpos($key, ':') === 0) ? $key : ':' . $key;
        $bound_values[$bind_name] = $value;
        oci_bind_by_name($stid, $bind_name, $bound_values[$bind_name]);
    }

    $result = @oci_execute($stid);

    if (!$result) {
        $error = oci_error($stid);
        oci_free_statement($stid);
        die('SQL execute failed: ' . $error['message']);
    }

    return $stid;
}

// ============================================
// FETCH HELPERS
// ============================================

// Fetch a single row as associative array
function db_fetch_one($conn, $sql, $params = []) {
    $stid = db_query($conn, $sql, $params);
    $row = oci_fetch_assoc($stid);
    oci_free_statement($stid);
    return $row ?: null;
}

// Fetch all rows as array of associative arrays
function db_fetch_all($conn, $sql, $params = []) {
    $stid = db_query($conn, $sql, $params);
    $rows = [];
    while ($row = oci_fetch_assoc($stid)) {
        $rows[] = $row;
    }
    oci_free_statement($stid);
    return $rows;
}

// Fetch a single column value from first row
function db_fetch_value($conn, $sql, $params = []) {
    $stid = db_query($conn, $sql, $params);
    $row = oci_fetch_row($stid);
    oci_free_statement($stid);
    return $row ? $row[0] : null;
}

// Get the number of affected rows from INSERT/UPDATE/DELETE
function db_affected_rows($conn, $stid) {
    return oci_num_rows($stid);
}

// Get the last inserted ID (uses sequences)
function db_last_insert_id($conn, $sequence_name) {
    $stid = db_query($conn, "SELECT " . $sequence_name . ".CURRVAL AS id FROM dual");
    $row = oci_fetch_assoc($stid);
    oci_free_statement($stid);
    return $row ? $row['ID'] : null;
}
