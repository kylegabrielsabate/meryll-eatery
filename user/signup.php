<?php
session_start();
include '../connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $student_id = strtoupper(trim($_POST['student_id'] ?? ''));
    $user_type = $_POST['user_type'] ?? 'student';
    
    // ✅ STRICT PASSWORD POLICY CHECKS
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = 'Password must contain at least one special character (!@#$%^&*).';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (empty($username) || empty($email) || empty($student_id)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // ✅ DUPLICATE CHECK (Email AND ID)
        $stmt = $con->prepare("SELECT user_id FROM users WHERE email = ? OR student_id = ?");
        $stmt->bind_param("ss", $email, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $check_email = $con->prepare("SELECT user_id FROM users WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                $error = 'This email is already registered.';
            } else {
                $error = 'This ID number (' . htmlspecialchars($student_id) . ') is already registered.';
            }
        } else {
            // ✅ VALIDATE ID FORMAT
            $valid_id = false;
            if ($user_type === 'instructor') {
                if (preg_match('/^TAG-\d{2,4}$/i', $student_id)) $valid_id = true;
                else $error = 'Instructor ID must be in format: TAG-123';
            } else {
                if (preg_match('/^[A-Z]\d{2}-\d{3,}$/i', $student_id)) $valid_id = true;
                else $error = 'Student ID must be in format: E24-00123';
            }
            
            if (!$error && $valid_id) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $role = 'user';
                
                $stmt = $con->prepare("INSERT INTO users (username, email, password, student_id, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $student_id, $role);
                
                if ($stmt->execute()) {
                    $success = 'Account created successfully! You can now login.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
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
.signup-box {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 480px;
    padding: 2.5rem 2rem;
    text-align: center;
}
.logo-icon { font-size: 3rem; color: #8B0000; margin-bottom: 0.5rem; }
.signup-box h1 { color: #8B0000; font-size: 1.8rem; font-weight: 700; margin-bottom: 0.3rem; }
.signup-box .subtitle { color: #6c757d; font-size: 0.95rem; margin-bottom: 2rem; }

.form-group { margin-bottom: 1.2rem; text-align: left; position: relative; }
.form-group label { display: block; font-weight: 600; font-size: 0.9rem; color: #333; margin-bottom: 0.4rem; }
.form-group input, .form-group select {
    width: 100%; padding: 12px 45px 12px 15px; border: 1px solid #dee2e6; border-radius: 10px;
    font-size: 1rem; font-family: inherit; outline: none; transition: border-color 0.2s;
}
.form-group input:focus, .form-group select:focus { 
    border-color: #8B0000; box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1); 
}

/* ✅ EYE TOGGLE BUTTON */
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
    z-index: 10;
}
.toggle-password:hover { color: #8B0000; }

/* ✅ PASSWORD STRENGTH METER */
.password-strength {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}
.password-strength-bar {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}
.strength-weak { width: 25%; background: #ef4444; }
.strength-fair { width: 50%; background: #f59e0b; }
.strength-good { width: 75%; background: #3b82f6; }
.strength-strong { width: 100%; background: #10b981; }

.password-rules {
    font-size: 0.75rem;
    color: #6c757d;
    margin-top: 8px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
}
.rule-item { display: flex; align-items: center; gap: 4px; }
.rule-item.met { color: #10b981; }
.rule-item.unmet { color: #ef4444; }

.id-format-info {
    background: #fff3cd; border: 1px solid #ffc107; color: #856404;
    padding: 10px; border-radius: 8px; font-size: 0.85rem; margin-top: 8px; display: none;
}
.id-format-info.show { display: block; }

.btn-signup {
    width: 100%; padding: 14px; background: #8B0000; color: #fff; border: none;
    border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer;
    margin-top: 0.5rem; transition: background 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-signup:hover { background: #6d0000; }
.btn-signup:disabled { background: #ccc; cursor: not-allowed; }

.error-msg { background: #ffe6e6; color: #8B0000; padding: 10px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1rem; border: 1px solid #ffcccc; }
.success-msg { background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1rem; border: 1px solid #c3e6cb; }

.login-link { color: #8B0000; text-decoration: none; font-weight: 700; }
.login-link:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="signup-box">
    <div class="logo-icon"><i class="bi bi-building"></i></div>
    <h1>Create Account</h1>
    <p class="subtitle">Join Meryll Eatery today</p>

    <?php if ($error): ?>
        <div class="error-msg"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success-msg"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="signupForm">
        <div class="form-group">
            <label><i class="bi bi-person"></i> Full Name</label>
            <input type="text" name="username" required placeholder="Juan Dela Cruz" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
        </div>
        
        <div class="form-group">
            <label><i class="bi bi-envelope"></i> Email Address</label>
            <input type="email" name="email" required placeholder="juan@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>
        
        <div class="form-group">
            <label><i class="bi bi-card-list"></i> User Type</label>
            <select name="user_type" id="userType" required onchange="updateIDFormat()">
                <option value="student" <?= (isset($_POST['user_type']) && $_POST['user_type'] === 'student') ? 'selected' : '' ?>>Student</option>
                <option value="instructor" <?= (isset($_POST['user_type']) && $_POST['user_type'] === 'instructor') ? 'selected' : '' ?>>Instructor</option>
            </select>
        </div>
        
        <div class="form-group">
            <label><i class="bi bi-card-id"></i> ID Number</label>
            <input type="text" name="student_id" id="studentId" required placeholder="Enter your ID" value="<?= isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : '' ?>">
            <div id="idFormatInfo" class="id-format-info">
                <strong>Format:</strong> <span id="formatExample">E24-00123</span>
            </div>
        </div>
        
        <!-- ✅ PASSWORD WITH EYE TOGGLE -->
        <div class="form-group">
            <label><i class="bi bi-lock"></i> Password</label>
            <input type="password" name="password" id="password" required placeholder="Min. 8 chars, upper, lower, number, symbol" oninput="checkPasswordStrength()">
            <button type="button" class="toggle-password" onclick="toggleVisibility('password', this)">
                <i class="bi bi-eye"></i>
            </button>
            <div class="password-strength">
                <div class="password-strength-bar" id="strengthBar"></div>
            </div>
            <div class="password-rules">
                <div class="rule-item unmet" id="rule-length"><i class="bi bi-circle"></i> 8+ characters</div>
                <div class="rule-item unmet" id="rule-upper"><i class="bi bi-circle"></i> Uppercase (A-Z)</div>
                <div class="rule-item unmet" id="rule-lower"><i class="bi bi-circle"></i> Lowercase (a-z)</div>
                <div class="rule-item unmet" id="rule-number"><i class="bi bi-circle"></i> Number (0-9)</div>
                <div class="rule-item unmet" id="rule-special"><i class="bi bi-circle"></i> Special (!@#$...)</div>
                <div class="rule-item unmet" id="rule-match"><i class="bi bi-circle"></i> Matches confirm</div>
            </div>
        </div>
        
        <!-- ✅ CONFIRM PASSWORD WITH EYE TOGGLE -->
        <div class="form-group">
            <label><i class="bi bi-lock-fill"></i> Confirm Password</label>
            <input type="password" name="confirm_password" id="confirmPassword" required placeholder="Re-enter password" oninput="checkPasswordStrength()">
            <button type="button" class="toggle-password" onclick="toggleVisibility('confirmPassword', this)">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <button type="submit" class="btn-signup" id="submitBtn" disabled><i class="bi bi-person-plus"></i> Create Account</button>
    </form>

    <p style="margin-top: 1.5rem; color: #555;">
        Already have an account? <a href="login.php" class="login-link">Sign In</a>
    </p>
</div>

<script>
function updateIDFormat() {
    const userType = document.getElementById('userType').value;
    const formatInfo = document.getElementById('idFormatInfo');
    const formatExample = document.getElementById('formatExample');
    const studentIdInput = document.getElementById('studentId');
    
    formatInfo.classList.add('show');
    
    if (userType === 'instructor') {
        formatExample.textContent = 'TAG-123';
        studentIdInput.placeholder = 'TAG-123';
    } else {
        formatExample.textContent = 'E24-00123';
        studentIdInput.placeholder = 'E24-00123';
    }
}

// ✅ REAL-TIME PASSWORD STRENGTH CHECKER
function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirmPassword').value;
    const bar = document.getElementById('strengthBar');
    const submitBtn = document.getElementById('submitBtn');
    
    // Check individual rules
    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    const matches = password === confirm && password.length > 0;
    
    // Update rule indicators
    updateRule('rule-length', hasLength);
    updateRule('rule-upper', hasUpper);
    updateRule('rule-lower', hasLower);
    updateRule('rule-number', hasNumber);
    updateRule('rule-special', hasSpecial);
    updateRule('rule-match', matches);
    
    // Calculate strength
    let score = 0;
    if (hasLength) score++;
    if (hasUpper) score++;
    if (hasLower) score++;
    if (hasNumber) score++;
    if (hasSpecial) score++;
    if (matches) score++;
    
    // Update bar
    bar.className = 'password-strength-bar';
    if (score <= 2) bar.classList.add('strength-weak');
    else if (score <= 4) bar.classList.add('strength-fair');
    else if (score === 5) bar.classList.add('strength-good');
    else if (score === 6) bar.classList.add('strength-strong');
    
    // Enable/disable submit button
    submitBtn.disabled = !(hasLength && hasUpper && hasLower && hasNumber && hasSpecial && matches);
}

function updateRule(id, met) {
    const el = document.getElementById(id);
    const icon = el.querySelector('i');
    if (met) {
        el.classList.remove('unmet');
        el.classList.add('met');
        icon.className = 'bi bi-check-circle-fill';
    } else {
        el.classList.remove('met');
        el.classList.add('unmet');
        icon.className = 'bi bi-circle';
    }
}

// ✅ TOGGLE PASSWORD VISIBILITY
function toggleVisibility(inputId, btn) {
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

// Show format info on page load if there's a value
if (document.getElementById('studentId').value) {
    updateIDFormat();
}
</script>

</body>
</html>