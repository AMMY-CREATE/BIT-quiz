<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_type']) && isset($_SESSION['student_id'])) {
    $student_id = $_SESSION['student_id'];
    $event_type = $conn->real_escape_string($_POST['event_type']);
    $details = isset($_POST['details']) ? $conn->real_escape_string($_POST['details']) : '';
    
    $stmt = $conn->prepare("INSERT INTO suspicious_logs (student_id, event_type, event_details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $student_id, $event_type, $details);
    $stmt->execute();
}
?>
