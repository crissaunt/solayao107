<?php
require_once __DIR__ . '/php/db_connection.php';
try {
    $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables:\n";
    foreach ($tables as $t) {
        echo "- $t\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
