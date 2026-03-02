<?php
/**
 * BIT Quiz - Export to Excel (CSV format, opens in Excel)
 * Generates 3 files: Student_Details, Student_Responses, Final_Results
 * Admin only. Files are generated on-demand, not stored on disk.
 */
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die("Access denied. Please log in.");
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$type    = isset($_GET['type']) ? trim($_GET['type']) : '';

if ($quiz_id <= 0) {
    $q = $conn->query("SELECT id FROM quizzes WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $r = $q ? $q->fetch_assoc() : null;
    $quiz_id = $r ? (int)$r['id'] : 0;
}

if ($quiz_id <= 0) {
    die("No quiz found.");
}

$date = date('Y-m-d');
$types = ['details' => 'Student_Details', 'responses' => 'Student_Responses', 'results' => 'Final_Results'];

if (!isset($types[$type])) {
    // Show download links page
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Export - BIT Quiz</title><link rel="stylesheet" href="style.css"></head><body>';
    echo '<div class="quiz-container" style="max-width:600px;margin:4rem auto;">';
    echo '<h1>Export Quiz Data</h1>';
    echo '<p style="color:var(--text-muted);margin-bottom:1.5rem;">Download 3 separate CSV files (open in Excel):</p>';
    echo '<ul style="list-style:none;padding:0;">';
    foreach ($types as $k => $label) {
        echo '<li style="margin-bottom:0.75rem;"><a href="export_excel.php?quiz_id=' . $quiz_id . '&type=' . $k . '" class="btn" style="display:inline-block;text-decoration:none;width:auto;">Download ' . $label . '.csv</a></li>';
    }
    echo '</ul>';
    echo '<p style="margin-top:2rem;"><a href="admin.php?quiz_id=' . $quiz_id . '" style="color:var(--primary);">Back to Admin</a></p>';
    echo '</div></body></html>';
    exit();
}

$filename = $types[$type] . '_' . $date . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($type) {
    case 'details':
        fputcsv($out, ['Student ID', 'Name', 'Class', 'Quiz ID', 'Login Time', 'Submission Time', 'Status']);
        $stmt = $conn->prepare("SELECT student_id, student_name, student_class, quiz_id, login_time, submit_time, status FROM student_attempts WHERE quiz_id = ? ORDER BY login_time DESC");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) {
            fputcsv($out, $row);
        }
        break;

    case 'responses':
        fputcsv($out, ['Student ID', 'Quiz ID', 'Question ID', 'Question Text', 'Option Selected', 'Correct Option', 'Is Correct']);
        $stmt = $conn->prepare("
            SELECT sr.student_id, sr.quiz_id, sr.question_id, sr.question_text, sr.option_selected, sr.correct_option,
                   CASE WHEN sr.is_correct = 1 THEN 'Yes' ELSE 'No' END AS is_correct
            FROM student_responses sr
            WHERE sr.quiz_id = ?
            ORDER BY sr.student_id, sr.question_id
        ");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) {
            fputcsv($out, $row);
        }
        break;

    case 'results':
        fputcsv($out, ['Student ID', 'Name', 'Quiz ID', 'Total Questions', 'Correct Answers', 'Wrong Answers', 'Final Marks']);
        $stmt = $conn->prepare("
            SELECT student_id, student_name, quiz_id, total_questions, correct_count, wrong_count, final_marks
            FROM student_attempts
            WHERE quiz_id = ? AND status = 'SUBMITTED'
            ORDER BY final_marks DESC, student_id
        ");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $rows = $stmt->get_result();
        while ($row = $rows->fetch_assoc()) {
            $row['correct_count'] = $row['correct_count'] ?? 0;
            $row['wrong_count']   = $row['wrong_count'] ?? 0;
            fputcsv($out, $row);
        }
        break;
}

fclose($out);
exit();
