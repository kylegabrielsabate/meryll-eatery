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
    $student_id = trim($_POST['student_id'] ?? '');
    $user_type = $_POST['user_type'] ?? 'student'; // 'student' or 'instructor'
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($student_id)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // ✅ Validate ID format based on user type
        if ($user_type === 'instructor') {
            // Instructor ID format: TAG-123
            if (!preg_match('/^TAG-\d{2,4}$/i', $student_id)) {
                $error = 'Instructor ID must be in format: TAG-123';
            }
        } else {
            // Student ID format: E24-00123 or similar
            if (!preg_match('/^[A-Z]\d{2}-\d{5,}$/i', $student_id)) {
                $error = 'Student ID must be in format: E24-00123';
            }
        }
        
        if (empty($error)) {
            // Check if email or ID already exists
            $stmt = $con->prepare("SELECT user_id FROM users WHERE email = ? OR student_id = ?");
            $stmt->bind_param("ss", $email, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email or ID already registered.';
            } else {
                // Create account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'user'; // Default role
                
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
    max-width: 450px;
    padding: 2.5rem 2rem;
    text-align: center;
}
.logo-icon { font-size: 3rem; color: #8B0000; margin-bottom: 0.5rem; }
.signup-box h1 { color: #8B0000; font-size: 1.8rem; font-weight: 700; margin-bottom: 0.3rem; }
.signup-box .subtitle { color: #6c757d; font-size: 0.95rem; margin-bottom: 2rem; }

.form-group { margin-bottom: 1.2rem; text-align: left; }
.form-group label { display: block; font-weight: 600; font-size: 0.9rem; color: #333; margin-bottom: 0.4rem; }
.form-group input, .form-group select {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    font-size: 1rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s;
}
.form-group input:focus, .form-group select:focus { 
    border-color: #8B0000; 
    box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1); 
}

.id-format-info {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
    padding: 10px;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-top: 8px;
    display: none;
}
.id-format-info.show { display: block; }

.btn-signup {
    width: 100%; padding: 14px; background: #8B0000; color: #fff; border: none;
    border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer;
    margin-top: 0.5rem; transition: background 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-signup:hover { background: #6d0000; }

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

    <form method="POST">
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
        
        <div class="form-group">
            <label><i class="bi bi-lock"></i> Password</label>
            <input type="password" name="password" required placeholder="Min. 6 characters">
        </div>
        
        <div class="form-group">
            <label><i class="bi bi-lock-fill"></i> Confirm Password</label>
            <input type="password" name="confirm_password" required placeholder="Re-enter password">
        </div>

        <button type="submit" class="btn-signup"><i class="bi bi-person-plus"></i> Create Account</button>
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

// Show format info on page load if there's a value
if (document.getElementById('studentId').value) {
    updateIDFormat();
}
</script>

</body>
</html>