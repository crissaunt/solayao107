<?php
require __DIR__ . '/../php/db_connection.php';
try {
    echo "Attempting to restore 'public' schema...\n";
    $conn->exec("CREATE SCHEMA IF NOT EXISTS public");
    $conn->exec("GRANT ALL ON SCHEMA public TO postgres");
    $conn->exec("GRANT ALL ON SCHEMA public TO public");
    echo "Successfully restored 'public' schema.\n";
    
    echo "Now attempting to recreate tables...\n";
    $sql = file_get_contents(__DIR__ . '/../recreate_users_table.sql');
    $conn->exec($sql);
    echo "Successfully ran recreate_users_table.sql.\n";
    
    $stmt = $conn->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables now in public schema:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
