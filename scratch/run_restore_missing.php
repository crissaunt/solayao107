<?php
require __DIR__ . '/../php/db_connection.php';

try {
    $sql = file_get_contents(__DIR__ . '/restore_missing_admin_tables.sql');
    $conn->exec($sql);
    echo "✅ Missing admin tables restored successfully!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
