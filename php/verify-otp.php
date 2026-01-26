<?php
// verify-otp.php
date_default_timezone_set('Asia/Manila'); // ADD THIS
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';
require_once 'reset-logic.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable debug mode
$debug = isset($_GET['debug']) || isset($_POST['debug']);

if ($debug) {
    echo "<pre>";
    echo "=== DEBUG MODE ===\n";
    echo "PHP Timezone: " . date_default_timezone_get() . "\n";
    echo "PHP Current Time: " . date('Y-m-d H:i:s') . "\n";
    echo "PHP Timestamp: " . time() . "\n";
    echo "==================\n\n";
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If JSON decode fails, try POST data
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    
    if ($debug) {
        echo "Received Data:\n";
        print_r($data);
        echo "\n";
    }
    
    if (empty($data['session_id']) || empty($data['otp'])) {
        $response['message'] = 'Session ID and OTP are required';
        if ($debug) {
            echo "Response: ";
            print_r($response);
        } else {
            echo json_encode($response);
        }
        exit();
    }
    
    $sessionId = trim($data['session_id']);
    $otp = trim($data['otp']);
    
    if ($debug) {
        echo "Session ID: $sessionId\n";
        echo "OTP: $otp\n\n";
    }
    
    try {
        // Get database time for comparison
        $timeStmt = $conn->query("SELECT NOW() as db_time, EXTRACT(epoch FROM NOW()) as db_timestamp");
        $timeResult = $timeStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($debug) {
            echo "Database Time: " . $timeResult['db_time'] . "\n";
            echo "Database Timestamp: " . $timeResult['db_timestamp'] . "\n";
            echo "PHP Timestamp: " . time() . "\n";
            echo "Difference: " . (time() - $timeResult['db_timestamp']) . " seconds\n\n";
        }
        
        $resetLogic = new PasswordReset($conn);
        $verifyResult = $resetLogic->verifyOTP($sessionId, $otp);
        
        if ($debug) {
            echo "Verify Result:\n";
            print_r($verifyResult);
            echo "\n\n";
            
            // Also show session directly from database
            $checkStmt = $conn->prepare("SELECT * FROM password_reset_sessions WHERE session_id = :session_id");
            $checkStmt->execute([':session_id' => $sessionId]);
            $session = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                echo "Session from Database:\n";
                print_r($session);
                echo "\n";
                
                $expiryTs = strtotime($session['otp_expiry']);
                $currentTs = time();
                echo "Expiry Timestamp: $expiryTs\n";
                echo "Current Timestamp: $currentTs\n";
                echo "Time Left: " . ($expiryTs - $currentTs) . " seconds\n";
                echo "Is Expired? " . ($expiryTs <= $currentTs ? 'YES' : 'NO') . "\n";
            }
        }
        
        if ($verifyResult['success']) {
            $response['success'] = true;
            $response['message'] = 'OTP verified successfully';
            $response['user_id'] = $verifyResult['user_id'];
        } else {
            $response['message'] = $verifyResult['message'];
        }
        
    } catch (PDOException $e) {
        error_log("OTP verification error: " . $e->getMessage());
        $response['message'] = 'System error occurred: ' . $e->getMessage();
        
        if ($debug) {
            echo "Database Error: " . $e->getMessage() . "\n";
        }
    }
} else {
    $response['message'] = 'Invalid request method';
}

if ($debug) {
    echo "\nFinal Response:\n";
    print_r($response);
    echo "</pre>";
} else {
    echo json_encode($response);
}
?>