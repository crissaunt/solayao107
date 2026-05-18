<?php
require __DIR__ . '/../php/db_connection.php';
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();
    echo "Transaction started.\n";
    
    // 1. insert user
    $sql = "INSERT INTO users (
        id_number, email, contact_number, username, first_name, middle_name, 
        last_name, extension_name, birthday, age, sex, password, 
        street_purok, barangay, city_municipal, province, country, zipcode,
        role_id, is_active, created_at, updated_at
    ) VALUES (
        '1234-5678', 'test1234@example.com', '09123456789', 'test1234', 'Test', '',
        'User', '', '1990-01-01', 30, 'Male', 'hashedpassword',
        'Purok 1', 'Barangay 1', 'City 1', 'Province 1', 'Philippines', '1234',
        3, true, NOW(), NOW()
    )";
    $conn->exec($sql);
    echo "User inserted.\n";
    
    $user_id = $conn->lastInsertId();
    echo "User ID: $user_id\n";
    
    // 2. setval
    try {
        $conn->exec("SELECT setval('user_security_answers_answer_id_seq', (SELECT COALESCE(MAX(answer_id), 0) FROM user_security_answers))");
        echo "Setval executed.\n";
    } catch (PDOException $e) {
        echo "Setval exception: " . $e->getMessage() . "\n";
    }
    
    // 3. insert answer
    try {
        $sql_answer = "INSERT INTO user_security_answers (user_id, question_id, answer_hash) VALUES ($user_id, 1, 'hashedanswer')";
        $conn->exec($sql_answer);
        echo "Answer inserted.\n";
    } catch (PDOException $e) {
        echo "Answer exception: " . $e->getMessage() . "\n";
    }
    
    // 4. insert activity log
    try {
        $log_query = "INSERT INTO activity_logs (table_name, record_id, action, new_data, performed_by, ip_address, user_agent) 
                      VALUES ('users', $user_id, 'INSERT', '{}', $user_id, '127.0.0.1', 'Mozilla')";
        $conn->exec($log_query);
        echo "Activity log inserted.\n";
    } catch (PDOException $e) {
        echo "Activity log exception: " . $e->getMessage() . "\n";
    }

    $conn->rollBack();
    echo "Rolled back successfully.";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Main Exception: " . $e->getMessage() . "\n";
}
