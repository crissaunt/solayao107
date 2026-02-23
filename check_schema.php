<?php
require_once __DIR__ . '/php/db_connection.php';

try {
    $tableName = 'users';
    $query = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$tableName' ORDER BY column_name";
    $stmt = $conn->query($query);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns for table: $tableName\n";
    foreach ($columns as $column) {
        echo str_pad($column['column_name'], 20) . ": " . $column['data_type'] . "\n";
    }
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
