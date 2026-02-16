<?php
// generate_hash.php
$password = 'your_new_password_here'; // Change this to whatever password you want
echo "Password: " . $password . "\n";
echo "Hash: " . password_hash($password, PASSWORD_DEFAULT) . "\n";
?>