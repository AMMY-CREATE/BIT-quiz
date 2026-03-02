<?php
/**
 * BIT Quiz - Student Login
 * Fields: Roll Number, Name, Class/Section, Quiz Code
 */
require_once 'config.php';

// Redirect if already in quiz
if (isset($_SESSION['student_id']) && isset($_SESSION['quiz_active']) && isset($_SESSION['quiz_id'])) {
    header("Location: quiz.php");
    exit();
}

// Redirect admin link
if (isset($_GET['admin'])) {
    header("Location: admin_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIT Quiz - Student Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-container">
        <h1>BIT Quiz</h1>
        <p class="auth-subtitle">Enter your details to start the quiz</p>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-msg"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['submitted'])): ?>
            <div class="success-msg">Quiz submitted successfully. You cannot access results.</div>
        <?php endif; ?>

        <form action="auth_student.php" method="POST" class="auth-form">
            <div class="form-group">
                <label for="student_id">Roll Number / Student ID</label>
                <input type="text" id="student_id" name="student_id" placeholder="e.g. BIT2024001" required maxlength="50">
            </div>
            <div class="form-group">
                <label for="student_name">Name</label>
                <input type="text" id="student_name" name="student_name" placeholder="Your full name" required maxlength="150">
            </div>
            <div class="form-group">
                <label for="student_class">Class / Section</label>
                <input type="text" id="student_class" name="student_class" placeholder="e.g. B.Tech CSE-A" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="quiz_code">Quiz Code</label>
                <input type="text" id="quiz_code" name="quiz_code" placeholder="Enter code provided by instructor" required maxlength="20" autocomplete="off">
            </div>
            <button type="submit" class="btn">Start Quiz</button>
        </form>
        <p class="auth-footer"><a href="index.php?admin=1">Admin Login</a></p>
    </div>
</body>
</html>
