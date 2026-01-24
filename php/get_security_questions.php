<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if db_connection.php exists
if (!file_exists('db_connection.php')) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database configuration not found',
        'questions' => getFallbackQuestions()
    ]);
    exit;
}

require 'db_connection.php';

try {
    // Test PostgreSQL connection
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists (PostgreSQL syntax)
    $tableCheck = $conn->query("SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name = 'security_questions'
    )");
    
    $tableExists = $tableCheck->fetchColumn();
    
    if ($tableExists) {
        // Table exists, fetch questions
        $sql = "SELECT question_id, question_text FROM security_questions WHERE is_active = TRUE ORDER BY question_text";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
    } 
    
    echo json_encode([
        'status' => 'success', 
        'questions' => $questions,
        'count' => count($questions),
        'database' => 'PostgreSQL'
    ]);
    
} catch(PDOException $e) {
    error_log("PostgreSQL Database error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'PostgreSQL Database connection failed: ' . $e->getMessage(),

    ]);
} catch(Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Server error: ' . $e->getMessage(),

    ]);
}

?>