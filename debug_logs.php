<?php
require_once __DIR__ . '/php/db_connection.php';
try {
    $stmt = $conn->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'admin_activity_logs' AND table_schema = 'public'");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in admin_activity_logs:\n";
    foreach ($cols as $col) {
        echo "- {$col['column_name']} ({$col['data_type']})\n";
    }
    
    if (empty($cols)) {
        echo "No columns found in public schema. Trying without schema filter...\n";
        $stmt = $conn->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'admin_activity_logs'");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "- {$col['column_name']} ({$col['data_type']})\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
