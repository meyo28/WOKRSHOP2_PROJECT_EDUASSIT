<?php
session_start();
include 'includes/config.php';

// Lecturer authentication
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if(!$class_id) {
    die("Please select a class to view history.");
}

// Get lecturer_id from staff_id
$stmt_lect = $conn->prepare("SELECT lecturer_id FROM lecturer WHERE staff_id = ?");
$stmt_lect->bind_param("s", $staff_id);
$stmt_lect->execute();
$result_lect = $stmt_lect->get_result();
if($row = $result_lect->fetch_assoc()){
    $lecturer_id = $row['lecturer_id'];
} else {
    die("Lecturer not found.");
}
$stmt_lect->close();

// Check role for THIS SPECIFIC class
$role_query = "SELECT role FROM course_lecturer WHERE class_id = ? AND lecturer_id = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("ii", $class_id, $lecturer_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$role_data = $role_result->fetch_assoc();
$role_stmt->close();

if(!$role_data) {
    die("You are not assigned to this class.");
}

$is_penyelaras = ($role_data['role'] == 'penyelaras');

// Get class name
$class_stmt = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$class_stmt->bind_param("i", $class_id);
$class_stmt->execute();
$class_name = $class_stmt->get_result()->fetch_assoc()['class_name'] ?? 'Unknown';
$class_stmt->close();

// ==========================================
// HANDLE PDF REPORT GENERATION
// ==========================================
if (isset($_GET['generate_report']) && isset($_GET['assignment_id'])) {
    $assignment_id = (int)$_GET['assignment_id'];
    
    // Fetch assignment details
    $stmt = $conn->prepare("SELECT a.tittle, a.type, a.class_id, c.class_name, c.class_code 
                            FROM assignment a 
                            JOIN class c ON a.class_id = c.class_id 
                            WHERE a.assignment_id = ?");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $assignment_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$assignment_data) {
        die("Assignment not found.");
    }
    
    $submission_type = $assignment_data['type'];
    $table = ($submission_type == 'essay') ? 'essay_submission' : 'code_submission';
    
    // FIX: Both Penyelaras and Pensyarah only see students from THEIR group
    // Get students from the lecturer's group only
    $sql_students = "
        SELECT s.student_id, s.matric_no, s.full_name, s.program,
               e.lecturer_id, l.full_name AS lecturer_name,
               (SELECT COUNT(*) FROM $table WHERE assignment_id = ? AND student_id = s.student_id) as has_submitted
        FROM student s
        JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ? AND e.lecturer_id = ?
        JOIN lecturer l ON e.lecturer_id = l.lecturer_id
        GROUP BY s.student_id
        ORDER BY s.matric_no ASC
    ";
    $stmt = $conn->prepare($sql_students);
    $stmt->bind_param("iii", $assignment_id, $class_id, $lecturer_id);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Calculate statistics
    $total_students = count($students);
    $submitted_count = 0;
    $graded_count = 0;
    $grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
    $total_score = 0;
    
    foreach ($students as $s) {
        if ($s['has_submitted'] > 0) {
            $submitted_count++;
            // Get grade if exists
            $grade_sql = "SELECT final_grade, total_score FROM $table WHERE assignment_id = ? AND student_id = ?";
            $grade_stmt = $conn->prepare($grade_sql);
            $grade_stmt->bind_param("ii", $assignment_id, $s['student_id']);
            $grade_stmt->execute();
            $grade_result = $grade_stmt->get_result()->fetch_assoc();
            $grade_stmt->close();
            
            if ($grade_result && !empty($grade_result['final_grade'])) {
                $graded_count++;
                if (isset($grade_distribution[$grade_result['final_grade']])) {
                    $grade_distribution[$grade_result['final_grade']]++;
                }
                $total_score += $grade_result['total_score'] ?? 0;
            }
        }
    }
    
    $not_submitted = $total_students - $submitted_count;
    $average_score = $graded_count > 0 ? round($total_score / $graded_count, 2) : 0;
    
    // Output PDF as HTML
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Grade Report - <?php echo htmlspecialchars($assignment_data['tittle']); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: Arial, Helvetica, sans-serif; 
                padding: 30px; 
                background: white; 
                color: #333;
                font-size: 12px;
            }
            .header {
                background: #003366;
                color: white;
                padding: 25px 30px;
                border-radius: 6px;
                margin-bottom: 25px;
            }
            .header h1 { font-size: 22px; margin-bottom: 3px; }
            .header p { opacity: 0.8; font-size: 13px; }
            .header .sub { font-size: 14px; margin-top: 5px; opacity: 0.9; }
            
            .info-box {
                background: #f8f9fa;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 15px 20px;
                margin-bottom: 20px;
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10px;
            }
            .info-box .item { font-size: 12px; }
            .info-box .label { color: #666; }
            .info-box .value { font-weight: 600; color: #003366; }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 12px;
                margin-bottom: 25px;
            }
            .stat-box {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 12px 15px;
                text-align: center;
            }
            .stat-box .number { 
                font-size: 24px; 
                font-weight: 700; 
                color: #003366; 
            }
            .stat-box .number.green { color: #16a34a; }
            .stat-box .number.orange { color: #f59e0b; }
            .stat-box .number.red { color: #dc2626; }
            .stat-box .number.gray { color: #64748b; }
            .stat-box .label { font-size: 10px; color: #666; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
            
            .grade-distribution {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                background: #f8f9fa;
                padding: 10px 15px;
                border-radius: 6px;
                margin-bottom: 20px;
                border: 1px solid #e2e8f0;
            }
            .grade-distribution .grade-item {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 12px;
            }
            .grade-distribution .grade-box {
                display: inline-block;
                width: 24px;
                height: 24px;
                border-radius: 4px;
                text-align: center;
                line-height: 24px;
                font-weight: 700;
                font-size: 11px;
                color: white;
            }
            .grade-box.a { background: #16a34a; }
            .grade-box.b { background: #22c55e; }
            .grade-box.c { background: #eab308; }
            .grade-box.d { background: #f59e0b; }
            .grade-box.f { background: #dc2626; }
            
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 15px 0; 
                font-size: 11px;
            }
            th { 
                background: #003366; 
                color: white; 
                padding: 8px 10px; 
                text-align: left;
                font-weight: 600;
            }
            td { padding: 7px 10px; border-bottom: 1px solid #eee; }
            tr:nth-child(even) { background: #f9fafb; }
            tr:hover { background: #f1f4f9; }
            tr.not-submitted { opacity: 0.6; }
            tr.not-submitted td { color: #888; }
            
            .grade-badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 4px;
                font-weight: 700;
                font-size: 11px;
                color: white;
                min-width: 30px;
                text-align: center;
            }
            .grade-badge.a { background: #16a34a; }
            .grade-badge.b { background: #22c55e; }
            .grade-badge.c { background: #eab308; }
            .grade-badge.d { background: #f59e0b; }
            .grade-badge.f { background: #dc2626; }
            .grade-badge.na { background: #9ca3af; }
            .grade-badge.not-submitted { background: #e2e8f0; color: #64748b; }
            
            .footer {
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                font-size: 10px;
                color: #888;
                text-align: center;
            }
            
            .summary-box {
                background: #f0f4ff;
                border: 1px solid #dbeafe;
                border-radius: 6px;
                padding: 12px 18px;
                margin: 15px 0;
                font-size: 12px;
            }
            .summary-box strong { color: #003366; }
            
            .status-badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 10px;
                font-size: 10px;
                font-weight: 600;
            }
            .status-badge.submitted { background: #dcfce7; color: #166534; }
            .status-badge.not-submitted { background: #f1f5f9; color: #64748b; }
            
            @media print {
                body { padding: 15px; }
                .no-print { display: none; }
            }
            @media (max-width: 768px) {
                .info-box { grid-template-columns: 1fr; }
                .stats-grid { grid-template-columns: repeat(2, 1fr); }
            }
            
            .print-btn {
                background: #003366;
                color: white;
                border: none;
                padding: 10px 25px;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                margin-bottom: 20px;
            }
            .print-btn:hover { background: #1a4d8c; }
            
            .back-btn {
                display: inline-block;
                background: #6c757d;
                color: white;
                padding: 10px 25px;
                border-radius: 6px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                margin-left: 10px;
            }
            .back-btn:hover { background: #5a6268; }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom:15px;">
            <button onclick="window.print()" class="print-btn">
                🖨️ Print / Save as PDF
            </button>
            <a href="lecture_history_plagiarism.php?class_id=<?php echo $class_id; ?>" class="back-btn">
                ← Back to History
            </a>
            <p style="font-size:12px; color:#666; margin-top:8px;">
                💡 Tip: Click "Print / Save as PDF" and choose "Save as PDF" in the print dialog.
            </p>
        </div>
        
        <div class="header">
            <h1>📊 Student Grade Report</h1>
            <div class="sub"><?php echo htmlspecialchars($assignment_data['tittle']); ?></div>
            <p><?php echo htmlspecialchars($assignment_data['class_name']); ?> (<?php echo htmlspecialchars($assignment_data['class_code']); ?>)</p>
            <p style="font-size:12px; color:#e0e7ff; margin-top:5px;">🔒 Showing <strong>My Group</strong> only</p>
        </div>
        
        <div class="info-box">
            <div class="item"><span class="label">Assignment Type:</span> <span class="value"><?php echo ucfirst($submission_type); ?></span></div>
            <div class="item"><span class="label">Generated By:</span> <span class="value"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo htmlspecialchars($staff_id); ?>)</span></div>
            <div class="item"><span class="label">Generated On:</span> <span class="value"><?php echo date('d/m/Y h:i A'); ?></span></div>
            <div class="item"><span class="label">Role:</span> <span class="value"><?php echo $is_penyelaras ? 'Penyelaras' : 'Pensyarah'; ?></span></div>
            <div class="item"><span class="label">My Students:</span> <span class="value"><?php echo $total_students; ?></span></div>
            <div class="item"><span class="label">Submitted:</span> <span class="value"><?php echo $submitted_count; ?> / <?php echo $total_students; ?></span></div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-box">
                <div class="number"><?php echo $total_students; ?></div>
                <div class="label">My Students</div>
            </div>
            <div class="stat-box">
                <div class="number green"><?php echo $submitted_count; ?></div>
                <div class="label">Submitted</div>
            </div>
            <div class="stat-box">
                <div class="number <?php echo $not_submitted > 0 ? 'red' : 'green'; ?>"><?php echo $not_submitted; ?></div>
                <div class="label">Not Submitted</div>
            </div>
            <div class="stat-box">
                <div class="number <?php echo $graded_count > 0 ? 'green' : 'orange'; ?>"><?php echo $graded_count; ?></div>
                <div class="label">Graded</div>
            </div>
            <div class="stat-box">
                <div class="number green"><?php echo $average_score; ?>%</div>
                <div class="label">Average Score</div>
            </div>
        </div>
        
        <!-- Grade Distribution -->
        <div class="grade-distribution">
            <span style="font-weight:600; color:#003366; font-size:12px;">Grade Distribution:</span>
            <?php 
            $grade_colors = ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd', 'F' => 'f'];
            $has_grades = false;
            foreach ($grade_distribution as $grade => $count): 
                if ($count > 0): 
                    $has_grades = true;
            ?>
                <span class="grade-item">
                    <span class="grade-box <?php echo $grade_colors[$grade]; ?>"><?php echo $grade; ?></span>
                    <?php echo $count; ?> (<?php echo round(($count / max($graded_count, 1)) * 100, 1); ?>%)
                </span>
            <?php endif; endforeach; ?>
            <?php if (!$has_grades): ?>
                <span style="color:#888;">No grades assigned yet</span>
            <?php endif; ?>
            <span style="color:#888; margin-left:10px;">| Not Submitted: <strong><?php echo $not_submitted; ?></strong></span>
        </div>
        
        <!-- Student Table -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Matric No</th>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th style="text-align:center;">Grade</th>
                    <th style="text-align:center;">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($students)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:30px; color:#888;">
                        No students found in your group for this assignment.
                    </td>
                </tr>
                <?php else: 
                    $row_num = 1;
                    foreach($students as $s): 
                        $has_submitted = $s['has_submitted'] > 0;
                        $row_class = $has_submitted ? '' : 'not-submitted';
                        
                        // Get grade if exists
                        $grade = '-';
                        $score = '-';
                        if ($has_submitted) {
                            $grade_sql = "SELECT final_grade, total_score FROM $table WHERE assignment_id = ? AND student_id = ?";
                            $grade_stmt = $conn->prepare($grade_sql);
                            $grade_stmt->bind_param("ii", $assignment_id, $s['student_id']);
                            $grade_stmt->execute();
                            $grade_result = $grade_stmt->get_result()->fetch_assoc();
                            $grade_stmt->close();
                            if ($grade_result) {
                                $grade = $grade_result['final_grade'] ?? '-';
                                $score = $grade_result['total_score'] ?? '-';
                            }
                        }
                        $grade_class = $grade != '-' ? strtolower($grade) : 'na';
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo $row_num++; ?></td>
                    <td><strong><?php echo htmlspecialchars($s['matric_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['program'] ?? '-'); ?></td>
                    <td style="text-align:center;">
                        <?php if ($has_submitted): ?>
                            <span class="status-badge submitted">✅ Submitted</span>
                        <?php else: ?>
                            <span class="status-badge not-submitted">⏳ Not Submitted</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($grade != '-'): ?>
                            <span class="grade-badge <?php echo $grade_class; ?>"><?php echo $grade; ?></span>
                        <?php elseif ($has_submitted): ?>
                            <span class="grade-badge na">-</span>
                        <?php else: ?>
                            <span class="grade-badge not-submitted">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php echo $score != '-' ? $score . '%' : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        
        <!-- Summary -->
        <div class="summary-box">
            <strong>📋 Summary:</strong>
            My Students: <strong><?php echo $total_students; ?></strong> | 
            Submitted: <strong><?php echo $submitted_count; ?></strong> (<?php echo $total_students > 0 ? round(($submitted_count / $total_students) * 100) : 0; ?>%) | 
            Not Submitted: <strong><?php echo $not_submitted; ?></strong> | 
            Graded: <strong><?php echo $graded_count; ?></strong> | 
            Average Score: <strong><?php echo $average_score; ?>%</strong>
            <?php if ($graded_count > 0): ?>
                <br>Grade Distribution: 
                <?php foreach ($grade_distribution as $grade => $count): 
                    if ($count > 0): ?>
                        <strong><?php echo $grade; ?></strong>: <?php echo $count; ?> (<?php echo round(($count / $graded_count) * 100, 1); ?>%) 
                    <?php endif; 
                endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            Generated by SILS - Student Integrity and Learning System | <?php echo date('d/m/Y h:i A'); ?>
        </div>
        
        <script>
            // Auto-print when page loads (optional)
            // window.onload = function() { window.print(); }
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ==========================================
// FETCH COMPLETED ASSIGNMENTS - BOTH PENYELARAS AND PENSYARAH ONLY SEE THEIR OWN ASSIGNMENTS
// ==========================================
$sql = "
    SELECT a.assignment_id, a.tittle, a.type, a.due_date, a.start_date, 
           a.lecturer_id, l.full_name AS lecturer_name, a.created_at,
           (SELECT COUNT(*) FROM essay_submission WHERE assignment_id = a.assignment_id) AS essay_count,
           (SELECT COUNT(*) FROM code_submission WHERE assignment_id = a.assignment_id) AS code_count,
           (SELECT COUNT(*) FROM essay_submission WHERE assignment_id = a.assignment_id AND final_grade IS NOT NULL) AS graded_essay,
           (SELECT COUNT(*) FROM code_submission WHERE assignment_id = a.assignment_id AND final_grade IS NOT NULL) AS graded_code,
           (SELECT COUNT(DISTINCT student_id) FROM enrollment WHERE class_id = a.class_id AND lecturer_id = a.lecturer_id) AS total_students
    FROM assignment a
    JOIN lecturer l ON a.lecturer_id = l.lecturer_id
    WHERE a.class_id = ? AND a.lecturer_id = ? AND a.is_completed = 1
    ORDER BY a.due_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $class_id, $lecturer_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get active assignments count - ONLY the lecturer's own active assignments
$active_sql = "SELECT COUNT(*) as active FROM assignment WHERE class_id = ? AND lecturer_id = ? AND is_completed = 0";
$active_stmt = $conn->prepare($active_sql);
$active_stmt->bind_param("ii", $class_id, $lecturer_id);
$active_stmt->execute();
$active_count = $active_stmt->get_result()->fetch_assoc()['active'] ?? 0;
$active_stmt->close();

// Get total students count for this class (all students in the class)
$student_count_sql = "SELECT COUNT(DISTINCT student_id) as total FROM enrollment WHERE class_id = ?";
$student_count_stmt = $conn->prepare($student_count_sql);
$student_count_stmt->bind_param("i", $class_id);
$student_count_stmt->execute();
$total_students = $student_count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$student_count_stmt->close();
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Assignment History — <?php echo htmlspecialchars($class_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f0f2f5; 
            min-height: 100vh;
        }
        
        .material-symbols-outlined { 
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; 
        }
        
        .header-gradient {
            background: linear-gradient(135deg, #003366 0%, #1a4d8c 50%, #2d6a9f 100%);
            position: relative;
            overflow: hidden;
        }
        .header-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 200%;
            background: rgba(255,255,255,0.05);
            transform: rotate(35deg);
            pointer-events: none;
        }
        
        .history-card {
            background: white;
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,51,102,0.08);
            overflow: hidden;
        }
        .history-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px -8px rgba(0,51,102,0.15);
        }
        
        .card-header-completed {
            background: linear-gradient(135deg, #065f46 0%, #0d9488 100%);
        }
        .card-header-essay {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8f 100%);
        }
        .card-header-code {
            background: linear-gradient(135deg, #1a3c2f 0%, #2d6a4f 100%);
        }
        
        .badge-completed {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .badge-essay {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-code {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .role-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .role-badge.penyelaras { 
            background: #003366; 
            color: white; 
        }
        .role-badge.pensyarah { 
            background: #e3f2fd; 
            color: #1565c0; 
        }
        
        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            border: 2px dashed #cbd5e1;
        }
        
        .btn-hover {
            transition: all 0.2s ease;
        }
        .btn-hover:hover {
            transform: translateY(-2px);
        }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #003366; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #1a4d8c; }
        
        .grade-bar {
            height: 6px;
            border-radius: 3px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .grade-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease;
        }
        
        .btn-pdf {
            background: #16a34a;
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .btn-pdf:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22,163,74,0.3);
        }
        
        .btn-view {
            background: #003366;
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .btn-view:hover {
            background: #1a4d8c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,51,102,0.3);
        }
        
        .stat-detail {
            font-size: 11px;
            color: #64748b;
        }
        .stat-detail .count {
            font-weight: 600;
        }
        .stat-detail .submitted { color: #16a34a; }
        .stat-detail .not-submitted { color: #dc2626; }
        
        .only-my-assignments {
            background: #e8f0fe;
            color: #003366;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header-gradient text-white">
    <div class="max-w-7xl mx-auto px-6 py-5">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" 
                   class="flex items-center gap-2 text-white/80 hover:text-white transition-all group">
                    <span class="material-symbols-outlined text-[20px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
                    <span class="text-sm font-medium">Class Dashboard</span>
                </a>
                <div class="w-px h-6 bg-white/20"></div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">📜 Assignment History</h1>
                    <p class="text-white/70 text-sm mt-0.5"><?php echo htmlspecialchars($class_name); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <?php if($is_penyelaras): ?>
                    <span class="role-badge penyelaras">📌 Penyelaras</span>
                <?php else: ?>
                    <span class="role-badge pensyarah">👨‍🏫 Pensyarah</span>
                <?php endif; ?>
                <span class="only-my-assignments">🔒 Only My Assignments</span>
                <a href="lecturer_dashboard.php" 
                   class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-xl text-sm font-semibold hover:bg-white/20 transition-all border border-white/20">
                    <span class="material-symbols-outlined text-[18px]">dashboard</span>
                    Dashboard
                </a>
            </div>
        </div>
    </div>
</header>

<!-- MAIN -->
<main class="max-w-7xl mx-auto px-6 py-8">

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="stat-box">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">My Students</p>
                    <p class="text-2xl font-bold text-blue-700"><?php echo $total_students; ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-blue-500">people</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">My Completed</p>
                    <p class="text-2xl font-bold text-green-700"><?php echo count($assignments); ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-green-500">check_circle</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">My Active</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $active_count; ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-orange-500">pending</span>
            </div>
        </div>
        <div class="stat-box">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">My Total</p>
                    <p class="text-2xl font-bold text-gray-700"><?php echo count($assignments) + $active_count; ?></p>
                </div>
                <span class="material-symbols-outlined text-3xl text-gray-500">assignment</span>
            </div>
        </div>
    </div>

    <!-- Assignments List -->
    <?php if(empty($assignments)): ?>
        <div class="empty-state">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">history</span>
            <h3 class="text-xl font-semibold text-slate-600 mb-2">No Completed Assignments</h3>
            <p class="text-slate-400 text-sm">You haven't completed any assignments in this class yet.</p>
            <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" 
               class="inline-flex items-center gap-2 mt-4 px-6 py-3 bg-blue-800 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Go to Active Assignments
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach($assignments as $assignment): 
                $submission_count = $assignment['essay_count'] + $assignment['code_count'];
                $graded_count = $assignment['graded_essay'] + $assignment['graded_code'];
                $grading_rate = $submission_count > 0 ? round(($graded_count / $submission_count) * 100) : 0;
                $is_essay = $assignment['type'] == 'essay';
                $headerClass = $is_essay ? 'card-header-essay' : 'card-header-code';
                
                $not_submitted = $assignment['total_students'] - $submission_count;
            ?>
            <div class="history-card">
                <!-- Card Header -->
                <div class="<?php echo $headerClass; ?> px-6 py-4">
                    <div class="flex justify-between items-start gap-3">
                        <div class="flex-1">
                            <h3 class="font-bold text-white text-lg leading-snug"><?php echo htmlspecialchars($assignment['tittle']); ?></h3>
                            <p class="text-white/70 text-sm mt-1 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[14px]">person</span>
                                <?php echo htmlspecialchars($assignment['lecturer_name']); ?>
                                <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full">(My Assignment)</span>
                            </p>
                        </div>
                        <span class="badge-completed flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">check_circle</span>
                            Completed
                        </span>
                    </div>
                </div>
                
                <!-- Body -->
                <div class="px-6 py-4">
                    <div class="flex flex-wrap gap-3 mb-4">
                        <span class="<?php echo $is_essay ? 'badge-essay' : 'badge-code'; ?>">
                            <?php echo $is_essay ? '📄 Essay' : '💻 Code'; ?>
                        </span>
                        <span class="text-sm text-slate-600 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">calendar_today</span>
                            Due: <?php echo date('d M Y', strtotime($assignment['due_date'])); ?>
                        </span>
                        <span class="text-sm text-slate-600 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">schedule</span>
                            Started: <?php echo date('d M Y', strtotime($assignment['start_date'])); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-4 gap-3 mt-2">
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">My Students</p>
                            <p class="text-xl font-bold text-blue-700"><?php echo $assignment['total_students']; ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Submitted</p>
                            <p class="text-xl font-bold text-green-700"><?php echo $submission_count; ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Not Submitted</p>
                            <p class="text-xl font-bold text-red-600"><?php echo $not_submitted; ?></p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-center">
                            <p class="text-xs text-gray-500">Graded</p>
                            <p class="text-xl font-bold text-orange-600"><?php echo $graded_count; ?></p>
                        </div>
                    </div>
                    
                    <!-- Grade bar -->
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Submission Rate (My Group)</span>
                            <span><?php echo $assignment['total_students'] > 0 ? round(($submission_count / $assignment['total_students']) * 100) : 0; ?>%</span>
                        </div>
                        <div class="grade-bar">
                            <div class="grade-bar-fill bg-blue-500" 
                                 style="width: <?php echo $assignment['total_students'] > 0 ? round(($submission_count / $assignment['total_students']) * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer with buttons -->
                <div class="px-6 py-3 border-t border-gray-100 bg-gray-50 flex justify-between items-center flex-wrap gap-2">
                    <span class="text-xs text-gray-400 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">event</span>
                        Completed: <?php echo date('d M Y', strtotime($assignment['created_at'])); ?>
                    </span>
                    <div class="flex items-center gap-2">
                        <a href="plagiarism_page.php?assignment_id=<?php echo $assignment['assignment_id']; ?>" 
                           class="btn-view">
                            <span class="material-symbols-outlined text-[16px]">search</span>
                            View Report
                        </a>
                        <a href="?class_id=<?php echo $class_id; ?>&generate_report=1&assignment_id=<?php echo $assignment['assignment_id']; ?>" 
                           target="_blank"
                           class="btn-pdf">
                            <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                            PDF Report
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Footer note -->
        <div class="mt-8 p-4 bg-white rounded-xl border border-gray-200 text-center">
            <p class="text-sm text-gray-500">
                <span class="material-symbols-outlined text-[16px] align-middle text-blue-600">lock</span>
                Showing <strong>ONLY</strong> your completed assignments in this class
                <?php if($is_penyelaras): ?>
                    (Penyelaras view - only assignments you created)
                <?php else: ?>
                    (Pensyarah view - only assignments you created)
                <?php endif; ?>
            </p>
            <p class="text-xs text-gray-400 mt-1">
                <?php echo count($assignments); ?> assignment(s) completed out of <?php echo count($assignments) + $active_count; ?> total (all by you)
            </p>
        </div>
    <?php endif; ?>

</main>

<!-- Bottom Navigation (Mobile) -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 flex justify-around items-center py-3 px-2 z-50 shadow-lg">
    <a href="lecturer_dashboard.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-blue-700 transition-colors">
        <span class="material-symbols-outlined text-xl">dashboard</span>
        <span class="text-[9px] font-medium">Dashboard</span>
    </a>
    <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" class="flex flex-col items-center gap-1 text-slate-500 hover:text-blue-700 transition-colors">
        <span class="material-symbols-outlined text-xl">class</span>
        <span class="text-[9px] font-medium">Class</span>
    </a>
    <a href="lecture_history_plagiarism.php?class_id=<?php echo $class_id; ?>" class="flex flex-col items-center gap-1 text-blue-700">
        <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">history</span>
        <span class="text-[9px] font-medium">History</span>
    </a>
    <a href="logout.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-red-600 transition-colors">
        <span class="material-symbols-outlined text-xl">logout</span>
        <span class="text-[9px] font-medium">Exit</span>
    </a>
</nav>

<!-- Add padding for mobile nav -->
<div class="md:hidden h-16"></div>

<script>
    // Animate grade bars on load
    document.addEventListener('DOMContentLoaded', function() {
        const bars = document.querySelectorAll('.grade-bar-fill');
        bars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 200);
        });
        
        // Card entrance animation
        const cards = document.querySelectorAll('.history-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 80 + 100);
        });
    });
</script>

</body>
</html>