<?php
// scratch/list_all_users_and_roles.php
require_once __DIR__ . '/../php/db_connection.php';

try {
    // 1. Fetch all roles
    $stmtRoles = $conn->query("SELECT role_id, role_name, role_description, is_active FROM public.roles ORDER BY role_id");
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch all users joined with their roles
    $stmtUsers = $conn->query("
        SELECT 
            u.user_id, 
            u.username, 
            u.email, 
            u.id_number, 
            u.first_name, 
            u.last_name, 
            u.role_id, 
            r.role_name, 
            u.is_active, 
            u.created_at
        FROM public.users u
        LEFT JOIN public.roles r ON u.role_id = r.role_id
        ORDER BY u.role_id, u.user_id
    ");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // Build markdown content
    $md = "# Database Users and Roles Account\n\n";
    $md .= "Generated on: " . date('Y-m-d H:i:s T') . "\n\n";

    $md .= "## Active Roles\n\n";
    $md .= "| Role ID | Role Name | Description | Status |\n";
    $md .= "|---|---|---|---|\n";
    foreach ($roles as $role) {
        $status = $role['is_active'] ? 'Active' : 'Inactive';
        $md .= "| {$role['role_id']} | {$role['role_name']} | {$role['role_description']} | {$status} |\n";
    }
    $md .= "\n";

    $md .= "## User Accounts\n\n";
    $md .= "| User ID | ID Number | Username | Name | Email | Role | Status | Created At |\n";
    $md .= "|---|---|---|---|---|---|---|---|\n";
    foreach ($users as $user) {
        $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
        $status = $user['is_active'] ? 'Active' : 'Inactive';
        $md .= "| {$user['user_id']} | {$user['id_number']} | {$user['username']} | {$fullName} | {$user['email']} | {$user['role_name']} | {$status} | {$user['created_at']} |\n";
    }

    // Write to a local markdown file in the scratch directory
    $outputFile = __DIR__ . '/users_and_roles.md';
    file_put_contents($outputFile, $md);

    echo "<h1>Database Audit Completed!</h1>";
    echo "<p>Found " . count($roles) . " roles and " . count($users) . " users.</p>";
    echo "<p>Successfully wrote results to <code>scratch/users_and_roles.md</code>.</p>";
    echo "<h2>Roles:</h2><pre>" . print_r($roles, true) . "</pre>";
    echo "<h2>Users:</h2><pre>" . print_r($users, true) . "</pre>";

} catch (Exception $e) {
    echo "<h1>Error running audit:</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
