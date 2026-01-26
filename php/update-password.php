<?php
// update-password.php
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
    
    if (empty($data['user_id']) || empty($data['new_password']) || empty($data['confirm_password'])) {
        $response['message'] = 'All fields are required';
        echo json_encode($response);
        exit();
    }
    
    $userId = intval($data['user_id']);
    $newPassword = $data['new_password'];
    $confirmPassword = $data['confirm_password'];
    
    // Validate passwords
    if ($newPassword !== $confirmPassword) {
        $response['message'] = 'Passwords do not match';
        echo json_encode($response);
        exit();
    }
    
    // Password strength validation
    if (strlen($newPassword) < 8) {
        $response['message'] = 'Password must be at least 8 characters';
        echo json_encode($response);
        exit();
    }
    
    if (!preg_match('/[A-Z]/', $newPassword)) {
        $response['message'] = 'Password must contain at least one uppercase letter';
        echo json_encode($response);
        exit();
    }
    
    if (!preg_match('/[a-z]/', $newPassword)) {
        $response['message'] = 'Password must contain at least one lowercase letter';
        echo json_encode($response);
        exit();
    }
    
    if (!preg_match('/[0-9]/', $newPassword)) {
        $response['message'] = 'Password must contain at least one number';
        echo json_encode($response);
        exit();
    }
    
    try {
        $resetLogic = new PasswordReset($conn);
        $updateResult = $resetLogic->updatePassword($userId, $newPassword);
        
        if ($updateResult['success']) {
            $response['success'] = true;
            $response['message'] = $updateResult['message'];
        } else {
            $response['message'] = $updateResult['message'];
        }
        
    } catch (PDOException $e) {
        error_log("Update password error: " . $e->getMessage());
        $response['message'] = 'System error occurred';
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>