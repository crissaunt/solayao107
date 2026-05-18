<?php
require __DIR__ . '/../php/db_connection.php';

try {
    $sql = file_get_contents(__DIR__ . '/update_users_schema.sql');
    $conn->exec($sql);
    echo "Users table schema update SUCCESSFUL.\n";
    
    // Final verification
    $stmt = $conn->query("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'users'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Current Columns in 'users' table:\n";
    foreach ($columns as $col) {
        echo "- {$col['column_name']} ({$col['data_type']})\n";
    }
    
} catch (Exception $e) {
    echo "Update FAILED: " . $e->getMessage();
}
