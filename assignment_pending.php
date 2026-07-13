<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student'){
    header("Location: index.php?error=login_required");
    exit();
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$student_id = $_SESSION['user_id'];

if(!$class_id) die("Course not selected.");

// Fetch course info
$stmt = $conn->prepare("SELECT class_name, class_code FROM class WHERE class_id=?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$course) die("Course not found.");

// Fetch all pending assignments for this student in this course
$stmt = $conn->prepare("
    SELECT a.assignment_id, a.tittle, a.type, a.due_date, a.description
    FROM assignment a
    LEFT JOIN (
        SELECT assignment_id 
        FROM essay_submission WHERE student_id=? 
        UNION ALL
        SELECT assignment_id 
        FROM code_submission WHERE student_id=?
    ) s ON s.assignment_id = a.assignment_id
    WHERE a.class_id=? AND s.assignment_id IS NULL AND a.due_date >= NOW()
    ORDER BY a.due_date ASC
");
$stmt->bind_param("iii", $student_id, $student_id, $class_id);
$stmt->execute();
$pending_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate progress for this course
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM assignment WHERE class_id=?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$total_assignments = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$completed_count = $total_assignments - count($pending_assignments);
$progress_percentage = ($total_assignments > 0) ? round(($completed_count / $total_assignments) * 100) : 0;
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?php echo htmlspecialchars($course['class_name']); ?> - Pending Assignments</title>
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
    
    .assignment-card {
        background: white;
        border-radius: 20px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0,51,102,0.08);
    }
    .assignment-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 25px -10px rgba(0,51,102,0.15);
        border-color: rgba(0,51,102,0.15);
    }
    
    .status-urgent { background: #fee2e2; color: #dc2626; }
    .status-today { background: #fed7aa; color: #d97706; }
    .status-upcoming { background: #dcfce7; color: #16a34a; }
    .status-overdue { background: #fecaca; color: #991b1b; }
    
    .progress-bar {
        background: #e2e8f0;
        border-radius: 100px;
        height: 8px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        border-radius: 100px;
        background: linear-gradient(90deg, #003366, #4a90e2);
        transition: width 0.5s ease;
    }
    
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px; }
    ::-webkit-scrollbar-thumb { background: #003366; border-radius: 10px; }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 24px;
        border: 2px dashed #cbd5e1;
    }
    
    .btn-hover {
        transition: all 0.2s ease;
    }
    .btn-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,51,102,0.2);
    }
</style>
</head>
<body>

<!-- Header -->
<header class="header-gradient text-white">
    <div class="max-w-6xl mx-auto px-6 py-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <a href="Assignment_Select.php" class="flex items-center gap-2 text-white/80 hover:text-white transition-all group">
                    <span class="material-symbols-outlined text-[20px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
                    <span class="text-sm font-medium">Back to Courses</span>
                </a>
                <div class="w-px h-6 bg-white/20"></div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight"><?php echo htmlspecialchars($course['class_name']); ?></h1>
                    <p class="text-white/70 text-sm mt-0.5"><?php echo htmlspecialchars($course['class_code']); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2 text-center">
                    <span class="text-xs text-white/70">Pending Tasks</span>
                    <p class="text-xl font-bold"><?php echo count($pending_assignments); ?></p>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-xl px-4 py-2 text-center">
                    <span class="text-xs text-white/70">Progress</span>
                    <p class="text-xl font-bold"><?php echo $progress_percentage; ?>%</p>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8">
    
    <!-- Progress Overview -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 mb-8">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">Course Progress</h2>
                <p class="text-sm text-slate-500">
                    <?php echo $completed_count; ?> of <?php echo $total_assignments; ?> assignments completed
                </p>
            </div>
            <div class="w-48">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                </div>
                <p class="text-xs text-right text-slate-500 mt-1"><?php echo $progress_percentage; ?>% complete</p>
            </div>
        </div>
    </div>
    
    <!-- Assignments List -->
    <div class="flex items-center gap-2 mb-5">
        <span class="material-symbols-outlined text-blue-700">assignment_late</span>
        <h2 class="text-xl font-bold text-slate-800">Pending Assignments</h2>
        <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium ml-2">
            <?php echo count($pending_assignments); ?> items
        </span>
    </div>
    
    <?php if(!empty($pending_assignments)): ?>
        <div class="grid grid-cols-1 gap-5">
            <?php foreach($pending_assignments as $a):
                $due_date = new DateTime($a['due_date']);
                $today = new DateTime();
                $diff = $today->diff($due_date);
                $days_left = (int)$diff->format("%r%a");
                
                if($days_left < 0) {
                    $status_text = 'Overdue';
                    $status_class = 'status-overdue';
                } elseif($days_left === 0) {
                    $status_text = 'Due Today!';
                    $status_class = 'status-today';
                } elseif($days_left <= 2) {
                    $status_text = $days_left . ' day' . ($days_left != 1 ? 's' : '') . ' left';
                    $status_class = 'status-urgent';
                } else {
                    $status_text = $days_left . ' days left';
                    $status_class = 'status-upcoming';
                }
                
                $type_icon = $a['type'] == 'essay' ? 'description' : 'code';
                $type_color = $a['type'] == 'essay' ? 'text-blue-600' : 'text-green-600';
            ?>
            <div class="assignment-card p-6">
                <div class="flex flex-wrap justify-between items-start gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined <?php echo $type_color; ?>"><?php echo $type_icon; ?></span>
                            <span class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?php echo ucfirst($a['type']); ?> Assignment</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-2"><?php echo htmlspecialchars($a['tittle']); ?></h3>
                        <p class="text-sm text-slate-600 mb-3"><?php echo nl2br(htmlspecialchars($a['description'] ?: 'No description provided.')); ?></p>
                        <div class="flex items-center gap-4 text-xs text-slate-500">
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">calendar_today</span>
                                <span><?php echo date('d M Y', strtotime($a['due_date'])); ?></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">schedule</span>
                                <span><?php echo date('h:i A', strtotime($a['due_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                        <a href="submit_assignment.php?assignment_id=<?php echo $a['assignment_id']; ?>" 
                           class="flex items-center gap-2 px-5 py-2.5 bg-blue-700 text-white rounded-xl text-sm font-semibold hover:bg-blue-800 transition-all btn-hover">
                            <span class="material-symbols-outlined text-[16px]">upload</span>
                            Submit Now
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <span class="material-symbols-outlined text-6xl text-green-300 mb-4">celebration</span>
            <h3 class="text-xl font-semibold text-slate-600 mb-2">All Caught Up! 🎉</h3>
            <p class="text-slate-400">You have no pending assignments for this course.</p>
            <a href="Assignment_Select.php" class="inline-flex items-center gap-2 mt-5 px-5 py-2.5 bg-blue-700 text-white rounded-xl text-sm font-semibold hover:bg-blue-800 transition-all">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to My Courses
            </a>
        </div>
    <?php endif; ?>
    
</main>

<!-- Mobile Bottom Nav -->
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
<div class="md:hidden h-16"></div>

<script>
    // Entrance animation for cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.assignment-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(15px)';
            setTimeout(() => {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 80);
        });
    });
</script>

</body>
</html>