<?php
session_start();
include '../connect.php';

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ../admin/admin.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // IMPORTANT: We must SELECT the 'role' column here!
        $stmt = $con->prepare("SELECT user_id, username, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Login success - set session variables
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role']; // <--- CRITICAL: Save the role!
                $_SESSION['cart']     = [];

                // Remember Me cookie logic
                if ($remember) {
                    setcookie('remember_email', $email, time() + (86400 * 30), "/");
                } else {
                    setcookie('remember_email', '', time() - 3600, "/");
                }

                // REDIRECT BASED ON ROLE
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/admin.php'); // Send to Admin Dashboard
                } else {
                    header('Location: index.php'); // Send to User Menu
                }
                exit();
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            $error = 'No account found with that email.';
        }
    }
}

// Auto-fill email from Remember Me cookie
if (empty($email) && isset($_COOKIE['remember_email'])) {
    $email = $_COOKIE['remember_email'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-container { background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); width: 100%; max-width: 420px; padding: 2.5rem; }
.logo { text-align: center; margin-bottom: 1.5rem; }
.logo i { font-size: 2.5rem; color: #8B0000; }
.logo h1 { font-size: 1.5rem; color: #8B0000; margin-top: 0.5rem; }
.logo p { color: #6c757d; font-size: 0.9rem; margin-top: 0.3rem; }
.form-group { margin-bottom: 1rem; position: relative; }
.form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem; color: #333; }
.form-group label .required { color: #e63946; }
.input-wrapper { position: relative; }
.form-group input { width: 100%; padding: 11px 40px 11px 14px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.95rem; outline: none; transition: border 0.2s; }
.form-group input:focus { border-color: #8B0000; }
.toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6c757d; font-size: 1.1rem; }
.toggle-password:hover { color: #8B0000; }
.form-options { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; font-size: 0.85rem; }
.remember-me { display: flex; align-items: center; gap: 6px; color: #555; cursor: pointer; }
.remember-me input { accent-color: #8B0000; }
.forgot-link { color: #8B0000; text-decoration: none; font-weight: 500; }
.forgot-link:hover { text-decoration: underline; }
.btn-login { width: 100%; padding: 12px; background: #8B0000; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-login:hover { background: #6d0000; }
.footer-link { text-align: center; margin-top: 1.2rem; font-size: 0.9rem; color: #6c757d; }
.footer-link a { color: #8B0000; text-decoration: none; font-weight: 600; }
.footer-link a:hover { text-decoration: underline; }
.custom-notif { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem 2.5rem; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); z-index: 9999; text-align: center; min-width: 320px; animation: popIn 0.3s ease; }
.custom-notif.show { display: block; }
.custom-notif.error { border-left: 5px solid #e63946; }
.custom-notif .notif-icon { font-size: 3rem; margin-bottom: 0.8rem; color: #e63946; }
.custom-notif .notif-title { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 0.4rem; }
.custom-notif .notif-msg { font-size: 0.9rem; color: #6c757d; margin-bottom: 1.2rem; }
.custom-notif .notif-btn { background: #8B0000; color: white; border: none; padding: 8px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; }
.notif-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9998; }
.notif-overlay.show { display: block; }
@keyframes popIn { from { opacity: 0; transform: translate(-50%, -50%) scale(0.8); } to { opacity: 1; transform: translate(-50%, -50%) scale(1); } }
</style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="bi bi-building"></i>
            <h1>Meryll Eatery</h1>
            <p>Sign in to your account</p>
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label><i class="bi bi-envelope"></i> Email Address <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="student@school.edu">
                </div>
            </div>

            <div class="form-group">
                <label><i class="bi bi-lock"></i> Password <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="loginPassword" required placeholder="Enter your password">
                    <button type="button" class="toggle-password" onclick="togglePass('loginPassword', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" <?php echo !empty($email) ? 'checked' : ''; ?>>
                    Remember me
                </label>
                <a href="forgot.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn-login"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
        </form>

        <div class="footer-link">
            Don't have an account? <a href="signup.php">Sign up here</a>
        </div>
    </div>

    <div class="notif-overlay" id="notifOverlay"></div>
    <div class="custom-notif" id="customNotif">
        <div class="notif-icon"><i class="bi bi-exclamation-circle"></i></div>
        <div class="notif-title">Login Failed</div>
        <div class="notif-msg" id="notifMsg">Something went wrong.</div>
        <button class="notif-btn" onclick="closeNotif()">OK</button>
    </div>

    <script>
        function togglePass(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.remove('bi-eye'); icon.classList.add('bi-eye-slash'); } 
            else { input.type = 'password'; icon.classList.remove('bi-eye-slash'); icon.classList.add('bi-eye'); }
        }
        function showNotif(msg) {
            document.getElementById('notifMsg').textContent = msg;
            document.getElementById('customNotif').classList.add('show');
            document.getElementById('notifOverlay').classList.add('show');
        }
        function closeNotif() {
            document.getElementById('customNotif').classList.remove('show');
            document.getElementById('notifOverlay').classList.remove('show');
        }
        <?php if ($error): ?> showNotif('<?php echo addslashes($error); ?>'); <?php endif; ?>
    </script>
</body>
</html>