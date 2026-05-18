<?php
require __DIR__ . '/../php/db_connection.php';
try {
    $stmt = $conn->query("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'users'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'users' table:\n";
    foreach ($columns as $col) {
        echo "- {$col['column_name']} ({$col['data_type']}, nullable: {$col['is_nullable']})\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
