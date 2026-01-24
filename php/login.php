
 <?php
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

// Check if user is already logged in
if (isset($_SESSION['username'])) {
    echo json_encode(['success' => true, 'redirect' => 'dashboard.php']); // Changed from login.php
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
                $_SESSION['user_id'] = $user['id']; // Store user ID if available
                unset($_SESSION['login_attempts'], $_SESSION['global_lockout_time'], $_SESSION['lockout_duration'], $_SESSION['max_attempts']);
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                echo json_encode(['success' => true, 'message' => 'Login successful. Redirecting...']);
                exit();
            } else {
                // Incorrect password
                $_SESSION['login_attempts']++;
                $attempts_left = $_SESSION['max_attempts'] - $_SESSION['login_attempts'];
                
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
/*
header('Content-Type: application/json');
header('Content-Security-Policy: default-src \'self\';');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

include 'db_connection.php';


if (isset($_SESSION['username'])) {
    echo json_encode(['success' => true, 'redirect' => 'login.php']);
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['global_lockout_time'])) $_SESSION['global_lockout_time'] = 0;
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_duration'])) $_SESSION['lockout_duration'] = 15;
if (!isset($_SESSION['max_attempts'])) $_SESSION['max_attempts'] = 3;

if ($_SESSION['global_lockout_time'] > time()) {
    $countdown = $_SESSION['global_lockout_time'] - time();
    echo json_encode([
        'success' => false,
        'message' => 'Access Denied. Try Again in ' . $countdown . ' seconds',
        'lockout_duration' => $countdown
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $username;
            unset($_SESSION['login_attempts'], $_SESSION['global_lockout_time'], $_SESSION['lockout_duration'], $_SESSION['max_attempts']);
            echo json_encode(['success' => true, 'message' => 'Login successful. Redirecting...']);
            exit();
        } else {
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= 9) {
                $_SESSION['lockout_duration'] = 60;
                $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
                echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts!!. Locked out for 60 seconds!!', 'lockout_duration' => $_SESSION['lockout_duration']. ' !!']);
                exit();
            } elseif ($_SESSION['login_attempts'] >= $_SESSION['max_attempts']) {
                $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
                echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts!!. Locked out for ' . $_SESSION['lockout_duration'] . ' seconds', 'lockout_duration' => $_SESSION['lockout_duration']. ' !!']);
                $_SESSION['lockout_duration'] += 15;
                $_SESSION['max_attempts'] += 3;
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Incorrect password. Attempt ' ]);
                exit();
            }
        }
    } else {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 9) {
            $_SESSION['lockout_duration'] = 60;
            $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
            echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts!!. Locked out for 60 seconds', 'lockout_duration' => $_SESSION['lockout_duration'].' !!']);
            exit();
        } elseif ($_SESSION['login_attempts'] >= $_SESSION['max_attempts']) {
            $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
            echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts!!. Locked out for ' . $_SESSION['lockout_duration'] . ' seconds', 'lockout_duration' => $_SESSION['lockout_duration'].' !!']);
            $_SESSION['lockout_duration'] += 15;
            $_SESSION['max_attempts'] += 3;
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Username not found!!.Failed Attempt ' ]);
            exit();
        }
    }
}
*/
?>
