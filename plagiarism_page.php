<?php
session_start();
include 'includes/config.php';
include 'includes/plagiarism_functions.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

// Get assignment ID - with better validation
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if(!$assignment_id) {
    if(isset($_GET['class_id'])) {
        $class_id = (int)$_GET['class_id'];
        $stmt = $conn->prepare("SELECT assignment_id FROM assignment WHERE class_id = ? AND is_completed = 0 LIMIT 1");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc()) {
            $assignment_id = $row['assignment_id'];
        }
        $stmt->close();
    }
}

if(!$assignment_id) {
    die("Assignment not selected. Please go back and select an assignment.");
}

$staff_id = $_SESSION['staff_id'];

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

// ==========================================
// FETCH ASSIGNMENT INFO
// ==========================================
$stmt = $conn->prepare("SELECT tittle, type, reference_file, class_id FROM assignment WHERE assignment_id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$assignment) {
    die("Assignment not found. ID: $assignment_id");
}

$submission_type = $assignment['type'];
$class_id = $assignment['class_id'];

// ==========================================
// CHECK LECTURER ROLE & ACCESS - AND VERIFY THEY CREATED THIS ASSIGNMENT
// ==========================================
$role_query = "SELECT role FROM course_lecturer WHERE class_id = ? AND lecturer_id = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("ii", $class_id, $lecturer_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$role_data = $role_result->fetch_assoc();
$role_stmt->close();

if(!$role_data){
    die("You are not assigned to this class.");
}

// ALSO VERIFY: The lecturer must be the one who created this assignment
$verify_assignment = $conn->prepare("SELECT lecturer_id FROM assignment WHERE assignment_id = ?");
$verify_assignment->bind_param("i", $assignment_id);
$verify_assignment->execute();
$assignment_creator = $verify_assignment->get_result()->fetch_assoc();
$verify_assignment->close();

if(!$assignment_creator || $assignment_creator['lecturer_id'] != $lecturer_id) {
    die("You are not authorized to view this assignment. Only the lecturer who created it can view plagiarism reports.");
}

$is_penyelaras = ($role_data['role'] == 'penyelaras');

// ==========================================
// FETCH ALL STUDENTS IN THE LECTURER'S GROUP ONLY (BOTH PENYELARAS AND PENSYARAH)
// ==========================================
// FIX: Both Penyelaras and Pensyarah only see students from their own group
$sql_all_students = "
    SELECT s.student_id, s.full_name, s.matric_no, s.program,
           e.lecturer_id, l.full_name AS lecturer_name
    FROM student s
    JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ? AND e.lecturer_id = ?
    JOIN lecturer l ON e.lecturer_id = l.lecturer_id
    GROUP BY s.student_id
    ORDER BY s.full_name ASC
";
$stmt_all = $conn->prepare($sql_all_students);
$stmt_all->bind_param("ii", $class_id, $lecturer_id);
$stmt_all->execute();
$all_students = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_all->close();

// ==========================================
// FETCH SUBMISSIONS (ONLY THOSE WHO SUBMITTED - FROM THE LECTURER'S GROUP)
// ==========================================
if ($submission_type == 'code') {
    $sql = "SELECT s.student_id, s.full_name, s.matric_no, cs.code AS submission_text, 
                   cs.file_name, cs.final_grade, cs.id AS submission_id, e.lecturer_id, l.full_name AS lecturer_name
            FROM student s
            JOIN code_submission cs ON s.student_id = cs.student_id
            JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ? AND e.lecturer_id = ?
            JOIN lecturer l ON e.lecturer_id = l.lecturer_id
            WHERE cs.assignment_id = ? AND cs.code <> ''
            GROUP BY s.student_id";
} else {
    $sql = "SELECT s.student_id, s.full_name, s.matric_no, es.essay AS submission_text, 
                   es.file_name, es.final_grade, es.id AS submission_id, e.lecturer_id, l.full_name AS lecturer_name
            FROM student s
            JOIN essay_submission es ON s.student_id = es.student_id
            JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ? AND e.lecturer_id = ?
            JOIN lecturer l ON e.lecturer_id = l.lecturer_id
            WHERE es.assignment_id = ? AND es.essay <> ''
            GROUP BY s.student_id";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $class_id, $lecturer_id, $assignment_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Create a map of student_id -> submission data
$submission_map = [];
foreach ($submissions as $sub) {
    $submission_map[$sub['student_id']] = $sub;
}

// ==========================================
// BUILD COMPLETE STUDENT LIST WITH SUBMISSION STATUS
// ==========================================
$all_student_data = [];
foreach ($all_students as $student) {
    $has_submitted = isset($submission_map[$student['student_id']]);
    $student_data = [
        'student_name' => $student['full_name'],
        'student_id' => $student['student_id'],
        'matric_no' => $student['matric_no'],
        'program' => $student['program'],
        'lecturer_name' => $student['lecturer_name'],
        'has_submitted' => $has_submitted,
        'submission_text' => $has_submitted ? $submission_map[$student['student_id']]['submission_text'] : '',
        'file_name' => $has_submitted ? ($submission_map[$student['student_id']]['file_name'] ?? 'Inline submission') : null,
        'final_grade' => $has_submitted ? ($submission_map[$student['student_id']]['final_grade'] ?? null) : null,
        'submission_id' => $has_submitted ? ($submission_map[$student['student_id']]['submission_id'] ?? 0) : 0,
        'score' => 0,
        'status' => $has_submitted ? 'Pending Review' : 'Not Submitted',
        'recommended_grade' => null,
        'best_match_student' => null
    ];
    $all_student_data[] = $student_data;
}

// ==========================================
// CALCULATE SIMILARITY FOR SUBMITTED STUDENTS ONLY
// ==========================================
// Create a separate array for submitted students
$submitted_students = array_filter($all_student_data, function($s) {
    return $s['has_submitted'];
});

$submission_texts = [];
foreach ($submitted_students as $s) {
    $submission_texts[$s['student_id']] = $s['submission_text'];
}

// Calculate similarities
foreach ($all_student_data as &$student) {
    if (!$student['has_submitted']) continue;
    
    $max_sim = 0;
    $best_match_student = null;
    
    foreach ($submission_texts as $other_id => $other_text) {
        if ($student['student_id'] == $other_id) continue;
        
        if ($submission_type == 'code') {
            $sim = calculateCodeSimilarity($student['submission_text'], $other_text, '');
        } else {
            $sim = calculateEssaySimilarity($student['submission_text'], $other_text, '');
        }
        
        if ($sim > $max_sim) {
            $max_sim = $sim;
            $best_match_student = $other_id;
        }
    }
    
    $risk = getRiskLevel($max_sim, $submission_type);
    $grade = getRecommendedGrade($max_sim, $submission_type);
    $final_grade = !empty($student['final_grade']) ? $student['final_grade'] : null;
    
    $student['score'] = round($max_sim, 2);
    $student['status'] = $risk;
    $student['recommended_grade'] = $grade;
    $student['final_grade'] = $final_grade;
    $student['best_match_student'] = $best_match_student;
}
unset($student);

// Sort: Submitted students first, then Not Submitted
usort($all_student_data, function($a, $b) {
    if ($a['has_submitted'] && !$b['has_submitted']) return -1;
    if (!$a['has_submitted'] && $b['has_submitted']) return 1;
    return strcmp($a['student_name'], $b['student_name']);
});

// Statistics
$total_students = count($all_student_data);
$submitted_count = count(array_filter($all_student_data, fn($r) => $r['has_submitted']));
$not_submitted_count = $total_students - $submitted_count;
$high_risk = count(array_filter($all_student_data, fn($r) => $r['status'] == "High Risk"));
$attention_required = count(array_filter($all_student_data, fn($r) => $r['status'] == "Attention Required"));
$clean_pass = count(array_filter($all_student_data, fn($r) => $r['status'] == "Verified"));

// Calculate average similarity only for submitted students
$submitted_scores = array_filter(array_column($all_student_data, 'score'), function($s) { return $s > 0; });
$average_similarity = !empty($submitted_scores) ? round(array_sum($submitted_scores) / count($submitted_scores), 2) : 0;
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Plagiarism Overview - SILS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Manrope', sans-serif; background: #f0f2f5; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        
        .risk-high { background-color: #fee2e2; color: #991b1b; }
        .risk-med { background-color: #fed7aa; color: #92400e; }
        .risk-low { background-color: #dcfce7; color: #166534; }
        .status-not-submitted { background-color: #f1f5f9; color: #64748b; }
        
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        
        .file-download-link { transition: all 0.3s ease; cursor: pointer; }
        .file-download-link:hover { transform: translateX(3px); }
        .file-download-link:hover .file-icon { color: #003366; }
        
        .badge-role { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .badge-role.penyelaras { background: #003366; color: white; }
        .badge-role.pensyarah { background: #e3f2fd; color: #1565c0; }

        .role-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .role-badge.penyelaras { background: #003366; color: white; }
        .role-badge.pensyarah { background: #e3f2fd; color: #1565c0; }
        
        .btn-hover {
            transition: all 0.2s ease;
        }
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,51,102,0.2);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .not-submitted-row {
            opacity: 0.7;
        }
        .not-submitted-row:hover {
            opacity: 1;
        }
        
        .only-my-group {
            background: #dbeafe;
            color: #1e40af;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<main class="max-w-7xl mx-auto px-6 py-8">

<!-- Back Button -->
<div class="mb-4">
    <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" 
       class="inline-flex items-center gap-2 text-blue-800 hover:text-blue-600 font-medium transition-all hover:translate-x-[-2px]">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        Back to Assignment
    </a>
</div>

<!-- Header -->
<div class="mb-8">
    <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-3xl font-bold text-blue-900">Plagiarism Overview</h1>
        <?php if($is_penyelaras): ?>
            <span class="badge-role penyelaras">📌 Penyelaras</span>
        <?php else: ?>
            <span class="badge-role pensyarah">👨‍🏫 Pensyarah</span>
        <?php endif; ?>
        <span class="only-my-group">🔒 Only My Group</span>
    </div>
    <p class="text-gray-600 mt-1">Assignment: <span class="font-semibold"><?php echo htmlspecialchars($assignment['tittle']); ?></span></p>
    <div class="mt-3 flex flex-wrap gap-3">
        <span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
            <span class="material-symbols-outlined text-[16px]">analytics</span>
            Detection Method: <strong><?php echo ($submission_type == 'code') ? 'Code Structure + Token Analysis' : 'Shingling (4-gram) + Word Frequency'; ?></strong>
        </span>
        <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
            <span class="material-symbols-outlined text-[16px]">info</span>
            Showing <strong>YOUR GROUP</strong> only
        </span>
        <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
            <span class="material-symbols-outlined text-[16px]">groups</span>
            <?php echo $total_students; ?> students (<?php echo $submitted_count; ?> submitted)
        </span>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-8">
    <div class="stat-card bg-white rounded-xl p-5 border border-gray-200 shadow-sm animate-in" style="animation-delay: 0.05s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 uppercase tracking-wide">My Students</p>
                <p class="text-3xl font-bold text-blue-900 mt-1"><?php echo $total_students; ?></p>
            </div>
            <span class="material-symbols-outlined text-4xl text-blue-300">people</span>
        </div>
    </div>
    <div class="stat-card bg-green-50 rounded-xl p-5 border border-green-200 shadow-sm animate-in" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-green-700 uppercase tracking-wide">Submitted</p>
                <p class="text-3xl font-bold text-green-700 mt-1"><?php echo $submitted_count; ?></p>
            </div>
            <span class="material-symbols-outlined text-4xl text-green-400">check_circle</span>
        </div>
    </div>
    <div class="stat-card bg-red-50 rounded-xl p-5 border border-red-200 shadow-sm animate-in" style="animation-delay: 0.15s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-red-700 uppercase tracking-wide">High Risk</p>
                <p class="text-3xl font-bold text-red-700 mt-1"><?php echo $high_risk; ?></p>
            </div>
            <span class="material-symbols-outlined text-4xl text-red-400">warning</span>
        </div>
    </div>
    <div class="stat-card bg-orange-50 rounded-xl p-5 border border-orange-200 shadow-sm animate-in" style="animation-delay: 0.2s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-orange-700 uppercase tracking-wide">Attention Required</p>
                <p class="text-3xl font-bold text-orange-700 mt-1"><?php echo $attention_required; ?></p>
            </div>
            <span class="material-symbols-outlined text-4xl text-orange-400">priority_high</span>
        </div>
    </div>
    <div class="stat-card bg-gray-100 rounded-xl p-5 border border-gray-200 shadow-sm animate-in" style="animation-delay: 0.25s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600 uppercase tracking-wide">Not Submitted</p>
                <p class="text-3xl font-bold text-gray-600 mt-1"><?php echo $not_submitted_count; ?></p>
            </div>
            <span class="material-symbols-outlined text-4xl text-gray-400">pending</span>
        </div>
    </div>
</div>

<!-- Average Similarity Card -->
<div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm mb-8 animate-in" style="animation-delay: 0.3s;">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <p class="text-sm text-gray-500 uppercase tracking-wide">My Group Avg Similarity</p>
            <p class="text-2xl font-bold text-blue-900"><?php echo $average_similarity; ?>%</p>
        </div>
        <div class="w-64">
            <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Low Risk</span>
                <span>Medium Risk</span>
                <span>High Risk</span>
            </div>
            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full bg-green-500" style="width: <?php echo $submitted_count > 0 ? ($clean_pass/$submitted_count)*100 : 0; ?>%"></div>
                <div class="h-full bg-orange-400" style="width: <?php echo $submitted_count > 0 ? ($attention_required/$submitted_count)*100 : 0; ?>%"></div>
                <div class="h-full bg-red-500" style="width: <?php echo $submitted_count > 0 ? ($high_risk/$submitted_count)*100 : 0; ?>%"></div>
            </div>
        </div>
    </div>
</div>

<!-- Risk Legend -->
<div class="bg-white rounded-xl p-4 border border-gray-200 mb-6 animate-in" style="animation-delay: 0.35s;">
    <h3 class="font-bold text-gray-700 mb-3">📊 Status Legend</h3>
    <div class="flex flex-wrap gap-6 text-sm">
        <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded-full bg-red-500"></div>
            <span><strong>&gt; <?php echo ($submission_type == 'code') ? '50%' : '40%'; ?></strong> = High Risk - Major similarity detected</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded-full bg-orange-400"></div>
            <span><strong><?php echo ($submission_type == 'code') ? '30-50%' : '25-40%'; ?></strong> = Attention Required - Review needed</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded-full bg-green-500"></div>
            <span><strong>&lt; <?php echo ($submission_type == 'code') ? '30%' : '25%'; ?></strong> = Verified - Original work</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-4 h-4 rounded-full bg-gray-400"></div>
            <span><strong>Not Submitted</strong> = No submission received</span>
        </div>
    </div>
    <p class="text-xs text-gray-400 mt-3 italic">
        🔒 <strong>Filtered:</strong> Showing only students in <strong>YOUR GROUP</strong>.
    </p>
</div>

<!-- Results Table -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden animate-in" style="animation-delay: 0.4s;">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-6 py-4 text-sm font-semibold text-gray-600">Student Name</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-600">Matric No</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-600">File Name</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-600 text-center">Similarity</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-600">Status</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-600 text-center">Grade</th>
                    <th class="px-6 py-4 text-sm font-semibold text-gray-600 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if(empty($all_student_data)): ?>
                <tr>
                    <td colspan="7" class="text-center py-12 text-gray-500">
                        <span class="material-symbols-outlined text-4xl mb-2">inbox</span><br>
                        No students found in your group for this assignment.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($all_student_data as $r): 
                    $is_submitted = $r['has_submitted'];
                    $row_class = $is_submitted ? '' : 'not-submitted-row';
                ?>
                <tr class="hover:bg-gray-50 transition-colors <?php echo $row_class; ?>">
                    <td class="px-6 py-4 font-medium text-gray-800">
                        <?php echo htmlspecialchars($r['student_name']); ?>
                        <?php if(!$is_submitted): ?>
                            <span class="ml-2 text-xs text-red-500 font-semibold">(Not Submitted)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?php echo htmlspecialchars($r['matric_no']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if($is_submitted && $r['file_name'] && $r['file_name'] != 'Inline submission'): ?>
                            <?php 
                            $file_path = 'uploads/submissions/' . $r['file_name'];
                            if(file_exists($file_path)):
                            ?>
                            <a href="<?php echo $file_path; ?>" download class="file-download-link inline-flex items-center gap-2 text-blue-600 hover:text-blue-800">
                                <span class="material-symbols-outlined text-[18px] file-icon">download</span>
                                <span class="text-sm"><?php echo htmlspecialchars($r['file_name']); ?></span>
                            </a>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm"><?php echo htmlspecialchars($r['file_name']); ?></span>
                            <?php endif; ?>
                        <?php elseif($is_submitted): ?>
                            <span class="text-gray-400 text-sm italic">Inline text</span>
                        <?php else: ?>
                            <span class="text-gray-400 text-sm italic">No submission</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if($is_submitted): ?>
                            <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-bold min-w-[70px] <?php 
                                echo $r['status'] == 'Verified' ? 'risk-low' : ($r['status'] == 'Attention Required' ? 'risk-med' : 'risk-high'); 
                            ?>">
                                <?php echo $r['score']; ?>%
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-bold min-w-[70px] status-not-submitted">
                                —
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if($is_submitted): ?>
                            <span class="inline-flex items-center gap-1 text-sm">
                                <span class="material-symbols-outlined text-[18px]"><?php echo $r['status'] == 'Verified' ? 'check_circle' : ($r['status'] == 'Attention Required' ? 'priority_high' : 'warning'); ?></span>
                                <?php echo $r['status']; ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-sm text-gray-400">
                                <span class="material-symbols-outlined text-[18px]">pending</span>
                                Not Submitted
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if($is_submitted): ?>
                            <?php if($r['final_grade']): ?>
                                <span class="px-3 py-1 bg-gray-100 rounded-full text-sm font-bold"><?php echo $r['final_grade']; ?></span>
                            <?php else: ?>
                                <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-bold"><?php echo $r['recommended_grade']; ?> (Recommended)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-400 text-sm">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            <?php if($is_submitted): ?>
                                <a href="plagiarism_view.php?assignment_id=<?php echo $assignment_id; ?>&student_id=<?php echo $r['student_id']; ?>" 
                                   class="inline-flex items-center gap-1 bg-blue-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors btn-hover">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                    View Report
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm italic">No submission</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
         </table>
    </div>
</div>

<div class="mt-6 text-center text-xs text-gray-400">
    <p>Detection Algorithm: <?php echo ($submission_type == 'code') ? 'Token-based shingling + Sequence similarity' : '4-gram shingling + Jaccard similarity + Word frequency analysis'; ?></p>
    <p class="mt-1">
        🔒 Showing <strong>YOUR GROUP</strong> only
        <?php if($is_penyelaras): ?>
            (Penyelaras view - only your group)
        <?php else: ?>
            (Pensyarah view - only your group)
        <?php endif; ?>
    </p>
</div>

</main>
</body>
</html>