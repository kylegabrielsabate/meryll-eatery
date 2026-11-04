<?php
session_start();

// Clear all session data (cart, user info, etc.)
$_SESSION = [];

// Remove the "Remember Me" cookie if it exists
if (isset($_COOKIE['remember_email'])) {
    setcookie('remember_email', '', time() - 3600, "/");
}

// Destroy the session completely
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Send back to login page
header('Location: login.php');
exit();
?>