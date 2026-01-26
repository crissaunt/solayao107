<?php
// test-timezone-fix.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connection.php';

echo "<h1>Timezone Fix Test</h1>";

// Test 1: Check PHP timezone
echo "<h2>1. PHP Timezone</h2>";
echo "<p>PHP timezone: " . date_default_timezone_get() . "</p>";
echo "<p>PHP current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP timestamp: " . time() . "</p>";

// Test 2: Check Database timezone
echo "<h2>2. Database Timezone</h2>";
try {
    $stmt = $conn->query("SELECT NOW() as db_time, current_setting('TIMEZONE') as db_timezone");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Database timezone: " . $result['db_timezone'] . "</p>";
    echo "<p>Database current time: " . $result['db_time'] . "</p>";
    
    // Convert to timestamp
    $dbTimestamp = strtotime($result['db_time']);
    echo "<p>Database timestamp: " . $dbTimestamp . "</p>";
    
    $phpTimestamp = time();
    $difference = abs($phpTimestamp - $dbTimestamp);
    
    if ($difference > 60) { // More than 1 minute difference
        echo "<p style='color: red;'>✗ Timezone mismatch! Difference: $difference seconds</p>";
    } else {
        echo "<p style='color: green;'>✓ Timezones match (difference: $difference seconds)</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

// Test 3: Create and verify a session
echo "<h2>3. Session Time Test</h2>";

// Create test expiry time
$expiryTime = date('Y-m-d H:i:s', strtotime('+10 minutes'));
echo "<p>Test expiry time (PHP): $expiryTime</p>";
echo "<p>Expiry timestamp: " . strtotime($expiryTime) . "</p>";
echo "<p>Current timestamp: " . time() . "</p>";
echo "<p>Time until expiry: " . (strtotime($expiryTime) - time()) . " seconds</p>";

// Test 4: Insert and retrieve
echo "<h2>4. Database Insert/Retrieve Test</h2>";

$testSessionId = 'test_' . bin2hex(random_bytes(16));
$testUserId = 2;

try {
    // Insert with PHP time
    $insertStmt = $conn->prepare("
        INSERT INTO password_reset_sessions 
        (session_id, user_id, email, otp_hash, otp_expiry, created_at, updated_at) 
        VALUES (:session_id, :user_id, :email, :otp_hash, :otp_expiry, NOW(), NOW())
    ");
    
    $insertStmt->execute([
        ':session_id' => $testSessionId,
        ':user_id' => $testUserId,
        ':email' => 'test@example.com',
        ':otp_hash' => password_hash('TEST123', PASSWORD_BCRYPT),
        ':otp_expiry' => $expiryTime
    ]);
    
    echo "<p style='color: green;'>✓ Test session inserted</p>";
    
    // Retrieve and check
    $selectStmt = $conn->prepare("
        SELECT otp_expiry, NOW() as db_now 
        FROM password_reset_sessions 
        WHERE session_id = :session_id
    ");
    
    $selectStmt->execute([':session_id' => $testSessionId]);
    $session = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "<p>Stored expiry: " . $session['otp_expiry'] . "</p>";
        echo "<p>Database NOW(): " . $session['db_now'] . "</p>";
        
        // Compare
        $expiryTs = strtotime($session['otp_expiry']);
        $dbNowTs = strtotime($session['db_now']);
        
        echo "<p>Expiry timestamp: $expiryTs</p>";
        echo "<p>DB Now timestamp: $dbNowTs</p>";
        echo "<p>Time left: " . ($expiryTs - $dbNowTs) . " seconds</p>";
        
        if ($expiryTs > $dbNowTs) {
            echo "<p style='color: green; font-weight: bold;'>✓ Session is NOT expired (as expected)</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ Session is EXPIRED (problem!)</p>";
        }
    }
    
    // Clean up
    $cleanup = $conn->prepare("DELETE FROM password_reset_sessions WHERE session_id = :session_id");
    $cleanup->execute([':session_id' => $testSessionId]);
    echo "<p>Test session cleaned up</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

// Test 5: Direct comparison
echo "<h2>5. Direct Time Comparison</h2>";

$phpTime = date('Y-m-d H:i:s');
$stmt = $conn->query("SELECT NOW() as db_time");
$dbTime = $stmt->fetch(PDO::FETCH_ASSOC)['db_time'];

echo "<p>PHP time: $phpTime</p>";
echo "<p>DB time: $dbTime</p>";

$phpTs = strtotime($phpTime);
$dbTs = strtotime($dbTime);
$diff = abs($phpTs - $dbTs);

echo "<p>PHP timestamp: $phpTs</p>";
echo "<p>DB timestamp: $dbTs</p>";
echo "<p>Difference: $diff seconds</p>";

if ($diff > 300) { // 5 minutes
    echo "<p style='color: red; font-weight: bold;'>✗ CRITICAL: Timezone mismatch of $diff seconds!</p>";
    echo "<p>Fix by setting timezone in PHP and PostgreSQL:</p>";
    echo "<pre>";
    echo "// In PHP:\n";
    echo "date_default_timezone_set('Asia/Manila');\n\n";
    echo "// In db_connection.php:\n";
    echo "\$conn->exec(\"SET timezone = 'Asia/Manila'\");\n";
    echo "</pre>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✓ Timezones are synchronized</p>";
}

echo "<hr>";
echo "<h3>Quick Fix Summary:</h3>";
echo "<ol>";
echo "<li>Set timezone in PHP: <code>date_default_timezone_set('Asia/Manila');</code></li>";
echo "<li>Set timezone in PostgreSQL connection: <code>\$conn->exec(\"SET timezone = 'Asia/Manila'\");</code></li>";
echo "<li>Use PHP time for calculations, not database NOW()</li>";
echo "<li>Compare timestamps directly, not datetime strings</li>";
echo "</ol>";
?>