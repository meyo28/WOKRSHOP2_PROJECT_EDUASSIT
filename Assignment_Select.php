<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php?error=login_required");
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch all enrolled courses for the student with their lecturer/group
$stmt = $conn->prepare("
    SELECT c.class_id, c.class_name, c.class_code, e.lecturer_id, l.full_name AS lecturer_name
    FROM class c
    JOIN enrollment e ON c.class_id = e.class_id
    JOIN lecturer l ON e.lecturer_id = l.lecturer_id
    WHERE e.student_id = ?
    ORDER BY c.class_name ASC
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Process assignments for each course - ONLY show assignments for student's group
foreach($student_courses as &$course){
    // Get ALL assignments for this specific course that are upcoming AND belong to this student's lecturer/group
    // Also include assignments with NULL lecturer_id (created by penyelaras for all groups)
    $stmt = $conn->prepare("
        SELECT a.assignment_id, a.tittle, a.type, a.due_date, a.description, a.lecturer_id, l.full_name AS lecturer_name
        FROM assignment a
        LEFT JOIN lecturer l ON a.lecturer_id = l.lecturer_id
        WHERE a.class_id = ? 
        AND a.due_date >= NOW()
        AND (a.lecturer_id = ? OR a.lecturer_id IS NULL)
        AND a.is_completed = 0
        ORDER BY a.due_date ASC
    ");
    $stmt->bind_param("ii", $course['class_id'], $course['lecturer_id']);
    $stmt->execute();
    $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_assignments = count($assignments);
    $completed_count = 0;
    $pending_assignments = [];

    foreach($assignments as $a){
        $submission_table = ($a['type'] === 'essay') ? 'essay_submission' : 'code_submission';
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as submitted 
            FROM $submission_table
            WHERE assignment_id = ? AND student_id = ?
        ");
        $stmt->bind_param("ii", $a['assignment_id'], $student_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($res && $res['submitted'] > 0){
            $completed_count++;
        } else {
            $pending_assignments[] = $a;
        }
    }

    $course['percentage'] = ($total_assignments > 0) ? round(($completed_count / $total_assignments) * 100) : 0;
    $course['pending_assignments'] = $pending_assignments;
    $course['total_assignments'] = $total_assignments;
    $course['completed_count'] = $completed_count;
}
unset($course);

// Calculate overall progress
$total_all_assignments = 0;
$total_completed_all = 0;
$total_pending = 0;
foreach($student_courses as $course) {
    $total_all_assignments += $course['total_assignments'];
    $total_completed_all += $course['completed_count'];
    $total_pending += count($course['pending_assignments']);
}
$overall_progress = $total_all_assignments > 0 ? round(($total_completed_all / $total_all_assignments) * 100) : 0;
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>My Courses | SILS Student Portal</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { 
        font-family: 'Inter', sans-serif; 
        background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
        min-height: 100vh;
    }
    
    /* Header Gradient */
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
    
    /* Course Card */
    .course-card {
        background: white;
        border-radius: 24px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0,51,102,0.08);
        position: relative;
        overflow: hidden;
    }
    .course-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #003366, #4a90e2);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }
    .course-card:hover::before {
        transform: scaleX(1);
    }
    .course-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 35px -12px rgba(0,51,102,0.2);
        border-color: rgba(0,51,102,0.15);
    }
    
    /* Lecturer badge */
    .lecturer-badge {
        background: #e8f0fe;
        color: #003366;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    /* Progress Ring */
    .progress-ring {
        position: relative;
        width: 80px;
        height: 80px;
    }
    .progress-ring svg {
        transform: rotate(-90deg);
    }
    .progress-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 18px;
        font-weight: 700;
        color: #003366;
    }
    
    /* Assignment Item */
    .assignment-item {
        background: #f8fafc;
        border-radius: 14px;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
    }
    .assignment-item:hover {
        background: #f1f5f9;
        transform: translateX(4px);
        border-color: #003366;
    }
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
    ::-webkit-scrollbar-thumb { background: #003366; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #1a4d8c; }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 24px;
        border: 2px dashed #cbd5e1;
    }
    
    /* Button Hover */
    .btn-hover {
        transition: all 0.2s ease;
    }
    .btn-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,51,102,0.2);
    }
    
    /* Animated Gradient Text */
    .gradient-text {
        background: linear-gradient(135deg, #003366, #4a90e2);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    
    /* Pulse Animation for Pending */
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); }
    }
    .pulse-badge {
        animation: pulse 2s infinite;
    }
    
    /* Overall Progress Bar */
    .overall-progress-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
    }
    .overall-progress-bar .fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #003366, #4a90e2);
        transition: width 1s ease;
    }
</style>
</head>
<body>

<!-- Header -->
<header class="header-gradient text-white">
    <div class="max-w-7xl mx-auto px-6 py-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="student_dashboard_2.php" class="flex items-center gap-2 text-white/80 hover:text-white transition-all group">
                    <span class="material-symbols-outlined text-[20px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
                    <span class="text-sm font-medium">Dashboard</span>
                </a>
                <div class="w-px h-6 bg-white/20"></div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">My Courses</h1>
                    <p class="text-white/70 text-sm mt-0.5">Manage your assignments and track progress</p>
                </div>
            </div>
            <a href="history_assignment.php" 
               class="flex items-center gap-2 px-5 py-2.5 bg-white/10 backdrop-blur-sm rounded-xl text-sm font-semibold hover:bg-white/20 transition-all border border-white/20">
                <span class="material-symbols-outlined text-[18px]">history</span>
                Submission History
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8">
    
    <!-- Welcome Section with Overall Progress -->
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h2>
            <p class="text-slate-500 text-sm mt-1">Here's an overview of your enrolled courses and pending tasks.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div class="bg-white rounded-xl px-4 py-2 shadow-sm border border-slate-200">
                <span class="text-xs text-slate-500">Enrolled Courses</span>
                <p class="text-xl font-bold text-blue-800"><?php echo count($student_courses); ?></p>
            </div>
            <div class="bg-white rounded-xl px-4 py-2 shadow-sm border border-slate-200">
                <span class="text-xs text-slate-500">Pending Tasks</span>
                <p class="text-xl font-bold text-orange-600"><?php echo $total_pending; ?></p>
            </div>
            <div class="bg-white rounded-xl px-4 py-2 shadow-sm border border-slate-200">
                <span class="text-xs text-slate-500">Overall Progress</span>
                <p class="text-xl font-bold text-green-600"><?php echo $overall_progress; ?>%</p>
            </div>
        </div>
    </div>
    
    <!-- Overall Progress Bar -->
    <?php if(count($student_courses) > 0): ?>
    <div class="mb-8 bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
        <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-semibold text-slate-700">Overall Course Progress</span>
            <span class="text-sm font-bold text-blue-800"><?php echo $overall_progress; ?>%</span>
        </div>
        <div class="overall-progress-bar">
            <div class="fill" style="width: <?php echo $overall_progress; ?>%"></div>
        </div>
        <p class="text-xs text-slate-400 mt-2">
            <?php echo $total_completed_all; ?> of <?php echo $total_all_assignments; ?> assignments completed
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Courses Grid -->
    <?php if(empty($student_courses)): ?>
        <div class="empty-state">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">school</span>
            <h3 class="text-xl font-semibold text-slate-600 mb-2">No Courses Enrolled Yet</h3>
            <p class="text-slate-400 mb-4">You haven't enrolled in any courses. Visit the student dashboard to join classes.</p>
            <a href="student_dashboard_2.php" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-800 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all">
                <span class="material-symbols-outlined">add_circle</span>
                Enroll in Courses
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach($student_courses as $course): 
                $pending_count = count($course['pending_assignments']);
                $has_pending = $pending_count > 0;
                $progress = $course['percentage'];
                $progress_color = $progress >= 70 ? '#16a34a' : ($progress >= 30 ? '#d97706' : '#dc2626');
            ?>
            <div class="course-card">
                <!-- Card Header -->
                <div class="p-6 pb-4">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl text-blue-700">menu_book</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl text-slate-800"><?php echo htmlspecialchars($course['class_name']); ?></h3>
                                <p class="text-xs text-slate-500 font-mono mt-0.5"><?php echo htmlspecialchars($course['class_code']); ?></p>
                                <div class="mt-1">
                                    <span class="lecturer-badge">
                                        <span class="material-symbols-outlined text-[12px]">person</span>
                                        <?php echo htmlspecialchars($course['lecturer_name']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if($has_pending): ?>
                            <span class="px-3 py-1.5 bg-orange-100 text-orange-700 rounded-full text-xs font-bold pulse-badge flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">pending</span>
                                <?php echo $pending_count; ?> Pending
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-bold flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                All Done
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Progress Section -->
                <div class="px-6 pb-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-medium text-slate-500">Course Progress</span>
                        <span class="text-sm font-bold" style="color: <?php echo $progress_color; ?>"><?php echo $progress; ?>%</span>
                    </div>
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" style="width: <?php echo $progress; ?>%; background: linear-gradient(90deg, #003366, #4a90e2);"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        <?php echo $course['completed_count']; ?> of <?php echo $course['total_assignments']; ?> assignments completed
                    </p>
                </div>
                
                <!-- Pending Assignments -->
                <?php if($has_pending): ?>
                <div class="px-6 pb-3">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-sm text-slate-400">assignment_late</span>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Pending Assignments</span>
                    </div>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <?php foreach(array_slice($course['pending_assignments'], 0, 3) as $a): 
                            $days_left = ceil((strtotime($a['due_date']) - time()) / 86400);
                            $is_urgent = $days_left <= 2;
                        ?>
                        <div class="assignment-item p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="material-symbols-outlined text-lg <?php echo $a['type'] == 'essay' ? 'text-blue-600' : 'text-green-600'; ?> shrink-0">
                                        <?php echo $a['type'] == 'essay' ? 'description' : 'code'; ?>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-700 truncate"><?php echo htmlspecialchars($a['tittle']); ?></p>
                                        <p class="text-xs text-slate-400">
                                            Due: <?php echo date('d M Y', strtotime($a['due_date'])); ?>
                                            <?php if($is_urgent): ?>
                                                <span class="text-red-500 ml-1">(Urgent!)</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if($a['lecturer_name']): ?>
                                        <p class="text-xs text-slate-400">
                                            👨‍🏫 <?php echo htmlspecialchars($a['lecturer_name']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="submit_assignment.php?assignment_id=<?php echo $a['assignment_id']; ?>" 
                                   class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-all flex items-center gap-1 shrink-0">
                                    <span class="material-symbols-outlined text-[12px]">upload</span>
                                    Submit
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if(count($course['pending_assignments']) > 3): ?>
                            <p class="text-xs text-slate-400 text-center pt-1">
                                +<?php echo count($course['pending_assignments']) - 3; ?> more assignment(s)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="px-6 pb-4">
                    <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
                        <span class="material-symbols-outlined text-green-500 text-2xl">celebration</span>
                        <p class="text-sm text-green-700 font-medium mt-1">All assignments completed!</p>
                        <p class="text-xs text-green-600">Great job keeping up with your coursework.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Card Footer -->
                <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-slate-400">assignment</span>
                            <span class="text-xs text-slate-500"><?php echo $course['total_assignments']; ?> total</span>
                        </div>
                        <div class="w-px h-4 bg-slate-200"></div>
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm text-slate-400">check_circle</span>
                            <span class="text-xs text-slate-500"><?php echo $progress; ?>% done</span>
                        </div>
                    </div>
                    <a href="assignment_pending.php?class_id=<?php echo $course['class_id']; ?>" 
                       class="flex items-center gap-1 text-blue-700 text-sm font-semibold hover:text-blue-800 transition-all group">
                        <span>View All</span>
                        <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Motivational Quote Section -->
    <?php if(count($student_courses) > 0 && $total_pending > 0): ?>
    <div class="mt-10 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 border border-blue-100">
        <div class="flex items-center gap-4 flex-wrap md:flex-nowrap">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-2xl text-blue-600">lightbulb</span>
            </div>
            <div class="flex-1">
                <p class="text-sm text-blue-800 font-medium">💡 You have <strong><?php echo $total_pending; ?> pending assignment(s)</strong> across your courses.</p>
                <p class="text-xs text-blue-600 mt-1">Stay on track by completing your tasks before the deadlines. You've got this!</p>
            </div>
            <a href="#top" class="shrink-0 text-blue-600 text-sm font-medium hover:text-blue-700 flex items-center gap-1">
                Back to top
                <span class="material-symbols-outlined text-[14px]">arrow_upward</span>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
</main>

<!-- Bottom Navigation (Mobile) -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 flex justify-around items-center py-3 px-2 z-50 shadow-lg">
    <a href="student_dashboard_2.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-blue-700 transition-colors">
        <span class="material-symbols-outlined text-xl">home</span>
        <span class="text-[9px] font-medium">Home</span>
    </a>
    <a href="Assignment_Select.php" class="flex flex-col items-center gap-1 text-blue-700">
        <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">library_books</span>
        <span class="text-[9px] font-medium">Courses</span>
    </a>
    <a href="history_assignment.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-blue-700 transition-colors">
        <span class="material-symbols-outlined text-xl">history</span>
        <span class="text-[9px] font-medium">History</span>
    </a>
    <a href="logout.php" class="flex flex-col items-center gap-1 text-slate-500 hover:text-red-600 transition-colors">
        <span class="material-symbols-outlined text-xl">logout</span>
        <span class="text-[9px] font-medium">Exit</span>
    </a>
</nav>

<!-- Add padding at bottom for mobile nav -->
<div class="md:hidden h-16"></div>

<script>
    // Add entrance animation for cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.course-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.4s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // Animate overall progress bar
        const progressBar = document.querySelector('.overall-progress-bar .fill');
        if (progressBar) {
            const width = progressBar.style.width;
            progressBar.style.width = '0%';
            setTimeout(() => {
                progressBar.style.width = width;
            }, 300);
        }
    });
</script>

</body>
</html>