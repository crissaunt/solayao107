<?php
require_once 'db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'cooldown_seconds' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['session_id'])) {
        $response['message'] = 'Session ID required';
        echo json_encode($response);
        exit();
    }
    
    $sessionId = trim($data['session_id']);
    
    try {
        // Check last resend time and calculate cooldown
        $stmt = $conn->prepare("
            SELECT 
                EXTRACT(EPOCH FROM (NOW() - created_at)) as seconds_since_creation,
                created_at
            FROM password_reset_sessions 
            WHERE session_id = :session_id
        ");
        
        $stmt->execute([':session_id' => $sessionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $secondsSinceCreation = floor($result['seconds_since_creation']);
            // 60 seconds cooldown for resend
            $cooldown = max(0, 60 - $secondsSinceCreation);
            
            $response['success'] = true;
            $response['cooldown_seconds'] = $cooldown;
        } else {
            $response['message'] = 'Session not found';
        }
        
    } catch (PDOException $e) {
        error_log("Get resend cooldown error: " . $e->getMessage());
        $response['message'] = 'System error';
    }
}

echo json_encode($response);
?>