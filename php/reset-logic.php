<?php
// reset-logic.php
session_start();
require_once 'db_connection.php';
require_once 'email-config.php';

class PasswordReset {
    private $conn;
    private $ip;
    private $userAgent;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    // Generate OTP
    public function generateOTP() {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $otp = '';
        for ($i = 0; $i < EmailConfig::OTP_LENGTH; $i++) {
            $otp .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $otp;
    }
    
    // Check rate limiting for email
    public function checkRateLimit($email, $type = 'otp_request') {
        try {
            $hourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
            
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as attempt_count 
                FROM password_reset_logs 
                WHERE email = :email 
                AND attempt_type = :type 
                AND attempt_time > :hour_ago
                AND success = false
            ");
            
            $stmt->execute([
                ':email' => $email,
                ':type' => $type,
                ':hour_ago' => $hourAgo
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            switch($type) {
                case 'otp_request':
                    return $result['attempt_count'] < EmailConfig::MAX_OTP_REQUESTS_PER_HOUR;
                case 'otp_verify':
                    return $result['attempt_count'] < EmailConfig::MAX_OTP_ATTEMPTS;
                case 'answer_verify':
                    return $result['attempt_count'] < EmailConfig::MAX_ANSWER_ATTEMPTS;
                default:
                    return true;
            }
            
        } catch (PDOException $e) {
            error_log("Rate limit check failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Create reset session - FIXED TIMEZONE
    public function createResetSession($email, $userId) {
        try {
            // Clean old sessions
            $this->cleanExpiredSessions();
            
            $otp = $this->generateOTP();
            $otpHash = password_hash($otp, PASSWORD_BCRYPT);
            
            // FIX: Use PHP time for expiry, not database NOW()
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+' . EmailConfig::OTP_EXPIRY_MINUTES . ' minutes'));
            $sessionId = bin2hex(random_bytes(32));
            
            // Debug logging
            error_log("Creating session - PHP time: " . date('Y-m-d H:i:s'));
            error_log("OTP Expiry set to: $otpExpiry");
            
            $stmt = $this->conn->prepare("
                INSERT INTO password_reset_sessions 
                (session_id, user_id, email, otp_hash, otp_expiry, created_at, updated_at) 
                VALUES (:session_id, :user_id, :email, :otp_hash, :otp_expiry, NOW(), NOW())
            ");
            
            $result = $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':email' => $email,
                ':otp_hash' => $otpHash,
                ':otp_expiry' => $otpExpiry  // Use PHP calculated time
            ]);
            
            if ($result) {
                $this->logAttempt($userId, $email, 'otp_request', true, 'OTP sent successfully');
                
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'otp' => $otp
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to create session'];
            }
            
        } catch (PDOException $e) {
            error_log("Create reset session failed: " . $e->getMessage());
            $this->logAttempt(null, $email, 'otp_request', false, 'Database error');
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    // Verify OTP - FIXED TIMEZONE COMPARISON

    public function verifyOTP($sessionId, $otp) {
    try {
        error_log("=== OTP VERIFICATION START ===");
        error_log("Session ID: " . substr($sessionId, 0, 20) . "...");
        error_log("OTP received: $otp");
        
        // Get current times
        $phpTime = date('Y-m-d H:i:s');
        $phpTimestamp = time();
        
        error_log("PHP Time: $phpTime");
        error_log("PHP Timestamp: $phpTimestamp");
        
        // Get database current time
        $dbTimeStmt = $this->conn->query("SELECT NOW() as db_time, EXTRACT(epoch FROM NOW()) as db_timestamp");
        $dbTimeResult = $dbTimeStmt->fetch(PDO::FETCH_ASSOC);
        $dbTime = $dbTimeResult['db_time'];
        $dbTimestamp = $dbTimeResult['db_timestamp'];
        
        error_log("DB Time: $dbTime");
        error_log("DB Timestamp: $dbTimestamp");
        error_log("Time Difference: " . ($phpTimestamp - $dbTimestamp) . " seconds");
        
        // First, check if session exists (without expiry check)
        $checkStmt = $this->conn->prepare("SELECT * FROM password_reset_sessions WHERE session_id = :session_id");
        $checkStmt->execute([':session_id' => $sessionId]);
        $session = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            error_log("✗ Session not found in database");
            $this->logAttempt(null, '', 'otp_verify', false, 'Session not found in database');
            return ['success' => false, 'message' => 'Invalid or expired session'];
        }
        
        error_log("✓ Session found for user_id: " . $session['user_id']);
        error_log("Session expiry: " . $session['otp_expiry']);
        
        // Convert expiry to timestamp
        $expiryTimestamp = strtotime($session['otp_expiry']);
        error_log("Expiry timestamp: $expiryTimestamp");
        error_log("Current PHP timestamp: $phpTimestamp");
        error_log("Current DB timestamp: $dbTimestamp");
        error_log("Time until expiry (vs PHP): " . ($expiryTimestamp - $phpTimestamp) . " seconds");
        error_log("Time until expiry (vs DB): " . ($expiryTimestamp - $dbTimestamp) . " seconds");
        
        // Check if expired - compare with PHP time
        if ($expiryTimestamp <= $phpTimestamp) {
            error_log("✗ Session expired! Expiry: $expiryTimestamp, Current: $phpTimestamp");
            $this->logAttempt($session['user_id'], $session['email'], 'otp_verify', false, 'Session expired');
            return ['success' => false, 'message' => 'Invalid or expired session'];
        }
        
        error_log("✓ Session is NOT expired");
        
        // Check OTP attempts
        if ($session['otp_attempts'] >= EmailConfig::MAX_OTP_ATTEMPTS) {
            error_log("✗ Max OTP attempts reached: " . $session['otp_attempts']);
            $this->logAttempt($session['user_id'], $session['email'], 'otp_verify', false, 'Max OTP attempts reached');
            return ['success' => false, 'message' => 'Maximum OTP attempts reached. Please request a new OTP.'];
        }
        
        // Verify OTP
        $otpUpper = strtoupper($otp);
        error_log("Verifying OTP: '$otpUpper'");
        
        if (password_verify($otpUpper, $session['otp_hash'])) {
            error_log("✓ OTP verification successful!");
            
            // Update session to next step
            $updateStmt = $this->conn->prepare("
                UPDATE password_reset_sessions 
                SET current_step = 'questions', updated_at = NOW() 
                WHERE session_id = :session_id
            ");
            $updateStmt->execute([':session_id' => $sessionId]);
            
            $this->logAttempt($session['user_id'], $session['email'], 'otp_verify', true, 'OTP verified successfully');
            
            return ['success' => true, 'user_id' => $session['user_id']];
        } else {
            error_log("✗ OTP verification failed");
            
            // Increment attempt counter
            $attemptStmt = $this->conn->prepare("
                UPDATE password_reset_sessions 
                SET otp_attempts = otp_attempts + 1, updated_at = NOW() 
                WHERE session_id = :session_id
            ");
            $attemptStmt->execute([':session_id' => $sessionId]);
            
            $attemptsLeft = EmailConfig::MAX_OTP_ATTEMPTS - ($session['otp_attempts'] + 1);
            
            $this->logAttempt($session['user_id'], $session['email'], 'otp_verify', false, 'Invalid OTP');
            
            return [
                'success' => false, 
                'message' => 'Invalid OTP. ' . $attemptsLeft . ' attempts remaining.'
            ];
        }
        
    } catch (PDOException $e) {
        error_log("OTP verification failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error occurred'];
    }
}
    

        public function getUserQuestions($userId) {
            try {
                // First, check if user has any security answers
                $checkStmt = $this->conn->prepare("
                    SELECT COUNT(*) as question_count 
                    FROM user_security_answers 
                    WHERE user_id = :user_id
                ");
                $checkStmt->execute([':user_id' => $userId]);
                $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($checkResult['question_count'] < 3) {
                    return ['success' => false, 'message' => 'User does not have enough security questions set up. Please contact support.'];
                }
                
                $stmt = $this->conn->prepare("
                    SELECT sq.question_id, sq.question_text 
                    FROM user_security_answers usa
                    JOIN security_questions sq ON usa.question_id = sq.question_id
                    WHERE usa.user_id = :user_id 
                    AND sq.is_active = true
                    ORDER BY RANDOM()  -- Randomize for security
                    LIMIT 3
                ");
                
                $stmt->execute([':user_id' => $userId]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($questions) < 3) {
                    return ['success' => false, 'message' => 'Not enough active security questions found'];
                }
                
                return ['success' => true, 'questions' => $questions];
                
            } catch (PDOException $e) {
                error_log("Get questions failed: " . $e->getMessage());
                return ['success' => false, 'message' => 'System error occurred'];
            }
        }
    
    // Verify security answer
    public function verifyAnswer($userId, $questionId, $answer) {
        try {
            $stmt = $this->conn->prepare("
                SELECT answer_hash FROM user_security_answers 
                WHERE user_id = :user_id AND question_id = :question_id
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':question_id' => $questionId
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $this->logAttempt($userId, '', 'answer_verify', false, 'Question not found for user');
                return ['success' => false, 'message' => 'Invalid question selected'];
            }
            
            // Normalize answer (lowercase, trim)
            $normalizedAnswer = strtolower(trim($answer));
            
            if (password_verify($normalizedAnswer, $result['answer_hash'])) {
                $this->logAttempt($userId, '', 'answer_verify', true, 'Security answer verified');
                return ['success' => true];
            } else {
                $this->logAttempt($userId, '', 'answer_verify', false, 'Incorrect security answer');
                return ['success' => false, 'message' => 'Incorrect answer'];
            }
            
        } catch (PDOException $e) {
            error_log("Verify answer failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error occurred'];
        }
    }
    
    // Update password
    public function updatePassword($userId, $newPassword) {
        try {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET password = :password, 
                    reset_token = NULL,
                    reset_token_expiry = NULL,
                    last_reset_attempt = NOW()
                WHERE user_id = :user_id
            ");
            
            $stmt->execute([
                ':password' => $hashedPassword,
                ':user_id' => $userId
            ]);
            
            // Clean up reset session
            $cleanStmt = $this->conn->prepare("
                DELETE FROM password_reset_sessions WHERE user_id = :user_id
            ");
            $cleanStmt->execute([':user_id' => $userId]);
            
            $this->logAttempt($userId, '', 'password_update', true, 'Password updated successfully');
            
            return ['success' => true, 'message' => 'Password updated successfully'];
            
        } catch (PDOException $e) {
            error_log("Update password failed: " . $e->getMessage());
            $this->logAttempt($userId, '', 'password_update', false, 'Database error');
            return ['success' => false, 'message' => 'Failed to update password'];
        }
    }
    
    // Log attempt
    private function logAttempt($userId, $email, $type, $success, $details = '', $lockoutUntil = null) {
        try {
            // Convert success to proper boolean if it's not already
            $successBool = (bool)$success;
            
            if ($lockoutUntil) {
                $stmt = $this->conn->prepare("
                    INSERT INTO password_reset_logs 
                    (user_id, email, attempt_type, ip_address, user_agent, success, lockout_until, details) 
                    VALUES (:user_id, :email, :type, :ip, :ua, :success, :lockout_until, :details)
                ");
                
                $stmt->execute([
                    ':user_id' => $userId,
                    ':email' => $email,
                    ':type' => $type,
                    ':ip' => $this->ip,
                    ':ua' => $this->userAgent,
                    ':success' => $successBool,
                    ':lockout_until' => $lockoutUntil,
                    ':details' => $details
                ]);
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO password_reset_logs 
                    (user_id, email, attempt_type, ip_address, user_agent, success, details) 
                    VALUES (:user_id, :email, :type, :ip, :ua, :success, :details)
                ");
                
                $stmt->execute([
                    ':user_id' => $userId,
                    ':email' => $email,
                    ':type' => $type,
                    ':ip' => $this->ip,
                    ':ua' => $this->userAgent,
                    ':success' => $successBool,
                    ':details' => $details
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Failed to log attempt: " . $e->getMessage());
        }
    }
    
    // Clean expired sessions
    private function cleanExpiredSessions() {
        try {
            // Use PHP time for consistency
            $expiryTime = date('Y-m-d H:i:s', strtotime('-1 day'));
            
            $stmt = $this->conn->prepare("
                DELETE FROM password_reset_sessions 
                WHERE otp_expiry < :expiry_time
            ");
            $stmt->execute([':expiry_time' => $expiryTime]);
        } catch (PDOException $e) {
            error_log("Clean sessions failed: " . $e->getMessage());
        }
    }
}
?>