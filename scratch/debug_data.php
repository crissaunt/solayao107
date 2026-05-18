<?php
// Simple debug: Check what $data contains vs what register.php reads
require __DIR__ . '/../php/db_connection.php';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
$_POST = [
    'id' => '1234-5678',
    'fname' => 'Test',
    'lname' => 'User',
    'email' => 'testdebug@example.com',
    'username' => 'testdebug',
    'age' => '30',
];

// Replicate what register.php does
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    $data = $_POST;
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $data = $input ?? $_POST;
}

$id_number  = isset($data['id'])    ? trim($data['id'])    : '';
$first_name = isset($data['fname']) ? trim($data['fname']) : '';
$last_name  = isset($data['lname']) ? trim($data['lname']) : '';
$age        = isset($data['age']) && $data['age'] !== '' ? (int)$data['age'] : 0;

echo "id_number:  '$id_number'\n";
echo "first_name: '$first_name'\n";
echo "last_name:  '$last_name'\n";
echo "age:        $age\n";
