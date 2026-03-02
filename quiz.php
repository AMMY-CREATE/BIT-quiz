<?php
/**
 * BIT Quiz - Student Quiz Interface
 * One question per screen, timer-based, anti-cheat measures
 * Students CANNOT see results. Shows only question number & time remaining.
 */
require_once 'config.php';

if (!isset($_SESSION['student_id']) || !isset($_SESSION['quiz_active']) || !isset($_SESSION['quiz_id'])) {
    header("Location: index.php");
    exit();
}

$student_id  = $_SESSION['student_id'];
$quiz_id     = (int)$_SESSION['quiz_id'];
$attempt_id  = (int)$_SESSION['attempt_id'];
$time_limit  = (int)$_SESSION['quiz_time_limit'];
$start_time  = (int)$_SESSION['start_time'];

// Verify session matches attempt
$check = $conn->prepare("SELECT id, status FROM student_attempts WHERE id = ? AND student_id = ? AND quiz_id = ?");
$check->bind_param("isi", $attempt_id, $student_id, $quiz_id);
$check->execute();
$attempt = $check->get_result()->fetch_assoc();

if (!$attempt || $attempt['status'] !== 'ACTIVE') {
    session_destroy();
    header("Location: index.php?error=Invalid or expired attempt");
    exit();
}

// Log page refresh (suspicious)
if (!isset($_SESSION['quiz_loaded_once'])) {
    $_SESSION['quiz_loaded_once'] = true;
} else {
    $log = $conn->prepare("INSERT INTO suspicious_logs (student_id, quiz_id, event_type, event_details) VALUES (?, ?, 'PAGE_REFRESH', 'User refreshed quiz page')");
    $log->bind_param("si", $student_id, $quiz_id);
    $log->execute();
}

// Load questions for this quiz (from session or DB)
if (!isset($_SESSION['questions'])) {
    $qstmt = $conn->prepare(
        "SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option
         FROM questions q
         INNER JOIN quiz_questions qq ON q.id = qq.question_id
         WHERE qq.quiz_id = ?
         ORDER BY qq.sort_order ASC"
    );
    $qstmt->bind_param("i", $quiz_id);
    $qstmt->execute();
    $rows = $qstmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $questions = [];
    foreach ($rows as $row) {
        $options = ['A' => $row['option_a'], 'B' => $row['option_b'], 'C' => $row['option_c'], 'D' => $row['option_d']];
        $correct_text = $options[$row['correct_option']];
        $keys = array_keys($options);
        shuffle($keys);
        $shuffled = [];
        $new_correct = '';
        foreach ($keys as $i => $k) {
            $label = chr(65 + $i);
            $shuffled[$label] = $options[$k];
            if ($options[$k] === $correct_text) $new_correct = $label;
        }
        $questions[] = [
            'id' => $row['id'],
            'question_text' => $row['question_text'],
            'options' => $shuffled,
            'correct_option' => $new_correct
        ];
    }
    $_SESSION['questions'] = $questions;
}

$questions = $_SESSION['questions'];
$total_q   = count($questions);
$time_left = $time_limit - (time() - $start_time);

if ($time_left <= 0) {
    header("Location: submit_quiz.php");
    exit();
}

// Load saved answers from DB (auto-save on disconnect)
$saved_answers = [];
$prog = $conn->prepare("SELECT answers_json FROM quiz_progress WHERE attempt_id = ?");
$prog->bind_param("i", $attempt_id);
$prog->execute();
$pr = $prog->get_result()->fetch_assoc();
if ($pr && !empty($pr['answers_json'])) {
    $dec = json_decode($pr['answers_json'], true);
    if (is_array($dec)) $saved_answers = $dec;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - BIT Quiz</title>
    <link rel="stylesheet" href="style.css">
</head>
<body oncontextmenu="return false;">
    <div class="timer-bar">
        <div id="timerProgress" class="timer-progress"></div>
    </div>

    <div class="quiz-container">
        <div class="quiz-header">
            <span>Question <strong id="qNum">1</strong> of <?php echo $total_q; ?></span>
            <span id="timerDisplay" class="timer-display">Time Left: --:--</span>
        </div>

        <form id="quizForm" action="submit_quiz.php" method="POST" onsubmit="return allowFormSubmit;">
            <?php foreach ($questions as $i => $q): ?>
            <div class="question-card <?php echo $i === 0 ? 'active' : ''; ?>" id="q<?php echo $i; ?>">
                <p class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></p>
                <div class="options-grid">
                    <?php foreach ($q['options'] as $label => $text): $sel = isset($saved_answers[$i]) && $saved_answers[$i] === $label ? ' selected' : ''; ?>
                    <label class="option-btn<?php echo $sel; ?>" data-q="<?php echo $i; ?>" data-label="<?php echo htmlspecialchars($label); ?>">
                        <input type="radio" name="ans[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($label); ?>"<?php echo $sel ? ' checked' : ''; ?> style="display:none">
                        <strong><?php echo $label; ?>.</strong> <?php echo htmlspecialchars($text); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="quiz-footer">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeQ(-1); return false;" style="visibility:hidden">Previous</button>
                <button type="button" class="btn" id="nextBtn" onclick="changeQ(1); return false;">Next</button>
                <button type="button" class="btn btn-success" id="submitBtn" style="display:none">Submit Quiz</button>
            </div>
        </form>
    </div>

    <script>
    (function() {
        var currentQ = 0;
        var totalQ = <?php echo $total_q; ?>;
        var timeLeft = <?php echo $time_left; ?>;
        var initialTime = <?php echo $time_limit; ?>;
        var heartbeatInterval;
        var tabSwitchCount = 0;
        var violationLimit = <?php echo defined('VIOLATIONS_AUTO_SUBMIT') ? VIOLATIONS_AUTO_SUBMIT : 5; ?>;
        var allowFormSubmit = false; // Only true when user clicks Submit Quiz

        function updateTimer() {
            if (timeLeft <= 0) {
                allowFormSubmit = true;
                document.getElementById('quizForm').submit();
                return;
            }
            timeLeft--;
            var m = Math.floor(timeLeft / 60);
            var s = timeLeft % 60;
            document.getElementById('timerDisplay').textContent = 'Time Left: ' + m + ':' + (s < 10 ? '0' : '') + s;
            document.getElementById('timerProgress').style.width = (timeLeft / initialTime * 100) + '%';
            if (timeLeft < 300) document.getElementById('timerProgress').style.background = '#ef4444';
        }
        setInterval(updateTimer, 1000);

        function changeQ(dir) {
            var nextQ = currentQ + dir;
            if (nextQ < 0 || nextQ >= totalQ) return;
            var prevEl = document.getElementById('q' + currentQ);
            var nextEl = document.getElementById('q' + nextQ);
            if (!prevEl || !nextEl) return;
            prevEl.classList.remove('active');
            currentQ = nextQ;
            nextEl.classList.add('active');
            document.getElementById('qNum').textContent = currentQ + 1;
            document.getElementById('prevBtn').style.visibility = currentQ === 0 ? 'hidden' : 'visible';
            if (currentQ === totalQ - 1) {
                document.getElementById('nextBtn').style.display = 'none';
                document.getElementById('submitBtn').style.display = 'inline-block';
            } else {
                document.getElementById('nextBtn').style.display = 'inline-block';
                document.getElementById('submitBtn').style.display = 'none';
            }
        }

        // Submit only when user clicks Submit Quiz (prevents Enter key or accidental submit)
        document.getElementById('submitBtn').addEventListener('click', function() {
            allowFormSubmit = true;
            document.getElementById('quizForm').submit();
        });

        document.querySelectorAll('.option-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var q = parseInt(this.dataset.q, 10);
                var label = this.dataset.label;
                document.querySelectorAll('#q' + q + ' .option-btn').forEach(function(b) { b.classList.remove('selected'); });
                this.classList.add('selected');
                this.querySelector('input').checked = true;
            });
        });

        // Heartbeat + auto-save progress (for disconnect recovery)
        function getAnswersJson() {
            var o = {};
            document.querySelectorAll('input[name^="ans["]').forEach(function(inp) {
                var m = inp.name.match(/ans\[(\d+)\]/);
                if (m && inp.checked) o[m[1]] = inp.value;
            });
            return JSON.stringify(o);
        }
        function sendHeartbeat() {
            var data = 'current_q=' + (currentQ + 1) + '&time_left=' + timeLeft + '&answers=' + encodeURIComponent(getAnswersJson());
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'api_heartbeat.php');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(data);
            // Also save progress
            var xhr2 = new XMLHttpRequest();
            xhr2.open('POST', 'api_save_progress.php');
            xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr2.send('current_q=' + (currentQ + 1) + '&answers=' + encodeURIComponent(getAnswersJson()));
        }
        sendHeartbeat();
        heartbeatInterval = setInterval(sendHeartbeat, 3000);

        // Tab switch detection - warn & auto-submit after N violations
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                tabSwitchCount++;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'log_tab_switch.php');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('');
                if (tabSwitchCount >= violationLimit) {
                    clearInterval(heartbeatInterval);
                    document.getElementById('quizForm').submit();
                } else {
                    alert('Warning: Switching tabs during the quiz is not allowed. You have ' + (violationLimit - tabSwitchCount) + ' warning(s) left before auto-submission.');
                }
            }
        });

        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
            alert('Back button is disabled during the quiz.');
        };
        window.addEventListener('beforeunload', function(e) { e.preventDefault(); e.returnValue = ''; });
        document.onkeydown = function(e) {
            if (e.keyCode === 123 || (e.ctrlKey && e.shiftKey && e.keyCode === 73)) return false;
        };
    })();
    </script>
</body>
</html>
