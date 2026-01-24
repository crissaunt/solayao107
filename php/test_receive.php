<?php
// test_receive.php
header('Content-Type: application/json');

// Log everything
error_log("=== TEST RECEIVE ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));

// Get all POST data
$postData = $_POST;

// Also get raw input
$rawInput = file_get_contents('php://input');
error_log("Raw input: " . $rawInput);

// Check security questions specifically
$securityQuestions = [
    'security_question1' => $_POST['security_question1'] ?? 'NOT SET',
    'security_answer1' => $_POST['security_answer1'] ?? 'NOT SET',
    'security_question2' => $_POST['security_question2'] ?? 'NOT SET',
    'security_answer2' => $_POST['security_answer2'] ?? 'NOT SET',
    'security_question3' => $_POST['security_question3'] ?? 'NOT SET',
    'security_answer3' => $_POST['security_answer3'] ?? 'NOT SET'
];

error_log("Security questions received: " . print_r($securityQuestions, true));

// Return everything
echo json_encode([
    'status' => 'success',
    'message' => 'Test received',
    'post_data' => $postData,
    'security_questions' => $securityQuestions,
    'raw_input_length' => strlen($rawInput)
]);
?>