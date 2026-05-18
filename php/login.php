<?php
// login.php
session_start();

// ── GET: show the login page ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect already-logged-in users to home
    if (isset($_SESSION['user_id'])) {
        header("Location: home.php");
        exit();
    }
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>

  <header class="header">
    <div class="flex">
      <a href="#" class="logo">Plants.&#127804;</a>
      <nav class="navbar">
        <a href="#">Home</a>
        <a id="signupToLogout" href="register.php">Sign up</a>
      </nav>
    </div>
  </header>

  <main class="wrapper">
    <div class="box-body">
      <div class="box-form">
        <header class="form-header">Log in</header>
        <div id="error-message" class="error-message"></div>
        <form id="loginForm" action="login.php" method="POST">
          <div class="input-box">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" placeholder="Ex. Alex24">
            <div class="error"></div>
          </div>

          <div class="input-box" style="position: relative;">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter your password">
            <img id="togglePassword1" src="../images/eye-icon.png"
              style="cursor: pointer; width: 20px;position:absolute; bottom: 27px; top:40px" class="fas1"
              onclick="togglePasswordVisibility1('password')">
            <div class="error-password"><i class='bx bx-error-alt'></i></div>
          </div>

          <div class="btn-create-account-container">
            <button type="submit" name="submit" class="btn-create-account" id="login-button">Login</button>
          </div>

          <p id="forgot-password" style="display:none;font-size: 13px;">Forgot your password? <a
              href="forgot-password.php" style="color:rgb(0, 81, 255);">Click here.</a></p>

        </form>
      </div>

      <div class="already-account">
        <p>Don't have Account?<a id="registerLink" href="register.php"> Click to Register</a></p>
      </div>
  </main>

  <footer>
    <div class="footer-container">
      <div class="footer-content">
        <div class="footer-logo">
          <a href="#" class="logo">Plants.&#127804;</a>
        </div>
        <div class="footer-links">
          <ul>
            <li><a href="home.php">Home</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2024 Plants.. All Rights Reserved.</p>
      </div>
    </div>
  </footer>

  <script src="../assets/js/login.js"></script>

</body>
</html>
    <?php
    exit();
}

// ── POST: JSON login logic — ONLY the users table is used ─────────────────────

header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include 'db_connection.php';

// Already logged in?
if (isset($_SESSION['username'])) {
    echo json_encode(['success' => true, 'redirect' => 'home.php']);
    exit();
}

// Session-based lockout
if (!isset($_SESSION['global_lockout_time'])) $_SESSION['global_lockout_time'] = 0;
if (!isset($_SESSION['login_attempts']))       $_SESSION['login_attempts']      = 0;
if (!isset($_SESSION['lockout_duration']))     $_SESSION['lockout_duration']    = 15;
if (!isset($_SESSION['max_attempts']))         $_SESSION['max_attempts']        = 3;

if ($_SESSION['global_lockout_time'] > time()) {
    $countdown = $_SESSION['global_lockout_time'] - time();
    echo json_encode([
        'success'          => false,
        'message'          => 'Access Denied. Try Again in ' . $countdown . ' seconds',
        'lockout_duration' => $countdown
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parse input (JSON or form-encoded)
$input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

try {
    // Helper to log logins
    function logLoginAttempt($conn, $user_id, $username, $success) {
        try {
            $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $conn->prepare("INSERT INTO login_attempts (user_id, username, ip_address, success) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $username, $ip, $success ? 1 : 0]);
        } catch (PDOException $e) {
            error_log("Failed to log attempt: " . $e->getMessage());
        }
    }

    // ── Only the users table ──────────────────────────────────────────────────
    $stmt = $conn->prepare(
        'SELECT user_id, username, password, first_name, last_name, role_id, is_active, last_login
         FROM   users
         WHERE  username = :username
         LIMIT  1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        // Block admins silently — show the same generic error as a wrong password
        // (avoids leaking that the username belongs to an admin account)
        if (($user['role_id'] ?? 3) == 1 || (($user['role_id'] ?? 3) == 2)) {
            logLoginAttempt($conn, $user['user_id'], $username, false);
            echo json_encode([
                'success' => false,
                'message' => 'Incorrect username or password.'
            ]);
            exit();
        }

        // Block inactive users (pending OR deactivated)
        if (!$user['is_active']) {
            logLoginAttempt($conn, $user['user_id'], $username, false);
            if (empty($user['last_login'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Your account registration is currently pending admin approval.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact support.'
                ]);
            }
            exit();
        }

        // ── Successful regular-user login ─────────────────────────────────────
        $_SESSION['username']  = $username;
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $_SESSION['role_id']   = $user['role_id'] ?? 3;
        $_SESSION['logged_in'] = true;
        $_SESSION['role']      = 'User';

        logLoginAttempt($conn, $user['user_id'], $username, true);

        // Update last_login — still only the users table
        $upd = $conn->prepare('UPDATE users SET last_login = NOW() WHERE user_id = :id');
        $upd->execute([':id' => $user['user_id']]);

        unset($_SESSION['login_attempts'], $_SESSION['global_lockout_time'],
              $_SESSION['lockout_duration'], $_SESSION['max_attempts']);
        session_regenerate_id(true);

        // Check if user has security questions set up
        $q_check = $conn->prepare("SELECT COUNT(*) FROM user_security_answers WHERE user_id = :uid");
        $q_check->execute([':uid' => $user['user_id']]);
        $has_questions = $q_check->fetchColumn() > 0;

        // Force password change if default password is used
        $is_default_password = ($password === 'abcd1234');
        if ($is_default_password) {
            $_SESSION['must_change_password'] = true;
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Login successful. Redirecting...',
            'redirect' => ($has_questions && !$is_default_password) ? 'home.php' : 'complete-setup.php'
        ]);
        exit();

    } elseif ($user) {
        // ── Wrong password ────────────────────────────────────────────────────
        $_SESSION['login_attempts']++;
        $left = $_SESSION['max_attempts'] - $_SESSION['login_attempts'];

        logLoginAttempt($conn, $user['user_id'], $username, false);

        if ($_SESSION['login_attempts'] >= 9) {
            $_SESSION['lockout_duration']    = 60;
            $_SESSION['global_lockout_time'] = time() + 60;
            echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts! Locked out for 60 seconds!', 'lockout_duration' => 60]);
        } elseif ($_SESSION['login_attempts'] >= $_SESSION['max_attempts']) {
            $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
            echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts! Locked out for ' . $_SESSION['lockout_duration'] . ' seconds', 'lockout_duration' => $_SESSION['lockout_duration']]);
            $_SESSION['lockout_duration'] += 15;
            $_SESSION['max_attempts']     += 3;
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password. Attempts left: ' . $left]);
        }
        exit();

    } else {
        // ── Username not found ────────────────────────────────────────────────
        $_SESSION['login_attempts']++;
        $left = $_SESSION['max_attempts'] - $_SESSION['login_attempts'];

        logLoginAttempt($conn, null, $username, false);

        if ($_SESSION['login_attempts'] >= 9) {
            $_SESSION['lockout_duration']    = 60;
            $_SESSION['global_lockout_time'] = time() + 60;
            echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts! Locked out for 60 seconds', 'lockout_duration' => 60]);
        } elseif ($_SESSION['login_attempts'] >= $_SESSION['max_attempts']) {
            $_SESSION['global_lockout_time'] = time() + $_SESSION['lockout_duration'];
            echo json_encode(['success' => false, 'message' => 'Too many incorrect attempts! Locked out for ' . $_SESSION['lockout_duration'] . ' seconds', 'lockout_duration' => $_SESSION['lockout_duration']]);
            $_SESSION['lockout_duration'] += 15;
            $_SESSION['max_attempts']     += 3;
        } else {
            echo json_encode(['success' => false, 'message' => 'Username not found! Attempts left: ' . $left]);
        }
        exit();
    }

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    exit();
}
?>