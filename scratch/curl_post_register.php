<?php
$data = [
    'step' => 2,
    'id' => '1234-5678',
    'fname' => 'Test',
    'lname' => 'User',
    'email' => 'testuser' . rand(1,1000) . '@example.com',
    'username' => 'testuser' . rand(1,1000),
    'password' => 'Password123!!AA',
    'contact_number' => '09123456789',
    'birthday' => '1990-01-01',
    'age' => 30,
    'sex' => 'Male',
    'street_purok' => 'Purok 1',
    'barangay' => 'Barangay 1',
    'city_municipal' => 'City 1',
    'province' => 'Province 1',
    'country' => 'Philippines',
    'zipcode' => '1234',
    'security_question1' => 1,
    'security_answer1' => 'Smith',
    'security_question2' => 3,
    'security_answer2' => 'Butuan',
    'security_question3' => 2,
    'security_answer3' => 'Buddy'
];

$ch = curl_init('http://localhost:8000/php/register.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
// simulate multipart/form-data
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

$response = curl_exec($ch);
if(curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch);
} else {
    echo "RESPONSE:\n" . $response;
}
curl_close($ch);
