<?php
// db_connection.php
$host = 'localhost';
$port = '5432'; // Default PostgreSQL port
$dbname = 'solayaodb';
$username = 'postgres'; // Default PostgreSQL username
$password = 'postgres'; // Your PostgreSQL password

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // echo "PostgreSQL connection successfuddddl!<br>";
} catch(PDOException $e) {
    die("PostgreSQL Connection failed: " . $e->getMessage());
}
?>