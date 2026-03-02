<?php
/**
 * BIT Quiz - Student Heartbeat API
 * Called every few seconds from quiz page. Updates last_heartbeat, current_q_num, time_remaining.
 * Used by admin live dashboard. Optimized for 100+ concurrent students (single UPDATE).
 */
require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');

if (!isset($_SESSION['student_id']) || !isset($_SESSION['quiz_active']) || !isset($_SESSION['attempt_id'])) {
    echo json_encode(['ok' => false]);
    exit();
}

$student_id  = $_SESSION['student_id'];
$quiz_id     = (int)$_SESSION['quiz_id'];
$attempt_id  = (int)$_SESSION['attempt_id'];

// Get params from POST (reduces URL logging)
$current_q   = isset($_POST['current_q']) ? (int)$_POST['current_q'] : 0;
$time_left   = isset($_POST['time_left']) ? (int)$_POST['time_left'] : 0;

// Single UPDATE - efficient for concurrency
$stmt = $conn->prepare(
    "UPDATE student_attempts SET last_heartbeat = NOW(), current_q_num = ?, time_remaining = ? WHERE id = ? AND student_id = ?"
);
$stmt->bind_param("iiis", $current_q, $time_left, $attempt_id, $student_id);
$stmt->execute();

// Also bump active_sessions.last_seen for "currently active" detection
$sess = $conn->prepare("UPDATE active_sessions SET last_seen = NOW() WHERE student_id = ? AND quiz_id = ?");
$sess->bind_param("si", $student_id, $quiz_id);
$sess->execute();

echo json_encode(['ok' => true]);
