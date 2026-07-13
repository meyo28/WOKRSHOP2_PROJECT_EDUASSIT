<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
if (!$assignment_id) {
    echo json_encode(['error' => 'Assignment ID required']);
    exit;
}

// Delete old reports
$del_sql = "DELETE pr FROM plagiarism_report pr
            JOIN essay_submission es ON pr.submission_id = es.essaySubmission_id AND pr.submission_type = 'essay'
            WHERE es.assignment_id = ? AND pr.source_type = 'internal'";
$stmt = $conn->prepare($del_sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$stmt->close();

// Fetch submissions
$sql = "SELECT es.essaySubmission_id, es.student_id, es.content 
        FROM essay_submission es
        WHERE es.assignment_id = ? AND es.status IN ('graded','reviewed')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($submissions) < 2) {
    echo json_encode(['message' => 'Need at least 2 graded submissions']);
    exit;
}

$insert_sql = "INSERT INTO plagiarism_report 
               (submission_id, submission_type, similarity_percentage, matched_submission_id, matched_student_id, matched_source_title, source_type, matched_text)
               VALUES (?, 'essay', ?, ?, ?, ?, 'internal', ?)";
$insert_stmt = $conn->prepare($insert_sql);
if (!$insert_stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$inserted = 0;
for ($i = 0; $i < count($submissions); $i++) {
    for ($j = $i+1; $j < count($submissions); $j++) {
        similar_text($submissions[$i]['content'], $submissions[$j]['content'], $percent);
        $percent = round($percent, 2);
        if ($percent >= 20) {
            $snippet = substr($submissions[$i]['content'], 0, 200);
            
            // Direction i -> j
            $sub_id_i = $submissions[$i]['essaySubmission_id'];
            $matched_id_j = $submissions[$j]['essaySubmission_id'];
            $matched_student_j = $submissions[$j]['student_id'];
            $source_title = "Submission " . $matched_id_j;
            $insert_stmt->bind_param("idiiss", $sub_id_i, $percent, $matched_id_j, $matched_student_j, $source_title, $snippet);
            $insert_stmt->execute();
            $inserted++;
            
            // Direction j -> i
            $sub_id_j = $submissions[$j]['essaySubmission_id'];
            $matched_id_i = $submissions[$i]['essaySubmission_id'];
            $matched_student_i = $submissions[$i]['student_id'];
            $source_title = "Submission " . $matched_id_i;
            $insert_stmt->bind_param("idiiss", $sub_id_j, $percent, $matched_id_i, $matched_student_i, $source_title, $snippet);
            $insert_stmt->execute();
            $inserted++;
        }
    }
}
$insert_stmt->close();

echo json_encode(['message' => "Essay comparison done. $inserted records inserted."]);
?>