<?php
// security_questions.php - Manage security questions
session_start();
require_once __DIR__ . '/../php/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin/login.php");
    exit();
}

// Check if user is Admin or Super Admin (role_id 1 or 2)
if (!in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];
$is_super_admin = ($role_id == 1);
$permissions = $_SESSION['permissions'] ?? [];

// If not super admin, check for specific permission
if (!$is_super_admin && !in_array('manage_questions', $permissions) && !in_array('all', $permissions)) {
    header("Location: dashboard.php");
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        $question_text = trim($_POST['question_text'] ?? '');
        $is_active = isset($_POST['is_active']) ? 'true' : 'false';

        if (empty($question_text)) {
            $error = "Question text is required.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO security_questions (question_text, is_active, created_at) VALUES (:text, :active, NOW())");
                $stmt->execute([':text' => $question_text, ':active' => $is_active]);
                
                // Log action
                $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, new_data, ip_address, created_at) 
                                         VALUES (:admin_id, 'INSERT', 'security_questions', :record_id, :new_data, :ip, NOW())");
                $log_stmt->execute([
                    ':admin_id' => $user_id,
                    ':record_id' => $conn->lastInsertId(),
                    ':new_data' => json_encode(['question_text' => $question_text, 'is_active' => $is_active]),
                    ':ip' => $_SERVER['REMOTE_ADDR']
                ]);

                $message = "Security question added successfully.";
            } catch (PDOException $e) {
                $error = "Error adding question: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['edit_question'])) {
        $id = (int)$_POST['question_id'];
        $question_text = trim($_POST['question_text'] ?? '');
        $is_active = isset($_POST['is_active']) ? 'true' : 'false';

        if (empty($question_text)) {
            $error = "Question text is required.";
        } else {
            try {
                // Get old data for log
                $old_stmt = $conn->prepare("SELECT * FROM security_questions WHERE question_id = :id");
                $old_stmt->execute([':id' => $id]);
                $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $conn->prepare("UPDATE security_questions SET question_text = :text, is_active = :active WHERE question_id = :id");
                $stmt->execute([':text' => $question_text, ':active' => $is_active, ':id' => $id]);

                // Log action
                $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, new_data, ip_address, created_at) 
                                         VALUES (:admin_id, 'UPDATE', 'security_questions', :record_id, :old_data, :new_data, :ip, NOW())");
                $log_stmt->execute([
                    ':admin_id' => $user_id,
                    ':record_id' => $id,
                    ':old_data' => json_encode($old_data),
                    ':new_data' => json_encode(['question_text' => $question_text, 'is_active' => $is_active]),
                    ':ip' => $_SERVER['REMOTE_ADDR']
                ]);

                $message = "Security question updated successfully.";
            } catch (PDOException $e) {
                $error = "Error updating question: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['question_id'];
        $new_status = $_POST['new_status'] === '1' ? 'true' : 'false';

        try {
            $stmt = $conn->prepare("UPDATE security_questions SET is_active = :active WHERE question_id = :id");
            $stmt->execute([':active' => $new_status, ':id' => $id]);
            
            // Log action
            $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, new_data, ip_address, created_at) 
                                     VALUES (:admin_id, 'TOGGLE_STATUS', 'security_questions', :record_id, :old_data, :new_data, :ip, NOW())");
            $log_stmt->execute([
                ':admin_id' => $user_id,
                ':record_id' => $id,
                ':old_data' => json_encode(['is_active' => $new_status === 'true' ? 'false' : 'true']),
                ':new_data' => json_encode(['is_active' => $new_status]),
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);

            $message = "Status updated successfully.";
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }

    if (isset($_POST['delete_question'])) {
        $id = (int)$_POST['question_id'];

        try {
            // Check if question is in use
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM user_security_answers WHERE question_id = :id");
            $check_stmt->execute([':id' => $id]);
            if ($check_stmt->fetchColumn() > 0) {
                $error = "Cannot delete question. It is currently being used by users.";
            } else {
                // Get old data for log
                $old_stmt = $conn->prepare("SELECT * FROM security_questions WHERE question_id = :id");
                $old_stmt->execute([':id' => $id]);
                $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $conn->prepare("DELETE FROM security_questions WHERE question_id = :id");
                $stmt->execute([':id' => $id]);

                // Log action
                $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action_type, table_name, record_id, old_data, ip_address, created_at) 
                                         VALUES (:admin_id, 'DELETE', 'security_questions', :record_id, :old_data, :ip, NOW())");
                $log_stmt->execute([
                    ':admin_id' => $user_id,
                    ':record_id' => $id,
                    ':old_data' => json_encode($old_data),
                    ':ip' => $_SERVER['REMOTE_ADDR']
                ]);

                $message = "Security question deleted successfully.";
            }
        } catch (PDOException $e) {
            $error = "Error deleting question: " . $e->getMessage();
        }
    }
}

// Build query
$conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $conditions[] = "question_text ILIKE :search";
    $params[':search'] = "%$search%";
}

if ($status_filter !== 'all') {
    $conditions[] = "is_active = " . ($status_filter === 'active' ? 'true' : 'false');
}

$where = implode(" AND ", $conditions);

// Count total
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM security_questions WHERE $where");
$count_stmt->execute($params);
$total_questions = $count_stmt->fetchColumn();
$total_pages = ceil($total_questions / $limit);

// Fetch questions
$stmt = $conn->prepare("SELECT * FROM security_questions WHERE $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $val) $stmt->bindValue($key, $val);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current admin info for sidebar
$current_admin_query = "SELECT u.first_name, u.last_name, u.username, r.role_name as role 
                       FROM users u JOIN roles r ON u.role_id = r.role_id 
                       WHERE u.user_id = :user_id";
$current_admin_stmt = $conn->prepare($current_admin_query);
$current_admin_stmt->execute([':user_id' => $user_id]);
$current_admin = $current_admin_stmt->fetch(PDO::FETCH_ASSOC);
$initials = strtoupper(substr($current_admin['first_name'], 0, 1) . substr($current_admin['last_name'], 0, 1));

$sidebar_closed = isset($_COOKIE['sidebar_closed']) ? $_COOKIE['sidebar_closed'] : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Questions - Plants. System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        .container { padding: 1.2rem 1.5rem; max-width: 1400px; margin: 0 auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-header h1 { color: #1c4c29; font-size: 13px; font-weight: 600; border-left: 8px solid #509c5b; padding-left: 1rem; }
        .btn { padding: 0.6rem 1.2rem; font-size: 13px; border-radius: 2px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; transition: all 0.2s; }
        .btn-primary { background: #2a6e3b; color: white; }
        .btn-primary:hover { background: #1d542b; }
        .btn-secondary { background: #fff; border: 1px solid #589065; color: #1b572b; }
        .filters-section { background: #fafff9; padding: 1rem; border: 1px solid #cbe6bf; border-radius: 5px; margin-bottom: 1.5rem; }
        .filters-form { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 0.3rem; color: #1d4d2d; font-size: 11px; font-weight: 600; }
        .filter-group input, .filter-group select { width: 100%; padding: 0.6rem; border: 1px solid #afcfaa; border-radius: 2px; font-size: 13px; }
        .table-container { background: #fafff9; padding: 1rem; border: 1px solid #cbe6bf; border-radius: 5px; overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { padding: 0.8rem; text-align: left; background: #f2f7f0; color: #1d4d2d; border-bottom: 2px solid #cbe6bf; }
        .data-table td { padding: 0.8rem; border-bottom: 1px solid #d8e8d0; }
        .badge { padding: 0.2rem 0.6rem; font-size: 11px; font-weight: 600; border-radius: 2px; }
        .badge-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .action-btn { padding: 0.3rem 0.6rem; font-size: 11px; border: 1px solid #ddd; border-radius: 2px; cursor: pointer; background: #fff; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: #fff; padding: 1.5rem; border-radius: 5px; width: 100%; max-width: 500px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-size: 12px; font-weight: 600; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 2px; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 2px; font-size: 13px; }
        .message-success { background: #d4edda; color: #155724; }
        .message-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileMenu()"><i class="fas fa-bars"></i></button>
    <div class="sidebar-toggle <?php echo $sidebar_closed === 'true' ? 'closed' : ''; ?>" id="sidebarToggle" onclick="toggleSidebar()"><i class="fas fa-chevron-left"></i></div>

    <aside class="sidebar <?php echo $sidebar_closed === 'true' ? 'closed' : ''; ?>" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="logo">Plants.<span>.🌿</span></a>
            <div class="user-profile">
                <div class="user-avatar"><?php echo $initials; ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($current_admin['first_name']); ?></div>
                    <div class="user-role"><span class="role-badge"><?php echo ucfirst(str_replace('_', ' ', $current_admin['role'])); ?></span></div>
                </div>
            </div>
        </div>
        <div class="nav-menu">
            <ul>
                <li class="nav-item"><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                <li class="nav-item"><a href="users.php"><i class="fas fa-users"></i><span>Users</span></a></li>
                <?php if ($is_super_admin || in_array('manage_questions', $permissions) || in_array('all', $permissions)): ?>
                <li class="nav-item active"><a href="security_questions.php"><i class="fas fa-shield-alt"></i><span>Security Questions</span></a></li>
                <?php endif; ?>
                <li class="nav-item"><a href="logs.php"><i class="fas fa-history"></i><span>Activity Logs</span></a></li>
                <li class="nav-divider"></li>
                <li class="nav-header">Administration</li>
                <li class="nav-item"><a href="user_management.php"><i class="fas fa-users-cog"></i><span>Admin Management</span></a></li>
            </ul>
        </div>
    </aside>

    <main class="main-content <?php echo $sidebar_closed === 'true' ? 'expanded' : ''; ?>" id="mainContent">
        <div class="container">
            <div class="page-header">
                <h1>Security Questions Management</h1>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Question</button>
            </div>

            <?php if ($message): ?><div class="message message-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="message message-error"><?php echo $error; ?></div><?php endif; ?>

            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label>Search Question</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search text...">
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        <a href="security_questions.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Question Text</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $q): ?>
                        <tr>
                            <td>#<?php echo $q['question_id']; ?></td>
                            <td><?php echo htmlspecialchars($q['question_text']); ?></td>
                            <td>
                                <span class="badge <?php echo $q['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $q['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($q['created_at'])); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button class="action-btn" onclick='openEditModal(<?php echo json_encode($q); ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to toggle status?')">
                                        <input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $q['is_active'] ? '0' : '1'; ?>">
                                        <button type="submit" name="toggle_status" class="action-btn" title="Toggle Status">
                                            <i class="fas <?php echo $q['is_active'] ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this question?')">
                                        <input type="hidden" name="question_id" value="<?php echo $q['question_id']; ?>">
                                        <button type="submit" name="delete_question" class="action-btn" style="color: #dc3545;"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($questions)): ?>
                        <tr><td colspan="5" style="text-align:center;">No security questions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div id="questionModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Add Security Question</h3>
            <form method="POST">
                <input type="hidden" name="question_id" id="modal_question_id">
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question_text" id="modal_question_text" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="modal_is_active" checked> Active
                    </label>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="add_question" id="modalSubmitBtn" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/sidebar.js"></script>
    <script>
        const modal = document.getElementById('questionModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalSubmitBtn = document.getElementById('modalSubmitBtn');
        
        function openAddModal() {
            modalTitle.innerText = "Add Security Question";
            modalSubmitBtn.name = "add_question";
            document.getElementById('modal_question_id').value = "";
            document.getElementById('modal_question_text').value = "";
            document.getElementById('modal_is_active').checked = true;
            modal.style.display = 'flex';
        }

        function openEditModal(question) {
            modalTitle.innerText = "Edit Security Question";
            modalSubmitBtn.name = "edit_question";
            document.getElementById('modal_question_id').value = question.question_id;
            document.getElementById('modal_question_text').value = question.question_text;
            document.getElementById('modal_is_active').checked = question.is_active;
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>
