<?php
// Get API key and Base URL from Render Environment Variables
// NOTE: The placeholder ensures GitHub never blocks your push again!
define('RESEND_API_KEY', trim(getenv('RESEND_API_KEY')) ?: 're_test_key_placeholder');
define('BASE_URL', trim(getenv('BASE_URL')) ?: 'http://localhost/kuyabatas/user');

function sendMail($toEmail, $subject, $htmlBody) {
    $url = 'https://api.resend.com/emails';
    
    $data = [
        'from' => 'Meryll Eatery <onboarding@resend.dev>', 
        'to' => [$toEmail],
        'subject' => $subject,
        'html' => $htmlBody
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . RESEND_API_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Resend API Error: HTTP ' . $httpCode];
    }
}
?>