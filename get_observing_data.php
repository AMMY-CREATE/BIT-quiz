<?php
/**
 * BIT Quiz - Admin Live Runtime Observation API
 * Returns JSON: students with Name, Roll No, Current Question #, Time Remaining, Status
 * Polled every few seconds by admin dashboard.
 */
require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// If no quiz specified, use first active quiz
if ($quiz_id <= 0) {
    $q = $conn->query("SELECT id FROM quizzes WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $r = $q ? $q->fetch_assoc() : null;
    $quiz_id = $r ? (int)$r['id'] : 0;
}

$students = [];
$live_events = [];

if ($quiz_id > 0) {
    // All attempts for this quiz (joined + active + submitted)
    $stmt = $conn->prepare("
        SELECT sa.id, sa.student_id, sa.student_name, sa.student_class, sa.status,
               sa.login_time, sa.submit_time, sa.current_q_num, sa.time_remaining,
               sa.total_questions, sa.violation_count, sa.last_heartbeat,
               COALESCE(ac.session_id, '') as has_active_session
        FROM student_attempts sa
        LEFT JOIN active_sessions ac ON sa.student_id = ac.student_id AND sa.quiz_id = ac.quiz_id
        WHERE sa.quiz_id = ?
        ORDER BY sa.login_time DESC
    ");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $now = time();
    $stale_sec = 15; // Consider disconnected if no heartbeat for 15 sec

    foreach ($rows as $r) {
        $status = $r['status'];
        if ($status === 'ACTIVE' && !empty($r['last_heartbeat'])) {
            $hb = strtotime($r['last_heartbeat']);
            if ($now - $hb > $stale_sec) {
                $status = 'DISCONNECTED';
            }
        } elseif ($status === 'ACTIVE' && empty($r['has_active_session'])) {
            $status = 'DISCONNECTED';
        }

        $students[] = [
            'id'            => (int)$r['id'],
            'student_id'    => $r['student_id'],
            'student_name'  => $r['student_name'],
            'student_class' => $r['student_class'],
            'current_q_num' => (int)($r['current_q_num'] ?? 0),
            'time_remaining'=> (int)($r['time_remaining'] ?? 0),
            'total_questions' => (int)($r['total_questions'] ?? 0),
            'status'        => $status,
            'login_time'    => $r['login_time'],
            'submit_time'   => $r['submit_time'],
            'violation_count' => (int)($r['violation_count'] ?? 0),
            'risk_level'    => (int)($r['violation_count'] ?? 0) >= 5 ? 'HIGH' : ((int)($r['violation_count'] ?? 0) >= 2 ? 'MODERATE' : 'LOW')
        ];
    }

    // Live events (tab switches, refreshes)
    $ev_stmt = $conn->prepare("
        (SELECT student_id, 'TAB_SWITCH' as event_type, switched_at as ts FROM tab_switches WHERE quiz_id = ? ORDER BY switched_at DESC LIMIT 30)
        UNION ALL
        (SELECT student_id, event_type, logged_at as ts FROM suspicious_logs WHERE quiz_id = ? ORDER BY logged_at DESC LIMIT 30)
        ORDER BY ts DESC LIMIT 50
    ");
    $ev_stmt->bind_param("ii", $quiz_id, $quiz_id);
    $ev_stmt->execute();
    $ev_rows = $ev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($ev_rows as $e) {
        $live_events[] = [
            'student_id'  => $e['student_id'],
            'event_type'  => $e['event_type'],
            'timestamp'   => $e['ts']
        ];
    }
}

echo json_encode([
    'active_candidates' => $students,
    'live_events'       => $live_events,
    'quiz_id'           => $quiz_id,
    'updated_at'        => date('Y-m-d H:i:s')
]);
