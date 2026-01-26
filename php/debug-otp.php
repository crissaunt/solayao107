<?php
// debug-otp.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';
require_once 'reset-logic.php';

echo "<h1>OTP Session Debug</h1>";

// Step 1: Test forgot-password.php first
echo "<h2>Step 1: Test Forgot Password</h2>";

// Create a test session
$resetLogic = new PasswordReset($conn);
$testEmail = 'solayaoflorence@gmail.com';

// Check if user exists
$stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $testEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p style='color: red;'>✗ User not found with email: $testEmail</p>";
    exit();
}

echo "<p style='color: green;'>✓ User found: " . $user['username'] . " (ID: " . $user['user_id'] . ")</p>";

// Create a session
echo "<h3>Creating reset session...</h3>";
$sessionResult = $resetLogic->createResetSession($testEmail, $user['user_id']);

echo "<pre>";
print_r($sessionResult);
echo "</pre>";

if ($sessionResult['success']) {
    $sessionId = $sessionResult['session_id'];
    $otp = $sessionResult['otp'];
    
    echo "<p style='color: green;'>✓ Session created with ID: $sessionId</p>";
    echo "<p style='color: green;'>✓ OTP generated: <strong>$otp</strong></p>";
    
    // Step 2: Check if session is saved in database
    echo "<h2>Step 2: Check Database Session</h2>";
    
    $stmt = $conn->prepare("SELECT * FROM password_reset_sessions WHERE session_id = :session_id");
    $stmt->execute([':session_id' => $sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "<p style='color: green;'>✓ Session found in database</p>";
        echo "<pre>";
        print_r($session);
        echo "</pre>";
        
        echo "<h3>Session Details:</h3>";
        echo "<ul>";
        echo "<li>Session ID: " . $session['session_id'] . "</li>";
        echo "<li>User ID: " . $session['user_id'] . "</li>";
        echo "<li>Email: " . $session['email'] . "</li>";
        echo "<li>OTP Hash: " . substr($session['otp_hash'], 0, 50) . "...</li>";
        echo "<li>OTP Expiry: " . $session['otp_expiry'] . "</li>";
        echo "<li>Current Time: " . date('Y-m-d H:i:s') . "</li>";
        echo "<li>Is expired? " . (strtotime($session['otp_expiry']) < time() ? 'YES' : 'NO') . "</li>";
        echo "</ul>";
        
        // Step 3: Verify OTP
        echo "<h2>Step 3: Verify OTP</h2>";
        
        $verifyResult = $resetLogic->verifyOTP($sessionId, $otp);
        
        echo "<pre>";
        print_r($verifyResult);
        echo "</pre>";
        
        if ($verifyResult['success']) {
            echo "<p style='color: green; font-weight: bold;'>✓ OTP verification SUCCESSFUL!</p>";
            echo "<p>User ID returned: " . $verifyResult['user_id'] . "</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ OTP verification FAILED: " . $verifyResult['message'] . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Session NOT found in database!</p>";
        echo "<p>Possible issues:</p>";
        echo "<ul>";
        echo "<li>Database insert failed</li>";
        echo "<li>Table doesn't exist</li>";
        echo "<li>Connection error</li>";
        echo "</ul>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Failed to create session: " . $sessionResult['message'] . "</p>";
}

// Step 4: Check database tables
echo "<h2>Step 4: Check Database Tables</h2>";

$tables = ['password_reset_sessions', 'password_reset_logs', 'users', 'security_questions', 'user_security_answers'];

foreach ($tables as $table) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p style='color: green;'>✓ Table '$table' exists (" . $result['count'] . " rows)</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Table '$table' error: " . $e->getMessage() . "</p>";
    }
}

// Step 5: Test the exact same code as verify-otp.php
echo "<h2>Step 5: Direct Verification Test</h2>";

if (isset($sessionId) && isset($otp)) {
    echo "<p>Testing with session_id: $sessionId</p>";
    echo "<p>Testing with OTP: $otp</p>";
    
    $stmt = $conn->prepare("
        SELECT * FROM password_reset_sessions 
        WHERE session_id = :session_id 
        AND otp_expiry > NOW()
    ");
    
    $stmt->execute([':session_id' => $sessionId]);
    $directSession = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($directSession) {
        echo "<p style='color: green;'>✓ Direct query found session</p>";
        echo "<p>Session expiry: " . $directSession['otp_expiry'] . "</p>";
        echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
        
        // Verify OTP directly
        if (password_verify(strtoupper($otp), $directSession['otp_hash'])) {
            echo "<p style='color: green; font-weight: bold;'>✓ Direct OTP verification SUCCESS!</p>";
        } else {
            echo "<p style='color: red;'>✗ Direct OTP verification FAILED</p>";
            echo "<p>Testing hash verification...</p>";
            
            // Debug hash
            echo "<p>Stored hash: " . substr($directSession['otp_hash'], 0, 20) . "...</p>";
            echo "<p>OTP to verify: " . strtoupper($otp) . "</p>";
            echo "<p>password_verify result: " . (password_verify(strtoupper($otp), $directSession['otp_hash']) ? 'true' : 'false') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Direct query: Session not found or expired</p>";
        
        // Check without expiry
        $stmt2 = $conn->prepare("SELECT * FROM password_reset_sessions WHERE session_id = :session_id");
        $stmt2->execute([':session_id' => $sessionId]);
        $anySession = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($anySession) {
            echo "<p>Session exists but might be expired:</p>";
            echo "<pre>";
            print_r($anySession);
            echo "</pre>";
            
            $expiryTime = strtotime($anySession['otp_expiry']);
            $currentTime = time();
            echo "<p>Expiry timestamp: $expiryTime</p>";
            echo "<p>Current timestamp: $currentTime</p>";
            echo "<p>Time difference: " . ($expiryTime - $currentTime) . " seconds</p>";
        } else {
            echo "<p>Session doesn't exist at all in database</p>";
        }
    }
}

// Step 6: Create a simple test endpoint
echo "<h2>Step 6: Create Simple Test Endpoint</h2>";
?>
<form method="POST" action="test-simple-otp.php">
    <input type="hidden" name="test" value="1">
    <button type="submit">Run Simple Test</button>
</form>
<?php

echo "<hr>";
echo "<h3>Troubleshooting Checklist:</h3>";
echo "<ol>";
echo "<li>✓ Check if password_reset_sessions table exists</li>";
echo "<li>✓ Check if session is being inserted into database</li>";
echo "<li>✓ Check if OTP expiry time is in the future</li>";
echo "<li>✓ Verify database timezone matches PHP timezone</li>";
echo "<li>✓ Check if OTP hash verification is working</li>";
echo "<li>✓ Ensure the same session_id is being used for verification</li>";
echo "</ol>";
?>