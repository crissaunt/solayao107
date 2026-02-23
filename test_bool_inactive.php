<?php
require_once __DIR__ . '/php/db_connection.php';

try {
    $query = "SELECT is_active FROM users WHERE is_active = false LIMIT 1";
    $stmt = $conn->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo "No inactive users found. Creating one for test...\n";
        $conn->exec("INSERT INTO users (username, email, password, first_name, last_name, role_id, is_active) 
                     VALUES ('test_inactive', 'test@example.com', 'pass', 'Test', 'Inactive', 3, false)");
        $stmt = $conn->query($query);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    echo "Value of is_active (inactive user): ";
    var_dump($row['is_active']);
    
    echo "Is empty? " . (empty($row['is_active']) ? 'YES' : 'NO') . "\n";
    echo "Is !empty? " . (!empty($row['is_active']) ? 'YES' : 'NO') . "\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
