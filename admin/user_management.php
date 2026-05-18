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
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';

// Handle form submissions
$message = '';
$error = '';

// Handle Add Admin
if (isset($_POST['add_admin'])) {
    $username = $_POST['username'] ?? '';
    $password = !empty($_POST['password']) ? $_POST['password'] : 'FFll24()';
    $email = $_POST['email'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $suffix = $_POST['suffix'] ?? '';
    $id_number = $_POST['id_number'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $street_purok = $_POST['street_purok'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $province = $_POST['province'] ?? '';
    $city_municipal = $_POST['city_municipal'] ?? '';
    $country = $_POST['country'] ?? '';
    $zipcode = $_POST['zipcode'] ?? '';
    $admin_role = $_POST['admin_role'] ?? 'admin'; // super_admin or admin
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $permissions_array = $_POST['permissions'] ?? [];
    $permissions_json = json_encode($permissions_array);

    // Password validation logic
    $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]).{8,}$/';
    
    if (empty($username) || empty($password) || empty($email) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match($password_pattern, $password)) {
        $error = "Password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.";
    } else {
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
                    middle_name, extension_name, id_number, birthday,
                    street_purok, barangay, province, city_municipal, country, zipcode,
                    role_id, permissions, is_active, created_at, created_by
                ) VALUES (
                    :username, :email, :password, :first_name, :last_name,
                    :middle_name, :extension_name, :id_number, :birthday,
                    :street_purok, :barangay, :province, :city_municipal, :country, :zipcode,
                    :role_id, :permissions, :is_active, NOW(), :created_by
                )";
                
                $new_role_id = ($admin_role === 'super_admin') ? 1 : 2;
                
                // Set default permissions for admin or super admin
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
                    ':middle_name' => $middle_name,
                    ':extension_name' => $suffix,
                    ':id_number' => $id_number,
                    ':birthday' => !empty($birthday) ? $birthday : null,
                    ':street_purok' => $street_purok,
                    ':barangay' => $barangay,
                    ':province' => $province,
                    ':city_municipal' => $city_municipal,
                    ':country' => $country,
                    ':zipcode' => $zipcode,
                    ':role_id' => $new_role_id,
                    ':permissions' => $permissions_json,
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
                    ':new_data' => json_encode([
                        'username' => $username, 
                        'email' => $email, 
                        'role_id' => $new_role_id,
                        'id_number' => $id_number,
                        'first_name' => $first_name,
                        'middle_name' => $middle_name,
                        'last_name' => $last_name,
                        'extension_name' => $suffix,
                        'birthday' => $birthday,
                        'sex' => $_POST['sex'] ?? '',
                        'street_purok' => $street_purok,
                        'barangay' => $barangay,
                        'province' => $province,
                        'city_municipal' => $city_municipal,
                        'country' => $country,
                        'zipcode' => $zipcode
                    ]),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]);

                $message = "Admin added successfully";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Edit Admin
if (isset($_POST['edit_admin'])) {
    $edit_user_id = (int)$_POST['admin_id']; // Using 'admin_id' from form but treating as user_id
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $suffix = $_POST['suffix'] ?? '';
    $id_number = $_POST['id_number'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $street_purok = $_POST['street_purok'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $province = $_POST['province'] ?? '';
    $city_municipal = $_POST['city_municipal'] ?? '';
    $country = $_POST['country'] ?? '';
    $zipcode = $_POST['zipcode'] ?? '';
    $admin_role = $_POST['admin_role'] ?? 'admin';
    $is_active = isset($_POST['is_active']) ? true : false;
    $new_password = $_POST['new_password'] ?? '';
    $permissions_array = $_POST['permissions'] ?? [];
    $permissions_json = json_encode($permissions_array);
    // Password validation for edit if new password is provided
    $password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]).{8,}$/';

    if (empty($username) || empty($email) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!empty($new_password) && !preg_match($password_pattern, $new_password)) {
        $error = "New password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.";
    } else {
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
                $error = "Username or email already exists for another user.";
            } else {
                    // Get old data for logging
                    $old_data_query = "SELECT * FROM users WHERE user_id = :user_id";
                    $old_data_stmt = $conn->prepare($old_data_query);
                    $old_data_stmt->execute([':user_id' => $edit_user_id]);
                    $old_data = $old_data_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$old_data) {
                        $error = "User not found";
                    } elseif ($old_data['role_id'] == 1 && $admin_role === 'admin' && $edit_user_id != $admin_id) {
                        $error = "You cannot demote another Super Admin. You can only demote yourself.";
                    } else {
                        // Build update query
                        $update_fields = [
                            'username = :username',
                            'email = :email',
                            'first_name = :first_name',
                            'last_name = :last_name',
                            'middle_name = :middle_name',
                            'extension_name = :extension_name',
                            'id_number = :id_number',
                            'birthday = :birthday',
                            'street_purok = :street_purok',
                            'barangay = :barangay',
                            'province = :province',
                            'city_municipal = :city_municipal',
                            'country = :country',
                            'zipcode = :zipcode',
                            'role_id = :role_id',
                            'is_active = :is_active',
                            'updated_at = NOW()',
                            'updated_by = :updated_by'
                        ];

                        $new_role_id = ($admin_role === 'super_admin') ? 1 : 2;

                        $params = [
                            ':username' => $username,
                            ':email' => $email,
                            ':first_name' => $first_name,
                            ':last_name' => $last_name,
                            ':middle_name' => $middle_name,
                            ':extension_name' => $suffix,
                            ':id_number' => $id_number,
                            ':birthday' => !empty($birthday) ? $birthday : null,
                            ':street_purok' => $street_purok,
                            ':barangay' => $barangay,
                            ':province' => $province,
                            ':city_municipal' => $city_municipal,
                            ':country' => $country,
                            ':zipcode' => $zipcode,
                            ':role_id' => $new_role_id,
                            ':is_active' => $is_active ? 'true' : 'false',
                            ':updated_by' => $admin_id,
                            ':user_id' => $edit_user_id
                        ];

                        // Update permissions based on role
                        $update_fields[] = 'permissions = :permissions';
                        $params[':permissions'] = $permissions_json;

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

                        // If the current admin deactivated themselves, logout
                        if ($edit_user_id == $admin_id && !$is_active) {
                            session_destroy();
                            header("Location: ../admin/login.php?msg=account_deactivated");
                            exit();
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Toggle Status
if (isset($_POST['toggle_status'])) {
    $target_user_id = (int)$_POST['admin_id'];
    $new_status = $_POST['new_status'] == '1' ? 'true' : 'false';
    
    // Allow self-toggle as requested
    if (true) {
        try {
            // Get old data for logging
            $old_data_query = "SELECT is_active, role_id FROM users WHERE user_id = :user_id";
            $old_data_stmt = $conn->prepare($old_data_query);
            $old_data_stmt->execute([':user_id' => $target_user_id]);
            $row = $old_data_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                if ($row['role_id'] == 1 && $target_user_id != $admin_id) {
                    $error = "You cannot deactivate another Super Admin.";
                } else {
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

                    // If the current admin deactivated themselves, logout
                    if ($target_user_id == $admin_id && $new_status === 'false') {
                        session_destroy();
                        header("Location: ../admin/login.php?msg=account_deactivated");
                        exit();
                    }
                }
            } else {
                $error = "User not found";
            }
        } catch (PDOException $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
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
                if ($user_data['role_id'] == 1) {
                    // Check if there are other Super Admins
                    $count_stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role_id = 1");
                    $super_admin_count = $count_stmt->fetchColumn();
                    
                    if ($super_admin_count <= 1) {
                        $error = "You cannot delete the only Super Admin in the system.";
                    } else {
                        // Proceed with deletion
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
                        $message = "Super Admin deleted successfully";
                    }
                } else {
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
                }
            } else {
                $error = "User not found";
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Failed to delete: " . $e->getMessage();
        }
    }
}

// Handle Approve Activation Request
if (isset($_POST['approve_activation']) && $is_super_admin) {
    $req_id = (int)$_POST['request_id'];
    $target_uid = (int)$_POST['target_user_id'];
    try {
        $conn->beginTransaction();
        // Activate the user
        $conn->prepare("UPDATE users SET is_active = true, updated_at = NOW(), updated_by = :by WHERE user_id = :uid")
             ->execute([':by' => $admin_id, ':uid' => $target_uid]);
        // Mark request as approved
        $conn->prepare("UPDATE activation_requests SET status = 'approved', reviewed_by = :by, updated_at = NOW() WHERE request_id = :rid")
             ->execute([':by' => $admin_id, ':rid' => $req_id]);
        $conn->commit();
        $message = "Activation request approved. Account has been activated.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Failed to approve: " . $e->getMessage();
    }
}

// Handle Deny Activation Request
if (isset($_POST['deny_activation']) && $is_super_admin) {
    $req_id = (int)$_POST['request_id'];
    try {
        $conn->prepare("UPDATE activation_requests SET status = 'denied', reviewed_by = :by, updated_at = NOW() WHERE request_id = :rid")
             ->execute([':by' => $admin_id, ':rid' => $req_id]);
        $message = "Activation request denied.";
    } catch (PDOException $e) {
        $error = "Failed to deny: " . $e->getMessage();
    }
}

// Fetch pending activation requests
$pending_activation_requests = [];
try {
    $act_req_stmt = $conn->prepare("
        SELECT ar.request_id, ar.user_id as target_user_id, ar.created_at,
               u.username, u.first_name, u.last_name, u.email, r.role_name
        FROM activation_requests ar
        JOIN users u ON ar.user_id = u.user_id
        JOIN roles r ON u.role_id = r.role_id
        WHERE ar.status = 'pending'
        ORDER BY ar.created_at ASC
    ");
    $act_req_stmt->execute();
    $pending_activation_requests = $act_req_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet — silently ignore
}

// Handle Export Admins
if (isset($_GET['export'])) {
    // Get all admins for export
    $export_query = "
        SELECT 
            a.username, a.id_number, a.email, a.first_name, a.last_name, a.middle_name, a.extension_name as suffix,
            a.birthday, a.street_purok, a.barangay, a.province, a.city_municipal as municipal_city, a.country, a.zipcode as postal_code,
            r.role_name as role, a.permissions,
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
    fputcsv($output, ['Username', 'ID Number', 'Email', 'First Name', 'Last Name', 'Middle Name', 'Suffix', 'Birthday', 'Purok/Street', 'Barangay', 'Province', 'Municipal/City', 'Country', 'Postal Code', 'Role', 'Permissions', 'Status', 'Created', 'Last Login', 'Created By', 'Updated By']);
    
    // Add data rows
    foreach ($export_admins as $admin) {
        fputcsv($output, [
            $admin['username'] ?? '',
            $admin['id_number'] ?? '',
            $admin['email'] ?? '',
            $admin['first_name'] ?? '',
            $admin['last_name'] ?? '',
            $admin['middle_name'] ?? '',
            $admin['suffix'] ?? '',
            $admin['birthday'] ?? '',
            $admin['street_purok'] ?? '',
            $admin['barangay'] ?? '',
            $admin['province'] ?? '',
            $admin['municipal_city'] ?? '',
            $admin['country'] ?? '',
            $admin['postal_code'] ?? '',
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

// Build query conditions for admin list
$conditions = ["a.role_id IN (1, 2)"]; // Manage admins and super admins
$params = [];

if (!empty($search)) {
    $conditions[] = "(a.first_name ILIKE :search OR a.last_name ILIKE :search OR a.username ILIKE :search OR a.email ILIKE :search)";
    $params[':search'] = "%$search%";
}

if ($role_filter !== 'all') {
    if ($role_filter === 'super_admin') {
        $conditions[] = "a.role_id = 1";
    } elseif ($role_filter === 'admin') {
        $conditions[] = "a.role_id = 2";
    }
}

if ($status_filter !== 'all') {
    $is_active_val = ($status_filter === 'active') ? 'true' : 'false';
    $conditions[] = "a.is_active = $is_active_val";
}

if (!empty($date_from)) {
    $conditions[] = "DATE(a.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(a.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
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
        a.id_number,
        a.role_id,
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
    <title>Admin Management - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        /* Admin Management specific styles - same as before */
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
            background: rgba(0, 0, 0, 0.8);
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
            transition: max-width 0.3s ease;
        }

        .modal-content.wide {
            max-width: 1000px;
        }

        .modal-content.wide .form-grid {
            grid-template-columns: repeat(4, 1fr);
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
            grid-column: 1 / -1;
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

        .form-group.has-error input,
        .form-group.has-error select {
            border-color: #dc3545;
        }

        .field-error {
            color: #dc3545;
            font-size: 10px;
            margin-top: 0.2rem;
            min-height: 12px;
            font-weight: 500;
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

        .password-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-input-group input {
            padding-right: 2.5rem;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #6b7c6b;
            cursor: pointer;
            padding: 5px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: #1c4c29;
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
                <?php if ($is_super_admin || in_array('manage_questions', $_SESSION['permissions'] ?? []) || in_array('all', $_SESSION['permissions'] ?? [])): ?>
                <li class="nav-item">
                    <a href="security_questions.php">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security Questions</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="logs.php">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                <li class="nav-header">Administration</li>

                <li class="nav-item active">
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
                <h1>Admin Management</h1>
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
            // Get proper statistics from database for all admins
            $stats_query = "
                SELECT 
                    SUM(CASE WHEN role_id = 1 THEN 1 ELSE 0 END) as total_superadmin,
                    SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as total_admin,
                    SUM(CASE WHEN role_id = 1 AND is_active = false THEN 1 ELSE 0 END) as inactive_superadmin,
                    SUM(CASE WHEN role_id = 2 AND is_active = false THEN 1 ELSE 0 END) as inactive_admin
                FROM users 
                WHERE role_id IN (1, 2)
            ";
            $stats_stmt = $conn->query($stats_query);
            $admin_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_superadmin = $admin_stats['total_superadmin'] ?? 0;
            $total_admin = $admin_stats['total_admin'] ?? 0;
            $inactive_superadmin = $admin_stats['inactive_superadmin'] ?? 0;
            $inactive_admin = $admin_stats['inactive_admin'] ?? 0;
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Super Admin</h3>
                    <div class="stat-value"><?php echo $total_superadmin; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Admin</h3>
                    <div class="stat-value"><?php echo $total_admin; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Inactive Super Admin</h3>
                    <div class="stat-value"><?php echo $inactive_superadmin; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Inactive Admin</h3>
                    <div class="stat-value"><?php echo $inactive_admin; ?></div>
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
            <!-- Activation Requests Panel -->
            <?php if (!empty($pending_activation_requests)): ?>
            <div style="margin-bottom: 1.5rem; border: 1px solid #f5c518; border-radius: 6px; overflow: hidden; background: #fffdf0;">
                <div style="background: #f5c518; padding: 0.6rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-bell" style="color: #856404;"></i>
                    <strong style="color: #856404; font-size: 13px;">
                        Pending Activation Requests (<?php echo count($pending_activation_requests); ?>)
                    </strong>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: #fef9db; border-bottom: 1px solid #f5c518;">
                            <th style="padding: 0.5rem 1rem; text-align: left; color: #6c5a00;">Name</th>
                            <th style="padding: 0.5rem 1rem; text-align: left; color: #6c5a00;">Username</th>
                            <th style="padding: 0.5rem 1rem; text-align: left; color: #6c5a00;">Email</th>
                            <th style="padding: 0.5rem 1rem; text-align: left; color: #6c5a00;">Role</th>
                            <th style="padding: 0.5rem 1rem; text-align: left; color: #6c5a00;">Requested At</th>
                            <th style="padding: 0.5rem 1rem; text-align: center; color: #6c5a00;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_activation_requests as $req): ?>
                        <tr style="border-bottom: 1px solid #fce96c;">
                            <td style="padding: 0.5rem 1rem;"><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></td>
                            <td style="padding: 0.5rem 1rem;">@<?php echo htmlspecialchars($req['username']); ?></td>
                            <td style="padding: 0.5rem 1rem;"><?php echo htmlspecialchars($req['email']); ?></td>
                            <td style="padding: 0.5rem 1rem;"><?php echo htmlspecialchars($req['role_name']); ?></td>
                            <td style="padding: 0.5rem 1rem;"><?php echo date('M d, Y H:i', strtotime($req['created_at'])); ?></td>
                            <td style="padding: 0.5rem 1rem; text-align: center; display: flex; gap: 0.4rem; justify-content: center;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                    <input type="hidden" name="target_user_id" value="<?php echo $req['target_user_id']; ?>">
                                    <button type="submit" name="approve_activation" 
                                            style="background: #1b5e20; color: white; border: none; border-radius: 4px; padding: 0.3rem 0.8rem; font-size: 11px; cursor: pointer;">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                    <button type="submit" name="deny_activation"
                                            style="background: #b71c1c; color: white; border: none; border-radius: 4px; padding: 0.3rem 0.8rem; font-size: 11px; cursor: pointer;">
                                        <i class="fas fa-times"></i> Deny
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                        <label>Role</label>
                        <select name="role">
                            <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="super_admin" <?php echo $role_filter == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Created From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Created To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
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
                            <th>ID Number</th>
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
                                    <code style="font-size: 11px; background: #e8f5e9; padding: 2px 4px; border-radius: 3px; color: #2e7d32;">
                                        <?php echo htmlspecialchars($admin['id_number'] ?? 'N/A'); ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo $admin['role'] ?? 'admin'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $admin['role'] ?? 'Admin')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $perms = !empty($admin['permissions']) ? json_decode($admin['permissions'], true) : [];
                                    if (is_array($perms) && !empty($perms)) {
                                        $perm_labels = [];
                                        
                                        // If 'all' is present, we show all standard labels
                                        if (in_array('all', $perms)) {
                                            $perms = ['manage_users', 'view_logs', 'manage_admins', 'manage_questions'];
                                        }

                                        foreach ($perms as $perm) {
                                            switch ($perm) {
                                                case 'manage_users':
                                                    $perm_labels[] = '<span class="badge" style="background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; margin: 2px; padding: 2px 6px; display: inline-block; font-size: 10px;">👥 User Management</span>';
                                                    break;
                                                case 'view_logs':
                                                    $perm_labels[] = '<span class="badge" style="background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; margin: 2px; padding: 2px 6px; display: inline-block; font-size: 10px;">📋 Audit Logs</span>';
                                                    break;
                                                case 'manage_admins':
                                                    $perm_labels[] = '<span class="badge" style="background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; margin: 2px; padding: 2px 6px; display: inline-block; font-size: 10px;">👑 Admin Control</span>';
                                                    break;
                                                case 'manage_questions':
                                                    $perm_labels[] = '<span class="badge" style="background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7; margin: 2px; padding: 2px 6px; display: inline-block; font-size: 10px;">🛡️ Security Questions</span>';
                                                    break;
                                                case 'all':
                                                    // Already handled
                                                    break;
                                                default:
                                                    $perm_labels[] = '<span class="badge" style="background: #f5f5f5; color: #424242; border: 1px solid #e0e0e0; margin: 2px; padding: 2px 6px; display: inline-block; font-size: 10px;">' . htmlspecialchars($perm) . '</span>';
                                            }
                                        }
                                        echo implode(' ', $perm_labels);
                                    } else {
                                        echo '<span class="badge badge-secondary" style="opacity: 0.6;">No permissions assigned</span>';
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
                                        <?php if ((int)$admin['role_id'] !== 1): ?>
                                            <!-- Admin actions: Edit, Toggle, Delete -->
                                            <button type="button" class="action-btn action-btn-edit" onclick="openEditModal(<?php echo $admin['admin_id']; ?>)">
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
                                        <?php else: ?>
                                            <!-- Super Admin row -->
                                            <?php if ($admin['admin_id'] == $admin_id): ?>
                                                <!-- Allow logged-in Super Admin to edit and toggle themselves -->
                                                <button type="button" class="action-btn action-btn-edit" onclick="openEditModal(<?php echo $admin['admin_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id'] ?? ''; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo !empty($admin['is_active']) ? '0' : '1'; ?>">
                                                    <button type="submit" name="toggle_status" class="action-btn <?php echo !empty($admin['is_active']) ? 'action-btn-toggle' : 'action-btn-toggle active'; ?>" 
                                                            title="<?php echo !empty($admin['is_active']) ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas <?php echo !empty($admin['is_active']) ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                    </button>
                                                </form>
                                                <!-- Actions allowed for other Super Admins -->
                                                <button type="button" class="action-btn action-btn-edit" onclick="openEditModal(<?php echo $admin['admin_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
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
        <div class="modal-content wide">
            <div class="modal-header">
                <h3>Add New Admin</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" id="addAdminForm" onsubmit="return validateAdminForm('add')" novalidate>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" required minlength="3" pattern="^[a-zA-Z0-9_]+$">
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>ID Number *</label>
                            <input type="text" name="id_number" placeholder="Ex. 0000-0000" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name">
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" name="suffix" placeholder="Ex. Jr., Sr.">
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Birthday *</label>
                            <input type="date" name="birthday" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group full-width" style="margin: 1rem 0 0.5rem; border-bottom: 1px solid #e0e0e0; padding-bottom: 0.3rem;">
                            <label style="font-weight: 700; color: #1c4c29; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Address Information</label>
                        </div>
                        <div class="form-group">
                            <label>Purok/Street *</label>
                            <input type="text" name="street_purok" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Barangay *</label>
                            <input type="text" name="barangay" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Province *</label>
                            <input type="text" name="province" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Municipal/City *</label>
                            <input type="text" name="city_municipal" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Country *</label>
                            <input type="text" name="country" value="Philippines" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Postal Code *</label>
                            <input type="text" name="zipcode" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Role *</label>
                            <select name="admin_role" id="add_admin_role" required onchange="updateDefaultPermissions('add')">
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group full-width">
                            <label>Permissions</label>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; background: #f0f7f0; padding: 1rem; border-radius: 4px; border: 1px solid #cbe6bf;">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="all" id="perm_all_add">
                                    <label for="perm_all_add" style="color: #1b5e20; font-weight: bold;">Full Access (All)</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="manage_users" id="perm_users_add">
                                    <label for="perm_users_add">Manage Users</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="view_logs" id="perm_logs_add">
                                    <label for="perm_logs_add">View Activity Logs</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="manage_admins" id="perm_admins_add">
                                    <label for="perm_admins_add">Manage Admins</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="system_settings" id="perm_settings_add">
                                    <label for="perm_settings_add">System Settings</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="manage_questions" id="perm_questions_add">
                                    <label for="perm_questions_add">Security Questions</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group full-width checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active_add" checked>
                            <label for="is_active_add">Active</label>
                        </div>
                    </div>
                    <div id="password-requirements" style="font-size: 10px; color: #666; margin-top: 10px; grid-column: span 4;">
                        Password requirement: 8+ chars, 1 uppercase, 1 lowercase, 1 number, 1 special char.
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
        <div class="modal-content wide">
            <div class="modal-header">
                <h3>Edit Admin</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editForm" onsubmit="return validateAdminForm('edit')" novalidate>
                <input type="hidden" name="admin_id" id="edit_admin_id">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" id="edit_username" required minlength="3" pattern="^[a-zA-Z0-9_]+$">
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>New Password (fill to change)</label>
                            <div class="password-input-group">
                                <input type="password" name="new_password" id="edit_password"
                                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{};':&quot;\\|,.<>\/?]).{0,}|(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{};':&quot;\\|,.<>\/?]).{8,}">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('edit_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="password-input-group">
                                <input type="password" name="confirm_new_password" id="edit_confirm_password">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('edit_confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" id="edit_email" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" id="edit_first_name" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" id="edit_last_name" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>ID Number *</label>
                            <input type="text" name="id_number" id="edit_id_number" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" id="edit_middle_name">
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" name="suffix" id="edit_suffix">
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Birthday *</label>
                            <input type="date" name="birthday" id="edit_birthday" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group full-width" style="margin: 1rem 0 0.5rem; border-bottom: 1px solid #e0e0e0; padding-bottom: 0.3rem;">
                            <label style="font-weight: 700; color: #1c4c29; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Address Information</label>
                        </div>
                        <div class="form-group">
                            <label>Purok/Street *</label>
                            <input type="text" name="street_purok" id="edit_street_purok" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Barangay *</label>
                            <input type="text" name="barangay" id="edit_barangay" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Province *</label>
                            <input type="text" name="province" id="edit_province" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Municipal/City *</label>
                            <input type="text" name="city_municipal" id="edit_city_municipal" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Country *</label>
                            <input type="text" name="country" id="edit_country" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Postal Code *</label>
                            <input type="text" name="zipcode" id="edit_zipcode" required>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group">
                            <label>Role *</label>
                            <select name="admin_role" id="edit_admin_role" required onchange="updateDefaultPermissions('edit')">
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                            <div class="field-error"></div>
                        </div>
                        <div class="form-group full-width">
                            <label>Permissions</label>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; background: #f0f7f0; padding: 1rem; border-radius: 4px; border: 1px solid #cbe6bf;">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="all" id="perm_all_edit">
                                    <label for="perm_all_edit" style="color: #1b5e20; font-weight: bold;">Full Access (All)</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="manage_users" id="perm_users_edit">
                                    <label for="perm_users_edit">Manage Users</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="view_logs" id="perm_logs_edit">
                                    <label for="perm_logs_edit">View Activity Logs</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="manage_admins" id="perm_admins_edit">
                                    <label for="perm_admins_edit">Manage Admins</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="system_settings" id="perm_settings_edit">
                                    <label for="perm_settings_edit">System Settings</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="permissions[]" value="manage_questions" id="perm_questions_edit">
                                    <label for="perm_questions_edit">Security Questions</label>
                                </div>
                            </div>
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
            // Set default permissions for add modal
            updateDefaultPermissions('add');
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
                    document.getElementById('edit_middle_name').value = data.middle_name || '';
                    document.getElementById('edit_suffix').value = data.extension_name || '';
                    document.getElementById('edit_id_number').value = data.id_number || '';
                    document.getElementById('edit_birthday').value = data.birthday || '';
                    document.getElementById('edit_street_purok').value = data.street_purok || '';
                    document.getElementById('edit_barangay').value = data.barangay || '';
                    document.getElementById('edit_province').value = data.province || '';
                    document.getElementById('edit_city_municipal').value = data.city_municipal || '';
                    document.getElementById('edit_country').value = data.country || '';
                    document.getElementById('edit_zipcode').value = data.zipcode || '';
                    // Determine role string from role_id (1 = super_admin, 2 = admin)
                    const role = (data.role_id == 1) ? 'super_admin' : 'admin';
                    document.getElementById('edit_admin_role').value = role;
                    document.getElementById('edit_is_active').checked = data.is_active == 1;
                    
                    // Populate permissions checkboxes
                    const perms = data.permissions ? JSON.parse(data.permissions) : [];
                    document.getElementById('perm_all_edit').checked = perms.includes('all');
                    document.getElementById('perm_users_edit').checked = perms.includes('manage_users');
                    document.getElementById('perm_logs_edit').checked = perms.includes('view_logs');
                    document.getElementById('perm_admins_edit').checked = perms.includes('manage_admins');
                    document.getElementById('perm_settings_edit').checked = perms.includes('system_settings');
                    document.getElementById('perm_questions_edit').checked = perms.includes('manage_questions');
                    
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

        // Form validation
        function validateAdminForm(type) {
            const formId = (type === 'add') ? 'addAdminForm' : 'editForm';
            const form = document.getElementById(formId);
            const passwordName = (type === 'add') ? 'password' : 'new_password';
            const passwordField = form.querySelector(`input[name="${passwordName}"]`);
            const password = passwordField ? passwordField.value : '';
            
            // Clear previous errors
            form.querySelectorAll('.field-error').forEach(div => div.textContent = '');
            form.querySelectorAll('.form-group').forEach(div => div.classList.remove('has-error'));
            
            let isValid = true;
            let firstErrorField = null;

            // Basic required check
            const requiredFields = form.querySelectorAll('[required]');
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    const group = field.closest('.form-group');
                    group.classList.add('has-error');
                    group.querySelector('.field-error').textContent = `${field.previousElementSibling.textContent.replace('*', '').trim()} is required.`;
                    if (!firstErrorField) firstErrorField = field;
                    isValid = false;
                }
            }
            
            // Password strength check (only if not empty for edit)
            if (passwordField && password) {
                const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]).{8,}$/;
                if (!passwordPattern.test(password)) {
                    const group = passwordField.closest('.form-group');
                    group.classList.add('has-error');
                    group.querySelector('.field-error').textContent = "8+ chars, upper, lower, number, special.";
                    if (!firstErrorField) firstErrorField = passwordField;
                    isValid = false;
                }
            }

            // Confirm password check
            const confirmName = (type === 'add') ? 'confirm_password' : 'confirm_new_password';
            const confirmField = form.querySelector(`input[name="${confirmName}"]`);
            if (confirmField && password && confirmField.value !== password) {
                const group = confirmField.closest('.form-group');
                group.classList.add('has-error');
                group.querySelector('.field-error').textContent = "Passwords do not match.";
                if (!firstErrorField) firstErrorField = confirmField;
                isValid = false;
            }

            if (firstErrorField) firstErrorField.focus();
            return isValid;
        }

        function updateDefaultPermissions(type) {
            const role = document.getElementById(type === 'add' ? 'add_admin_role' : 'edit_admin_role').value;
            const suffix = type === 'add' ? '_add' : '_edit';
            
            if (role === 'super_admin') {
                document.getElementById('perm_all' + suffix).checked = true;
                document.getElementById('perm_users' + suffix).checked = true;
                document.getElementById('perm_logs' + suffix).checked = true;
                document.getElementById('perm_admins' + suffix).checked = true;
                document.getElementById('perm_settings' + suffix).checked = true;
                document.getElementById('perm_questions' + suffix).checked = true;
            } else {
                document.getElementById('perm_all' + suffix).checked = false;
                document.getElementById('perm_users' + suffix).checked = true;
                document.getElementById('perm_logs' + suffix).checked = true;
                document.getElementById('perm_admins' + suffix).checked = false;
                document.getElementById('perm_settings' + suffix).checked = false;
                document.getElementById('perm_questions' + suffix).checked = false;
            }
        }

        // Password toggle function
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.message').forEach(function(message) {
                if (message) message.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>