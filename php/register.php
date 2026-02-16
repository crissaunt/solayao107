<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/db_connection.php';

session_start();

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Debug: Log all incoming data
$log_data = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
    'post_data' => $_POST,
    'input_data' => file_get_contents('php://input')
];

error_log("=== REGISTRATION ATTEMPT ===");
error_log(print_r($log_data, true));

// Check if it's multipart/form-data or application/x-www-form-urlencoded
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    $data = $_POST;
} else {
    // For other content types, read raw input
    $input = json_decode(file_get_contents('php://input'), true);
    $data = $input ?? $_POST;
}

error_log("Data received: " . print_r($data, true));

if(isset($_SESSION['username'])){
    echo json_encode(['status' => 'error', 'message' => 'You are already logged in']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  
    // Get all data with proper type casting
    $id_number      = isset($data['id']) ? trim($data['id']) : '';
    $email          = isset($data['email']) ? trim($data['email']) : '';
    $contact_number = isset($data['contact_number']) ? trim($data['contact_number']) : '';
    $username       = isset($data['username']) ? trim($data['username']) : '';
    $first_name     = isset($data['fname']) ? trim($data['fname']) : '';
    $middle_name    = isset($data['mname']) ? trim($data['mname']) : '';
    $last_name      = isset($data['lname']) ? trim($data['lname']) : '';
    $extension_name = isset($data['extend_name']) ? trim($data['extend_name']) : '';
    $birthday       = isset($data['birthday']) ? trim($data['birthday']) : '';
    
    // AGE: Convert to integer, default to 0 if empty
    $age = isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : 0;
    
    $sex            = isset($data['sex']) ? trim($data['sex']) : '';
    $password       = isset($data['password']) ? $data['password'] : '';
    $street_purok   = isset($data['street_purok']) ? trim($data['street_purok']) : '';
    $barangay       = isset($data['barangay']) ? trim($data['barangay']) : '';
    $city_municipal = isset($data['city_municipal']) ? trim($data['city_municipal']) : '';
    $province       = isset($data['province']) ? trim($data['province']) : '';
    $country        = isset($data['country']) ? trim($data['country']) : '';
    $zipcode        = isset($data['zipcode']) ? trim($data['zipcode']) : '';
    
    // Security questions - CONVERT TO INTEGERS, default to 0 if empty
    $security_question1 = isset($data['security_question1']) && $data['security_question1'] !== '' ? (int)$data['security_question1'] : 0;
    $security_answer1   = isset($data['security_answer1']) ? trim($data['security_answer1']) : '';
    $security_question2 = isset($data['security_question2']) && $data['security_question2'] !== '' ? (int)$data['security_question2'] : 0;
    $security_answer2   = isset($data['security_answer2']) ? trim($data['security_answer2']) : '';
    $security_question3 = isset($data['security_question3']) && $data['security_question3'] !== '' ? (int)$data['security_question3'] : 0;
    $security_answer3   = isset($data['security_answer3']) ? trim($data['security_answer3']) : '';

    // Debug output to see actual values
    error_log("=== DEBUG DATA ===");
    error_log("ID: " . $id_number);
    error_log("Email: " . $email);
    error_log("Age: " . $age . " (Type: " . gettype($age) . ")");
    error_log("Username: " . $username);
    error_log("First Name: " . $first_name);
    error_log("Last Name: " . $last_name);
    error_log("Birthday: " . $birthday);
    error_log("Sex: " . $sex);
    error_log("Password: " . (empty($password) ? "EMPTY" : "SET"));
    error_log("Street: " . $street_purok);
    error_log("Barangay: " . $barangay);
    error_log("City: " . $city_municipal);
    error_log("Province: " . $province);
    error_log("Country: " . $country);
    error_log("Zipcode: " . $zipcode);
    error_log("Security Q1: " . $security_question1 . " (Type: " . gettype($security_question1) . ")");
    error_log("Security A1: " . (empty($security_answer1) ? "EMPTY" : "SET"));
    error_log("Security Q2: " . $security_question2 . " (Type: " . gettype($security_question2) . ")");
    error_log("Security A2: " . (empty($security_answer2) ? "EMPTY" : "SET"));
    error_log("Security Q3: " . $security_question3 . " (Type: " . gettype($security_question3) . ")");
    error_log("Security A3: " . (empty($security_answer3) ? "EMPTY" : "SET"));

    try {
        $conn->beginTransaction();
        
        // Validate required fields - give specific error messages
        $errors = [];
        if (empty($id_number)) $errors[] = "ID number is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($contact_number)) $errors[] = "Contact number is required";
        if (empty($username)) $errors[] = "Username is required";
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($birthday)) $errors[] = "Birthday is required";
        if ($age <= 0) $errors[] = "Valid age is required (must be at least 18)";
        if (empty($sex)) $errors[] = "Sex is required";
        if (empty($password)) $errors[] = "Password is required";
        if (empty($street_purok)) $errors[] = "Street/Purok is required";
        if (empty($barangay)) $errors[] = "Barangay is required";
        if (empty($city_municipal)) $errors[] = "City/Municipal is required";
        if (empty($province)) $errors[] = "Province is required";
        if (empty($country)) $errors[] = "Country is required";
        if (empty($zipcode)) $errors[] = "Zip code is required";
        
        if (!empty($errors)) {
            $conn->rollBack();
            echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
            exit();
        }
        
        // Validate security questions - CHECK FOR > 0
        if ($security_question1 <= 0 || empty($security_answer1) || 
            $security_question2 <= 0 || empty($security_answer2) || 
            $security_question3 <= 0 || empty($security_answer3)) {
            echo json_encode(['status' => 'error', 'message' => 'Please complete all security questions and answers']);
            exit();
        }
        
        // Check if questions are unique
        if ($security_question1 == $security_question2 || 
            $security_question1 == $security_question3 || 
            $security_question2 == $security_question3) {
            echo json_encode(['status' => 'error', 'message' => 'Please select three different security questions']);
            exit();
        }
        
        // Check if user already exists
        $sql_check = "SELECT * FROM users WHERE id_number = :id_number OR email = :email OR contact_number = :contact_number OR username = :username";
        $stmt = $conn->prepare($sql_check);
        $stmt->execute([
            ':id_number' => $id_number,
            ':email' => $email,
            ':contact_number' => $contact_number,
            ':username' => $username
        ]);
        
        $exist_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exist_user) {
            $conn->rollBack();
            if ($exist_user['id_number'] == $id_number) {
                echo json_encode(['status' => 'error', 'message' => 'ID is already taken']);
            } else if ($exist_user['email'] == $email) {
                echo json_encode(['status' => 'error', 'message' => 'Email is already in use']);
            } else if ($exist_user['contact_number'] == $contact_number) {
                echo json_encode(['status' => 'error', 'message' => 'Contact number is already in use']);
            } else if ($exist_user['username'] == $username) {
                echo json_encode(['status' => 'error', 'message' => 'Username is already in use']);
            }
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert new user with role_id = 3 (regular user)
        $sql_insert = "INSERT INTO users (id_number, email, age, contact_number, username, first_name, middle_name, last_name, extension_name, birthday, sex, password, street_purok, barangay, city_municipal, province, country, zipcode, role_id, is_active) 
                      VALUES (:id_number, :email, :age, :contact_number, :username, :first_name, :middle_name, :last_name, :extension_name, :birthday, :sex, :password, :street_purok, :barangay, :city_municipal, :province, :country, :zipcode, 3, true)";
        
        error_log("Executing insert with age: " . $age);
        
        $stmt_insert = $conn->prepare($sql_insert);
        $result = $stmt_insert->execute([
            ':id_number' => $id_number,
            ':email' => $email,
            ':age' => $age,
            ':contact_number' => $contact_number,
            ':username' => $username,
            ':first_name' => $first_name,
            ':middle_name' => $middle_name,
            ':last_name' => $last_name,
            ':extension_name' => $extension_name,
            ':birthday' => $birthday,
            ':sex' => $sex,
            ':password' => $hashed_password,
            ':street_purok' => $street_purok,
            ':barangay' => $barangay,
            ':city_municipal' => $city_municipal,
            ':province' => $province,
            ':country' => $country,
            ':zipcode' => $zipcode
        ]);
        
        if (!$result) {
            $conn->rollBack();
            $errorInfo = $stmt_insert->errorInfo();
            error_log("Insert error: " . print_r($errorInfo, true));
            echo json_encode(['status' => 'error', 'message' => 'Error inserting user data: ' . $errorInfo[2]]);
            exit();
        }
        
        // Get the inserted user_id
        $user_id = $conn->lastInsertId();
        
        // Fix the sequence for security answers if needed
        try {
            // Check and fix the sequence
            $conn->exec("SELECT setval('user_security_answers_answer_id_seq', (SELECT COALESCE(MAX(answer_id), 0) FROM user_security_answers))");
        } catch (PDOException $e) {
            error_log("Sequence fix warning: " . $e->getMessage());
            // Continue anyway
        }
        
        // Insert security answers - HASH THE ANSWERS for security
        $security_answers = [
            ['question_id' => $security_question1, 'answer' => $security_answer1],
            ['question_id' => $security_question2, 'answer' => $security_answer2],
            ['question_id' => $security_question3, 'answer' => $security_answer3]
        ];
        
        $sql_answer = "INSERT INTO user_security_answers (user_id, question_id, answer_hash) VALUES (:user_id, :question_id, :answer_hash)";
        $stmt_answer = $conn->prepare($sql_answer);
        
        foreach ($security_answers as $answer) {
            // Hash the answer for security - normalize to lowercase and trim
            $normalized_answer = strtolower(trim($answer['answer']));
            $hashed_answer = password_hash($normalized_answer, PASSWORD_BCRYPT);
            
            $result_answer = $stmt_answer->execute([
                ':user_id' => $user_id,
                ':question_id' => $answer['question_id'],
                ':answer_hash' => $hashed_answer
            ]);
            
            if (!$result_answer) {
                $conn->rollBack();
                $errorInfo = $stmt_answer->errorInfo();
                error_log("Security answer insert error: " . print_r($errorInfo, true));
                echo json_encode(['status' => 'error', 'message' => 'Error saving security answers: ' . $errorInfo[2]]);
                exit();
            }
        }
        
        // LOG THE REGISTRATION ACTIVITY (not VIEW)
        try {
            // Check if activity_logs table exists and log the registration
            $log_query = "INSERT INTO activity_logs (table_name, record_id, action, new_data, performed_by, ip_address, user_agent) 
                         VALUES (:table_name, :record_id, :action, :new_data, :performed_by, :ip_address, :user_agent)";
            
            // Prepare new data for logging (excluding sensitive info)
            $new_user_data = [
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role_id' => 3
            ];
            
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->execute([
                ':table_name' => 'users',
                ':record_id' => $user_id,
                ':action' => 'INSERT',
                ':new_data' => json_encode($new_user_data),
                ':performed_by' => $user_id, // User registering themselves
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            error_log("Registration logged successfully for user ID: " . $user_id);
            
        } catch (PDOException $e) {
            // Log table might not exist, just log to error log but don't fail registration
            error_log("Could not log registration activity: " . $e->getMessage());
        }
        
        $conn->commit();
        
        // Log success to error log
        error_log("=== REGISTRATION SUCCESS === User ID: " . $user_id . ", Username: " . $username);
        
        echo json_encode(['status' => 'success', 'message' => 'Your Account is Successfully Created']);
        
    } catch(PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>