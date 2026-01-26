<?php
// check-user-setup.php - Diagnostic tool
require_once 'db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'data' => []];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = $_GET['email'] ?? '';
    
    try {
        if (!empty($email)) {
            // Check specific user
            $stmt = $conn->prepare("
                SELECT 
                    u.user_id,
                    u.username,
                    u.email,
                    u.password IS NOT NULL as has_password,
                    COUNT(usa.answer_id) as security_question_count
                FROM users u
                LEFT JOIN user_security_answers usa ON u.user_id = usa.user_id
                WHERE u.email = :email
                GROUP BY u.user_id, u.username, u.email
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $response['success'] = true;
                $response['data']['user'] = $user;
                
                // Get security questions details
                $questionsStmt = $conn->prepare("
                    SELECT 
                        sq.question_id,
                        sq.question_text,
                        sq.is_active
                    FROM user_security_answers usa
                    JOIN security_questions sq ON usa.question_id = sq.question_id
                    WHERE usa.user_id = :user_id
                ");
                $questionsStmt->execute([':user_id' => $user['user_id']]);
                $response['data']['security_questions'] = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $response['message'] = 'User not found';
            }
        } else {
            // Get overall system status
            $usersStmt = $conn->query("
                SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN password IS NOT NULL THEN 1 END) as users_with_password
                FROM users
            ");
            $response['data']['users'] = $usersStmt->fetch(PDO::FETCH_ASSOC);
            
            $questionsStmt = $conn->query("
                SELECT 
                    COUNT(*) as total_questions,
                    COUNT(CASE WHEN is_active THEN 1 END) as active_questions
                FROM security_questions
            ");
            $response['data']['questions'] = $questionsStmt->fetch(PDO::FETCH_ASSOC);
            
            $answersStmt = $conn->query("
                SELECT 
                    COUNT(DISTINCT user_id) as users_with_answers,
                    COUNT(*) as total_answers
                FROM user_security_answers
            ");
            $response['data']['answers'] = $answersStmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
        }
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>