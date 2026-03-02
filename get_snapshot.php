<?php
/**
 * Serves latest screen snapshot for a candidate. Admin only.
 * GET ?student_id=xxx
 */
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit();
}

$student_id = isset($_GET['student_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['student_id']) : '';
if ($student_id === '') {
    http_response_code(400);
    exit();
}

$path = __DIR__ . '/snapshots/' . $student_id . '.jpg';
if (!is_file($path)) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-store, no-cache');
    echo '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 320 180"><rect fill="#f1f5f9" width="320" height="180"/><text x="160" y="95" text-anchor="middle" fill="#94a3b8" font-family="sans-serif" font-size="14">No snapshot yet</text></svg>';
    exit();
}

header('Content-Type: image/jpeg');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($path);
