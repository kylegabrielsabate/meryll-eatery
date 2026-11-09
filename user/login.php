<?php
session_start();
include '../connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $con->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:'Inter',sans-serif;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    /* ✅ REPLACE THE URL BELOW WITH YOUR OWN IMAGE */
    background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('../images/background_image.jpg') center/cover no-repeat;
}
.login-box{
    background:rgba(255,255,255,0.95);
    backdrop-filter:blur(10px);
    border-radius:20px;
    padding:2.5rem;
    width:100%;
    max-width:400px;
    box-shadow:0 20px 60px rgba(0,0,0,0.3);
}
.logo{text-align:center;margin-bottom:1.5rem}
.logo i{font-size:3rem;color:#8B0000}
.logo h1{font-size:1.5rem;color:#8B0000;margin-top:0.5rem}
.logo p{color:#6c757d;font-size:0.9rem;margin-top:0.3rem}
.form-group{margin-bottom:1.2rem}
.form-group label{display:block;font-weight:600;font-size:0.85rem;margin-bottom:0.4rem;color:#333}
.form-group input{
    width:100%;
    padding:12px 14px;
    border:1px solid #dee2e6;
    border-radius:10px;
    font-size:0.95rem;
    outline:none;
    transition:border 0.2s;
}
.form-group input:focus{border-color:#8B0000}
.btn{
    width:100%;
    padding:13px;
    background:#8B0000;
    color:#fff;
    border:none;
    border-radius:10px;
    font-size:1rem;
    font-weight:600;
    cursor:pointer;
    margin-top:0.5rem;
    transition:background 0.2s;
}
.btn:hover{background:#6d0000}
.footer-link{text-align:center;margin-top:1.2rem;font-size:0.9rem;color:#6c757d}
.footer-link a{color:#8B0000;text-decoration:none;font-weight:600}
.error-msg{background:#ffe0e0;color:#8B0000;padding:10px;border-radius:8px;font-size:0.85rem;margin-bottom:1rem;text-align:center}
</style>
</head>
<body>
<div class="login-box">
    <div class="logo">
        <i class="bi bi-building"></i>
        <h1>Meryll Eatery</h1>
        <p>Sign in to your account</p>
    </div>
    
    <?php if($error): ?>
    <div class="error-msg"><i class="bi bi-exclamation-circle"></i> <?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label><i class="bi bi-person"></i> Username</label>
            <input type="text" name="username" required placeholder="Enter your username">
        </div>
        <div class="form-group">
            <label><i class="bi bi-lock"></i> Password</label>
            <input type="password" name="password" required placeholder="Enter your password">
        </div>
        <button type="submit" class="btn"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
    </form>
    <div class="footer-link">
        <a href="forgot.php">Forgot Password?</a>
    </div>
    <!-- ✅ SIGN UP LINK ADDED -->
<div style="text-align: center; margin-top: 15px; font-size: 0.9rem; color: #fff;">
    Don't have an account? 
    <a href="register.php" style="color: #ffc107; text-decoration: none; font-weight: 600;">Sign Up</a>
</div>
</div>
</body>
</html>