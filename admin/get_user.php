<?php
// get_user.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in and is admin (Super Admin or Admin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role_id'] > 2) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit();
}

$user_id = (int)$_GET['id'];

try {
    $query = "SELECT * FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Remove password from response
        unset($user['password']);
        header('Content-Type: application/json');
        echo json_encode($user);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>