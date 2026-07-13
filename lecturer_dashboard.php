<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$lecturer_id = null;

// Get lecturer info
$stmt = $conn->prepare("SELECT lecturer_id, full_name, email FROM lecturer WHERE staff_id = ?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$lecturer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if($lecturer){
    $lecturer_id = $lecturer['lecturer_id'];
} else {
    die("Lecturer not found.");
}

// ==========================================
// CHECK LECTURER ROLES
// ==========================================
$role_query = "SELECT role, is_primary, class_id, group_name FROM course_lecturer WHERE lecturer_id = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("i", $lecturer_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();

$is_penyelaras = false;
$is_pensyarah = false;
$lecturer_class_ids = [];
$lecturer_roles_by_class = [];

while ($row = $role_result->fetch_assoc()) {
    $class_id = $row['class_id'];
    $role = $row['role'];
    
    if ($role == 'penyelaras') {
        $is_penyelaras = true;
    }
    if ($role == 'pensyarah') {
        $is_pensyarah = true;
    }
    
    if (!in_array($class_id, $lecturer_class_ids)) {
        $lecturer_class_ids[] = $class_id;
    }
    
    $lecturer_roles_by_class[$class_id][] = $role;
}
$role_stmt->close();

// ==========================================
// FETCH ALL CLASSES (BOTH PENYELARAS AND PENSYARAH)
// ==========================================
if (!empty($lecturer_class_ids)) {
    $class_ids_string = implode(',', array_fill(0, count($lecturer_class_ids), '?'));
    $types = str_repeat('i', count($lecturer_class_ids));
    
    $class_sql = "
        SELECT 
            c.class_id, 
            c.class_name, 
            c.class_code,
            c.coordinator_id,
            COUNT(DISTINCT e.student_id) AS total_students,
            COUNT(DISTINCT cl2.lecturer_id) AS total_lecturers,
            GROUP_CONCAT(DISTINCT 
                CASE 
                    WHEN cl.role = 'penyelaras' THEN 'Penyelaras'
                    WHEN cl.role = 'pensyarah' THEN 'Pensyarah'
                END
            ) AS roles,
            GROUP_CONCAT(DISTINCT 
                CASE 
                    WHEN cl.role = 'pensyarah' THEN cl.group_name
                END
            ) AS group_names
        FROM class c
        JOIN course_lecturer cl ON c.class_id = cl.class_id
        LEFT JOIN enrollment e ON c.class_id = e.class_id
        LEFT JOIN course_lecturer cl2 ON c.class_id = cl2.class_id AND cl2.role = 'pensyarah'
        WHERE c.class_id IN ($class_ids_string) AND cl.lecturer_id = ?
        GROUP BY c.class_id
        ORDER BY c.class_name ASC
    ";
    
    $class_stmt = $conn->prepare($class_sql);
    
    $params = array_merge($lecturer_class_ids, [$lecturer_id]);
    $types = str_repeat('i', count($lecturer_class_ids)) . 'i';
    $class_stmt->bind_param($types, ...$params);
    
    $class_stmt->execute();
    $classes = $class_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $class_stmt->close();
} else {
    $classes = [];
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>SILS Lecturer Dashboard</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<style>
    body { font-family: 'Manrope', sans-serif; background: #f0f4f8; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    
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
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,51,102,0.08);
    }
    
    .class-card {
        background: white;
        border-radius: 16px;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .class-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px -8px rgba(0,51,102,0.15);
        border-color: #003366;
    }
    
    .group-item {
        background: #f8fafc;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 6px;
        border-left: 3px solid #003366;
        transition: all 0.2s ease;
    }
    .group-item:hover {
        background: #e8f0fe;
    }
    
    .badge-penyelaras { background: #003366; color: white; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .badge-pensyarah { background: #e3f2fd; color: #1565c0; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .badge-both { background: #8b5cf6; color: white; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    
    .role-tag {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
    }
    .role-tag.coordinator {
        background: #003366;
        color: white;
    }
    .role-tag.lecturer {
        background: #e3f2fd;
        color: #1565c0;
    }
    
    .btn-hover { transition: all 0.2s ease; }
    .btn-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,51,102,0.15); }
    
    .performance-btn {
        background: linear-gradient(135deg, #003366, #1a4d8c);
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .performance-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,51,102,0.3);
    }
    
    @media (max-width: 768px) {
        .stat-grid { grid-template-columns: 1fr 1fr; }
    }
</style>
</head>
<body>

<!-- Header -->
<header class="header-gradient text-white">
    <div class="max-w-7xl mx-auto px-6 py-5">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">👨‍🏫 Lecturer Dashboard</h1>
                <p class="text-white/70 text-sm mt-0.5">Welcome, <?php echo htmlspecialchars($lecturer['full_name']); ?></p>
            </div>
            <div class="flex items-center gap-3">
                <?php if($is_penyelaras && $is_pensyarah): ?>
                    <span class="badge-both">📌 Penyelaras & Pensyarah</span>
                <?php elseif($is_penyelaras): ?>
                    <span class="badge-penyelaras">📌 Penyelaras</span>
                <?php elseif($is_pensyarah): ?>
                    <span class="badge-pensyarah">👨‍🏫 Pensyarah</span>
                <?php endif; ?>
                <a href="logout.php" class="bg-white/10 backdrop-blur-sm px-4 py-2 rounded-xl text-sm font-semibold hover:bg-white/20 transition-all border border-white/20 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">logout</span> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-6 py-8">

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8 stat-grid">
    <div class="stat-card p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-700 text-2xl">class</span>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800"><?php echo count($classes); ?></p>
                <p class="text-xs text-slate-500">Class(es)</p>
            </div>
        </div>
    </div>
    <div class="stat-card p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-green-700 text-2xl">groups</span>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">
                    <?php 
                    $total_students = 0;
                    foreach($classes as $c) { $total_students += $c['total_students']; }
                    echo $total_students;
                    ?>
                </p>
                <p class="text-xs text-slate-500">Student(s)</p>
            </div>
        </div>
    </div>
    <div class="stat-card p-5">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-orange-700 text-2xl">assignment</span>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-800">
                    <?php 
                    $total_assignments = 0;
                    foreach($classes as $c) {
                        $assign_count = $conn->prepare("SELECT COUNT(*) as count FROM assignment WHERE class_id = ? AND lecturer_id = ?");
                        $assign_count->bind_param("ii", $c['class_id'], $lecturer_id);
                        $assign_count->execute();
                        $total_assignments += $assign_count->get_result()->fetch_assoc()['count'];
                        $assign_count->close();
                    }
                    echo $total_assignments;
                    ?>
                </p>
                <p class="text-xs text-slate-500">Assignment(s)</p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="flex flex-wrap gap-3 mb-8">
    <a href="create_assignment.php" class="bg-blue-800 text-white px-5 py-2.5 rounded-xl text-sm font-semibold btn-hover flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">add</span> Create Assignment
    </a>
</div>

<!-- Classes -->
<h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
    <span class="material-symbols-outlined text-blue-700">class</span> My Classes
</h2>

<?php if(empty($classes)): ?>
    <div class="bg-white rounded-xl p-10 text-center border border-dashed border-gray-300">
        <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">school</span>
        <p class="text-gray-500">You are not assigned to any classes yet.</p>
        <p class="text-sm text-gray-400 mt-1">Contact administrator to assign you to courses.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <?php foreach($classes as $class): 
            $roles = explode(',', $class['roles'] ?? '');
            $is_penyelaras_class = in_array('Penyelaras', $roles);
            $is_pensyarah_class = in_array('Pensyarah', $roles);
            $role_display = '';
            if ($is_penyelaras_class && $is_pensyarah_class) {
                $role_display = '<span class="role-tag coordinator">Penyelaras</span> <span class="role-tag lecturer">Pensyarah</span>';
            } elseif ($is_penyelaras_class) {
                $role_display = '<span class="role-tag coordinator">Penyelaras</span>';
            } elseif ($is_pensyarah_class) {
                $role_display = '<span class="role-tag lecturer">Pensyarah</span>';
            }
            
            $group_names = $class['group_names'] ?? '';
        ?>
        <a href="class_dashboard.php?class_id=<?php echo $class['class_id']; ?>" class="class-card">
            <!-- Class Header -->
            <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-5 py-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-bold text-white text-lg"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                        <p class="text-blue-200 text-sm"><?php echo htmlspecialchars($class['class_code']); ?></p>
                    </div>
                    <div class="flex gap-1 flex-wrap justify-end">
                        <?php echo $role_display; ?>
                    </div>
                </div>
                <div class="flex gap-5 mt-3 text-sm text-blue-100">
                    <span>👥 <?php echo $class['total_students']; ?> students</span>
                    <span>👨‍🏫 <?php echo $class['total_lecturers']; ?> lecturer(s)</span>
                    <?php if($group_names): ?>
                        <span>📁 <?php echo htmlspecialchars($group_names); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Body -->
            <div class="p-5">
                <div class="flex items-center justify-end text-sm text-blue-600">
                    <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        Click to view class
                    </span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</main>
</body>
</html>