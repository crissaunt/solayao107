<?php
require __DIR__ . '/php/db_connection.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check that roles table has role_id = 1
    $roleCheck = $conn->query("SELECT role_id, role_name FROM roles WHERE role_id IN (1,2) ORDER BY role_id");
    $roles = $roleCheck->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($roles)) {
        // Insert roles if missing
        $conn->exec("INSERT INTO roles (role_id, role_name) VALUES (1, 'Super Admin'), (2, 'Admin'), (3, 'User')
                     ON CONFLICT (role_id) DO NOTHING");
        echo "Roles inserted.\n";
    } else {
        echo "Roles found:\n";
        foreach ($roles as $r) echo "  role_id={$r['role_id']} -> {$r['role_name']}\n";
    }

    // Config for superadmin
    $username   = 'superadmin';
    $password   = 'SuperAdmin@2024!!';   // change as needed
    $email      = 'superadmin@solayao.com';
    $first_name = 'Super';
    $last_name  = 'Admin';
    $role_id    = 1;                     // Super Admin

    // Check if already exists
    $exists = $conn->prepare("SELECT user_id FROM users WHERE username = :u OR email = :e");
    $exists->execute([':u' => $username, ':e' => $email]);
    $existing = $exists->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "\nSuperadmin already exists (user_id={$existing['user_id']}).\n";
        echo "Resetting password...\n";
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE users SET password = :p, role_id = 1, is_active = true WHERE user_id = :id");
        $upd->execute([':p' => $hashed, ':id' => $existing['user_id']]);
        echo "Password updated successfully.\n";
    } else {
        // Hash the password
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users (
                    username, email, password, first_name, last_name,
                    role_id, is_active, permissions, created_at, updated_at
                ) VALUES (
                    :username, :email, :password, :first_name, :last_name,
                    :role_id, true, '[]', NOW(), NOW()
                ) RETURNING user_id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':username'   => $username,
            ':email'      => $email,
            ':password'   => $hashed,
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':role_id'    => $role_id,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $row['user_id'];

        echo "\n✅ Superadmin created successfully!\n";
        echo "   user_id  : $user_id\n";
    }

    echo "\n--- Login Credentials ---\n";
    echo "  URL      : http://localhost:8000/admin/login.php\n";
    echo "  Username : $username\n";
    echo "  Password : $password\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
