<?php
require_once __DIR__ . '/../php/db_connection.php';

$tables_to_drop = [
    'admin_users_backup',
    'role_permissions',
    'permissions'
];

echo "<h3>Starting Cleanup of Unused Tables...</h3>";

foreach ($tables_to_drop as $table) {
    try {
        // We use CASCADE to safely drop any leftover/orphaned foreign keys pointing to these tables
        $conn->exec("DROP TABLE IF EXISTS $table CASCADE");
        echo "<p style='color:green'>✅ Table <b>'$table'</b> dropped successfully (or did not exist).</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Error dropping table <b>'$table'</b>: " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Cleanup Completed!</h3>";
?>
