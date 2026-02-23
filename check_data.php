<?php
require_once __DIR__ . '/php/db_connection.php';
try {
    $stmt = $conn->query("SELECT record_id, action_type, table_name FROM admin_activity_logs LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "DATA in admin_activity_logs:\n";
    print_r($rows);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
