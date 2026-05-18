<?php
// logs.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit();
}

// Only Super Admin and Admin can view logs
if ($_SESSION['role_id'] > 2) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? 0;
$is_super_admin = ($role_id == 1);
$is_admin = ($role_id <= 2); // Super Admin or Admin

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filter
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filter_role = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

try {
    // Build UNION query to get logs from all tables
    $base_queries = [];
    $count_queries = [];
    $params = [];
    $param_counter = 1;

    // 1. Activity logs table
    $q1 = "SELECT 
            a.created_at,
            a.action_type as action,
            a.table_name,
            a.record_id::text as record_id,
            a.ip_address::text as ip_address,
            a.user_agent,
            u.username,
            u.first_name,
            u.last_name,
            a.old_data,
            a.new_data,
            NULL as description,
            'activity_log' as source,
            a.admin_id as user_id,
            r.role_name,
            r.role_id
          FROM admin_activity_logs a 
          LEFT JOIN users u ON a.admin_id = u.user_id 
          LEFT JOIN roles r ON u.role_id = r.role_id
          WHERE a.action_type != 'TOGGLE_STATUS'";

    // 2. Login attempts table
    $q2 = "SELECT 
            la.attempt_time as created_at,
            CASE WHEN la.success THEN 'LOGIN_SUCCESS' ELSE 'LOGIN_FAILED' END as action,
            'login_attempts' as table_name,
            la.attempt_id::text as record_id,
            la.ip_address::text as ip_address,
            NULL as user_agent,
            COALESCE(u.username, la.username) as username,
            u.first_name,
            u.last_name,
            NULL as old_data,
            NULL as new_data,
            CASE WHEN la.success THEN 'Successful login' ELSE 'Failed login attempt' END as description,
            'login_attempt' as source,
            la.user_id,
            r.role_name,
            r.role_id
          FROM login_attempts la
          LEFT JOIN users u ON la.user_id = u.user_id
          LEFT JOIN roles r ON u.role_id = r.role_id
          WHERE 1=1";

    // 3. Password reset logs
    $q3 = "SELECT 
            prl.attempt_time as created_at,
            'PASSWORD_RESET' as action,
            'password_reset' as table_name,
            prl.log_id::text as record_id,
            prl.ip_address::text as ip_address,
            prl.user_agent,
            COALESCE(u.username, prl.email) as username,
            u.first_name,
            u.last_name,
            NULL as old_data,
            NULL as new_data,
            prl.details as description,
            'password_reset' as source,
            prl.user_id,
            r.role_name,
            r.role_id
          FROM password_reset_logs prl
          LEFT JOIN users u ON prl.user_id = u.user_id
          LEFT JOIN roles r ON u.role_id = r.role_id
          WHERE prl.attempt_type = 'password_update' AND prl.success = true";

    // 4. System activity logs (authentication events)
    $q4 = "SELECT 
            sal.created_at,
            sal.action,
            sal.category as table_name,
            sal.record_identifier as record_id,
            sal.ip_address::text as ip_address,
            sal.user_agent,
            u.username,
            u.first_name,
            u.last_name,
            NULL as old_data,
            NULL as new_data,
            sal.description,
            'system_log' as source,
            sal.user_id,
            r.role_name,
            r.role_id
          FROM system_activity_logs sal
          LEFT JOIN users u ON sal.user_id = u.user_id
          LEFT JOIN roles r ON u.role_id = r.role_id
          WHERE sal.category = 'authentication'";

    // 5. User activity logs
    $q5 = "SELECT 
            al.created_at,
            al.action,
            al.table_name,
            al.record_id::text as record_id,
            al.ip_address::text as ip_address,
            al.user_agent,
            u.username,
            u.first_name,
            u.last_name,
            al.old_data,
            al.new_data,
            NULL as description,
            'user_activity_log' as source,
            al.performed_by as user_id,
            r.role_name,
            r.role_id
          FROM activity_logs al
          LEFT JOIN users u ON al.performed_by = u.user_id
          LEFT JOIN roles r ON u.role_id = r.role_id
          WHERE al.action != 'TOGGLE_STATUS'";

    // Apply filters to each query
    $filter_conditions = [];
    
    if ($filter_action) {
        $filter_conditions[] = " action = :action" . $param_counter;
        $params[':action' . $param_counter] = $filter_action;
        $param_counter++;
    }
    
    if ($filter_user > 0) {
        $filter_conditions[] = " user_id = :user_id" . $param_counter;
        $params[':user_id' . $param_counter] = $filter_user;
        $param_counter++;
    }
    
    if ($filter_role > 0) {
        $filter_conditions[] = " role_id = :role_id" . $param_counter;
        $params[':role_id' . $param_counter] = $filter_role;
        $param_counter++;
    }
    
    if ($filter_date_from) {
        $filter_conditions[] = " created_at >= :date_from" . $param_counter;
        $params[':date_from' . $param_counter] = $filter_date_from . ' 00:00:00';
        $param_counter++;
    }
    
    if ($filter_date_to) {
        $filter_conditions[] = " created_at <= :date_to" . $param_counter;
        $params[':date_to' . $param_counter] = $filter_date_to . ' 23:59:59';
        $param_counter++;
    }
    
    $filter_sql = '';
    if (!empty($filter_conditions)) {
        $filter_sql = ' WHERE ' . implode(' AND ', $filter_conditions);
    }
    
    // Combine all queries for data
    $union_query = "SELECT * FROM (
                        ($q1) UNION ALL ($q2) UNION ALL ($q3) UNION ALL ($q4) UNION ALL ($q5)
                    ) as combined $filter_sql 
                    ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    
    // Count query (need to count from all tables)
    $count_query = "SELECT COUNT(*) as total FROM (
                        ($q1) UNION ALL ($q2) UNION ALL ($q3) UNION ALL ($q4) UNION ALL ($q5)
                    ) as combined $filter_sql";
    
    // Get total records for pagination
    $count_stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Execute main query
    $stmt = $conn->prepare($union_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users for filter dropdown
    $users_stmt = $conn->query("SELECT user_id, username, first_name, last_name FROM users WHERE is_active = true ORDER BY username");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get roles for filter dropdown
    $roles_stmt = $conn->query("SELECT role_id, role_name FROM roles WHERE is_active = true ORDER BY role_name");
    $log_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique actions for filter dropdown
    $actions_query = "SELECT DISTINCT action FROM (
                        SELECT action_type as action FROM admin_activity_logs
                        UNION
                        SELECT CASE WHEN success THEN 'LOGIN_SUCCESS' ELSE 'LOGIN_FAILED' END as action FROM login_attempts
                        UNION
                        SELECT 'PASSWORD_RESET' as action FROM password_reset_logs
                        UNION
                        SELECT action FROM system_activity_logs WHERE category = 'authentication'
                        UNION
                        SELECT action FROM activity_logs WHERE action != 'TOGGLE_STATUS'
                      ) as actions ORDER BY action";
    $actions_stmt = $conn->query($actions_query);
    $available_actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    // Handle database errors
    $error = "Database error: " . $e->getMessage();
    error_log($error);
    $logs = [];
    $users = [];
    $available_actions = [];
    $total_records = 0;
    $total_pages = 1;
}

// Get user initials for avatar
$full_name = $_SESSION['full_name'] ?? 'Admin User';
$name_parts = explode(' ', $full_name);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : substr($name_parts[0], 1, 1)));

// Check if sidebar is closed from session or cookie
$sidebar_closed = isset($_COOKIE['sidebar_closed']) ? $_COOKIE['sidebar_closed'] : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        /* Specific overrides for logs page */
        .logs-panel-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .source-badge {
            background: #e9d8fd;
            color: #553c9a;
            padding: 0.15rem 0.4rem;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #d6bcf5;
            border-radius: 3px;
            display: inline-block;
        }

        .json-data {
            background: #f8f9fa;
            padding: 0.3rem;
            border: 1px solid #d8e8d0;
            font-size: 12px;
            font-family: monospace;
            max-width: 200px;
            overflow-x: auto;
            white-space: nowrap;
            margin-top: 0.2rem;
            border-radius: 3px;
        }

        .formatted-data {
            background: #f8f9fa;
            padding: 0.5rem;
            border: 1px solid #d8e8d0;
            font-size: 12px;
            margin-top: 0.4rem;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            max-width: 320px;
        }
        .formatted-data-row {
            margin-bottom: 0.25rem;
            word-break: break-word;
        }
        .formatted-data-key {
            font-weight: 600;
            color: #2a6e3b;
        }
        .formatted-data-val {
            color: #4a5568;
            font-family: monospace;
        }

        /* Log Details Modal - Premium UI/UX */
        .log-modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 32, 10, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            animation: modalBlurIn 0.4s ease;
        }

        @keyframes modalBlurIn {
            from { background-color: rgba(0,0,0,0); backdrop-filter: blur(0px); }
            to { background-color: rgba(0, 32, 10, 0.4); backdrop-filter: blur(10px); }
        }

        .log-modal-content {
            background: #ffffff;
            margin: 1.5% auto;
            width: 95%;
            max-width: 1100px;
            box-shadow: 0 40px 80px -15px rgba(0, 40, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 16px;
            overflow: hidden;
            max-height: 94vh;
            display: flex;
            flex-direction: column;
            animation: modalSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .log-modal-header {
            padding: 1.4rem 2.8rem;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .log-modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }

        .log-modal-header h2 {
            font-size: 1.1rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .log-close {
            font-size: 1.4rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(0, 0, 0,1);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .log-close:hover {
            background: rgba(8, 8, 8, 0.25);
            transform: rotate(90deg) scale(1.1);
        }

        .log-modal-body {
            padding: 2.8rem;
            overflow-y: auto;
            background: #ffffff;
            scrollbar-width: thin;
            scrollbar-color: #d1e4cb transparent;
        }

        .detailed-log-view {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .log-detail-section {
            padding-bottom: 2rem;
            border-bottom: 1px solid #f0f4ef;
        }
        
        .log-detail-section:last-child {
            border-bottom: none;
        }

        .log-section-title {
            font-size: 0.8rem;
            font-weight: 800;
            color: #589065;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .log-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem 1.5rem;
        }

        .log-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 4px;
        }

        .log-field-label {
            font-size: 0.65rem;
            color: #8c9c8c;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .log-field-value {
            font-size: 0.9rem;
            color: #1a2a1f;
            font-weight: 600;
            line-height: 1.4;
        }

        @media (max-width: 1100px) {
            .log-grid { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 850px) {
            .log-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 550px) {
            .log-grid { grid-template-columns: 1fr; }
            .log-modal-body { padding: 1.5rem; }
            .log-modal-header { padding: 1.2rem 1.8rem; }
        }
        
        .btn-view {
            background: #e9ecef;
            color: #495057;
            border: 1px solid #ced4da;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
            margin-top: 5px;
        }
        .btn-view:hover {
            background: #dde0e3;
            color: #212529;
        }

        .log-modal .formatted-data {
            max-width: 100%;
            max-height: 400px;
        }

        .user-info-cell {
            line-height: 1.4;
        }

        .user-name {
            font-weight: 600;
            color: #1c4c29;
        }

        .user-email {
            font-size: 12px;
            color: #6b7c6b;
        }

        /* Mobile menu button (visible when sidebar is closed on mobile) */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1002;
            background: #2a6e3b;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1001;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Toggle Button for Desktop -->
    <div class="sidebar-toggle <?php echo $sidebar_closed === 'true' ? 'closed' : ''; ?>" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="fas fa-chevron-left"></i>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar <?php echo $sidebar_closed === 'true' ? 'closed' : ''; ?>" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">
                Plants.<span>.🌿</span>
            </a>
            <div class="user-profile">
                <div class="user-avatar"><?php echo $initials; ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'Admin User')[0]); ?></div>
                    <div class="user-role">
                        <span class="role-badge"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="nav-menu">
            <ul>
                <li class="nav-item">
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <?php if ($is_super_admin || in_array('manage_questions', $_SESSION['permissions'] ?? []) || in_array('all', $_SESSION['permissions'] ?? [])): ?>
                <li class="nav-item">
                    <a href="security_questions.php">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security Questions</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item active">
                    <a href="logs.php">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                
                <?php if ($is_super_admin): ?>
                <li class="nav-divider"></li>
                <li class="nav-header">Administration</li>

                <li class="nav-item ">
                    <a href="user_management.php">
                        <i class="fas fa-users-cog"></i>
                        <span>Admin Management</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <a href="../admin/logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
            <div class="system-info">
                <i class="fas fa-circle" style="font-size: 6px; color: #7fbf7f;"></i> System Online
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content <?php echo $sidebar_closed === 'true' ? 'expanded' : ''; ?>" id="mainContent">
        <div class="logs-panel-container">
            <div class="welcome-section glass-card">
                <h1>Activity Logs 📜</h1>
                <p>Track system changes and monitor administrative actions.</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger glass-card" style="background: rgba(254, 215, 215, 0.9); color: #c53030; border-left: 5px solid #c53030; padding: 1rem; margin-bottom: 2rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card glass-card">
                    <h3>Total Logs</h3>
                    <div class="stat-value"><?php echo number_format($total_records ?? 0); ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Total Pages</h3>
                    <div class="stat-value"><?php echo $total_pages ?? 1; ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Showing</h3>
                    <div class="stat-value"><?php echo count($logs); ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Current Page</h3>
                    <div class="stat-value"><?php echo $page; ?></div>
                </div>
            </div>
            
            <div class="filters-section glass-card">
                <form method="GET" class="filters-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="filter-group">
                        <label for="action">Action Type</label>
                        <select name="action" id="action">
                            <option value="">All Actions</option>
                            <option value="INSERT" <?php echo $filter_action == 'INSERT' ? 'selected' : ''; ?>>INSERT / REGISTER</option>
                            <option value="UPDATE" <?php echo $filter_action == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                            <option value="DELETE" <?php echo $filter_action == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                            <option value="LOGIN_SUCCESS" <?php echo $filter_action == 'LOGIN_SUCCESS' ? 'selected' : ''; ?>>LOGIN</option>
                            <option value="LOGIN_FAILED" <?php echo $filter_action == 'LOGIN_FAILED' ? 'selected' : ''; ?>>LOGIN FAILED</option>
                            <option value="PASSWORD_RESET" <?php echo $filter_action == 'PASSWORD_RESET' ? 'selected' : ''; ?>>PASSWORD CHANGE</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="user_id">User</label>
                        <select name="user_id" id="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $filter_user == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(($user['username'] ?? '') . ' - ' . trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="role_id">Performer Role</label>
                        <select name="role_id" id="role_id">
                            <option value="">All Roles</option>
                            <?php foreach ($log_roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>" <?php echo $filter_role == $role['role_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="logs.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="logs-list-section glass-card" style="margin-top: 2rem;">
                <div class="table-container">
                    <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Source</th>
                            <th>Record ID</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (!function_exists('formatLogData')) {
                            function formatLogData($data, $label = '', $conn = null, $recordId = null) {
                                if (empty($data)) return '';
                                $arr = is_string($data) ? json_decode($data, true) : $data;
                                if (!is_array($arr)) {
                                    $str = htmlspecialchars(is_string($data) ? $data : json_encode($data));
                                    return '<div class="json-data">' . ($label ? '<b>' . $label . ':</b> ' : '') . $str . '</div>';
                                }

                                // If this looks like user data, format it in sections
                                $user_fields = ['id_number', 'username', 'email', 'first_name', 'last_name', 'street_purok'];
                                $is_user_data = false;
                                foreach ($user_fields as $f) {
                                    if (isset($arr[$f]) || isset($arr['fname']) || isset($arr['lname'])) $is_user_data = true;
                                }

                                if ($is_user_data) {
                                    $html = '<div class="detailed-log-view">';
                                    
                                    if ($label) $html .= '<h3 class="log-detail-main-label">' . htmlspecialchars($label) . '</h3>';

                                    // 1. Account Information
                                    $html .= '<div class="log-detail-section">';
                                    $html .= '<h4 class="log-section-title"><i class="fas fa-user-circle"></i> Account Information</h4>';
                                    $html .= '<div class="log-grid">';
                                    $html .= renderLogField('ID Number', $arr['id_number'] ?? $arr['id_number'] ?? 'Not provided');
                                    $html .= renderLogField('Username', $arr['username'] ?? 'Not provided');
                                    $html .= renderLogField('Email', $arr['email'] ?? 'Not provided');
                                    $html .= renderLogField('Contact Number', $arr['contact_number'] ?? 'Not provided');
                                    $html .= '</div></div>';

                                    // 2. Personal Information
                                    $html .= '<div class="log-detail-section">';
                                    $html .= '<h4 class="log-section-title"><i class="fas fa-id-card"></i> Personal Information</h4>';
                                    $html .= '<div class="log-grid">';
                                    $html .= renderLogField('First Name', $arr['first_name'] ?? $arr['first_name'] ?? 'Not provided');
                                    $html .= renderLogField('Middle Name', $arr['middle_name'] ?? $arr['middle_name'] ?? 'None');
                                    $html .= renderLogField('Last Name', $arr['last_name'] ?? $arr['last_name'] ?? 'Not provided');
                                    $html .= renderLogField('Extension Name', $arr['extension_name'] ?? $arr['extend_name'] ?? 'None');
                                    
                                    $bday = $arr['birthday'] ?? '';
                                    $html .= renderLogField('Birthday', $bday ? date('d/m/Y', strtotime($bday)) : 'Not provided');
                                    
                                    $age = $arr['age'] ?? '';
                                    if (!$age && $bday) {
                                        $birthDate = new DateTime($bday);
                                        $today = new DateTime();
                                        $age = $today->diff($birthDate)->y;
                                    }
                                    $html .= renderLogField('Age', $age ? $age . ' years old' : 'Not provided');
                                    $html .= renderLogField('Sex', ucwords($arr['sex'] ?? 'Not specified'));
                                    $html .= '</div></div>';

                                    // 3. Address Information
                                    $html .= '<div class="log-detail-section">';
                                    $html .= '<h4 class="log-section-title"><i class="fas fa-map-marker-alt"></i> Personal Address</h4>';
                                    $html .= '<div class="log-grid">';
                                    $html .= renderLogField('Purok/Street', $arr['street_purok'] ?? 'Not provided');
                                    $html .= renderLogField('Barangay', $arr['barangay'] ?? 'Not provided');
                                    $html .= renderLogField('City/Municipal', $arr['city_municipal'] ?? 'Not provided');
                                    $html .= renderLogField('Province', $arr['province'] ?? 'Not provided');
                                    $html .= renderLogField('Country', $arr['country'] ?? 'Not provided');
                                    $html .= renderLogField('Zip Code', $arr['zipcode'] ?? $arr['zip_code'] ?? 'Not provided');
                                    $html .= '</div></div>';

                                    // 4. Security Questions (Fetch if conn and recordId provided)
                                    if ($conn && $recordId) {
                                        try {
                                            $q_stmt = $conn->prepare("
                                                SELECT q.question_text 
                                                FROM user_security_answers a
                                                JOIN security_questions q ON a.question_id = q.question_id
                                                WHERE a.user_id = :uid
                                            ");
                                            $q_stmt->execute([':uid' => $recordId]);
                                            $questions = $q_stmt->fetchAll(PDO::FETCH_COLUMN);

                                            if (!empty($questions)) {
                                                $html .= '<div class="log-detail-section">';
                                                $html .= '<h4 class="log-section-title"><i class="fas fa-shield-alt"></i> Security Questions</h4>';
                                                $html .= '<div class="log-grid">';
                                                foreach ($questions as $i => $q) {
                                                    $html .= renderLogField('Question ' . ($i+1), $q);
                                                }
                                                $html .= '</div></div>';
                                            }
                                        } catch (Exception $e) {}
                                    }

                                    $html .= '</div>';
                                    return $html;
                                }
                                
                                // Default formatting for non-user data
                                $html = '<div class="formatted-data">';
                                if ($label) $html .= '<div style="font-weight:bold; margin-bottom:10px; padding-bottom:4px; border-bottom:1px solid #d8e8d0; color:#1c4c29;">' . htmlspecialchars($label) . '</div>';
                                foreach ($arr as $key => $val) {
                                    if ($key === 'password' || $key === 'password_hash') continue; // Hide passwords
                                    $keyStr = htmlspecialchars(ucwords(str_replace('_', ' ', $key)));
                                    $valStr = htmlspecialchars(is_array($val) ? json_encode($val) : (string)$val);
                                    $html .= '<div class="formatted-data-row"><span class="formatted-data-key">' . $keyStr . ':</span> <span class="formatted-data-val">' . $valStr . '</span></div>';
                                }
                                $html .= '</div>';
                                return $html;
                            }

                            function renderLogField($label, $value) {
                                return '<div class="log-field">
                                            <span class="log-field-label">' . htmlspecialchars($label) . ':</span>
                                            <span class="log-field-value">' . htmlspecialchars($value) . '</span>
                                        </div>';
                            }
                        }
                        ?>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'] ?? 'now')); ?></td>
                                <td>
                                    <div class="user-info-cell">
                                        <?php 
                                        if (!empty($log['first_name']) && !empty($log['last_name'])) {
                                            echo '<span class="user-name">' . htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) . '</span>';
                                            if (!empty($log['username'])) {
                                                echo '<br><span class="user-email">@' . htmlspecialchars($log['username']) . '</span>';
                                            }
                                        } elseif (!empty($log['username'])) {
                                            echo '<span class="user-name">' . htmlspecialchars($log['username']) . '</span>';
                                        } else {
                                            $desc = $log['description'] ?? '';
                                            if (preg_match('/user ([^ ]+)/', $desc, $matches)) {
                                                echo '<span class="user-name">' . htmlspecialchars($matches[1]) . '</span>';
                                            } else {
                                                echo '<em class="text-muted">System</em>';
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($log['user_id'])): ?>
                                        <br><span class="text-muted">ID: <?php echo $log['user_id']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($log['role_name'])): ?>
                                        <span class="role-badge" style="font-size: 0.7rem;"><?php echo htmlspecialchars($log['role_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.7rem; font-style: italic;">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-info';
                                    $action = $log['action'] ?? 'UNKNOWN';
                                    $display_action = $action;
                                    
                                    if (strpos($action, 'LOGIN_SUCCESS') !== false) {
                                        $badge_class = 'badge-success';
                                        $display_action = 'LOGIN';
                                    } else if (strpos($action, 'LOGIN_FAILED') !== false) {
                                        $badge_class = 'badge-danger';
                                        $display_action = 'LOGIN FAILED';
                                    } else if (strpos($action, 'LOGOUT') !== false) {
                                        $badge_class = 'badge-warning';
                                    } else if (strpos($action, 'PASSWORD_RESET') !== false) {
                                        $badge_class = 'badge-primary';
                                        $display_action = 'PASSWORD CHANGE';
                                    } else if ($action == 'INSERT') {
                                        if (($log['table_name'] ?? '') == 'users') {
                                            $badge_class = 'badge-success';
                                            $display_action = 'REGISTER';
                                        } else {
                                            $badge_class = 'badge-success';
                                        }
                                    } else if ($action == 'UPDATE') {
                                        $badge_class = 'badge-warning';
                                    } else if ($action == 'DELETE') {
                                        $badge_class = 'badge-danger';
                                    } else if ($action == 'VIEW') {
                                        $badge_class = 'badge-info';
                                    } else if ($action == 'LOGIN') {
                                        $badge_class = 'badge-info';
                                    } else if ($action == 'TOGGLE_STATUS') {
                                        $badge_class = 'badge-info';
                                        $display_action = 'STATUS TOGGLE';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($display_action); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="source-badge">
                                        <?php 
                                        $source = $log['source'] ?? 'unknown';
                                        echo htmlspecialchars(str_replace('_', ' ', $source));
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $log['record_id'] ?? '-'; ?></td>
                                <td>
                                    <?php 
                                    $action = $log['action'] ?? '';
                                    $description = $log['description'] ?? '';
                                    
                                    if (!empty($description)) {
                                        if ($action === 'PASSWORD_RESET') {
                                            echo 'Password changed successfully';
                                        } else {
                                            echo htmlspecialchars($description);
                                        }
                                    } elseif (strpos($action, 'LOGIN_SUCCESS') !== false) {
                                        echo 'User logged in to the system';
                                    } elseif (strpos($action, 'LOGIN_FAILED') !== false) {
                                        echo 'Security warning: Failed login attempt';
                                    } elseif (strpos($action, 'LOGOUT') !== false) {
                                        echo 'User session ended';
                                    } elseif (strpos($action, 'PASSWORD_RESET') !== false) {
                                        echo 'Account password has been changed';
                                    } elseif ($action == 'VIEW') {
                                        echo 'Accessed ' . ($log['table_name'] ?? 'page');
                                    } elseif ($action == 'INSERT') {
                                        if (($log['table_name'] ?? '') == 'users') {
                                            echo 'New account registered';
                                        } else {
                                            echo 'Added new record';
                                        }
                                        if (!empty($log['new_data'])) {
                                            $uniqueId = 'log_' . uniqid();
                                            echo '<br><button class="btn-view" onclick="viewLogDetails(\''.$uniqueId.'\')"><i class="fas fa-eye"></i> View Details</button>';
                                            echo '<div id="'.$uniqueId.'" style="display:none;">' . formatLogData($log['new_data'], '', $conn, $log['record_id']) . '</div>';
                                        }
                                    } elseif ($display_action == 'UPDATE') {
                                        echo 'Updated record details';
                                        if (!empty($log['old_data']) || !empty($log['new_data'])) {
                                            $uniqueId = 'log_' . uniqid();
                                            echo '<br><button class="btn-view" onclick="viewLogDetails(\''.$uniqueId.'\')"><i class="fas fa-eye"></i> View Details</button>';
                                            echo '<div id="'.$uniqueId.'" style="display:none;">';
                                            if (!empty($log['old_data'])) echo formatLogData($log['old_data'], 'Old Data', $conn, $log['record_id']);
                                            if (!empty($log['new_data'])) echo formatLogData($log['new_data'], 'New Data', $conn, $log['record_id']);
                                            echo '</div>';
                                        }
                                    } elseif ($display_action == 'DELETE') {
                                        echo 'Removed record from the system';
                                        if (!empty($log['old_data'])) {
                                            $uniqueId = 'log_' . uniqid();
                                            echo '<br><button class="btn-view" onclick="viewLogDetails(\''.$uniqueId.'\')"><i class="fas fa-eye"></i> View Details</button>';
                                            echo '<div id="'.$uniqueId.'" style="display:none;">' . formatLogData($log['old_data'], 'Deleted Data', $conn, $log['record_id']) . '</div>';
                                        }
                                    } else {
                                        echo 'Action completed';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem; color: #6b7c6b;">
                                <i class="fas fa-history" style="font-size: 24px; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <br>
                                No activity logs found.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (($total_pages ?? 1) > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=1<?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?><?php echo $filter_role ? '&role_id='.$filter_role : ''; ?><?php echo $filter_date_from ? '&date_from='.$filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to='.$filter_date_to : ''; ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page-1; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?><?php echo $filter_role ? '&role_id='.$filter_role : ''; ?><?php echo $filter_date_from ? '&date_from='.$filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to='.$filter_date_to : ''; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?><?php echo $filter_role ? '&role_id='.$filter_role : ''; ?><?php echo $filter_date_from ? '&date_from='.$filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to='.$filter_date_to : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?><?php echo $filter_role ? '&role_id='.$filter_role : ''; ?><?php echo $filter_date_from ? '&date_from='.$filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to='.$filter_date_to : ''; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?><?php echo $filter_role ? '&role_id='.$filter_role : ''; ?><?php echo $filter_date_from ? '&date_from='.$filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to='.$filter_date_to : ''; ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="pagination-info">
                Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo number_format($total_records); ?> total records)
            </div>
            <?php endif; ?>
        </div>

        <!-- Log Details Modal -->
        <div id="logDetailsModal" class="log-modal">
            <div class="log-modal-content">
                <div class="log-modal-header">
                    <h2>Log Details</h2>
                    <span class="log-close" onclick="closeLogModal()">&times;</span>
                </div>
                <div class="log-modal-body" id="logDetailsContent">
                    <!-- Details content will be injected here -->
                </div>
            </div>
        </div>
    </main>

    <script>
        // Toggle sidebar function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('sidebarToggle');
            
            sidebar.classList.toggle('closed');
            mainContent.classList.toggle('expanded');
            toggleBtn.classList.toggle('closed');
            
            // Save state to cookie
            const isClosed = sidebar.classList.contains('closed');
            document.cookie = `sidebar_closed=${isClosed}; path=/; max-age=31536000`; // 1 year expiry
        }
        
        // Toggle mobile menu
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileBtn = document.getElementById('mobileMenuBtn');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !mobileBtn.contains(event.target)) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                if (alert) alert.style.display = 'none';
            });
        }, 5000);
        // Log Details Modal Functions
        function viewLogDetails(elementId) {
            const content = document.getElementById(elementId).innerHTML;
            document.getElementById('logDetailsContent').innerHTML = content;
            document.getElementById('logDetailsModal').style.display = 'block';
        }

        function closeLogModal() {
            document.getElementById('logDetailsModal').style.display = 'none';
        }

        document.addEventListener('click', function(event) {
            const modal = document.getElementById('logDetailsModal');
            if (event.target == modal) {
                closeLogModal();
            }
        });
    </script>
</body>
</html>