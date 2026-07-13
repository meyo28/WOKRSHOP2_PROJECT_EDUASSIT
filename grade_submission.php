<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$submission_id = (int)($_POST['submission_id'] ?? 0);
$type = $_POST['type'] ?? '';
$grade = $_POST['grade'] ?? '';
$score = (float)($_POST['score'] ?? 0);

// Convert letter grade to score if not provided
if (empty($score) && !empty($grade)) {
    $grade_map = ['A' => 90, 'B' => 80, 'C' => 70, 'D' => 60, 'F' => 0];
    $score = $grade_map[$grade] ?? 0;
}

if (!$submission_id || !in_array($type, ['essay', 'code']) || empty($grade)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

if ($type == 'essay') {
    $sql = "UPDATE essay_submission SET final_grade = ?, total_score = ?, status = 'reviewed' WHERE essaySubmission_id = ?";
} else {
    $sql = "UPDATE code_submission SET final_grade = ?, total_score = ?, status = 'reviewed' WHERE codeSubmission_id = ?";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param("sdi", $grade, $score, $submission_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>