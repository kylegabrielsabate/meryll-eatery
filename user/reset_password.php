<?php
session_start();
include '../connect.php';

$token     = $_GET['token'] ?? ($_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$valid     = false;
$user      = null;

// Verify token exists in DB and has NOT expired (prepared statement - NFR-2)
$stmt = $con->prepare("SELECT user_id, username, email FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 1) { $valid = true; $user = $res->fetch_assoc(); }

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/',$password) || !preg_match('/[0-9]/',$password) || !preg_match('/[^A-Za-z0-9]/',$password)) {
        $error = 'Password must be 8+ characters with an uppercase letter, a number, and a special character.';
    } else {
        // Hash new password, then DESTROY the token so it can't be reused
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $upd = $con->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
        $upd->bind_param("si", $hashed, $user['user_id']);
        $upd->execute();
        $success = 'Password changed successfully! Redirecting to login...';
        $valid = false; // token consumed
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);width:100%;max-width:440px;padding:2rem}
.logo{text-align:center;margin-bottom:1.2rem}
.logo i{font-size:2.2rem;color:#8B0000}
.logo h1{font-size:1.3rem;color:#8B0000;margin-top:.4rem}
.form-group{margin-bottom:.9rem;position:relative}
.form-group label{display:block;font-weight:600;font-size:.8rem;margin-bottom:.3rem}
.form-group label .req{color:#e63946}
.input-wrapper{position:relative}
.form-group input{width:100%;padding:10px 38px 10px 12px;border:1px solid #dee2e6;border-radius:8px;font-size:.9rem;outline:none}
.form-group input:focus{border-color:#8B0000}
.form-group input.invalid{border-color:#e63946}
.form-group input.valid{border-color:#2ecc71}
.toggle-password{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6c757d}
.policy-box{background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:.5rem .7rem;margin-top:.3rem;font-size:.7rem}
.policy-item{display:flex;align-items:center;gap:5px;color:#6c757d;margin-bottom:2px}
.policy-item.met{color:#2ecc71}
.btn{width:100%;padding:12px;background:#8B0000;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;margin-top:.5rem}
.btn:hover{background:#6d0000}
.expired{text-align:center;padding:2rem 0;color:#6c757d}
.expired i{font-size:3rem;color:#e63946;margin-bottom:1rem;display:block}
.expired a{color:#8B0000;font-weight:600;text-decoration:none}
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
<?php if (!$valid && !$success): ?>
    <!-- Invalid / expired / already-used link -->
    <div class="expired">
        <i class="bi bi-shield-exclamation"></i>
        <h2>Link Invalid or Expired</h2>
        <p style="margin:.8rem 0 1.5rem">This reset link is no longer valid, has expired (1-hour limit), or was already used.</p>
        <a href="forgot.php">Request a new link</a>
    </div>
<?php else: ?>
    <div class="logo">
        <i class="bi bi-shield-lock"></i>
        <h1>Set New Password</h1>
        <p style="color:#6c757d;font-size:.85rem;margin-top:.3rem">for <?=htmlspecialchars($user['email'] ?? '')?></p>
    </div>
    <form method="POST" action="reset_password.php?token=<?=htmlspecialchars($token)?>">
        <div class="form-group">
            <label>New Password <span class="req">*</span></label>
            <div class="input-wrapper">
                <input type="password" id="pw" name="password" required placeholder="Min. 8 characters">
                <button type="button" class="toggle-password" onclick="togglePass('pw',this)"><i class="bi bi-eye"></i></button>
            </div>
            <div class="policy-box">
                <div class="policy-item" id="p-len"><i class="bi bi-circle"></i> At least 8 characters</div>
                <div class="policy-item" id="p-up"><i class="bi bi-circle"></i> One uppercase letter (A-Z)</div>
                <div class="policy-item" id="p-num"><i class="bi bi-circle"></i> One number (0-9)</div>
                <div class="policy-item" id="p-sp"><i class="bi bi-circle"></i> One special character (!@#$...)</div>
            </div>
        </div>
        <div class="form-group">
            <label>Confirm Password <span class="req">*</span></label>
            <div class="input-wrapper">
                <input type="password" id="cpw" name="confirm_password" required placeholder="Re-enter password">
                <button type="button" class="toggle-password" onclick="togglePass('cpw',this)"><i class="bi bi-eye"></i></button>
            </div>
        </div>
        <button type="submit" class="btn"><i class="bi bi-check-circle"></i> Change Password</button>
    </form>
<?php endif; ?>
</div>

<div class="notif-overlay" id="notifOverlay"></div>
<div class="custom-notif" id="customNotif">
    <div class="notif-icon" id="notifIcon"></div>
    <div class="notif-title" id="notifTitle"></div>
    <div class="notif-msg" id="notifMsg"></div>
    <button class="notif-btn" onclick="closeNotif()">OK</button>
</div>

<script>
function togglePass(id,btn){
    const i=document.getElementById(id),ic=btn.querySelector('i');
    if(i.type==='password'){i.type='text';ic.className='bi bi-eye-slash'}else{i.type='password';ic.className='bi bi-eye'}
}
function showNotif(type,title,msg,redirect){
    const n=document.getElementById('customNotif');
    n.className='custom-notif show '+type;
    document.getElementById('notifOverlay').classList.add('show');
    document.getElementById('notifIcon').innerHTML = type==='error' ? '<i class="bi bi-exclamation-circle"></i>' : '<i class="bi bi-check-circle"></i>';
    document.getElementById('notifTitle').textContent=title;
    document.getElementById('notifMsg').textContent=msg;
    if(redirect){ setTimeout(()=>{window.location.href='login.php'},2500); }
}
function closeNotif(){
    document.getElementById('customNotif').classList.remove('show');
    document.getElementById('notifOverlay').classList.remove('show');
}
function checkPolicy(id,met){
    const el=document.getElementById(id),ic=el.querySelector('i');
    el.classList.toggle('met',met);
    ic.className = met ? 'bi bi-check-circle-fill' : 'bi bi-circle';
}
const pw=document.getElementById('pw'),cpw=document.getElementById('cpw');
if(pw){
    pw.addEventListener('input',()=>{
        const v=pw.value;
        checkPolicy('p-len',v.length>=8);
        checkPolicy('p-up',/[A-Z]/.test(v));
        checkPolicy('p-num',/[0-9]/.test(v));
        checkPolicy('p-sp',/[^A-Za-z0-9]/.test(v));
        matchCheck();
    });
    cpw.addEventListener('input',matchCheck);
}
function matchCheck(){
    if(!cpw.value){cpw.classList.remove('valid','invalid');return}
    cpw.classList.toggle('valid',pw.value===cpw.value);
    cpw.classList.toggle('invalid',pw.value!==cpw.value);
}
<?php if($error): ?>showNotif('error','Error','<?=addslashes($error)?>');<?php endif; ?>
<?php if($success): ?>showNotif('success','Success!','<?=addslashes($success)?>',true);<?php endif; ?>
</script>
</body>
</html>