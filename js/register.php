<?php
header('Content-Type: application/json');

require 'db_connection.php';

if(isset($_SESSION['username'])){
    header('Location: home.php');
}


if ($_SERVER["REQUEST_METHOD"]== "POST") {
  
    $id_number = $_POST['id'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $username = $_POST['username'];
    $first_name = $_POST['fname'];
    $middle_name = $_POST['mname'];
    $last_name = $_POST['lname'];
    $extension_name = $_POST['extend_name'];
    $birthday = $_POST['birthday'];
    $age = $_POST['age'];
    $sex = $_POST['sex'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $street_purok = $_POST['street_purok'];
    $barangay = $_POST['barangay'];
    $city_municipal = $_POST['city_municipal'];
    $province = $_POST['province'];
    $country = $_POST['country'];
    $zipcode = $_POST['zipcode'];


    $sql_check = "SELECT * FROM users WHERE id_number = ? OR email = ? OR contact_number = ? OR username = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("ssss", $id_number, $email, $contact_number, $username);


    $stmt->execute();
    $result = $stmt->get_result();
    $exist_user = $result->fetch_assoc();

    if ($result->num_rows > 0) {

        if ($exist_user['id_number'] == $id_number) {
            
            echo json_encode(['status' => 'error', 'message' => 'ID is already taken']);
        }else if ($exist_user['email'] == $email) {
            echo json_encode(['status' => 'error', 'message' => 'Email is already in use']);
        }else if ($exist_user['contact_number'] == $contact_number) {
            echo json_encode(['status' => 'error', 'message' => 'Contact number is already in use']);
        }else if ($exist_user['username'] == $username) {
            echo json_encode(['status' => 'error', 'message' => 'Username is already in use']);
        }

        
    } else {
     
        $sql_insert = "INSERT INTO users (id_number, email, age,contact_number, username, first_name, middle_name, last_name, extension_name, birthday, sex, password, street_purok, barangay, city_municipal, province, country, zipcode) VALUES (?, ?, ?, ?,?, ?, ?, ?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("ssssssssssssssssss", $id_number, $email,$age, $contact_number,$username, $first_name, $middle_name, $last_name, $extension_name, $birthday, $sex, $password, $street_purok, $barangay, $city_municipal, $province, $country, $zipcode);
        
        if ($stmt_insert->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Your Account is Successfully Created']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error inserting data']);
        }
        
    }
     $stmt->close();
    $conn->close();
    
}
?>
