<?php
// login.php
session_start();

// Include database connection
require_once __DIR__ . '/../php/db_connection.php';

// Redirect if already logged in - check for admin_id instead of user_id
if (isset($_SESSION['admin_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
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
            // Query ONLY the admin_users table
            $query = "SELECT * FROM admin_users 
                      WHERE username = :username AND is_active = true";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([':username' => $username]);
            
            if ($stmt->rowCount() > 0) {
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password using password_verify
                if (password_verify($password, $admin['password_hash'])) {
                    
                    // Set session variables - use admin_id as primary
                    $_SESSION['admin_id'] = $admin['admin_id'];
                    $_SESSION['user_id'] = $admin['admin_id']; // For backward compatibility
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['full_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                    $_SESSION['role'] = ucfirst(str_replace('_', ' ', $admin['role']));
                    $_SESSION['role_id'] = ($admin['role'] == 'super_admin') ? 1 : 2;
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login in admin_users table
                    $update_query = "UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE admin_id = :admin_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([':admin_id' => $admin['admin_id']]);
                    
                    // Log successful login to admin_login_attempts
                    try {
                        $log_query = "INSERT INTO admin_login_attempts (username, ip_address, success, created_at) 
                                     VALUES (:username, :ip_address, true, NOW())";
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->execute([
                            ':username' => $username,
                            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);
                    } catch (PDOException $e) {
                        // Log table might not exist, continue anyway
                        error_log("Login logging error: " . $e->getMessage());
                    }
                    
                    // Redirect to dashboard
                    header("Location: ../admin/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid password";
                    // Log failed login attempt
                    try {
                        $log_query = "INSERT INTO admin_login_attempts (username, ip_address, success, created_at) 
                                     VALUES (:username, :ip_address, false, NOW())";
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->execute([
                            ':username' => $username,
                            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);
                    } catch (PDOException $e) {
                        // Log table might not exist, continue anyway
                        error_log("Login logging error: " . $e->getMessage());
                    }
                }
            } else {
                $error = "Admin not found or inactive";
                // Log failed login attempt
                try {
                    $log_query = "INSERT INTO admin_login_attempts (username, ip_address, success, created_at) 
                                 VALUES (:username, :ip_address, false, NOW())";
                    $log_stmt = $conn->prepare($log_query);
                    $log_stmt->execute([
                        ':username' => $username,
                        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);
                } catch (PDOException $e) {
                    // Log table might not exist, continue anyway
                    error_log("Login logging error: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Login database error: " . $e->getMessage());
        }
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a3b2a 0%, #2c4a33 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo a {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .logo span {
            color: #a8e6a8;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.98);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(168, 230, 168, 0.3);
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1a3b2a;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #5f9e6b;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0f0d8;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #1a3b2a;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e0e8e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2a6e3b;
            box-shadow: 0 0 0 3px rgba(42, 110, 59, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 0.8rem;
            background: #1a3b2a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 0.5rem;
        }

        .login-btn:hover {
            background: #2a6e3b;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(26, 59, 42, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid #fcc;
        }

        .footer {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255,255,255,0.8);
            font-size: 0.85rem;
        }

        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* Simple responsive */
        @media (max-width: 480px) {
            .login-box {
                padding: 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <a href="#">
                Plants.<span>.🌿</span>
            </a>
        </div>

        <div class="login-box">
            <h1>Admin Login</h1>
            <div class="subtitle">Sign in to access the admin panel</div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle" style="margin-right: 5px;"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user" style="margin-right: 5px; color: #1a3b2a;"></i>
                        Username
                    </label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock" style="margin-right: 5px; color: #1a3b2a;"></i>
                        Password
                    </label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                    Sign In
                </button>
            </form>
        </div>

        <div class="footer">
            &copy; 2025 Plants. System Admin Panel
        </div>
    </div>
</body>
</html>