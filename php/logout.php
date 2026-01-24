<?php
session_start();
session_destroy(); // Destroy the session
header("Location: ../html/login.html"); // Redirect to login page
exit();
?>
