<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Load PHPMailer ---
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    require __DIR__ . '/phpmailer/src/Exception.php';
    require __DIR__ . '/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/phpmailer/src/SMTP.php';
}

// ============================================================
//  Environment Variables (Render will override these)
// ============================================================
define('SMTP_USER', getenv('SMTP_USER') ?: 'kuya.batas28@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'xvxirflqxnxssyut'); // Update locally if needed
define('BASE_URL',  getenv('BASE_URL')  ?: 'http://localhost/kuyabatas/user');

// ============================================================
//  Reusable email-sending function
// ============================================================
function sendMail($toEmail, $subject, $htmlBody) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Timeout = 15; // Fail after 15 seconds instead of hanging forever
        
        // ✅ CRITICAL FIX 1: Force IPv4 to bypass Docker IPv6 routing issues
        $mail->Host = gethostbyname('smtp.gmail.com'); 
        
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS; 
        
        // ✅ CRITICAL FIX 2: Use Port 465 with SMTPS (SSL). 
        // This is much more reliable on Render than Port 587/STARTTLS.
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom(SMTP_USER, 'Meryll Eatery');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return ['success' => true];
        } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>