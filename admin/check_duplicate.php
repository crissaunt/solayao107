<?php
// check_duplicate.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');
$exclude_id = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : 0;

if (empty($field) || empty($value)) {
    echo json_encode(['exists' => false]);
    exit();
}

$allowed_fields = ['username', 'email', 'id_number', 'contact_number'];
if (!in_array($field, $allowed_fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid field']);
    exit();
}

try {
    $query = "SELECT COUNT(*) FROM users WHERE $field = :value";
    $params = [':value' => $value];
    
    if ($exclude_id > 0) {
        $query .= " AND user_id != :exclude_id";
        $params[':exclude_id'] = $exclude_id;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    
    echo json_encode(['exists' => $count > 0]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
