<?php
// Base URL from Render Environment Variables
define('BASE_URL', trim(getenv('BASE_URL')) ?: 'https://meryll-eatery.onrender.com/user');

// ✅ SECURE: Brevo API Key (HTTPS, works on Render free tier)
define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: 'placeholder_api_key');
define('BREVO_FROM_EMAIL', getenv('BREVO_FROM_EMAIL') ?: 'your-email@gmail.com');
define('BREVO_FROM_NAME', 'Meryll Eatery');

function sendMail($toEmail, $subject, $htmlBody) {
    $url = 'https://api.brevo.com/v3/smtp/email';
    
    $data = [
        'sender' => [
            'name' => BREVO_FROM_NAME,
            'email' => BREVO_FROM_EMAIL
        ],
        'to' => [
            ['email' => $toEmail]
        ],
        'subject' => $subject,
        'htmlContent' => $htmlBody
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . BREVO_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Brevo API Error: HTTP ' . $httpCode . ($error ? ' - ' . $error : '')];
    }
}
?>