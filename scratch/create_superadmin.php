<?php
require_once __DIR__ . '/../php/db_connection.php';

$username = 'cris24';
$password = 'FlorenceCris24()';
$email = 'cris24@example.com';
$id_number = 'SUPER-001';
$first_name = 'Cris';
$last_name = 'Superadmin';
$birthday = '1990-01-01';
$age = 34;
$sex = 'Male';
$contact_number = '09000000000';
$street_purok = 'Main St';
$barangay = 'Central';
$city_municipal = 'City';
$province = 'Province';
$country = 'Philippines';
$zipcode = '1234';
$role_id = 1; // Super Admin
$is_active = true;

try {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (
        username, password, email, id_number, first_name, last_name, 
        birthday, age, sex, contact_number, street_purok, barangay, 
        city_municipal, province, country, zipcode, role_id, is_active, created_at
    ) VALUES (
        :username, :password, :email, :id_number, :first_name, :last_name, 
        :birthday, :age, :sex, :contact_number, :street_purok, :barangay, 
        :city_municipal, :province, :country, :zipcode, :role_id, :is_active, NOW()
    )";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':username' => $username,
        ':password' => $hashed_password,
        ':email' => $email,
        ':id_number' => $id_number,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':birthday' => $birthday,
        ':age' => $age,
        ':sex' => $sex,
        ':contact_number' => $contact_number,
        ':street_purok' => $street_purok,
        ':barangay' => $barangay,
        ':city_municipal' => $city_municipal,
        ':province' => $province,
        ':country' => $country,
        ':zipcode' => $zipcode,
        ':role_id' => $role_id,
        ':is_active' => $is_active ? 'true' : 'false'
    ]);
    
    echo "Superadmin user '$username' created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
