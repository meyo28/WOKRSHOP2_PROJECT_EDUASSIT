<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$staff_id = $_SESSION['staff_id'];

// Get lecturer info
$stmt = $conn->prepare("SELECT lecturer_id, full_name, email FROM lecturer WHERE staff_id = ?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$lecturer = $stmt->get_result()->fetch_assoc();
$lecturer_id = $lecturer['lecturer_id'] ?? null;
$stmt->close();

if(!$lecturer_id) die("Lecturer not found.");

// ==========================================
// CHECK IF LECTURER IS PENYELARAS FOR ANY CLASS
// ==========================================
$role_check = $conn->prepare("
    SELECT cl.class_id, c.class_name, c.class_code, cl.group_name
    FROM course_lecturer cl 
    JOIN class c ON cl.class_id = c.class_id 
    WHERE cl.lecturer_id = ? AND cl.role = 'penyelaras' 
    ORDER BY c.class_name ASC
");
$role_check->bind_param("i", $lecturer_id);
$role_check->execute();
$coordinator_classes = $role_check->get_result()->fetch_all(MYSQLI_ASSOC);
$role_check->close();

$is_penyelaras = !empty($coordinator_classes);

if(!$is_penyelaras) {
    die("You are not a Penyelaras. This page is only for course coordinators.");
}

// Get selected class filter
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// If no class selected, use the first one
if($selected_class_id == 0 && !empty($coordinator_classes)) {
    $selected_class_id = $coordinator_classes[0]['class_id'];
}

// Get class name for selected class
$selected_class_name = '';
foreach($coordinator_classes as $c) {
    if($c['class_id'] == $selected_class_id) {
        $selected_class_name = $c['class_name'];
        break;
    }
}

// ==========================================
// FETCH PERFORMANCE DATA FOR SELECTED CLASS
// ==========================================
$class_performance = [];
$class_data = null;

if($selected_class_id > 0) {
    // Get class details
    $class_sql = "SELECT * FROM class WHERE class_id = ?";
    $class_stmt = $conn->prepare($class_sql);
    $class_stmt->bind_param("i", $selected_class_id);
    $class_stmt->execute();
    $class_data = $class_stmt->get_result()->fetch_assoc();
    $class_stmt->close();
    
    // Get ALL groups in this class (both penyelaras AND pensyarah) with detailed stats
    $group_sql = "
        SELECT 
            l.lecturer_id, 
            l.full_name AS lecturer_name, 
            l.staff_id,
            cl.group_name,
            cl.role,
            COALESCE((SELECT COUNT(DISTINCT e.student_id) 
                FROM enrollment e 
                WHERE e.class_id = ? AND e.lecturer_id = l.lecturer_id
            ), 0) AS student_count,
            COALESCE((SELECT COUNT(DISTINCT es.id) 
                FROM essay_submission es 
                JOIN assignment a ON es.assignment_id = a.assignment_id 
                WHERE a.class_id = ? 
                AND es.student_id IN (
                    SELECT e2.student_id 
                    FROM enrollment e2 
                    WHERE e2.class_id = ? AND e2.lecturer_id = l.lecturer_id
                )
            ), 0) AS essay_submissions,
            COALESCE((SELECT COUNT(DISTINCT cs.id) 
                FROM code_submission cs 
                JOIN assignment a ON cs.assignment_id = a.assignment_id 
                WHERE a.class_id = ? 
                AND cs.student_id IN (
                    SELECT e2.student_id 
                    FROM enrollment e2 
                    WHERE e2.class_id = ? AND e2.lecturer_id = l.lecturer_id
                )
            ), 0) AS code_submissions,
            COALESCE((SELECT COUNT(DISTINCT es.id) 
                FROM essay_submission es 
                JOIN assignment a ON es.assignment_id = a.assignment_id 
                WHERE a.class_id = ? 
                AND es.final_grade IS NOT NULL 
                AND es.student_id IN (
                    SELECT e2.student_id 
                    FROM enrollment e2 
                    WHERE e2.class_id = ? AND e2.lecturer_id = l.lecturer_id
                )
            ), 0) AS graded_essay,
            COALESCE((SELECT COUNT(DISTINCT cs.id) 
                FROM code_submission cs 
                JOIN assignment a ON cs.assignment_id = a.assignment_id 
                WHERE a.class_id = ? 
                AND cs.final_grade IS NOT NULL 
                AND cs.student_id IN (
                    SELECT e2.student_id 
                    FROM enrollment e2 
                    WHERE e2.class_id = ? AND e2.lecturer_id = l.lecturer_id
                )
            ), 0) AS graded_code,
            COALESCE((SELECT AVG(es.total_score) 
                FROM essay_submission es 
                JOIN assignment a ON es.assignment_id = a.assignment_id 
                WHERE a.class_id = ? 
                AND es.total_score IS NOT NULL
                AND es.student_id IN (
                    SELECT e2.student_id 
                    FROM enrollment e2 
                    WHERE e2.class_id = ? AND e2.lecturer_id = l.lecturer_id
                )
            ), 0) AS avg_essay_score,
            COALESCE((SELECT AVG(cs.total_score) 
                FROM code_submission cs 
                JOIN assignment a ON cs.assignment_id = a.assignment_id 
                WHERE a.class_id = ? 
                AND cs.total_score IS NOT NULL
                AND cs.student_id IN (
                    SELECT e2.student_id 
                    FROM enrollment e2 
                    WHERE e2.class_id = ? AND e2.lecturer_id = l.lecturer_id
                )
            ), 0) AS avg_code_score,
            COALESCE((SELECT COUNT(DISTINCT a2.assignment_id) 
                FROM assignment a2 
                WHERE a2.class_id = ? AND a2.lecturer_id = l.lecturer_id AND a2.is_completed = 1
            ), 0) AS completed_assignments
        FROM course_lecturer cl
        JOIN lecturer l ON cl.lecturer_id = l.lecturer_id
        WHERE cl.class_id = ? 
          AND cl.role IN ('penyelaras', 'pensyarah')  -- FIXED: Include BOTH roles
        GROUP BY l.lecturer_id
        ORDER BY FIELD(cl.role, 'penyelaras', 'pensyarah'), cl.group_name ASC, l.full_name ASC
    ";
    
    $group_stmt = $conn->prepare($group_sql);
    $group_stmt->bind_param(
        "iiiiiiiiiiiiiii", 
        $selected_class_id, // student_count
        $selected_class_id, $selected_class_id, // essay_submissions
        $selected_class_id, $selected_class_id, // code_submissions
        $selected_class_id, $selected_class_id, // graded_essay
        $selected_class_id, $selected_class_id, // graded_code
        $selected_class_id, $selected_class_id, // avg_essay_score
        $selected_class_id, $selected_class_id, // avg_code_score
        $selected_class_id, // completed_assignments
        $selected_class_id // WHERE clause
    );
    $group_stmt->execute();
    $groups = $group_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $group_stmt->close();
    
    // Get total assignments count for this class
    $assign_sql = "SELECT COUNT(*) as total FROM assignment WHERE class_id = ? AND is_completed = 0";
    $assign_stmt = $conn->prepare($assign_sql);
    $assign_stmt->bind_param("i", $selected_class_id);
    $assign_stmt->execute();
    $active_assignments = $assign_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $assign_stmt->close();
    
    // Get completed assignments count
    $completed_sql = "SELECT COUNT(*) as total FROM assignment WHERE class_id = ? AND is_completed = 1";
    $completed_stmt = $conn->prepare($completed_sql);
    $completed_stmt->bind_param("i", $selected_class_id);
    $completed_stmt->execute();
    $completed_assignments = $completed_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $completed_stmt->close();
    
    $class_performance = [
        'class' => $class_data,
        'groups' => $groups,
        'active_assignments' => $active_assignments,
        'completed_assignments' => $completed_assignments,
        'total_assignments' => $active_assignments + $completed_assignments,
        'total_students' => array_sum(array_column($groups, 'student_count')),
        'total_submissions' => array_sum(array_column($groups, 'essay_submissions')) + array_sum(array_column($groups, 'code_submissions')),
        'total_graded' => array_sum(array_column($groups, 'graded_essay')) + array_sum(array_column($groups, 'graded_code')),
        'avg_score' => 0
    ];
    
    // Calculate overall average score
    $all_scores = [];
    foreach($groups as $g) {
        if($g['avg_essay_score'] > 0) $all_scores[] = $g['avg_essay_score'];
        if($g['avg_code_score'] > 0) $all_scores[] = $g['avg_code_score'];
    }
    $class_performance['avg_score'] = !empty($all_scores) ? round(array_sum($all_scores) / count($all_scores), 2) : 0;
}

// Calculate overall stats
$overall_grade_rate = 0;
if($class_performance && $class_performance['total_submissions'] > 0) {
    $overall_grade_rate = round(($class_performance['total_graded'] / $class_performance['total_submissions']) * 100, 2);
}

// Function to get grade distribution for a group
function getGradeDistribution($conn, $class_id, $lecturer_id) {
    $grades = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
    
    // Essay submissions
    $sql = "
        SELECT final_grade 
        FROM essay_submission es
        JOIN assignment a ON es.assignment_id = a.assignment_id
        WHERE a.class_id = ? 
        AND es.student_id IN (
            SELECT student_id FROM enrollment WHERE class_id = ? AND lecturer_id = ?
        )
        AND es.final_grade IS NOT NULL
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $class_id, $class_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        if(isset($grades[$row['final_grade']])) {
            $grades[$row['final_grade']]++;
        }
    }
    $stmt->close();
    
    // Code submissions
    $sql = "
        SELECT final_grade 
        FROM code_submission cs
        JOIN assignment a ON cs.assignment_id = a.assignment_id
        WHERE a.class_id = ? 
        AND cs.student_id IN (
            SELECT student_id FROM enrollment WHERE class_id = ? AND lecturer_id = ?
        )
        AND cs.final_grade IS NOT NULL
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $class_id, $class_id, $lecturer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        if(isset($grades[$row['final_grade']])) {
            $grades[$row['final_grade']]++;
        }
    }
    $stmt->close();
    
    return $grades;
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Lecturer Performance Report - EDUASSIST</title>
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
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px -8px rgba(0,51,102,0.12);
        }
        
        .performance-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .performance-card:hover {
            box-shadow: 0 12px 30px -8px rgba(0,51,102,0.12);
        }
        
        .group-row {
            transition: all 0.2s ease;
        }
        .group-row:hover {
            background: #f8fafc;
        }
        .group-row:last-child {
            border-bottom: none;
        }
        
        .badge-penyelaras {
            background: #003366;
            color: white;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        
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
        
        .grade-high { color: #16a34a; }
        .grade-medium { color: #d97706; }
        .grade-low { color: #dc2626; }
        
        .badge-role {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-role.penyelaras { 
            background: #003366; 
            color: white; 
        }
        .badge-role.pensyarah { 
            background: #e3f2fd; 
            color: #1565c0; 
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
        }

        @media print {
            .header-gradient, .badge-role, .btn-hover, .no-print {
                display: none !important;
            }
            .stat-card, .performance-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            body { background: white !important; }
        }
        
        .filter-select {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            outline: none;
            min-width: 200px;
        }
        .filter-select:focus {
            border-color: #003366;
            box-shadow: 0 0 0 3px rgba(0,51,102,0.1);
        }
        
        .grade-distribution {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }
        .grade-distribution .grade-box {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: white;
        }
        .grade-distribution .grade-box.grade-a { background: #16a34a; }
        .grade-distribution .grade-box.grade-b { background: #22c55e; }
        .grade-distribution .grade-box.grade-c { background: #eab308; }
        .grade-distribution .grade-box.grade-d { background: #f59e0b; }
        .grade-distribution .grade-box.grade-f { background: #dc2626; }
        
        .grade-legend {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 11px;
            color: #64748b;
        }
        .grade-legend .dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 4px;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header-gradient text-white no-print">
    <div class="max-w-7xl mx-auto px-6 py-5">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="lecturer_dashboard.php" 
                   class="flex items-center gap-2 text-white/80 hover:text-white transition-all group">
                    <span class="material-symbols-outlined text-[20px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
                    <span class="text-sm font-medium">Dashboard</span>
                </a>
                <div class="w-px h-6 bg-white/20"></div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">📊 Lecturer Performance</h1>
                    <p class="text-white/70 text-sm mt-0.5"><?php echo htmlspecialchars($lecturer['full_name']); ?> — Penyelaras</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="badge-role penyelaras">📌 Penyelaras</span>
                <button onclick="window.print()" 
                        class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-xl text-sm font-semibold hover:bg-white/20 transition-all border border-white/20">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    Export PDF
                </button>
            </div>
        </div>
    </div>
</header>

<!-- MAIN -->
<main class="max-w-7xl mx-auto px-6 py-8">

    <!-- Class Selector -->
    <div class="bg-white rounded-xl p-4 border border-gray-200 mb-6 flex items-center gap-4 flex-wrap">
        <span class="text-sm font-medium text-gray-700 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">class</span>
            Select Class:
        </span>
        <form method="GET" action="" class="flex items-center gap-3 flex-wrap">
            <select name="class_id" class="filter-select" onchange="this.form.submit()">
                <?php foreach($coordinator_classes as $c): ?>
                    <option value="<?php echo $c['class_id']; ?>" <?php echo ($selected_class_id == $c['class_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['class_name'] . ' (' . $c['class_code'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="text-xs text-gray-400">
            You are the Penyelaras for <?php echo count($coordinator_classes); ?> class(es)
        </span>
    </div>

    <?php if(empty($class_performance) || empty($class_performance['groups'])): ?>
        <div class="empty-state">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">analytics</span>
            <h3 class="text-xl font-semibold text-slate-600 mb-2">No Performance Data Available</h3>
            <p class="text-slate-400 text-sm">
                <?php if($selected_class_id > 0): ?>
                    No groups found for this class yet.
                <?php else: ?>
                    Select a class to view performance data.
                <?php endif; ?>
            </p>
            <a href="lecturer_dashboard.php" 
               class="inline-flex items-center gap-2 mt-4 px-6 py-3 bg-blue-800 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Go to Dashboard
            </a>
        </div>
    <?php else: 
        $class = $class_performance['class'];
        $groups = $class_performance['groups'];
        $total_students = $class_performance['total_students'];
        $total_submissions = $class_performance['total_submissions'];
        $total_graded = $class_performance['total_graded'];
        $grade_rate = $total_submissions > 0 ? round(($total_graded / $total_submissions) * 100, 2) : 0;
        $has_groups = !empty($groups);
    ?>
    
    <!-- Overview Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-8">
        <div class="stat-card animate-in" style="animation-delay: 0.05s;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Total Students</p>
                    <p class="text-3xl font-bold text-blue-900"><?php echo $total_students; ?></p>
                </div>
                <span class="material-symbols-outlined text-4xl text-blue-400">groups</span>
            </div>
        </div>
        <div class="stat-card animate-in" style="animation-delay: 0.1s;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Groups</p>
                    <p class="text-3xl font-bold text-blue-900"><?php echo count($groups); ?></p>
                </div>
                <span class="material-symbols-outlined text-4xl text-purple-400">group_work</span>
            </div>
        </div>
        <div class="stat-card animate-in" style="animation-delay: 0.15s;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Submissions</p>
                    <p class="text-3xl font-bold text-blue-900"><?php echo $total_submissions; ?></p>
                </div>
                <span class="material-symbols-outlined text-4xl text-orange-400">assignment</span>
            </div>
        </div>
        <div class="stat-card animate-in" style="animation-delay: 0.2s;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Grading Rate</p>
                    <p class="text-3xl font-bold <?php echo $grade_rate >= 80 ? 'text-green-600' : ($grade_rate >= 50 ? 'text-orange-600' : 'text-red-600'); ?>">
                        <?php echo $grade_rate; ?>%
                    </p>
                </div>
                <span class="material-symbols-outlined text-4xl text-purple-400">analytics</span>
            </div>
        </div>
        <div class="stat-card animate-in" style="animation-delay: 0.25s;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Avg Score</p>
                    <p class="text-3xl font-bold text-blue-900"><?php echo $class_performance['avg_score']; ?>%</p>
                </div>
                <span class="material-symbols-outlined text-4xl text-green-400">score</span>
            </div>
        </div>
    </div>
    
    <!-- Class Progress Bar -->
    <div class="bg-white rounded-xl p-4 border border-gray-200 mb-8">
        <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-semibold text-gray-700">Overall Progress</span>
            <span class="text-sm font-bold text-blue-800">
                <?php echo $class_performance['completed_assignments']; ?>/<?php echo $class_performance['total_assignments']; ?> Assignments Completed
            </span>
        </div>
        <div class="grade-bar h-3">
            <div class="grade-bar-fill bg-gradient-to-r from-blue-600 to-green-500" 
                 style="width: <?php echo $class_performance['total_assignments'] > 0 ? round(($class_performance['completed_assignments'] / $class_performance['total_assignments']) * 100) : 0; ?>%"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-400 mt-1">
            <span>0%</span>
            <span><?php echo $class_performance['completed_assignments']; ?> completed</span>
            <span>100%</span>
        </div>
    </div>
    
    <!-- Performance Card -->
    <div class="performance-card animate-in" style="animation-delay: 0.3s;">
        <!-- Class Header -->
        <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
            <div class="flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($class['class_name']); ?></h2>
                    <p class="text-blue-200 text-sm"><?php echo htmlspecialchars($class['class_code']); ?></p>
                </div>
                <div class="flex items-center gap-4 text-sm text-blue-100">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">groups</span>
                        <?php echo $total_students; ?> students
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">assignment</span>
                        <?php echo $class_performance['active_assignments']; ?> active
                    </span>
                    <span class="badge-penyelaras">📌 Penyelaras</span>
                </div>
            </div>
        </div>
        
        <!-- Groups Table -->
        <div class="p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600">groups</span>
                Groups Performance
            </h3>
            
            <?php if(!$has_groups): ?>
                <p class="text-gray-400 text-sm">No groups assigned to this class yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-50 text-xs font-semibold text-gray-600 uppercase tracking-wider rounded-lg">
                                <th class="px-4 py-3 rounded-l-lg">Group / Lecturer</th>
                                <th class="px-4 py-3 text-center">Students</th>
                                <th class="px-4 py-3 text-center">Submissions</th>
                                <th class="px-4 py-3 text-center">Graded</th>
                                <th class="px-4 py-3 text-center">Grade Rate</th>
                                <th class="px-4 py-3 text-center">Avg Score</th>
                                <th class="px-4 py-3 text-center">Grade Distribution</th>
                                <th class="px-4 py-3 text-center rounded-r-lg">Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($groups as $group): 
                                $submissions = $group['essay_submissions'] + $group['code_submissions'];
                                $graded = $group['graded_essay'] + $group['graded_code'];
                                $grade_rate_group = $submissions > 0 ? round(($graded / $submissions) * 100, 2) : 0;
                                $avg_score = max($group['avg_essay_score'], $group['avg_code_score']);
                                $grade_color = $grade_rate_group >= 80 ? 'text-green-600' : ($grade_rate_group >= 50 ? 'text-orange-600' : 'text-red-600');
                                
                                // Get grade distribution for this group
                                $grade_dist = getGradeDistribution($conn, $selected_class_id, $group['lecturer_id']);
                                $total_grades = array_sum($grade_dist);
                            ?>
                            <tr class="group-row border-b border-gray-100">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($group['group_name'] ?: 'Unnamed Group'); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($group['lecturer_name']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($group['staff_id']); ?></p>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-700 font-semibold"><?php echo $group['student_count']; ?></td>
                                <td class="px-4 py-3 text-center text-gray-700"><?php echo $submissions; ?></td>
                                <td class="px-4 py-3 text-center text-gray-700"><?php echo $graded; ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-20 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full <?php echo $grade_rate_group >= 80 ? 'bg-green-500' : ($grade_rate_group >= 50 ? 'bg-orange-400' : 'bg-red-500'); ?>" 
                                                 style="width: <?php echo $grade_rate_group; ?>%"></div>
                                        </div>
                                        <span class="text-sm font-bold <?php echo $grade_color; ?>">
                                            <?php echo $grade_rate_group; ?>%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-sm font-bold <?php echo $avg_score >= 70 ? 'text-green-600' : ($avg_score >= 50 ? 'text-orange-600' : 'text-red-600'); ?>">
                                        <?php echo $avg_score > 0 ? $avg_score . '%' : 'N/A'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="grade-distribution justify-center">
                                        <?php if($total_grades > 0): ?>
                                            <?php foreach(['A' => 'grade-a', 'B' => 'grade-b', 'C' => 'grade-c', 'D' => 'grade-d', 'F' => 'grade-f'] as $grade => $class): ?>
                                                <div class="grade-box <?php echo $class; ?>" title="<?php echo $grade; ?>: <?php echo $grade_dist[$grade]; ?>">
                                                    <?php echo $grade_dist[$grade] > 0 ? $grade_dist[$grade] : ''; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-sm font-semibold text-green-600">
                                        <?php echo $group['completed_assignments']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Grade Legend -->
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="grade-legend">
                        <span class="font-medium text-gray-600 mr-2">Grade Legend:</span>
                        <span><span class="dot grade-a"></span>A</span>
                        <span><span class="dot grade-b"></span>B</span>
                        <span><span class="dot grade-c"></span>C</span>
                        <span><span class="dot grade-d"></span>D</span>
                        <span><span class="dot grade-f"></span>F</span>
                        <span class="text-xs text-gray-400 ml-4">(Numbers show count per grade)</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex justify-between items-center flex-wrap gap-2">
            <span class="text-xs text-gray-400">
                Last updated: <?php echo date('d M Y, h:i A'); ?>
            </span>
            <div class="flex gap-2">
                <button onclick="window.print()" 
                        class="inline-flex items-center gap-1 text-green-700 hover:text-green-900 text-sm font-medium transition-all btn-hover no-print">
                    <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                    Export PDF
                </button>
            </div>
        </div>
    </div>
    
    <?php endif; ?>

    <!-- Footer Note -->
    <div class="mt-8 p-4 bg-white rounded-xl border border-gray-200 text-center">
        <p class="text-sm text-gray-500">
            <span class="material-symbols-outlined text-[16px] align-middle text-blue-600">info</span>
            This report shows performance data for all groups in this class. Only <strong>Penyelaras</strong> can access this page.
        </p>
        <p class="text-xs text-gray-400 mt-1">
            Grade Rate = (Graded Submissions / Total Submissions) × 100% | Average Score = Average of all graded submissions
        </p>
    </div>

</main>

<!-- Bottom Navigation (Mobile) -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 flex justify-around items-center py-3 px-2 z-50 shadow-lg no-print">
    <a href="lecturer_dashboard.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-blue-700 transition-colors">
        <span class="material-symbols-outlined text-xl">dashboard</span>
        <span class="text-[9px] font-medium">Dashboard</span>
    </a>
    <a href="lecturer_performance.php" class="flex flex-col items-center gap-1 text-blue-700">
        <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">analytics</span>
        <span class="text-[9px] font-medium">Performance</span>
    </a>
    <a href="logout.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-red-600 transition-colors">
        <span class="material-symbols-outlined text-xl">logout</span>
        <span class="text-[9px] font-medium">Exit</span>
    </a>
</nav>

<div class="md:hidden h-16 no-print"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animate grade bars
        const bars = document.querySelectorAll('.grade-bar-fill');
        bars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.transition = 'width 1s ease';
                bar.style.width = width;
            }, 300);
        });
    });
</script>

</body>
</html>