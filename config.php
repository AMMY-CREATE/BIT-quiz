<?php
/**
 * BIT Quiz - Configuration & Database Connection
 * Production-ready for XAMPP Intranet deployment
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'mysql');
define('DB_NAME', 'bit_quiz');

// --- Concurrency & Polling ---
define('HEARTBEAT_INTERVAL', 3);       // Student heartbeat every 3 sec
define('ADMIN_POLL_INTERVAL', 2000);   // Admin dashboard refresh (ms)
define('VIOLATIONS_AUTO_SUBMIT', 5);   // Auto-submit after N violations

// --- Database Connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("
    <div style='font-family:sans-serif;text-align:center;margin-top:100px;color:#ef4444;'>
        <h2>&#9888; Database Connection Failed</h2>
        <p>" . htmlspecialchars($conn->connect_error) . "</p>
        <p style='color:#555;'>Ensure XAMPP MySQL is running and database.sql has been imported.</p>
        <a href='index.php' style='color:#3b82f6;'>Go Back</a>
    </div>");
}

$conn->set_charset("utf8");

/**
 * Log activity for audit trail
 */
function log_activity($actor_type, $actor_id, $action, $details = null) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $conn->prepare("INSERT INTO activity_log (actor_type, actor_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $actor_type, $actor_id, $action, $details, $ip);
    $stmt->execute();
}
