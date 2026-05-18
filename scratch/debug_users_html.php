<?php
// Mock session for users.php
session_start();
$_SESSION['user_id'] = 1010;
$_SESSION['logged_in'] = true;
$_SESSION['role_id'] = 1;
$_SESSION['full_name'] = 'Super Admin';

// Capture output
ob_start();
include 'admin/users.php';
$html = ob_get_clean();

$lines = explode("\n", $html);
echo "Total lines: " . count($lines) . "\n";

// Search for where the JS block starts
$js_start = 0;
foreach ($lines as $i => $line) {
    if (strpos($line, '<script>') !== false) {
        $js_start = $i;
        // Don't break, find the last one (main JS block)
    }
}

echo "Main JS block starts at line: " . ($js_start + 1) . "\n";
for ($i = $js_start; $i < min($js_start + 50, count($lines)); $i++) {
    echo ($i + 1) . ": " . $lines[$i] . "\n";
}
