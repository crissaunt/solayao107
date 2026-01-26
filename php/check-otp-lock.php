<?php
// check-otp-lock.php
require_once 'db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['locked' => false, 'remaining_seconds' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['session_id'])) {
        echo json_encode($response);
        exit();
    }
    
    $sessionId = trim($data['session_id']);
    
    try {
        // Check if OTP attempts exceeded for this session
        $stmt = $conn->prepare("
            SELECT otp_attempts, otp_expiry 
            FROM password_reset_sessions 
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
        $session = $stmt->fetch();
        
        if ($session && $session['otp_attempts'] >= 3) {
            // Check if lockout period has passed (15 minutes = 900 seconds)
            $lockExpiry = date('Y-m-d H:i:s', strtotime($session['otp_expiry']) + 900);
            $currentTime = date('Y-m-d H:i:s');
            
            if ($lockExpiry > $currentTime) {
                // Still locked, calculate remaining seconds
                $remainingStmt = $conn->prepare("
                    SELECT EXTRACT(EPOCH FROM (CAST(:lock_expiry AS TIMESTAMP) - NOW())) as remaining_seconds
                ");
                $remainingStmt->execute([':lock_expiry' => $lockExpiry]);
                $remaining = $remainingStmt->fetch();
                
                $response['locked'] = true;
                $response['remaining_seconds'] = max(0, floor($remaining['remaining_seconds']));
            }
        }
        
        // Alternative: Check in password_reset_logs table for OTP lock
        $logStmt = $conn->prepare("
            SELECT lockout_until 
            FROM password_reset_logs 
            WHERE session_id = :session_id 
            AND attempt_type = 'otp_verify'
            AND lockout_until > NOW()
            ORDER BY attempt_time DESC 
            LIMIT 1
        ");
        $logStmt->execute([':session_id' => $sessionId]);
        $lockInfo = $logStmt->fetch();
        
        if ($lockInfo) {
            $remainingLockStmt = $conn->prepare("
                SELECT EXTRACT(EPOCH FROM (lockout_until - NOW())) as remaining_seconds
                FROM password_reset_logs 
                WHERE session_id = :session_id 
                AND lockout_until > NOW()
                ORDER BY attempt_time DESC 
                LIMIT 1
            ");
            $remainingLockStmt->execute([':session_id' => $sessionId]);
            $remaining = $remainingLockStmt->fetch();
            
            if ($remaining && $remaining['remaining_seconds'] > 0) {
                $response['locked'] = true;
                $response['remaining_seconds'] = floor($remaining['remaining_seconds']);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Check OTP lock error: " . $e->getMessage());
    }
}

echo json_encode($response);
?>