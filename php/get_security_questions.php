<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getFallbackQuestions() {
    return [
        ['question_id' => 1, 'question_text' => "What is your mother's maiden name?"],
        ['question_id' => 2, 'question_text' => "What was the name of your first pet?"],
        ['question_id' => 3, 'question_text' => "What city were you born in?"],
        ['question_id' => 4, 'question_text' => "What is the name of your favorite movie?"],
        ['question_id' => 5, 'question_text' => "What was the name of your first elementary school?"],
        ['question_id' => 6, 'question_text' => "What is your favorite color?"]
    ];
}

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

// Initialize with fallback questions
$questions = getFallbackQuestions();
$db_status = 'Fallback';

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
        $db_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($db_questions)) {
            $questions = $db_questions;
            $db_status = 'PostgreSQL';
        }
    } 
    
    echo json_encode([
        'status' => 'success', 
        'questions' => $questions,
        'count' => count($questions),
        'database' => $db_status
    ]);
    
} catch(PDOException $e) {
    error_log("PostgreSQL Database error: " . $e->getMessage());
    echo json_encode([
        'status' => 'success', // Still return success but with fallback questions
        'message' => 'Database connection failed, using fallback questions',
        'questions' => $questions,
        'count' => count($questions),
        'database' => 'Fallback (Error: ' . $e->getMessage() . ')'
    ]);
} catch(Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Server error: ' . $e->getMessage(),
        'questions' => $questions
    ]);
}


?>