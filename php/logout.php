<?php
// logout.php
session_start();

// Include database connection
include 'db_connection.php';

// Function to log logout attempts
function logLogout($conn, $user_id, $username) {
    try {
        // Get client IP address
        $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Log to activity_logs table
        $activity_query = "INSERT INTO activity_logs (table_name, action, performed_by, ip_address, user_agent, created_at) 
                          VALUES ('users', 'LOGOUT', :user_id, :ip_address, :user_agent, NOW())";
        $activity_stmt = $conn->prepare($activity_query);
        $activity_stmt->execute([
            ':user_id' => $user_id,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
        
        // Log to admin_activity_logs if user is admin (role_id 1 or 2)
        if ($user_id) {
            // Get user role for logging check
            $role_check = $conn->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
            $role_check->execute([':user_id' => $user_id]);
            $user_role_id = $role_check->fetchColumn();

            if ($user_role_id == 1 || $user_role_id == 2) {
                $admin_activity_query = "INSERT INTO admin_activity_logs 
                                        (admin_id, username, action_type, table_name, ip_address, user_agent, endpoint, method, status_code, created_at) 
                                        VALUES (:admin_id, :username, 'LOGOUT', 'users', :ip_address, :user_agent, '/logout.php', 'GET', 200, NOW())";
                $admin_activity_stmt = $conn->prepare($admin_activity_query);
                $admin_activity_stmt->execute([
                    ':admin_id' => $user_id, // admin_id is now user_id
                    ':username' => $username,
                    ':ip_address' => $ip_address,
                    ':user_agent' => $user_agent
                ]);
            }
        }
        
        // Log to system_activity_logs
        $system_log_query = "INSERT INTO system_activity_logs 
                            (user_id, actor_type, action, category, description, ip_address, user_agent, created_at) 
                            VALUES (:user_id, :actor_type, :action, :category, :description, :ip_address, :user_agent, NOW())";
        $system_log_stmt = $conn->prepare($system_log_query);
        
        // Determine actor_type
        $actor_type = 'user';
        if ($user_id) {
            $check_role = $conn->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
            $check_role->execute([':user_id' => $user_id]);
            $u_role_id = $check_role->fetchColumn();
            if ($u_role_id == 1 || $u_role_id == 2) {
                $actor_type = 'admin';
            }
        }
        
        $system_log_stmt->execute([
            ':user_id' => $user_id,
            ':actor_type' => $actor_type,
            ':action' => 'LOGOUT',
            ':category' => 'authentication',
            ':description' => "User $username logged out",
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
        
    } catch (PDOException $e) {
        // Log error but don't stop the logout process
        error_log("Failed to log logout: " . $e->getMessage());
    }
}

// Get user info before destroying session
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'unknown';

// Log the logout if user was logged in
if ($user_id) {
    logLogout($conn, $user_id, $username);
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>