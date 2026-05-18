<?php
// email-config.php
class EmailConfig {
    // SMTP Configuration
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = '242018love@gmail.com'; // Change this
    const SMTP_PASSWORD = 'latv cexi uipy czxt'; // Use App Password, not regular password
    const SMTP_FROM_EMAIL = 'noreply@plants.com';
    const SMTP_FROM_NAME = 'Plants Support';
    
    // OTP Configuration
    const OTP_LENGTH = 6;
    const OTP_EXPIRY_MINUTES = 10;
    
    // Rate limiting
    const MAX_OTP_REQUESTS_PER_HOUR = 3;
    const MAX_OTP_ATTEMPTS = 3;
    const MAX_ANSWER_ATTEMPTS = 3;
    
    // Lockout times (in seconds)
    const OTP_LOCKOUT_TIME = 900; // 15 minutes
    const ANSWER_LOCKOUT_TIME = 1800; // 30 minutes
    const RESET_LOCKOUT_TIME = 3600; // 60 minutes
    
    public static function sendOTP($toEmail, $otp, $username) {
        try {
            // Create PHPMailer instance
            require_once '../vendor/autoload.php'; // If using Composer
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host = self::SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = self::SMTP_USERNAME;
            $mail->Password = self::SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = self::SMTP_PORT;
            
            // Recipients
            $mail->setFrom(self::SMTP_FROM_EMAIL, self::SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $username);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP - Plants';
            
            $htmlContent = self::getOTPEmailTemplate($username, $otp);
            $plainContent = self::getPlainOTPEmail($username, $otp);
            
            $mail->Body = $htmlContent;
            $mail->AltBody = $plainContent;
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private static function getOTPEmailTemplate($username, $otp) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 10px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .otp-code { 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #4CAF50;
                    text-align: center;
                    letter-spacing: 5px;
                    margin: 20px 0;
                    padding: 15px;
                    background-color: #fff;
                    border: 2px dashed #4CAF50;
                    border-radius: 5px;
                }
                .footer { 
                    margin-top: 20px; 
                    padding-top: 10px; 
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #666;
                }
                .warning { 
                    background-color: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    color: #856404;
                    padding: 10px;
                    border-radius: 5px;
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Plants - Password Reset</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$username},</h2>
                    <p>You've requested to reset your password for your Plants account.</p>
                    <p>Please use the following One-Time Password (OTP) to verify your identity:</p>
                    
                    <div class='otp-code'>{$otp}</div>
                    
                    <p>This OTP is valid for " . self::OTP_EXPIRY_MINUTES . " minutes only.</p>
                    
                    <div class='warning'>
                        <strong>Important:</strong>
                        <ul>
                            <li>Never share this OTP with anyone</li>
                            <li>If you didn't request this password reset, please ignore this email</li>
                            <li>For security reasons, this OTP will expire in " . self::OTP_EXPIRY_MINUTES . " minutes</li>
                        </ul>
                    </div>
                    
                    <p>If you're having trouble, please contact our support team.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Plants. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private static function getPlainOTPEmail($username, $otp) {
        return "Password Reset Request - Plants\n\n" .
               "Hello {$username},\n\n" .
               "You've requested to reset your password for your Plants account.\n\n" .
               "Your OTP Code: {$otp}\n\n" .
               "This OTP is valid for " . self::OTP_EXPIRY_MINUTES . " minutes only.\n\n" .
               "If you didn't request this, please ignore this email.\n\n" .
               "This is an automated message, please do not reply.\n";
    }

    public static function sendRegistrationStatusEmail($toEmail, $username, $status, $reason = '') {
        try {
            require_once '../vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = self::SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = self::SMTP_USERNAME;
            $mail->Password = self::SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = self::SMTP_PORT;
            
            $mail->setFrom(self::SMTP_FROM_EMAIL, self::SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $username);
            $mail->isHTML(true);
            
            if ($status === 'accepted') {
                $mail->Subject = 'Registration Approved - Plants';
                $htmlContent = self::getRegistrationAcceptedTemplate($username);
                $plainContent = "Hello {$username},\n\nYour registration has been approved. You can now log in.\n";
            } else {
                $mail->Subject = 'Registration Rejected - Plants';
                $htmlContent = self::getRegistrationRejectedTemplate($username, $reason);
                $plainContent = "Hello {$username},\n\nYour registration has been rejected.\nReason: {$reason}\n";
            }
            
            $mail->Body = $htmlContent;
            $mail->AltBody = $plainContent;
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("Registration status email failed: " . $e->getMessage());
            return false;
        }
    }

    public static function sendWelcomeEmail($toEmail, $firstName, $username, $password) {
        try {
            require_once '../vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = self::SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = self::SMTP_USERNAME;
            $mail->Password = self::SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = self::SMTP_PORT;
            
            $mail->setFrom(self::SMTP_FROM_EMAIL, self::SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $firstName);
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to Plants - Account Created';
            
            $htmlContent = self::getWelcomeEmailTemplate($firstName, $username, $password);
            $plainContent = "Hello {$firstName},\n\nYour account has been created by an Administrator.\n\nUsername: {$username}\nTemporary Password: {$password}\n\nPlease log in and change your password immediately.\n";
            
            $mail->Body = $htmlContent;
            $mail->AltBody = $plainContent;
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("Welcome email failed: " . $e->getMessage());
            return false;
        }
    }

    private static function getWelcomeEmailTemplate($firstName, $username, $password) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2a6e3b; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 30px; background-color: #f9f9f9; border: 1px solid #eee; border-top: none; }
                .credentials-box { background-color: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .credential-item { margin-bottom: 10px; }
                .label { font-weight: bold; color: #666; width: 100px; display: inline-block; }
                .value { font-family: monospace; font-size: 1.1rem; color: #2a6e3b; font-weight: bold; }
                .btn { display: inline-block; padding: 12px 25px; background-color: #2a6e3b; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { margin-top: 20px; font-size: 12px; color: #888; text-align: center; }
                .warning { color: #856404; background-color: #fff3cd; padding: 15px; border-radius: 5px; font-size: 0.9rem; margin-top: 20px; border: 1px solid #ffeeba; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Plants.&#127804;</h1>
                </div>
                <div class='content'>
                    <h2>Welcome, {$firstName}!</h2>
                    <p>An administrator has created an account for you in the Plants System. You can now access the platform using the credentials below:</p>
                    
                    <div class='credentials-box'>
                        <div class='credential-item'>
                            <span class='label'>Username:</span>
                            <span class='value'>{$username}</span>
                        </div>
                        <div class='credential-item'>
                            <span class='label'>Password:</span>
                            <span class='value'>{$password}</span>
                        </div>
                    </div>

                    <div class='warning'>
                        <strong>Security Reminder:</strong> For your security, please log in and change this temporary password immediately. You should also set up your security questions for account recovery.
                    </div>

                    <p style='text-align: center;'>
                        <a href='http://" . $_SERVER['HTTP_HOST'] . "/solayao107/php/complete-setup.php' class='btn'>Complete Your Account Setup</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply.</p>
                    <p>&copy; " . date('Y') . " Plants System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function getRegistrationAcceptedTemplate($username) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2a6e3b; color: white; padding: 10px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .success-box { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; text-align: center; margin: 20px 0; border: 1px solid #c3e6cb; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Plants - Registration Approved</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$username},</h2>
                    <div class='success-box'>
                        <h3>Congratulations!</h3>
                        <p>Your registration for the Plants system has been approved by the Administrator.</p>
                    </div>
                    <p>You can now log in to your account.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function getRegistrationRejectedTemplate($username, $reason) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #dc3545; color: white; padding: 10px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .error-box { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Plants - Registration Rejected</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$username},</h2>
                    <p>We are sorry to inform you that your registration for the Plants system has been rejected.</p>
                    <div class='error-box'>
                        <strong>Reason:</strong> " . (!empty($reason) ? htmlspecialchars($reason) : "Did not meet registration requirements.") . "
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>