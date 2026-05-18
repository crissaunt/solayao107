<?php
// fix_users_table.php
require 'php/db_connection.php';

try {
    $sql = file_get_contents('recreate_users_table.sql');
    $conn->exec($sql);
    echo "Successfully recreated 'users' table and migrated data if backup was available.\n";
    
    // Check if users table now exists
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Current user count: $count\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
