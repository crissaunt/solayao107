<?php
require_once __DIR__ . '/../php/db_connection.php';
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS activation_requests (
            request_id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
            status VARCHAR(20) DEFAULT 'pending',
            reviewed_by INT REFERENCES users(user_id),
            review_notes TEXT,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_activation_requests_pending 
            ON activation_requests(user_id) WHERE status = 'pending';
    ");
    echo "<h2 style='color:green'>✅ activation_requests table created successfully!</h2>";
    echo "<p>You can now <a href='login.php'>delete this file</a> and go back to the admin panel.</p>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
?>
