<?php
require_once "includes/db/config.php";

// Check if tables exist
$tables = array(
    'pending_approvals' => "CREATE TABLE IF NOT EXISTS pending_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        is_used TINYINT(1) DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    'verification_codes' => "CREATE TABLE IF NOT EXISTS verification_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        code VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
);

foreach ($tables as $table => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table {$table} exists or was created successfully<br>";
    } else {
        echo "Error with table {$table}: " . $conn->error . "<br>";
    }
}
