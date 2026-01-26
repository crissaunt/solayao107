<?php
// test-email.php
session_start();

// SMTP Configuration
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_username = '242018love@gmail.com'; // Your Gmail
$smtp_password = 'latv cexi uipy czxt'; // Use App Password
$from_email = 'test@plants.com';
$from_name = 'Plants Test';

// Recipient
$to_email = 'msteramigo24@gmail.com'; // Change to your test email
$subject = 'Test Email from Plants System';
$message = 'This is a test email sent from the Plants password reset system.';

// Simple email test without PHPMailer
function sendSimpleEmail($to, $subject, $message) {
    $headers = "From: Plants Test <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Test with PHP mail() function
echo "<h2>Testing Email System</h2>";
echo "<p>Sending test email to: $to_email</p>";

if (sendSimpleEmail($to_email, $subject, $message)) {
    echo "<p style='color: green;'><strong>✓ Email sent successfully!</strong></p>";
    echo "<p>Check your inbox (and spam folder) for the test email.</p>";
} else {
    echo "<p style='color: red;'><strong>✗ Email failed to send.</strong></p>";
    echo "<p>Check PHP error logs for more information.</p>";
}

// Test with PHPMailer (if installed)
echo "<hr><h3>Testing with PHPMailer</h3>";

if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_port;
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'PHPMailer Test - ' . $subject;
        $mail->Body = '<h3>PHPMailer Test</h3>' . $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            echo "<p style='color: green;'>✓ PHPMailer email sent successfully!</p>";
        } else {
            echo "<p style='color: red;'>✗ PHPMailer failed: " . $mail->ErrorInfo . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ PHPMailer Exception: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>PHPMailer not installed. Install via: <code>composer require phpmailer/phpmailer</code></p>";
}

// Test SMTP connection
echo "<hr><h3>Testing SMTP Connection</h3>";

$connection = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);

if ($connection) {
    echo "<p style='color: green;'>✓ SMTP Connection successful to $smtp_host:$smtp_port</p>";
    fclose($connection);
} else {
    echo "<p style='color: red;'>✗ SMTP Connection failed: $errstr ($errno)</p>";
}

// Show PHP mail configuration
echo "<hr><h3>PHP Mail Configuration</h3>";
echo "<pre>";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "sendmail_from: " . ini_get('sendmail_from') . "\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "</pre>";

// Show server information
echo "<hr><h3>Server Information</h3>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server OS: " . php_uname() . "\n";
echo "Loaded Extensions: " . implode(', ', get_loaded_extensions()) . "\n";
echo "</pre>";
?>