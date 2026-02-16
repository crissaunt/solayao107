<?php
// login.php
// Start session at the very beginning
session_start();

// Set headers first
header('Content-Type: application/json');
header('Content-Security-Policy: default-src \'self\';');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Access-Control-Allow-Origin: *"); // For development only, restrict in production
header("Access-Control-Allow-Methods: POST"); // Allow POST method

include 'db_connection.php';

// Function to log login attempts
function logLoginAttempt($conn, $username, $user_id, $success, $failure_reason = null, $attempt_type = 'login') {
    try {
        // Get client IP address
        $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Get current timestamp
        $attempt_time = date('Y-m-d H:i:s');
        
        // Log to login_attempts table
        $login_query = "INSERT INTO login_attempts (user_id, username, ip_address, success, attempt_time) 
                       VALUES (:user_id, :username, :ip_address, :success, :attempt_time)";
        $login_stmt = $conn->prepare($login_query);
        $login_stmt->execute([
            ':user_id' => $user_id,
            ':username' => $username,
            ':ip_address' => $ip_address,
            ':success' => $success ? 'true' : 'false',
            ':attempt_time' => $attempt_time
        ]);
        
        // Also log to activity_logs table for successful logins
        if ($success) {
            $activity_query = "INSERT INTO activity_logs (table_name, action, performed_by, ip_address, user_agent, created_at) 
                              VALUES ('users', 'LOGIN', :user_id, :ip_address, :user_agent, NOW())";
            $activity_stmt = $conn->prepare($activity_query);
            $activity_stmt->execute([
                ':user_id' => $user_id,
                ':ip_address' => $ip_address,
                ':user_agent' => $user_agent
            ]);
        }
        
        // Log to admin_activity_logs if user is admin
        if ($success && $user_id) {
            // Check if user is admin
            $check_admin = $conn->prepare("SELECT admin_id FROM admin_users WHERE user_id = :user_id");
            $check_admin->execute([':user_id' => $user_id]);
            $admin_data = $check_admin->fetch(PDO::FETCH_ASSOC);
            
            if ($admin_data) {
                $admin_activity_query = "INSERT INTO admin_activity_logs 
                                        (admin_id, username, action_type, table_name, ip_address, user_agent, endpoint, method, status_code, created_at) 
                                        VALUES (:admin_id, :username, 'LOGIN', 'users', :ip_address, :user_agent, '/login.php', 'POST', 200, NOW())";
                $admin_activity_stmt = $conn->prepare($admin_activity_query);
                $admin_activity_stmt->execute([
                    ':admin_id' => $admin_data['admin_id'],
                    ':username' => $username,
                    ':ip_address' => $ip_address,
                    ':user_agent' => $user_agent
                ]);
            }
        }
        
        // Log to password_reset_logs for failed attempts
        if (!$success && $failure_reason) {
            $reset_log_query = "INSERT INTO password_reset_logs 
                               (user_id, email, attempt_type, ip_address, user_agent, attempt_time, success, details) 
                               VALUES (:user_id, :email, :attempt_type, :ip_address, :user_agent, :attempt_time, false, :details)";
            $reset_log_stmt = $conn->prepare($reset_log_query);
            $reset_log_stmt->execute([
                ':user_id' => $user_id,
                ':email' => $username, // Assuming username might be email
                ':attempt_type' => $attempt_type,
                ':ip_address' => $ip_address,
                ':user_agent' => $user_agent,
                ':attempt_time' => $attempt_time,
                ':details' => $failure_reason
            ]);
        }
        
        // FIXED: Update system_activity_logs - use 'user' instead of 'system' for actor_type
        $system_log_query = "INSERT INTO system_activity_logs 
                            (user_id, actor_type, action, category, description, ip_address, user_agent, created_at) 
                            VALUES (:user_id, :actor_type, :action, :category, :description, :ip_address, :user_agent, NOW())";
        $system_log_stmt = $conn->prepare($system_log_query);
        
        // Determine the correct actor_type based on the situation
        $actor_type = 'user'; // Default to 'user'
        
        // If user_id exists and it's a successful login, check if they're admin
        if ($success && $user_id) {
            $check_admin = $conn->prepare("SELECT admin_id FROM admin_users WHERE user_id = :user_id");
            $check_admin->execute([':user_id' => $user_id]);
            if ($check_admin->fetch(PDO::FETCH_ASSOC)) {
                $actor_type = 'admin';
            }
        }
        
        // For failed attempts, still use 'user' as actor_type (not 'system')
        
        $system_log_stmt->execute([
            ':user_id' => $user_id,
            ':actor_type' => $actor_type, // Always use 'user' or 'admin', never 'system'
            ':action' => $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED',
            ':category' => 'authentication',
            ':description' => $success ? "User $username logged in successfully" : "Failed login attempt for user $username" . ($failure_reason ? ": $failure_reason" : ""),
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
        
    } catch (PDOException $e) {
        // Log error but don't stop the login process
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

// Check if user is already logged in
if (isset($_SESSION['username'])) {
    echo json_encode(['success' => true, 'redirect' => 'home.php']);
    exit();
}

// Initialize session variables if not set
if (!isset($_SESSION['global_lockout_time'])) $_SESSION['global_lockout_time'] = 0;
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_duration'])) $_SESSION['lockout_duration'] = 15;
if (!isset($_SESSION['max_attempts'])) $_SESSION['max_attempts'] = 3;

// Check global lockout
if ($_SESSION['global_lockout_time'] > time()) {
    $countdown = $_SESSION['global_lockout_time'] - time();
    
    // Log the blocked attempt - use IP as username
    $blocked_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    logLoginAttempt($conn, "IP:$blocked_ip", null, false, "Blocked due to lockout", 'lockout');
    
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied. Try Again in ' . $countdown . ' seconds',
        'lockout_duration' => $countdown
    ]);
    exit();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get raw POST data for JSON or form-data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If JSON input is not available, use regular POST
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate input
    if (!isset($input['username']) || !isset($input['password'])) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }
    
    $username = trim($input['username']);
    $password = trim($input['password']);
    
    // Basic input validation
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }

    try {
        // PostgreSQL prepared statement with PDO
        $stmt = $conn->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = $user['user_id']; // Store user ID
                $_SESSION['full_name'] = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
                $_SESSION['role_id'] = $user['role_id'] ?? 0;
                
                // Get role name
                if (isset($user['role_id'])) {
                    $role_stmt = $conn->prepare('SELECT role_name FROM roles WHERE role_id = :role_id');
                    $role_stmt->execute([':role_id' => $user['role_id']]);
                    $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
                    $_SESSION['role'] = $role['role_name'] ?? 'User';
                } else {
                    $_SESSION['role'] = 'User';
                }
                
                // Update last login
                $update_stmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE user_id = :user_id');
                $update_stmt->execute([':user_id' => $user['user_id']]);
                
                // Log successful login
                logLoginAttempt($conn, $username, $user['user_id'], true);
                
                unset($_SESSION['login_attempts'], $_SESSION['global_lockout_time'], $_SESSION['lockout_duration'], $_SESSION['max_attempts']);
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                echo json_encode(['success' => true, 'message' => 'Login successful. Redirecting...']);
                exit();
            } else {
                // Incorrect password
                $_SESSION['login_attempts']++;
                $attempts_left = $_SESSION['max_attempts'] - $_SESSION['login_attempts'];
                
                // Log failed login attempt
                logLoginAttempt($conn, $username, $user['user_id'], false, 'Incorrect password');
                
                if ($_SESSION['login_attempts'] >= 9) {
                    $_SESSION['lockout_duration'] = 60;
                    $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Too many incorrect attempts! Locked out for 60 seconds!',
                        'lockout_duration' => $_SESSION['lockout_duration']
                    ]);
                    exit();
                } elseif ($_SESSION['login_attempts'] >= $_SESSION['max_attempts']) {
                    $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Too many incorrect attempts! Locked out for ' . $_SESSION['lockout_duration'] . ' seconds',
                        'lockout_duration' => $_SESSION['lockout_duration']
                    ]);
                    $_SESSION['lockout_duration'] += 15;
                    $_SESSION['max_attempts'] += 3;
                    exit();
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Incorrect password. Attempts left: ' . $attempts_left
                    ]);
                    exit();
                }
            }
        } else {
            // Username not found
            $_SESSION['login_attempts']++;
            $attempts_left = $_SESSION['max_attempts'] - $_SESSION['login_attempts'];
            
            // Log failed login attempt (username not found)
            logLoginAttempt($conn, $username, null, false, 'Username not found');
            
            if ($_SESSION['login_attempts'] >= 9) {
                $_SESSION['lockout_duration'] = 60;
                $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
                echo json_encode([
                    'success' => false, 
                    'message' => 'Too many incorrect attempts! Locked out for 60 seconds',
                    'lockout_duration' => $_SESSION['lockout_duration']
                ]);
                exit();
            } elseif ($_SESSION['login_attempts'] >= $_SESSION['max_attempts']) {
                $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
                echo json_encode([
                    'success' => false, 
                    'message' => 'Too many incorrect attempts! Locked out for ' . $_SESSION['lockout_duration'] . ' seconds',
                    'lockout_duration' => $_SESSION['lockout_duration']
                ]);
                $_SESSION['lockout_duration'] += 15;
                $_SESSION['max_attempts'] += 3;
                exit();
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Username not found! Attempts left: ' . $attempts_left
                ]);
                exit();
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        exit();
    }
} else {
    // Not a POST request
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed']);
    exit();
}
?>