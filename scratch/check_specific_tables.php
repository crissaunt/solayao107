<?php
require __DIR__ . '/../php/db_connection.php';
try {
    $tables_to_check = [
        'users',
        'roles',
        'security_questions',
        'user_security_answers',
        'activity_logs',
        'system_activity_logs',
        'admin_users_backup'
    ];
    
    echo "Checking tables in 'public' schema:\n";
    foreach ($tables_to_check as $table) {
        $stmt = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '$table')");
        $exists = $stmt->fetchColumn() ? 'EXISTS' : 'MISSING';
        echo "- $table: $exists\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
