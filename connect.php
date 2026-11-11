<?php
// Use Environment Variables for Render, fallback to localhost for XAMPP
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3306;
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'db_shop';

// Initialize mysqli connection
$con = mysqli_init();
if (!$con) {
    die("mysqli_init failed");
}

// Check if we are connecting to the cloud (TiDB)
$is_cloud = ($host !== 'localhost' && $host !== '127.0.0.1');

if ($is_cloud) {
    // Force SSL for TiDB Cloud (Required!)
    mysqli_ssl_set($con, NULL, NULL, NULL, NULL, NULL);
    $flags = MYSQLI_CLIENT_SSL;
} else {
    // Local XAMPP doesn't need SSL
    $flags = 0;
}

// Connect to the database
if (!mysqli_real_connect($con, $host, $user, $pass, $db, $port, NULL, $flags)) {
    die("Connection failed: " . mysqli_connect_error());
}

// ✅ PHILIPPINE TIMEZONE FIX - Wrapped in error handling
try {
    date_default_timezone_set('Asia/Manila');
    @$con->query("SET time_zone = '+08:00'");
} catch (Exception $e) {
    // Silently fail if timezone setting doesn't work
    // PHP timezone is still set, just MySQL might use UTC
}
?>