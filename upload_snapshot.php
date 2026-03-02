<?php
/**
 * Accepts screen snapshot from quiz page (candidate). Saves as snapshots/{student_id}.jpg
 * Requires active quiz session.
 */
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id']) || !isset($_SESSION['quiz_active'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not in quiz']);
    exit();
}

$student_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $_SESSION['student_id']);
if ($student_id === '') {
    echo json_encode(['ok' => false, 'error' => 'Invalid student']);
    exit();
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    echo json_encode(['ok' => false, 'error' => 'No data']);
    exit();
}

$data = json_decode($raw, true);
if (!isset($data['snapshot']) || !is_string($data['snapshot'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit();
}

// Expect base64 data URL: data:image/jpeg;base64,...
$snapshot = $data['snapshot'];
if (preg_match('/^data:image\/jpeg;base64,(.+)$/', $snapshot, $m)) {
    $bin = base64_decode($m[1], true);
} else {
    $bin = base64_decode($snapshot, true);
}
if ($bin === false || strlen($bin) > 500 * 1024) { // max 500KB
    echo json_encode(['ok' => false, 'error' => 'Invalid image']);
    exit();
}

$dir = __DIR__ . '/snapshots';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$path = $dir . '/' . $student_id . '.jpg';
if (file_put_contents($path, $bin) === false) {
    echo json_encode(['ok' => false, 'error' => 'Save failed']);
    exit();
}

echo json_encode(['ok' => true]);
