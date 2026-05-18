<?php
// Quick test: simulate the admin login POST from root
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
$_POST = ['username' => 'superadmin', 'password' => 'SuperAdmin@2024!!'];

ob_start();
try {
    require __DIR__ . '/admin/login.php';
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage();
}
$output = ob_get_clean();
echo $output;
