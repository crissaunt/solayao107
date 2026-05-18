<?php
// users.php
session_start();
require_once __DIR__ . '/../php/db_connection.php';
require_once __DIR__ . '/../php/email-config.php';

// Auto-patch database for registration_status
try {
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS registration_status VARCHAR(20) DEFAULT 'pending'");
    $conn->exec("UPDATE users SET registration_status = 'approved' WHERE is_active = true AND registration_status = 'pending'");
} catch(PDOException $e) {}

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
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sex_filter = isset($_GET['sex']) ? $_GET['sex'] : 'all';
$barangay_filter = isset($_GET['barangay']) ? $_GET['barangay'] : 'all';

// Get unique barangays for filter
$barangays = [];
try {
    $b_stmt = $conn->query("SELECT DISTINCT barangay FROM users WHERE barangay IS NOT NULL AND barangay != '' ORDER BY barangay");
    $barangays = $b_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching barangays: " . $e->getMessage());
}

// Get admin role IDs to exclude
$admin_roles = [];
$admin_roles_query = "SELECT role_id FROM roles WHERE role_name IN ('Admin', 'Super Admin')";
try {
    $admin_roles_stmt = $conn->query($admin_roles_query);
    while ($row = $admin_roles_stmt->fetch(PDO::FETCH_ASSOC)) {
        $admin_roles[] = $row['role_id'];
    }
} catch (PDOException $e) {
    error_log("Error fetching admin roles: " . $e->getMessage());
}

// Initialize variables to avoid undefined warnings
$users = [];
$roles = [];
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'inactive_users' => 0,
    'new_this_week' => 0,
    'online_24h' => 0
];

// Get all roles for filter dropdown and forms - Move up to ensure they are available
try {
    $roles_query = "SELECT role_id, role_name FROM roles WHERE is_active = true ORDER BY role_name";
    $roles_stmt = $conn->query($roles_query);
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
}

// Handle Add User
if (isset($_POST['add_user'])) {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? 'abcd1234';
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
    $role_id = (int)($_POST['role_id'] ?? 3);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    $errors = [];
    if (empty($id_number)) $errors['id_number'] = "ID Number is required";
    if (empty($username)) $errors['username'] = "Username is required";
    if (empty($email)) $errors['email'] = "Email is required";
    if (empty($contact_number)) $errors['contact_number'] = "Contact number is required";
    if (empty($first_name)) $errors['first_name'] = "First name is required";
    if (empty($last_name)) $errors['last_name'] = "Last name is required";
    if (empty($birthday)) $errors['birthday'] = "Birthday is required";
    if (empty($sex)) $errors['sex'] = "Sex is required";
    if (empty($street_purok)) $errors['street_purok'] = "Street/Purok is required";
    if (empty($barangay)) $errors['barangay'] = "Barangay is required";
    if (empty($city_municipal)) $errors['city_municipal'] = "City/Municipal is required";
    if (empty($province)) $errors['province'] = "Province is required";
    if (empty($zipcode)) $errors['zipcode'] = "Zip code is required";

    // Age validation
    if (!empty($birthday)) {
        $birthdate = new DateTime($birthday);
        $today = new DateTime();
        $age_calc = $today->diff($birthdate)->y;
        if ($age_calc < 18) {
            $errors['birthday'] = "User must be at least 18 years old.";
        }
    }

    if (empty($errors)) {
        try {
            // Check if username, email, id_number, or contact_number already exists
            $check_query = "SELECT username, email, id_number, contact_number FROM users WHERE username = :username OR email = :email OR id_number = :id_number OR contact_number = :contact_number";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([':username' => $username, ':email' => $email, ':id_number' => $id_number, ':contact_number' => $contact_number]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                if ($existing['username'] === $username) $errors['username'] = "Username is already taken";
                else if ($existing['email'] === $email) $errors['email'] = "Email is already in use";
                else if ($existing['id_number'] === $id_number) $errors['id_number'] = "ID number is already taken";
                else if ($existing['contact_number'] === $contact_number) $errors['contact_number'] = "Contact number is already taken";
                
                if ($is_ajax) {
                    echo json_encode(['status' => 'error', 'errors' => $errors]);
                    exit();
                } else {
                    $_SESSION['error_message'] = reset($errors);
                }
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_query = "INSERT INTO users (username, password, email, id_number, first_name, middle_name, last_name, extension_name, birthday, age, sex, contact_number, street_purok, barangay, city_municipal, province, country, zipcode, role_id, is_active, created_at) 
                               VALUES (:username, :password, :email, :id_number, :first_name, :middle_name, :last_name, :extension_name, :birthday, :age, :sex, :contact_number, :street_purok, :barangay, :city_municipal, :province, :country, :zipcode, :role_id, :is_active, NOW())";
                
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->execute([
                    ':username' => $username, ':password' => $hashed_password, ':email' => $email, ':id_number' => $id_number ?: null,
                    ':first_name' => $first_name, ':middle_name' => $middle_name ?: null, ':last_name' => $last_name, ':extension_name' => $extension_name ?: null,
                    ':birthday' => $birthday ?: null, ':age' => $age, ':sex' => $sex ?: null, ':contact_number' => $contact_number ?: null,
                    ':street_purok' => $street_purok ?: null, ':barangay' => $barangay ?: null, ':city_municipal' => $city_municipal ?: null,
                    ':province' => $province ?: null, ':country' => $country, ':zipcode' => $zipcode ?: null,
                    ':role_id' => $role_id, ':is_active' => $is_active ? 'true' : 'false'
                ]);

                $new_user_id = $conn->lastInsertId();
                
                // Log the action
                $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, new_data, ip_address, user_agent, created_at) 
                             VALUES (:admin_id, 'INSERT', 'users', :record_id, :new_data, :ip_address, :user_agent, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':record_id' => $new_user_id, ':admin_id' => $user_id,
                    ':new_data' => json_encode(['username' => $username, 'email' => $email, 'role_id' => $role_id]),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);

                $email_sent = EmailConfig::sendWelcomeEmail($email, $first_name, $username, $password);
                $success_msg = "User added successfully." . ($email_sent ? " A welcome email with credentials and a setup link has been sent." : " However, the welcome email could not be sent.");
                
                if ($is_ajax) {
                    echo json_encode(['status' => 'success', 'message' => $success_msg]);
                    exit();
                } else {
                    $_SESSION['success_message'] = $success_msg;
                }
            }
        } catch (PDOException $e) {
            if ($is_ajax) {
                echo json_encode(['status' => 'error', 'message' => "Database error: " . $e->getMessage()]);
                exit();
            }
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        if ($is_ajax) {
            echo json_encode(['status' => 'error', 'errors' => $errors]);
            exit();
        }
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
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

    // If regular admin, handle as request
    if (!$is_super_admin) {
        $requested_data = [
            'username' => $username, 'email' => $email, 'id_number' => $id_number, 'first_name' => $first_name,
            'middle_name' => $middle_name, 'last_name' => $last_name, 'extension_name' => $extension_name,
            'birthday' => $birthday, 'sex' => $sex, 'contact_number' => $contact_number,
            'street_purok' => $street_purok, 'barangay' => $barangay, 'city_municipal' => $city_municipal,
            'province' => $province, 'country' => $country, 'zipcode' => $zipcode,
            'role_id' => $role_id, 'is_active' => $is_active
        ];
        if (!empty($new_password)) $requested_data['new_password'] = password_hash($new_password, PASSWORD_DEFAULT);

        try {
            $reason = $_POST['edit_reason'] ?? 'User information update requested.';
            $ins_req_stmt = $conn->prepare("INSERT INTO edit_requests (user_id, requested_by, requested_data, reason) VALUES (:user_id, :requested_by, :requested_data, :reason)");
            $ins_req_stmt->execute([':user_id' => $edit_user_id, ':requested_by' => $user_id, ':requested_data' => json_encode($requested_data), ':reason' => $reason]);
            
            if ($is_ajax) {
                echo json_encode(['status' => 'success', 'message' => "Edit request submitted successfully and is awaiting Super Admin approval."]);
                exit();
            }
            $_SESSION['success_message'] = "Edit request submitted successfully and is awaiting Super Admin approval.";
        } catch (PDOException $e) {
            if ($is_ajax) {
                echo json_encode(['status' => 'error', 'message' => "Failed to submit edit request: " . $e->getMessage()]);
                exit();
            }
            $_SESSION['error_message'] = "Failed to submit edit request: " . $e->getMessage();
        }
        header("Location: users.php?" . http_build_query($_GET));
        exit();
    }

    // Validation for Super Admin direct edit
    $errors = [];
    if (empty($id_number)) $errors['id_number'] = "ID Number is required";
    if (empty($username)) $errors['username'] = "Username is required";
    if (empty($email)) $errors['email'] = "Email is required";
    if (empty($contact_number)) $errors['contact_number'] = "Contact number is required";
    if (empty($first_name)) $errors['first_name'] = "First name is required";
    if (empty($last_name)) $errors['last_name'] = "Last name is required";
    if (empty($birthday)) $errors['birthday'] = "Birthday is required";
    if (empty($sex)) $errors['sex'] = "Sex is required";
    if (empty($street_purok)) $errors['street_purok'] = "Street/Purok is required";
    if (empty($barangay)) $errors['barangay'] = "Barangay is required";
    if (empty($city_municipal)) $errors['city_municipal'] = "City/Municipal is required";
    if (empty($province)) $errors['province'] = "Province is required";
    if (empty($zipcode)) $errors['zipcode'] = "Zip code is required";

    if (!empty($birthday)) {
        $birthdate = new DateTime($birthday);
        $today = new DateTime();
        if ($today->diff($birthdate)->y < 18) $errors['birthday'] = "User must be at least 18 years old.";
    }

    if (empty($errors)) {
        try {
            $check_query = "SELECT username, email, id_number, contact_number FROM users WHERE (username = :username OR email = :email OR id_number = :id_number OR contact_number = :contact_number) AND user_id != :user_id";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([':username' => $username, ':email' => $email, ':id_number' => $id_number, ':contact_number' => $contact_number, ':user_id' => $edit_user_id]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                if ($existing['username'] === $username) $errors['username'] = "Username is already taken";
                else if ($existing['email'] === $email) $errors['email'] = "Email is already in use";
                else if ($existing['id_number'] === $id_number) $errors['id_number'] = "ID number already exists";
                else if ($existing['contact_number'] === $contact_number) $errors['contact_number'] = "Contact number already exists";
                
                if ($is_ajax) {
                    echo json_encode(['status' => 'error', 'errors' => $errors]);
                    exit();
                }
                $_SESSION['error_message'] = reset($errors);
            } else {
                $age = !empty($birthday) ? (new DateTime())->diff(new DateTime($birthday))->y : null;
                $update_query = "UPDATE users SET username = :username, email = :email, id_number = :id_number, first_name = :first_name, middle_name = :middle_name, last_name = :last_name, extension_name = :extension_name, birthday = :birthday, age = :age, sex = :sex, contact_number = :contact_number, street_purok = :street_purok, barangay = :barangay, city_municipal = :city_municipal, province = :province, country = :country, zipcode = :zipcode, role_id = :role_id, is_active = :is_active, updated_at = NOW() WHERE user_id = :user_id";
                
                $params = [
                    ':username' => $username, ':email' => $email, ':id_number' => $id_number, ':first_name' => $first_name, ':middle_name' => $middle_name, ':last_name' => $last_name, ':extension_name' => $extension_name, ':birthday' => $birthday, ':age' => $age, ':sex' => $sex, ':contact_number' => $contact_number, ':street_purok' => $street_purok, ':barangay' => $barangay, ':city_municipal' => $city_municipal, ':province' => $province, ':country' => $country, ':zipcode' => $zipcode, ':role_id' => $role_id, ':is_active' => $is_active ? 'true' : 'false', ':user_id' => $edit_user_id
                ];
                $conn->prepare($update_query)->execute($params);

                if (!empty($new_password)) {
                    $conn->prepare("UPDATE users SET password = :password WHERE user_id = :user_id")->execute([':password' => password_hash($new_password, PASSWORD_DEFAULT), ':user_id' => $edit_user_id]);
                }

                // Log the action
                $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, new_data, ip_address, user_agent, created_at) 
                             VALUES (:admin_id, 'UPDATE', 'users', :record_id, :new_data, :ip_address, :user_agent, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':record_id' => $edit_user_id,
                    ':admin_id' => $user_id,
                    ':new_data' => json_encode(['username' => $username, 'email' => $email, 'role_id' => $role_id]),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);

                if ($is_ajax) {
                    echo json_encode(['status' => 'success', 'message' => "User updated successfully."]);
                    exit();
                }
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
    $new_status_val = $_POST['new_status'] === 'true' ? true : false;
    
    // If regular admin, handle as request
    if (!$is_super_admin) {
        try {
            $reason = $_POST['status_reason'] ?? 'User status change requested.';
            $requested_data = ['is_active' => $new_status_val ? 1 : 0];
            
            // Check if there's already a pending edit request for this user's status
            $check_req_stmt = $conn->prepare("SELECT COUNT(*) FROM edit_requests WHERE user_id = :user_id AND status = 'pending'");
            $check_req_stmt->execute([':user_id' => $target_user_id]);
            
            if ($check_req_stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "An edit or status request for this user is already pending.";
            } else {
                $ins_req_stmt = $conn->prepare("INSERT INTO edit_requests (user_id, requested_by, requested_data, reason) VALUES (:user_id, :requested_by, :requested_data, :reason)");
                $ins_req_stmt->execute([
                    ':user_id' => $target_user_id,
                    ':requested_by' => $user_id,
                    ':requested_data' => json_encode($requested_data),
                    ':reason' => $reason
                ]);
                
                $_SESSION['success_message'] = "Status change request submitted successfully and is awaiting Super Admin approval.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to submit status request: " . $e->getMessage();
        }
        header("Location: users.php?" . http_build_query($_GET));
        exit();
    }

    // Super Admin direct toggle
    try {
        // Fetch user data first to check last_login and email
        $user_query = "SELECT email, first_name, last_login, is_active FROM users WHERE user_id = :user_id";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->execute([':user_id' => $target_user_id]);
        $userData = $user_stmt->fetch(PDO::FETCH_ASSOC);

        $update_query = "UPDATE users SET is_active = :is_active, registration_status = 'approved', updated_at = NOW() WHERE user_id = :user_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->execute([
            ':is_active' => $new_status_val ? 'true' : 'false',
            ':user_id' => $target_user_id
        ]);
        
        // Log the action in admin_activity_logs
        $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, created_at) 
                     VALUES (:admin_id, 'TOGGLE_STATUS', 'users', :record_id, :old_data, :new_data, :ip_address, :user_agent, NOW())";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->execute([
            ':record_id' => $target_user_id,
            ':admin_id' => $user_id,
            ':old_data' => json_encode(['is_active' => !($new_status_val)]),
            ':new_data' => json_encode(['is_active' => $new_status_val]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        // Send approval email if this is a newly registered user being activated
        if ($userData && empty($userData['last_login']) && $userData['is_active'] == false && $new_status_val == true) {
            EmailConfig::sendRegistrationStatusEmail($userData['email'], $userData['first_name'], 'accepted');
            $_SESSION['success_message'] = "User registration approved and notification email sent.";
        } else {
            $_SESSION['success_message'] = "User status updated successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to update user status.";
    }
    
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle Reject Registration Request (Admin and Super Admin)
if (isset($_POST['reject_registration']) && $is_admin) {
    $target_user_id = (int)$_POST['user_id'];
    $reason = $_POST['reason'] ?? 'Did not meet requirements.';
    
    try {
        $conn->beginTransaction();
        
        // Fetch user data
        $select_query = "SELECT * FROM users WHERE user_id = :user_id";
        $select_stmt = $conn->prepare($select_query);
        $select_stmt->execute([':user_id' => $target_user_id]);
        $user_data = $select_stmt->fetch(PDO::FETCH_ASSOC);
        
        $is_active_bool = filter_var($user_data['is_active'], FILTER_VALIDATE_BOOLEAN);
        
        if ($user_data && !$is_active_bool) {
            // Reject user instead of deleting
            $reject_query = "UPDATE users SET registration_status = 'rejected', updated_at = NOW() WHERE user_id = :user_id";
            $reject_stmt = $conn->prepare($reject_query);
            $reject_stmt->execute([':user_id' => $target_user_id]);
            
            $conn->commit();
            
            // Try to send email, but don't fail the rejection if email fails
            try {
                EmailConfig::sendRegistrationStatusEmail($user_data['email'], $user_data['first_name'], 'rejected', $reason);
                $_SESSION['success_message'] = "User registration rejected and notification email sent.";
            } catch (Exception $emailException) {
                $_SESSION['success_message'] = "User registration rejected, but notification email could not be sent.";
            }
        } else {
            $conn->rollBack();
            $_SESSION['error_message'] = "Cannot reject this user. They may already be active or missing.";
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error_message'] = "Failed to reject user: " . $e->getMessage();
    }
    
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle User Deletion (super admin only) - keep existing code
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
                // Delete related records first
                $conn->prepare("DELETE FROM admin_activity_logs WHERE admin_id = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM login_attempts WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM password_reset_logs WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM password_reset_sessions WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                $conn->prepare("DELETE FROM user_security_answers WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
                
                // Delete the user
                $delete_query = "DELETE FROM users WHERE user_id = :user_id";
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->execute([':user_id' => $target_user_id]);
                
                // Log the action in admin_activity_logs
                $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, ip_address, user_agent, created_at) 
                             VALUES (:admin_id, 'DELETE', 'users', :record_id, :old_data, :ip_address, :user_agent, NOW())";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->execute([
                    ':record_id' => $target_user_id,
                    ':admin_id' => $user_id,
                    ':old_data' => json_encode($user_data),
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

// Handle Deletion Request (Admin/Super Admin)
if (isset($_POST['request_deletion'])) {
    $target_user_id = (int)$_POST['user_id'];
    $reason = $_POST['reason'] ?? '';
    
    // Don't allow requesting deletion of yourself
    if ($target_user_id == $user_id) {
        $_SESSION['error_message'] = "You cannot request deletion of your own account.";
    } else {
        try {
            // Check if there's already a pending request
            $check_req_stmt = $conn->prepare("SELECT COUNT(*) FROM deletion_requests WHERE user_id = :user_id AND status = 'pending'");
            $check_req_stmt->execute([':user_id' => $target_user_id]);
            
            if ($check_req_stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "A deletion request for this user is already pending.";
            } else {
                $ins_req_stmt = $conn->prepare("INSERT INTO deletion_requests (user_id, requested_by, reason) VALUES (:user_id, :requested_by, :reason)");
                $ins_req_stmt->execute([
                    ':user_id' => $target_user_id,
                    ':requested_by' => $user_id,
                    ':reason' => $reason
                ]);
                
                $_SESSION['success_message'] = "Deletion request submitted successfully and is awaiting Super Admin approval.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Failed to submit deletion request: " . $e->getMessage();
        }
    }
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle Approve Deletion (Super Admin only)
if (isset($_POST['approve_deletion']) && $is_super_admin) {
    $request_id = (int)$_POST['request_id'];
    $target_user_id = (int)$_POST['user_id'];
    $review_notes = $_POST['review_notes'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // Get user data for logging
        $select_query = "SELECT * FROM users WHERE user_id = :user_id";
        $select_stmt = $conn->prepare($select_query);
        $select_stmt->execute([':user_id' => $target_user_id]);
        $user_data = $select_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            // Update request status
            $upd_req_stmt = $conn->prepare("UPDATE deletion_requests SET status = 'approved', reviewed_by = :reviewed_by, review_notes = :review_notes, updated_at = NOW() WHERE request_id = :request_id");
            $upd_req_stmt->execute([
                ':request_id' => $request_id,
                ':reviewed_by' => $user_id,
                ':review_notes' => $review_notes
            ]);
            
            // Delete related records
            $conn->prepare("DELETE FROM admin_activity_logs WHERE admin_id = :user_id")->execute([':user_id' => $target_user_id]);
            $conn->prepare("DELETE FROM login_attempts WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
            $conn->prepare("DELETE FROM password_reset_logs WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
            $conn->prepare("DELETE FROM password_reset_sessions WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
            $conn->prepare("DELETE FROM user_security_answers WHERE user_id = :user_id")->execute([':user_id' => $target_user_id]);
            
            // Delete the user
            $delete_query = "DELETE FROM users WHERE user_id = :user_id";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->execute([':user_id' => $target_user_id]);
            
            // Log deletion in admin_activity_logs
            $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, ip_address, user_agent, created_at) 
                         VALUES (:admin_id, 'DELETE', 'users', :record_id, :old_data, :ip_address, :user_agent, NOW())";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->execute([
                ':record_id' => $target_user_id,
                ':admin_id' => $user_id,
                ':old_data' => json_encode($user_data),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            $conn->commit();
            $_SESSION['success_message'] = "User deletion approved and executed successfully.";
        } else {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error_message'] = "User not found.";
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error_message'] = "Failed to approve deletion: " . $e->getMessage();
    }
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle Deny Deletion (Super Admin only)
if (isset($_POST['deny_deletion']) && $is_super_admin) {
    $request_id = (int)$_POST['request_id'];
    $review_notes = $_POST['review_notes'] ?? '';
    
    try {
        $upd_req_stmt = $conn->prepare("UPDATE deletion_requests SET status = 'denied', reviewed_by = :reviewed_by, review_notes = :review_notes, updated_at = NOW() WHERE request_id = :request_id");
        $upd_req_stmt->execute([
            ':request_id' => $request_id,
            ':reviewed_by' => $user_id,
            ':review_notes' => $review_notes
        ]);
        
        $_SESSION['success_message'] = "Deletion request denied.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to deny deletion: " . $e->getMessage();
    }
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle Approve Edit (Super Admin only)
if (isset($_POST['approve_edit']) && $is_super_admin) {
    $request_id = (int)$_POST['request_id'];
    $target_user_id = (int)$_POST['user_id'];
    $review_notes = $_POST['review_notes'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // Get request data
        $req_stmt = $conn->prepare("SELECT requested_data FROM edit_requests WHERE request_id = :request_id AND status = 'pending'");
        $req_stmt->execute([':request_id' => $request_id]);
        $request_row = $req_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request_row) {
            $data = json_decode($request_row['requested_data'], true);
            
            // Build update query based on requested data
            $update_fields = [];
            $params = [':user_id' => $target_user_id];
            
            foreach ($data as $key => $value) {
                if ($key === 'new_password') {
                    $update_fields[] = 'password = :password';
                    $params[':password'] = $value; // Already hashed
                } else if ($key === 'is_active') {
                    $update_fields[] = 'is_active = :is_active';
                    $params[':is_active'] = $value ? 'true' : 'false';
                } else if ($key === 'role_id') {
                    $update_fields[] = 'role_id = :role_id';
                    $params[':role_id'] = $value;
                } else {
                    $update_fields[] = "$key = :$key";
                    $params[":$key"] = $value ?: null;
                }
            }
            $update_fields[] = 'updated_at = NOW()';
            
            $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE user_id = :user_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->execute($params);
            
            // Update request status
            $upd_req_stmt = $conn->prepare("UPDATE edit_requests SET status = 'approved', reviewed_by = :reviewed_by, review_notes = :review_notes, updated_at = NOW() WHERE request_id = :request_id");
            $upd_req_stmt->execute([
                ':request_id' => $request_id,
                ':reviewed_by' => $user_id,
                ':review_notes' => $review_notes
            ]);
            
            // Log the action
            $log_query = "INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, new_data, ip_address, user_agent, created_at) 
                         VALUES (:admin_id, 'UPDATE_APPROVED', 'users', :record_id, :old_data, :new_data, :ip_address, :user_agent, NOW())";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->execute([
                ':record_id' => $target_user_id,
                ':admin_id' => $user_id,
                ':old_data' => null,
                ':new_data' => json_encode($data),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
            
            // Check if this was a registration approval (active = true, last_login = NULL)
            $user_check_stmt = $conn->prepare("SELECT email, first_name, last_login FROM users WHERE user_id = :user_id");
            $user_check_stmt->execute([':user_id' => $target_user_id]);
            $userData = $user_check_stmt->fetch(PDO::FETCH_ASSOC);
            
            $notif_sent = false;
            if ($userData && empty($userData['last_login']) && isset($data['is_active']) && $data['is_active'] == 1) {
                EmailConfig::sendRegistrationStatusEmail($userData['email'], $userData['first_name'], 'accepted');
                $notif_sent = true;
            }

            $conn->commit();
            $_SESSION['success_message'] = "User edit approved and applied successfully." . ($notif_sent ? " Notification email sent." : "");
        } else {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $_SESSION['error_message'] = "Edit request not found or not pending.";
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error_message'] = "Failed to approve edit: " . $e->getMessage();
    }
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Handle Deny Edit (Super Admin only)
if (isset($_POST['deny_edit']) && $is_super_admin) {
    $request_id = (int)$_POST['request_id'];
    $review_notes = $_POST['review_notes'] ?? '';
    
    try {
        $upd_req_stmt = $conn->prepare("UPDATE edit_requests SET status = 'denied', reviewed_by = :reviewed_by, review_notes = :review_notes, updated_at = NOW() WHERE request_id = :request_id");
        $upd_req_stmt->execute([
            ':request_id' => $request_id,
            ':reviewed_by' => $user_id,
            ':review_notes' => $review_notes
        ]);
        
        $_SESSION['success_message'] = "Edit request denied.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Failed to deny edit: " . $e->getMessage();
    }
    header("Location: users.php?" . http_build_query($_GET));
    exit();
}

// Get pending deletion requests if super admin
$pending_requests = [];
if ($is_super_admin) {
    try {
        $pend_req_query = "
            SELECT dr.*, u.username as target_username, u.first_name as target_fname, u.last_name as target_lname,
                   rb.username as requester_username
            FROM deletion_requests dr
            JOIN users u ON dr.user_id = u.user_id
            JOIN users rb ON dr.requested_by = rb.user_id
            WHERE dr.status = 'pending'
            ORDER BY dr.created_at ASC
        ";
        $pending_requests = $conn->query($pend_req_query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silently fail or log
        error_log("Error fetching pending deletion requests: " . $e->getMessage());
    }
}

$pending_edit_requests = [];
if ($is_super_admin) {
    try {
        $pend_req_query = "
            SELECT er.*, u.username as target_username, u.first_name as target_fname, u.last_name as target_lname,
                   rb.username as requester_username
            FROM edit_requests er
            JOIN users u ON er.user_id = u.user_id
            JOIN users rb ON er.requested_by = rb.user_id
            WHERE er.status = 'pending'
            ORDER BY er.created_at ASC
        ";
        $pending_edit_requests = $conn->query($pend_req_query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silently fail or log
        error_log("Error fetching pending edit requests: " . $e->getMessage());
    }
}

// Build query conditions - MODIFIED TO EXCLUDE ADMIN USERS
$conditions = [];
$params = [];

// Exclude users with admin roles (role_id = 1 or 2)
if (!empty($admin_roles)) {
    $placeholders = [];
    foreach ($admin_roles as $index => $role) {
        $param_name = ":admin_role_" . $index;
        $placeholders[] = $param_name;
        $params[$param_name] = $role;
    }
    $conditions[] = "u.role_id NOT IN (" . implode(',', $placeholders) . ")";
}

if (!empty($search)) {
    $conditions[] = "(u.first_name ILIKE :search OR u.last_name ILIKE :search OR u.username ILIKE :search OR u.email ILIKE :search OR u.id_number ILIKE :search)";
    $params[':search'] = "%$search%";
}

if ($status_filter === 'active') {
    $conditions[] = "u.is_active = true";
} elseif ($status_filter === 'inactive') {
    $conditions[] = "u.is_active = false AND (u.registration_status IS NULL OR u.registration_status = 'approved')";
} elseif ($status_filter === 'new') {
    $conditions[] = "u.is_active = false AND u.last_login IS NULL AND (u.registration_status IS NULL OR u.registration_status = 'pending')";
} elseif ($status_filter === 'rejected') {
    $conditions[] = "u.registration_status = 'rejected'";
}

if (!empty($date_from)) {
    $conditions[] = "DATE(u.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(u.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($sex_filter !== 'all') {
    $conditions[] = "u.sex = :sex";
    $params[':sex'] = $sex_filter;
}

if ($barangay_filter !== 'all') {
    $conditions[] = "u.barangay = :barangay";
    $params[':barangay'] = $barangay_filter;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total users count for pagination (excluding admins)
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
$count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total_users = $count_result ? (int)$count_result['total'] : 0;
$total_pages = $total_users > 0 ? ceil($total_users / $limit) : 1;

// Get users with their roles and activity stats (excluding admins)
try {
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
        u.registration_status,
        u.role_id,
        r.role_name,
        r.role_description,
        (SELECT status FROM deletion_requests WHERE user_id = u.user_id AND status = 'pending' LIMIT 1) as pending_deletion_status,
        (SELECT request_id FROM deletion_requests WHERE user_id = u.user_id AND status = 'pending' LIMIT 1) as pending_deletion_id,
        (
            SELECT COUNT(*) 
            FROM admin_activity_logs a 
            WHERE a.record_id = u.user_id::text 
            AND a.table_name = 'users'
            AND a.created_at >= NOW() - INTERVAL '30 days'
        ) as activities_30d,
        (
            SELECT MAX(created_at) 
            FROM admin_activity_logs a 
            WHERE a.record_id = u.user_id::text
            AND a.table_name = 'users'
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

// Get all roles for filter dropdown and forms - (Already fetched above)

// Get summary statistics (excluding admins)
$stats_query = "
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN is_active = true THEN 1 END) as active_users,
        COUNT(CASE WHEN is_active = false THEN 1 END) as inactive_users,
        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '7 days' THEN 1 END) as new_this_week,
        COUNT(CASE WHEN last_login >= NOW() - INTERVAL '24 hours' THEN 1 END) as online_24h
    FROM users
";

$stats_params = [];
if (!empty($admin_roles)) {
    $placeholders = [];
    foreach ($admin_roles as $index => $role) {
        $param_name = ":stats_admin_role_" . $index;
        $placeholders[] = $param_name;
        $stats_params[$param_name] = $role;
    }
    $stats_query .= " WHERE role_id NOT IN (" . implode(',', $placeholders) . ")";
}

$stats_stmt = $conn->prepare($stats_query);
foreach ($stats_params as $key => $value) {
    $stats_stmt->bindValue($key, $value);
}
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in users.php main query: " . $e->getMessage());
    $_SESSION['error_message'] = "A database error occurred while fetching user data.";
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
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin_style.css">
    <style>
        :root {
            --primary: #2a6e3b;
            --primary-light: #3b8739;
            --primary-dark: #1c4c2a;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #fafff9;
            --dark: #1e3a2f;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --border-color: #cbe6bf;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            --font-main: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        body {
            font-family: var(--font-main);
            background-color: #f4f7f2;
            color: var(--dark);
        }

        .users-panel-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .glass-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .welcome-section h1 {
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            color: var(--secondary);
            font-size: 0.95rem;
        }

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 1.2rem;
            border-left: 4px solid var(--primary);
        }

        .stat-card h3 {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--secondary);
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-dark);
        }

        /* Buttons */
        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--gray-300);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background-color: var(--gray-400);
        }

        /* Filters */
        .filters-section {
            padding: 1.2rem;
        }

        .filter-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.4rem;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .modern-table th {
            background-color: #f8faf7;
            text-align: left;
            padding: 0.8rem;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            white-space: nowrap;
        }

        .modern-table td {
            padding: 0.8rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .modern-table tr:hover {
            background-color: #fafdfa;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background-color: var(--primary-light);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .user-name {
            font-weight: 600;
            color: var(--primary-dark);
        }

        .user-email {
            font-size: 0.8rem;
            color: var(--secondary);
        }

        .role-badge {
            background-color: #e9f5ec;
            color: var(--primary);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.4rem;
        }

        .action-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--secondary);
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .action-btn:hover {
            background-color: var(--gray-100);
            color: var(--dark);
        }

        .action-btn-view:hover { color: var(--info); border-color: var(--info); }
        .action-btn-edit:hover { color: var(--primary); border-color: var(--primary); }
        .action-btn-delete:hover { color: var(--danger); border-color: var(--danger); }

        /* Alerts */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            max-width: 900px;
            width: 95%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-content form {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }

        .modal-footer {
            padding: 1.2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.8rem;
        }

        /* Forms in Modal */
        .input-box { margin-bottom: 1rem; }
        .input-box label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 0.4rem;
        }
        .input-box input, 
        .input-box select {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
        }

        .form-header {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-dark);
            border-bottom: 1px solid var(--border-color);
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding-bottom: 2rem;
        }

        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .pagination a:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--light);
        }

        .pagination a.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
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
            color: var(--secondary);
            cursor: pointer;
            padding: 5px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        /* Avatar styles */
        .user-avatar.large {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }

        /* Mobile specific */
        @media (max-width: 768px) {
            .users-panel-container { padding: 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .column { flex-direction: column !important; gap: 10px !important; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        /* User Profile Detailed View - Premium UI/UX */
        .detailed-log-view {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .log-detail-section {
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f0f4ef;
        }
        
        .log-detail-section:last-child {
            border-bottom: none;
        }

        .log-section-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: #589065;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .log-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.2rem 1rem;
        }

        .acc-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.2rem 1rem;
        }

        .log-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .log-field-label {
            font-size: 0.65rem;
            color: #8c9c8c;
            font-weight: 700;
            text-transform: uppercase;
        }

        .log-field-value {
            font-size: 0.85rem;
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
        }
        .error-msg {
            color: var(--danger);
            font-size: 0.75rem;
            margin-top: 4px;
            display: none;
            font-weight: 500;
        }

        .input-box input.invalid, 
        .input-box select.invalid {
            border-color: var(--danger);
            background-color: #fff8f8;
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
                
                <?php if ($is_super_admin): ?>
                <li class="nav-divider"></li>
                <li class="nav-header">Administration</li>

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
        <div class="users-panel-container">
            <div class="welcome-section glass-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1>User Management 👥</h1>
                        <p>Manage community members and monitor account statuses.</p>
                    </div>
                    <?php if ($is_super_admin): ?>
                    <button onclick="openAddModal()" class="btn-modern btn-primary">
                        <i class="fas fa-user-plus"></i> Add New User
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card glass-card">
                    <h3>Total Users</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Active Users</h3>
                    <div class="stat-value"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Inactive Users</h3>
                    <div class="stat-value"><?php echo number_format($stats['inactive_users'] ?? 0); ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>New This Week</h3>
                    <div class="stat-value"><?php echo number_format($stats['new_this_week'] ?? 0); ?></div>
                </div>
                <div class="stat-card glass-card">
                    <h3>Online (24h)</h3>
                    <div class="stat-value"><?php echo number_format($stats['online_24h'] ?? 0); ?></div>
                </div>
            </div>

            <?php if ($is_super_admin && !empty($pending_requests)): ?>
                <div class="pending-requests-section glass-card" style="margin-bottom: 2rem; border-left: 5px solid var(--warning);">
                    <h2 class="section-title" style="font-size: 1.2rem; color: var(--primary-dark); margin-bottom: 1rem;">
                        <i class="fas fa-exclamation-triangle" style="color: var(--warning); margin-right: 0.5rem;"></i>
                        Pending Deletion Requests
                    </h2>
                    <div class="table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Target User</th>
                                    <th>Requested By</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $req): ?>
                                    <tr>
                                        <td>
                                            <div class="user-name"><?php echo htmlspecialchars($req['target_username']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($req['target_fname'] . ' ' . $req['target_lname']); ?></div>
                                        </td>
                                        <td>
                                            <span class="role-badge"><?php echo htmlspecialchars($req['requester_username']); ?></span>
                                        </td>
                                        <td style="max-width: 300px; white-space: normal; line-height: 1.4;">
                                            <?php echo htmlspecialchars($req['reason']); ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.8rem;"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('H:i', strtotime($req['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn" style="color: var(--success);" 
                                                        onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8'); ?>, 'approve')" title="Approve & Delete">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="action-btn" style="color: var(--danger);" 
                                                        onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8'); ?>, 'deny')" title="Deny Request">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($is_super_admin && !empty($pending_edit_requests)): ?>
                <div class="pending-requests-section glass-card" style="margin-bottom: 2rem; border-left: 5px solid var(--info);">
                    <h2 class="section-title" style="font-size: 1.2rem; color: var(--primary-dark); margin-bottom: 1rem;">
                        <i class="fas fa-edit" style="color: var(--info); margin-right: 0.5rem;"></i>
                        Pending Edit Requests
                    </h2>
                    <div class="table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Target User</th>
                                    <th>Requested By</th>
                                    <th>Reason / Details</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_edit_requests as $req): ?>
                                    <tr>
                                        <td>
                                            <div class="user-name"><?php echo htmlspecialchars($req['target_username']); ?></div>
                                            <div class="user-email"><?php echo htmlspecialchars($req['target_fname'] . ' ' . $req['target_lname']); ?></div>
                                        </td>
                                        <td>
                                            <span class="role-badge"><?php echo htmlspecialchars($req['requester_username']); ?></span>
                                        </td>
                                        <td style="max-width: 300px; white-space: normal; line-height: 1.4;">
                                            <?php echo htmlspecialchars($req['reason']); ?><br>
                                            <span style="font-size: 0.8rem; color: var(--secondary);"><a href="#" onclick="openViewEditRequestModal(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8'); ?>); return false;">View ChangesRequested</a></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.8rem;"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('H:i', strtotime($req['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn" style="color: var(--success);" 
                                                        onclick="openReviewEditModal(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8'); ?>, 'approve')" title="Approve & Apply Edit">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="action-btn" style="color: var(--danger);" 
                                                        onclick="openReviewEditModal(<?php echo htmlspecialchars(json_encode($req), ENT_QUOTES, 'UTF-8'); ?>, 'deny')" title="Deny Request">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->

            
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
            <div class="filters-section glass-card">
                <form method="GET" class="filters-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search Users</label>
                        <input type="text" name="search" placeholder="Name, email, username, ID..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-filter"></i> Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><i class="far fa-calendar-alt"></i> Created From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="far fa-calendar-alt"></i> Created To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>


                    
                    
                    <div class="filter-actions" style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn-modern btn-primary" style="flex: 1;">
                            Filter
                        </button>
                        <a href="users.php" class="btn-modern btn-secondary" style="flex: 1; justify-content: center;">
                            Clear
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="users-list-section glass-card" style="margin-top: 1.5rem;">
                <div class="table-container">
                    <table class="modern-table">
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
                                <td colspan="8" style="text-align: center; padding: 3rem; color: var(--secondary);">
                                    <i class="fas fa-users" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <br> No users found matches your criteria.
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
                                                    if (isset($user['pending_deletion_status']) && $user['pending_deletion_status'] === 'pending') {
                                                        echo ' <span class="badge" style="background: var(--warning); color: #000; font-size: 0.6rem;">PENDING DELETION</span>';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="user-email">
                                                    <?php echo htmlspecialchars($user['email'] ?? 'No email'); ?>
                                                    <?php if (!empty($user['username'])): ?>
                                                    <br><span style="opacity: 0.7;">@<?php echo htmlspecialchars($user['username']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($user['sex'] ?? ''); ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($user['contact_number'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.8rem; line-height: 1.2;">
                                            <?php 
                                            $location = array_filter([
                                                $user['barangay'] ?? '',
                                                $user['city_municipal'] ?? '',
                                                $user['province'] ?? ''
                                            ]);
                                            echo htmlspecialchars(!empty($location) ? implode(', ', $location) : 'No location info');
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge">
                                            <?php echo htmlspecialchars($user['role_name'] ?? 'Guest'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($user['registration_status']) && $user['registration_status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">REJECTED</span>
                                        <?php elseif (!$user['is_active'] && empty($user['last_login']) && (!isset($user['registration_status']) || $user['registration_status'] === 'pending')): ?>
                                        <span class="badge" style="background-color: #17a2b8; color: white;">NEW</span>
                                        <?php elseif ($user['is_active']): ?>
                                        <span class="badge badge-success">ACTIVE</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">INACTIVE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.8rem;"><?php echo $user['activities_30d'] ?? 0; ?> actions</div>
                                        <?php if ($user['last_activity']): ?>
                                        <div class="text-muted" style="font-size: 0.7rem;">
                                            Last: <?php echo date('M d', strtotime($user['last_activity'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.8rem;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                        <?php if ($user['last_login']): ?>
                                        <div class="text-muted" style="font-size: 0.7rem;">
                                            Log: <?php echo date('M d', strtotime($user['last_login'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn action-btn-view" onclick="viewUser(<?php echo $user['user_id']; ?>)" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn action-btn-edit" onclick="openEditModal(<?php echo $user['user_id']; ?>)" title="Quick Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            
                                            <?php if ($user['user_id'] != $user_id): ?>
                                            <?php if (!$user['is_active'] && empty($user['last_login']) && (!isset($user['registration_status']) || $user['registration_status'] === 'pending')): ?>
                                                <!-- Registration Approval Flow -->
                                                <form method="POST" style="display: contents;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="new_status" value="true">
                                                    <button type="submit" name="toggle_status" class="action-btn" 
                                                            title="Approve Registration" style="color: var(--success);">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: contents;" onsubmit="return confirm('Are you sure you want to reject this registration?');">
                                                    <input type="hidden" name="reject_registration" value="1">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <button type="submit" class="action-btn" 
                                                            title="Reject Registration" style="color: var(--danger);">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Standard Activate/Deactivate Toggle -->
                                                <?php if ($is_super_admin): ?>
                                                    <form method="POST" style="display: contents;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $user['is_active'] ? 'false' : 'true'; ?>">
                                                        <button type="submit" name="toggle_status" class="action-btn" 
                                                                title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Request Status Change -->
                                                    <button type="button" class="action-btn" 
                                                            onclick="showStatusRequestModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($full_name)); ?>', <?php echo $user['is_active'] ? 'false' : 'true'; ?>)"
                                                            title="Request <?php echo $user['is_active'] ? 'Deactivation' : 'Activation'; ?>">
                                                        <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Delete functionality removed as per request -->
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
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 1rem; color: var(--secondary); font-size: 0.85rem;">
                Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> users
            </div>
            <?php endif; ?>
        </div>
    </main>
    
<!-- Add User Modal -->
<div id="addModal" class="modal">
    <div class="modal-content" style="max-width: 1100px;">
        <div class="modal-header">
            <h3 style="color: var(--primary-dark); font-weight: 700;">Add New User</h3>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST" id="addForm">
            <div class="modal-body">
                <div class="form-header">Account Information</div>
                <div class="acc-info">
                    <div class="input-box">
                        <label>ID Number</label>
                        <input type="text" name="id_number" id="add_id_number" placeholder="0000-0000" autocomplete="off">
                        <span class="error-msg" id="err-id_number"></span>
                    </div>
                    <div class="input-box">
                        <label>Username</label>
                        <input type="text" name="username" id="add_username" placeholder="alex24" autocomplete="username">
                        <span class="error-msg" id="err-username"></span>
                    </div>
                    <div class="input-box">
                        <label>Email Address</label>
                        <input type="email" name="email" id="add_email" placeholder="alex.ligalig@company.com" autocomplete="email">
                        <span class="error-msg" id="err-email"></span>
                    </div>
                    <div class="input-box">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" id="add_contact_number" placeholder="09123456789" autocomplete="tel">
                        <span class="error-msg" id="err-contact_number"></span>
                    </div>
                    <div class="input-box">
                        <label>Password</label>
                        <div class="password-input-group">
                            <input type="password" name="password" id="add_password" value="FFll24()" placeholder="Create complex password" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePasswordVisibility('add_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="strength-meter-container" style="margin-top: 5px;">
                            <div id="add_strength_meter" style="height: 4px; width: 0; transition: all 0.3s; border-radius: 2px;"></div>
                            <span id="add_strength_text" style="font-size: 0.7rem; color: var(--secondary);">Password Strength: None</span>
                        </div>
                        <span class="error-msg" id="err-password"></span>
                    </div>
                </div>

                <div class="form-header">Personal Information</div>
                <div class="acc-info">
                    <div class="input-box">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="add_first_name" autocomplete="given-name">
                        <span class="error-msg" id="err-first_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Middle Name (Optional)</label>
                        <input type="text" name="middle_name" id="add_middle_name" autocomplete="additional-name">
                        <span class="error-msg" id="err-middle_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="add_last_name" autocomplete="family-name">
                        <span class="error-msg" id="err-last_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Extension Name</label>
                        <input type="text" name="extension_name" id="add_extension_name" placeholder="Jr., Sr." autocomplete="honorific-suffix">
                        <span class="error-msg" id="err-extension_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Birthday</label>
                        <input type="date" name="birthday" onchange="calculateAddAge()" id="add_birthday" autocomplete="bday">
                        <span class="error-msg" id="err-birthday"></span>
                    </div>
                    <div class="input-box">
                        <label>Age</label>
                        <input type="text" id="add_age" readonly style="background-color: var(--gray-100);">
                    </div>
                    <div class="input-box">
                        <label>Sex</label>
                        <select name="sex" id="add_sex">
                            <option value="">Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <span class="error-msg" id="err-sex"></span>
                    </div>
                    <div class="input-box">
                        <!-- Spacer for grid alignment -->
                    </div>
                </div>

                <div class="form-header">Address Information</div>
                <div class="log-grid">
                    <div class="input-box">
                        <label>Purok/Street</label>
                        <input type="text" name="street_purok" id="add_street_purok" autocomplete="street-address">
                        <span class="error-msg" id="err-street_purok"></span>
                    </div>
                    <div class="input-box">
                        <label>Barangay</label>
                        <input type="text" name="barangay" id="add_barangay" autocomplete="address-level3">
                        <span class="error-msg" id="err-barangay"></span>
                    </div>
                    <div class="input-box">
                        <label>City/Municipal</label>
                        <input type="text" name="city_municipal" id="add_city_municipal" autocomplete="address-level2">
                        <span class="error-msg" id="err-city_municipal"></span>
                    </div>
                    <div class="input-box">
                        <label>Province</label>
                        <input type="text" name="province" id="add_province" autocomplete="address-level1">
                        <span class="error-msg" id="err-province"></span>
                    </div>
                    <div class="input-box">
                        <label>Country</label>
                        <input type="text" name="country" id="add_country" value="Philippines" autocomplete="country-name">
                        <span class="error-msg" id="err-country"></span>
                    </div>
                    <div class="input-box">
                        <label>Zip Code</label>
                        <input type="text" name="zipcode" id="add_zipcode" autocomplete="postal-code">
                        <span class="error-msg" id="err-zipcode"></span>
                    </div>
                </div>

                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modern btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" name="add_user" class="btn-modern btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content" style="max-width: 1100px;">
        <div class="modal-header">
            <h3 style="color: var(--primary-dark); font-weight: 700;">Edit User Information</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-body">
                <div class="form-header">Account Information</div>
                <div class="log-grid">
                    <div class="input-box">
                        <label>ID Number</label>
                        <input type="text" id="edit_id_number" name="id_number" autocomplete="off">
                        <span class="error-msg" id="err-edit-id_number"></span>
                    </div>
                    <div class="input-box">
                        <label>Username</label>
                        <input type="text" id="edit_username" name="username" autocomplete="username">
                        <span class="error-msg" id="err-edit-username"></span>
                    </div>
                    <div class="input-box">
                        <label>Email Address</label>
                        <input type="email" id="edit_email" name="email" autocomplete="email">
                        <span class="error-msg" id="err-edit-email"></span>
                    </div>
                    <div class="input-box">
                        <label>Contact Number</label>
                        <input type="text" id="edit_contact_number" name="contact_number" autocomplete="tel">
                        <span class="error-msg" id="err-edit-contact_number"></span>
                    </div>
                </div>

                <div class="form-header">Personal Information</div>
                <div class="log-grid">
                    <div class="input-box">
                        <label>First Name</label>
                        <input type="text" id="edit_first_name" name="first_name" autocomplete="given-name">
                        <span class="error-msg" id="err-edit-first_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Middle Name (Optional)</label>
                        <input type="text" id="edit_middle_name" name="middle_name" autocomplete="additional-name">
                        <span class="error-msg" id="err-edit-middle_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Last Name</label>
                        <input type="text" id="edit_last_name" name="last_name" autocomplete="family-name">
                        <span class="error-msg" id="err-edit-last_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Extension Name (Optional)</label>
                        <input type="text" id="edit_extension_name" name="extension_name" autocomplete="honorific-suffix">
                        <span class="error-msg" id="err-edit-extension_name"></span>
                    </div>
                    <div class="input-box">
                        <label>Birthday</label>
                        <input type="date" id="edit_birthday" name="birthday" onchange="calculateEditAge()" autocomplete="bday">
                        <span class="error-msg" id="err-edit-birthday"></span>
                    </div>
                    <div class="input-box">
                        <label>Age</label>
                        <input type="text" id="edit_age" readonly style="background-color: var(--gray-100);">
                    </div>
                    <div class="input-box">
                        <label>Sex</label>
                        <select id="edit_sex" name="sex">
                            <option value="">Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <span class="error-msg" id="err-edit-sex"></span>
                    </div>
                    <div class="input-box">
                        <!-- Spacer -->
                    </div>
                </div>

                <div class="form-header">Address Information</div>
                <div class="log-grid">
                    <div class="input-box">
                        <label>Street/Purok</label>
                        <input type="text" id="edit_street_purok" name="street_purok" autocomplete="street-address">
                        <span class="error-msg" id="err-edit-street_purok"></span>
                    </div>
                    <div class="input-box">
                        <label>Barangay</label>
                        <input type="text" id="edit_barangay" name="barangay" autocomplete="address-level3">
                        <span class="error-msg" id="err-edit-barangay"></span>
                    </div>
                    <div class="input-box">
                        <label>City/Municipal</label>
                        <input type="text" id="edit_city_municipal" name="city_municipal" autocomplete="address-level2">
                        <span class="error-msg" id="err-edit-city_municipal"></span>
                    </div>
                    <div class="input-box">
                        <label>Province</label>
                        <input type="text" id="edit_province" name="province" autocomplete="address-level1">
                        <span class="error-msg" id="err-edit-province"></span>
                    </div>
                    <div class="input-box">
                        <label>Country</label>
                        <input type="text" id="edit_country" name="country" value="Philippines" autocomplete="country-name">
                        <span class="error-msg" id="err-edit-country"></span>
                    </div>
                    <div class="input-box">
                        <label>Zipcode</label>
                        <input type="text" id="edit_zipcode" name="zipcode" autocomplete="postal-code">
                        <span class="error-msg" id="err-edit-zipcode"></span>
                    </div>
                </div>

                <div class="form-header">Security & Access</div>
                <div class="log-grid">
                    <div class="input-box">
                        <label>Role</label>
                        <select id="edit_role_id" name="role_id">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>">
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-box">
                        <label>New Password (Optional)</label>
                        <div class="password-input-group">
                            <input type="password" name="new_password" id="edit_password" placeholder="Leave blank to keep current" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePasswordVisibility('edit_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <button type="button" class="btn-modern btn-secondary" style="margin-top: 5px; padding: 4px 8px; font-size: 0.75rem; width: auto;" onclick="document.getElementById('edit_password').value = 'abcd1234'">
                            <i class="fas fa-key"></i> Set to abcd1234
                        </button>
                    </div>
                    <div class="input-box" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem; grid-column: span 2;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="is_active" id="edit_is_active" style="width: auto; margin: 0;">
                            <label for="edit_is_active" style="margin: 0; cursor: pointer;">Account is Active</label>
                        </div>
                    </div>
                </div>
            
            <?php if (!$is_super_admin): ?>
                <div class="input-box" style="border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1rem;">
                    <label>Reason for Edit Request <span style="color: #c53030;">(Required for Admin)</span></label>
                    <textarea name="edit_reason" rows="2" style="width: 100%; border: 1px solid var(--gray-300); border-radius: 4px; padding: 0.7rem;" placeholder="Why are you requesting this edit?"></textarea>
                </div>
            <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-modern btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="edit_user" class="btn-modern btn-primary"><?php echo $is_super_admin ? 'Update User' : 'Request Update'; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- View User Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header">
            <h3 style="color: var(--primary-dark); font-weight: 700;">User Profile Details</h3>
            <span class="close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewModalBody">
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
                <p style="margin-top: 1rem; color: var(--secondary);">Fetching user information...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modern btn-secondary" onclick="closeViewModal()">Close</button>
            <button type="button" class="btn-modern btn-primary" onclick="editFromView()">
                <i class="fas fa-edit"></i> Edit This User
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal (existing) -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 style="color: var(--danger); font-weight: 700;">Confirm Deletion</h3>
            <span class="close" onclick="hideDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 1rem;">Are you sure you want to delete <strong id="deleteUserName" style="color: var(--primary-dark);"></strong>?</p>
            <div style="background-color: #fff5f5; color: #c53030; padding: 1rem; border-radius: 6px; font-size: 0.85rem; border: 1px solid #feb2b2;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                This will permanently remove the user and all associated records. This action cannot be undone.
            </div>
        </div>
        <div class="modal-footer">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="button" class="btn-modern btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                <button type="submit" name="delete_user" class="btn-modern btn-primary" style="background-color: var(--danger);">Delete Now</button>
            </form>
        </div>
    </div>
</div>

<!-- Status Change Request Modal -->
<div id="statusRequestModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 style="color: var(--primary); font-weight: 700;">Request Status Change</h3>
            <span class="close" onclick="hideStatusRequestModal()">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <p style="margin-bottom: 1rem;">Requesting to <strong id="statusActionLabel"></strong> account for <strong id="statusUserName"></strong>.</p>
                <input type="hidden" name="user_id" id="statusUserId">
                <input type="hidden" name="new_status" id="statusNewValue">
                <div class="input-box">
                    <label>Reason for Request <span style="color: #c53030;">(Required)</span></label>
                    <textarea name="status_reason" rows="3" style="width: 100%; border: 1px solid var(--gray-300); border-radius: 4px; padding: 0.7rem;" placeholder="Please provide a reason for this status change..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modern btn-secondary" onclick="hideStatusRequestModal()">Cancel</button>
                <button type="submit" name="toggle_status" class="btn-modern btn-primary">Submit Request</button>
            </div>
        </form>
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
                const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
                setVal('edit_user_id', data.user_id);
                setVal('edit_username', data.username || '');
                setVal('edit_email', data.email || '');
                setVal('edit_id_number', data.id_number || '');
                setVal('edit_first_name', data.first_name || '');
                setVal('edit_middle_name', data.middle_name || '');
                setVal('edit_last_name', data.last_name || '');
                setVal('edit_extension_name', data.extension_name || '');
                setVal('edit_birthday', data.birthday || '');
                setVal('edit_age', data.age || '');
                
                let sexVal = (data.sex || '').toLowerCase();
                if (sexVal === 'male') setVal('edit_sex', 'Male');
                else if (sexVal === 'female') setVal('edit_sex', 'Female');
                else setVal('edit_sex', '');
                
                setVal('edit_contact_number', data.contact_number || '');
                setVal('edit_street_purok', data.street_purok || '');
                setVal('edit_barangay', data.barangay || '');
                setVal('edit_city_municipal', data.city_municipal || '');
                setVal('edit_province', data.province || '');
                setVal('edit_country', data.country || 'Philippines');
                setVal('edit_zipcode', data.zipcode || '');
                setVal('edit_role_id', data.role_id || '3');
                
                const activeChk = document.getElementById('edit_is_active');
                if (activeChk) activeChk.checked = data.is_active == 1;
                
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

    // Status Request Modal functions
    function showStatusRequestModal(userId, userName, newStatus) {
        document.getElementById('statusUserId').value = userId;
        document.getElementById('statusUserName').textContent = userName;
        document.getElementById('statusNewValue').value = newStatus;
        document.getElementById('statusActionLabel').textContent = newStatus ? 'Activate' : 'Deactivate';
        document.getElementById('statusRequestModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function hideStatusRequestModal() {
        document.getElementById('statusRequestModal').style.display = 'none';
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

    // Real-time duplicate checking for users.php
    function attachDuplicateCheckers() {
        const fieldsToCheck = [
            { name: 'username', addId: 'add_username', editId: 'edit_username', errAdd: 'err-username', errEdit: 'err-edit-username' },
            { name: 'email', addId: 'add_email', editId: 'edit_email', errAdd: 'err-email', errEdit: 'err-edit-email' },
            { name: 'id_number', addId: 'add_id_number', editId: 'edit_id_number', errAdd: 'err-id_number', errEdit: 'err-edit-id_number' },
            { name: 'contact_number', addId: 'add_contact_number', editId: 'edit_contact_number', errAdd: 'err-contact_number', errEdit: 'err-edit-contact_number' }
        ];
        
        fieldsToCheck.forEach(field => {
            const addInput = document.getElementById(field.addId);
            if (addInput) {
                addInput.addEventListener('blur', function() {
                    checkDuplicate(field.name, this.value, this, field.errAdd, 0);
                });
            }
            
            const editInput = document.getElementById(field.editId);
            if (editInput) {
                editInput.addEventListener('blur', function() {
                    const userId = document.getElementById('edit_user_id').value;
                    checkDuplicate(field.name, this.value, this, field.errEdit, userId);
                });
            }
        });
    }

    function checkDuplicate(fieldName, value, inputElement, errId, excludeId = 0) {
        if (!value.trim()) return;
        
        const formData = new FormData();
        formData.append('field', fieldName);
        formData.append('value', value);
        if (excludeId > 0) formData.append('exclude_id', excludeId);

        fetch('check_duplicate.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const errSpan = document.getElementById(errId);
            if (data.exists) {
                inputElement.classList.add('invalid');
                let displayLabel = fieldName.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                if (fieldName === 'id_number') displayLabel = 'ID Number';
                if (errSpan) {
                    errSpan.textContent = `${displayLabel} already exists in the system.`;
                    errSpan.style.display = 'block';
                }
                inputElement.dataset.exists = "true";
            } else {
                inputElement.dataset.exists = "false";
                if (errSpan && errSpan.textContent.includes('already exists in the system.')) {
                    inputElement.classList.remove('invalid');
                    errSpan.textContent = '';
                    errSpan.style.display = 'none';
                }
            }
        })
        .catch(err => console.error('Duplicate check error:', err));
    }

    // Password strength meter for add form
    document.addEventListener('DOMContentLoaded', function() {
        attachDuplicateCheckers();
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
        
        // Form validation and submission for add user
        const addForm = document.getElementById('addForm');
        if (addForm) {
            addForm.setAttribute('novalidate', 'true');
            addForm.addEventListener('submit', function(e) {
                e.preventDefault();
                let isValid = true;
                
                // Fields to validate (matching register.php requirements)
                const fields = [
                    { id: 'add_id_number', errId: 'err-id_number', name: 'ID Number', required: true },
                    { id: 'add_username', errId: 'err-username', name: 'Username', required: true },
                    { id: 'add_email', errId: 'err-email', name: 'Email Address', required: true },
                    { id: 'add_contact_number', errId: 'err-contact_number', name: 'Contact Number', required: true },
                    { id: 'add_first_name', errId: 'err-first_name', name: 'First Name', required: true },
                    { id: 'add_middle_name', errId: 'err-middle_name', name: 'Middle Name', required: false },
                    { id: 'add_last_name', errId: 'err-last_name', name: 'Last Name', required: true },
                    { id: 'add_extension_name', errId: 'err-extension_name', name: 'Extension Name', required: false },
                    { id: 'add_birthday', errId: 'err-birthday', name: 'Birthday', required: true },
                    { id: 'add_sex', errId: 'err-sex', name: 'Sex', required: true },
                    { id: 'add_street_purok', errId: 'err-street_purok', name: 'Purok/Street', required: true },
                    { id: 'add_barangay', errId: 'err-barangay', name: 'Barangay', required: true },
                    { id: 'add_city_municipal', errId: 'err-city_municipal', name: 'City/Municipal', required: true },
                    { id: 'add_province', errId: 'err-province', name: 'Province', required: true },
                    { id: 'add_country', errId: 'err-country', name: 'Country', required: true },
                    { id: 'add_zipcode', errId: 'err-zipcode', name: 'Zip Code', required: true },
                    { id: 'add_password', errId: 'err-password', name: 'Password', required: true }
                ];
                
                // Clear previous errors
                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const errSpan = document.getElementById(field.errId);
                    if (input) input.classList.remove('invalid');
                    if (errSpan) {
                        errSpan.textContent = '';
                        errSpan.style.display = 'none';
                    }
                });
                
                // Client-side validation (Synchronized with register.js)
                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const errSpan = document.getElementById(field.errId);
                    if (!input) return;
                    const value = input.value.trim();

                    if (field.required && !value) {
                        isValid = false;
                        input.classList.add('invalid');
                        if (errSpan) {
                            errSpan.textContent = `${field.name} is required.`;
                            errSpan.style.display = 'block';
                        }
                    } else if (input.dataset.exists === "true") {
                        isValid = false;
                        input.classList.add('invalid');
                        if (errSpan) {
                            errSpan.textContent = `${field.name} already exists.`;
                            errSpan.style.display = 'block';
                        }
                    } else if (value) {
                        // Detailed validations matching register.js
                        if (field.id === 'add_id_number') {
                            if (!/^\d{4}-\d{4}$/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'ID Number must be in the format xxxx-xxxx';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'add_username') {
                            if (value.length < 5 || value.length > 20) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Username must be between 5 and 20 characters';
                                    errSpan.style.display = 'block';
                                }
                            } else if (/[A-Z]/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'No capital letters allowed';
                                    errSpan.style.display = 'block';
                                }
                            } else if (/\s/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'No spaces allowed';
                                    errSpan.style.display = 'block';
                                }
                            } else if (!/^(?![0-9])/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Username cannot start with a number';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'add_email') {
                            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                            if (!emailRegex.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Please enter a valid email address.';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'add_contact_number') {
                            if (!/^(09\d{9}|(\+639)\d{9})$/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Please enter a valid contact number (09xxxxxxxxx).';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (['add_first_name', 'add_last_name', 'add_street_purok', 'add_barangay', 'add_city_municipal', 'add_province', 'add_country'].includes(field.id)) {
                            if (value.length < 2 || value.length > 30) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = `${field.name} must be between 2 and 30 characters.`;
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'add_zipcode') {
                            if (!/^\d{4}$/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Zip Code must be 4 digits.';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'add_birthday') {
                            const birthDate = new Date(value);
                            const today = new Date();
                            let age = today.getFullYear() - birthDate.getFullYear();
                            if (today.getMonth() < birthDate.getMonth() || (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) age--;
                            if (age < 18) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'User must be at least 18 years old.';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'add_password') {
                            if (value.length < 8 || value.length > 30) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Password must be between 8 and 30 characters';
                                    errSpan.style.display = 'block';
                                }
                            } else {
                                const uppercaseCount = (value.match(/[A-Z]/g) || []).length;
                                const lowercaseCount = (value.match(/[a-z]/g) || []).length;
                                const numberCount = (value.match(/[0-9]/g) || []).length;
                                const specialCount = (value.match(/[!@#$%^&*(),.?":{}|<>]/g) || []).length;
                                
                                if(uppercaseCount < 2) {
                                    isValid = false;
                                    input.classList.add('invalid');
                                    if (errSpan) { errSpan.textContent = 'At least two (2) uppercase letters required'; errSpan.style.display = 'block'; }
                                } else if(lowercaseCount < 2) {
                                    isValid = false;
                                    input.classList.add('invalid');
                                    if (errSpan) { errSpan.textContent = 'At least two (2) lowercase letters required'; errSpan.style.display = 'block'; }
                                } else if(numberCount < 2) {
                                    isValid = false;
                                    input.classList.add('invalid');
                                    if (errSpan) { errSpan.textContent = 'At least two (2) numbers required'; errSpan.style.display = 'block'; }
                                } else if(specialCount < 2) {
                                    isValid = false;
                                    input.classList.add('invalid');
                                    if (errSpan) { errSpan.textContent = 'At least two (2) special characters required'; errSpan.style.display = 'block'; }
                                }
                            }
                        }
                    }
                });
                
                if (isValid) {
                    const formData = new FormData(addForm);
                    formData.append('add_user', '1');
                    
                    fetch('users.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            if (data.errors) {
                                for (const field in data.errors) {
                                    const errSpan = document.getElementById(`err-${field}`);
                                    const input = document.getElementById(`add_${field}`);
                                    if (errSpan) {
                                        errSpan.textContent = data.errors[field];
                                        errSpan.style.display = 'block';
                                    }
                                    if (input) input.classList.add('invalid');
                                }
                                const firstError = addForm.querySelector('.invalid');
                                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            } else if (data.message) {
                                alert(data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred. Please try again.');
                    });
                } else {
                    const firstInvalid = addForm.querySelector('.invalid');
                    if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }

        // Form validation and submission for edit user
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.setAttribute('novalidate', 'true');
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                let isValid = true;
                
                const fields = [
                    { id: 'edit_id_number', errId: 'err-edit-id_number', name: 'ID Number', required: true },
                    { id: 'edit_username', errId: 'err-edit-username', name: 'Username', required: true },
                    { id: 'edit_email', errId: 'err-edit-email', name: 'Email Address', required: true },
                    { id: 'edit_contact_number', errId: 'err-edit-contact_number', name: 'Contact Number', required: true },
                    { id: 'edit_first_name', errId: 'err-edit-first_name', name: 'First Name', required: true },
                    { id: 'edit_middle_name', errId: 'err-edit-middle_name', name: 'Middle Name', required: false },
                    { id: 'edit_last_name', errId: 'err-edit-last_name', name: 'Last Name', required: true },
                    { id: 'edit_extension_name', errId: 'err-edit-extension_name', name: 'Extension Name', required: false },
                    { id: 'edit_birthday', errId: 'err-edit-birthday', name: 'Birthday', required: true },
                    { id: 'edit_sex', errId: 'err-edit-sex', name: 'Sex', required: true },
                    { id: 'edit_street_purok', errId: 'err-edit-street_purok', name: 'Street/Purok', required: true },
                    { id: 'edit_barangay', errId: 'err-edit-barangay', name: 'Barangay', required: true },
                    { id: 'edit_city_municipal', errId: 'err-edit-city_municipal', name: 'City/Municipal', required: true },
                    { id: 'edit_province', errId: 'err-edit-province', name: 'Province', required: true },
                    { id: 'edit_country', errId: 'err-edit-country', name: 'Country', required: true },
                    { id: 'edit_zipcode', errId: 'err-edit-zipcode', name: 'Zipcode', required: true }
                ];
                
                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const errSpan = document.getElementById(field.errId);
                    if (input) input.classList.remove('invalid');
                    if (errSpan) {
                        errSpan.textContent = '';
                        errSpan.style.display = 'none';
                    }
                });
                
                // Client-side validation (Synchronized with register.js)
                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const errSpan = document.getElementById(field.errId);
                    if (!input) return;
                    const value = input.value.trim();

                    if (field.required && !value) {
                        isValid = false;
                        input.classList.add('invalid');
                        if (errSpan) {
                            errSpan.textContent = `${field.name} is required.`;
                            errSpan.style.display = 'block';
                        }
                    } else if (input.dataset.exists === "true") {
                        isValid = false;
                        input.classList.add('invalid');
                        if (errSpan) {
                            errSpan.textContent = `${field.name} already exists.`;
                            errSpan.style.display = 'block';
                        }
                    } else if (value) {
                        // Detailed validations matching register.js
                        if (field.id === 'edit_id_number') {
                            if (!/^\d{4}-\d{4}$/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'ID Number must be in the format xxxx-xxxx';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'edit_username') {
                            if (value.length < 5 || value.length > 20) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Username must be between 5 and 20 characters';
                                    errSpan.style.display = 'block';
                                }
                            } else if (/[A-Z]/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'No capital letters allowed';
                                    errSpan.style.display = 'block';
                                }
                            } else if (/\s/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'No spaces allowed';
                                    errSpan.style.display = 'block';
                                }
                            } else if (!/^(?![0-9])/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Username cannot start with a number';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'edit_email') {
                            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                            if (!emailRegex.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Please enter a valid email address.';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'edit_contact_number') {
                            if (!/^(09\d{9}|(\+639)\d{9})$/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Please enter a valid contact number (09xxxxxxxxx).';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (['edit_first_name', 'edit_last_name', 'edit_street_purok', 'edit_barangay', 'edit_city_municipal', 'edit_province', 'edit_country'].includes(field.id)) {
                            if (value.length < 2 || value.length > 30) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = `${field.name} must be between 2 and 30 characters.`;
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'edit_zipcode') {
                            if (!/^\d{4}$/.test(value)) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'Zip Code must be 4 digits.';
                                    errSpan.style.display = 'block';
                                }
                            }
                        } else if (field.id === 'edit_birthday') {
                            const birthDate = new Date(value);
                            const today = new Date();
                            let age = today.getFullYear() - birthDate.getFullYear();
                            if (today.getMonth() < birthDate.getMonth() || (today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) age--;
                            if (age < 18) {
                                isValid = false;
                                input.classList.add('invalid');
                                if (errSpan) {
                                    errSpan.textContent = 'User must be at least 18 years old.';
                                    errSpan.style.display = 'block';
                                }
                            }
                        }
                    }
                });
                
                if (isValid) {
                    const formData = new FormData(editForm);
                    formData.append('edit_user', '1');
                    
                    fetch('users.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            if (data.errors) {
                                for (const field in data.errors) {
                                    const errSpan = document.getElementById(`err-edit-${field}`);
                                    const input = document.getElementById(`edit_${field}`);
                                    if (errSpan) {
                                        errSpan.textContent = data.errors[field];
                                        errSpan.style.display = 'block';
                                    }
                                    if (input) input.classList.add('invalid');
                                }
                                const firstError = editForm.querySelector('.invalid');
                                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            } else if (data.message) {
                                alert(data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred. Please try again.');
                    });
                } else {
                    const firstInvalid = editForm.querySelector('.invalid');
                    if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        const statusModal = document.getElementById('statusRequestModal');
        
        if (event.target == addModal) closeAddModal();
        if (event.target == editModal) closeEditModal();
        if (event.target == viewModal) closeViewModal();
        if (event.target == deleteModal) hideDeleteModal();
        if (event.target == statusModal) hideStatusRequestModal();
    }

    // Handle ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAddModal();
            closeEditModal();
            closeViewModal();
            hideDeleteModal();
            hideStatusRequestModal();
            
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

    // Deletion Request functions
    function showRequestDeletionModal(userId, userName) {
        document.getElementById('requestDeleteUserId').value = userId;
        document.getElementById('requestDeleteUserName').textContent = userName;
        document.getElementById('requestDeletionModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function hideRequestDeletionModal() {
        document.getElementById('requestDeletionModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function openReviewModal(req, action) {
        document.getElementById('reviewRequestId').value = req.request_id;
        document.getElementById('reviewTargetUserId').value = req.user_id;
        
        const details = document.getElementById('reviewRequestDetails');
        details.innerHTML = `
            <p style="margin-bottom: 8px;"><strong>Target User:</strong> ${req.target_username} (${req.target_fname} ${req.target_lname})</p>
            <p style="margin-bottom: 8px;"><strong>Requested By:</strong> ${req.requester_username}</p>
            <p style="margin-bottom: 8px;"><strong>Reason:</strong> ${req.reason}</p>
            <p><strong>Date:</strong> ${new Date(req.created_at).toLocaleString()}</p>
        `;
        
        const submitBtn = document.getElementById('reviewSubmitBtn');
        const title = document.getElementById('reviewModalTitle');
        
        if (action === 'approve') {
            title.textContent = 'Approve Deletion Request';
            submitBtn.name = 'approve_deletion';
            submitBtn.style.backgroundColor = '#dc3545';
            submitBtn.style.color = 'white';
            submitBtn.textContent = 'Confirm Approval & Delete';
        } else {
            title.textContent = 'Deny Deletion Request';
            submitBtn.name = 'deny_deletion';
            submitBtn.style.backgroundColor = '#6c757d';
            submitBtn.style.color = 'white';
            submitBtn.textContent = 'Confirm Denial';
        }
        
        document.getElementById('reviewRequestModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function hideReviewRequestModal() {
        document.getElementById('reviewRequestModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Edit Request Modals
    function openReviewEditModal(req, action) {
        document.getElementById('reviewEditRequestId').value = req.request_id;
        document.getElementById('reviewEditTargetUserId').value = req.user_id;
        
        const details = document.getElementById('reviewEditRequestDetails');
        let requestedData = {};
        try {
            requestedData = JSON.parse(req.requested_data);
        } catch(e) {}
        
        details.innerHTML = `
            <p style="margin-bottom: 8px;"><strong>Target User:</strong> ${req.target_username} (${req.target_fname} ${req.target_lname})</p>
            <p style="margin-bottom: 8px;"><strong>Requested By:</strong> ${req.requester_username}</p>
            <p style="margin-bottom: 8px;"><strong>Reason:</strong> ${req.reason}</p>
            <p><strong>Date:</strong> ${new Date(req.created_at).toLocaleString()}</p>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--gray-300);">
                <strong>Changes Requested:</strong><br>
                <div style="max-height: 150px; overflow-y: auto; background: #fff; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 0.8rem; border: 1px solid var(--gray-200);">
                    ${Object.keys(requestedData).map(k => {
                        const val = requestedData[k];
                        if (k === 'new_password' && val) return `<b>password:</b> [NEW PASSWORD REQUESTED]`;
                        return `<b>${k}:</b> ${val !== '' && val !== null ? val : '<i>(empty)</i>'}`;
                    }).join('<br>')}
                </div>
            </div>
        `;
        
        const submitBtn = document.getElementById('reviewEditSubmitBtn');
        const title = document.getElementById('reviewEditModalTitle');
        
        if (action === 'approve') {
            title.textContent = 'Approve Edit Request';
            submitBtn.name = 'approve_edit';
            submitBtn.style.backgroundColor = 'var(--success)';
            submitBtn.style.color = 'white';
            submitBtn.textContent = 'Confirm Approval & Apply';
        } else {
            title.textContent = 'Deny Edit Request';
            submitBtn.name = 'deny_edit';
            submitBtn.style.backgroundColor = '#6c757d';
            submitBtn.style.color = 'white';
            submitBtn.textContent = 'Confirm Denial';
        }
        
        document.getElementById('reviewEditRequestModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function hideReviewEditModal() {
        document.getElementById('reviewEditRequestModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function openViewEditRequestModal(req) {
        let requestedData = {};
        try {
            requestedData = JSON.parse(req.requested_data);
        } catch(e) {}
        
        const details = document.getElementById('viewEditRequestDetails');
        details.innerHTML = `
            <div style="margin-bottom: 1rem;">
                <p><strong>Target User:</strong> ${req.target_username}</p>
                <p><strong>Requested By:</strong> ${req.requester_username}</p>
                <p><strong>Reason:</strong> ${req.reason}</p>
            </div>
            <div style="background: var(--gray-50); padding: 1rem; border-radius: 8px; border: 1px solid var(--gray-200);">
                <h4 style="margin-top: 0; margin-bottom: 0.5rem; color: var(--primary-dark);">Requested Data Changes</h4>
                <div style="max-height: 300px; overflow-y: auto; background: #fff; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 0.85rem; border: 1px solid var(--gray-200);">
                    ${Object.keys(requestedData).map(k => {
                        const val = requestedData[k];
                        if (k === 'new_password' && val) return `<b>password:</b> [NEW PASSWORD REQUESTED]`;
                        return `<b>${k}:</b> ${val !== '' && val !== null ? val : '<i>(empty)</i>'}`;
                    }).join('<br>')}
                </div>
            </div>
        `;
        document.getElementById('viewEditRequestModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function hideViewEditRequestModal() {
        document.getElementById('viewEditRequestModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
</script>

<!-- Request Deletion Modal -->
<div id="requestDeletionModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 style="color: var(--primary-dark); font-weight: 700;">Request User Deletion</h3>
            <span class="close" onclick="hideRequestDeletionModal()">&times;</span>
        </div>
        <form action="" method="POST">
            <div class="modal-body">
                <input type="hidden" name="user_id" id="requestDeleteUserId">
                <p style="margin-bottom: 1rem;">Are you sure you want to request deletion for <strong id="requestDeleteUserName" style="color: var(--danger);"></strong>?</p>
                <div class="form-group">
                    <label style="font-weight: 600; color: var(--primary-dark); display: block; margin-bottom: 0.5rem;">Reason for deletion request:</label>
                    <textarea name="reason" rows="4" style="width: 100%; border: 1px solid var(--gray-300); border-radius: 6px; padding: 0.75rem; font-family: inherit;" required placeholder="Please provide a detailed reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modern btn-secondary" onclick="hideRequestDeletionModal()">Cancel</button>
                <button type="submit" name="request_deletion" class="btn-modern btn-primary" style="background-color: var(--danger);">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Review Deletion Request Modal -->
<div id="reviewRequestModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="reviewModalTitle" style="color: var(--primary-dark); font-weight: 700;">Review Deletion Request</h3>
            <span class="close" onclick="hideReviewRequestModal()">&times;</span>
        </div>
        <form action="" method="POST" id="reviewRequestForm">
            <div class="modal-body">
                <input type="hidden" name="request_id" id="reviewRequestId">
                <input type="hidden" name="user_id" id="reviewTargetUserId">
                <div id="reviewRequestDetails" style="background: var(--gray-50); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid var(--gray-200); font-size: 0.9rem; color: var(--primary-dark); line-height: 1.6;">
                    <!-- Details will be injected here -->
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; color: var(--primary-dark); display: block; margin-bottom: 0.5rem;">Review Notes (optional):</label>
                    <textarea name="review_notes" rows="3" style="width: 100%; border: 1px solid var(--gray-300); border-radius: 6px; padding: 0.75rem; font-family: inherit;" placeholder="Add any notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modern btn-secondary" onclick="hideReviewRequestModal()">Cancel</button>
                <button type="submit" id="reviewSubmitBtn" name="approve_deletion" class="btn-modern btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Review Edit Request Modal -->
<div id="reviewEditRequestModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="reviewEditModalTitle" style="color: var(--primary-dark); font-weight: 700;">Review Edit Request</h3>
            <span class="close" onclick="hideReviewEditModal()">&times;</span>
        </div>
        <form action="" method="POST">
            <div class="modal-body">
                <input type="hidden" name="request_id" id="reviewEditRequestId">
                <input type="hidden" name="user_id" id="reviewEditTargetUserId">
                <div id="reviewEditRequestDetails" style="background: var(--gray-50); padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid var(--gray-200); font-size: 0.9rem; color: var(--primary-dark); line-height: 1.6;">
                    <!-- Details will be injected here -->
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; color: var(--primary-dark); display: block; margin-bottom: 0.5rem;">Review Notes (optional):</label>
                    <textarea name="review_notes" rows="3" style="width: 100%; border: 1px solid var(--gray-300); border-radius: 6px; padding: 0.75rem; font-family: inherit;" placeholder="Add any notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modern btn-secondary" onclick="hideReviewEditModal()">Cancel</button>
                <button type="submit" id="reviewEditSubmitBtn" name="approve_edit" class="btn-modern btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- View Edit Request Modal -->
<div id="viewEditRequestModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 style="color: var(--primary-dark); font-weight: 700;">View Requested Changes</h3>
            <span class="close" onclick="hideViewEditRequestModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewEditRequestDetails" style="font-size: 0.95rem; color: var(--primary-dark); line-height: 1.6;">
            <!-- Details will be injected here -->
        </div>
        <div class="modal-footer" style="border-top: none;">
            <button type="button" class="btn-modern btn-secondary" onclick="hideViewEditRequestModal()">Close</button>
        </div>
    </div>
</div>
</body>
</html> 