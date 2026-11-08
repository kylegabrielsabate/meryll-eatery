<?php
// ✅ PHPMailer Classes (Must be at the top!)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Base URL from Render Environment Variables
define('BASE_URL', trim(getenv('BASE_URL')) ?: 'https://meryll-eatery.onrender.com/user');

// ✅ SECURE: Read from Environment Variables
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', getenv('SMTP_USER') ?: 'placeholder@gmail.com'); 
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'placeholder_app_password');      
define('SMTP_FROM_NAME', 'Meryll Eatery');

function sendMail($toEmail, $subject, $htmlBody) {
    // Load Composer autoloader
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Mailer Error: ' . $mail->ErrorInfo];
    }
}
?>