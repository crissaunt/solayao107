<?php
// verify-answer.php
require_once 'db_connection.php';
require_once 'reset-logic.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => '', 'lock_time' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['user_id']) || empty($data['answers'])) {
        $response['message'] = 'All security answers are required';
        echo json_encode($response);
        exit();
    }
    
    $userId = intval($data['user_id']);
    $answers = $data['answers'];
    
    try {
        $resetLogic = new PasswordReset($conn);
        
        // Check if user is currently locked out
        $lockCheckStmt = $conn->prepare("
            SELECT lockout_until 
            FROM password_reset_logs 
            WHERE user_id = :user_id 
            AND attempt_type = 'security_answer'
            AND lockout_until > NOW()
            ORDER BY attempt_time DESC 
            LIMIT 1
        ");
        $lockCheckStmt->execute([':user_id' => $userId]);
        $lockInfo = $lockCheckStmt->fetch();
        
        if ($lockInfo) {
            $remainingLockStmt = $conn->prepare("
                SELECT EXTRACT(EPOCH FROM (lockout_until - NOW())) as remaining_seconds
                FROM password_reset_logs 
                WHERE user_id = :user_id 
                AND lockout_until > NOW()
                ORDER BY attempt_time DESC 
                LIMIT 1
            ");
            $remainingLockStmt->execute([':user_id' => $userId]);
            $remaining = $remainingLockStmt->fetch();
            
            $response['message'] = 'Account is temporarily locked. Please try again later.';
            $response['lock_time'] = $remaining['remaining_seconds'];
            echo json_encode($response);
            exit();
        }
        
        // Get user email
        $userStmt = $conn->prepare("SELECT email FROM users WHERE user_id = :user_id");
        $userStmt->execute([':user_id' => $userId]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            $response['message'] = 'User not found';
            echo json_encode($response);
            exit();
        }

        // Check recent failed attempts
        $attemptsStmt = $conn->prepare("
            SELECT COUNT(*) as failed_attempts
            FROM password_reset_logs 
            WHERE user_id = :user_id 
            AND attempt_type = 'security_answer'
            AND success = false
            AND attempt_time > NOW() - INTERVAL '1 hour'
        ");
        $attemptsStmt->execute([':user_id' => $userId]);
        $attempts = $attemptsStmt->fetch();
        
        $maxAttempts = 3; 
        $lockoutMinutes = 30; 
        
        if ($attempts['failed_attempts'] >= $maxAttempts) {
            $lockoutUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
            
            $lockStmt = $conn->prepare("
                INSERT INTO password_reset_logs 
                (user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, lockout_until)
                VALUES (:user_id, :email, 'security_answer', :ip, :agent, NOW(), false, :lockout_until)
            ");
            
            $lockStmt->execute([
                ':user_id' => $userId,
                ':email' => $user['email'],
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ':lockout_until' => $lockoutUntil
            ]);
            
            $response['message'] = 'Too many failed attempts. Account locked for ' . $lockoutMinutes . ' minutes.';
            $response['lock_time'] = $lockoutMinutes * 60;
            echo json_encode($response);
            exit();
        }
        
        // Verify all answers
        $verifyResult = $resetLogic->verifyAllAnswers($userId, $answers);
        
        if ($verifyResult['success']) {
            $logStmt = $conn->prepare("
                INSERT INTO password_reset_logs 
                (user_id, email, attempt_type, ip_address, user_agent, attempt_time, success)
                VALUES (:user_id, :email, 'security_answer', :ip, :agent, NOW(), true)
            ");
            
            $logStmt->execute([
                ':user_id' => $userId,
                ':email' => $user['email'],
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Answers verified successfully';
        } else {
            $logStmt = $conn->prepare("
                INSERT INTO password_reset_logs 
                (user_id, email, attempt_type, ip_address, user_agent, attempt_time, success)
                VALUES (:user_id, :email, 'security_answer', :ip, :agent, NOW(), false)
            ");
            
            $logStmt->execute([
                ':user_id' => $userId,
                ':email' => $user['email'],
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            $remainingAttempts = $maxAttempts - ($attempts['failed_attempts'] + 1);
            $response['message'] = $verifyResult['message'] . ' (' . $remainingAttempts . ' attempts remaining)';
        }
        
    } catch (PDOException $e) {
        error_log("Verify answer error: " . $e->getMessage());
        $response['message'] = 'System error occurred';
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>