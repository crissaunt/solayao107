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

try {
    // Build UNION query to get logs from all tables
    $base_queries = [];
    $count_queries = [];
    $params = [];
    $param_counter = 0;

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
            a.admin_id as user_id
          FROM admin_activity_logs a 
          LEFT JOIN users u ON a.admin_id = u.user_id 
          WHERE 1=1";
        // WHERE (u.role_id IS NULL OR u.role_id > 1)";

    // 2. Login attempts table
    $q2 = "SELECT 
            la.attempt_time as created_at,
            CASE WHEN la.success THEN 'LOGIN_SUCCESS' ELSE 'LOGIN_FAILED' END as action,
            'login_attempts' as table_name,
            la.attempt_id::text as record_id,
            la.ip_address::text as ip_address,
            NULL as user_agent,
            la.username,
            NULL as first_name,
            NULL as last_name,
            NULL as old_data,
            NULL as new_data,
            CASE WHEN la.success THEN 'Successful login' ELSE 'Failed login attempt' END as description,
            'login_attempt' as source,
            la.user_id
          FROM login_attempts la
          WHERE 1=1";

    // 3. Password reset logs
    $q3 = "SELECT 
            prl.attempt_time as created_at,
            'PASSWORD_RESET' as action,
            'password_reset' as table_name,
            prl.log_id::text as record_id,
            prl.ip_address::text as ip_address,
            prl.user_agent,
            prl.email as username,
            NULL as first_name,
            NULL as last_name,
            NULL as old_data,
            NULL as new_data,
            prl.details as description,
            'password_reset' as source,
            prl.user_id
          FROM password_reset_logs prl
          WHERE 1=1";

    // 4. System activity logs (authentication events)
    $q4 = "SELECT 
            sal.created_at,
            sal.action,
            sal.category as table_name,
            sal.log_id::text as record_id,
            sal.ip_address::text as ip_address,
            sal.user_agent,
            NULL as username,
            NULL as first_name,
            NULL as last_name,
            NULL as old_data,
            NULL as new_data,
            sal.description,
            'system_log' as source,
            sal.user_id
          FROM system_activity_logs sal
          WHERE sal.category = 'authentication'";

    // Apply filters to each query
    $filter_conditions = [];
    
    if ($filter_action) {
        $filter_conditions[] = " action = :action" . $param_counter;
        $params[':action' . $param_counter] = $filter_action;
    }
    
    if ($filter_user > 0) {
        $filter_conditions[] = " user_id = :user_id" . $param_counter;
        $params[':user_id' . $param_counter] = $filter_user;
    }
    
    $filter_sql = '';
    if (!empty($filter_conditions)) {
        $filter_sql = ' AND (' . implode(' AND ', $filter_conditions) . ')';
    }
    
    // Apply filters to all queries
    $q1 .= $filter_sql;
    $q2 .= $filter_sql;
    $q3 .= $filter_sql;
    $q4 .= $filter_sql;
    
    // Combine all queries for data
    $union_query = "($q1) UNION ALL ($q2) UNION ALL ($q3) UNION ALL ($q4) 
                    ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    
    // Count query (need to count from all tables)
    $count_query = "SELECT COUNT(*) as total FROM (
                        ($q1) UNION ALL ($q2) UNION ALL ($q3) UNION ALL ($q4)
                    ) as combined";
    
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
    
    // Get unique actions for filter dropdown
    $actions_query = "SELECT DISTINCT action FROM (
                        SELECT action_type as action FROM admin_activity_logs
                        UNION
                        SELECT CASE WHEN success THEN 'LOGIN_SUCCESS' ELSE 'LOGIN_FAILED' END as action FROM login_attempts
                        UNION
                        SELECT 'PASSWORD_RESET' as action FROM password_reset_logs
                        UNION
                        SELECT action FROM system_activity_logs WHERE category = 'authentication'
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
                <li class="nav-item active">
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
        <div class="logs-panel-container">
            <div class="welcome-section glass-card">
                <h1>Activity Logs 📜</h1>
                <p>Track system changes and monitor administrative actions.</p>
            </div>
            
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
                            <option value="INSERT" <?php echo $filter_action == 'INSERT' ? 'selected' : ''; ?>>INSERT</option>
                            <option value="UPDATE" <?php echo $filter_action == 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                            <option value="DELETE" <?php echo $filter_action == 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                            <option value="LOGIN" <?php echo $filter_action == 'LOGIN' ? 'selected' : ''; ?>>LOGIN</option>
                            <option value="LOGIN_SUCCESS" <?php echo $filter_action == 'LOGIN_SUCCESS' ? 'selected' : ''; ?>>LOGIN SUCCESS</option>
                            <option value="LOGIN_FAILED" <?php echo $filter_action == 'LOGIN_FAILED' ? 'selected' : ''; ?>>LOGIN FAILED</option>
                            <option value="PASSWORD_RESET" <?php echo $filter_action == 'PASSWORD_RESET' ? 'selected' : ''; ?>>PASSWORD RESET</option>
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
                            <th>Action</th>
                            <th>Source</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                    <?php
                                    $badge_class = 'badge-info';
                                    $action = $log['action'] ?? 'UNKNOWN';
                                    
                                    if (strpos($action, 'LOGIN_SUCCESS') !== false) {
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
                                    } else if ($action == 'LOGIN') {
                                        $badge_class = 'badge-info';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($action); ?>
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
                                <td><?php echo htmlspecialchars($log['table_name'] ?? 'N/A'); ?></td>
                                <td><?php echo $log['record_id'] ?? '-'; ?></td>
                                <td>
                                    <?php 
                                    $action = $log['action'] ?? '';
                                    $description = $log['description'] ?? '';
                                    
                                    if (!empty($description)) {
                                        echo htmlspecialchars($description);
                                    } elseif (strpos($action, 'LOGIN_SUCCESS') !== false) {
                                        echo 'User logged in successfully';
                                    } elseif (strpos($action, 'LOGIN_FAILED') !== false) {
                                        echo 'Failed login attempt';
                                    } elseif (strpos($action, 'LOGOUT') !== false) {
                                        echo 'User logged out';
                                    } elseif (strpos($action, 'PASSWORD_RESET') !== false) {
                                        echo 'Password reset attempt';
                                    } elseif ($action == 'VIEW') {
                                        echo 'Viewed ' . ($log['table_name'] ?? 'page');
                                    } elseif ($action == 'INSERT') {
                                        echo 'Added new record';
                                        if (!empty($log['new_data'])) {
                                            echo '<div class="json-data">' . htmlspecialchars(is_string($log['new_data']) ? $log['new_data'] : json_encode($log['new_data'])) . '</div>';
                                        }
                                    } elseif ($action == 'UPDATE') {
                                        echo 'Updated record';
                                        if (!empty($log['old_data']) || !empty($log['new_data'])) {
                                            echo '<div class="json-data">Old: ' . htmlspecialchars(is_string($log['old_data']) ? $log['old_data'] : json_encode($log['old_data'])) . '</div>';
                                            echo '<div class="json-data">New: ' . htmlspecialchars(is_string($log['new_data']) ? $log['new_data'] : json_encode($log['new_data'])) . '</div>';
                                        }
                                    } elseif ($action == 'DELETE') {
                                        echo 'Deleted record';
                                        if (!empty($log['old_data'])) {
                                            echo '<div class="json-data">' . htmlspecialchars(is_string($log['old_data']) ? $log['old_data'] : json_encode($log['old_data'])) . '</div>';
                                        }
                                    } else {
                                        echo 'Action performed';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $ip = $log['ip_address'] ?? '-';
                                    echo htmlspecialchars($ip);
                                    if ($ip != '-' && $ip != '::1') {
                                        echo '<br><span class="text-muted">' . (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'IPv6' : 'IPv4') . '</span>';
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
                <a href="?page=1<?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page-1; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?>">
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
                    <a href="?page=<?php echo $i; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?><?php echo $filter_action ? '&action='.urlencode($filter_action) : ''; ?><?php echo $filter_user ? '&user_id='.$filter_user : ''; ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="pagination-info">
                Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo number_format($total_records); ?> total records)
            </div>
            <?php endif; ?>
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
    </script>
</body>
</html>