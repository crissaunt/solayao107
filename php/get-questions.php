<?php
// get-questions.php
require_once 'db_connection.php';
require_once 'reset-logic.php';

header('Content-Type: application/json');

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['user_id'])) {
        $response['message'] = 'User ID is required';
        echo json_encode($response);
        exit();
    }
    
    $userId = intval($data['user_id']);
    
    try {
        $resetLogic = new PasswordReset($conn);
        $questionsResult = $resetLogic->getUserQuestions($userId);
        
        if ($questionsResult['success']) {
            $response['success'] = true;
            $response['questions'] = $questionsResult['questions'];
        } else {
            $response['message'] = $questionsResult['message'];
        }
        
    } catch (PDOException $e) {
        error_log("Get questions error: " . $e->getMessage());
        $response['message'] = 'System error occurred';
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>