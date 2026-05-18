<?php
require_once __DIR__ . '/../php/db_connection.php';
try {
    $stmt = $conn->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'edit_requests'");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['column_name'] . ": " . $col['data_type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
