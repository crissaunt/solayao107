<?php
// logout.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Logout process continues below

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