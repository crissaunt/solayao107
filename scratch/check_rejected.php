<?php
require_once __DIR__ . '/../php/db_connection.php';

try {
    $stmt = $conn->query("SELECT user_id, first_name, last_name, email, is_active, registration_status FROM users ORDER BY user_id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2 style='font-family: sans-serif;'>Database Diagnostic: Registration Status</h2>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; font-family: sans-serif;'>";
    echo "<tr style='background: #f4f4f4;'>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>is_active</th>
            <th>registration_status</th>
          </tr>";
          
    foreach ($users as $u) {
        $activeText = $u['is_active'] ? "<span style='color:green;'>True</span>" : "<span style='color:red;'>False</span>";
        $statusText = $u['registration_status'];
        if ($statusText === 'rejected') {
            $statusText = "<b style='color:red;'>REJECTED</b>";
        } elseif ($statusText === 'pending') {
            $statusText = "<b style='color:#17a2b8;'>PENDING</b>";
        } elseif ($statusText === 'approved') {
            $statusText = "<b style='color:green;'>APPROVED</b>";
        }
        
        echo "<tr>
                <td>{$u['user_id']}</td>
                <td>" . htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) . "</td>
                <td>" . htmlspecialchars($u['email']) . "</td>
                <td>{$activeText}</td>
                <td>{$statusText}</td>
              </tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
