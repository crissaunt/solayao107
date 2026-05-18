<?php
require __DIR__ . '/../php/db_connection.php';
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SELECT setval('user_security_answers_answer_id_seq', (SELECT COALESCE(MAX(answer_id), 0) FROM user_security_answers))");
    echo "SUCCESS";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
