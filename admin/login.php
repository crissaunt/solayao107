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
            // Updated to query the unified 'users' table and check for admin roles (1 or 2)
            $query = "SELECT u.*, r.role_name 
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      WHERE u.username = :username AND u.is_active = true AND u.role_id IN (1, 2)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([':username' => $username]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password using password_verify (mapped to 'password' column in users table)
                if (password_verify($password, $user['password'])) {
                    
                    // Standardize session variables across the entire system
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['role'] = $user['role_name'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login in users table
                    $update_query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = :user_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([':user_id' => $user['user_id']]);
                    
                    // Redirect to dashboard
                    header("Location: ../admin/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "Admin account not found or access denied";
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

        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #888;
            font-size: 0.75rem;
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
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Password">
                </div>

                <button type="submit" class="login-btn">Sign In</button>
            </form>
        </div>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> Plants. Admin Panel
        </div>
    </div>
</body>
</html>