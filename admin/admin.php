<?php
// admin.php - Super Admin only page
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit();
}

// Check if user is Super Admin (role_id = 1)
if ($_SESSION['role_id'] != 1) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? 0;
$is_super_admin = ($role_id == 1);
$is_admin = ($role_id <= 2);

// Get system statistics
$stats = [];
$users_by_role = [];
$recent_logs = [];

try {
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total roles
    $stmt = $conn->query("SELECT COUNT(*) as total FROM roles");
    $stats['total_roles'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total permissions
    $stmt = $conn->query("SELECT COUNT(*) as total FROM permissions");
    $stats['total_permissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Users by role
    $query = "
        SELECT r.role_name, COUNT(u.user_id) as user_count 
        FROM roles r 
        LEFT JOIN users u ON r.role_id = u.role_id 
        GROUP BY r.role_name, r.role_id 
        ORDER BY r.role_id
    ";
    $stmt = $conn->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users_by_role[] = $row;
    }

    // Recent activity logs
    $query = "
        SELECT a.*, u.username, u.first_name, u.last_name 
        FROM activity_logs a 
        LEFT JOIN users u ON a.performed_by = u.user_id 
        ORDER BY a.created_at DESC 
        LIMIT 20
    ";
    $stmt = $conn->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $recent_logs[] = $row;
    }

    // Log this page view
    try {
        $log_query = "INSERT INTO activity_logs (table_name, action, performed_by, ip_address, user_agent, created_at) 
                     VALUES (:table_name, :action, :performed_by, :ip_address, :user_agent, NOW())";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([
            ':table_name' => 'admin',
            ':action' => 'VIEW',
            ':performed_by' => $user_id,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        // Log table might not exist, continue anyway
    }

} catch (PDOException $e) {
    // Handle database errors gracefully
    $error = "Database error: " . $e->getMessage();
    // Set default values
    $stats['total_users'] = $stats['total_users'] ?? 0;
    $stats['total_roles'] = $stats['total_roles'] ?? 0;
    $stats['total_permissions'] = $stats['total_permissions'] ?? 0;
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
    <title>Admin Panel - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        /* Admin panel specific styles - optimized */
        .container {
            padding: 1.2rem 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            color: #1c4c29;
            font-size: 24px;
            font-weight: 600;
            border-left: 8px solid #509c5b;
            padding-left: 1rem;
            margin-bottom: 0.3rem;
        }

        .page-header p {
            color: #4a6b52;
            font-size: 14px;
            margin-left: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: #fafff9;
            padding: 1.2rem;
            box-shadow: 0 4px 12px rgba(40, 80, 30, 0.1);
            border: 1px solid #cbe6bf;
            border-radius: 4px;
        }

        .stat-card h3 {
            color: #1d4d2d;
            font-size: 12px;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #0f4d1f;
        }

        .admin-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .admin-card {
            background: #fafff9;
            box-shadow: 0 4px 12px rgba(40, 80, 30, 0.1);
            border: 1px solid #cbe6bf;
            border-radius: 4px;
            overflow: hidden;
        }

        .admin-card-header {
            background: linear-gradient(97deg, #d8efd0 0%, #f3fcf0 60%);
            color: #1c4c29;
            padding: 0.8rem 1.2rem;
            font-weight: 600;
            font-size: 16px;
            border-bottom: 1px solid #bdd8b3;
        }

        .admin-card-body {
            padding: 1.2rem;
        }

        .role-list {
            list-style: none;
        }

        .role-list li {
            padding: 0.6rem 0;
            border-bottom: 1px solid #d8e8d0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .role-list li:last-child {
            border-bottom: none;
        }

        .role-name {
            font-weight: 600;
            color: #1c4c29;
        }

        .role-count {
            background: #2a6e3b;
            color: white;
            padding: 0.15rem 0.8rem;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #1c4c2a;
            border-radius: 3px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.8rem;
        }

        .quick-action-btn {
            background: #fff;
            border: 1px solid #589065;
            color: #1b572b;
            padding: 12px;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            border-radius: 4px;
        }

        .quick-action-btn:hover {
            background: #226f36;
            border-color: #226f36;
            color: white;
        }

        .quick-action-btn i {
            font-size: 14px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1c4927;
            margin: 1.5rem 0 1rem;
            border-left: 6px solid #509c5b;
            padding-left: 1rem;
        }

        .logs-table-container {
            background: #fafff9;
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(40, 80, 30, 0.1);
            border: 1px solid #cbe6bf;
            border-radius: 4px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
            font-size: 14px;
        }

        .logs-table th {
            padding: 0.8rem 0.6rem;
            text-align: left;
            font-weight: 600;
            color: #1d4d2d;
            border-bottom: 2px solid #cbe6bf;
            background: #f2f7f0;
        }

        .logs-table td {
            padding: 12px 0.6rem;
            border-bottom: 1px solid #d8e8d0;
            color: #1e3a2f;
        }

        .logs-table tr:last-child td {
            border-bottom: none;
        }

        .logs-table tr:hover {
            background: #f2f7f0;
        }

        .badge {
            padding: 0.25rem 0.6rem;
            font-size: 12px;
            border-radius: 3px;
        }

        .user-info-cell {
            line-height: 1.3;
        }

        .user-name {
            font-weight: 600;
            color: #1c4c29;
            font-size: 14px;
        }

        .text-muted {
            color: #6b7c6b;
            font-size: 12px;
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
            
            .admin-sections {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .page-header p {
                margin-left: 1rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }
            
            .page-header h1 {
                font-size: 16px;
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
                <li class="nav-item">
                    <a href="logs.php">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                <li class="nav-header">Administration</li>
                <li class="nav-item active">
                    <a href="admin.php">
                        <i class="fas fa-cog"></i>
                        <span>Admin Panel</span>
                    </a>
                </li>
                <li class="nav-item ">
                    <a href="user_management.php">
                        <i class="fas fa-users-cog"></i>
                        <span>Admin Management</span>
                    </a>
                </li>
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
        <div class="container">
            <div class="page-header">
                <h1>Administration Panel</h1>
                <p>Welcome to the super admin panel. Here you can manage system settings and monitor activities.</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>System Roles</h3>
                    <div class="stat-value"><?php echo $stats['total_roles'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Permissions</h3>
                    <div class="stat-value"><?php echo $stats['total_permissions'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Recent Activities</h3>
                    <div class="stat-value"><?php echo count($recent_logs); ?></div>
                </div>
            </div>
            
            <div class="admin-sections">
                <div class="admin-card">
                    <div class="admin-card-header">
                        <i class="fas fa-users" style="margin-right: 8px;"></i>
                        User Distribution by Role
                    </div>
                    <div class="admin-card-body">
                        <ul class="role-list">
                            <?php if (!empty($users_by_role)): ?>
                                <?php foreach ($users_by_role as $role): ?>
                                <li>
                                    <span class="role-name"><?php echo htmlspecialchars($role['role_name'] ?? 'Unknown'); ?></span>
                                    <span class="role-count"><?php echo $role['user_count'] ?? 0; ?></span>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>
                                    <span class="role-name">No data available</span>
                                    <span class="role-count">0</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="admin-card">
                    <div class="admin-card-header">
                        <i class="fas fa-bolt" style="margin-right: 8px;"></i>
                        Quick Actions
                    </div>
                    <div class="admin-card-body">
                        <div class="quick-actions">
                            <a href="users.php" class="quick-action-btn">
                                <i class="fas fa-users-cog"></i> Manage Users
                            </a>
                            <a href="#" class="quick-action-btn">
                                <i class="fas fa-tags"></i> Manage Roles
                            </a>
                            <a href="#" class="quick-action-btn">
                                <i class="fas fa-cog"></i> System Settings
                            </a>
                            <a href="logs.php" class="quick-action-btn">
                                <i class="fas fa-history"></i> View All Logs
                            </a>
                            <a href="#" class="quick-action-btn">
                                <i class="fas fa-database"></i> Backup DB
                            </a>
                            <a href="#" class="quick-action-btn">
                                <i class="fas fa-trash-alt"></i> Clear Cache
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <h2 class="section-title">Recent System Activities</h2>
            
            <div class="logs-table-container">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_logs)): ?>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'] ?? 'now')); ?></td>
                                <td>
                                    <div class="user-info-cell">
                                        <?php 
                                        if (!empty($log['username'])) {
                                            echo '<span class="user-name">' . htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) . '</span>';
                                            echo '<br><span class="text-muted">@' . htmlspecialchars($log['username']) . '</span>';
                                        } else {
                                            echo '<span class="user-name">System</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = 'badge-info';
                                    $action = $log['action'] ?? 'UNKNOWN';
                                    if ($action == 'INSERT') $badge_class = 'badge-success';
                                    if ($action == 'UPDATE') $badge_class = 'badge-warning';
                                    if ($action == 'DELETE') $badge_class = 'badge-danger';
                                    if ($action == 'LOGIN') $badge_class = 'badge-info';
                                    if ($action == 'VIEW') $badge_class = 'badge-info';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($action); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['table_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    if ($action == 'LOGIN') {
                                        echo 'User logged in';
                                    } elseif ($action == 'VIEW') {
                                        echo 'Viewed ' . ($log['table_name'] ?? 'page');
                                    } elseif ($action == 'INSERT') {
                                        echo 'Added new record' . ($log['record_id'] ? ' (ID: ' . $log['record_id'] . ')' : '');
                                    } elseif ($action == 'UPDATE') {
                                        echo 'Updated record' . ($log['record_id'] ? ' (ID: ' . $log['record_id'] . ')' : '');
                                    } elseif ($action == 'DELETE') {
                                        echo 'Deleted record' . ($log['record_id'] ? ' (ID: ' . $log['record_id'] . ')' : '');
                                    } else {
                                        echo 'Record ID: ' . ($log['record_id'] ?? 'N/A');
                                    }
                                    ?>
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

        // Handle ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });
    </script>
</body>
</html>