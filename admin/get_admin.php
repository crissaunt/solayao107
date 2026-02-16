<?php
// get_admin.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Admin ID required']);
    exit();
}

$admin_id = (int)$_GET['id'];

try {
    $query = "SELECT admin_id, username, email, first_name, last_name, role, is_active 
              FROM admin_users 
              WHERE admin_id = :admin_id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':admin_id' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        // Remove sensitive data if needed
        header('Content-Type: application/json');
        echo json_encode($admin);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Admin not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>