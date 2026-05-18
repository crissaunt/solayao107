<?php
// php/complete-setup.php
session_start();
require_once __DIR__ . '/db_connection.php';

// Must be logged in to complete setup
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Determine if password change is mandatory
$must_change_password = isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] === true;

// If they have questions AND don't need to change password, they don't need to be here
if ($has_questions && !$must_change_password) {
    header("Location: home.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['new_password'] ?? '';
    
    // Only require questions if they don't have them
    $q1 = $has_questions ? 1 : (int)($_POST['security_question1'] ?? 0);
    $a1 = $has_questions ? 'placeholder' : trim($_POST['security_answer1'] ?? '');
    $q2 = $has_questions ? 2 : (int)($_POST['security_question2'] ?? 0);
    $a2 = $has_questions ? 'placeholder' : trim($_POST['security_answer2'] ?? '');
    $q3 = $has_questions ? 3 : (int)($_POST['security_question3'] ?? 0);
    $a3 = $has_questions ? 'placeholder' : trim($_POST['security_answer3'] ?? '');

    $errors = [];
    if (empty($password)) $errors[] = "New password is required";
    if ($password === 'FFll24()') $errors[] = "You must choose a password different from the default one";
    
    if (!$has_questions) {
        if ($q1 <= 0 || empty($a1)) $errors[] = "Security Question 1 is required";
        if ($q2 <= 0 || empty($a2)) $errors[] = "Security Question 2 is required";
        if ($q3 <= 0 || empty($a3)) $errors[] = "Security Question 3 is required";
        if ($q1 == $q2 || $q1 == $q3 || $q2 == $q3) $errors[] = "Please select three different security questions";
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // 1. Update Password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = :pass WHERE user_id = :uid");
            $upd->execute([':pass' => $hashed, ':uid' => $user_id]);

            // 2. Insert Security Answers (only if they didn't have them)
            if (!$has_questions) {
                $ans_stmt = $conn->prepare("INSERT INTO user_security_answers (user_id, question_id, answer_hash) VALUES (:uid, :qid, :hash)");
                $answers = [
                    ['qid' => $q1, 'ans' => $a1],
                    ['qid' => $q2, 'ans' => $a2],
                    ['qid' => $q3, 'ans' => $a3]
                ];
                foreach ($answers as $a) {
                    $hash = password_hash(strtolower(trim($a['ans'])), PASSWORD_BCRYPT);
                    $ans_stmt->execute([':uid' => $user_id, ':qid' => $a['qid'], ':hash' => $hash]);
                }
            }

            $conn->commit();
            unset($_SESSION['must_change_password']);
            $_SESSION['success_message'] = "Account setup complete!";
            
            // Redirect based on role
            $role_id = $_SESSION['role_id'] ?? 3;
            if ($role_id == 1 || $role_id == 2) {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: home.php");
            }
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Error saving data: " . $e->getMessage();
        }
    }
}

// Get available questions
$questions = [];
try {
    $q_stmt = $conn->query("SELECT * FROM security_questions ORDER BY question_text");
    $questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Account Setup - Plants</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary: #2a6e3b;
            --primary-dark: #1b4d2d;
            --secondary: #666;
            --error: #dc3545;
            --bg: #f4f7f6;
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: var(--bg); 
            color: #333; 
            margin: 0; 
            padding: 0; 
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-container { 
            width: 95%;
            max-width: 1100px; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            overflow: hidden; 
            display: flex;
            flex-direction: column;
            max-height: 95vh;
        }
        .setup-header { background: var(--primary); color: white; padding: 15px 30px; text-align: center; }
        .setup-header h1 { margin: 0; font-size: 1.5rem; }
        .setup-header p { margin: 5px 0 0; opacity: 0.9; font-size: 0.9rem; }
        
        .setup-body { 
            padding: 20px 30px; 
            overflow-y: auto; 
            flex: 1;
        }
        
        .setup-grid {
            display: grid;
            grid-template-columns: <?php echo !$has_questions ? '1fr 1.5fr' : '1fr'; ?>;
            gap: 30px;
            align-items: start;
        }

        .form-section { margin-bottom: 0; }
        .section-title { font-weight: bold; color: var(--primary-dark); margin-bottom: 15px; display: flex; align-items: center; font-size: 1.1rem; }
        .section-title i { margin-right: 10px; }
        
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.85rem; }
        .input-group input, .input-group select { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            box-sizing: border-box; 
            font-size: 0.95rem; 
            transition: border-color 0.3s; 
        }
        .input-group input:focus, .input-group select:focus { outline: none; border-color: var(--primary); }
        
        .password-wrapper { position: relative; }
        .toggle-pass { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888; }
        
        .error-list { background: #fff5f5; border-left: 4px solid var(--error); color: var(--error); padding: 10px 15px; margin-bottom: 20px; border-radius: 4px; font-size: 0.9rem; }
        .error-list ul { margin: 0; padding-left: 20px; }
        
        .btn-submit { 
            display: block; 
            width: 100%; 
            padding: 12px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 6px; 
            font-size: 1rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: background 0.3s; 
            margin-top: 20px;
        }
        .btn-submit:hover { background: var(--primary-dark); }
        
        .security-q-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .security-q-item { 
            background: #fafafa; 
            padding: 15px; 
            border-radius: 8px; 
            border: 1px solid #eee; 
        }

        @media (max-width: 900px) {
            .setup-grid { grid-template-columns: 1fr; }
            .setup-container { height: auto; max-height: 100vh; }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>Complete Your Account Setup</h1>
            <p>Welcome to Plants! Please secure your account to continue.</p>
        </div>
        <div class="setup-body">
            <?php if (!empty($errors)): ?>
                <div class="error-list">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="setup-grid">
                    <!-- Left Side: Password -->
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-lock"></i> Set New Password</div>
                        <div class="input-group">
                            <label for="new_password">New Permanent Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required>
                                <i class="fas fa-eye toggle-pass" onclick="togglePass('new_password', this)"></i>
                            </div>
                        </div>
                        <div style="font-size: 0.8rem; color: #666; background: #e8f5e9; padding: 10px; border-radius: 6px;">
                            <strong>Requirements:</strong><br>
                            • 8-30 characters<br>
                            • 2 Uppercase, 2 Lowercase<br>
                            • 2 Numbers, 2 Special Characters
                        </div>
                    </div>

                    <!-- Right Side: Security Questions -->
                    <?php if (!$has_questions): ?>
                    <div class="form-section">
                        <div class="section-title"><i class="fas fa-shield-alt"></i> Security Questions</div>
                        <div class="security-q-grid">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="security-q-item">
                                <div class="input-group">
                                    <label>Question <?php echo $i; ?></label>
                                    <select name="security_question<?php echo $i; ?>" required>
                                        <option value="">Select...</option>
                                        <?php foreach ($questions as $q): ?>
                                            <option value="<?php echo $q['question_id']; ?>"><?php echo htmlspecialchars($q['question_text']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="input-group" style="margin-bottom: 0;">
                                    <label>Answer <?php echo $i; ?></label>
                                    <div class="password-wrapper">
                                        <input type="password" name="security_answer<?php echo $i; ?>" placeholder="Answer..." required>
                                        <i class="fas fa-eye toggle-pass" onclick="togglePass(this.previousElementSibling)"></i>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-submit">Complete Setup & Continue</button>
            </form>
        </div>
    </div>

    <script>
        function togglePass(idOrEl, btn) {
            const input = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
            const icon = btn || event.target;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
