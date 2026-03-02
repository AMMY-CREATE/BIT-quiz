<?php
/**
 * BIT Quiz - Student Authentication
 * Validates Roll No, Name, Class, Quiz Code. Creates attempt record.
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Sanitize inputs (prepared statements used for DB)
$student_id   = trim($_POST['student_id'] ?? '');
$student_name = trim($_POST['student_name'] ?? '');
$student_class= trim($_POST['student_class'] ?? '');
$quiz_code    = strtoupper(trim($_POST['quiz_code'] ?? ''));

// Validation
if (empty($student_id) || empty($student_name) || empty($student_class) || empty($quiz_code)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: index.php");
    exit();
}

if (strlen($quiz_code) < 4 || strlen($quiz_code) > 20) {
    $_SESSION['error'] = "Invalid quiz code format.";
    header("Location: index.php");
    exit();
}

// Validate quiz exists and is active
$stmt = $conn->prepare("SELECT id, title, time_limit_sec, total_marks, marks_per_q FROM quizzes WHERE quiz_code = ? AND is_active = 1");
$stmt->bind_param("s", $quiz_code);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

if (!$quiz) {
    $_SESSION['error'] = "Invalid or inactive quiz code. Please check with your instructor.";
    header("Location: index.php");
    exit();
}

$quiz_id = (int)$quiz['id'];

// Check if student already attempted this quiz
$check = $conn->prepare("SELECT id, status FROM student_attempts WHERE student_id = ? AND quiz_id = ?");
$check->bind_param("si", $student_id, $quiz_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();

if ($existing) {
    if ($existing['status'] === 'ACTIVE') {
        // Resume existing attempt after disconnect
        $resume = $conn->prepare("SELECT sa.id, sa.login_time FROM student_attempts sa WHERE sa.student_id = ? AND sa.quiz_id = ?");
        $resume->bind_param("si", $student_id, $quiz_id);
        $resume->execute();
        $r = $resume->get_result()->fetch_assoc();
        if (!$r) {
            $_SESSION['error'] = "Invalid attempt.";
            header("Location: index.php");
            exit();
        }
        $attempt_id = (int)$r['id'];
        $sess_id = session_id();
        $upd_att = $conn->prepare("UPDATE student_attempts SET session_id = ? WHERE id = ?");
        $upd_att->bind_param("si", $sess_id, $attempt_id);
        $upd_att->execute();
        $upd = $conn->prepare("UPDATE active_sessions SET session_id = ?, last_seen = NOW() WHERE student_id = ? AND quiz_id = ?");
        $upd->bind_param("ssi", $sess_id, $student_id, $quiz_id);
        $upd->execute();
        if ($upd->affected_rows === 0) {
            $ins = $conn->prepare("INSERT INTO active_sessions (student_id, quiz_id, attempt_id, session_id) VALUES (?, ?, ?, ?)");
            $ins->bind_param("siss", $student_id, $quiz_id, $attempt_id, $sess_id);
            $ins->execute();
        }
        $_SESSION['student_id'] = $student_id;
        $_SESSION['student_name'] = $student_name;
        $_SESSION['student_class'] = $student_class;
        $_SESSION['quiz_id'] = $quiz_id;
        $_SESSION['attempt_id'] = $attempt_id;
        $_SESSION['quiz_code'] = $quiz_code;
        $_SESSION['quiz_time_limit'] = (int)$quiz['time_limit_sec'];
        $_SESSION['quiz_total_marks'] = (int)$quiz['total_marks'];
        $_SESSION['quiz_marks_per_q'] = (int)$quiz['marks_per_q'];
        $_SESSION['start_time'] = strtotime($r['login_time']);
        $_SESSION['quiz_active'] = true;
        $_SESSION['quiz_loaded_once'] = false;
        log_activity('STUDENT', $student_id, 'RESUME', "Quiz $quiz_id");
        header("Location: quiz.php");
        exit();
    } else {
        $_SESSION['error'] = "You have already submitted this quiz.";
        header("Location: index.php");
        exit();
    }
}

// Create attempt record (optimized single insert)
$session_id = session_id();
$stmt = $conn->prepare(
    "INSERT INTO student_attempts (student_id, student_name, student_class, quiz_id, quiz_code, status, total_questions, session_id, last_heartbeat) VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?, ?, NOW())"
);

$total_q = 0;
$q_count = $conn->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?");
$q_count->bind_param("i", $quiz_id);
$q_count->execute();
$total_q = (int)$q_count->get_result()->fetch_row()[0];

if ($total_q <= 0) {
    $_SESSION['error'] = "This quiz has no questions yet.";
    header("Location: index.php");
    exit();
}

$stmt->bind_param("sssisis", $student_id, $student_name, $student_class, $quiz_id, $quiz_code, $total_q, $session_id);
$stmt->execute();
$attempt_id = $conn->insert_id;

// Register active session for live monitoring
$sess_stmt = $conn->prepare("INSERT INTO active_sessions (student_id, quiz_id, attempt_id, session_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE attempt_id = ?, session_id = ?, last_seen = NOW()");
$sess_stmt->bind_param("siisis", $student_id, $quiz_id, $attempt_id, $session_id, $attempt_id, $session_id);
$sess_stmt->execute();

// Set session data
$_SESSION['student_id']    = $student_id;
$_SESSION['student_name']  = $student_name;
$_SESSION['student_class'] = $student_class;
$_SESSION['quiz_id']       = $quiz_id;
$_SESSION['attempt_id']    = $attempt_id;
$_SESSION['quiz_code']     = $quiz_code;
$_SESSION['quiz_time_limit'] = (int)$quiz['time_limit_sec'];
$_SESSION['quiz_total_marks'] = (int)$quiz['total_marks'];
$_SESSION['quiz_marks_per_q'] = (int)$quiz['marks_per_q'];
$_SESSION['start_time']    = time();
$_SESSION['quiz_active']   = true;
$_SESSION['quiz_loaded_once'] = false;

log_activity('STUDENT', $student_id, 'LOGIN', "Quiz $quiz_id ($quiz_code)");

header("Location: quiz.php");
exit();
