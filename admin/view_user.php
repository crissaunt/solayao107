<?php
// view_user.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in and has admin access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    exit();
}

$role_id = $_SESSION['role_id'] ?? 0;
$is_admin = ($role_id <= 2); // Super Admin or Admin

if (!$is_admin) {
    http_response_code(403);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit();
}

$id = (int)$_GET['id'];

try {
    $query = "
        SELECT 
            u.*,
            r.role_name,
            r.role_description
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE u.user_id = :user_id
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([':user_id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo '<div class="alert alert-error">User not found.</div>';
        exit();
    }

    // Fetch security questions
    $q_stmt = $conn->prepare("
        SELECT q.question_text 
        FROM user_security_answers a
        JOIN security_questions q ON a.question_id = q.question_id
        WHERE a.user_id = :uid
    ");
    $q_stmt->execute([':uid' => $id]);
    $questions = $q_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Calculate age if not provided
    $age = $user['age'];
    if (!$age && !empty($user['birthday'])) {
        $birthDate = new DateTime($user['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
    }

    function renderField($label, $value) {
        return '<div class="log-field">
                    <span class="log-field-label">' . htmlspecialchars($label) . ':</span>
                    <span class="log-field-value">' . htmlspecialchars($value ?: 'Not provided') . '</span>
                </div>';
    }
    ?>
    
    <div class="detailed-log-view">
        <!-- Profile Header -->
        <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; padding: 1.5rem; background: #fcfdfc; border-radius: 12px; border: 1px solid #eef5eb;">
            <div style="width: 64px; height: 64px; background: #1e4d2d; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 24px; border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)); ?>
            </div>
            <div>
                <h2 style="color: #1a2a1f; font-size: 1.4rem; margin: 0; font-weight: 700;"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h2>
                <div style="display: flex; gap: 10px; margin-top: 5px; align-items: center;">
                    <span style="color: #6b7c6b; font-size: 0.9rem;">ID: <?php echo htmlspecialchars($user['id_number'] ?: 'N/A'); ?></span>
                    <span style="background: #eef5eb; color: #1e4d2d; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase;"><?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?></span>
                    <?php if ($user['is_active']): ?>
                        <span style="background: #e6f9ed; color: #1e7e34; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase;">Active</span>
                    <?php else: ?>
                        <span style="background: #fff5f5; color: #c53030; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase;">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 1. Account Information -->
        <div class="log-detail-section">
            <h4 class="log-section-title"><i class="fas fa-user-circle"></i> Account Information</h4>
            <div class="log-grid">
                <?php 
                echo renderField('ID Number', $user['id_number']);
                echo renderField('Username', $user['username']);
                echo renderField('Email', $user['email']);
                echo renderField('Contact Number', $user['contact_number']);
                ?>
            </div>
        </div>

        <!-- 2. Personal Information -->
        <div class="log-detail-section">
            <h4 class="log-section-title"><i class="fas fa-id-card"></i> Personal Information</h4>
            <div class="log-grid">
                <?php 
                echo renderField('First Name', $user['first_name']);
                echo renderField('Middle Name', $user['middle_name'] ?: 'None');
                echo renderField('Last Name', $user['last_name']);
                echo renderField('Extension Name', $user['extension_name'] ?: 'None');
                echo renderField('Birthday', !empty($user['birthday']) ? date('d/m/Y', strtotime($user['birthday'])) : '');
                echo renderField('Age', $age ? $age . ' years old' : '');
                echo renderField('Sex', ucwords($user['sex'] ?? ''));
                echo renderField('Password', '********');
                ?>
            </div>
        </div>

        <!-- 3. Address Information -->
        <div class="log-detail-section">
            <h4 class="log-section-title"><i class="fas fa-map-marker-alt"></i> Personal Address</h4>
            <div class="log-grid">
                <?php 
                echo renderField('Purok/Street', $user['street_purok']);
                echo renderField('Barangay', $user['barangay']);
                echo renderField('City/Municipal', $user['city_municipal']);
                echo renderField('Province', $user['province']);
                echo renderField('Country', $user['country']);
                echo renderField('Zip Code', $user['zipcode']);
                ?>
            </div>
        </div>

        <!-- 4. Security Questions -->
        <div class="log-detail-section">
            <h4 class="log-section-title"><i class="fas fa-shield-alt"></i> Security Questions</h4>
            <div class="log-grid">
                <?php if (!empty($questions)): ?>
                    <?php foreach ($questions as $i => $q): ?>
                        <div class="log-field" style="grid-column: span 2;">
                            <span class="log-field-label">Question <?php echo $i+1; ?>:</span>
                            <span class="log-field-value"><?php echo htmlspecialchars($q); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="log-field" style="grid-column: span 4;">
                        <span class="log-field-value" style="color: #8c9c8c; font-style: italic;">No security questions set.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php
} catch (PDOException $e) {
    echo '<div style="padding: 2rem; text-align: center; color: #c53030; background: #fff5f5; border-radius: 8px;">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>