<?php
require '../php/db_connection.php';

$query = "
CREATE TABLE IF NOT EXISTS edit_requests (
    request_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(user_id) ON DELETE CASCADE,
    requested_by INTEGER REFERENCES users(user_id) ON DELETE CASCADE,
    requested_data JSONB,
    reason TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending', /* pending, approved, denied */
    reviewed_by INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    review_notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
";

try {
    $conn->exec($query);
    echo "Successfully created edit_requests table.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
