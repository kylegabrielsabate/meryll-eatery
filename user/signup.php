<?php
session_start();
include '../connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Retain form data
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$phone = $_POST['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } else {
        $check = $con->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already registered. Please login instead.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $con->prepare("INSERT INTO users (username, email, student_id, phone, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $student_id, $phone, $hashed);
            if ($stmt->execute()) {
                $success = 'Account created successfully!';
                // Clear retained data on success
                $username = $email = $student_id = $phone = '';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Meryll Eatery</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; overflow: hidden; }
        body {
    font-family: 'Inter', sans-serif;
    /* ✅ SAME BACKGROUND IMAGE AS LOGIN */
    background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('../images/background_image.jpg') no-repeat center center fixed; 
    background-size: cover;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
        .signup-container {
            background: white;
            border-radius: 14px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 440px;
            max-height: 95vh;
            padding: 1.5rem;
            overflow-y: auto;
        }
        .signup-container::-webkit-scrollbar { width: 4px; }
        .signup-container::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }

        .logo { text-align: center; margin-bottom: 1rem; }
        .logo i { font-size: 2rem; color: #8B0000; }
        .logo h1 { font-size: 1.3rem; color: #8B0000; margin-top: 0.3rem; }
        .logo p { color: #6c757d; font-size: 0.8rem; margin-top: 0.2rem; }

        .form-row { display: flex; gap: 0.6rem; }
        .form-row .form-group { flex: 1; }

        .form-group { margin-bottom: 0.6rem; position: relative; }
        .form-group label { display: block; font-weight: 600; font-size: 0.75rem; margin-bottom: 0.25rem; color: #333; }
        .form-group label .required { color: #e63946; }
        .input-wrapper { position: relative; }
        .form-group input {
            width: 100%; padding: 8px 35px 8px 10px; border: 1px solid #dee2e6;
            border-radius: 6px; font-size: 0.85rem; outline: none; transition: border 0.2s;
        }
        .form-group input:focus { border-color: #8B0000; }
        .form-group input.invalid { border-color: #e63946; }
        .form-group input.valid { border-color: #2ecc71; }
        .toggle-password {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #6c757d; font-size: 0.95rem;
        }

        /* Password Policy Box */
        .policy-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 0.5rem 0.7rem;
            margin-top: 0.3rem;
            font-size: 0.7rem;
        }
        .policy-item {
            display: flex; align-items: center; gap: 5px;
            color: #6c757d; margin-bottom: 2px; transition: color 0.2s;
        }
        .policy-item.met { color: #2ecc71; }
        .policy-item i { font-size: 0.75rem; }

        .btn-signup {
            width: 100%; padding: 10px; background: #8B0000; color: white; border: none;
            border-radius: 6px; font-size: 0.95rem; font-weight: 600; cursor: pointer;
            transition: background 0.2s; margin-top: 0.5rem;
        }
        .btn-signup:hover { background: #6d0000; }

        .footer-link { text-align: center; margin-top: 0.8rem; font-size: 0.8rem; color: #6c757d; }
        .footer-link a { color: #8B0000; text-decoration: none; font-weight: 600; }

        /* Centered Notification */
        .custom-notif {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            background: white; padding: 2rem 2.5rem; border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); z-index: 9999;
            text-align: center; min-width: 320px; animation: popIn 0.3s ease;
        }
        .custom-notif.show { display: block; }
        .custom-notif.error { border-left: 5px solid #e63946; }
        .custom-notif.success { border-left: 5px solid #2ecc71; }
        .custom-notif .notif-icon { font-size: 3rem; margin-bottom: 0.8rem; }
        .custom-notif.error .notif-icon { color: #e63946; }
        .custom-notif.success .notif-icon { color: #2ecc71; }
        .custom-notif .notif-title { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 0.4rem; }
        .custom-notif .notif-msg { font-size: 0.9rem; color: #6c757d; margin-bottom: 1.2rem; }
        .custom-notif .notif-btn {
            background: #8B0000; color: white; border: none; padding: 8px 24px;
            border-radius: 6px; cursor: pointer; font-weight: 600;
        }
        .notif-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9998; }
        .notif-overlay.show { display: block; }

        @keyframes popIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="logo">
            <i class="bi bi-building"></i>
            <h1>Create Account</h1>
            <p>Join Meryll Eatery</p>
        </div>

        <form method="POST" action="" id="signupForm" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="bi bi-person"></i> Full Name <span class="required">*</span></label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required placeholder="Juan Dela Cruz">
                </div>
                <div class="form-group">
                    <label><i class="bi bi-card-text"></i> Student ID</label>
                    <input type="text" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" placeholder="2024-0001">
                </div>
            </div>

            <div class="form-group">
                <label><i class="bi bi-envelope"></i> Email Address <span class="required">*</span></label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="student@school.edu">
            </div>

            <div class="form-group">
                <label><i class="bi bi-phone"></i> Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="09XX-XXX-XXXX">
            </div>

            <div class="form-group">
                <label><i class="bi bi-lock"></i> Password <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="password" name="password" id="signupPassword" required placeholder="Min. 8 characters">
                    <button type="button" class="toggle-password" onclick="togglePass('signupPassword', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="policy-box">
                    <div class="policy-item" id="policy-length"><i class="bi bi-circle"></i> At least 8 characters</div>
                    <div class="policy-item" id="policy-upper"><i class="bi bi-circle"></i> One uppercase letter (A-Z)</div>
                    <div class="policy-item" id="policy-number"><i class="bi bi-circle"></i> One number (0-9)</div>
                    <div class="policy-item" id="policy-special"><i class="bi bi-circle"></i> One special character (!@#$...)</div>
                </div>
            </div>

            <div class="form-group">
                <label><i class="bi bi-lock-fill"></i> Confirm Password <span class="required">*</span></label>
                <div class="input-wrapper">
                    <input type="password" name="confirm_password" id="signupConfirm" required placeholder="Re-enter password">
                    <button type="button" class="toggle-password" onclick="togglePass('signupConfirm', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-signup">Create Account</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
    </div>

    <!-- Centered Notification -->
    <div class="notif-overlay" id="notifOverlay"></div>
    <div class="custom-notif" id="customNotif">
        <div class="notif-icon" id="notifIcon"><i class="bi bi-exclamation-circle"></i></div>
        <div class="notif-title" id="notifTitle">Error</div>
        <div class="notif-msg" id="notifMsg">Something went wrong.</div>
        <button class="notif-btn" onclick="closeNotif()">OK</button>
    </div>

    <script>
        function togglePass(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        function showNotif(type, title, msg) {
            const notif = document.getElementById('customNotif');
            const overlay = document.getElementById('notifOverlay');
            const icon = document.getElementById('notifIcon');
            notif.className = 'custom-notif show ' + type;
            overlay.classList.add('show');
            icon.innerHTML = type === 'error' ? '<i class="bi bi-exclamation-circle"></i>' : '<i class="bi bi-check-circle"></i>';
            document.getElementById('notifTitle').textContent = title;
            document.getElementById('notifMsg').textContent = msg;
        }

        function closeNotif() {
            document.getElementById('customNotif').classList.remove('show');
            document.getElementById('notifOverlay').classList.remove('show');
        }

        // Real-time Password Policy Validation
        const passwordInput = document.getElementById('signupPassword');
        const confirmInput = document.getElementById('signupConfirm');

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            checkPolicy('policy-length', val.length >= 8);
            checkPolicy('policy-upper', /[A-Z]/.test(val));
            checkPolicy('policy-number', /[0-9]/.test(val));
            checkPolicy('policy-special', /[^A-Za-z0-9]/.test(val));
            checkMatch();
        });

        confirmInput.addEventListener('input', checkMatch);

        function checkPolicy(id, met) {
            const el = document.getElementById(id);
            const icon = el.querySelector('i');
            if (met) {
                el.classList.add('met');
                icon.className = 'bi bi-check-circle-fill';
            } else {
                el.classList.remove('met');
                icon.className = 'bi bi-circle';
            }
        }

        function checkMatch() {
            const pw = passwordInput.value;
            const cpw = confirmInput.value;
            if (cpw.length === 0) {
                confirmInput.classList.remove('valid', 'invalid');
                return;
            }
            if (pw === cpw) {
                confirmInput.classList.add('valid');
                confirmInput.classList.remove('invalid');
            } else {
                confirmInput.classList.add('invalid');
                confirmInput.classList.remove('valid');
            }
        }

        <?php if ($error): ?>
        showNotif('error', 'Registration Failed', '<?php echo addslashes($error); ?>');
        <?php endif; ?>
        <?php if ($success): ?>
        showNotif('success', 'Success!', '<?php echo addslashes($success); ?> Redirecting to login...');
        setTimeout(() => { window.location.href = 'login.php'; }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>