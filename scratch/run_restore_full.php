<?php
require __DIR__ . '/../php/db_connection.php';

try {
    $sql = file_get_contents(__DIR__ . '/restore_full_schema.sql');
    $conn->exec($sql);
    echo "Full schema restoration SUCCESSFUL.\n";
    
    // Final verification
    $tables = ['users', 'roles', 'activity_logs', 'security_questions', 'user_security_answers', 'system_activity_logs'];
    echo "Current Table Status:\n";
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '$table')");
        $exists = $stmt->fetchColumn() ? 'EXISTS' : 'MISSING';
        echo "- $table: $exists\n";
    }
    
} catch (Exception $e) {
    echo "Restoration FAILED: " . $e->getMessage();
}
