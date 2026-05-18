<?php
require_once __DIR__ . '/../php/db_connection.php';

try {
    // Drop the unused admin_login_attempts table
    $conn->exec("DROP TABLE IF EXISTS admin_login_attempts CASCADE;");
    echo "Successfully deleted table: admin_login_attempts\n";

    // Drop the unused password_reset_attempts table
    $conn->exec("DROP TABLE IF EXISTS password_reset_attempts CASCADE;");
    echo "Successfully deleted table: password_reset_attempts\n";

} catch (PDOException $e) {
    echo "Error deleting tables: " . $e->getMessage() . "\n";
}
?>
