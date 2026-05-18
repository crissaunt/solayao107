<?php
require_once __DIR__ . '/../php/db_connection.php';

$tables = [
    'admin_activity_logs',
    'login_attempts',
    'password_reset_logs',
    'system_activity_logs',
    'activity_logs',
    'users',
    'roles'
];

echo "Checking tables...\n";
foreach ($tables as $table) {
    try {
        $stmt = $conn->query("SELECT 1 FROM $table LIMIT 1");
        echo "Table '$table' exists.\n";
    } catch (PDOException $e) {
        echo "Table '$table' DOES NOT EXIST or error: " . $e->getMessage() . "\n";
    }
}
?>
