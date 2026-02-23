<?php
// ============================================================
//  db.php — Connects to MySQL using config.php settings
// ============================================================

require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("
        <div style='font-family:sans-serif;padding:40px;text-align:center'>
            <h2 style='color:#DC2626'>❌ Database Connection Failed</h2>
            <p style='color:#64748B'>Error: " . $conn->connect_error . "</p>
            <p style='color:#64748B'>Please check your database settings in <code>includes/config.php</code></p>
        </div>
    ");
}

// Set charset to utf8 so special characters work
$conn->set_charset("utf8mb4");