<?php
session_start();
include '../connect.php';
include '../mail_config.php';

$notif = '';
$notifType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Look up the user by email (prepared statement - NFR-2)
    $stmt = $con->prepare("SELECT user_id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Generate a secure random token; store only its HASH in the DB
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        // ✅ FIX: Let MySQL compute the expiry so both sides use the same clock
        // (prevents PHP vs MySQL timezone mismatch = "instantly expired" bug)
        $upd = $con->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?");
        $upd->bind_param("si", $tokenHash, $user['user_id']);
        $upd->execute();

        // Build the reset link the user will click
        $link = BASE_URL . '/reset_password.php?token=' . $token;

        // Styled HTML email
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;border:1px solid #eee;border-radius:12px;overflow:hidden'>
            <div style='background:#8B0000;color:#fff;padding:20px;text-align:center'>
                <h2 style='margin:0'>Kuya Batas Canteen</h2>
            </div>
            <div style='padding:25px'>
                <p>Hi <b>" . htmlspecialchars($user['username']) . "</b>,</p>
                <p>We received a request to reset your password. Click the button below to set a new one. This link is valid for <b>1 hour</b>.</p>
                <p style='text-align:center;margin:25px 0'>
                    <a href='$link' style='background:#8B0000;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block'>Reset My Password</a>
                </p>
                <p style='color:#777;font-size:13px'>If the button doesn't work, copy and paste this link into your browser:<br><span style='color:#8B0000;word-break:break-all'>$link</span></p>
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0'>
                <p style='color:#999;font-size:12px'>If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
            </div>
        </div>";

        $sent = sendMail($email, 'Password Reset - Kuya Batas Canteen', $html);

        if ($sent['success']) {
            $notif = 'A password reset link was sent to your email. It expires in 1 hour.';
        } else {
            $notifType = 'error';
            $notif = 'Email failed to send: ' . $sent['message'];
        }
    } else {
        // Generic message to prevent account enumeration (NFR-2)
        $notif = 'If that email exists in our system, a reset link has been sent.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Kuya Batas Canteen</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);width:100%;max-width:420px;padding:2.5rem}
.logo{text-align:center;margin-bottom:1.5rem}
.logo i{font-size:2.5rem;color:#8B0000}
.logo h1{font-size:1.4rem;color:#8B0000;margin-top:.5rem}
.logo p{color:#6c757d;font-size:.9rem;margin-top:.3rem}
.form-group{margin-bottom:1.2rem}
.form-group label{display:block;font-weight:600;font-size:.85rem;margin-bottom:.4rem}
.form-group input{width:100%;padding:11px 14px;border:1px solid #dee2e6;border-radius:8px;font-size:.95rem;outline:none}
.form-group input:focus{border-color:#8B0000}
.btn{width:100%;padding:12px;background:#8B0000;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer}
.btn:hover{background:#6d0000}
.footer-link{text-align:center;margin-top:1.2rem;font-size:.9rem;color:#6c757d}
.footer-link a{color:#8B0000;text-decoration:none;font-weight:600}
.custom-notif{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:2rem 2.5rem;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);z-index:9999;text-align:center;min-width:320px;animation:popIn .3s ease}
.custom-notif.show{display:block}
.custom-notif.error{border-left:5px solid #e63946}
.custom-notif.success{border-left:5px solid #2ecc71}
.custom-notif .notif-icon{font-size:3rem;margin-bottom:.8rem}
.custom-notif.error .notif-icon{color:#e63946}
.custom-notif.success .notif-icon{color:#2ecc71}
.custom-notif .notif-title{font-size:1.1rem;font-weight:700;margin-bottom:.4rem}
.custom-notif .notif-msg{font-size:.9rem;color:#6c757d;margin-bottom:1.2rem}
.custom-notif .notif-btn{background:#8B0000;color:#fff;border:none;padding:8px 24px;border-radius:6px;cursor:pointer;font-weight:600}
.notif-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
.notif-overlay.show{display:block}
@keyframes popIn{from{opacity:0;transform:translate(-50%,-50%) scale(.8)}to{opacity:1;transform:translate(-50%,-50%) scale(1)}}
</style>
</head>
<body>
<div class="box">
    <div class="logo">
        <i class="bi bi-key"></i>
        <h1>Forgot Password</h1>
        <p>Enter your email and we'll send you a reset link</p>
    </div>
    <form method="POST">
        <div class="form-group">
            <label><i class="bi bi-envelope"></i> Email Address</label>
            <input type="email" name="email" required placeholder="student@school.edu">
        </div>
        <button type="submit" class="btn"><i class="bi bi-send"></i> Send Reset Link</button>
    </form>
    <div class="footer-link"><a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a></div>
</div>

<div class="notif-overlay" id="notifOverlay"></div>
<div class="custom-notif" id="customNotif">
    <div class="notif-icon" id="notifIcon"></div>
    <div class="notif-title" id="notifTitle"></div>
    <div class="notif-msg" id="notifMsg"></div>
    <button class="notif-btn" onclick="closeNotif()">OK</button>
</div>

<script>
function showNotif(type,title,msg){
    const n=document.getElementById('customNotif');
    n.className='custom-notif show '+type;
    document.getElementById('notifOverlay').classList.add('show');
    document.getElementById('notifIcon').innerHTML = type==='error' ? '<i class="bi bi-exclamation-circle"></i>' : '<i class="bi bi-check-circle"></i>';
    document.getElementById('notifTitle').textContent=title;
    document.getElementById('notifMsg').textContent=msg;
}
function closeNotif(){
    document.getElementById('customNotif').classList.remove('show');
    document.getElementById('notifOverlay').classList.remove('show');
}
<?php if($notif): ?>
showNotif('<?=$notifType?>','<?=$notifType==='error'?'Email Failed':'Check Your Email'?>','<?=addslashes($notif)?>');
<?php endif; ?>
</script>
</body>
</html>