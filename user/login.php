<?php
session_start();
include '../connect.php';

$error = '';

// ✅ 1. AUTO-LOGIN CHECK: If session is empty but cookie exists, log them in automatically
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $cookie_input = $_COOKIE['remember_me'];
    $stmt = $con->prepare("SELECT user_id, username, role FROM users WHERE username = ? OR email = ? OR student_id = ? LIMIT 1");
    $stmt->bind_param("sss", $cookie_input, $cookie_input, $cookie_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: ../admin/admin.php');
        } else {
            header('Location: index.php');
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = 'Please enter your credentials.';
    } else {
        $stmt = $con->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? OR email = ? OR student_id = ? LIMIT 1");
        $stmt->bind_param("sss", $login_input, $login_input, $login_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // ✅ 2. HANDLE "REMEMBER ME" COOKIE
                if (isset($_POST['remember'])) {
                    // Save cookie for 30 days
                    setcookie('remember_me', $login_input, time() + (86400 * 30), "/");
                } else {
                    // Delete cookie if unchecked
                    if (isset($_COOKIE['remember_me'])) {
                        setcookie('remember_me', "", time() - 3600, "/");
                    }
                }

                if ($user['role'] === 'admin') {
                     header('Location: ../admin/admin.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Invalid password.';
            }
        } else {
            $error = 'Account not found.';
        }
    }
}

// Pre-fill the input if cookie exists
$saved_login = isset($_COOKIE['remember_me']) ? htmlspecialchars($_COOKIE['remember_me']) : '';
$is_remembered = isset($_COOKIE['remember_me']) ? 'checked' : '';
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
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../images/background_image.jpg') no-repeat center center fixed;
    background-size: cover;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.login-box {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 420px;
    padding: 2.5rem 2rem;
    text-align: center;
}
.logo-icon { font-size: 3rem; color: #8B0000; margin-bottom: 0.5rem; }
.login-box h1 { color: #8B0000; font-size: 1.8rem; font-weight: 700; margin-bottom: 0.3rem; }
.login-box .subtitle { color: #6c757d; font-size: 0.95rem; margin-bottom: 2rem; }

.form-group { margin-bottom: 1.2rem; text-align: left; position: relative; }
.form-group label { display: block; font-weight: 600; font-size: 0.9rem; color: #333; margin-bottom: 0.4rem; }
.form-group label i { margin-right: 5px; color: #6c757d; }

.form-group input[type="text"], 
.form-group input[type="password"] {
    width: 100%;
    padding: 12px 45px 12px 15px;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    font-size: 1rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
}
.form-group input:focus { border-color: #8B0000; box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1); }

/* ✅ Eye Icon Styles */
.toggle-password {
    position: absolute;
    right: 15px;
    top: 38px;
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0;
    display: flex;
    align-items: center;
}
.toggle-password:hover { color: #8B0000; }

/* ✅ Remember Me Checkbox Styles */
.remember-container {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1.5rem;
    margin-top: -0.5rem;
}
.remember-container input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #8B0000;
    cursor: pointer;
}
.remember-container label {
    font-size: 0.9rem;
    color: #555;
    font-weight: 500;
    cursor: pointer;
    margin: 0;
}

.btn-signin {
    width: 100%; padding: 14px; background: #8B0000; color: #fff; border: none;
    border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer;
    margin-top: 0.5rem; transition: background 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-signin:hover { background: #6d0000; }
.error-msg { background: #ffe6e6; color: #8B0000; padding: 10px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1rem; border: 1px solid #ffcccc; }

.links-container { margin-top: 1.5rem; display: flex; flex-direction: row; justify-content: space-between; align-items: center; width: 100%; font-size: 0.9rem; }
.forgot-link { color: #8B0000; text-decoration: none; font-weight: 600; }
.forgot-link:hover { text-decoration: underline; }
.signup-text { color: #555; font-weight: 500; }
.signup-link { color: #8B0000; text-decoration: none; font-weight: 700; margin-left: 4px; }
.signup-link:hover { text-decoration: underline; color: #6d0000; }
</style>
</head>
<body>

<div class="login-box">
    <div class="logo-icon"><i class="bi bi-building"></i></div>
    <h1>Meryll Eatery</h1>
    <p class="subtitle">Sign in to your account</p>

    <?php if ($error): ?>
        <div class="error-msg"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label><i class="bi bi-person"></i> Username, Email, or ID</label>
            <input type="text" name="login_input" value="<?= $saved_login ?>" required placeholder="e.g. john_doe, email@school.edu, or E24-00123">
        </div>
        
        <div class="form-group">
            <label><i class="bi bi-lock"></i> Password</label>
            <input type="password" id="passwordInput" name="password" required placeholder="Enter your password">
            <button type="button" class="toggle-password" onclick="togglePassword()">
                <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
        </div>

        <!-- ✅ Remember Me Checkbox -->
        <div class="remember-container">
            <input type="checkbox" id="remember" name="remember" <?= $is_remembered ?>>
            <label for="remember">Remember me for 30 days</label>
        </div>

        <button type="submit" class="btn-signin"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
    </form>

    <div class="links-container">
        <a href="forgot.php" class="forgot-link">Forgot Password?</a>
        <span class="signup-text">No account? <a href="signup.php" class="signup-link">Sign Up</a></span>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.remove('bi-eye');
            eyeIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.remove('bi-eye-slash');
            eyeIcon.classList.add('bi-eye');
        }
    }
</script>

</body>
</html>