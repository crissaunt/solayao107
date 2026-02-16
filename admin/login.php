<?php
// login.php
session_start();

// Include database connection
require_once __DIR__ . '/../php/db_connection.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            // Query to get user with role information
            $query = "SELECT u.*, r.role_name, r.role_id 
                      FROM users u 
                      JOIN roles r ON u.role_id = r.role_id 
                      WHERE u.username = :username AND u.is_active = true";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([':username' => $username]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Compare password with the one in database
                if ($password === $user['password']) { // In production, use password_verify()
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['role'] = $user['role_name'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login
                    $update_query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = :user_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([':user_id' => $user['user_id']]);
                    
                    // Log successful login
                    try {
                        $log_query = "INSERT INTO login_attempts (user_id, username, ip_address, success) 
                                     VALUES (:user_id, :username, :ip_address, true)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->execute([
                            ':user_id' => $user['user_id'],
                            ':username' => $username,
                            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);
                    } catch (PDOException $e) {
                        // Log table might not exist, continue anyway
                    }
                    
                    // Redirect based on role
                    if ($user['role_id'] == 1) {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: ../admin/dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Invalid password";
                    // Log failed login attempt
                    try {
                        $log_query = "INSERT INTO login_attempts (username, ip_address, success) 
                                     VALUES (:username, :ip_address, false)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->execute([
                            ':username' => $username,
                            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);
                    } catch (PDOException $e) {
                        // Log table might not exist, continue anyway
                    }
                }
            } else {
                $error = "User not found or inactive";
                // Log failed login attempt
                try {
                    $log_query = "INSERT INTO login_attempts (username, ip_address, success) 
                                 VALUES (:username, :ip_address, false)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_stmt->execute([
                        ':username' => $username,
                        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);
                } catch (PDOException $e) {
                    // Log table might not exist, continue anyway
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #f5f9f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 360px;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo a {
            font-size: 32px;
            font-weight: 600;
            color: #1a3b2a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .logo span {
            color: #5f9e6b;
            font-size: 24px;
        }

        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 30, 0, 0.08);
            border: 1px solid #e0f0d8;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a3b2a;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #1a3b2a;
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1px solid ;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2a6e3b;
            box-shadow: 0 0 0 2px rgba(42, 110, 59, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 0.7rem;
            background: #1a3b2a;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 0.5rem;
        }

        .login-btn:hover {
            background: #2a6e3b;
        }

        .error-message {
            background: #fff2f0;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 14px;
            text-align: center;
            border: 1px solid #ffcdd2;
        }

        .demo-info {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f5f9f5;
            border-radius: 6px;
            font-size: 12px;
        }

        .demo-info p {
            color: #1a3b2a;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .demo-info i {
            color: #5f9e6b;
            width: 16px;
            font-size: 14px;
        }

        .demo-info .note {
            color: #6b7c6b;
            font-size: 14px;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #d0e8c8;
        }

        .footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #6b7c6b;
            font-size: 14px;
        }

        /* Simple responsive */
        @media (max-width: 480px) {
            .login-box {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <a href="#">
                Plants.<span>🌿</span>
            </a>
        </div>

        <div class="login-box">
            <h1>Sign In</h1>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle" style="margin-right: 5px;"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>

                <button type="submit" class="login-btn">
                    Sign In
                </button>
            </form>

            <!-- <div class="demo-info">
                <p><i class="fas fa-user-tie"></i> <strong>Super Admin:</strong> superadmin / superadmin123</p>
                <p><i class="fas fa-user-cog"></i> <strong>Admin:</strong> admin / admin123</p>
                <div class="note">
                    <i class="fas fa-info-circle"></i> Use proper password hashing in production
                </div>
            </div> -->
        </div>

        <div class="footer">
            &copy; 2024 Plants. System
        </div>
    </div>
</body>
</html>