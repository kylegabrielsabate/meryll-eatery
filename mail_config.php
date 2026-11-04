<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    require __DIR__ . '/phpmailer/src/Exception.php';
    require __DIR__ . '/phpmailer/src/PHPMailer.php';
    require __DIR__ . '/phpmailer/src/SMTP.php';
}

// ✅ TRIM() removes ALL invisible spaces/newlines from copy-pasting
define('SMTP_USER', trim(getenv('SMTP_USER')) ?: 'kuya.batas28@gmail.com');
define('SMTP_PASS', trim(getenv('SMTP_PASS')) ?: 'xvxirflqxnxssyut'); 
define('BASE_URL',  trim(getenv('BASE_URL'))  ?: 'http://localhost/kuyabatas/user');

function sendMail($toEmail, $subject, $htmlBody) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Timeout = 15; 
        
        // Try standard Gmail with explicit SSL
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Explicit SSL
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