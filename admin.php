<?php
/**
 * BIT Quiz - Admin Dashboard
 * Quiz CRUD, Live Runtime Observation, Results, Export
 */
require_once 'config.php';

// Redirect to login if not admin
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    log_activity('ADMIN', $_SESSION['admin_username'] ?? 'admin', 'LOGOUT', null);
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

// Handle quiz actions (add/edit/delete)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add_quiz') {
        $title = trim($_POST['title'] ?? '');
        $code  = strtoupper(trim($_POST['quiz_code'] ?? ''));
        $time  = (int)($_POST['time_limit'] ?? 1800);
        $marks = (int)($_POST['total_marks'] ?? 30);
        $mpq   = (int)($_POST['marks_per_q'] ?? 1);
        if ($title && $code && strlen($code) >= 4) {
            $stmt = $conn->prepare("INSERT INTO quizzes (title, quiz_code, time_limit_sec, total_marks, marks_per_q) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiii", $title, $code, $time, $marks, $mpq);
            if ($stmt->execute()) $msg = "Quiz created successfully. Share code: $code";
            else $msg = "Error: " . $conn->error;
        } else $msg = "Invalid data.";
    } elseif ($action === 'delete_quiz') {
        $id = (int)($_POST['quiz_id'] ?? 0);
        if ($id > 0) {
            $conn->query("DELETE FROM quizzes WHERE id = $id");
            $msg = "Quiz deleted.";
        }
    } elseif ($action === 'add_question_to_quiz') {
        $quiz_id = (int)($_POST['quiz_id'] ?? 0);
        $qid     = (int)($_POST['question_id'] ?? 0);
        if ($quiz_id > 0 && $qid > 0) {
            $max = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM quiz_questions WHERE quiz_id = $quiz_id")->fetch_assoc()['n'];
            $ins = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_id, sort_order) VALUES (?, ?, ?)");
            $ins->bind_param("iii", $quiz_id, $qid, $max);
            $ins->execute();
            $msg = "Question added.";
        }
    } elseif ($action === 'remove_question') {
        $qq_id = (int)($_POST['qq_id'] ?? 0);
        if ($qq_id > 0) $conn->query("DELETE FROM quiz_questions WHERE id = $qq_id");
        $msg = "Question removed.";
    } elseif ($action === 'add_question') {
        $qt = trim($_POST['q_text'] ?? '');
        $oa = trim($_POST['option_a'] ?? '');
        $ob = trim($_POST['option_b'] ?? '');
        $oc = trim($_POST['option_c'] ?? '');
        $od = trim($_POST['option_d'] ?? '');
        $co = strtoupper(trim($_POST['correct_option'] ?? 'A'));
        if (in_array($co, ['A','B','C','D']) && $qt && $oa && $ob && $oc && $od) {
            $ins = $conn->prepare("INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->bind_param("ssssss", $qt, $oa, $ob, $oc, $od, $co);
            $ins->execute();
            $msg = "Question added to bank.";
        } else $msg = "Invalid question data.";
    }
}

// Fetch quizzes for dropdown
$quizzes = $conn->query("SELECT id, title, quiz_code, time_limit_sec, total_marks, is_active FROM quizzes ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$questions = $conn->query("SELECT id, question_text FROM questions ORDER BY id LIMIT 200")->fetch_all(MYSQLI_ASSOC);
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : (count($quizzes) ? (int)$quizzes[0]['id'] : 0);

// Attempts for selected quiz
$attempts = [];
if ($quiz_id > 0) {
    $a = $conn->prepare("SELECT sa.* FROM student_attempts sa WHERE sa.quiz_id = ? ORDER BY sa.login_time DESC");
    $a->bind_param("i", $quiz_id);
    $a->execute();
    $attempts = $a->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - BIT Quiz</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
        .admin-section { margin-bottom: 2.5rem; }
        table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 12px; overflow: hidden; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; font-size: 0.9rem; }
        th { background: var(--primary); color: white; font-weight: 600; }
        .badge { padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .live-badge { font-size: 0.65rem; padding: 0.2rem 0.5rem; background: #ef4444; color: white; border-radius: 999px; margin-left: 0.5rem; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.7} }
        .observing-table { font-size: 0.85rem; }
        .observing-table td { padding: 0.5rem 0.75rem; }
        .section-desc { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem; }
        .form-inline { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
        .form-inline input, .form-inline select { padding: 0.4rem 0.6rem; border: 1px solid var(--border); border-radius: 6px; }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; width: auto; }
        .msg { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .msg-success { background: #d1fae5; color: #065f46; }
        .msg-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="quiz-container" style="max-width: 1200px;">
        <div class="admin-header">
            <h1>Admin Dashboard</h1>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="export_excel.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-sm" style="text-decoration:none;">Export Excel (3 files)</a>
                <a href="admin.php?logout=1" class="btn btn-sm" style="background:#6b7280;text-decoration:none;">Logout</a>
            </div>
        </div>

        <?php if ($msg): ?>
        <div class="msg <?php echo strpos($msg, 'Error') !== false ? 'msg-error' : 'msg-success'; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <!-- Quiz selector -->
        <div class="admin-section">
            <h2>Select Quiz</h2>
            <div class="form-inline">
                <select id="quizSelect" onchange="location.href='admin.php?quiz_id='+this.value">
                    <?php foreach ($quizzes as $q): ?>
                    <option value="<?php echo $q['id']; ?>" <?php echo $quiz_id == $q['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($q['title']); ?> (<?php echo htmlspecialchars($q['quiz_code']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Live Runtime Observation -->
        <div class="admin-section">
            <h2>Live Runtime Observation <span class="live-badge">LIVE</span></h2>
            <p class="section-desc">Real-time list of students: Joined, Active, Submitted, Disconnected. Auto-refreshes every 2 seconds.</p>
            <div id="observingContainer">
                <table class="observing-table">
                    <thead>
                        <tr>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Current Q#</th>
                            <th>Time Left</th>
                            <th>Status</th>
                            <th>Violations</th>
                        </tr>
                    </thead>
                    <tbody id="observingBody">
                        <tr><td colspan="7" style="color:var(--text-muted);">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="section-desc" style="font-size:0.8rem;margin-top:0.5rem;" id="lastUpdated">—</p>
        </div>

        <!-- Quiz Management -->
        <div class="admin-section">
            <h2>Create New Quiz</h2>
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_quiz">
                <input type="text" name="title" placeholder="Quiz title" required maxlength="200">
                <input type="text" name="quiz_code" placeholder="Quiz code (e.g. GK2025)" required minlength="4" maxlength="20" style="text-transform:uppercase">
                <input type="number" name="time_limit" placeholder="Time (sec)" value="1800" min="60">
                <input type="number" name="total_marks" placeholder="Total marks" value="30" min="1">
                <input type="number" name="marks_per_q" placeholder="Marks per Q" value="1" min="1">
                <button type="submit" class="btn btn-sm">Create</button>
            </form>
        </div>

        <div class="admin-section">
            <h2>Add New Question to Bank</h2>
            <form method="POST" style="max-width:600px;">
                <input type="hidden" name="action" value="add_question">
                <div class="form-group">
                    <label>Question Text</label>
                    <input type="text" name="q_text" required maxlength="500" style="width:100%;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div class="form-group"><label>Option A</label><input type="text" name="option_a" required maxlength="500"></div>
                    <div class="form-group"><label>Option B</label><input type="text" name="option_b" required maxlength="500"></div>
                    <div class="form-group"><label>Option C</label><input type="text" name="option_c" required maxlength="500"></div>
                    <div class="form-group"><label>Option D</label><input type="text" name="option_d" required maxlength="500"></div>
                </div>
                <div class="form-group">
                    <label>Correct Option</label>
                    <select name="correct_option"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select>
                </div>
                <button type="submit" class="btn btn-sm">Add Question</button>
            </form>
        </div>

        <div class="admin-section">
            <h2>Add Question to Quiz</h2>
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_question_to_quiz">
                <select name="quiz_id">
                    <?php foreach ($quizzes as $q): ?>
                    <option value="<?php echo $q['id']; ?>" <?php echo $quiz_id == $q['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($q['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="question_id">
                    <?php foreach ($questions as $q): ?>
                    <option value="<?php echo $q['id']; ?>"><?php echo htmlspecialchars(mb_substr($q['question_text'], 0, 60)) . (mb_strlen($q['question_text']) > 60 ? '...' : ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm">Add</button>
            </form>
        </div>

        <!-- Results -->
        <div class="admin-section">
            <h2>Results (Submitted Attempts)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Correct</th>
                        <th>Wrong</th>
                        <th>Marks</th>
                        <th>Time</th>
                        <th>Submit Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($a['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($a['student_class']); ?></td>
                        <td><span class="badge <?php echo $a['status']==='SUBMITTED'?'badge-success':($a['status']==='ACTIVE'?'badge-warn':'badge-danger'); ?>"><?php echo htmlspecialchars($a['status']); ?></span></td>
                        <td><?php echo $a['correct_count']; ?></td>
                        <td><?php echo $a['wrong_count']; ?></td>
                        <td><?php echo $a['final_marks']; ?></td>
                        <td><?php echo gmdate('i:s', $a['time_taken_sec']); ?></td>
                        <td><?php echo $a['submit_time'] ?? '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    (function() {
        var quizId = <?php echo $quiz_id; ?>;
        var POLL = 2000;

        function escapeHtml(s) {
            if (!s) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function formatTime(sec) {
            if (sec <= 0) return '—';
            var m = Math.floor(sec / 60);
            var s = sec % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        function render(body, data) {
            var students = data.active_candidates || [];
            var html = '';
            if (students.length === 0) {
                html = '<tr><td colspan="7" style="color:var(--text-muted);">No students yet for this quiz.</td></tr>';
            } else {
                students.forEach(function(s) {
                    var statusClass = s.status === 'ACTIVE' ? 'badge-warn' : (s.status === 'SUBMITTED' ? 'badge-success' : 'badge-danger');
                    html += '<tr><td>' + escapeHtml(s.student_id) + '</td><td>' + escapeHtml(s.student_name) + '</td><td>' + escapeHtml(s.student_class) + '</td><td>' + (s.current_q_num || '—') + '</td><td>' + formatTime(s.time_remaining) + '</td><td><span class="badge ' + statusClass + '">' + escapeHtml(s.status) + '</span></td><td>' + (s.violation_count || 0) + '</td></tr>';
                });
            }
            body.innerHTML = html;
            var u = document.getElementById('lastUpdated');
            if (u && data.updated_at) u.textContent = 'Last updated: ' + data.updated_at;
        }

        function poll() {
            fetch('get_observing_data.php?quiz_id=' + quizId + '&t=' + Date.now())
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.error) render(document.getElementById('observingBody'), data);
                })
                .catch(function() {});
        }
        poll();
        setInterval(poll, POLL);
    })();
    </script>
</body>
</html>
