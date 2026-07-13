<?php
session_start();
include 'includes/config.php';

// Lecturer authentication
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer'){
    header("Location: index.php?error=login_required");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if(!$class_id){
    die("Please select a class to view assignments.");
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

// ==========================================
// CHECK LECTURER ROLE & ACCESS
// ==========================================
$role_query = "SELECT role, group_name FROM course_lecturer WHERE class_id = ? AND lecturer_id = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("ii", $class_id, $lecturer_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$role_data = $role_result->fetch_assoc();
$role_stmt->close();

if(!$role_data){
    die("You are not assigned to this class.");
}

$is_penyelaras = ($role_data['role'] == 'penyelaras');
$lecturer_group_name = $role_data['group_name'] ?? '';

// Get class name
$stmt_class = $conn->prepare("SELECT class_name FROM class WHERE class_id = ?");
$stmt_class->bind_param("i", $class_id);
$stmt_class->execute();
$result_class = $stmt_class->get_result();
if($row = $result_class->fetch_assoc()){
    $class_name = $row['class_name'];
} else {
    die("Class not found.");
}
$stmt_class->close();

// ==========================================
// HANDLE DELETE ASSIGNMENT
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['assignment_id'])) {
    $delete_id = (int)$_GET['assignment_id'];
    
    // Check if assignment has submissions
    $check_sql = "
        SELECT 
            (SELECT COUNT(*) FROM essay_submission WHERE assignment_id = ?) as essay_count,
            (SELECT COUNT(*) FROM code_submission WHERE assignment_id = ?) as code_count
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $delete_id, $delete_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($result['essay_count'] > 0 || $result['code_count'] > 0) {
        $error_msg = "Cannot delete assignment: There are submissions already.";
    } else {
        $del_stmt = $conn->prepare("DELETE FROM assignment WHERE assignment_id = ? AND lecturer_id = ?");
        $del_stmt->bind_param("ii", $delete_id, $lecturer_id);
        if ($del_stmt->execute()) {
            $success_msg = "Assignment deleted successfully!";
        } else {
            $error_msg = "Error deleting assignment.";
        }
        $del_stmt->close();
    }
}

// ==========================================
// FETCH ASSIGNMENTS FOR THIS CLASS (ONLY LECTURER'S OWN)
// ==========================================
$sql_assign = "
    SELECT a.assignment_id, a.tittle, a.type, a.due_date, a.start_date, a.description,
           a.lecturer_id, l.full_name AS lecturer_name, a.group_id,
           CASE WHEN a.due_date < NOW() THEN 'Active' ELSE 'Pending' END AS status,
           (SELECT COUNT(*) FROM essay_submission WHERE assignment_id = a.assignment_id AND final_grade IS NULL) AS ungraded_essay,
           (SELECT COUNT(*) FROM code_submission WHERE assignment_id = a.assignment_id AND final_grade IS NULL) AS ungraded_code,
           (SELECT COUNT(*) FROM essay_submission WHERE assignment_id = a.assignment_id) AS total_essay,
           (SELECT COUNT(*) FROM code_submission WHERE assignment_id = a.assignment_id) AS total_code
    FROM assignment a
    JOIN lecturer l ON a.lecturer_id = l.lecturer_id
    WHERE a.class_id = ? AND a.lecturer_id = ? AND a.is_completed = 0
    ORDER BY a.due_date ASC
";
$stmt_assign = $conn->prepare($sql_assign);
$stmt_assign->bind_param("ii", $class_id, $lecturer_id);
$stmt_assign->execute();
$assignments = $stmt_assign->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assign->close();

// ==========================================
// FETCH STUDENTS FOR THIS CLASS (ONLY LECTURER'S GROUP)
// ==========================================
$student_sql = "
    SELECT s.student_id, s.matric_no, s.full_name, s.program,
           e.lecturer_id, l.full_name AS lecturer_name,
           (SELECT COUNT(*) FROM essay_submission WHERE student_id = s.student_id) AS essay_submitted,
           (SELECT COUNT(*) FROM code_submission WHERE student_id = s.student_id) AS code_submitted
    FROM enrollment e
    JOIN student s ON e.student_id = s.student_id
    JOIN lecturer l ON e.lecturer_id = l.lecturer_id
    WHERE e.class_id = ? AND e.lecturer_id = ?
    ORDER BY s.full_name ASC
";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("ii", $class_id, $lecturer_id);
$student_stmt->execute();
$students = $student_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$student_stmt->close();

// ==========================================
// HANDLE DONE BUTTON
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'mark_done' && isset($_GET['done_id'])) {
    $done_id = (int)$_GET['done_id'];
    
    $check_sql = "SELECT type FROM assignment WHERE assignment_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $done_id);
    $check_stmt->execute();
    $type_result = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($type_result) {
        $table = ($type_result['type'] == 'essay') ? 'essay_submission' : 'code_submission';
        $check_ungraded = $conn->prepare("SELECT COUNT(*) as ungraded FROM $table WHERE assignment_id = ? AND final_grade IS NULL");
        $check_ungraded->bind_param("i", $done_id);
        $check_ungraded->execute();
        $ungraded_result = $check_ungraded->get_result()->fetch_assoc();
        $check_ungraded->close();
        
        if ($ungraded_result['ungraded'] == 0) {
            $update_complete = $conn->prepare("UPDATE assignment SET is_completed = 1 WHERE assignment_id = ?");
            $update_complete->bind_param("i", $done_id);
            $update_complete->execute();
            $update_complete->close();
            
            header("Location: class_dashboard.php?class_id=" . $class_id . "&done=1");
            exit();
        } else {
            $_SESSION['done_error'] = "Please grade all submissions before marking as done.";
            header("Location: class_dashboard.php?class_id=" . $class_id . "&error=incomplete");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Class Dashboard — <?php echo htmlspecialchars($class_name); ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style>
    :root {
        --navy: #0f2044;
        --navy-mid: #1a3460;
        --accent: #3b82f6;
        --success: #16a34a;
        --warning: #d97706;
        --danger: #dc2626;
        --surface: #f8fafc;
        --card: #ffffff;
        --border: #e2e8f0;
        --text: #1e293b;
        --muted: #64748b;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--surface); color: var(--text); min-height: 100vh; }
    h1, h2, h3 { font-family: 'Space Grotesk', sans-serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

    .site-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
        box-shadow: 0 4px 20px rgba(15,32,68,0.3);
    }

    .assignment-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .assignment-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.1); }

    .card-header-essay  { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8f 100%); }
    .card-header-code   { background: linear-gradient(135deg, #1a3c2f 0%, #2d6a4f 100%); }

    .status-active  { background: #d1fae5; color: #065f46; }
    .status-pending { background: #f1f5f9; color: #475569; }

    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; text-decoration: none; }
    .btn-primary { background: #1d4ed8; color: white; }
    .btn-primary:hover { background: #1e40af; transform: translateY(-1px); }
    .btn-success { background: var(--success); color: white; }
    .btn-success:hover { background: #15803d; transform: translateY(-1px); }
    .btn-disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }
    .btn-edit { background: #f59e0b; color: white; }
    .btn-edit:hover { background: #d97706; transform: translateY(-1px); }
    .btn-delete { background: #dc2626; color: white; }
    .btn-delete:hover { background: #b91c1c; transform: translateY(-1px); }
    .btn-performance { background: #7c3aed; color: white; }
    .btn-performance:hover { background: #6d28d9; transform: translateY(-1px); }
    .btn-pending-edit { background: #f59e0b; color: white; }
    .btn-pending-edit:hover { background: #d97706; transform: translateY(-1px); }
    .btn-pending-delete { background: #dc2626; color: white; }
    .btn-pending-delete:hover { background: #b91c1c; transform: translateY(-1px); }

    .chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: rgba(255,255,255,0.8); }

    .ungraded-bar { background: #fef3c7; border-left: 4px solid #d97706; padding: 8px 14px; font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 6px; }

    .toast { position: fixed; top: 20px; right: 20px; z-index: 999; border-radius: 14px; padding: 14px 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); animation: slideDown 0.4s ease; min-width: 280px; }
    .toast-success { background: linear-gradient(135deg, #16a34a, #15803d); color: white; }
    .toast-error   { background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; }
    @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes checkmark { from { stroke-dashoffset: 100; } to { stroke-dashoffset: 0; } }
    .toast svg path { stroke: white; stroke-width: 3; fill: none; stroke-dasharray: 100; stroke-dashoffset: 100; animation: checkmark 0.5s ease 0.3s forwards; }

    .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; border: 2px dashed var(--border); }
    
    .student-table th { background: #f8fafc; font-weight: 600; color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .student-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .student-table tr:hover { background: #f8fafc; }
    
    .role-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
    .role-badge.penyelaras { background: #003366; color: white; }
    .role-badge.pensyarah { background: #e3f2fd; color: #1565c0; }
    
    .group-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        display: inline-block;
    }
    
    .btn-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .student-table th { 
    background: #f8fafc; 
    font-weight: 600; 
    color: var(--muted); 
    font-size: 12px; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
    padding: 12px 16px;
    border-bottom: 2px solid #e2e8f0;
}
.student-table td { 
    padding: 10px 16px; 
    border-bottom: 1px solid #f1f5f9; 
    font-size: 13px; 
    vertical-align: middle;
}
.student-table tr:hover { background: #f8fafc; }
.student-table .text-center { text-align: center; }
.student-table .text-left { text-align: left; }

.badge-program {
    background: #fff3e0;
    color: #e65100;
    border: 1px solid #ffe0b2;
    padding: 2px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}
</style>
</head>
<body>

<!-- TOASTS -->
<?php if(isset($_GET['done']) && $_GET['done'] == 1): ?>
<div id="toast" class="toast toast-success">
    <svg width="22" height="22" viewBox="0 0 52 52"><path d="M14 27 L22 35 L38 18"/></svg>
    <div><div style="font-weight:700;font-size:14px;">Assignment Completed!</div><div style="font-size:12px;opacity:.85;">Moved to plagiarism history</div></div>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t){t.style.opacity='0';t.style.transition='opacity 0.5s';setTimeout(()=>t.remove(),500);}},3500);</script>
<?php endif; ?>

<?php if(isset($error_msg)): ?>
<div id="toast" class="toast toast-error">
    <span class="material-symbols-outlined">warning</span>
    <div><div style="font-weight:700;font-size:14px;">Error</div><div style="font-size:12px;opacity:.85;"><?php echo $error_msg; ?></div></div>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t){t.style.opacity='0';t.style.transition='opacity 0.5s';setTimeout(()=>t.remove(),500);}},3500);</script>
<?php endif; ?>

<?php if(isset($success_msg)): ?>
<div id="toast" class="toast toast-success">
    <svg width="22" height="22" viewBox="0 0 52 52"><path d="M14 27 L22 35 L38 18"/></svg>
    <div><div style="font-weight:700;font-size:14px;">Success</div><div style="font-size:12px;opacity:.85;"><?php echo $success_msg; ?></div></div>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t){t.style.opacity='0';t.style.transition='opacity 0.5s';setTimeout(()=>t.remove(),500);}},3500);</script>
<?php endif; ?>

<?php if(isset($_GET['error']) && $_GET['error']=='incomplete' && isset($_SESSION['done_error'])): ?>
<div id="toast" class="toast toast-error">
    <span class="material-symbols-outlined">warning</span>
    <div><div style="font-weight:700;font-size:14px;">Cannot Mark as Done</div><div style="font-size:12px;opacity:.85;"><?php echo $_SESSION['done_error']; unset($_SESSION['done_error']); ?></div></div>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t){t.style.opacity='0';t.style.transition='opacity 0.5s';setTimeout(()=>t.remove(),500);}},3500);</script>
<?php endif; ?>

<!-- HEADER -->
<header class="site-header">
  <div class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between gap-4 flex-wrap">
    <div class="flex items-center gap-4">
        <a href="lecturer_dashboard.php" class="flex items-center gap-2 text-blue-200 hover:text-white transition-colors text-sm font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Dashboard
        </a>
        <div class="w-px h-5 bg-white/20"></div>
        <div>
            <div class="text-xs text-blue-300 uppercase tracking-widest">Class</div>
            <h1 class="text-white font-bold text-xl leading-tight"><?php echo htmlspecialchars($class_name); ?></h1>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <?php if($is_penyelaras): ?>
            <span class="role-badge penyelaras">📌 Penyelaras</span>
        <?php else: ?>
            <span class="role-badge pensyarah">👨‍🏫 Pensyarah</span>
            <?php if($lecturer_group_name): ?>
                <span class="group-badge">📁 <?php echo htmlspecialchars($lecturer_group_name); ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <a href="lecture_history_plagiarism.php?class_id=<?php echo $class_id; ?>"
           class="btn" style="background:rgba(255,255,255,0.1);color:white;border:1px solid rgba(255,255,255,0.2);">
           <span class="material-symbols-outlined text-[16px]">history</span> History
        </a>
    </div>
  </div>
</header>

<!-- MAIN -->
<main class="max-w-7xl mx-auto px-6 py-8">

<!-- Top Actions -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-4">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Assignments</h2>
        <p class="text-sm text-slate-500 mt-1">
            <?php $total = count($assignments); echo $total; ?> assignment<?php echo $total!=1?'s':''; ?> pending
            <span class="text-xs text-blue-600 ml-2">(Your group only)</span>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <?php if($is_penyelaras): ?>
            <a href="lecturer_performance.php?class_id=<?php echo $class_id; ?>" 
               class="btn btn-performance">
                <span class="material-symbols-outlined text-[16px]">analytics</span> Performance Report
            </a>
        <?php endif; ?>
        <a href="create_assignment.php?class_id=<?php echo $class_id; ?>" 
           class="btn btn-primary">
            <span class="material-symbols-outlined text-[16px]">add</span> Create Assignment
        </a>
    </div>
</div>

<!-- Students Count -->
<div class="bg-white rounded-xl p-4 border border-slate-200 mb-6">
    <div class="flex items-center gap-4 flex-wrap">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-blue-600">groups</span>
            <span class="font-semibold"><?php echo count($students); ?></span>
            <span class="text-sm text-slate-500">student(s) in your group</span>
        </div>
        <?php if($lecturer_group_name): ?>
            <div class="text-xs text-slate-500 border-l border-slate-200 pl-4">
                📁 Group: <strong><?php echo htmlspecialchars($lecturer_group_name); ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Student List (Collapsible) -->
<div class="bg-white rounded-xl border border-slate-200 mb-6 overflow-hidden">
    <button onclick="toggleStudentList()" class="w-full px-5 py-3 bg-gray-50 hover:bg-gray-100 transition-colors flex justify-between items-center">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-blue-600">people</span>
            <span class="font-semibold text-slate-700">Students</span>
            <span class="text-sm text-slate-500">(<?php echo count($students); ?>)</span>
        </div>
        <span class="material-symbols-outlined text-slate-400" id="studentToggleIcon">expand_more</span>
    </button>
    <div id="studentListContainer" class="hidden overflow-x-auto">
        <table class="student-table w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Matric No</th>
                    <th class="px-4 py-3 text-left">Student Name</th>
                    <th class="px-4 py-3 text-left">Program</th>
                    <th class="px-4 py-3 text-center">Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($students)): ?>
                    <tr><td colspan="4" class="text-center text-slate-400 py-6">No students enrolled in your group yet.</td></tr>
                <?php else: ?>
                    <?php foreach($students as $student): ?>
                    <tr>
                        <td class="px-4 py-3 font-mono text-sm"><?php echo htmlspecialchars($student['matric_no']); ?></td>
                        <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td class="px-4 py-3">
                            <span class="badge badge-program"><?php echo htmlspecialchars($student['program']); ?></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-semibold <?php echo ($student['essay_submitted'] + $student['code_submitted']) > 0 ? 'text-green-600' : 'text-red-500'; ?>">
                                <?php echo ($student['essay_submitted'] + $student['code_submitted']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assignments -->
<?php if(empty($assignments)): ?>
<div class="empty-state">
    <span class="material-symbols-outlined text-6xl text-slate-300">assignment</span>
    <h3 class="text-lg font-semibold text-slate-600 mt-4">No pending assignments</h3>
    <p class="text-sm text-slate-400 mt-2">All assignments have been completed and moved to history.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <?php foreach($assignments as $assignment):
    $isEssay  = $assignment['type'] == 'essay';
    $isActive = $assignment['status'] === 'Active';
    $ungraded = ($isEssay) ? $assignment['ungraded_essay'] : $assignment['ungraded_code'];
    $total    = ($isEssay) ? $assignment['total_essay'] : $assignment['total_code'];
    $graded_complete = ($ungraded == 0 && $total > 0);
    $headerClass = $isEssay ? 'card-header-essay' : 'card-header-code';
  ?>
  <div class="assignment-card">
    <div class="<?php echo $headerClass; ?> px-6 py-5">
        <div class="flex items-start justify-between gap-3 mb-3">
            <h2 class="font-bold text-white text-base leading-snug flex-1"><?php echo htmlspecialchars($assignment['tittle']); ?></h2>
            <span class="<?php echo $isActive ? 'status-active' : 'status-pending'; ?> px-2.5 py-1 rounded-full text-xs font-semibold shrink-0 mt-0.5">
                <?php echo $assignment['status']; ?>
            </span>
        </div>
        <div class="flex flex-wrap gap-4">
            <span class="chip"><span class="material-symbols-outlined text-[14px]"><?php echo $isEssay?'description':'code'; ?></span><?php echo ucfirst($assignment['type']); ?></span>
            <span class="chip"><span class="material-symbols-outlined text-[14px]">calendar_today</span><?php echo date('d M Y', strtotime($assignment['due_date'])); ?></span>
            <span class="chip"><span class="material-symbols-outlined text-[14px]">person</span><?php echo htmlspecialchars($assignment['lecturer_name']); ?></span>
            <?php if($total > 0): ?>
                <span class="chip" style="color:<?php echo $graded_complete ? '#86efac' : '#fde68a'; ?>;">
                    <span class="material-symbols-outlined text-[14px]"><?php echo $graded_complete ? 'task_alt' : 'pending'; ?></span>
                    <?php echo $total - $ungraded; ?>/<?php echo $total; ?> graded
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if($isActive && $ungraded > 0): ?>
    <div class="ungraded-bar">
        <span class="material-symbols-outlined text-[15px]">warning</span>
        <?php echo $ungraded; ?> submission<?php echo $ungraded!=1?'s':''; ?> still need grading
    </div>
    <?php endif; ?>

    <div class="px-6 py-4 border-b border-slate-100">
        <p class="text-sm text-slate-600 leading-relaxed italic">
            <?php echo htmlspecialchars($assignment['description']) ?: '<span class="text-slate-400">No description provided.</span>'; ?>
        </p>
    </div>

    <!-- UPDATED: Buttons appear for both Active AND Pending assignments -->
    <div class="px-6 py-4 flex items-center justify-end gap-2 bg-slate-50 flex-wrap">
        <div class="btn-group">
            <?php if($isActive): ?>
                <!-- ACTIVE Assignment Buttons -->
                <a href="plagiarism_page.php?assignment_id=<?php echo $assignment['assignment_id']; ?>" class="btn btn-primary">
                    <span class="material-symbols-outlined text-[16px]">manage_search</span> Check Plagiarism
                </a>
                <?php if($graded_complete): ?>
                <a href="?class_id=<?php echo $class_id; ?>&action=mark_done&done_id=<?php echo $assignment['assignment_id']; ?>" 
                   class="btn btn-success">
                    <span class="material-symbols-outlined text-[16px]">check_circle</span> Done Grading
                </a>
                <?php else: ?>
                <button class="btn btn-disabled" disabled title="Grade all <?php echo $ungraded; ?> remaining student(s) first">
                    <span class="material-symbols-outlined text-[16px]">lock</span> Done (<?php echo $ungraded; ?> left)
                </button>
                <?php endif; ?>
                <a href="edit_assignment.php?assignment_id=<?php echo $assignment['assignment_id']; ?>" 
                   class="btn btn-edit">
                    <span class="material-symbols-outlined text-[16px]">edit</span> Edit
                </a>
                <a href="?class_id=<?php echo $class_id; ?>&action=delete&assignment_id=<?php echo $assignment['assignment_id']; ?>" 
                   class="btn btn-delete"
                   onclick="return confirm('Delete this assignment? This will also delete all submissions.')">
                    <span class="material-symbols-outlined text-[16px]">delete</span> Delete
                </a>
            <?php else: ?>
                <!-- PENDING (Not Due Yet) Assignment Buttons - Edit & Delete are visible -->
                <a href="edit_assignment.php?assignment_id=<?php echo $assignment['assignment_id']; ?>" 
                   class="btn btn-pending-edit">
                    <span class="material-symbols-outlined text-[16px]">edit</span> Edit
                </a>
                <a href="?class_id=<?php echo $class_id; ?>&action=delete&assignment_id=<?php echo $assignment['assignment_id']; ?>" 
                   class="btn btn-pending-delete"
                   onclick="return confirm('Delete this assignment? This will also delete all submissions.')">
                    <span class="material-symbols-outlined text-[16px]">delete</span> Delete
                </a>
                <button class="btn btn-disabled" disabled>
                    <span class="material-symbols-outlined text-[16px]">schedule</span> Not Due Yet
                </button>
            <?php endif; ?>
        </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</main>

<script>
function toggleStudentList() {
    const container = document.getElementById('studentListContainer');
    const icon = document.getElementById('studentToggleIcon');
    container.classList.toggle('hidden');
    icon.textContent = container.classList.contains('hidden') ? 'expand_more' : 'expand_less';
}
</script>

</body>
</html>