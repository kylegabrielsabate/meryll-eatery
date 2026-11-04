<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Load PHPMailer (auto-detects your Composer install) ---
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback for manual install (in case you ever switch)
    require __DIR__ . '/phpmailer/src/Exception.php';
    require __DIR__ . '/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/phpmailer/src/SMTP.php';
}

// ============================================================
//  ⚠️ ENVIRONMENT VARIABLES FOR CLOUD DEPLOYMENT (RENDER) ⚠️
//  Falls back to your local credentials if not set in the cloud
// ============================================================
define('SMTP_USER', getenv('SMTP_USER') ?: 'kuya.batas28@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'bjip tybd hfxy dwuh'); 
define('BASE_URL',  getenv('BASE_URL')  ?: 'http://localhost/kuyabatas/user');

// ============================================================
//  Reusable email-sending function
// ============================================================
function sendMail($toEmail, $subject, $htmlBody) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(SMTP_USER, 'Kuya Batas Canteen');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>