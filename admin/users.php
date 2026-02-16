<?php
// users.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in and has admin access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'] ?? 0;
$is_super_admin = ($role_id == 1);
$is_admin = ($role_id <= 2); // Super Admin or Admin

// Redirect if not admin
if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$role_filter = isset($_GET['role']) ? (int)$_GET['role'] : 0;

// Handle Add User
if (isset($_POST['add_user'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $id_number = $_POST['id_number'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $extension_name = $_POST['extension_name'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $age = $_POST['age'] ?? null;
    $sex = $_POST['sex'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $street_purok = $_POST['street_purok'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $city_municipal = $_POST['city_municipal'] ?? '';
    $province = $_POST['province'] ?? '';
    $country = $_POST['country'] ?? 'Philippines';
    $zipcode = $_POST['zipcode'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 3); // Default to regular user role
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";

    if (empty($errors)) {
        try {
            // Check if username, email, or id_number already exists
            $check_query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
            $params = [':username' => $username, ':email' => $email];
            
            if (!empty($id_number)) {
                $check_query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email OR id_number = :id_number";
                $params[':id_number'] = $id_number;
            }
            
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute($params);
            
            if ($check_stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Username, email, or ID number already exists";
            } else {
                // Calculate age if birthday is provided
                if (!empty($birthday)) {
                    $birthdate = new DateTime($birthday);
                    $today = new DateTime();
                    $age = $today->diff($birthdate)->y;
                }

                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $insert_query = "INSERT INTO users (
                    username, password, email, id_number, first_name, middle_name, last_name, extension_name,
                    birthday, age, sex, contact_number,
                    street_purok, barangay, city_municipal, province, country, zipcode,
                    role_id, is_active, created_at
                ) VALUES (
                    :username, :password, :email, :id_number, :first_name, :middle_name, :last_name, :extension_name,
                    :birthday, :age, :sex, :contact_number,
                    :street_purok, :barangay, :city_municipal, :province, :country, :zipcode,
                    :role_id, :is_active, NOW()
                )";
                
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->execute([
                    ':username' => $username,
                    ':password' => $hashed_password,
                    ':email' => $email,
                    ':id_number' => $id_number ?: null,
                    ':first_name' => $first_name,
                    ':middle_name' => $middle_name ?: null,
                    ':last_name' => $last_name,
                    ':extension_name' => $extension_name ?: null,
                    ':birthday' => $birthday ?: null,
                    ':age' => $age,
                    ':sex' => $sex ?: null,
                    ':contact_number' => $contact_number ?: null,
                    ':street_purok' => $street_purok ?: null,
                    ':barangay' => $barangay ?: null,
                    ':city_municipal' => $city_municipal ?: null,
                    ':province' => $province ?: null,
                    ':country' => $country,
                    ':zipcode' => $zipcode ?: null,
                    ':role_id' => $role_id,
                    ':is_active' => $is_active
                ]);

                $new_user_id = $conn->lastInsertId();

                // Log the action
                $log_query = "INSERT INTO activity_logs (table_name, record_id, action, performed_by, ip_address, user_agent, created_at) 
                             VALUES ('users', :record_id, 'INSERT', :performed_by, :ip_address, :user_agent, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':record_id' => $new_user_id,
                    ':performed_by' => $user_id,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);

                $_SESSION['success_message'] = "User added successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $edit_user_id = (int)$_POST['user_id'];
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $id_number = $_POST['id_number'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $extension_name = $_POST['extension_name'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $street_purok = $_POST['street_purok'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $city_municipal = $_POST['city_municipal'] ?? '';
    $province = $_POST['province'] ?? '';
    $country = $_POST['country'] ?? 'Philippines';
    $zipcode = $_POST['zipcode'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 3);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';

    // Validation
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";

    if (empty($errors)) {
        try {
            // Check if username, email, or id_number already exists for other users
            $check_query = "SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email) AND user_id != :user_id";
            $params = [
                ':username' => $username,
                ':email' => $email,
                ':user_id' => $edit_user_id
            ];
            
            if (!empty($id_number)) {
                $check_query = "SELECT COUNT(*) FROM users WHERE (username = :username OR email = :email OR id_number = :id_number) AND user_id != :user_id";
                $params[':id_number'] = $id_number;
            }
            
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute($params);
            
            if ($check_stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Username, email, or ID number already exists";
            } else {
                // Calculate age if birthday is provided
                $age = null;
                if (!empty($birthday)) {
                    $birthdate = new DateTime($birthday);
                    $today = new DateTime();
                    $age = $today->diff($birthdate)->y;
                }

                // Build update query
                $update_fields = [
                    'username = :username',
                    'email = :email',
                    'id_number = :id_number',
                    'first_name = :first_name',
                    'middle_name = :middle_name',
                    'last_name = :last_name',
                    'extension_name = :extension_name',
                    'birthday = :birthday',
                    'age = :age',
                    'sex = :sex',
                    'contact_number = :contact_number',
                    'street_purok = :street_purok',
                    'barangay = :barangay',
                    'city_municipal = :city_municipal',
                    'province = :province',
                    'country = :country',
                    'zipcode = :zipcode',
                    'role_id = :role_id',
                    'is_active = :is_active',
                    'updated_at = NOW()'
                ];

                $params = [
                    ':username' => $username,
                    ':email' => $email,
                    ':id_number' => $id_number ?: null,
                    ':first_name' => $first_name,
                    ':middle_name' => $middle_name ?: null,
                    ':last_name' => $last_name,
                    ':extension_name' => $extension_name ?: null,
                    ':birthday' => $birthday ?: null,
                    ':age' => $age,
                    ':sex' => $sex ?: null,
                    ':contact_number' => $contact_number ?: null,
                    ':street_purok' => $street_purok ?: null,
                    ':barangay' => $barangay ?: null,
                    ':city_municipal' => $city_municipal ?: null,
                    ':province' => $province ?: null,
                    ':country' => $country,
                    ':zipcode' => $zipcode ?: null,
                    ':role_id' => $role_id,
                    ':is_active' => $is_active,
                    ':user_id' => $edit_user_id
                ];

                // Add password if provided
                if (!empty($new_password)) {
                    $update_fields[] = 'password = :password';
                    $params[':password'] = password_hash($new_password, PASSWORD_DEFAULT);
                }

                $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE user_id = :user_id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->execute($params);

                // Log the action
                $log_query = "INSERT INTO activity_logs (table_name, record_id, action, performed_by, ip_address, user_agent, created_at) 
                             VALUES ('users', :record_id, 'UPDATE', :performed_by, :ip_address, :user_agent, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':record_id' => $edit_user_id,
                    ':performed_by' => $user_id,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);

                $_SESSION['success_message'] = "User updated successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle User Status Toggle
if (isset($_POST['toggle_status']) && $is_admin) {
    $target_user_id = (int)$_POST['user_id'];
    $new_status = $_POST['new_status'] === 'true' ? true : false;
    
    try {
        $update_query = "UPDATE users SET is_active = :is_active, updated_at = NOW() WHERE user_id = :user_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->execute([
            ':is_active' => $new_status,
            ':user_id' => $target_user_id
        ]);
        
        // Log the action
        $log_query = "INSERT INTO activity_logs (table_name, record_id, action, old_data, new_data, performed_by, ip_address, user_agent, created_at) 
                     VALUES ('users', :record_id, 'UPDATE', :old_data, :new_data, :performed_by, :ip_address, :user_agent, NOW())";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([
            ':record_id' => $target_user_id,
            ':old_data' => json_encode(['is_active' => !$new_status]),
            ':new_data' => json_encode(['is_active' => $new_status]),
            ':performed_by' => $user_id,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        $_SESSION['success_message'] = "User status updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to update user status.";
    }
    
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle User Deletion (super admin only)
if (isset($_POST['delete_user']) && $is_super_admin) {
    $target_user_id = (int)$_POST['user_id'];
    
    // Don't allow deleting yourself
    if ($target_user_id == $user_id) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Get user data for logging
            $select_query = "SELECT * FROM users WHERE user_id = :user_id";
            $select_stmt = $conn->prepare($select_query);
            $select_stmt->execute([':user_id' => $target_user_id]);
            $user_data = $select_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                // Delete related records first (foreign key constraints)
                $conn->prepare("DELETE FROM activity_logs WHERE performed_by = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM login_attempts WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM password_reset_logs WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM password_reset_sessions WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM user_security_answers WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                
                // Delete the user
                $delete_query = "DELETE FROM users WHERE user_id = :user_id";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->execute([':user_id' => $target_user_id]);
                
                // Log the deletion
                $log_query = "INSERT INTO activity_logs (table_name, record_id, action, old_data, performed_by, ip_address, user_agent, created_at) 
                             VALUES ('users', :record_id, 'DELETE', :old_data, :performed_by, :ip_address, :user_agent, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':record_id' => $target_user_id,
                    ':old_data' => json_encode($user_data),
                    ':performed_by' => $user_id,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);
                
                $conn->commit();
                $_SESSION['success_message'] = "User deleted successfully.";
            } else {
                $_SESSION['error_message'] = "User not found.";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error_message'] = "Failed to delete user: " . $e->getMessage();
        }
    }
    
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(u.first_name ILIKE :search OR u.last_name ILIKE :search OR u.username ILIKE :search OR u.email ILIKE :search OR u.id_number ILIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status_filter !== 'all') {
    $is_active = ($status_filter === 'active') ? 'true' : 'false';
    $conditions[] = "u.is_active = $is_active";
}

if ($role_filter > 0) {
    $conditions[] = "u.role_id = :role_id";
    $params[':role_id'] = $role_filter;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total users count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.role_id 
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $limit);

// Get users with their roles and activity stats
$query = "
    SELECT 
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
        r.role_description,
        (
            SELECT COUNT(*) 
            FROM activity_logs a 
            WHERE a.performed_by = u.user_id 
            AND a.created_at >= NOW() - INTERVAL '30 days'
        ) as activities_30d,
        (
            SELECT MAX(created_at) 
            FROM activity_logs a 
            WHERE a.performed_by = u.user_id
        ) as last_activity,
        (
            SELECT COUNT(*) 
            FROM login_attempts la 
            WHERE la.user_id = u.user_id 
            AND la.success = false 
            AND la.attempt_time >= NOW() - INTERVAL '24 hours'
        ) as failed_attempts_24h
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id
    $where_clause
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles for filter dropdown and forms
$roles_query = "SELECT role_id, role_name FROM roles WHERE is_active = true ORDER BY role_name";
$roles_stmt = $conn->query($roles_query);
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN is_active = true THEN 1 END) as active_users,
        COUNT(CASE WHEN is_active = false THEN 1 END) as inactive_users,
        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '7 days' THEN 1 END) as new_this_week,
        COUNT(CASE WHEN last_login >= NOW() - INTERVAL '24 hours' THEN 1 END) as online_24h
    FROM users
";
$stats_stmt = $conn->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Log this page view
try {
    $log_query = "INSERT INTO activity_logs (table_name, action, performed_by, ip_address, user_agent, created_at) 
                 VALUES ('users', 'VIEW_LIST', :performed_by, :ip_address, :user_agent, NOW())";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->execute([
        ':performed_by' => $user_id,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
} catch (PDOException $e) {
    // Silently fail if logging doesn't work
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
    <title>Manage Users - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        /* Additional page-specific styles - optimized */
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
            font-size: 24px;
            font-weight: 600;
            border-left: 8px solid #509c5b;
            padding-left: 1rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            font-size: 14px;
            border-radius: 4px;
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

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: #fafff9;
            padding: 1rem;
            border: 1px solid #cbe6bf;
            border-radius: 4px;
            text-align: center;
        }

        .stat-card h3 {
            color: #1d4d2d;
            font-size: 12px;
            margin-bottom: 0.3rem;
            text-transform: uppercase;
        }

        .stat-card .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #0f4d1f;
        }

        /* Alert Messages */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            opacity: 0.5;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Filters */
        .filters-section {
            background: #fafff9;
            padding: 1rem;
            border: 1px solid #cbe6bf;
            border-radius: 4px;
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
            font-size: 12px;
            font-weight: 600;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.6rem 12px;
            border: 1px solid #afcfaa;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Users Table */
        .users-table-container {
            background: #fafff9;
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(40, 80, 30, 0.1);
            border: 1px solid #cbe6bf;
            border-radius: 4px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1.5rem;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
            font-size: 14px;
        }

        .users-table th {
            padding: 12px 0.6rem;
            text-align: left;
            font-weight: 600;
            color: #1d4d2d;
            border-bottom: 2px solid #cbe6bf;
            white-space: nowrap;
            background: #f2f7f0;
        }

        .users-table td {
            padding: 0.7rem 0.6rem;
            border-bottom: 1px solid #d8e8d0;
            color: #1e3a2f;
            vertical-align: middle;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .users-table tr:hover {
            background: #f2f7f0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
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
            font-size: 14px;
            border: 2px solid #a5cf9b;
            border-radius: 50%;
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
            font-size: 12px;
            color: #6b7c6b;
        }

        .role-badge {
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.2rem 0.6rem;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #cbd5e0;
            border-radius: 3px;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.3rem 0.6rem;
            font-size: 12px;
            border: 1px solid transparent;
            border-radius: 3px;
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

        .action-btn-toggle.inactive {
            background: #daf3d1;
            color: #0f4d1f;
            border-color: #a5cf9b;
        }

        .action-btn-view {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        .badge {
            padding: 0.25rem 0.6rem;
            font-size: 12px;
            border-radius: 3px;
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

        .online-indicator {
            width: 6px;
            height: 6px;
            background: #48bb78;
            display: inline-block;
            margin-right: 4px;
            border-radius: 50%;
        }

        .text-muted {
            font-size: 12px;
            color: #6b7c6b;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.3rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 0.4rem 12px;
            font-size: 14px;
            text-decoration: none;
            color: #1b572b;
            background: #fafff9;
            border: 1px solid #cbe6bf;
            border-radius: 4px;
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

        /* Modal Styles */
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
            max-width: 600px;
            width: 90%;
            margin: 1rem;
            border: 1px solid #cbe6bf;
            border-radius: 6px;
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
            font-size: 16px;
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
            gap: 12px;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: #fafff9;
            padding-top: 1rem;
            border-top: 1px solid #cbe6bf;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 0.5rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            color: #1d4d2d;
            font-size: 13px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #afcfaa;
            border-radius: 4px;
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

        .section-header {
            grid-column: span 2;
            color: #1c4c29;
            font-weight: 600;
            margin-top: 0.5rem;
            padding-bottom: 0.3rem;
            border-bottom: 1px solid #cbe6bf;
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width,
            .section-header {
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header .btn {
                width: 100%;
                justify-content: center;
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
                font-size: 18px;
            }
        }

        /* Add this to your existing styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }

        .modal-content {
            background-color: rgb(248, 246, 239);
            border: 3px solid #30833f;
            border-radius: 5px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header .close:hover {
            color: #205d1c;
        }

        .input-box input:focus,
        .input-box select:focus {
            outline: none;
            border-color: #2a6e3b !important;
            box-shadow: 0 0 5px rgba(42, 110, 59, 0.3);
        }

        .btn-success:hover {
            background-color: #2a6e3b !important;
        }

        .btn-secondary:hover {
            background-color: #5a6268 !important;
        }

        .btn-danger:hover {
            background-color: #c82333 !important;
        }

        /* Custom scrollbar for modal body */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #30833f;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #205d1c;
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
                <li class="nav-item active">
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
                <li class="nav-item">
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
        <div class="container">
            <div class="page-header">
                <h1>Manage Users</h1>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Users</h3>
                    <div class="stat-value"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Inactive Users</h3>
                    <div class="stat-value"><?php echo number_format($stats['inactive_users'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>New This Week</h3>
                    <div class="stat-value"><?php echo number_format($stats['new_this_week'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Online (24h)</h3>
                    <div class="stat-value"><?php echo number_format($stats['online_24h'] ?? 0); ?></div>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Name, email, username, ID..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="0">All Roles</option>
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>" <?php echo $role_filter == $role['role_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="users-table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Activity</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: #6b7c6b;">
                                <i class="fas fa-users" style="font-size: 24px; margin-bottom: 12px; opacity: 0.5;"></i>
                                <br>
                                No users found.
                                <?php if (!empty($search) || $status_filter !== 'all' || $role_filter > 0): ?>
                                <br>
                                <a href="users.php" style="color: #2a6e3b;">Clear filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php 
                                            $initial1 = strtoupper(substr($user['first_name'] ?? 'U', 0, 1));
                                            $initial2 = strtoupper(substr($user['last_name'] ?? 'S', 0, 1));
                                            echo $initial1 . $initial2;
                                            ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name">
                                                <?php 
                                                $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                                                if (!empty($user['extension_name'])) {
                                                    $full_name .= ' ' . $user['extension_name'];
                                                }
                                                echo htmlspecialchars($full_name ?: 'Unnamed User');
                                                ?>
                                            </div>
                                            <div class="user-email">
                                                <?php echo htmlspecialchars($user['email'] ?? 'No email'); ?>
                                                <?php if (!empty($user['username'])): ?>
                                                <br>@<?php echo htmlspecialchars($user['username']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($user['id_number'])): ?>
                                            <div class="text-muted">ID: <?php echo htmlspecialchars($user['id_number']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($user['sex'])): ?>
                                    <div><?php echo htmlspecialchars($user['sex']); ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted"><?php echo htmlspecialchars($user['contact_number'] ?? 'No contact'); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $location = array_filter([
                                        $user['barangay'] ?? '',
                                        $user['city_municipal'] ?? '',
                                        $user['province'] ?? ''
                                    ]);
                                    echo htmlspecialchars(!empty($location) ? implode(', ', $location) : 'No location');
                                    ?>
                                </td>
                                <td>
                                    <span class="role-badge">
                                        <?php echo htmlspecialchars($user['role_name'] ?? 'No Role'); ?>
                                    </span>
                                    <?php if ($user['failed_attempts_24h'] > 0): ?>
                                    <div class="text-muted" style="color: #721c24; margin-top: 4px;">
                                        <?php echo $user['failed_attempts_24h']; ?> failed attempts
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                    <span class="badge badge-success">
                                        <span class="online-indicator"></span> Active
                                    </span>
                                    <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $last_login = $user['last_login'] ?? null;
                                    if ($last_login && strtotime($last_login) > strtotime('-24 hours')): 
                                    ?>
                                    <div class="text-muted" style="margin-top: 4px;">Online now</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['activities_30d'] > 0): ?>
                                    <div><?php echo $user['activities_30d']; ?> actions (30d)</div>
                                    <?php if ($user['last_activity']): ?>
                                    <div class="text-muted">
                                        Last: <?php echo date('M d, H:i', strtotime($user['last_activity'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">No activity</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                    <?php if ($user['last_login']): ?>
                                    <div class="text-muted">
                                        Last login: <?php echo date('M d, H:i', strtotime($user['last_login'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-view" onclick="viewUser(<?php echo $user['user_id']; ?>)" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn action-btn-edit" onclick="openEditModal(<?php echo $user['user_id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <?php if ($user['user_id'] != $user_id): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $user['is_active'] ? 'false' : 'true'; ?>">
                                            <button type="submit" name="toggle_status" class="action-btn <?php echo $user['is_active'] ? 'action-btn-toggle' : 'action-btn-toggle inactive'; ?>" 
                                                    title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas <?php echo $user['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if ($is_super_admin): ?>
                                        <button onclick="showDeleteModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($full_name)); ?>')" 
                                                class="action-btn action-btn-delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
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
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 12px; color: #6b7c6b; font-size: 14px;">
                Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> users
            </div>
            <?php endif; ?>
        </div>
    </main>
    
<!-- Add User Modal -->
<div id="addModal" class="modal">
    <div class="modal-content" style="max-width: 900px; width: 95%; padding: 20px;">
        <div class="modal-header" style="border-bottom: 2px solid #3b8739; padding-bottom: 10px; margin-bottom: 20px;">
            <h3 style="color: #3b8739; font-size: 24px; font-weight: bold; letter-spacing: 2px;">Personal Information</h3>
            <span class="close" onclick="closeAddModal()" style="font-size: 28px; color: #808080; cursor: pointer;">&times;</span>
        </div>
        <form method="POST" id="addForm">
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding-right: 10px;">
                <!-- Personal Information Section -->
                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="add_id_number" style="font-size: 13px; color: #808080; font-weight: 700;">ID number:</label>
                        <input type="text" id="add_id_number" name="id_number" placeholder="Ex. 0000-0000" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_username" style="font-size: 13px; color: #808080; font-weight: 700;">Username:</label>
                        <input type="text" id="add_username" name="username" placeholder="Ex. alex24" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_email" style="font-size: 13px; color: #808080; font-weight: 700;">Email:</label>
                        <input type="email" id="add_email" name="email" placeholder="Ex. alex.ligalig@company.com" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_contact_number" style="font-size: 13px; color: #808080; font-weight: 700;">Contact Number:</label>
                        <input type="text" id="add_contact_number" name="contact_number" placeholder="Ex. 09123456789" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                </div>

                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="add_first_name" style="font-size: 13px; color: #808080; font-weight: 700;">First Name:</label>
                        <input type="text" id="add_first_name" name="first_name" placeholder="Ex. Alex" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_middle_name" style="display: flex; align-items: center; gap: 5px; font-size: 13px; color: #808080; font-weight: 700;">
                            Middle Name <span style="color: rgb(214, 51, 51); font-size: 12px;">(Optional)</span>:
                        </label>
                        <input type="text" id="add_middle_name" name="middle_name" placeholder="Ex. Dela Cruz" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_last_name" style="font-size: 13px; color: #808080; font-weight: 700;">Last Name:</label>
                        <input type="text" id="add_last_name" name="last_name" placeholder="Ex. Ligalig" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_extension_name" style="display: flex; align-items: center; gap: 5px; font-size: 13px; color: #808080; font-weight: 700;">
                            Extension Name <span style="color: rgb(214, 51, 51); font-size: 12px;">(Optional)</span>:
                        </label>
                        <input type="text" id="add_extension_name" name="extension_name" placeholder="Ex. Jr., Sr." style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                </div>

                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="add_birthday" style="font-size: 13px; color: #808080; font-weight: 700;">Birthday:</label>
                        <input type="date" id="add_birthday" name="birthday" onchange="calculateAddAge()" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_age" style="font-size: 13px; color: #808080; font-weight: 700;">Age:</label>
                        <input type="text" id="add_age" name="age" readonly style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%; background-color: #f0f0f0;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_sex" style="font-size: 13px; color: #808080; font-weight: 700;">Sex:</label>
                        <select id="add_sex" name="sex" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                            <option value="" hidden>Select Your Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_role_id" style="font-size: 13px; color: #808080; font-weight: 700;">Role:</label>
                        <select id="add_role_id" name="role_id" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>" <?php echo ($role['role_id'] == 3) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error"></div>
                    </div>
                </div>

                <!-- Password Section -->
                <header class="form-header" style="font-size: 22px; font-weight: bold; color: #333; text-align: center; border-bottom: 2px solid #3b8739; padding-bottom: 5px; letter-spacing: 2px; margin: 20px 0 15px;">Password</header>
                
                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px; position: relative;">
                    <div class="input-box" style="flex: 1;">
                        <label for="add_password" style="font-size: 13px; color: #808080; font-weight: 700;">Password:</label>
                        <input type="password" id="add_password" name="password" autocomplete="new-password" placeholder="Enter your password" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 35px 0 10px; width: 100%;" required>
                        <div class="strength-meter" id="add_strength_meter" style="height: 8px; width: 100%; background-color: #ddd; border-radius: 5px; margin-top: 10px;"></div>
                        <span class="strength-text" id="add_strength_text" style="margin-top: 5px; font-size: 14px; color: #333;">Password Strength: </span>
                        <div class="error"></div>
                    </div>
                    
                    <div style="position: relative; flex: 1;">
                        <img id="add_toggle_password" src="../images/eye-icon.png" style="position: absolute; cursor: pointer; width: 20px; right: 15px; top: 35px; z-index: 2;" onclick="togglePasswordVisibility('add_password', 'add_toggle_password')">
                        
                        <div class="input-box">
                            <label for="add_repassword" style="font-size: 13px; color: #808080; font-weight: 700;">Re-enter Password:</label>
                            <input type="password" id="add_repassword" name="repassword" autocomplete="new-password" placeholder="Enter your password again" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 35px 0 10px; width: 100%;" required>
                            <div class="error"></div>
                        </div>
                    </div>
                </div>

                <!-- Address Section -->
                <header class="form-header address-header" style="font-size: 22px; font-weight: bold; color: #333; text-align: center; border-bottom: 2px solid #3b8739; padding-bottom: 5px; letter-spacing: 2px; margin: 20px 0 15px;">Personal Address</header>
                
                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="add_street_purok" style="font-size: 13px; color: #808080; font-weight: 700;">Purok/Street:</label>
                        <input type="text" id="add_street_purok" name="street_purok" placeholder="Ex. Purok 3" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_barangay" style="font-size: 13px; color: #808080; font-weight: 700;">Barangay:</label>
                        <input type="text" id="add_barangay" name="barangay" placeholder="Ex. San Jose" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_city_municipal" style="font-size: 13px; color: #808080; font-weight: 700;">City/Municipal:</label>
                        <input type="text" id="add_city_municipal" name="city_municipal" placeholder="Ex. Davao City" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                </div>

                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="add_province" style="font-size: 13px; color: #808080; font-weight: 700;">Province:</label>
                        <input type="text" id="add_province" name="province" placeholder="Ex. Davao del Sur" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_country" style="font-size: 13px; color: #808080; font-weight: 700;">Country:</label>
                        <input type="text" id="add_country" name="country" value="Philippines" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="add_zipcode" style="font-size: 13px; color: #808080; font-weight: 700;">Zip Code:</label>
                        <input type="text" id="add_zipcode" name="zipcode" placeholder="Ex. 8000" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                </div>
                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="is_active" id="add_is_active" checked style="width: 20px; height: 20px;">
                        <label for="add_is_active" style="font-size: 13px; color: #808080; font-weight: 700;">Active</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #cbe6bf;">
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()" style="background-color: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-success" style="background-color: #205d1c; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width: 900px; width: 95%; padding: 20px;">
        <div class="modal-header" style="border-bottom: 2px solid #3b8739; padding-bottom: 10px; margin-bottom: 20px;">
            <h3 style="color: #3b8739; font-size: 24px; font-weight: bold; letter-spacing: 2px;">Edit User Information</h3>
            <span class="close" onclick="closeEditModal()" style="font-size: 28px; color: #808080; cursor: pointer;">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding-right: 10px;">
                <!-- Personal Information Section -->
                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_id_number" style="font-size: 13px; color: #808080; font-weight: 700;">ID number:</label>
                        <input type="text" id="edit_id_number" name="id_number" placeholder="Ex. 0000-0000" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_username" style="font-size: 13px; color: #808080; font-weight: 700;">Username:</label>
                        <input type="text" id="edit_username" name="username" placeholder="Ex. alex24" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_email" style="font-size: 13px; color: #808080; font-weight: 700;">Email:</label>
                        <input type="email" id="edit_email" name="email" placeholder="Ex. alex.ligalig@company.com" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_contact_number" style="font-size: 13px; color: #808080; font-weight: 700;">Contact Number:</label>
                        <input type="text" id="edit_contact_number" name="contact_number" placeholder="Ex. 09123456789" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                </div>

                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_first_name" style="font-size: 13px; color: #808080; font-weight: 700;">First Name:</label>
                        <input type="text" id="edit_first_name" name="first_name" placeholder="Ex. Alex" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_middle_name" style="display: flex; align-items: center; gap: 5px; font-size: 13px; color: #808080; font-weight: 700;">
                            Middle Name <span style="color: rgb(214, 51, 51); font-size: 12px;">(Optional)</span>:
                        </label>
                        <input type="text" id="edit_middle_name" name="middle_name" placeholder="Ex. Dela Cruz" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_last_name" style="font-size: 13px; color: #808080; font-weight: 700;">Last Name:</label>
                        <input type="text" id="edit_last_name" name="last_name" placeholder="Ex. Ligalig" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;" required>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_extension_name" style="display: flex; align-items: center; gap: 5px; font-size: 13px; color: #808080; font-weight: 700;">
                            Extension Name <span style="color: rgb(214, 51, 51); font-size: 12px;">(Optional)</span>:
                        </label>
                        <input type="text" id="edit_extension_name" name="extension_name" placeholder="Ex. Jr., Sr." style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                </div>

                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_birthday" style="font-size: 13px; color: #808080; font-weight: 700;">Birthday:</label>
                        <input type="date" id="edit_birthday" name="birthday" onchange="calculateEditAge()" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_age" style="font-size: 13px; color: #808080; font-weight: 700;">Age:</label>
                        <input type="text" id="edit_age" name="age" readonly style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%; background-color: #f0f0f0;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_sex" style="font-size: 13px; color: #808080; font-weight: 700;">Sex:</label>
                        <select id="edit_sex" name="sex" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                            <option value="" hidden>Select Your Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_role_id" style="font-size: 13px; color: #808080; font-weight: 700;">Role:</label>
                        <select id="edit_role_id" name="role_id" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>">
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error"></div>
                    </div>
                </div>

                <!-- Password Section -->
                <header class="form-header" style="font-size: 22px; font-weight: bold; color: #333; text-align: center; border-bottom: 2px solid #3b8739; padding-bottom: 5px; letter-spacing: 2px; margin: 20px 0 15px;">Change Password</header>
                
                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1; position: relative;">
                        <label for="edit_password" style="font-size: 13px; color: #808080; font-weight: 700;">New Password (leave blank to keep current):</label>
                        <input type="password" id="edit_password" name="new_password" autocomplete="new-password" placeholder="Enter new password" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 35px 0 10px; width: 100%;">
                        <img id="edit_toggle_password" src="../images/eye-icon.png" style="position: absolute; cursor: pointer; width: 20px; right: 15px; top: 35px; z-index: 2;" onclick="togglePasswordVisibility('edit_password', 'edit_toggle_password')">
                        <div class="error"></div>
                    </div>
                </div>

                <!-- Address Section -->
                <header class="form-header address-header" style="font-size: 22px; font-weight: bold; color: #333; text-align: center; border-bottom: 2px solid #3b8739; padding-bottom: 5px; letter-spacing: 2px; margin: 20px 0 15px;">Personal Address</header>
                
                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_street_purok" style="font-size: 13px; color: #808080; font-weight: 700;">Purok/Street:</label>
                        <input type="text" id="edit_street_purok" name="street_purok" placeholder="Ex. Purok 3" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_barangay" style="font-size: 13px; color: #808080; font-weight: 700;">Barangay:</label>
                        <input type="text" id="edit_barangay" name="barangay" placeholder="Ex. San Jose" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_city" style="font-size: 13px; color: #808080; font-weight: 700;">City/Municipal:</label>
                        <input type="text" id="edit_city" name="city_municipal" placeholder="Ex. Davao City" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                </div>

                <div class="column" style="display: flex; flex-direction: row; gap: 15px; margin-bottom: 15px;">
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_province" style="font-size: 13px; color: #808080; font-weight: 700;">Province:</label>
                        <input type="text" id="edit_province" name="province" placeholder="Ex. Davao del Sur" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_country" style="font-size: 13px; color: #808080; font-weight: 700;">Country:</label>
                        <input type="text" id="edit_country" name="country" value="Philippines" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1;">
                        <label for="edit_zipcode" style="font-size: 13px; color: #808080; font-weight: 700;">Zip Code:</label>
                        <input type="text" id="edit_zipcode" name="zipcode" placeholder="Ex. 8000" style="height: 40px; border: 1px solid rgb(92, 92, 92); border-radius: 2px; padding: 0 10px; width: 100%;">
                        <div class="error"></div>
                    </div>
                    
                    <div class="input-box" style="flex: 1; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="is_active" id="edit_is_active" style="width: 20px; height: 20px;">
                        <label for="edit_is_active" style="font-size: 13px; color: #808080; font-weight: 700;">Active</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #cbe6bf;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="background-color: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
                <button type="submit" name="edit_user" class="btn btn-success" style="background-color: #205d1c; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- View User Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 700px; width: 90%; padding: 20px;">
        <div class="modal-header" style="border-bottom: 2px solid #3b8739; padding-bottom: 10px; margin-bottom: 20px;">
            <h3 style="color: #3b8739; font-size: 24px; font-weight: bold; letter-spacing: 2px;">User Details</h3>
            <span class="close" onclick="closeViewModal()" style="font-size: 28px; color: #808080; cursor: pointer;">&times;</span>
        </div>
        <div class="modal-body" id="viewModalBody" style="max-height: 70vh; overflow-y: auto; padding-right: 10px;">
            <!-- Content will be loaded via AJAX -->
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #2a6e3b;"></i>
                <p style="margin-top: 1rem; color: #808080;">Loading user details...</p>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #cbe6bf;">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()" style="background-color: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer;">Close</button>
            <button type="button" class="btn btn-primary" onclick="editFromView()" id="editFromViewBtn" style="background-color: #205d1c; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Edit User</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 450px; width: 90%; padding: 20px;">
        <div class="modal-header" style="border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 20px;">
            <h3 style="color: #dc3545; font-size: 24px; font-weight: bold; letter-spacing: 2px;">Confirm Delete</h3>
            <span class="close" onclick="hideDeleteModal()" style="font-size: 28px; color: #808080; cursor: pointer;">&times;</span>
        </div>
        <div class="modal-body">
            <p style="font-size: 16px; color: #333; margin-bottom: 15px;">Are you sure you want to delete user: <strong id="deleteUserName" style="color: #205d1c;"></strong>?</p>
            <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; font-size: 14px;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                This action cannot be undone. All user data including activity logs, login attempts, and security answers will be permanently deleted.
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid #cbe6bf;">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="button" onclick="hideDeleteModal()" class="btn btn-secondary" style="background-color: #6c757d; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
                <button type="submit" name="delete_user" class="btn btn-danger" style="background-color: #dc3545; color: white; padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-left: 10px;">Delete User</button>
            </form>
        </div>
    </div>
</div>
    
    <script>
    // Toggle sidebar function (already in your code)
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
    
    // Toggle mobile menu (already in your code)
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
    }
    
    // Close mobile menu when clicking outside (already in your code)
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const mobileBtn = document.getElementById('mobileMenuBtn');
        
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !mobileBtn.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });
    
    // Handle window resize (already in your code)
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
        }
    });

    // ========== MODAL FUNCTIONS ==========
    
    // Add Modal functions
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
        document.getElementById('addModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Edit Modal functions
    function openEditModal(userId) {
        fetch(`get_user.php?id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                document.getElementById('edit_user_id').value = data.user_id;
                document.getElementById('edit_username').value = data.username || '';
                document.getElementById('edit_email').value = data.email || '';
                document.getElementById('edit_id_number').value = data.id_number || '';
                document.getElementById('edit_first_name').value = data.first_name || '';
                document.getElementById('edit_middle_name').value = data.middle_name || '';
                document.getElementById('edit_last_name').value = data.last_name || '';
                document.getElementById('edit_extension_name').value = data.extension_name || '';
                document.getElementById('edit_birthday').value = data.birthday || '';
                document.getElementById('edit_age').value = data.age || '';
                document.getElementById('edit_sex').value = data.sex || '';
                document.getElementById('edit_contact_number').value = data.contact_number || '';
                document.getElementById('edit_street_purok').value = data.street_purok || '';
                document.getElementById('edit_barangay').value = data.barangay || '';
                document.getElementById('edit_city').value = data.city_municipal || '';
                document.getElementById('edit_province').value = data.province || '';
                document.getElementById('edit_country').value = data.country || 'Philippines';
                document.getElementById('edit_zipcode').value = data.zipcode || '';
                document.getElementById('edit_role_id').value = data.role_id || '3';
                document.getElementById('edit_is_active').checked = data.is_active == 1;
                
                document.getElementById('editModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load user data. Please try again.');
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // View Modal functions
    function viewUser(userId) {
        const viewModal = document.getElementById('viewModal');
        const viewBody = document.getElementById('viewModalBody');
        
        viewModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Store current user ID for edit button
        viewModal.dataset.currentUserId = userId;
        
        // Load user details
        fetch(`view_user.php?id=${userId}`)
            .then(response => response.text())
            .then(html => {
                viewBody.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                viewBody.innerHTML = '<div style="text-align: center; color: #721c24; padding: 2rem;">Failed to load user details. Please try again.</div>';
            });
    }

    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function editFromView() {
        const viewModal = document.getElementById('viewModal');
        const userId = viewModal.dataset.currentUserId;
        
        if (userId) {
            closeViewModal();
            openEditModal(userId);
        }
    }

    // Delete Modal functions
    function showDeleteModal(userId, userName) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserName').textContent = userName;
        document.getElementById('deleteModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function hideDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Calculate age for add form
    function calculateAddAge() {
        const birthday = document.getElementById('add_birthday').value;
        if (birthday) {
            const birthDate = new Date(birthday);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            document.getElementById('add_age').value = age;
        }
    }

    // Calculate age for edit form
    function calculateEditAge() {
        const birthday = document.getElementById('edit_birthday').value;
        if (birthday) {
            const birthDate = new Date(birthday);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            document.getElementById('edit_age').value = age;
        }
    }

    // Password toggle function
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (input.type === "password") {
            input.type = "text";
            icon.src = "../images/close-eye.png";
        } else {
            input.type = "password";
            icon.src = "../images/eye-icon.png";
        }
    }

    // Password strength meter for add form
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('add_password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const meter = document.getElementById('add_strength_meter');
                const text = document.getElementById('add_strength_text');
                
                let strength = 0;
                if (password.length >= 8) strength++;
                if (password.match(/[a-z]+/)) strength++;
                if (password.match(/[A-Z]+/)) strength++;
                if (password.match(/[0-9]+/)) strength++;
                if (password.match(/[$@#&!]+/)) strength++;
                
                switch(strength) {
                    case 0:
                    case 1:
                        meter.style.backgroundColor = '#ff4444';
                        text.innerHTML = 'Password Strength: Weak';
                        break;
                    case 2:
                    case 3:
                        meter.style.backgroundColor = '#ffbb33';
                        text.innerHTML = 'Password Strength: Medium';
                        break;
                    case 4:
                        meter.style.backgroundColor = '#00C851';
                        text.innerHTML = 'Password Strength: Strong';
                        break;
                    case 5:
                        meter.style.backgroundColor = '#007E33';
                        text.innerHTML = 'Password Strength: Very Strong';
                        break;
                }
            });
        }

        // Validate password match for add form
        const addForm = document.getElementById('addForm');
        if (addForm) {
            addForm.addEventListener('submit', function(e) {
                const password = document.getElementById('add_password').value;
                const repassword = document.getElementById('add_repassword').value;
                
                if (password !== repassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                }
            });
        }
    });

    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        const viewModal = document.getElementById('viewModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target == addModal) {
            closeAddModal();
        }
        if (event.target == editModal) {
            closeEditModal();
        }
        if (event.target == viewModal) {
            closeViewModal();
        }
        if (event.target == deleteModal) {
            hideDeleteModal();
        }
    }

    // Handle ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAddModal();
            closeEditModal();
            closeViewModal();
            hideDeleteModal();
            
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
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