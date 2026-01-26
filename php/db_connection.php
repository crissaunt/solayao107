<?php
// db_connection.php
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$port = '5432';
$dbname = 'solayaodb';
$username = 'postgres';
$password = 'postgres';

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set PostgreSQL timezone to match PHP
    $conn->exec("SET timezone = 'Asia/Manila'");
    
} catch(PDOException $e) {
    die("PostgreSQL Connection failed: " . $e->getMessage());
}
?>