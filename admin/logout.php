<?php
// logout.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

if (isset($_SESSION['user_id'])) {
    try {
        // Log the logout activity if activity_logs table exists
        $log_query = "INSERT INTO activity_logs (table_name, action, performed_by, ip_address, user_agent) 
                     VALUES (:table_name, :action, :performed_by, :ip_address, :user_agent)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([
            ':table_name' => 'users',
            ':action' => 'LOGOUT',
            ':performed_by' => $_SESSION['user_id'],
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        // Activity logs table might not exist, continue with logout anyway
    }
}

// Destroy the session
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to login page
header("Location: ../admin/login.php");
exit();
?>