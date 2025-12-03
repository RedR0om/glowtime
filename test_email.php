<?php
/**
 * Email Test Script
 * Use this to test if email is working correctly
 */

require_once 'inc/bootstrap.php';

// Test email configuration
echo "<h2>Email Configuration Test</h2>";
echo "<pre>";

echo "EMAIL_ENABLED: " . (defined('EMAIL_ENABLED') ? EMAIL_ENABLED : 'NOT DEFINED') . "\n";
echo "EMAIL_METHOD: " . (defined('EMAIL_METHOD') ? EMAIL_METHOD : 'NOT DEFINED') . "\n";
echo "SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT DEFINED') . "\n";
echo "SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NOT DEFINED') . "\n";
echo "SMTP_USERNAME: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NOT DEFINED') . "\n";
echo "SMTP_PASSWORD: " . (defined('SMTP_PASSWORD') ? (strlen(SMTP_PASSWORD) > 0 ? '***SET***' : 'EMPTY') : 'NOT DEFINED') . "\n";
echo "SMTP_FROM_EMAIL: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'NOT DEFINED') . "\n";
echo "SMTP_FROM_NAME: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NOT DEFINED') . "\n";
echo "SMTP_SECURE: " . (defined('SMTP_SECURE') ? SMTP_SECURE : 'NOT DEFINED') . "\n";

echo "\n--- PHPMailer Check ---\n";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "✓ PHPMailer is available\n";
} else {
    echo "✗ PHPMailer NOT found\n";
    echo "  Run: composer require phpmailer/phpmailer\n";
}

echo "\n--- Testing Email Send ---\n";

// Get test email from GET parameter or use default
$testEmail = $_GET['email'] ?? 'accuracyarcher27@gmail.com';

echo "Sending test email to: {$testEmail}\n";
echo "This may take a few seconds...\n\n";

$testMessage = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #e91e63; color: white; padding: 20px; text-align: center; }
        .content { background: #f8f9fa; padding: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Test Email from Glowtime Salon</h2>
        </div>
        <div class='content'>
            <p>This is a test email to verify that the email system is working correctly.</p>
            <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p>If you received this email, the configuration is working! ✅</p>
        </div>
    </div>
</body>
</html>
";

// Send email with debug enabled
$result = sendEmail(
    $testEmail,
    "Test Email - Glowtime Salon Email System",
    $testMessage,
    true // Enable debug mode
);

echo "\n--- Result ---\n";
if ($result['success']) {
    echo "✓ Email sent successfully!\n";
    echo "Check your inbox (and spam folder) at: {$testEmail}\n";
} else {
    echo "✗ Email failed to send!\n";
    echo "Error: " . $result['error'] . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check PHP error logs\n";
    echo "2. Verify Gmail App Password is correct\n";
    echo "3. Make sure 2-Step Verification is enabled on Gmail\n";
    echo "4. Check if SMTP credentials are correct\n";
}

echo "\n--- PHP Error Log Location ---\n";
$errorLog = ini_get('error_log');
if ($errorLog) {
    echo "Error log: {$errorLog}\n";
} else {
    echo "Error log: Check php.ini for error_log setting\n";
    echo "Common locations:\n";
    echo "  - XAMPP: C:\\xampp\\php\\logs\\php_error_log\n";
    echo "  - XAMPP: C:\\xampp\\apache\\logs\\error.log\n";
}

echo "</pre>";

echo "<hr>";
echo "<p><strong>Usage:</strong> Add ?email=your-email@example.com to test with a different email</p>";
echo "<p><a href='?email=accuracyarcher27@gmail.com'>Test Again</a></p>";
?>

