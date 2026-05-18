<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
$_POST = [
    'step' => 2,
    'id' => '1234-5678',
    'fname' => 'Test',
    'mname' => '',
    'lname' => 'User',
    'extend_name' => '',
    'email' => 'testuser' . rand(1,1000) . '@example.com',
    'username' => 'testuser' . rand(1,1000),
    'password' => 'Password123!!AA',
    'contact_number' => '09123456789',
    'birthday' => '1990-01-01',
    'age' => '30',
    'sex' => 'Male',
    'street_purok' => 'Purok 1',
    'barangay' => 'Barangay 1',
    'city_municipal' => 'City 1',
    'province' => 'Province 1',
    'country' => 'Philippines',
    'zipcode' => '1234',
    'security_question1' => '1',
    'security_answer1' => 'Smith',
    'security_question2' => '3',
    'security_answer2' => 'Butuan',
    'security_question3' => '2',
    'security_answer3' => 'Buddy'
];

ob_start();
try {
    require __DIR__ . '/../php/register.php';
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();
echo "OUTPUT:\n$output";
