<?php

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
                echo json_encode(['success' => false, 'message' => 'Incorrect password. Attempt ' . $_SESSION['login_attempts']]);
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
            echo json_encode(['success' => false, 'message' => 'Username not found!!. Attempt ' . $_SESSION['login_attempts'] ]);
            exit();
        }
    }
}
?>
