<?php
// diagnose.php
// Check oci8 installation status

echo "<h2>OCI8 Diagnostic Report</h2>";

// 1. Check if oci8 is loaded
echo "<h3>1. OCI8 Extension Status</h3>";
if (extension_loaded('oci8')) {
    echo "<p style='color:green; font-weight:bold;'>✓ OCI8 is LOADED</p>";
    echo "<p>Version: " . phpversion('oci8') . "</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>✗ OCI8 is NOT loaded</p>";
}

// 2. Check PHP configuration
echo "<h3>2. PHP Configuration</h3>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>PHP Binary:</strong> " . PHP_BINARY . "</p>";

// 3. Check which php.ini is loaded
echo "<h3>3. Loaded php.ini</h3>";
$ini_loaded = php_ini_loaded_file();
echo "<p><strong>Loaded php.ini:</strong> " . ($ini_loaded ?: 'Not found') . "</p>";

// 4. Check extension_dir
echo "<h3>4. Extension Directory</h3>";
$ext_dir = ini_get('extension_dir');
echo "<p><strong>extension_dir:</strong> " . ($ext_dir ?: 'Not set') . "</p>";

// 5. Check if oci8 DLL exists
echo "<h3>5. OCI8 DLL Check</h3>";
$dlls = glob($ext_dir . DIRECTORY_SEPARATOR . 'php_oci8*.dll');
if ($dlls) {
    echo "<p style='color:green;'>✓ Found OCI8 DLL(s):</p><ul>";
    foreach ($dlls as $dll) {
        echo "<li>" . htmlspecialchars(basename($dll)) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red;'>✗ No OCI8 DLLs found in extension_dir</p>";
    echo "<p>Searched: " . htmlspecialchars($ext_dir) . "</p>";
}

// 6. Check PATH environment variable
echo "<h3>6. PATH Environment Variable</h3>";
$path = getenv('PATH');
echo "<p><strong>PATH contains:</strong></p>";
$paths = explode(PATH_SEPARATOR, $path);
$oracle_found = false;
foreach ($paths as $p) {
    if (stripos($p, 'oracle') !== false || stripos($p, 'instant') !== false) {
        echo "<p style='color:blue;'>→ $p</p>";
        $oracle_found = true;
    }
}
if (!$oracle_found) {
    echo "<p style='color:red;'>✗ No Oracle/Instant Client path found in PATH</p>";
}

// 7. Check loaded extensions
echo "<h3>7. All Loaded Extensions (search for oci)</h3>";
$extensions = get_loaded_extensions();
$oci_found = false;
foreach ($extensions as $ext) {
    if (stripos($ext, 'oci') !== false) {
        echo "<p style='color:green;'>→ $ext</p>";
        $oci_found = true;
    }
}
if (!$oci_found) {
    echo "<p style='color:red;'>✗ No OCI-related extensions loaded</p>";
}

// 8. Recommendations
echo "<h3>8. Recommendations</h3>";
if (!extension_loaded('oci8')) {
    echo "<div style='background:#fff3cd; padding:15px; border-radius:5px; border-left:4px solid #ffc107;'>";
    echo "<p><strong>To fix this issue:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Install Oracle Instant Client:</strong><br>";
    echo "Download from: <a href='https://www.oracle.com/database/technologies/instant-client.html' target='_blank'>https://www.oracle.com/database/technologies/instant-client.html</a><br>";
    echo "Extract to: <code>C:\\oracle\\instantclient_19_10</code> or <code>C:\\oracle\\instantclient_21_10</code></li>";
    echo "<li><strong>Add to Windows PATH:</strong><br>";
    echo "Add the Instant Client folder to your system PATH environment variable</li>";
    echo "<li><strong>Enable extension in php.ini:</strong><br>";
    echo "Uncomment or add: <code>extension=oci8_12c</code> (or <code>oci8_19</code> / <code>oci8_21</code>)<br>";
    echo "Make sure you edit the correct php.ini: <code>" . ($ini_loaded ?: 'check above') . "</code></li>";
    echo "<li><strong>Restart Apache</strong> in XAMPP Control Panel</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr><p><a href='../index.php'>&larr; Back to Home</a> | <a href='test_connection.php'>Test Connection</a></p>";
?>
