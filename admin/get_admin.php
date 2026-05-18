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
    $query = "SELECT u.user_id as admin_id, u.username, u.email, u.first_name, u.last_name, 
                     u.middle_name, u.extension_name, u.id_number, u.birthday,
                     u.street_purok, u.barangay, u.city_municipal, u.province, u.country, u.zipcode,
                     r.role_name as role, u.role_id, u.is_active 
              FROM users u
              JOIN roles r ON u.role_id = r.role_id
              WHERE u.user_id = :admin_id AND u.role_id IN (1, 2)";
    $stmt = $conn->prepare($query);
    $stmt->execute([':admin_id' => $admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        // Ensure is_active is returned as numeric for JS consistency
        $admin['is_active'] = $admin['is_active'] ? 1 : 0;
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