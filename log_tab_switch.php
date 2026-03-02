<?php
/**
 * BIT Quiz - Log Tab Switch
 * Called when student switches away from quiz tab. Updates violation count.
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit();
}

if (!isset($_SESSION['student_id']) || !isset($_SESSION['quiz_id'])) {
    exit();
}

$student_id = $_SESSION['student_id'];
$quiz_id    = (int)$_SESSION['quiz_id'];

// Log tab switch
$stmt = $conn->prepare("INSERT INTO tab_switches (student_id, quiz_id) VALUES (?, ?)");
$stmt->bind_param("si", $student_id, $quiz_id);
$stmt->execute();

// Log suspicious activity
$stmt2 = $conn->prepare("INSERT INTO suspicious_logs (student_id, quiz_id, event_type, event_details) VALUES (?, ?, 'TAB_SWITCH', 'User switched away from quiz tab')");
$stmt2->bind_param("si", $student_id, $quiz_id);
$stmt2->execute();

// Increment violation count on attempt
$upd = $conn->prepare("UPDATE student_attempts SET violation_count = violation_count + 1 WHERE student_id = ? AND quiz_id = ?");
$upd->bind_param("si", $student_id, $quiz_id);
$upd->execute();
