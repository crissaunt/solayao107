<?php
// scratch/automatic_permissions_setup.php
require_once __DIR__ . '/../php/db_connection.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Enforcing Default Permissions System</h1>";

    // 1. Update existing Super Admin and Admin records that have empty or null permissions
    echo "<h3>Step 1: Migrating existing user permissions...</h3>";

    // Set Super Admin permissions (role_id = 1)
    $superAdminPerms = '["all", "manage_users", "view_logs", "manage_admins", "system_settings", "manage_questions"]';
    $stmt1 = $conn->prepare("
        UPDATE public.users 
        SET permissions = :perms 
        WHERE role_id = 1 AND (permissions IS NULL OR permissions = '[]'::jsonb OR permissions = '\"[]\"'::jsonb)
    ");
    $stmt1->execute([':perms' => $superAdminPerms]);
    $superUpdated = $stmt1->rowCount();
    echo "<p>Updated <strong>{$superUpdated}</strong> existing Super Admin accounts with default permissions.</p>";

    // Set Admin permissions (role_id = 2)
    $adminPerms = '["manage_users", "view_logs"]';
    $stmt2 = $conn->prepare("
        UPDATE public.users 
        SET permissions = :perms 
        WHERE role_id = 2 AND (permissions IS NULL OR permissions = '[]'::jsonb OR permissions = '\"[]\"'::jsonb)
    ");
    $stmt2->execute([':perms' => $adminPerms]);
    $adminUpdated = $stmt2->rowCount();
    echo "<p>Updated <strong>{$adminUpdated}</strong> existing Admin accounts with default permissions.</p>";


    // 2. Create PostgreSQL Function and Trigger for Automatic Database-Level Enforcement
    echo "<h3>Step 2: Installing PostgreSQL database-level automatic trigger...</h3>";

    // Create or replace function
    $functionSql = "
    CREATE OR REPLACE FUNCTION public.set_default_user_permissions()
    RETURNS TRIGGER AS $$
    BEGIN
        -- On INSERT or when permissions are explicitly set to NULL/empty/blank array
        IF NEW.permissions IS NULL OR NEW.permissions = '[]'::jsonb OR NEW.permissions = '\"[]\"'::jsonb OR NEW.permissions = '\"\"'::jsonb THEN
            IF NEW.role_id = 1 THEN
                NEW.permissions := '[\"all\", \"manage_users\", \"view_logs\", \"manage_admins\", \"system_settings\", \"manage_questions\"]'::jsonb;
            ELSIF NEW.role_id = 2 THEN
                NEW.permissions := '[\"manage_users\", \"view_logs\"]'::jsonb;
            ELSE
                NEW.permissions := '[]'::jsonb;
            END IF;
        END IF;
        
        -- On UPDATE, if the role_id is changed and permissions is equal to either NULL, empty, or the defaults of the old role,
        -- we automatically update the permissions to the defaults of the new role.
        IF TG_OP = 'UPDATE' AND OLD.role_id IS DISTINCT FROM NEW.role_id THEN
            IF OLD.permissions IS NULL OR OLD.permissions = '[]'::jsonb OR OLD.permissions = '\"[]\"'::jsonb OR
               (OLD.role_id = 1 AND OLD.permissions = '[\"all\", \"manage_users\", \"view_logs\", \"manage_admins\", \"system_settings\", \"manage_questions\"]'::jsonb) OR
               (OLD.role_id = 2 AND OLD.permissions = '[\"manage_users\", \"view_logs\"]'::jsonb) THEN
                
                IF NEW.role_id = 1 THEN
                    NEW.permissions := '[\"all\", \"manage_users\", \"view_logs\", \"manage_admins\", \"system_settings\", \"manage_questions\"]'::jsonb;
                ELSIF NEW.role_id = 2 THEN
                    NEW.permissions := '[\"manage_users\", \"view_logs\"]'::jsonb;
                ELSE
                    NEW.permissions := '[]'::jsonb;
                END IF;
            END IF;
        END IF;
        
        RETURN NEW;
    END;
    $$ LANGUAGE plpgsql;
    ";
    
    $conn->exec($functionSql);
    echo "<p>🟢 PostgreSQL Trigger function <code>set_default_user_permissions()</code> created successfully.</p>";

    // Create the trigger
    $triggerSql = "
    DROP TRIGGER IF EXISTS trigger_set_default_user_permissions ON public.users;
    CREATE TRIGGER trigger_set_default_user_permissions
        BEFORE INSERT OR UPDATE ON public.users
        FOR EACH ROW
        EXECUTE FUNCTION public.set_default_user_permissions();
    ";
    
    $conn->exec($triggerSql);
    echo "<p>🟢 PostgreSQL Trigger <code>trigger_set_default_user_permissions</code> installed successfully on table <code>public.users</code>.</p>";


    // 3. Verify users and permissions
    echo "<h3>Step 3: Verification Audit</h3>";
    $auditStmt = $conn->query("
        SELECT user_id, username, role_id, permissions 
        FROM public.users 
        WHERE role_id IN (1, 2)
        ORDER BY role_id, user_id
    ");
    $admins = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; border-color: #ddd;'>";
    echo "<tr style='background: #f4f4f4;'><th>User ID</th><th>Username</th><th>Role ID</th><th>Assigned Permissions (JSON)</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td>{$admin['user_id']}</td>";
        echo "<td><strong>@{$admin['username']}</strong></td>";
        echo "<td>" . ($admin['role_id'] == 1 ? '1 (Super Admin)' : '2 (Admin)') . "</td>";
        echo "<td><code>" . htmlspecialchars($admin['permissions']) . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p style='color: green; font-weight: bold; margin-top: 1.5rem;'>🎉 Permissions successfully enforced! Any new Super Admin or Admin account created hereafter will automatically receive correct default permissions.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error enforcing permissions system:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
