<?php
// admin/login.php
session_start();

// Include database connection
require_once __DIR__ . '/../php/db_connection.php';

// ── GET: show the login page ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect if already logged in - check for admin-related session variables
    if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2)) {
        header("Location: ../admin/dashboard.php");
        exit();
    }
?>
<?php
    // We will output the HTML below, but first we need to handle some GET messages
    $success_msg = '';
    if (isset($_GET['msg'])) {
        if ($_GET['msg'] === 'account_deactivated') {
            $success_msg = "Your account has been deactivated. An activation request has been automatically sent to a Super Admin.";
        } elseif ($_GET['msg'] === 'request_approved') {
            $success_msg = "Your account has been activated! You may now log in.";
        } elseif ($_GET['msg'] === 'request_denied') {
            $success_msg = "Your activation request was denied. Please contact a Super Admin for more information.";
        }
    }
} else {
    // ── POST: JSON login logic ──────────────────────────────────────────────────
    header('Content-Type: application/json');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

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

    // Parse input (JSON or form-encoded)
    $input    = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }

    try {
        if (!function_exists('logLoginAttempt')) {
            function logLoginAttempt($conn, $user_id, $username, $success) {
                try {
                    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $stmt = $conn->prepare("INSERT INTO login_attempts (user_id, username, ip_address, success) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $username, $ip, $success ? 1 : 0]);
                } catch (PDOException $e) {
                    error_log("Failed to log admin login: " . $e->getMessage());
                }
            }
        }

        // Query the unified 'users' table and check for admin roles (1 or 2)
        $query = "SELECT u.*, r.role_name 
                  FROM users u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE u.username = :username AND u.role_id IN (1, 2)
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            
            if (!$user['is_active']) {
                logLoginAttempt($conn, $user['user_id'], $username, false);
                
                // Account deactivated — auto-send activation request
                $request_sent = false;
                try {
                    $check_stmt = $conn->prepare(
                        "SELECT request_id FROM activation_requests WHERE user_id = :uid AND status = 'pending'"
                    );
                    $check_stmt->execute([':uid' => $user['user_id']]);
                    if (!$check_stmt->fetch()) {
                        $ins_stmt = $conn->prepare(
                            "INSERT INTO activation_requests (user_id, status, created_at) VALUES (:uid, 'pending', NOW())"
                        );
                        $ins_stmt->execute([':uid' => $user['user_id']]);
                    }
                    $request_sent = true;
                } catch (PDOException $re) {
                    // Table may not exist yet — silently continue
                }

                echo json_encode([
                    'success' => false,
                    'message' => $request_sent ? 'deactivated_request_sent' : 'deactivated'
                ]);
                exit();
            }

            // Successful login
            logLoginAttempt($conn, $user['user_id'], $username, true);
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['admin_id'] = $user['user_id']; // For compatibility with older checks
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['permissions'] = json_decode($user['permissions'] ?? '[]', true);
            $_SESSION['logged_in'] = true;
            
            $update_query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = :user_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute([':user_id' => $user['user_id']]);

            unset($_SESSION['login_attempts'], $_SESSION['global_lockout_time'],
                  $_SESSION['lockout_duration'], $_SESSION['max_attempts']);
            session_regenerate_id(true);

            // Check if user has security questions set up
            $q_check = $conn->prepare("SELECT COUNT(*) FROM user_security_answers WHERE user_id = :uid");
            $q_check->execute([':uid' => $user['user_id']]);
            $has_questions = $q_check->fetchColumn() > 0;

            // Force password change if default password is used
            $is_default_password = ($password === 'FFll24()');
            if ($is_default_password) {
                $_SESSION['must_change_password'] = true;
            }

            echo json_encode([
                'success' => true, 
                'redirect' => ($has_questions && !$is_default_password) ? '../admin/dashboard.php' : '../php/complete-setup.php'
            ]);
            exit();

        } elseif ($user) {
            // Wrong password
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
            // Username not found or not an admin
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
                echo json_encode(['success' => false, 'message' => 'Admin account not found! Attempts left: ' . $left]);
            }
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        body {
            background: url('../images/bg-plants.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #333;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            z-index: 1;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 2;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo a {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1b4d2d;
            text-decoration: none;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .login-box {
            background: white;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
        }

        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #444;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
            background: #fafafa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1b4d2d;
            background: #fff;
        }

        .login-btn {
            width: 100%;
            padding: 0.75rem;
            background: #1b4d2d;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 1rem;
        }

        .login-btn:hover {
            background: #143a22;
        }

        .error-message {
            background: #fff5f5;
            color: #c53030;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.8rem;
            text-align: center;
            border: 1px solid #feb2b2;
        }

        .success-message {
            background: #f0fff4;
            color: #276749;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.8rem;
            text-align: center;
            border: 1px solid #c6f6d5;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <a href="#">Plants</a>
        </div>

        <div class="login-box">
            <h1>Admin Login</h1>
            <div class="subtitle">Enter your credentials</div>
            
            <div id="error-message" class="error-message" style="display: none;"></div>
            
            <?php if ($success_msg): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           placeholder="Username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <div class="error" style="color: #c53030; font-size: 0.75rem; margin-top: 0.25rem; display: none;"></div>
                </div>

                <div class="form-group" style="position: relative;">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Password">
                    <i id="togglePassword" class="fas fa-eye" 
                       style="position: absolute; right: 10px; top: 35px; cursor: pointer; color: #666;"></i>
                    <div class="error-password" style="color: #c53030; font-size: 0.75rem; margin-top: 0.25rem; display: none;"></div>
                </div>

                <button type="submit" id="login-button" class="login-btn">Sign In</button>
            </form>
        </div>

        <div class="footer" style="text-align: center; margin-top: 1.5rem; color: #666; font-size: 0.8rem;">
            &copy; <?php echo date('Y'); ?> Plants. Admin Panel
        </div>
    </div>

    <script>
        let loginAttempts = 0;
        let countdownInterval;

        function handleLockout(lockoutDuration) {
            if (!lockoutDuration) return;

            const now = Math.floor(Date.now() / 1000);
            const lockoutEndTime = now + lockoutDuration;

            const countdownElement = document.getElementById('error-message');
            const loginButton = document.getElementById('login-button');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            loginButton.disabled = true;
            usernameInput.disabled = true;
            passwordInput.disabled = true;

            localStorage.setItem('adminLockoutEndTime', lockoutEndTime);

            clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                const currentTime = Math.floor(Date.now() / 1000);
                const countdown = lockoutEndTime - currentTime;

                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    countdownElement.innerText = '';
                    countdownElement.style.display = 'none';

                    loginButton.disabled = false;
                    usernameInput.disabled = false;
                    passwordInput.disabled = false;

                    localStorage.removeItem('adminLockoutEndTime');
                } else {
                    countdownElement.style.display = 'block';
                    countdownElement.innerText = `Access Denied. Try Again in ${countdown} seconds`;
                }
            }, 1000);
        }

        function handleLogin(event) {
            event.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const errorMsg = document.getElementById('error-message');
            const errorUser = document.querySelector('.error');
            const errorPass = document.querySelector('.error-password');

            // Reset errors
            errorUser.style.display = 'none';
            errorPass.style.display = 'none';
            errorMsg.style.display = 'none';

            // Client-side validation
            let hasError = false;
            if (!username) {
                errorUser.innerText = 'Username is required';
                errorUser.style.display = 'block';
                hasError = true;
            } else if (username.length < 1 || username.length > 15) {
                errorUser.innerText = 'Username must be between 1 and 15 characters';
                errorUser.style.display = 'block';
                hasError = true;
            }

            if (!password) {
                errorPass.innerText = 'Password is required';
                errorPass.style.display = 'block';
                hasError = true;
            }

            if (hasError) return;

            fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ username, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    loginAttempts++;
                    
                    if (data.message === 'deactivated_request_sent') {
                        errorMsg.innerHTML = '<strong>⚠️ Account Deactivated</strong><br>Your activation request has been <strong>automatically sent</strong> to a Super Admin.';
                        errorMsg.style.background = '#fff8e1';
                        errorMsg.style.color = '#856404';
                        errorMsg.style.borderColor = '#ffc107';
                    } else if (data.message === 'deactivated') {
                        errorMsg.innerHTML = '<strong>⚠️ Account Deactivated</strong><br>Your account is deactivated. Please contact a Super Admin.';
                        errorMsg.style.background = '#fff8e1';
                        errorMsg.style.color = '#856404';
                        errorMsg.style.borderColor = '#ffc107';
                    } else {
                        errorMsg.innerText = data.message;
                        errorMsg.style.background = '#fff5f5';
                        errorMsg.style.color = '#c53030';
                        errorMsg.style.borderColor = '#feb2b2';
                    }
                    errorMsg.style.display = 'block';

                    if (data.lockout_duration) {
                        handleLockout(parseInt(data.lockout_duration));
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorMsg.innerText = 'An error occurred. Please try again.';
                errorMsg.style.display = 'block';
            });
        }

        // Toggle Password Visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });

        const loginForm = document.getElementById('loginForm');
        loginForm.addEventListener('submit', handleLogin);

        // Check for existing lockout on page load
        window.addEventListener('load', () => {
            const lockoutEndTime = localStorage.getItem('adminLockoutEndTime');
            if (lockoutEndTime) {
                const now = Math.floor(Date.now() / 1000);
                const remainingTime = lockoutEndTime - now;
                if (remainingTime > 0) {
                    handleLockout(remainingTime);
                } else {
                    localStorage.removeItem('adminLockoutEndTime');
                }
            }
        });
    </script>
</body>
</html>