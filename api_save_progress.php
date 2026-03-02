<?php
/**
 * BIT Quiz - Auto-save progress (answers + current question)
 * Called on answer change + heartbeat. Handles sudden disconnects.
 */
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id']) || !isset($_SESSION['attempt_id'])) {
    echo json_encode(['ok' => false]);
    exit();
}

$attempt_id = (int)$_SESSION['attempt_id'];
$current_q  = isset($_POST['current_q']) ? (int)$_POST['current_q'] : 0;
$answers    = isset($_POST['answers']) ? $_POST['answers'] : '{}';

// Validate JSON
$decoded = json_decode($answers);
if (!is_object($decoded) && !is_array($decoded)) {
    $answers = '{}';
}

$stmt = $conn->prepare(
    "INSERT INTO quiz_progress (attempt_id, answers_json, current_q) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE answers_json = VALUES(answers_json), current_q = VALUES(current_q)"
);
$stmt->bind_param("isi", $attempt_id, $answers, $current_q);
$stmt->execute();

echo json_encode(['ok' => true]);
