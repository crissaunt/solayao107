<?php
require __DIR__ . '/php/db_connection.php';
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check roles table columns
    $stmt = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name='roles' ORDER BY ordinal_position");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "roles columns: " . implode(', ', $cols) . "\n";
    
    // Check if is_active exists on roles
    echo "has is_active: " . (in_array('is_active', $cols) ? 'YES' : 'NO') . "\n";
    echo "has role_description: " . (in_array('role_description', $cols) ? 'YES' : 'NO') . "\n";
    
    // Show roles data
    $rows = $conn->query("SELECT * FROM roles ORDER BY role_id")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nRoles:\n";
    foreach ($rows as $r) { echo "  " . json_encode($r) . "\n"; }
    
    // Try the problematic query fragment
    echo "\nTesting main query...\n";
    $conn->query("SELECT u.user_id, r.role_name, r.role_description FROM users u LEFT JOIN roles r ON u.role_id = r.role_id LIMIT 1");
    echo "Main query OK\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
