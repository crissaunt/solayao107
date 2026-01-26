<?php
// forgot-password.php
require_once 'db_connection.php';
require_once 'email-config.php';
require_once 'reset-logic.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => '', 'demo_otp' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If JSON decode fails, try POST data
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    
    if (empty($data['email'])) {
        $response['message'] = 'Email is required';
        echo json_encode($response);
        exit();
    }
    
    $email = trim($data['email']);
    $isResend = !empty($data['resend']);
    
    try {
        $resetLogic = new PasswordReset($conn);
        
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Check if we should tell user email doesn't exist
            // For better UX, we can be specific
            $response['message'] = 'The email address "' . htmlspecialchars($email) . '" is not registered in our system.';
            $response['email_not_found'] = true;
            echo json_encode($response);
            exit();
        }
        
        // Check if user has security questions
        $checkQuestionsStmt = $conn->prepare("
            SELECT COUNT(*) as question_count 
            FROM user_security_answers 
            WHERE user_id = :user_id
        ");
        $checkQuestionsStmt->execute([':user_id' => $user['user_id']]);
        $questionResult = $checkQuestionsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($questionResult['question_count'] < 3) {
            $response['message'] = 'Your account is not set up for password reset. Please contact support.';
            echo json_encode($response);
            exit();
        }
        
        // Check rate limiting using the correct table
        $rateLimitStmt = $conn->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM password_reset_logs 
            WHERE email = :email 
            AND attempt_type = 'otp_request'
            AND attempt_time > NOW() - INTERVAL '1 hour'
        ");
        $rateLimitStmt->execute([':email' => $email]);
        $rateResult = $rateLimitStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rateResult['attempt_count'] >= EmailConfig::MAX_OTP_REQUESTS_PER_HOUR) {
            $response['message'] = 'Too many password reset attempts. Please try again in an hour.';
            echo json_encode($response);
            exit();
        }
        
        // Create reset session and generate OTP
        $sessionResult = $resetLogic->createResetSession($email, $user['user_id']);
        
        if ($sessionResult['success']) {
            // Calculate and return remaining time
            $remainingStmt = $conn->prepare("
                SELECT EXTRACT(EPOCH FROM (otp_expiry - NOW())) as remaining_seconds
                FROM password_reset_sessions 
                WHERE session_id = :session_id
            ");
            $remainingStmt->execute([':session_id' => $sessionResult['session_id']]);
            $remainingResult = $remainingStmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = 'OTP sent to ' . $user['email'];
            $response['session_id'] = $sessionResult['session_id'];
            $response['remaining_seconds'] = floor($remainingResult['remaining_seconds']);
            $response['demo_otp'] = $sessionResult['otp'];


            // Send OTP email using EmailConfig
            error_log("Sending OTP email to: " . $user['email']);
            
            $emailSent = EmailConfig::sendOTP(
                $user['email'],
                $sessionResult['otp'],
                $user['username']
            );
            
            if ($emailSent) {
                $response['success'] = true;
                $response['message'] = 'OTP sent to ' . $user['email'];
                $response['session_id'] = $sessionResult['session_id'];
                // For demo/testing only - remove in production
                $response['demo_otp'] = $sessionResult['otp'];
            } else {
                $response['message'] = 'Failed to send OTP email. Please try again or contact support.';
                error_log("Failed to send OTP email to: " . $user['email']);
            }
        } else {
            $response['message'] = $sessionResult['message'];
        }
        
    } catch (PDOException $e) {
        error_log("Forgot password error: " . $e->getMessage());
        $response['message'] = 'A system error occurred. Please try again later.';
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>