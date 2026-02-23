<?php
require_once __DIR__ . '/php/db_connection.php';
try {
    $stmt = $conn->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'activity_logs' AND table_schema = 'public'");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "ACTIVITY_LOGS:\n";
    foreach ($cols as $col) { echo "- {$col['column_name']} ({$col['data_type']})\n"; }
    
    $stmt = $conn->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'admin_activity_logs' AND table_schema = 'public'");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nADMIN_ACTIVITY_LOGS:\n";
    foreach ($cols as $col) { echo "- {$col['column_name']} ({$col['data_type']})\n"; }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
