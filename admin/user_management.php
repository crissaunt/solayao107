<?php
// user_management.php - Super Admin admin management page
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

// Get current admin info
$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];
$is_super_admin = ($role_id == 1);
$admin_id = $user_id; // Standardized to use user_id

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all'; // For admin roles: super_admin, admin
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Handle form submissions
$message = '';
$error = '';

// Handle Add Admin
if (isset($_POST['add_admin'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $admin_role = $_POST['admin_role'] ?? 'admin'; // super_admin or admin
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!in_array($admin_role, ['super_admin', 'admin'])) $errors[] = "Valid role is required";

    if (empty($errors)) {
        try {
            // Check if username or email already exists in users table
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([':username' => $username, ':email' => $email]);
            if ($check_stmt->fetchColumn() > 0) {
                $error = "Username or email already exists";
            } else {
                // Insert new admin into users table with password hashing
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                $insert_query = "INSERT INTO users (
                    username, email, password, first_name, last_name, 
                    role_id, permissions, is_active, created_at, created_by
                ) VALUES (
                    :username, :email, :password, :first_name, :last_name,
                    :role_id, :permissions, :is_active, NOW(), :created_by
                )";
                
                $new_role_id = 2; // Forced to regular admin
                
                // Set default permissions for regular admin
                $permissions = $admin_role === 'super_admin' 
                    ? '["all"]' 
                    : '["manage_users", "view_logs"]';
                
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => $hashed_password,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':role_id' => $new_role_id,
                    ':permissions' => $permissions,
                    ':is_active' => $is_active ? 'true' : 'false',
                    ':created_by' => $admin_id
                ]);

                $new_user_id = $conn->lastInsertId();

                // Log the action in admin_activity_logs
                $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, new_data, ip_address, created_at) 
                             VALUES (:admin_id, 'INSERT', 'users', :record_id, NULL, :new_data, :ip_address, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':admin_id' => $admin_id,
                    ':record_id' => $new_user_id,
                    ':new_data' => json_encode(['username' => $username, 'email' => $email, 'role_id' => $new_role_id]),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);

                $message = "Admin added successfully";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle Edit Admin
if (isset($_POST['edit_admin'])) {
    $edit_user_id = (int)$_POST['admin_id']; // Using 'admin_id' from form but treating as user_id
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $admin_role = $_POST['admin_role'] ?? 'admin';
    $is_active = isset($_POST['is_active']) ? true : false;
    $new_password = $_POST['new_password'] ?? '';

    // Don't allow changing own role or deactivating self
    if ($edit_user_id == $admin_id) {
        $error = "You cannot edit your own admin account through this form";
    } else {
        // Validation
        $errors = [];
        if (empty($username)) $errors[] = "Username is required";
        if (empty($email)) $errors[] = "Email is required";
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (!in_array($admin_role, ['super_admin', 'admin'])) $errors[] = "Valid role is required";

        if (empty($errors)) {
            try {
                // Check if username or email already exists for other users
                $check_query = "SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND user_id != :user_id";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':user_id' => $edit_user_id
                ]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    $error = "Username or email already exists";
                } else {
                    // Get old data for logging
                    $old_data_query = "SELECT * FROM users WHERE user_id = :user_id";
                    $old_data_stmt = $conn->prepare($old_data_query);
                    $old_data_stmt->execute([':user_id' => $edit_user_id]);
                    $old_data = $old_data_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$old_data) {
                        $error = "User not found";
                    } else {
                        // Build update query
                        $update_fields = [
                            'username = :username',
                            'email = :email',
                            'first_name = :first_name',
                            'last_name = :last_name',
                            'role_id = :role_id',
                            'is_active = :is_active',
                            'updated_at = NOW()',
                            'updated_by = :updated_by'
                        ];

                        $new_role_id = 2; // Forced to regular admin

                        $params = [
                            ':username' => $username,
                            ':email' => $email,
                            ':first_name' => $first_name,
                            ':last_name' => $last_name,
                            ':role_id' => $new_role_id,
                            ':is_active' => $is_active ? 'true' : 'false',
                            ':updated_by' => $admin_id,
                            ':user_id' => $edit_user_id
                        ];

                        // Update permissions based on role
                        $permissions = $admin_role === 'super_admin' ? '["all"]' : '["manage_users", "view_logs"]';
                        $update_fields[] = 'permissions = :permissions';
                        $params[':permissions'] = $permissions;

                        // Add password if provided
                        if (!empty($new_password)) {
                            $update_fields[] = 'password = :password';
                            $params[':password'] = password_hash($new_password, PASSWORD_BCRYPT);
                        }

                        $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE user_id = :user_id";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->execute($params);

                        // Log the action
                        $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, new_data, ip_address, created_at) 
                                     VALUES (:admin_id, 'UPDATE', 'users', :record_id, :old_data, :new_data, :ip_address, NOW())";
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->execute([
                            ':admin_id' => $admin_id,
                            ':record_id' => $edit_user_id,
                            ':old_data' => json_encode($old_data),
                            ':new_data' => json_encode($_POST),
                            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);

                        $message = "Admin updated successfully";
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// Handle Toggle Status
if (isset($_POST['toggle_status'])) {
    $target_user_id = (int)$_POST['admin_id'];
    $new_status = $_POST['new_status'] == '1' ? 'true' : 'false';
    
    if ($target_user_id != $admin_id) {
        try {
            // Get old data for logging
            $old_data_query = "SELECT is_active FROM users WHERE user_id = :user_id";
            $old_data_stmt = $conn->prepare($old_data_query);
            $old_data_stmt->execute([':user_id' => $target_user_id]);
            $row = $old_data_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $old_status_val = $row['is_active'];
                $update_query = "UPDATE users SET is_active = :is_active, updated_at = NOW(), updated_by = :updated_by WHERE user_id = :user_id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute([
                    ':is_active' => $new_status,
                    ':updated_by' => $admin_id,
                    ':user_id' => $target_user_id
                ]);
                
                // Log the action
                $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, new_data, ip_address, created_at) 
                             VALUES (:admin_id, 'TOGGLE_STATUS', 'users', :record_id, :old_data, :new_data, :ip_address, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':admin_id' => $admin_id,
                    ':record_id' => $target_user_id,
                    ':old_data' => json_encode(['is_active' => $old_status_val ? 1 : 0]),
                    ':new_data' => json_encode(['is_active' => $new_status === 'true' ? 1 : 0]),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
                
                $message = "Admin status updated successfully";
            } else {
                $error = "User not found";
            }
        } catch (PDOException $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
    } else {
        $error = "You cannot change your own status";
    }
}

// Handle Delete Admin
if (isset($_POST['delete_admin'])) {
    $delete_user_id = (int)$_POST['admin_id'];
    
    if ($delete_user_id == $admin_id) {
        $error = "You cannot delete your own account";
    } else {
        try {
            $conn->beginTransaction();
            
            // Get user data for logging
            $select_query = "SELECT * FROM users WHERE user_id = :user_id";
            $select_stmt = $conn->prepare($select_query);
            $select_stmt->execute([':user_id' => $delete_user_id]);
            $user_data = $select_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                // Update references in admin_activity_logs (if they were the admin performing actions)
                $conn->prepare("UPDATE admin_activity_logs SET admin_id = :current_admin WHERE admin_id = :deleted_admin")
                    ->execute([':current_admin' => $admin_id, ':deleted_admin' => $delete_user_id]);
                
                // Delete user (or just change role back to 3 if we want to keep the record)
                // For "Delete Admin", we might just want to demote them to regular user or delete them.
                // Assuming "Delete Admin" means remove the account entirely for this refined system.
                $delete_query = "DELETE FROM users WHERE user_id = :user_id";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->execute([':user_id' => $delete_user_id]);
                
                // Log the deletion
                $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, ip_address, created_at) 
                             VALUES (:admin_id, 'DELETE', 'users', :record_id, :old_data, :ip_address, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':admin_id' => $admin_id,
                    ':record_id' => $delete_user_id,
                    ':old_data' => json_encode($user_data),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);
                
                $conn->commit();
                $message = "Admin deleted successfully";
            } else {
                $error = "User not found";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Failed to delete: " . $e->getMessage();
        }
    }
}

// Handle Export Admins
if (isset($_GET['export'])) {
    // Get all admins for export
    $export_query = "
        SELECT 
            a.username, a.email, a.first_name, a.last_name, r.role_name as role, a.permissions,
            a.is_active, a.created_at, a.last_login,
            (SELECT username FROM users a2 WHERE a2.user_id = a.created_by) as created_by_username,
            (SELECT username FROM users a2 WHERE a2.user_id = a.updated_by) as updated_by_username
        FROM users a
        JOIN roles r ON a.role_id = r.role_id
        WHERE a.role_id IN (1, 2)
        ORDER BY a.created_at DESC
    ";
    $export_stmt = $conn->query($export_query);
    $export_admins = $export_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="admins_export_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, ['Username', 'Email', 'First Name', 'Last Name', 'Role', 'Permissions', 'Status', 'Created', 'Last Login', 'Created By', 'Updated By']);
    
    // Add data rows
    foreach ($export_admins as $admin) {
        fputcsv($output, [
            $admin['username'] ?? '',
            $admin['email'] ?? '',
            $admin['first_name'] ?? '',
            $admin['last_name'] ?? '',
            $admin['role'] ?? '',
            $admin['permissions'] ?? '',
            $admin['is_active'] ? 'Active' : 'Inactive',
            $admin['created_at'] ?? '',
            $admin['last_login'] ?? '',
            $admin['created_by_username'] ?? '',
            $admin['updated_by_username'] ?? ''
        ]);
    }
    
    fclose($output);
    exit();
}

// Build query conditions for staff list
$conditions = ["a.role_id = 2"]; // Only manage regular admins (Staff)
$params = [];

if (!empty($search)) {
    $conditions[] = "(a.first_name ILIKE :search OR a.last_name ILIKE :search OR a.username ILIKE :search OR a.email ILIKE :search)";
    $params[':search'] = "%$search%";
}

// Role filter removed as it's now exclusively for regular admins

if ($status_filter !== 'all') {
    $is_active_val = ($status_filter === 'active') ? 'true' : 'false';
    $conditions[] = "a.is_active = $is_active_val";
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Get total admins count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM users a 
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total_admins = $count_result ? (int)$count_result['total'] : 0;
$total_pages = $total_admins > 0 ? ceil($total_admins / $limit) : 1;

// Get admins with their details
$query = "
    SELECT 
        a.user_id as admin_id,
        a.username,
        a.email,
        a.first_name,
        a.last_name,
        r.role_name as role,
        a.permissions,
        a.is_active,
        a.created_at,
        a.last_login,
        a.updated_at,
        creator.username as created_by_username,
        updater.username as updated_by_username,
        (SELECT COUNT(*) FROM admin_activity_logs l WHERE l.admin_id = a.user_id) as activity_count
    FROM users a
    JOIN roles r ON a.role_id = r.role_id
    LEFT JOIN users creator ON a.created_by = creator.user_id
    LEFT JOIN users updater ON a.updated_by = updater.user_id
    $where_clause
    ORDER BY 
        a.role_id ASC,
        a.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current admin info
$current_admin_query = "
    SELECT u.first_name, u.last_name, u.username, r.role_name as role 
    FROM users u 
    JOIN roles r ON u.role_id = r.role_id 
    WHERE u.user_id = :user_id";
$current_admin_stmt = $conn->prepare($current_admin_query);
$current_admin_stmt->execute([':user_id' => $user_id]);
$current_admin = $current_admin_stmt->fetch(PDO::FETCH_ASSOC);

// Provide default values if current admin not found
if (!$current_admin) {
    $current_admin = [
        'first_name' => 'Admin',
        'last_name' => 'User',
        'username' => 'admin',
        'role' => 'super_admin'
    ];
}

// Get user initials for avatar
$full_name = ($current_admin['first_name'] ?? 'Admin') . ' ' . ($current_admin['last_name'] ?? 'User');
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
    <title>Staff Management - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        /* Staff Management specific styles - same as before */
        .container {
            padding: 1.2rem 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            color: #1c4c29;
            font-size: 13px;
            font-weight: 600;
            border-left: 8px solid #509c5b;
            padding-left: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            font-size: 13px;
            border-radius: 2px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2a6e3b;
            color: white;
        }

        .btn-primary:hover {
            background: #1d542b;
        }

        .btn-secondary {
            background: #fff;
            border: 1px solid #589065;
            color: #1b572b;
        }

        .btn-secondary:hover {
            background: #226f36;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #1e3a2f;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-info {
            background: #FFA500;
            color: black;
            border: none; 
        }

        .btn-info:hover {
            background: #E69500; /* A clearly darker orange */
            cursor: pointer;    /* Ensures the hand icon appears */
        }

        /* Filters */
        .filters-section {
            background: #fafff9;
            padding: 1rem;
            border: 1px solid #cbe6bf;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }

        .filters-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1 1 180px;
            min-width: 180px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.3rem;
            color: #1d4d2d;
            font-size: 11px;
            font-weight: 600;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #afcfaa;
            border-radius: 2px;
            font-size: 13px;
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: #fafff9;
            padding: 1rem;
            border: 1px solid #cbe6bf;
            border-radius: 5px;
            text-align: center;
        }

        .stat-card h3 {
            color: #1d4d2d;
            font-size: 11px;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }

        .stat-card .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #0f4d1f;
        }

        /* Messages */
        .message {
            padding: 0.8rem 1rem;
            border-radius: 2px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Admins Table */
        .table-container {
            background: #fafff9;
            padding: 1rem;
            border: 1px solid #cbe6bf;
            border-radius: 5px;
            overflow-x: auto;
            margin-bottom: 1.5rem;
        }

        .admins-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
            font-size: 13px;
        }

        .admins-table th {
            padding: 0.8rem 0.6rem;
            text-align: left;
            font-weight: 600;
            color: #1d4d2d;
            border-bottom: 2px solid #cbe6bf;
            background: #f2f7f0;
        }

        .admins-table td {
            padding: 0.7rem 0.6rem;
            border-bottom: 1px solid #d8e8d0;
            vertical-align: middle;
        }

        .admins-table tr:hover {
            background: #f2f7f0;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .admin-avatar {
            width: 32px;
            height: 32px;
            background: #2a6e3b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 11px;
            border-radius: 50%;
        }

        .admin-details {
            line-height: 1.3;
        }

        .admin-name {
            font-weight: 600;
            color: #1c4c29;
            font-size: 13px;
        }

        .admin-email {
            font-size: 11px;
            color: #6b7c6b;
        }

        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            font-size: 11px;
            font-weight: 600;
            border-radius: 2px;
        }

        .role-badge.super_admin {
            background: #1a3b2a;
            color: #d8f0d0;
            border: 1px solid #5f9e6b;
        }

        .role-badge.admin {
            background: #2d5a3a;
            color: #f0f9e8;
            border: 1px solid #7fbf7f;
        }

        .badge {
            padding: 0.2rem 0.6rem;
            font-size: 11px;
            font-weight: 600;
            border-radius: 2px;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .action-buttons {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.3rem 0.6rem;
            font-size: 11px;
            border: 1px solid transparent;
            border-radius: 2px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: transparent;
        }

        .action-btn-edit {
            background: #daf3d1;
            color: #0f4d1f;
            border-color: #a5cf9b;
        }

        .action-btn-edit:hover {
            background: #cbe6c1;
        }

        .action-btn-delete {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .action-btn-delete:hover {
            background: #f1b0b7;
        }

        .action-btn-toggle {
            background: #e2e8f0;
            color: #4a5568;
            border-color: #cbd5e0;
        }

        .action-btn-toggle.active {
            background: #daf3d1;
            color: #0f4d1f;
            border-color: #a5cf9b;
        }

        .action-btn-view {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 40, 0, 0.5);
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        .modal-content {
            background: #fafff9;
            padding: 1.5rem;
            max-width: 500px;
            width: 90%;
            margin: 1rem;
            border: 1px solid #cbe6bf;
            border-radius: 5px;
            box-shadow: 0 10px 30px rgba(32, 72, 52, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: #fafff9;
            z-index: 10;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #cbe6bf;
        }

        .modal-header h3 {
            color: #1c4c29;
            font-size: 13px;
            font-weight: 600;
        }

        .modal-header .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7c6b;
        }

        .modal-header .close:hover {
            color: #1c4c29;
        }

        .modal-body {
            margin-bottom: 1.2rem;
        }

        .modal-footer {
            display: flex;
            gap: 0.8rem;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: #fafff9;
            padding-top: 1rem;
            border-top: 1px solid #cbe6bf;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            color: #1d4d2d;
            font-size: 11px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #afcfaa;
            border-radius: 2px;
            font-size: 13px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2a6e3b;
            box-shadow: 0 0 0 2px rgba(42, 110, 59, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.3rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 0.4rem 0.8rem;
            text-decoration: none;
            color: #1b572b;
            background: #fafff9;
            border: 1px solid #cbe6bf;
            border-radius: 2px;
            font-size: 13px;
            min-width: 32px;
            text-align: center;
        }

        .pagination a:hover {
            background: #daf3d1;
        }

        .pagination .active {
            background: #2a6e3b;
            color: white;
            border-color: #1c4c2a;
        }

        .pagination-info {
            text-align: center;
            margin-top: 0.5rem;
            color: #6b7c6b;
            font-size: 11px;
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

        .note {
            font-size: 11px;
            color: #6b7c6b;
            margin-top: 1rem;
            font-style: italic;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions .btn {
                flex: 1;
            }
            
            .filters-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-actions {
                width: 100%;
            }
            
            .filter-actions .btn {
                flex: 1;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }
            
            .page-header h1 {
                font-size: 13px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
                    <div class="user-name"><?php echo htmlspecialchars($current_admin['first_name'] ?? 'Admin'); ?></div>
                    <div class="user-role">
                        <span class="role-badge <?php echo $current_admin['role'] ?? 'super_admin'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $current_admin['role'] ?? 'Super Admin')); ?>
                        </span>
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
                <li class="nav-item">
                    <a href="admin.php">
                        <i class="fas fa-cog"></i>
                        <span>Admin Panel</span>
                    </a>
                </li>
                <li class="nav-item active">
                    <a href="user_management.php">
                        <i class="fas fa-users-cog"></i>
                        <span>Staff Management</span>
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
                <h1>Staff Management</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Admin
                    </button>
                    <a href="?export=1" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                    <a href="users.php" class="btn btn-info">
                        <i class="fas fa-users"></i> View Regular Users
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <?php
            $total_staff = 0;
            $active_staff = 0;
            $inactive_staff = 0;
            
            if (!empty($admins)) {
                foreach ($admins as $admin) {
                    $total_staff++;
                    if (isset($admin['is_active']) && $admin['is_active']) {
                        $active_staff++;
                    } else {
                        $inactive_staff++;
                    }
                }
            }
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Staff</h3>
                    <div class="stat-value"><?php echo $total_staff; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Staff</h3>
                    <div class="stat-value"><?php echo $active_staff; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Inactive Staff</h3>
                    <div class="stat-value"><?php echo $inactive_staff; ?></div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="message message-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message message-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, email, username..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    


                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="user_management.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Admins Table -->
            <div class="table-container">
                <table class="admins-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created By</th>
                            <th>Activities</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: #6b7c6b;">
                                    <i class="fas fa-user-shield" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <br>
                                    No admins found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($admins as $admin): 
                                $full_name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="admin-info">
                                        <div class="admin-avatar">
                                            <?php 
                                            $first_initial = !empty($admin['first_name']) ? substr($admin['first_name'], 0, 1) : 'A';
                                            $last_initial = !empty($admin['last_name']) ? substr($admin['last_name'], 0, 1) : 'U';
                                            echo strtoupper($first_initial . $last_initial);
                                            ?>
                                        </div>
                                        <div class="admin-details">
                                            <div class="admin-name"><?php echo htmlspecialchars($full_name ?: 'Unnamed'); ?></div>
                                            <div class="admin-email"><?php echo htmlspecialchars($admin['email'] ?? 'No email'); ?></div>
                                            <div class="text-muted">@<?php echo htmlspecialchars($admin['username'] ?? 'unknown'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $admin['role'] ?? 'admin'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $admin['role'] ?? 'Admin')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $perms = !empty($admin['permissions']) ? json_decode($admin['permissions'], true) : [];
                                    if (is_array($perms)) {
                                        if (in_array('all', $perms)) {
                                            echo '<span class="badge badge-info">Full Access</span>';
                                        } else {
                                            // Display actual permissions instead of just count
                                            $perm_labels = [];
                                            foreach ($perms as $perm) {
                                                switch ($perm) {
                                                    case 'manage_users':
                                                        $perm_labels[] = '<span class="badge badge-secondary" style="background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; margin: 2px; padding: 2px 6px; display: inline-block;">👥 Users</span>';
                                                        break;
                                                    case 'view_logs':
                                                        $perm_labels[] = '<span class="badge badge-secondary" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; margin: 2px; padding: 2px 6px; display: inline-block;">📋 Logs</span>';
                                                        break;
                                                    case 'manage_admins':
                                                        $perm_labels[] = '<span class="badge badge-secondary" style="background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; margin: 2px; padding: 2px 6px; display: inline-block;">👑 Admins</span>';
                                                        break;
                                                    case 'system_settings':
                                                        $perm_labels[] = '<span class="badge badge-secondary" style="background: #f3e5f5; color: #4a148c; border: 1px solid #e1bee7; margin: 2px; padding: 2px 6px; display: inline-block;">⚙️ Settings</span>';
                                                        break;
                                                    default:
                                                        $perm_labels[] = '<span class="badge badge-secondary" style="background: #f5f5f5; color: #424242; border: 1px solid #e0e0e0; margin: 2px; padding: 2px 6px; display: inline-block;">' . htmlspecialchars($perm) . '</span>';
                                                }
                                            }
                                            echo implode(' ', $perm_labels);
                                        }
                                    } else {
                                        echo '<span class="badge badge-secondary">No permissions</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($admin['is_active']) && $admin['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo !empty($admin['last_login']) ? date('M d, Y H:i', strtotime($admin['last_login'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($admin['created_by_username'] ?? 'System'); ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $admin['activity_count'] ?? 0; ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-edit" onclick="openEditModal(<?php echo $admin['admin_id']; ?>)" <?php echo ($admin['admin_id'] ?? 0) == $admin_id ? 'disabled' : ''; ?>>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if (($admin['admin_id'] ?? 0) != $admin_id): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id'] ?? ''; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo !empty($admin['is_active']) ? '0' : '1'; ?>">
                                                <button type="submit" name="toggle_status" class="action-btn <?php echo !empty($admin['is_active']) ? 'action-btn-toggle' : 'action-btn-toggle active'; ?>" 
                                                        title="<?php echo !empty($admin['is_active']) ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas <?php echo !empty($admin['is_active']) ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <button type="button" class="action-btn action-btn-delete" title="Delete" onclick="confirmDelete(<?php echo $admin['admin_id']; ?>, '<?php echo htmlspecialchars($full_name); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
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
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="pagination-info">
                    Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_admins); ?> of <?php echo $total_admins; ?> admins
                </div>
            <?php endif; ?>
            
            <div class="note">
                <i class="fas fa-info-circle"></i> This page manages administrative staff only. Super Admin accounts are managed separately for security. For regular user management, go to <a href="users.php">Users</a>.
            </div>
        </div>
    </main>

    <!-- Add Admin Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Admin</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="Staff Admin" disabled>
                            <input type="hidden" name="admin_role" value="admin">
                        </div>
                        <div class="form-group full-width checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active_add" checked>
                            <label for="is_active_add">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_admin" class="btn btn-success">Add Admin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Admin</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" id="edit_username" required>
                        </div>
                        <div class="form-group">
                            <label>New Password (leave blank to keep current)</label>
                            <input type="password" name="new_password" id="edit_password">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" id="edit_email" required>
                        </div>
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" id="edit_last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="Staff Admin" disabled>
                            <input type="hidden" name="admin_role" value="admin">
                        </div>
                        <div class="form-group full-width checkbox-group">
                            <input type="checkbox" name="is_active" id="edit_is_active">
                            <label for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_admin" class="btn btn-success">Update Admin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_admin_name"></strong>?</p>
                <p style="font-size: 11px; color: #dc3545; margin-top: 0.5rem;"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="admin_id" id="delete_admin_id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_admin" class="btn btn-danger">Confirm Delete</button>
                </div>
            </form>
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
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openEditModal(adminId) {
            // Fetch admin data via AJAX
            fetch(`get_admin.php?id=${adminId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    document.getElementById('edit_admin_id').value = data.admin_id || '';
                    document.getElementById('edit_username').value = data.username || '';
                    document.getElementById('edit_email').value = data.email || '';
                    document.getElementById('edit_first_name').value = data.first_name || '';
                    document.getElementById('edit_last_name').value = data.last_name || '';
                    // Role is fixed to staff admin for this page, so we don't need to set it
                    document.getElementById('edit_is_active').checked = data.is_active == 1;
                    
                    document.getElementById('editModal').style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load admin data. Please try again.');
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function confirmDelete(adminId, adminName) {
            document.getElementById('delete_admin_id').value = adminId;
            document.getElementById('delete_admin_name').textContent = adminName;
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // Handle ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
                closeDeleteModal();
                
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.message').forEach(function(message) {
                if (message) message.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>