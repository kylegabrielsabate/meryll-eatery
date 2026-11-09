<?php
session_start();

// ✅ 1. DELETE THE REMEMBER ME COOKIE
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// ✅ 2. DESTROY THE SESSION
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// ✅ 3. REDIRECT TO LOGIN
header('Location: login.php');
exit();
?>