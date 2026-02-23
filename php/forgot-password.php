<?php
// forgot-password.php — GET: show HTML form | POST: JSON password-reset logic

session_start();

// ── GET: show the forgot-password page ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect already-logged-in users
    if (isset($_SESSION['user_id'])) {
        if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) {
            header("Location: ../admin/dashboard.php");
        } else {
            header("Location: home.php");
        }
        exit();
    }
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Plants</title>
    <link rel="stylesheet" href="../assets/css/forgot-password.css">

</head>

<body>
    <header class="header">
        <div class="flex">
            <a href="home.php" class="logo">Plants.&#127804;</a>
            <nav class="navbar">
                <a href="home.php">Home</a>
                <a href="login.php">Login</a>
            </nav>
        </div>
    </header>

    <main class="wrapper">
        <div class="reset-container">
            <h2 style="text-align: center; margin-bottom: 30px;">Reset Your Password</h2>

            <div class="step-indicator">
                <div class="step active" id="step1">
                    <div class="step-number">1</div>
                    <div class="step-title">Enter Email</div>
                </div>
                <div class="step" id="step2">
                    <div class="step-number">2</div>
                    <div class="step-title">Verify OTP</div>
                </div>
                <div class="step" id="step3">
                    <div class="step-number">3</div>
                    <div class="step-title">Security Question</div>
                </div>
                <div class="step" id="step4">
                    <div class="step-number">4</div>
                    <div class="step-title">New Password</div>
                </div>
            </div>

            <!-- Step 1: Email Input -->
            <div id="step1-content" class="step-content">
                <p>Enter your registered email address. We'll send you a one-time password (OTP) to verify your
                    identity.</p>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" class="form-control" placeholder="your.email@example.com" required>
                    <div id="email-error" class="error-message"></div>
                    <div id="email-success" class="success-message"></div>
                </div>

                <button id="send-otp-btn" class="btn-reset" onclick="sendOTP()">Send OTP</button>

                <div class="demo-note">
                    <strong>Demo Note:</strong> Check your email inbox (and spam folder) for the OTP. If using Gmail
                    test account, OTP will be in the test email.
                </div>

                <div class="resend-link">
                    <p>Remember your password? <a href="login.php">Back to Login</a></p>
                </div>
            </div>

            <!-- Step 2: OTP Verification -->
            <div id="step2-content" class="step-content" style="display: none;">
                <p>Enter the 6-digit OTP sent to your email. The OTP will expire in 10 minutes.</p>

                <div class="form-group">
                    <label for="otp">OTP Code</label>
                    <input type="text" id="otp" class="form-control" placeholder="Enter 6-digit code" maxlength="6"
                        required>
                    <div id="otp-error" class="error-message"></div>
                    <div id="otp-timer" class="timer"></div>
                </div>

                <button id="verify-otp-btn" class="btn-reset" onclick="verifyOTP()">Verify OTP</button>

                <div class="resend-link">
                    <p>Didn't receive the OTP? <a href="#" onclick="resendOTP()" id="resend-link">Resend OTP</a></p>
                    <p id="resend-timer" style="display: none;">Resend available in: <span id="countdown">60</span>
                        seconds</p>
                </div>

                <div class="back-link">
                    <a href="#" onclick="goBackToEmail()">← Use a different email</a>
                </div>
            </div>

            <!-- Step 3: Security Questions -->
            <div id="step3-content" class="step-content" style="display: none;">
                <p>Select one of your security questions and provide the answer.</p>

                <div class="form-group">
                    <label for="security-question">Select Security Question</label>
                    <select id="security-question" class="form-control" required>
                        <option value="">-- Select a question --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="security-answer">Your Answer</label>
                    <input type="text" id="security-answer" class="form-control" placeholder="Enter your answer"
                        required>
                    <div id="answer-error" class="error-message"></div>
                    <div id="answer-attempts" class="attempts-display"></div>
                </div>

                <button id="verify-answer-btn" class="btn-reset" onclick="verifyAnswer()">Verify Answer</button>

                <div class="back-link">
                    <a href="#" onclick="goBackToEmail()">← Use a different email</a>
                </div>
            </div>

            <!-- Step 4: New Password -->
            <div id="step4-content" class="step-content" style="display: none;">
                <p>Create a new password for your account.</p>

                <div class="form-group">
                    <label for="new-password">New Password</label>
                    <input type="password" id="new-password" class="form-control" placeholder="Enter new password"
                        required>
                    <div class="password-requirements">
                        <div>Password must contain:</div>
                        <div class="requirement not-met" id="length-check">✓ At least 8 characters</div>
                        <div class="requirement not-met" id="uppercase-check">✓ One uppercase letter</div>
                        <div class="requirement not-met" id="lowercase-check">✓ One lowercase letter</div>
                        <div class="requirement not-met" id="number-check">✓ One number</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" class="form-control" placeholder="Confirm new password"
                        required>
                    <div id="confirm-error" class="error-message"></div>
                </div>

                <button id="update-password-btn" class="btn-reset" onclick="updatePassword()">Update Password</button>

                <div class="back-link">
                    <a href="#" onclick="goBackToEmail()">← Use a different email</a>
                </div>
            </div>

            <!-- Success Message -->
            <div id="success-content" class="step-content" style="display: none; text-align: center;">
                <div style="color: #4CAF50; font-size: 48px; margin: 20px 0;">✓</div>
                <h3>Password Reset Successful!</h3>
                <p>Your password has been updated successfully.</p>
                <p>You can now login with your new password.</p>
                <button class="btn-reset" onclick="window.location.href='login.php'">Go to Login</button>
            </div>

            <!-- Email Not Found Message -->
            <div id="email-not-found-content" class="step-content" style="display: none; text-align: center;">
                <div style="color: #f44336; font-size: 48px; margin: 20px 0;">✗</div>
                <h3>Email Not Found</h3>
                <p>The email address you entered is not registered with our system.</p>
                <div class="action-buttons">
                    <button class="btn-reset" onclick="goBackToEmail()">Try Different Email</button>
                    <button class="btn-secondary" onclick="window.location.href='register.php'">Create
                        Account</button>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-logo">
                    <a href="home.php" class="logo">Plants.&#127804;</a>
                </div>
                <div class="footer-links">
                    <ul>
                        <li><a href="home.php">Home</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Plants. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../assets/js/password-reset.js"></script>
</body>

</html>
    <?php
    exit();
}

// ── POST: JSON forgot-password logic ─────────────────────────────────────────

require_once 'db_connection.php';
require_once 'email-config.php';
require_once 'reset-logic.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => ''];

$data = json_decode(file_get_contents('php://input'), true);

// If JSON decode fails, try POST data
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (empty($data['email'])) {
    $response['message'] = 'Email is required';
    echo json_encode($response);
    exit();
}

$email = trim($data['email']);
$isResend = !empty($data['resend']);

try {
    $resetLogic = new PasswordReset($conn);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id, username, email FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = 'The email address "' . htmlspecialchars($email) . '" is not registered in our system.';
        $response['email_not_found'] = true;
        echo json_encode($response);
        exit();
    }
    
    // Check if user has security questions
    $checkQuestionsStmt = $conn->prepare("
        SELECT COUNT(*) as question_count 
        FROM user_security_answers 
        WHERE user_id = :user_id
    ");
    $checkQuestionsStmt->execute([':user_id' => $user['user_id']]);
    $questionResult = $checkQuestionsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($questionResult['question_count'] < 3) {
        $response['message'] = 'Your account is not set up for password reset. Please contact support.';
        echo json_encode($response);
        exit();
    }
    
    // Check rate limiting using the correct table
    $rateLimitStmt = $conn->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM password_reset_logs 
        WHERE email = :email 
        AND attempt_type = 'otp_request'
        AND attempt_time > NOW() - INTERVAL '1 hour'
    ");
    $rateLimitStmt->execute([':email' => $email]);
    $rateResult = $rateLimitStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rateResult['attempt_count'] >= EmailConfig::MAX_OTP_REQUESTS_PER_HOUR) {
        $response['message'] = 'Too many password reset attempts. Please try again in an hour.';
        echo json_encode($response);
        exit();
    }
    
    // Create reset session and generate OTP
    $sessionResult = $resetLogic->createResetSession($email, $user['user_id']);
    
    if ($sessionResult['success']) {
        // Calculate and return remaining time
        $remainingStmt = $conn->prepare("
            SELECT EXTRACT(EPOCH FROM (otp_expiry - NOW())) as remaining_seconds
            FROM password_reset_sessions 
            WHERE session_id = :session_id
        ");
        $remainingStmt->execute([':session_id' => $sessionResult['session_id']]);
        $remainingResult = $remainingStmt->fetch(PDO::FETCH_ASSOC);
        
        $response['success'] = true;
        $response['message'] = 'OTP sent to ' . $user['email'];
        $response['session_id'] = $sessionResult['session_id'];
        $response['remaining_seconds'] = floor($remainingResult['remaining_seconds']);

        // Send OTP email using EmailConfig
        error_log("Sending OTP email to: " . $user['email']);
        
        $emailSent = EmailConfig::sendOTP(
            $user['email'],
            $sessionResult['otp'],
            $user['username']
        );
        
        if ($emailSent) {
            $response['success'] = true;
            $response['message'] = 'OTP sent to ' . $user['email'];
            $response['session_id'] = $sessionResult['session_id'];
        } else {
            $response['message'] = 'Failed to send OTP email. Please try again or contact support.';
            error_log("Failed to send OTP email to: " . $user['email']);
        }
    } else {
        $response['message'] = $sessionResult['message'];
    }
    
} catch (PDOException $e) {
    error_log("Forgot password error: " . $e->getMessage());
    $response['message'] = 'A system error occurred. Please try again later.';
}

echo json_encode($response);
?>