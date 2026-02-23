<?php
// dashboard.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in and has admin/super admin role (1 or 2)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: ../admin/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];
$is_super_admin = ($role_id == 1);
$is_admin = ($role_id <= 2); // Super Admin or Admin

// Get user statistics
$stats = [];
$recent_activities = [];
$regular_users = [];

try {
    // Get role IDs for admin and super admin
    $role_query = "SELECT role_id FROM roles WHERE role_name IN ('Admin', 'Super Admin')";
    $role_stmt = $conn->query($role_query);
    $admin_roles = $role_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Total regular users count (non-admin)
    $count_query = "SELECT COUNT(*) as total FROM users";
    if (!empty($admin_roles)) {
        $admin_roles_str = implode(',', $admin_roles);
        $count_query = "SELECT COUNT(*) as total FROM users WHERE role_id NOT IN ($admin_roles_str) OR role_id IS NULL";
    }
    $stmt = $conn->query($count_query);
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active regular users today
    $active_query = "SELECT COUNT(*) as total FROM users WHERE DATE(last_login) = CURRENT_DATE";
    if (!empty($admin_roles)) {
        $admin_roles_str = implode(',', $admin_roles);
        $active_query = "SELECT COUNT(*) as total FROM users WHERE DATE(last_login) = CURRENT_DATE AND (role_id NOT IN ($admin_roles_str) OR role_id IS NULL)";
    }
    $stmt = $conn->query($active_query);
    $stats['active_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Failed login attempts today
    $failed_query = "SELECT COUNT(*) as total FROM login_attempts WHERE success = false AND DATE(attempt_time) = CURRENT_DATE";
    $stmt = $conn->query($failed_query);
    $stats['failed_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get all regular users (non-admin, non-super admin) for the modal
    $users_query = "SELECT 
                        u.user_id,
                        u.id_number,
                        u.username,
                        u.email,
                        u.first_name,
                        u.middle_name,
                        u.last_name,
                        u.extension_name,
                        u.sex,
                        u.contact_number,
                        u.barangay,
                        u.city_municipal,
                        u.province,
                        u.is_active,
                        u.created_at,
                        u.last_login,
                        u.role_id,
                        r.role_name,
                        (SELECT COUNT(*) FROM admin_activity_logs a WHERE a.admin_id = u.user_id) as total_activities
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.role_id";
    
    // Add condition to exclude admin roles
    if (!empty($admin_roles)) {
        $admin_roles_str = implode(',', $admin_roles);
        $users_query .= " WHERE (u.role_id NOT IN ($admin_roles_str) OR u.role_id IS NULL)";
    }
    
    $users_query .= " ORDER BY u.created_at DESC";
    
    $users_stmt = $conn->query($users_query);
    $regular_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent activities - Combined from all log tables with proper type casting
    // Get activities from activity_logs (successful logins, page views, etc)
    $query1 = "SELECT 
                a.created_at,
                a.action_type as action,
                a.table_name,
                a.record_id::text,
                a.ip_address::text as ip_address,
                a.user_agent,
                u.username,
                u.first_name,
                u.last_name,
                NULL as description,
                'activity_log' as source
              FROM admin_activity_logs a 
              LEFT JOIN users u ON a.admin_id = u.user_id 
              WHERE a.created_at IS NOT NULL";

    // Get login attempts from login_attempts (failed logins)
    $query2 = "SELECT 
                la.attempt_time as created_at,
                CASE WHEN la.success THEN 'LOGIN_SUCCESS' ELSE 'LOGIN_FAILED' END as action,
                'login_attempts' as table_name,
                la.attempt_id::text as record_id,
                la.ip_address::text as ip_address,
                NULL as user_agent,
                la.username,
                NULL as first_name,
                NULL as last_name,
                CASE WHEN la.success THEN 'Successful login' ELSE 'Failed login attempt' END as description,
                'login_attempt' as source
              FROM login_attempts la
              WHERE la.attempt_time IS NOT NULL";

    // Get failed attempts from password_reset_logs
    $query3 = "SELECT 
                prl.attempt_time as created_at,
                'PASSWORD_RESET' as action,
                'password_reset' as table_name,
                prl.log_id::text as record_id,
                prl.ip_address::text as ip_address,
                prl.user_agent,
                prl.email as username,
                NULL as first_name,
                NULL as last_name,
                prl.details as description,
                'password_reset' as source
              FROM password_reset_logs prl
              WHERE prl.attempt_time IS NOT NULL";

    // Get system activity logs for authentication events
    $query4 = "SELECT 
                sal.created_at,
                sal.action,
                sal.category as table_name,
                sal.log_id::text as record_id,
                sal.ip_address::text as ip_address,
                sal.user_agent,
                NULL as username,
                NULL as first_name,
                NULL as last_name,
                sal.description,
                'system_log' as source
              FROM system_activity_logs sal
              WHERE sal.created_at IS NOT NULL AND sal.category = 'authentication'";

    // Combine all queries with UNION and order by date
    $union_query = "($query1) UNION ALL ($query2) UNION ALL ($query3) UNION ALL ($query4) 
                    ORDER BY created_at DESC LIMIT 50";
    
    $stmt = $conn->query($union_query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $recent_activities[] = $row;
    }

    } catch (PDOException $e) {
        // Handle database errors gracefully
        $error = "Database error: " . $e->getMessage();
        error_log($error);
    }

// Get user initials for avatar
$full_name = $_SESSION['full_name'] ?? 'Admin User';
$name_parts = explode(' ', $full_name);
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : substr($name_parts[0], 1, 1)));

// Check if sidebar is closed from cookie
$sidebar_closed = isset($_COOKIE['sidebar_closed']) ? $_COOKIE['sidebar_closed'] : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        /* Specific page overrides if needed */
        .dashboard-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        /* Dashboard specific styles - optimized for size */
        .container {
            padding: 1.2rem 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* welcome section */
        .welcome-section {
            margin-bottom: 2rem;
            padding: 2rem;
        }

        .welcome-section h1 {
            font-size: 24px;
            margin-bottom: 0.5rem;
        }

        /* stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            padding: 1.5rem;
        }

        .stat-card h3 {
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-bottom: 0.8rem;
        }

        .stat-card .stat-value {
            font-size: 26px;
            margin-bottom: 1rem;
        }

        .stat-card .stat-action {
            margin-top: auto;
        }

        .stat-btn {
            background-color: #fff;
            border: 1px solid #589065;
            color: #1b572b;
            font-size: 14px;
            font-weight: 600;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            border-radius: 2px;
        }

        .stat-btn:hover {
            background: #226f36;
            border-color: #226f36;
            color: white;
        }

        /* action buttons */
        .action-buttons {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            background-color: #fff;
            border: 1px solid #589065;
            color: #1b572b;
            font-size: 14px;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            border-radius: 2px;
        }

        .action-btn:hover {
            background: #226f36;
            border-color: #226f36;
            color: white;
        }

        /* section title */
        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #1c4927;
            margin-bottom: 1.2rem;
            border-left: 6px solid #509c5b;
            padding-left: 1rem;
        }

        /* activities table */
        .activities-table {
            background: #fafff9;
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(40, 80, 30, 0.1);
            border: 1px solid #cbe6bf;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .activities-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
            font-size: 13px;
        }

        .activities-table th {
            padding: 0.8rem 0.6rem;
            text-align: left;
            font-weight: 600;
            color: #1d4d2d;
            border-bottom: 2px solid #cbe6bf;
            background: #f2f7f0;
        }

        .activities-table td {
            padding: 0.7rem 0.6rem;
            border-bottom: 1px solid #d8e8d0;
            color: #1e3a2f;
        }

        .activities-table tr:last-child td {
            border-bottom: none;
        }

        .activities-table tr:hover {
            background: #f2f7f0;
        }

        /* badges */
        .badge {
            padding: 0.25rem 0.6rem;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid transparent;
            border-radius: 2px;
        }

        .badge-success {
            background: #daf3d1;
            color: #0f4d1f;
            border-color: #a5cf9b;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .badge-dark {
            background: #e2e8f0;
            color: #4a5568;
            border-color: #cbd5e0;
        }

        .text-muted {
            color: #6b7c6b;
            font-size: 11px;
        }

        /* Modal Styles - optimized */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 40, 0, 0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: #fafff9;
            margin: 30px auto;
            width: 90%;
            max-width: 1100px;
            box-shadow: 0 15px 30px rgba(32, 72, 52, 0.25);
            border: 1px solid #cbe6bf;
            border-radius: 5px;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(97deg, #d8efd0 0%, #f3fcf0 60%);
            color: #1c4c29;
            padding: 1.2rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #bdd8b3;
            border-radius: 6px 6px 0 0;
        }

        .modal-header h2 {
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-header h2 i {
            font-size: 16px;
            color: #2b5e3c;
        }

        .modal-header h2 span {
            background: #fff;
            padding: 0.2rem 0.8rem;
            font-size: 11px;
            margin-left: 8px;
            border: 1px solid #a5cf9b;
            border-radius: 2px;
        }

        .modal-header .close {
            color: #2b5e3c;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-header .close:hover {
            color: #174e25;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            background: #f2f7f0;
            padding: 1rem 1.5rem;
            text-align: right;
            border-top: 1px solid #bdd8b3;
            border-radius: 0 0 5px 5px;
        }

        .modal-footer .btn-close {
            background: #2a6e3b;
            border: 1px solid #1c4c2a;
            color: #fff;
            padding: 0.5rem 1.5rem;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 2px;
        }

        .modal-footer .btn-close:hover {
            background-color: #1d542b;
        }

        /* Modal Search */
        .modal-search {
            margin-bottom: 1.2rem;
            display: flex;
            gap: 0.8rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .modal-search input {
            flex: 1;
            min-width: 200px;
            padding: 0.6rem 1rem;
            border: 1px solid #afcfaa;
            font-size: 13px;
            background: white;
            border-radius: 2px;
        }

        .modal-search input:focus {
            outline: none;
            border-color: #589065;
        }

        .modal-search select {
            padding: 0.6rem 1.2rem;
            border: 1px solid #afcfaa;
            font-size: 13px;
            background: white;
            color: #1e3a2f;
            border-radius: 2px;
        }

        /* Modal Stats */
        .modal-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.2rem;
            padding: 1rem;
            background: #f2f7f0;
            flex-wrap: wrap;
            border: 1px solid #cbe6bf;
            border-radius: 5px;
        }

        .modal-stat-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .modal-stat-value {
            font-size: 13px;
            font-weight: 700;
            color: #0f4d1f;
        }

        .modal-stat-label {
            color: #1d4d2d;
            font-size: 11px;
        }

        /* Users Table in Modal */
        .modal-users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.8rem;
            font-size: 13px;
        }

        .modal-users-table th {
            background: #f2f7f0;
            padding: 0.8rem 0.6rem;
            text-align: left;
            font-weight: 600;
            color: #1d4d2d;
            border-bottom: 2px solid #cbe6bf;
            position: sticky;
            top: 0;
        }

        .modal-users-table td {
            padding: 0.7rem 0.6rem;
            border-bottom: 1px solid #d8e8d0;
            color: #1e3a2f;
        }

        .modal-users-table tr:hover {
            background: #f2f7f0;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: #2a6e3b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 11px;
            border-radius: 50%;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .user-details {
            line-height: 1.3;
        }

        .user-name {
            font-weight: 600;
            color: #1c4c29;
            font-size: 14px;
        }

        .user-email {
            font-size: 11px;
            color: #6b7c6b;
        }

        .role-badge {
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.2rem 0.6rem;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #cbd5e0;
            border-radius: 2px;
            white-space: nowrap;
        }

        .online-indicator {
            width: 6px;
            height: 6px;
            background: #48bb78;
            display: inline-block;
            margin-right: 4px;
            border-radius: 50%;
        }

        .pagination-info {
            margin-top: 1rem;
            text-align: center;
            color: #1d4d2d;
            font-size: 13px;
        }

        .export-btn {
            background: #fff;
            border: 1px solid #589065;
            color: #1b572b;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 2px;
            font-size: 13px;
        }

        .export-btn:hover {
            background: #226f36;
            border-color: #226f36;
            color: white;
        }

        /* Mobile menu button */
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

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .activities-table {
                overflow-x: auto;
            }
            
            .activities-table table {
                min-width: 700px;
            }
            
            .modal-search {
                flex-direction: column;
                align-items: stretch;
            }
            
            .modal-users-table {
                min-width: 800px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }
            
            .welcome-section {
                padding: 1rem;
            }
            
            .welcome-section h1 {
                font-size: 1.4rem;
            }
            
            .section-title {
                font-size: 1.1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
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
                <li class="nav-item active">
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
                <li class="nav-item">
                    <a href="logs.php">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                
                <?php if ($is_super_admin): ?>
                <li class="nav-divider"></li>
                <li class="nav-header">Administration</li>
                <li class="nav-item">
                    <a href="admin.php">
                        <i class="fas fa-cog"></i>
                        <span>Admin Panel</span>
                    </a>
                </li>
                <li class="nav-item ">
                    <a href="user_management.php">
                        <i class="fas fa-users-cog"></i>
                        <span>Staff Management</span>
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
        <div class="dashboard-container">
            <div class="welcome-section glass-card">
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? 'User')[0]); ?>! 🪴</h1>
                <p><?php echo date('l, F j, Y'); ?> • Here's what's happening with your system today.</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card glass-card">
                    <h3>Total Regular Users</h3>
                    <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
                    <div class="stat-action">
                        <button onclick="openUserModal()" class="btn-modern btn-secondary">
                            <i class="fas fa-list"></i> View All
                        </button>
                    </div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Active Today</h3>
                    <div class="stat-value"><?php echo $stats['active_today'] ?? 0; ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Failed Attempts</h3>
                    <div class="stat-value"><?php echo $stats['failed_today'] ?? 0; ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Your Role</h3>
                    <div class="stat-value"><?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?></div>
                </div>
            </div>
            
            <?php if ($is_admin): ?>
            <div class="action-buttons">
                <a href="users.php" class="action-btn"><i class="fas fa-users"></i> Manage All Users</a>
                <a href="logs.php" class="action-btn"><i class="fas fa-history"></i> View Activity Logs</a>
                <?php if ($is_super_admin): ?>
                <a href="admin.php" class="action-btn"><i class="fas fa-cog"></i> Admin Panel</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <h2 class="section-title">Recent Activities</h2>
            
            <div class="activities-table glass-card">
                <div class="table-container">
                    <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Source</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                            <tr>
                                <td><?php echo date('M d, H:i:s', strtotime($activity['created_at'] ?? 'now')); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($activity['first_name']) && !empty($activity['last_name'])) {
                                        echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']);
                                    } elseif (!empty($activity['username'])) {
                                        echo htmlspecialchars($activity['username']);
                                    } else {
                                        $desc = $activity['description'] ?? '';
                                        if (preg_match('/user ([^ ]+)/', $desc, $matches)) {
                                            echo htmlspecialchars($matches[1]);
                                        } else {
                                            echo 'System';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $action = $activity['action'] ?? 'UNKNOWN';
                                    $badge_class = 'badge-info';
                                    
                                    if (strpos($action, 'LOGIN_SUCCESS') !== false || $action == 'LOGIN') {
                                        $badge_class = 'badge-success';
                                    } else if (strpos($action, 'LOGIN_FAILED') !== false) {
                                        $badge_class = 'badge-danger';
                                    } else if (strpos($action, 'LOGOUT') !== false) {
                                        $badge_class = 'badge-warning';
                                    } else if (strpos($action, 'PASSWORD_RESET') !== false) {
                                        $badge_class = 'badge-warning';
                                    } else if ($action == 'INSERT') {
                                        $badge_class = 'badge-success';
                                    } else if ($action == 'UPDATE') {
                                        $badge_class = 'badge-warning';
                                    } else if ($action == 'DELETE') {
                                        $badge_class = 'badge-danger';
                                    } else if ($action == 'VIEW') {
                                        $badge_class = 'badge-info';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($action); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-dark">
                                        <?php 
                                        $source = $activity['source'] ?? 'unknown';
                                        echo htmlspecialchars(str_replace('_', ' ', $source));
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $action = $activity['action'] ?? '';
                                    $description = $activity['description'] ?? '';
                                    
                                    if (!empty($description)) {
                                        echo htmlspecialchars($description);
                                    } elseif (strpos($action, 'LOGIN_SUCCESS') !== false) {
                                        echo 'User logged in successfully';
                                    } elseif (strpos($action, 'LOGIN_FAILED') !== false) {
                                        echo 'Failed login attempt for user ' . ($activity['username'] ?? 'unknown');
                                    } elseif (strpos($action, 'LOGOUT') !== false) {
                                        echo 'User logged out';
                                    } elseif (strpos($action, 'PASSWORD_RESET') !== false) {
                                        echo 'Password reset attempt for ' . ($activity['username'] ?? 'unknown');
                                    } elseif ($action == 'VIEW') {
                                        echo 'Viewed ' . ($activity['table_name'] ?? 'dashboard');
                                    } elseif ($action == 'INSERT') {
                                        echo 'Added new record' . ($activity['record_id'] ? ' (ID: ' . $activity['record_id'] . ')' : '');
                                    } elseif ($action == 'UPDATE') {
                                        echo 'Updated record' . ($activity['record_id'] ? ' (ID: ' . $activity['record_id'] . ')' : '');
                                    } elseif ($action == 'DELETE') {
                                        echo 'Deleted record' . ($activity['record_id'] ? ' (ID: ' . $activity['record_id'] . ')' : '');
                                    } else {
                                        echo 'Action performed';
                                    }
                                    ?>
                                    
                                    <?php if (!empty($activity['ip_address']) && $activity['ip_address'] != '::1'): ?>
                                    <br><small class="text-muted">IP: <?php echo htmlspecialchars($activity['ip_address']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #6b7c6b;">
                                <i class="fas fa-history" style="font-size: 2.5rem; margin-bottom: 0.8rem; opacity: 0.5;"></i>
                                <br>
                                No recent activities found.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Regular Users List Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-users"></i> Regular Users
                    <span><?php echo count($regular_users); ?> total</span>
                </h2>
                <span class="close" onclick="closeUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-search">
                    <input type="text" id="userSearch" placeholder="Search by name, email, username, ID..." onkeyup="filterUsers()">
                    <select id="statusFilter" onchange="filterUsers()">
                        <option value="all">All Status</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                    <button class="export-btn" onclick="exportUserList()">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
                
                <div class="modal-stats">
                    <div class="modal-stat-item">
                        <span class="modal-stat-value"><?php echo count($regular_users); ?></span>
                        <span class="modal-stat-label">Total Users</span>
                    </div>
                    <div class="modal-stat-item">
                        <span class="modal-stat-value"><?php echo count(array_filter($regular_users, function($u) { return $u['is_active'] == 1; })); ?></span>
                        <span class="modal-stat-label">Active</span>
                    </div>
                    <div class="modal-stat-item">
                        <span class="modal-stat-value"><?php echo count(array_filter($regular_users, function($u) { return $u['is_active'] == 0; })); ?></span>
                        <span class="modal-stat-label">Inactive</span>
                    </div>
                </div>
                
                <table class="modal-users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Activities</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regular_users as $user): 
                            $full_name = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name'] . ($user['extension_name'] ? ' ' . $user['extension_name'] : ''));
                            $display_name = $full_name ?: $user['username'];
                            $initial = strtoupper(substr($display_name, 0, 1));
                        ?>
                        <tr class="user-row" 
                            data-name="<?php echo strtolower($display_name); ?>"
                            data-email="<?php echo strtolower($user['email'] ?? ''); ?>"
                            data-username="<?php echo strtolower($user['username'] ?? ''); ?>"
                            data-idnumber="<?php echo strtolower($user['id_number'] ?? ''); ?>"
                            data-status="<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar"><?php echo $initial; ?></div>
                                    <div class="user-details">
                                        <div class="user-name"><?php echo htmlspecialchars($display_name); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['email'] ?? 'No email'); ?></div>
                                        <div class="text-muted">ID: <?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['contact_number'] ?? 'N/A'); ?><br>
                                <span class="text-muted"><?php echo htmlspecialchars($user['sex'] ?? 'Not specified'); ?></span>
                            </td>
                            <td>
                                <?php 
                                $location = array_filter([
                                    $user['barangay'] ?? '',
                                    $user['city_municipal'] ?? '',
                                    $user['province'] ?? ''
                                ]);
                                echo !empty($location) ? htmlspecialchars(implode(', ', $location)) : 'N/A';
                                ?>
                            </td>
                            <td>
                                <span class="role-badge"><?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?></span>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                <span class="badge badge-success"><span class="online-indicator"></span> Active</span>
                                <?php else: ?>
                                <span class="badge badge-dark">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['created_at'] ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></td>
                            <td><?php echo $user['last_login'] ? date('M d, H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td><span class="badge badge-info"><?php echo $user['total_activities'] ?? 0; ?> activities</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="pagination-info" id="paginationInfo">
                    Showing <?php echo count($regular_users); ?> of <?php echo count($regular_users); ?> regular users
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-close" onclick="closeUserModal()">Close</button>
            </div>
        </div>
    </div>

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
            document.cookie = `sidebar_closed=${isClosed}; path=/; max-age=31536000`;
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

        // Modal functions
        function openUserModal() {
            document.getElementById('userModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target == modal) {
                closeUserModal();
            }
        }
        
        // Filter users function
        function filterUsers() {
            const searchInput = document.getElementById('userSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.user-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const email = row.getAttribute('data-email') || '';
                const username = row.getAttribute('data-username') || '';
                const idnumber = row.getAttribute('data-idnumber') || '';
                const status = row.getAttribute('data-status');
                
                const matchesSearch = searchInput === '' || 
                    name.includes(searchInput) || 
                    email.includes(searchInput) || 
                    username.includes(searchInput) || 
                    idnumber.includes(searchInput);
                    
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            const totalRows = document.querySelectorAll('.user-row').length;
            document.getElementById('paginationInfo').textContent = 
                `Showing ${visibleCount} of ${totalRows} regular users`;
        }
        
        // Export user list as CSV
        function exportUserList() {
            const rows = document.querySelectorAll('.user-row:not([style*="display: none"])');
            
            if (rows.length === 0) {
                alert('No users to export.');
                return;
            }
            
            let csv = 'Name,Email,Username,ID Number,Role,Status,Joined,Last Login,Activities\n';
            
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    
                    const nameCell = cells[0]?.querySelector('.user-name')?.textContent.trim() || '';
                    const emailCell = cells[0]?.querySelector('.user-email')?.textContent.trim().split('\n')[0] || '';
                    const usernameCell = cells[0]?.querySelector('.user-email')?.textContent.trim().includes('@') ? 
                        cells[0]?.querySelector('.user-email')?.textContent.trim().split('@')[1]?.trim() || '' : '';
                    const idCell = cells[0]?.querySelector('.text-muted')?.textContent.replace('ID:', '').trim() || '';
                    const roleCell = cells[3]?.querySelector('.role-badge')?.textContent.trim() || '';
                    const statusCell = cells[4]?.querySelector('.badge')?.textContent.trim() || '';
                    const joinedCell = cells[5]?.textContent.trim() || '';
                    const lastLoginCell = cells[6]?.textContent.trim() || '';
                    const activitiesCell = cells[7]?.querySelector('.badge')?.textContent.trim() || '0';
                    
                    csv += `"${nameCell}","${emailCell}","${usernameCell}","${idCell}","${roleCell}","${statusCell}","${joinedCell}","${lastLoginCell}","${activitiesCell}"\n`;
                }
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'regular_users_list.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Handle ESC key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeUserModal();
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });
    </script>
</body>
</html>