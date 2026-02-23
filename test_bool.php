<?php
require_once __DIR__ . '/php/db_connection.php';

try {
    $query = "SELECT is_active FROM users LIMIT 1";
    $stmt = $conn->query($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Value of is_active: ";
    var_dump($row['is_active']);
    
    echo "Is empty? " . (empty($row['is_active']) ? 'YES' : 'NO') . "\n";
    echo "Is !empty? " . (!empty($row['is_active']) ? 'YES' : 'NO') . "\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
