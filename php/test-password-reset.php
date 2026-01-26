<?php
// test-password-reset.php
require_once 'db_connection.php';
require_once 'email-config.php';

echo "<h1>Testing Complete Password Reset System</h1>";

// Test 1: Check if user exists
echo "<h2>1. Checking Test User</h2>";
try {
    $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => 'solayaoflorence@gmail.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p style='color: green;'>✓ User found: " . htmlspecialchars($user['username']) . " (" . htmlspecialchars($user['email']) . ")</p>";
        
        // Test 2: Generate OTP
        echo "<h2>2. Testing OTP Generation and Email</h2>";
        $otp = 'TEST' . rand(100, 999);
        
        echo "<p>Generated OTP: <strong>$otp</strong></p>";
        
        // Test 3: Send OTP email
        $result = EmailConfig::sendOTP($user['email'], $otp, $user['username']);
        
        if ($result) {
            echo "<p style='color: green; font-weight: bold;'>✓ OTP email sent successfully!</p>";
            echo "<p>Check " . htmlspecialchars($user['email']) . " for the test email.</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ Failed to send OTP email</p>";
            echo "<p>Check PHP error logs for details.</p>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠ Test user not found. Using default test.</p>";
        
        // Test with hardcoded values
        $test_email = 'solayaoflorence@gmail.com';
        $test_username = 'TestUser';
        $test_otp = 'TEST123';
        
        echo "<h2>2. Testing Email with Hardcoded Values</h2>";
        echo "<p>Email: $test_email</p>";
        echo "<p>OTP: $test_otp</p>";
        
        $result = EmailConfig::sendOTP($test_email, $test_otp, $test_username);
        
        if ($result) {
            echo "<p style='color: green; font-weight: bold;'>✓ Test email sent successfully!</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ Test email failed</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Check email configuration
echo "<h2>3. Email Configuration Check</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
echo "SMTP Host: " . EmailConfig::SMTP_HOST . "\n";
echo "SMTP Port: " . EmailConfig::SMTP_PORT . "\n";
echo "SMTP Username: " . EmailConfig::SMTP_USERNAME . "\n";
echo "SMTP Password: " . (strlen(EmailConfig::SMTP_PASSWORD) > 0 ? "***SET***" : "NOT SET") . "\n";
echo "From Email: " . EmailConfig::SMTP_FROM_EMAIL . "\n";
echo "From Name: " . EmailConfig::SMTP_FROM_NAME . "\n";
echo "</pre>";

// Check PHPMailer installation
echo "<h2>4. PHPMailer Check</h2>";
$autoload_paths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php'
];

$found = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        echo "<p style='color: green;'>✓ PHPMailer found at: " . htmlspecialchars($path) . "</p>";
        $found = true;
        break;
    }
}

if (!$found) {
    echo "<p style='color: red;'>✗ PHPMailer not found!</p>";
    echo "<p>Install with: <code>composer require phpmailer/phpmailer</code></p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Delete test-email.php (contains password)</li>";
echo "<li>Test the forgot password page at: <a href='../html/forgot-password.html'>forgot-password.html</a></li>";
echo "<li>Check spam folder for OTP emails</li>";
echo "<li>Enable sockets extension in php.ini</li>";
echo "</ol>";
?>