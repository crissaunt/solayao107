<?php
require_once 'db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'remaining_seconds' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['session_id'])) {
        $response['message'] = 'Session ID required';
        echo json_encode($response);
        exit();
    }
    
    $sessionId = trim($data['session_id']);
    
    try {
        // Calculate remaining seconds using database time
        $stmt = $conn->prepare("
            SELECT 
                EXTRACT(EPOCH FROM (otp_expiry - NOW())) as remaining_seconds
            FROM password_reset_sessions 
            WHERE session_id = :session_id
            AND otp_expiry > NOW()
        ");
        
        $stmt->execute([':session_id' => $sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['remaining_seconds'] > 0) {
            $response['success'] = true;
            $response['remaining_seconds'] = floor($result['remaining_seconds']);
        } else {
            $response['message'] = 'Session expired or not found';
        }
        
    } catch (PDOException $e) {
        error_log("Get OTP time error: " . $e->getMessage());
        $response['message'] = 'System error';
    }
}

echo json_encode($response);
?>