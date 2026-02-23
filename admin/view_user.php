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
        FROM admin_activity_logs 
        WHERE record_id = :user_id::text AND table_name = 'users'
    ";
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->execute([':user_id' => $user_id]);
    $activity = $activity_stmt->fetch(PDO::FETCH_ASSOC);
    
    $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if (!empty($user['extension_name'])) {
        $full_name .= ' ' . $user['extension_name'];
    }
    ?>
    
    <div style="display: flex; align-items: center; gap: 1.25rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--gray-200);">
        <div style="width: 70px; height: 70px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 28px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
            <?php 
            echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) . 
                 strtoupper(substr($user['last_name'] ?? 'S', 0, 1));
            ?>
        </div>
        <div>
            <h2 style="color: var(--primary-dark); font-size: 1.5rem; margin: 0; font-weight: 700;"><?php echo htmlspecialchars($full_name ?: 'Unnamed User'); ?></h2>
            <div style="display: flex; gap: 0.75rem; margin-top: 0.5rem; align-items: center;">
                <span style="color: var(--secondary); font-size: 0.9rem;">@<?php echo htmlspecialchars($user['username'] ?? 'unknown'); ?></span>
                <span class="role-badge" style="margin: 0; transform: scale(0.9);"><?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?></span>
                <?php if ($user['is_active']): ?>
                <span class="badge badge-success" style="margin: 0; transform: scale(0.9);">Active</span>
                <?php else: ?>
                <span class="badge badge-danger" style="margin: 0; transform: scale(0.9);">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
        <div class="glass-card" style="padding: 1rem; border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-envelope" style="margin-right: 0.25rem;"></i> Email Address
            </div>
            <div style="font-size: 1rem; color: var(--primary-dark);"><?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></div>
        </div>
        
        <div class="glass-card" style="padding: 1rem; border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-id-card" style="margin-right: 0.25rem;"></i> ID Number
            </div>
            <div style="font-size: 1rem; color: var(--primary-dark);"><?php echo htmlspecialchars($user['id_number'] ?? 'Not provided'); ?></div>
        </div>
        
        <div class="glass-card" style="padding: 1rem; border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-birthday-cake" style="margin-right: 0.25rem;"></i> Personal Info
            </div>
            <div style="font-size: 1rem; color: var(--primary-dark);">
                <?php 
                if (!empty($user['birthday'])) {
                    echo date('M d, Y', strtotime($user['birthday']));
                    if (!empty($user['age'])) {
                        echo ' (' . $user['age'] . ' yrs)';
                    }
                } else {
                    echo 'No DOB';
                }
                echo ' • ' . ($user['sex'] ?? 'Unspecified');
                ?>
            </div>
        </div>
        
        <div class="glass-card" style="padding: 1rem; border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-phone" style="margin-right: 0.25rem;"></i> Contact
            </div>
            <div style="font-size: 1rem; color: var(--primary-dark);"><?php echo htmlspecialchars($user['contact_number'] ?? 'Not provided'); ?></div>
        </div>
        
        <div class="glass-card" style="grid-column: 1 / -1; padding: 1rem; border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-map-marker-alt" style="margin-right: 0.25rem;"></i> Address
            </div>
            <div style="font-size: 1rem; color: var(--primary-dark);">
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
        
        <div class="glass-card" style="padding: 1rem; border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-clock" style="margin-right: 0.25rem;"></i> Activity Status
            </div>
            <div style="font-size: 0.9rem; color: var(--primary-dark);">
                Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?><br>
                Last Login: <?php echo $user['last_login'] ? date('M d, H:i', strtotime($user['last_login'])) : 'Never'; ?>
            </div>
        </div>
        
        <div class="glass-card" style="padding: 1rem; border: 1px solid var(--gray-200); background: var(--gray-50);">
            <div style="font-size: 0.75rem; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-chart-line" style="margin-right: 0.25rem;"></i> Engagement
            </div>
            <div style="font-size: 0.9rem; color: var(--primary-dark);">
                <?php if ($activity['total_activities'] > 0): ?>
                <?php echo $activity['total_activities']; ?> total actions • <?php echo $activity['activities_30d']; ?> last 30d
                <?php else: ?>
                No activity recorded yet
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php
} catch (PDOException $e) {
    echo '<div class="alert alert-error">Database error occurred.</div>';
}
?>