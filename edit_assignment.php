<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if(!$assignment_id) {
    die("Assignment not selected.");
}

// Get lecturer_id from staff_id
$query_lect = "SELECT lecturer_id FROM lecturer WHERE staff_id = ?";
$stmt_lect = $conn->prepare($query_lect);
$stmt_lect->bind_param("s", $staff_id);
$stmt_lect->execute();
$result_lect = $stmt_lect->get_result();
if ($row_lect = $result_lect->fetch_assoc()) {
    $lecturer_id = $row_lect['lecturer_id'];
}
$stmt_lect->close();

// Fetch assignment data
$sql = "SELECT * FROM assignment WHERE assignment_id = ? AND lecturer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $assignment_id, $lecturer_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$assignment) {
    die("Assignment not found or you don't have permission to edit.");
}

// Check if assignment has submissions
$check_sql = "
    SELECT 
        (SELECT COUNT(*) FROM essay_submission WHERE assignment_id = ?) as essay_count,
        (SELECT COUNT(*) FROM code_submission WHERE assignment_id = ?) as code_count
";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $assignment_id, $assignment_id);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

$has_submissions = ($result['essay_count'] > 0 || $result['code_count'] > 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    
    // Fix: Use existing assignment type if not provided in POST (when field is disabled)
    $type = isset($_POST['type']) ? $_POST['type'] : $assignment['type'];
    
    $update_sql = "UPDATE assignment SET tittle=?, description=?, start_date=?, due_date=?, type=? WHERE assignment_id=? AND lecturer_id=?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssssii", $title, $description, $start_date, $due_date, $type, $assignment_id, $lecturer_id);
    
    if ($update_stmt->execute()) {
        $success_msg = "✅ Assignment updated successfully!";
        // Refresh assignment data
        $sql = "SELECT * FROM assignment WHERE assignment_id = ? AND lecturer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $assignment_id, $lecturer_id);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $error_msg = "❌ Error updating assignment: " . $conn->error;
    }
    $update_stmt->close();
}

$currentDateTime = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment - SILS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; min-height: 100vh; }
        .header { 
            background: linear-gradient(135deg, #003366 0%, #1a4d8c 50%, #2d6a9f 100%);
            color: white; 
            padding: 20px; 
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,51,102,0.3);
        }
        .container { max-width: 700px; margin: 40px auto; padding: 30px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); }
        .back-link { 
            display: inline-block; 
            margin-bottom: 20px; 
            color: #003366; 
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .back-link:hover { 
            transform: translateX(-4px);
            color: #1a4d8c;
        }
        .form-group { margin-bottom: 22px; }
        label { 
            font-weight: 600; 
            display: block; 
            margin-bottom: 8px; 
            color: #333; 
            font-size: 14px;
        }
        input, select, textarea { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            font-family: inherit; 
            font-size: 14px;
            transition: all 0.3s;
            background: #fafbfc;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #003366;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(0,51,102,0.08);
        }
        textarea { height: 100px; resize: vertical; }
        input:disabled { background: #f0f0f0; cursor: not-allowed; }

        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #86efac; }
        .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        
        .btn-submit { 
            background: linear-gradient(135deg, #003366, #1a4d8c);
            color: white; 
            border: none; 
            padding: 14px 24px; 
            border-radius: 10px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            width: 100%; 
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,51,102,0.3);
        }
        .btn-submit:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,51,102,0.4);
        }
        .btn-submit:disabled { 
            background: #ccc; 
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .info-note { 
            background: #eff6ff; 
            color: #1e40af; 
            padding: 14px; 
            border-radius: 10px; 
            font-size: 13px; 
            margin-top: 8px; 
            display: flex; 
            align-items: flex-start; 
            gap: 10px;
            border-left: 4px solid #3b82f6;
        }
        .info-note .icon { font-size: 20px; flex-shrink: 0; }
        
        .required-star { color: #dc2626; margin-left: 2px; }
        
        @media (max-width: 640px) {
            .container { margin: 20px; padding: 20px; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>✏️ Edit Assignment</h1>
</div>

<div class="container">

    <a href="class_dashboard.php?class_id=<?php echo $assignment['class_id']; ?>" class="back-link">← Back to Class</a>

    <?php if (isset($error_msg)): ?>
        <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
    <?php endif; ?>
    
    <?php if ($has_submissions): ?>
        <div class="alert alert-warning">
            ⚠️ <strong>Warning:</strong> This assignment already has submissions. You can only edit title, description, and dates. Type cannot be changed.
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Assignment Title <span class="required-star">*</span></label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($assignment['tittle']); ?>" required>
        </div>

        <div class="form-group">
            <label>Description (optional)</label>
            <textarea name="description"><?php echo htmlspecialchars($assignment['description']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Start Date & Time <span class="required-star">*</span></label>
            <input type="datetime-local" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['start_date'])); ?>" required>
        </div>

        <div class="form-group">
            <label>Due Date & Time <span class="required-star">*</span></label>
            <input type="datetime-local" name="due_date" value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" required>
        </div>

        <div class="form-group">
            <label>Assignment Type <span class="required-star">*</span></label>
            <select name="type" required <?php echo $has_submissions ? 'disabled' : ''; ?>>
                <option value="essay" <?php echo $assignment['type'] == 'essay' ? 'selected' : ''; ?>>📄 Essay</option>
                <option value="code" <?php echo $assignment['type'] == 'code' ? 'selected' : ''; ?>>💻 Code</option>
            </select>
            <?php if ($has_submissions): ?>
                <input type="hidden" name="type" value="<?php echo $assignment['type']; ?>">
                <small style="color: #666; display: block; margin-top: 4px;">Type cannot be changed because submissions exist.</small>
            <?php endif; ?>
        </div>

        <div class="info-note" style="margin-bottom: 20px;">
            <span class="icon">ℹ️</span>
            <div>
                <strong>Note:</strong> 
                This assignment is for <strong><?php echo htmlspecialchars($assignment['class_name'] ?? 'your class'); ?></strong>.
                <?php if ($has_submissions): ?>
                    <br>⚠️ Changes to dates may affect existing submissions.
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" name="update_assignment" class="btn-submit">💾 Update Assignment</button>
    </form>

</div>

</body>
</html>