<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$assignment_id = (int)($_GET['assignment_id'] ?? 0);
$type = $_GET['type'] ?? '';
if (!$assignment_id || !in_array($type, ['essay', 'code'])) {
    echo json_encode(['error' => 'Missing or invalid parameters']);
    exit;
}

if ($type == 'essay') {
    $sql = "SELECT 
                pr.similarity_percentage,
                s1.full_name AS student1_name, s1.matric_no AS student1_matric, es1.content AS content1, es1.essaySubmission_id AS sub1_id,
                s2.full_name AS student2_name, s2.matric_no AS student2_matric, es2.content AS content2, es2.essaySubmission_id AS sub2_id
            FROM plagiarism_report pr
            JOIN essay_submission es1 ON pr.submission_id = es1.essaySubmission_id
            JOIN student s1 ON es1.student_id = s1.student_id
            JOIN essay_submission es2 ON pr.matched_submission_id = es2.essaySubmission_id
            JOIN student s2 ON es2.student_id = s2.student_id
            WHERE es1.assignment_id = ? AND pr.source_type = 'internal' AND pr.submission_type = 'essay'
            GROUP BY LEAST(pr.submission_id, pr.matched_submission_id), GREATEST(pr.submission_id, pr.matched_submission_id)
            ORDER BY pr.similarity_percentage DESC";
} else {
    $sql = "SELECT 
                pr.similarity_percentage,
                s1.full_name AS student1_name, s1.matric_no AS student1_matric, cs1.code_text AS content1, cs1.codeSubmission_id AS sub1_id,
                s2.full_name AS student2_name, s2.matric_no AS student2_matric, cs2.code_text AS content2, cs2.codeSubmission_id AS sub2_id
            FROM plagiarism_report pr
            JOIN code_submission cs1 ON pr.submission_id = cs1.codeSubmission_id
            JOIN student s1 ON cs1.student_id = s1.student_id
            JOIN code_submission cs2 ON pr.matched_submission_id = cs2.codeSubmission_id
            JOIN student s2 ON cs2.student_id = s2.student_id
            WHERE cs1.assignment_id = ? AND pr.source_type = 'internal' AND pr.submission_type = 'code'
            GROUP BY LEAST(pr.submission_id, pr.matched_submission_id), GREATEST(pr.submission_id, pr.matched_submission_id)
            ORDER BY pr.similarity_percentage DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

$matches = [];
while ($row = $result->fetch_assoc()) {
    $matches[] = [
        'similarity' => round($row['similarity_percentage'], 2),
        'student1_name' => $row['student1_name'],
        'student1_matric' => $row['student1_matric'],
        'student2_name' => $row['student2_name'],
        'student2_matric' => $row['student2_matric'],
        'content1' => $row['content1'],
        'content2' => $row['content2'],
        'sub1_id' => $row['sub1_id'],
        'sub2_id' => $row['sub2_id']
    ];
}
echo json_encode(['matches' => $matches]);
?>