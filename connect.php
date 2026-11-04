<?php
// Use Environment Variables for Render, fallback to localhost for XAMPP
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3306;
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'db_shop';

// Note the $port added at the end of the mysqli connection
$con = new mysqli($host, $user, $pass, $db, $port);

if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}
?>