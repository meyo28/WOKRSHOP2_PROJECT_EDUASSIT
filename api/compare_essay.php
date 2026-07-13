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

// Delete old internal reports for this assignment
$del_sql = "DELETE pr FROM plagiarism_report pr
            JOIN code_submission cs ON pr.submission_id = cs.codeSubmission_id AND pr.submission_type = 'code'
            WHERE cs.assignment_id = ? AND pr.source_type = 'internal'";
$stmt = $conn->prepare($del_sql);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$stmt->close();

// Fetch all code submissions that are graded or reviewed
$sql = "SELECT cs.codeSubmission_id, cs.student_id, cs.code_text 
        FROM code_submission cs
        WHERE cs.assignment_id = ? AND cs.status IN ('graded','reviewed')";
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
               VALUES (?, 'code', ?, ?, ?, ?, 'internal', ?)";
$insert_stmt = $conn->prepare($insert_sql);
if (!$insert_stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$inserted = 0;
for ($i = 0; $i < count($submissions); $i++) {
    for ($j = $i+1; $j < count($submissions); $j++) {
        $code1 = $submissions[$i]['code_text'];
        $code2 = $submissions[$j]['code_text'];
        
        // Use token-based similarity (same as your original function)
        $tokens1 = preg_split('/[\s,;(){}\[\]=+<>!&|]+/', $code1);
        $tokens2 = preg_split('/[\s,;(){}\[\]=+<>!&|]+/', $code2);
        $tokens1 = array_filter($tokens1, 'strlen');
        $tokens2 = array_filter($tokens2, 'strlen');
        if (empty($tokens1) || empty($tokens2)) {
            $percent = 0;
        } else {
            $common = count(array_intersect($tokens1, $tokens2));
            $total = count($tokens1) + count($tokens2);
            $percent = ($common * 200) / $total;
        }
        $percent = round($percent, 2);
        
        if ($percent >= 20) {
            $snippet = substr($code1, 0, 200);
            
            // Direction i -> j
            $sub_id_i = $submissions[$i]['codeSubmission_id'];
            $matched_id_j = $submissions[$j]['codeSubmission_id'];
            $matched_student_j = $submissions[$j]['student_id'];
            $source_title = "Submission " . $matched_id_j;
            $insert_stmt->bind_param("idiiss", $sub_id_i, $percent, $matched_id_j, $matched_student_j, $source_title, $snippet);
            $insert_stmt->execute();
            $inserted++;
            
            // Direction j -> i
            $sub_id_j = $submissions[$j]['codeSubmission_id'];
            $matched_id_i = $submissions[$i]['codeSubmission_id'];
            $matched_student_i = $submissions[$i]['student_id'];
            $source_title = "Submission " . $matched_id_i;
            $insert_stmt->bind_param("idiiss", $sub_id_j, $percent, $matched_id_i, $matched_student_i, $source_title, $snippet);
            $insert_stmt->execute();
            $inserted++;
        }
    }
}
$insert_stmt->close();

echo json_encode(['message' => "Code comparison done. $inserted records inserted."]);
?>