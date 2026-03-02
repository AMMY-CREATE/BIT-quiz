<?php
/**
 * BIT Quiz - Submit Quiz
 * Calculates results server-side, stores responses, updates attempt.
 * Students are NOT shown results - redirect to thank-you page.
 */
require_once 'config.php';

if (!isset($_SESSION['student_id']) || !isset($_SESSION['quiz_active']) || !isset($_SESSION['attempt_id'])) {
    header("Location: index.php");
    exit();
}

$student_id  = $_SESSION['student_id'];
$quiz_id     = (int)$_SESSION['quiz_id'];
$attempt_id  = (int)$_SESSION['attempt_id'];
$questions   = $_SESSION['questions'] ?? [];
$start_time  = (int)$_SESSION['start_time'];
$marks_per_q = (int)($_SESSION['quiz_marks_per_q'] ?? 1);
$answers     = $_POST['ans'] ?? [];
$time_taken  = time() - $start_time;

// Verify attempt
$check = $conn->prepare("SELECT id, status, total_questions FROM student_attempts WHERE id = ? AND student_id = ?");
$check->bind_param("is", $attempt_id, $student_id);
$check->execute();
$attempt = $check->get_result()->fetch_assoc();

if (!$attempt || $attempt['status'] !== 'ACTIVE') {
    $_SESSION['quiz_active'] = false;
    header("Location: index.php?submitted=1");
    exit();
}

// Calculate score server-side
$correct_count = 0;
$wrong_count   = 0;

// Batch insert responses (efficient for 100+ students)
$insert_resp = $conn->prepare(
    "INSERT INTO student_responses (attempt_id, student_id, quiz_id, question_id, question_text, option_selected, correct_option, is_correct) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

$opt_map = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];

foreach ($questions as $idx => $q) {
    $selected = isset($answers[$idx]) ? strtoupper(trim($answers[$idx])) : null;
    $correct  = ($q['correct_option']);
    $is_correct = ($selected !== null && $selected === $correct) ? 1 : 0;
    if ($selected !== null) {
        if ($selected === $correct) $correct_count++; else $wrong_count++;
    }
    $q_text = $q['question_text'];
    $q_id   = $q['id'] ?? 0;
    $insert_resp->bind_param("isissssi", $attempt_id, $student_id, $quiz_id, $q_id, $q_text, $selected, $correct, $is_correct);
    $insert_resp->execute();
}

$total_q    = count($questions);
$final_marks = $correct_count * $marks_per_q;

// Update attempt
$viol_count = 0;
$vstmt = $conn->prepare("SELECT violation_count FROM student_attempts WHERE id = ?");
$vstmt->bind_param("i", $attempt_id);
$vstmt->execute();
$vr = $vstmt->get_result()->fetch_assoc();
if ($vr) $viol_count = (int)$vr['violation_count'];

$upd = $conn->prepare(
    "UPDATE student_attempts SET status = 'SUBMITTED', submit_time = NOW(), correct_count = ?, wrong_count = ?, final_marks = ?, time_taken_sec = ?, total_questions = ? WHERE id = ?"
);
$upd->bind_param("iiiiii", $correct_count, $wrong_count, $final_marks, $time_taken, $total_q, $attempt_id);
$upd->execute();

// Remove from active_sessions
$del = $conn->prepare("DELETE FROM active_sessions WHERE student_id = ? AND quiz_id = ?");
$del->bind_param("si", $student_id, $quiz_id);
$del->execute();

// Clear session
$_SESSION['quiz_active'] = false;
unset($_SESSION['questions'], $_SESSION['start_time'], $_SESSION['quiz_id'], $_SESSION['attempt_id'], $_SESSION['quiz_time_limit'], $_SESSION['quiz_marks_per_q'], $_SESSION['quiz_total_marks'], $_SESSION['quiz_code']);
$_SESSION['quiz_loaded_once'] = false;

log_activity('STUDENT', $student_id, 'SUBMIT', "Quiz $quiz_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Submitted - BIT Quiz</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .submitted-card { text-align: center; padding: 3rem 2rem; max-width: 500px; margin: 4rem auto; }
        .submitted-card h1 { color: var(--success); margin-bottom: 1rem; }
        .submitted-card p { color: var(--text-muted); margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="quiz-container">
        <div class="submitted-card">
            <h1>Quiz Submitted</h1>
            <p>Your responses have been recorded. Thank you.</p>
            <p style="font-size: 0.9rem;">Results will be announced by your instructor.</p>
            <a href="index.php" class="btn" style="text-decoration:none;display:inline-block;">Return to Home</a>
        </div>
    </div>
</body>
</html>
<?php session_destroy(); ?>
