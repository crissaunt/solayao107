<?php
require __DIR__ . '/../php/db_connection.php';
try {
    $stmt = $conn->query("
        SELECT relname 
        FROM pg_class 
        WHERE relkind = 'S' 
        AND relname LIKE 'user_security_answers%'
    ");
    $sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sequences found:\n";
    foreach ($sequences as $seq) {
        echo "- {$seq['relname']}\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
