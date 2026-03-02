<?php
/**
 * Diagnostic: Check why Observing shows no candidates
 * Run this while a student is taking the quiz.
 */
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Observing Diagnostic ===\n\n";

echo "1. Database: " . DB_NAME . "\n";
echo "2. Connection: " . ($conn->connect_error ? "FAILED - " . $conn->connect_error : "OK") . "\n";

if ($conn->connect_error) {
    exit();
}

$tables = $conn->query("SHOW TABLES LIKE 'active_sessions'");
echo "3. active_sessions table: " . ($tables && $tables->num_rows > 0 ? "EXISTS" : "MISSING! Import database.sql") . "\n";

$rows = $conn->query("SELECT * FROM active_sessions");
if (!$rows) {
    echo "4. Query error: " . $conn->error . "\n";
    exit();
}
echo "4. Rows in active_sessions: " . $rows->num_rows . "\n";

if ($rows->num_rows > 0) {
    echo "\nCurrent active candidates:\n";
    while ($r = $rows->fetch_assoc()) {
        echo "   - " . $r['student_id'] . " (since " . ($r['created_at'] ?? 'N/A') . ")\n";
    }
} else {
    echo "\n>> No rows. Ensure:\n";
    echo "   - Student started quiz via index.php -> Enter ID -> Start\n";
    echo "   - Student and Admin use SAME database (same XAMPP)\n";
    echo "   - Admin in different browser/tab than student (to avoid session conflict)\n";
}
