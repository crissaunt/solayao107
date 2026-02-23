<?php
require_once __DIR__ . '/php/db_connection.php';

$tables = ['activity_logs', 'deletion_requests', 'roles', 'users', 'admin_activity_logs'];
foreach ($tables as $table) {
    try {
        $stmt = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$table'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Table: $table\n";
        if (empty($columns)) {
            echo "  NOT FOUND\n";
        } else {
            echo "  Columns: " . implode(", ", $columns) . "\n";
        }
    } catch (PDOException $e) {
        echo "Error checking $table: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
?>
