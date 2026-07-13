<?php
session_start();
include 'includes/config.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Only lecturers can run this
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$assignment_id = $_POST['assignment_id'] ?? 0;
$type = $_POST['type'] ?? '';

if (!$assignment_id || !in_array($type, ['essay','code'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid parameters']));
}

try {
    // Delete old internal reports for this assignment
    if ($type == 'essay') {
        $del_sql = "DELETE pr FROM plagiarism_report pr
                    JOIN essay_submission es ON pr.submission_id = es.essaySubmission_id AND pr.submission_type = 'essay'
                    WHERE es.assignment_id = ? AND pr.source_type = 'internal'";
    } else {
        $del_sql = "DELETE pr FROM plagiarism_report pr
                    JOIN code_submission cs ON pr.submission_id = cs.codeSubmission_id AND pr.submission_type = 'code'
                    WHERE cs.assignment_id = ? AND pr.source_type = 'internal'";
    }
    $stmt_del = $conn->prepare($del_sql);
    if (!$stmt_del) throw new Exception("Delete prepare failed: " . $conn->error);
    $stmt_del->bind_param("i", $assignment_id);
    $stmt_del->execute();
    $stmt_del->close();

    // Get all submissions for this assignment
    if ($type == 'essay') {
        $sub_sql = "SELECT essaySubmission_id AS sub_id, student_id, content FROM essay_submission WHERE assignment_id = ? AND status = 'graded'";
    } else {
        $sub_sql = "SELECT codeSubmission_id AS sub_id, student_id, code_text AS content FROM code_submission WHERE assignment_id = ? AND status = 'graded'";
    }
    $stmt_sub = $conn->prepare($sub_sql);
    if (!$stmt_sub) throw new Exception("Submission prepare failed: " . $conn->error);
    $stmt_sub->bind_param("i", $assignment_id);
    $stmt_sub->execute();
    $result = $stmt_sub->get_result();
    $submissions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_sub->close();

    if (count($submissions) < 2) {
        echo json_encode(['message' => 'Not enough graded submissions to compare.']);
        exit;
    }

    // Compare each pair
    $inserted = 0;
    for ($i = 0; $i < count($submissions); $i++) {
        for ($j = $i+1; $j < count($submissions); $j++) {
            $content1 = $submissions[$i]['content'];
            $content2 = $submissions[$j]['content'];
            
            // Simple similarity – replace with your NLP method
            similar_text($content1, $content2, $percent);
            $percent = round($percent, 2);
            
            if ($percent >= 20) { // threshold 20%
                // Extract a snippet (first 200 chars of first submission)
                $snippet = substr($content1, 0, 200);
                
                // Insert both directions
                $insert_sql = "INSERT INTO plagiarism_report 
                               (submission_id, submission_type, similarity_percentage, matched_submission_id, matched_student_id, matched_source_title, source_type, matched_text)
                               VALUES (?, ?, ?, ?, ?, ?, 'internal', ?)";
                $stmt_ins = $conn->prepare($insert_sql);
                if (!$stmt_ins) throw new Exception("Insert prepare failed: " . $conn->error);
                
                // Row i matched with j
                $source_title = "Submission " . $submissions[$j]['sub_id'];
                $stmt_ins->bind_param("isdisss", 
                    $submissions[$i]['sub_id'], 
                    $type, 
                    $percent, 
                    $submissions[$j]['sub_id'], 
                    $submissions[$j]['student_id'], 
                    $source_title, 
                    $snippet
                );
                $stmt_ins->execute();
                $inserted++;
                
                // Row j matched with i
                $source_title = "Submission " . $submissions[$i]['sub_id'];
                $stmt_ins->bind_param("isdisss", 
                    $submissions[$j]['sub_id'], 
                    $type, 
                    $percent, 
                    $submissions[$i]['sub_id'], 
                    $submissions[$i]['student_id'], 
                    $source_title, 
                    $snippet
                );
                $stmt_ins->execute();
                $inserted++;
                $stmt_ins->close();
            }
        }
    }
    
    echo json_encode(['message' => "Plagiarism check completed. $inserted records inserted."]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>