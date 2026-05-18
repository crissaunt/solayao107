<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';

    $duplicates = [];

    try {
        if (!empty($id)) {
            $stmt = $conn->prepare("SELECT id_number FROM users WHERE id_number = :id_number");
            $stmt->execute([':id_number' => $id]);
            if ($stmt->fetch()) {
                $duplicates['id'] = 'ID number already exists';
            }
        }

        if (!empty($email)) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $duplicates['email'] = 'Email already exists';
            }
        }

        if (!empty($username)) {
            $stmt = $conn->prepare("SELECT username FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            if ($stmt->fetch()) {
                $duplicates['username'] = 'Username already exists';
            }
        }

        if (!empty($contact_number)) {
            $stmt = $conn->prepare("SELECT contact_number FROM users WHERE contact_number = :contact_number");
            $stmt->execute([':contact_number' => $contact_number]);
            if ($stmt->fetch()) {
                $duplicates['contact_number'] = 'Contact number already exists';
            }
        }

        if (!empty($duplicates)) {
            echo json_encode(['status' => 'exists', 'errors' => $duplicates]);
        } else {
            echo json_encode(['status' => 'available']);
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
