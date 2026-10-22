<?php
/**
 * db.php
 * MySQL database connection using MySQLi (OOP style).
 * Update the credentials below to match your local / server environment.
 */

// ---------- Database configuration ----------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'health_tracker');

// ---------- Create connection ----------
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ---------- Check connection ----------
if ($conn->connect_error) {
    // Return JSON error instead of a raw PHP error (useful since this file
    // is included by bmi.php which talks to JavaScript via fetch()).
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Ensure correct character set
$conn->set_charset('utf8mb4');
?>
