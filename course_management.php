<?php
session_start();
include 'includes/config.php';

// Check if user is logged in AND is an admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php?error=login_required");
    exit();
}

$success_msg = '';
$error_msg = '';

// ==========================================
// 1. FETCH ALL COURSES WITH THEIR LECTURERS - WITH PAGINATION AND FILTERS
// ==========================================
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$program_filter = isset($_GET['program']) ? trim($_GET['program']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$where_conditions = [];

// Program filter (BITS or BITD)
if (!empty($program_filter)) {
    $safe_program = mysqli_real_escape_string($conn, $program_filter);
    $where_conditions[] = "c.class_code LIKE '$safe_program%'";
}

// Search by course name only
if (!empty($search_term)) {
    $safe_search = mysqli_real_escape_string($conn, $search_term);
    $where_conditions[] = "c.class_name LIKE '%$safe_search%'";
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
}

// ==========================================
// COUNT TOTAL COURSES FOR PAGINATION
// ==========================================
$count_query = "
    SELECT COUNT(DISTINCT c.class_id) as total
    FROM class c
    $where_clause
";
$count_result = mysqli_query($conn, $count_query);
$total_courses = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_courses / $limit);

// ==========================================
// FETCH COURSES WITH PAGINATION
// ==========================================
$query = "
    SELECT DISTINCT c.class_id, c.class_code, c.class_name, c.coordinator_id,
           (SELECT COUNT(*) FROM course_lecturer WHERE class_id = c.class_id AND role = 'pensyarah') as total_pensyarah,
           (SELECT COUNT(*) FROM enrollment WHERE class_id = c.class_id) as total_students,
           COALESCE(
               (SELECT CONCAT(l.full_name, ' (Penyelaras)') 
                FROM course_lecturer cl 
                JOIN lecturer l ON cl.lecturer_id = l.lecturer_id 
                WHERE cl.class_id = c.class_id AND cl.role = 'penyelaras' 
                LIMIT 1), 
               'Not assigned'
           ) as penyelaras_info,
           (SELECT GROUP_CONCAT(
                CONCAT(l.full_name, ' (', COALESCE(cl.group_name, 'No Group'), ')') 
                SEPARATOR ' | '
            ) FROM course_lecturer cl 
            JOIN lecturer l ON cl.lecturer_id = l.lecturer_id 
            WHERE cl.class_id = c.class_id AND cl.role = 'pensyarah'
           ) as pensyarah_list
    FROM class c
    $where_clause
    ORDER BY c.class_code ASC
    LIMIT $limit OFFSET $offset
";
$courses_result = mysqli_query($conn, $query);
$all_courses = [];
while ($row = mysqli_fetch_assoc($courses_result)) {
    $all_courses[] = $row;
}

// ==========================================
// 2. FETCH ALL LECTURERS WITH THEIR CURRENT CLASS COUNT
// ==========================================
$lecturers_query = "
    SELECT l.lecturer_id, l.staff_id, l.full_name, l.lecturer_type, l.division,
           COUNT(DISTINCT cl.class_id) as current_class_count
    FROM lecturer l
    LEFT JOIN course_lecturer cl ON l.lecturer_id = cl.lecturer_id
    GROUP BY l.lecturer_id
    ORDER BY l.full_name ASC
";
$lecturers_result = mysqli_query($conn, $lecturers_query);
$all_lecturers = [];
while ($row = mysqli_fetch_assoc($lecturers_result)) {
    $all_lecturers[] = $row;
}

// ==========================================
// 3. HANDLE ASSIGN LECTURER TO COURSE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_lecturer'])) {
    $class_id = (int)$_POST['class_id'];
    $lecturer_id = (int)$_POST['lecturer_id'];
    $role = $_POST['role'];
    $group_name = trim($_POST['group_name'] ?? '');
    
    // === VALIDATION 1: Check if lecturer already assigned to this class ===
    $check_stmt = $conn->prepare("SELECT id, role FROM course_lecturer WHERE class_id = ? AND lecturer_id = ?");
    $check_stmt->bind_param("ii", $class_id, $lecturer_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing) {
        $error_msg = "❌ Lecturer is already assigned to this class as " . ucfirst($existing['role']) . "!";
    }
    
    // === VALIDATION 2: Check max subjects per lecturer (MAX 4) ===
    if (empty($error_msg)) {
        $count_subjects = $conn->prepare("SELECT COUNT(DISTINCT class_id) as total FROM course_lecturer WHERE lecturer_id = ?");
        $count_subjects->bind_param("i", $lecturer_id);
        $count_subjects->execute();
        $subject_count = $count_subjects->get_result()->fetch_assoc()['total'];
        $count_subjects->close();
        
        if ($subject_count >= 4) {
            $error_msg = "❌ This lecturer is already teaching 4 subjects. Maximum limit is 4 subjects per lecturer!";
        }
    }
    
    // === VALIDATION 3: Check max pensyarah (max 3) ===
    if (empty($error_msg) && $role == 'pensyarah') {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_lecturer WHERE class_id = ? AND role = 'pensyarah'");
        $count_stmt->bind_param("i", $class_id);
        $count_stmt->execute();
        $pensyarah_count = $count_stmt->get_result()->fetch_assoc()['count'];
        $count_stmt->close();
        
        if ($pensyarah_count >= 3) {
            $error_msg = "❌ Maximum 3 pensyarah per course! (Currently $pensyarah_count groups)";
        }
    }
    
    // === VALIDATION 4: Check if penyelaras already exists (only one) ===
    if (empty($error_msg) && $role == 'penyelaras') {
        $check_primary = $conn->prepare("SELECT id FROM course_lecturer WHERE class_id = ? AND role = 'penyelaras'");
        $check_primary->bind_param("i", $class_id);
        $check_primary->execute();
        if ($check_primary->get_result()->num_rows > 0) {
            $error_msg = "❌ This course already has a Penyelaras! Only one allowed.";
        }
        $check_primary->close();
    }
    
    // === VALIDATION 5: Group name required for pensyarah ===
    if (empty($error_msg) && $role == 'pensyarah' && empty($group_name)) {
        $error_msg = "❌ Please enter a group name for the lecturer.";
    }
    
    // === If all validations pass, proceed ===
    if (empty($error_msg)) {
        if ($role == 'penyelaras') {
            // Assign as penyelaras
            $stmt = $conn->prepare("INSERT INTO course_lecturer (class_id, lecturer_id, role, is_primary) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("iis", $class_id, $lecturer_id, $role);
            
            if ($stmt->execute()) {
                // Update class coordinator_id
                $update_class = $conn->prepare("UPDATE class SET coordinator_id = ? WHERE class_id = ?");
                $update_class->bind_param("ii", $lecturer_id, $class_id);
                $update_class->execute();
                $update_class->close();
                
                // Update lecturer type
                $update_lect = $conn->prepare("UPDATE lecturer SET lecturer_type = 'both' WHERE lecturer_id = ?");
                $update_lect->bind_param("i", $lecturer_id);
                $update_lect->execute();
                $update_lect->close();
                
                $success_msg = "✅ Penyelaras assigned successfully!";
            } else {
                $error_msg = "❌ Error assigning penyelaras: " . $conn->error;
            }
            $stmt->close();
            
        } else {
            // Assign as pensyarah with group
            // Count existing pensyarah groups for this class
            $group_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_lecturer WHERE class_id = ? AND role = 'pensyarah'");
            $group_count_stmt->bind_param("i", $class_id);
            $group_count_stmt->execute();
            $group_count_result = $group_count_stmt->get_result()->fetch_assoc();
            $group_count = $group_count_result['count'];
            $group_count_stmt->close();
            
            // Determine group name
            if (!empty($group_name)) {
                $default_group_name = $group_name;
            } else {
                // Auto-generate based on count
                $group_letters = ['A', 'B', 'C', 'D', 'E'];
                if ($group_count < count($group_letters)) {
                    $default_group_name = "Group " . $group_letters[$group_count];
                } else {
                    $default_group_name = "Group " . ($group_count + 1);
                }
            }
            
            // Insert as pensyarah
            $stmt = $conn->prepare("INSERT INTO course_lecturer (class_id, lecturer_id, role, is_primary, group_name) VALUES (?, ?, ?, 0, ?)");
            $stmt->bind_param("iiss", $class_id, $lecturer_id, $role, $default_group_name);
            
            if ($stmt->execute()) {
                // Update lecturer type
                $update_lect = $conn->prepare("UPDATE lecturer SET lecturer_type = 'both' WHERE lecturer_id = ?");
                $update_lect->bind_param("i", $lecturer_id);
                $update_lect->execute();
                $update_lect->close();
                
                $success_msg = "✅ Pensyarah assigned successfully to group: $default_group_name";
            } else {
                $error_msg = "❌ Error assigning pensyarah: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// ==========================================
// 4. HANDLE REMOVE LECTURER FROM COURSE
// ==========================================
if (isset($_GET['remove_id']) && isset($_GET['class_id'])) {
    $remove_id = (int)$_GET['remove_id'];
    $class_id = (int)$_GET['class_id'];
    
    // Get the role before deleting
    $role_stmt = $conn->prepare("SELECT role, lecturer_id FROM course_lecturer WHERE id = ? AND class_id = ?");
    $role_stmt->bind_param("ii", $remove_id, $class_id);
    $role_stmt->execute();
    $role_data = $role_stmt->get_result()->fetch_assoc();
    $role_stmt->close();
    
    if ($role_data) {
        // If removing penyelaras, clear coordinator_id from class
        if ($role_data['role'] == 'penyelaras') {
            $update_class = $conn->prepare("UPDATE class SET coordinator_id = NULL WHERE class_id = ?");
            $update_class->bind_param("i", $class_id);
            $update_class->execute();
            $update_class->close();
        }
        
        $del_stmt = $conn->prepare("DELETE FROM course_lecturer WHERE id = ? AND class_id = ?");
        $del_stmt->bind_param("ii", $remove_id, $class_id);
        if ($del_stmt->execute()) {
            $success_msg = "✅ Lecturer removed from course successfully!";
            header("Location: course_management.php?msg=removed");
            exit();
        } else {
            $error_msg = "❌ Error removing lecturer: " . $conn->error;
        }
        $del_stmt->close();
    }
}
if (isset($_GET['msg']) && $_GET['msg'] == 'removed') $success_msg = "✅ Lecturer removed successfully!";

// ==========================================
// 5. FETCH FOR EDIT MODE (View course details)
// ==========================================
$view_mode = false;
$view_course_id = 0;
$view_course = null;
$view_lecturers = [];

if (isset($_GET['view_id'])) {
    $view_mode = true;
    $view_course_id = (int)$_GET['view_id'];
    
    $view_stmt = $conn->prepare("SELECT * FROM class WHERE class_id = ?");
    $view_stmt->bind_param("i", $view_course_id);
    $view_stmt->execute();
    $view_course = $view_stmt->get_result()->fetch_assoc();
    $view_stmt->close();
    
    // Get lecturers for this course
    $lect_stmt = $conn->prepare("
        SELECT cl.id, cl.lecturer_id, cl.role, cl.is_primary, cl.group_name,
               l.full_name, l.staff_id, l.lecturer_type
        FROM course_lecturer cl
        JOIN lecturer l ON cl.lecturer_id = l.lecturer_id
        WHERE cl.class_id = ?
        ORDER BY FIELD(cl.role, 'penyelaras', 'pensyarah'), l.full_name ASC
    ");
    $lect_stmt->bind_param("i", $view_course_id);
    $lect_stmt->execute();
    $view_lecturers = $lect_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $lect_stmt->close();
    
    // Check if penyelaras exists
    $has_penyelaras = false;
    foreach ($view_lecturers as $lect) {
        if ($lect['role'] == 'penyelaras') {
            $has_penyelaras = true;
            break;
        }
    }
    
    // Get pensyarah count
    $pensyarah_count = 0;
    foreach ($view_lecturers as $lect) {
        if ($lect['role'] == 'pensyarah') {
            $pensyarah_count++;
        }
    }
}

// ==========================================
// 6. HANDLE UPDATE COURSE DETAILS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_course'])) {
    $class_id = (int)$_POST['class_id'];
    $class_code = trim($_POST['class_code']);
    $class_name = trim($_POST['class_name']);
    
    $update_stmt = $conn->prepare("UPDATE class SET class_code = ?, class_name = ? WHERE class_id = ?");
    $update_stmt->bind_param("ssi", $class_code, $class_name, $class_id);
    if ($update_stmt->execute()) {
        $success_msg = "✅ Course details updated successfully!";
    } else {
        $error_msg = "❌ Error updating course: " . $conn->error;
    }
    $update_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - SILS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #333; }
        .header { background: #003366; color: white; padding: 20px; text-align: center; position: relative; }
        .back-btn { position: absolute; left: 20px; top: 25px; color: white; text-decoration: none; font-weight: 500; background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 5px; }
        .back-btn:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fff0f0; color: #c00; border-left: 4px solid #c00; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }

        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card h2 { font-size: 18px; color: #003366; margin-bottom: 20px; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; }
        
        .badge-penyelaras { background: #003366; color: white; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-pensyarah { background: #e3f2fd; color: #1565c0; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-coordinator { background: #28a745; color: white; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-subject-count { 
            background: #f1f5f9; 
            color: #475569; 
            padding: 2px 10px; 
            border-radius: 12px; 
            font-size: 10px; 
            font-weight: 600; 
            display: inline-block; 
        }
        .badge-subject-count.full { 
            background: #fee2e2; 
            color: #991b1b; 
        }
        
        /* Search & Filter Container */
        .search-filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .search-filter-container input[type="text"] {
            flex: 2;
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }
        .search-filter-container select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            background: white;
            min-width: 150px;
            cursor: pointer;
        }
        .search-btn { 
            background: #003366; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            white-space: nowrap;
        }
        .search-btn:hover { background: #1a4d8c; }
        .clear-btn { 
            background: #6c757d; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            text-decoration: none; 
            white-space: nowrap;
        }
        .clear-btn:hover { background: #5a6268; }
        
        /* Pagination */
        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 14px;
            border: 1.5px solid #e1e5ee;
            border-radius: 7px;
            text-decoration: none;
            color: #003366;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .pagination a:hover {
            background: #003366;
            color: white;
            border-color: #003366;
        }
        .pagination .active {
            background: #003366;
            color: white;
            border-color: #003366;
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .result-count {
            text-align: center;
            font-size: 13px;
            color: #888;
            margin-top: 10px;
        }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fc; color: #003366; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
        
        .action-btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; margin-right: 5px; display: inline-block; }
        .btn-view { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .btn-view:hover { background: #bbdefb; }
        .btn-remove { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .btn-remove:hover { background: #ffcdd2; }
        .btn-primary { background: #003366; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: #1a4d8c; }
        .btn-success { background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-danger:hover { background: #c82333; }
        
        .info-note { background: #fff3cd; color: #856404; padding: 10px; border-radius: 6px; font-size: 13px; margin-top: 15px; }
        .info-note ul { margin: 5px 0 0 20px; font-size: 13px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 500; }
        .input-group input, .input-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 14px; }
        .submit-btn { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .submit-btn:hover { background: #218838; }
        
        .lecturer-list { margin-top: 10px; }
        .lecturer-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #f8f9fc; border-radius: 6px; margin-bottom: 5px; border-left: 3px solid #28a745; }
        .lecturer-item .role-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
        .lecturer-item .role-badge.penyelaras { background: #003366; color: white; }
        .lecturer-item .role-badge.pensyarah { background: #e3f2fd; color: #1565c0; }
        .lecturer-item .group-tag { font-size: 11px; color: #666; background: #f0f0f0; padding: 2px 10px; border-radius: 12px; }
        .lecturer-item .subject-count { font-size: 10px; color: #64748b; }
        .lecturer-item .subject-count.full { color: #dc2626; font-weight: 700; }
        
        .rules-box {
            background: #e8f0fe;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #003366;
        }
        .rules-box strong { color: #c62828; }
        .rules-box .check { color: #28a745; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
            background: #f9f9f9;
            border-radius: 10px;
            border: 1.5px dashed #d0d4dc;
        }
        
        .full-indicator {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
            margin-left: 6px;
        }
        
        @media (max-width: 768px) {
            .search-filter-container {
                flex-direction: column;
            }
            .search-filter-container input[type="text"] {
                min-width: unset;
            }
            .search-filter-container select {
                min-width: unset;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="admin_dashboard.php" class="back-btn">← Dashboard</a>
        <h1>📚 Course Management</h1>
    </div>

    <div class="container">
        <?php if($error_msg): ?>
            <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>
        <?php if($success_msg): ?>
            <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>

        <!-- Search & Course List -->
        <div class="card">
            <h2>📋 Course List</h2>
            
            <!-- Updated Search & Filter -->
            <form action="course_management.php" method="GET" class="search-filter-container">
                <input type="text" name="search" placeholder="🔍 Search by course name..." value="<?php echo htmlspecialchars($search_term); ?>">
                
                <select name="program">
                    <option value="">All Programs</option>
                    <option value="BITS" <?php echo $program_filter == 'BITS' ? 'selected' : ''; ?>>BITS - Software Engineering</option>
                    <option value="BITD" <?php echo $program_filter == 'BITD' ? 'selected' : ''; ?>>BITD - Database Management</option>
                </select>
                
                <button type="submit" class="search-btn">🔍 Search</button>
                
                <?php if(!empty($search_term) || !empty($program_filter)): ?>
                    <a href="course_management.php" class="clear-btn">✕ Clear</a>
                <?php endif; ?>
            </form>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Penyelaras</th>
                            <th>Pensyarah / Groups</th>
                            <th>Students</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($all_courses) > 0): ?>
                            <?php foreach($all_courses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['class_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['class_name']); ?></td>
                                    <td>
                                        <?php if($course['penyelaras_info'] && $course['penyelaras_info'] != 'Not assigned'): ?>
                                            <span class="badge-penyelaras">📌 <?php echo htmlspecialchars($course['penyelaras_info']); ?></span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">⚠️ No coordinator assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($course['pensyarah_list']): ?>
                                            <?php foreach(explode(' | ', $course['pensyarah_list']) as $p): ?>
                                                <span class="badge-pensyarah">👨‍🏫 <?php echo htmlspecialchars($p); ?></span>
                                                <br>
                                            <?php endforeach; ?>
                                            <span style="font-size: 11px; color: #666;">(<?php echo $course['total_pensyarah']; ?>/3 groups)</span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">No pensyarah assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $course['total_students']; ?></td>
                                    <td>
                                        <a href="course_management.php?view_id=<?php echo $course['class_id']; ?>&search=<?php echo urlencode($search_term); ?>&program=<?php echo urlencode($program_filter); ?>&page=<?php echo $page; ?>" class="action-btn btn-view">👁️ Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <?php if(!empty($search_term) || !empty($program_filter)): ?>
                                            <span class="material-symbols-outlined" style="font-size: 40px;">search_off</span><br>
                                            No courses found matching your filters.
                                        <?php else: ?>
                                            <span class="material-symbols-outlined" style="font-size: 40px;">school</span><br>
                                            No courses found in the system.
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Result Count -->
            <div class="result-count">
                Showing <?php echo count($all_courses); ?> of <?php echo $total_courses; ?> courses
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_term); ?>&program=<?php echo urlencode($program_filter); ?>">‹ Prev</a>
                <?php else: ?>
                    <span class="disabled">‹ Prev</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&program=<?php echo urlencode($program_filter); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_term); ?>&program=<?php echo urlencode($program_filter); ?>">Next ›</a>
                <?php else: ?>
                    <span class="disabled">Next ›</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- View/Manage Course -->
        <?php if($view_mode && $view_course): ?>
        <div class="card">
            <h2>
                <?php echo htmlspecialchars($view_course['class_code'] . ' - ' . $view_course['class_name']); ?>
                <span style="font-size: 14px; font-weight: 400; color: #666;">
                    (Max 3 groups)
                </span>
            </h2>
            
            <!-- Rules Box - Updated with subject limit -->
            <div class="rules-box">
                <strong>📋 Course Assignment Rules:</strong>
                <ul style="margin: 5px 0 0 20px;">
                    <li><span class="check">✓</span> <strong>WAJIB</strong> ada <strong>1 Penyelaras</strong> untuk setiap subjek</li>
                    <li><span class="check">✓</span> Maksimum <strong>3 Pensyarah</strong> dalam satu subjek</li>
                    <li><span class="check">✓</span> Seorang lecturer boleh mengajar <strong>MAKSIMUM 4 SUBJEK</strong> sahaja</li>
                    <li><span class="check">✓</span> Lecturer boleh jadi <strong>Penyelaras</strong> untuk banyak subjek</li>
                    <li><span class="check">✓</span> Lecturer boleh jadi <strong>Pensyarah</strong> untuk subjek lain</li>
                    <li><span class="check">✓</span> Seorang lecturer BOLEH jadi Penyelaras untuk Subjek A dan Pensyarah untuk Subjek B</li>
                </ul>
            </div>
            
            <!-- Current Status -->
            <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fc; border-radius: 6px;">
                <strong>Current Status:</strong>
                <?php 
                $has_penyelaras = false;
                $pensyarah_count = 0;
                foreach($view_lecturers as $lect) {
                    if($lect['role'] == 'penyelaras') $has_penyelaras = true;
                    if($lect['role'] == 'pensyarah') $pensyarah_count++;
                }
                ?>
                <span style="margin-left: 10px;">
                    <?php if($has_penyelaras): ?>
                        <span class="badge-coordinator">✅ Penyelaras assigned</span>
                    <?php else: ?>
                        <span style="color: #dc3545; font-weight: 600;">❌ No Penyelaras assigned (Required!)</span>
                    <?php endif; ?>
                </span>
                <span style="margin-left: 15px;">
                    Pensyarah: <strong><?php echo $pensyarah_count; ?>/3</strong>
                </span>
            </div>
            
            <!-- Update Course Details -->
            <form method="POST" style="margin-bottom: 20px;">
                <input type="hidden" name="class_id" value="<?php echo $view_course['class_id']; ?>">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Course Code</label>
                        <input type="text" name="class_code" value="<?php echo htmlspecialchars($view_course['class_code']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Course Name</label>
                        <input type="text" name="class_name" value="<?php echo htmlspecialchars($view_course['class_name']); ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_course" class="submit-btn">Update Course</button>
            </form>

            <hr style="margin: 20px 0;">

            <!-- Assign Lecturer Form -->
            <h3 style="margin-bottom: 15px; color: #003366;">➕ Assign Lecturer to Course</h3>
            
            <?php if(!$has_penyelaras): ?>
                <div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                    ⚠️ <strong>Warning:</strong> This course does not have a Penyelaras yet. Please assign one first.
                </div>
            <?php endif; ?>
            
            <?php if($pensyarah_count >= 3): ?>
                <div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                    ⚠️ <strong>Maximum groups reached!</strong> This course already has <?php echo $pensyarah_count; ?> pensyarah (max 3).
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="class_id" value="<?php echo $view_course['class_id']; ?>">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Select Lecturer</label>
                        <select name="lecturer_id" required>
                            <option value="">-- Choose Lecturer --</option>
                            <?php 
                            // Get already assigned lecturers for this class
                            $assigned_lecturers = array_column($view_lecturers, 'lecturer_id');
                            
                            // Determine which division to filter based on class code
                            $division_filter = '';
                            $class_code = $view_course['class_code'];
                            if (strpos($class_code, 'BITS') === 0) {
                                $division_filter = "AND division = 'JABATAN KEJURUTERAAN PERISIAN'";
                            } elseif (strpos($class_code, 'BITD') === 0) {
                                $division_filter = "AND division = 'JABATAN KEJURUTERAAN DATA GUNAAN'";
                            }
                            
                            // Fetch lecturers filtered by division
                            $filtered_lecturers_query = "
                                SELECT l.lecturer_id, l.staff_id, l.full_name, l.lecturer_type, l.division,
                                       COUNT(DISTINCT cl2.class_id) as current_subjects
                                FROM lecturer l
                                LEFT JOIN course_lecturer cl2 ON l.lecturer_id = cl2.lecturer_id
                                WHERE 1=1 $division_filter
                                GROUP BY l.lecturer_id
                                ORDER BY l.full_name ASC
                            ";
                            $filtered_lecturers_result = mysqli_query($conn, $filtered_lecturers_query);
                            $filtered_lecturers = [];
                            while ($row = mysqli_fetch_assoc($filtered_lecturers_result)) {
                                $filtered_lecturers[] = $row;
                            }
                            
                            foreach($filtered_lecturers as $lecturer): 
                                // Skip if already assigned to this class
                                if(in_array($lecturer['lecturer_id'], $assigned_lecturers)) continue;
                                
                                $is_full = $lecturer['current_subjects'] >= 4;
                            ?>
                                <option value="<?php echo $lecturer['lecturer_id']; ?>" <?php echo $is_full ? 'disabled style="color:#999;"' : ''; ?>>
                                    <?php echo htmlspecialchars($lecturer['full_name'] . ' (' . $lecturer['staff_id'] . ')'); ?>
                                    <?php if($lecturer['lecturer_type'] == 'penyelaras'): ?>
                                        [Penyelaras]
                                    <?php elseif($lecturer['lecturer_type'] == 'both'): ?>
                                        [Both]
                                    <?php endif; ?>
                                    <?php 
                                    $division_short = '';
                                    if ($lecturer['division'] == 'JABATAN KEJURUTERAAN PERISIAN') {
                                        $division_short = '🖥️ SW';
                                    } elseif ($lecturer['division'] == 'JABATAN KEJURUTERAAN DATA GUNAAN') {
                                        $division_short = '📊 DB';
                                    }
                                    if (!empty($division_short)): ?>
                                        <span style="font-size: 10px; color: #666;">[<?php echo $division_short; ?>]</span>
                                    <?php endif; ?>
                                    <span style="font-size: 10px; color: <?php echo $is_full ? '#dc2626' : '#64748b'; ?>;">
                                        (<?php echo $lecturer['current_subjects']; ?>/4 subjects)
                                        <?php if($is_full): ?>
                                            <span style="color: #dc2626; font-weight: 700;">🔴 FULL</span>
                                        <?php endif; ?>
                                    </span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Role</label>
                        <select name="role" required id="roleSelect" onchange="toggleGroupInput()">
                            <?php if(!$has_penyelaras): ?>
                                <option value="penyelaras">📌 Penyelaras (Required - Only ONE)</option>
                            <?php endif; ?>
                            <?php if($pensyarah_count < 3): ?>
                                <option value="pensyarah" <?php echo $has_penyelaras ? 'selected' : ''; ?>>👨‍🏫 Pensyarah (Group)</option>
                            <?php endif; ?>
                        </select>
                        <?php if($has_penyelaras && $pensyarah_count >= 3): ?>
                            <small style="color: #dc3545;">⚠️ Cannot assign more lecturers. Max 3 pensyarah reached.</small>
                        <?php endif; ?>
                    </div>
                    <div class="input-group" id="groupInputGroup" style="display: <?php echo (!$has_penyelaras) ? 'none' : 'block'; ?>;">
                        <label>Group Name</label>
                        <input type="text" name="group_name" placeholder="e.g., Group A, Group 1, Lab 1" 
                               <?php echo (!$has_penyelaras) ? 'disabled' : ''; ?>>
                        <small style="color: #666;">Required for Pensyarah. Max 3 groups per course.</small>
                    </div>
                </div>
                <button type="submit" name="assign_lecturer" class="btn-success" 
                        <?php echo ($has_penyelaras && $pensyarah_count >= 3) ? 'disabled' : ''; ?>>
                    ➕ Assign Lecturer
                </button>
            </form>

            <!-- Current Lecturers List -->
            <h3 style="margin: 20px 0 15px; color: #003366;">👨‍🏫 Current Lecturers</h3>
            <?php if(count($view_lecturers) > 0): ?>
                <div class="lecturer-list">
                    <?php foreach($view_lecturers as $lect): 
                        // Get current subject count for this lecturer
                        $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT class_id) as total FROM course_lecturer WHERE lecturer_id = ?");
                        $count_stmt->bind_param("i", $lect['lecturer_id']);
                        $count_stmt->execute();
                        $subject_count = $count_stmt->get_result()->fetch_assoc()['total'];
                        $count_stmt->close();
                        $is_full = $subject_count >= 4;
                    ?>
                        <div class="lecturer-item" style="border-left-color: <?php echo $lect['role'] == 'penyelaras' ? '#003366' : '#28a745'; ?>;">
                            <div>
                                <strong><?php echo htmlspecialchars($lect['full_name']); ?></strong>
                                <span style="color: #666; font-size: 12px;">(<?php echo htmlspecialchars($lect['staff_id']); ?>)</span>
                                <span class="role-badge <?php echo $lect['role']; ?>">
                                    <?php echo ucfirst($lect['role']); ?>
                                    <?php if($lect['is_primary']): ?> ⭐<?php endif; ?>
                                </span>
                                <?php if($lect['group_name']): ?>
                                    <span class="group-tag">📁 <?php echo htmlspecialchars($lect['group_name']); ?></span>
                                <?php endif; ?>
                                <span class="subject-count <?php echo $is_full ? 'full' : ''; ?>">
                                    📚 <?php echo $subject_count; ?>/4 subjects
                                    <?php if($is_full): ?>
                                        <span class="full-indicator">🔴 FULL</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div>
                                <?php if($lect['role'] == 'penyelaras'): ?>
                                    <span style="font-size: 11px; color: #28a745;">(Required)</span>
                                <?php endif; ?>
                                <a href="course_management.php?remove_id=<?php echo $lect['id']; ?>&class_id=<?php echo $view_course['class_id']; ?>&search=<?php echo urlencode($search_term); ?>&program=<?php echo urlencode($program_filter); ?>&page=<?php echo $page; ?>" 
                                   class="action-btn btn-remove"
                                   onclick="return confirm('Are you sure you want to remove this lecturer?<?php echo $lect['role'] == 'penyelaras' ? ' This will leave the course without a coordinator!' : ''; ?>');">
                                   🗑️ Remove
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #999; font-size: 13px;">No lecturers assigned to this course yet.</p>
            <?php endif; ?>

            <div class="info-note">
                💡 <strong>Note:</strong> 
                <ul>
                    <li>Only <strong>ONE</strong> Penyelaras per course.</li>
                    <li>Maximum <strong>3</strong> groups (Pensyarah) per course.</li>
                    <li>Each lecturer can teach a <strong>maximum of 4 subjects</strong>.</li>
                    <li>Students can enroll with any Pensyarah/Group.</li>
                    <li>Each Pensyarah will only see their own group's submissions.</li>
                    <li>Penyelaras can see <strong>ALL groups</strong> performance.</li>
                </ul>
            </div>

            <div style="margin-top: 15px;">
                <a href="course_management.php?search=<?php echo urlencode($search_term); ?>&program=<?php echo urlencode($program_filter); ?>&page=<?php echo $page; ?>" class="action-btn btn-view">← Back to Course List</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleGroupInput() {
            const roleSelect = document.getElementById('roleSelect');
            const groupInput = document.getElementById('groupInputGroup');
            const groupNameInput = groupInput.querySelector('input');
            
            if (roleSelect.value === 'pensyarah') {
                groupInput.style.display = 'block';
                groupNameInput.disabled = false;
                groupNameInput.required = true;
            } else {
                groupInput.style.display = 'none';
                groupNameInput.disabled = true;
                groupNameInput.required = false;
            }
        }
    </script>

</body>
</html>