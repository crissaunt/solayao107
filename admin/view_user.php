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

$user_id = (int)$_GET['id'];

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
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo '<div class="alert alert-error">User not found.</div>';
        exit();
    }
    
    // Get activity summary
    $activity_query = "
        SELECT 
            COUNT(*) as total_activities,
            COUNT(CASE WHEN created_at >= NOW() - INTERVAL '30 days' THEN 1 END) as activities_30d,
            MAX(created_at) as last_activity
        FROM activity_logs 
        WHERE performed_by = :user_id
    ";
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->execute([':user_id' => $user_id]);
    $activity = $activity_stmt->fetch(PDO::FETCH_ASSOC);
    
    $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (!empty($user['extension_name'])) {
        $full_name .= ' ' . $user['extension_name'];
    }
    ?>
    
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
        <div style="width: 60px; height: 60px; background: #2a6e3b; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 24px; border: 2px solid #a5cf9b; border-radius: 50%;">
            <?php 
            echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) . 
                 strtoupper(substr($user['last_name'] ?? 'S', 0, 1));
            ?>
        </div>
        <div>
            <h4 style="color: #1c4c29; font-size: 18px; margin: 0;"><?php echo htmlspecialchars($full_name ?: 'Unnamed User'); ?></h4>
            <p style="margin: 4px 0 0; color: #6b7c6b;">@<?php echo htmlspecialchars($user['username'] ?? 'unknown'); ?></p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Email</div>
            <div style="font-size: 14px;"><?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">ID Number</div>
            <div style="font-size: 14px;"><?php echo htmlspecialchars($user['id_number'] ?? 'Not provided'); ?></div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Role</div>
            <div style="font-size: 14px;"><?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?></div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Status</div>
            <div style="font-size: 14px;">
                <?php if ($user['is_active']): ?>
                <span style="color: #155724;">Active</span>
                <?php else: ?>
                <span style="color: #721c24;">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Birthday</div>
            <div style="font-size: 14px;">
                <?php 
                if (!empty($user['birthday'])) {
                    echo date('F j, Y', strtotime($user['birthday']));
                    if (!empty($user['age'])) {
                        echo ' (' . $user['age'] . ' years old)';
                    }
                } else {
                    echo 'Not provided';
                }
                ?>
            </div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Sex</div>
            <div style="font-size: 14px;"><?php echo htmlspecialchars($user['sex'] ?? 'Not specified'); ?></div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Contact Number</div>
            <div style="font-size: 14px;"><?php echo htmlspecialchars($user['contact_number'] ?? 'Not provided'); ?></div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Member Since</div>
            <div style="font-size: 14px;"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
        </div>
        
        <div style="grid-column: span 2; padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Address</div>
            <div style="font-size: 14px;">
                <?php 
                $address = array_filter([
                    $user['street_purok'] ?? '',
                    $user['barangay'] ?? '',
                    $user['city_municipal'] ?? '',
                    $user['province'] ?? '',
                    $user['country'] ?? ''
                ]);
                
                if (!empty($address)) {
                    echo htmlspecialchars(implode(', ', $address));
                    if (!empty($user['zipcode'])) {
                        echo ' ' . $user['zipcode'];
                    }
                } else {
                    echo 'No address provided';
                }
                ?>
            </div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Last Login</div>
            <div style="font-size: 14px;">
                <?php echo $user['last_login'] ? date('F j, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
            </div>
        </div>
        
        <div style="padding: 0.8rem; background: #f2f7f0; border-radius: 4px;">
            <div style="font-size: 12px; color: #6b7c6b;">Activity Summary</div>
            <div style="font-size: 14px;">
                <?php if ($activity['total_activities'] > 0): ?>
                <div><?php echo $activity['total_activities']; ?> total actions</div>
                <div><?php echo $activity['activities_30d']; ?> actions (30 days)</div>
                <?php if ($activity['last_activity']): ?>
                <div class="text-muted">Last: <?php echo date('M d, H:i', strtotime($activity['last_activity'])); ?></div>
                <?php endif; ?>
                <?php else: ?>
                <span>No activity recorded</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database error occurred.</div>';
}
?>