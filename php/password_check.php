<?php
header('Content-Type: application/json'); // Set the response type to JSON

$password = $_POST['password']; // Retrieve the password sent from JavaScript
$isValid = false;

include 'db_connection.php'; // Include your database connection file


// Query to retrieve all hashed passwords
$passwordcheck = "SELECT password FROM users";
$passwordResult = $conn->query($passwordcheck);

if ($passwordResult->num_rows > 0) {
    while ($passwordRow = $passwordResult->fetch_assoc()) {
        $password_stored = $passwordRow['password'];
        
        // Compare the input password with the stored hashed password
        if (password_verify($password, $password_stored)) {
            $isValid = true;
            break; // No need to check further
        }
    }
}

// Prepare the response in JSON format
$response = [
    'status' => $isValid ? 'duplicate' : 'unique',
    'message' => $isValid ? 'Password is already in use' : 'Password is unique'
];

echo json_encode($response); // Send JSON response back to JavaScript
?>
