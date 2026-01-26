<?php
require_once 'db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['is_valid' => false, 'remaining_minutes' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['session_id'])) {
        $response['message'] = 'Session ID required';
        echo json_encode($response);
        exit();
    }
    
    $sessionId = trim($data['session_id']);
    
    try {
        // Check if OTP is still valid (has more than 2 minutes left)
        $stmt = $conn->prepare("
            SELECT 
                EXTRACT(EPOCH FROM (otp_expiry - NOW())) as remaining_seconds
            FROM password_reset_sessions 
            WHERE session_id = :session_id
            AND otp_expiry > NOW()
        ");
        
        $stmt->execute([':session_id' => $sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['remaining_seconds'] > 120) { // More than 2 minutes left
            $response['is_valid'] = true;
            $response['remaining_minutes'] = ceil($result['remaining_seconds'] / 60);
        } else {
            $response['is_valid'] = false;
            $response['remaining_minutes'] = $result ? ceil($result['remaining_seconds'] / 60) : 0;
        }
        
    } catch (PDOException $e) {
        error_log("Check OTP valid error: " . $e->getMessage());
        $response['message'] = 'System error';
    }
}

echo json_encode($response);
?>