<?php
session_start();
include 'includes/config.php';

// Ensure only students and lecturer can access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] != 'student' && $_SESSION['user_type'] != 'lecturer')) {
    header("Location: index.php?error=login_required");
    exit();
}

$student_id = $_SESSION['user_id'];
$error = '';
$internal_result = null;
$external_result = null;
$input_text = '';
$selected_assignment = '';

// Fetch assignments for classes the student is enrolled in
$query = "SELECT a.assignment_id, a.tittle, c.class_name 
          FROM ASSIGNMENT a 
          JOIN CLASS c ON a.class_id = c.class_id 
          JOIN ENROLLMENT e ON c.class_id = e.class_id 
          WHERE e.student_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$assignments_result = mysqli_stmt_get_result($stmt);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_assignment = $_POST['assignment_id'] ?? '';
    $check_internal = isset($_POST['check_internal']);
    $check_external = isset($_POST['check_external']);
    
    // 1. Get the text (File upload or Copy/Paste)
    if (isset($_FILES['essay_file']) && $_FILES['essay_file']['error'] == UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['essay_file']['name'], PATHINFO_EXTENSION));
        if ($file_ext == 'txt') {
            $input_text = file_get_contents($_FILES['essay_file']['tmp_name']);
        } else {
            $error = "Only .txt files are supported. Please paste your text instead.";
        }
    } elseif (!empty($_POST['essay_text'])) {
        $input_text = trim($_POST['essay_text']);
    } else {
        $error = "Please provide some text to scan.";
    }

    if (!$check_internal && !$check_external) {
        $error = "Please select at least one scanning engine (Internal or External).";
    }

    // 2. Process the Checks
    if (!empty($input_text) && empty($error)) {
        
        // --- INTERNAL CHECK LOGIC ---
        if ($check_internal) {
            if (empty($selected_assignment)) {
                $error = "Assignment selection is required for Internal Check.";
            } else {
                $internal_query = "SELECT s.full_name, e.content 
                                   FROM ESSAY_SUBMISSION e 
                                   JOIN STUDENT s ON e.student_id = s.student_id 
                                   WHERE e.assignment_id = ? AND e.student_id != ?";
                $int_stmt = mysqli_prepare($conn, $internal_query);
                mysqli_stmt_bind_param($int_stmt, "ii", $selected_assignment, $student_id);
                mysqli_stmt_execute($int_stmt);
                $peer_essays = mysqli_stmt_get_result($int_stmt);

                if (mysqli_num_rows($peer_essays) == 0) {
                    $internal_result = [
                        'percentage' => 0,
                        'status' => 'Pass (First Submitter)',
                        'matched_with' => 'N/A'
                    ];
                } else {
                    $highest_match = 0;
                    $matched_student = "None";
                    while ($row = mysqli_fetch_assoc($peer_essays)) {
                        similar_text($input_text, $row['content'], $percent);
                        if ($percent > $highest_match) {
                            $highest_match = round($percent, 2);
                            $matched_student = $row['full_name'];
                        }
                    }
                    $internal_result = [
                        'percentage' => $highest_match,
                        'status' => ($highest_match > 30) ? 'Warning: High Similarity' : 'Pass',
                        'matched_with' => ($highest_match > 0) ? $matched_student : 'N/A'
                    ];
                }
            }
        }

        // --- EXTERNAL CHECK LOGIC ---
        if ($check_external && empty($error)) {
            $external_result = callExternalPlagiarismAPI($input_text);
        }
    }
}

function callExternalPlagiarismAPI($text) {
    // Simulation of External API Result
    sleep(1); 
    $chance = rand(1, 100);
    if ($chance > 70) {
        return [
            'percentage' => rand(10, 40),
            'status' => 'Warning: Web Matches Found',
            'source' => 'Google Scholar / Web'
        ];
    }
    return [
        'percentage' => rand(0, 5),
        'status' => 'Pass: Original Content',
        'source' => 'N/A'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Plagiarism Check - SILS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; padding-bottom: 50px; }
        .header { background: #8b0000; color: white; padding: 20px; text-align: center; }
        .container { max-width: 900px; margin: 30px auto; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        textarea { height: 180px; }
        
        .engine-selector { background: #fff5f5; padding: 15px; border: 1px solid #ffcccc; border-radius: 8px; display: flex; gap: 30px; }
        .engine-selector label { font-weight: normal; margin-bottom: 0; cursor: pointer; display: flex; align-items: center; gap: 8px; }

        .btn { background: #8b0000; color: white; padding: 14px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 600; font-size: 16px; margin-top: 10px; }
        .btn:hover { background: #660000; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #666; text-decoration: none; font-size: 14px; }
        
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .results-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .res-card { padding: 20px; border-radius: 10px; text-align: center; border: 2px solid #eee; }
        .res-card.pass { border-color: #28a745; background: #f8fff9; }
        .res-card.warn { border-color: #dc3545; background: #fff8f8; }
        
        .score-circle { width: 80px; height: 80px; border-radius: 50%; border: 4px solid #eee; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 20px; font-weight: bold; }
        .pass .score-circle { border-color: #28a745; color: #28a745; }
        .warn .score-circle { border-color: #dc3545; color: #dc3545; }
    </style>
</head>
<body>

<div class="header">
    <h1>🔍 Plagiarism Scanner</h1>
</div>

<div class="container">
    <a href="student_dashboard.php" class="back-link">← Return to Dashboard</a>

    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label>1. Select Target Assignment</label>
            <select name="assignment_id">
                <option value="">-- Choose Assignment --</option>
                <?php while($row = mysqli_fetch_assoc($assignments_result)): ?>
                    <option value="<?php echo $row['assignment_id']; ?>" <?php echo ($selected_assignment == $row['assignment_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($row['class_name'] . " : " . $row['tittle']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>2. Choose Scan Type</label>
            <div class="engine-selector">
                <label><input type="checkbox" name="check_internal" <?php echo (!isset($_POST['submit']) || isset($_POST['check_internal'])) ? 'checked' : ''; ?>> 🏫 Internal (Classmates)</label>
                <label><input type="checkbox" name="check_external" <?php echo (!isset($_POST['submit']) || isset($_POST['check_external'])) ? 'checked' : ''; ?>> 🌐 External (Web/Scholar)</label>
            </div>
        </div>

        <div class="form-group">
            <label>3. Upload or Paste Content</label>
            <input type="file" name="essay_file" accept=".txt" style="margin-bottom: 10px;">
            <textarea name="essay_text" placeholder="Paste essay content here..."><?php echo htmlspecialchars($input_text); ?></textarea>
        </div>

        <button type="submit" name="submit" class="btn">Start Plagiarism Analysis</button>
    </form>

    <?php if($internal_result || $external_result): ?>
        <div class="results-grid">
            
            <?php if($internal_result): ?>
            <div class="res-card <?php echo ($internal_result['percentage'] > 25) ? 'warn' : 'pass'; ?>">
                <h3>Internal Result</h3>
                <div class="score-circle"><?php echo $internal_result['percentage']; ?>%</div>
                <p><strong>Status:</strong> <?php echo $internal_result['status']; ?></p>
                <p><small>Matched with: <?php echo $internal_result['matched_with']; ?></small></p>
            </div>
            <?php endif; ?>

            <?php if($external_result): ?>
            <div class="res-card <?php echo ($external_result['percentage'] > 25) ? 'warn' : 'pass'; ?>">
                <h3>External Result</h3>
                <div class="score-circle"><?php echo $external_result['percentage']; ?>%</div>
                <p><strong>Status:</strong> <?php echo $external_result['status']; ?></p>
                <p><small>Source: <?php echo $external_result['source']; ?></small></p>
            </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>
</div>

</body>
</html>