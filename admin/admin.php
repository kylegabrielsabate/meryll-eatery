<?php
session_start();
// Adjust this path if admin.php is in the root folder instead of the admin folder
include '../connect.php'; 

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check users table for admin role
        $stmt = $con->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // ✅ Set Admin Session Variables
                $_SESSION['admin_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'admin';

                header('Location: 
                    admin.php');
                exit();
            } else {
                $error = 'Invalid password.';
            }
        } else {
            $error = 'Admin account not found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #8B0000 0%, #4a0000 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.login-box {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 400px;
    padding: 2.5rem 2rem;
    text-align: center;
}
.logo-icon { font-size: 3rem; color: #8B0000; margin-bottom: 0.5rem; }
.login-box h1 { color: #8B0000; font-size: 1.8rem; font-weight: 700; margin-bottom: 0.3rem; }
.login-box .subtitle { color: #6c757d; font-size: 0.95rem; margin-bottom: 2rem; }
.form-group { margin-bottom: 1.2rem; text-align: left; }
.form-group label { display: block; font-weight: 600; font-size: 0.9rem; color: #333; margin-bottom: 0.4rem; }
.form-group input {
    width: 100%; padding: 12px 15px; border: 1px solid #dee2e6; border-radius: 10px;
    font-size: 1rem; outline: none; transition: border-color 0.2s;
}
.form-group input:focus { border-color: #8B0000; box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1); }
.btn-login {
    width: 100%; padding: 14px; background: #8B0000; color: #fff; border: none;
    border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer;
    margin-top: 0.5rem; transition: background 0.2s;
}
.btn-login:hover { background: #6d0000; }
.error-msg {
    background: #ffe6e6; color: #8B0000; padding: 10px; border-radius: 8px;
    font-size: 0.9rem; margin-bottom: 1rem; border: 1px solid #ffcccc;
}
.back-link { margin-top: 1.5rem; display: block; color: #6c757d; text-decoration: none; font-size: 0.9rem; }
.back-link:hover { color: #8B0000; }
</style>
</head>
<body>

<div class="login-box">
    <div class="logo-icon"><i class="bi bi-shield-lock"></i></div>
    <h1>Admin Portal</h1>
    <p class="subtitle">Meryll Eatery Management</p>

    <?php if ($error): ?>
        <div class="error-msg"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label><i class="bi bi-person"></i> Admin Username</label>
            <input type="text" name="username" required placeholder="Enter admin username">
        </div>
        <div class="form-group">
            <label><i class="bi bi-lock"></i> Password</label>
            <input type="password" name="password" required placeholder="Enter password">
        </div>
        <button type="submit" class="btn-login"><i class="bi bi-box-arrow-in-right"></i> Login</button>
    </form>

    <a href="../user/login.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to User Login</a>
</div>

</body>
</html>