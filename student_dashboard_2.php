<?php
session_start();
include 'includes/config.php';

// ==========================================
// CHECK: STUDENT ONLY
// ==========================================
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php?error=login_required");
    exit();
}

$student_id = $_SESSION['user_id'];
$student_program = $_SESSION['program']; // Get student's program (BITS or BITD)
$enroll_msg = '';
$unenroll_msg = '';

// ==========================================
// HANDLE NEW CLASS ENROLLMENT (WITH LECTURER)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll_class_id']) && isset($_POST['enroll_lecturer_id'])) {
    $class_to_enroll = (int)$_POST['enroll_class_id'];
    $lecturer_to_enroll = (int)$_POST['enroll_lecturer_id'];
    
    // Check if already enrolled in this class with any lecturer
    $check_sql = "SELECT * FROM ENROLLMENT WHERE student_id = ? AND class_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $student_id, $class_to_enroll);
    mysqli_stmt_execute($check_stmt);
    
    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
        $enroll_msg = "<div class='alert alert-error'>❌ You are already enrolled in this class.</div>";
    } else {
        // Check if lecturer is assigned to this class as pensyarah OR penyelaras
        $check_lect = "SELECT id, group_name, role FROM course_lecturer WHERE class_id = ? AND lecturer_id = ? AND role IN ('pensyarah', 'penyelaras')";
        $check_lect_stmt = mysqli_prepare($conn, $check_lect);
        mysqli_stmt_bind_param($check_lect_stmt, "ii", $class_to_enroll, $lecturer_to_enroll);
        mysqli_stmt_execute($check_lect_stmt);
        $lect_result = mysqli_stmt_get_result($check_lect_stmt);
        $lect_data = mysqli_fetch_assoc($lect_result);
        
        if (!$lect_data) {
            $enroll_msg = "<div class='alert alert-error'>❌ This lecturer is not assigned to this class as Pensyarah or Penyelaras.</div>";
        } else {
            // Check if group is full (max 50 students per group)
            $count_sql = "SELECT COUNT(*) as count FROM ENROLLMENT WHERE class_id = ? AND lecturer_id = ?";
            $count_stmt = mysqli_prepare($conn, $count_sql);
            mysqli_stmt_bind_param($count_stmt, "ii", $class_to_enroll, $lecturer_to_enroll);
            mysqli_stmt_execute($count_stmt);
            $count_result = mysqli_stmt_get_result($count_stmt);
            $count_row = mysqli_fetch_assoc($count_result);
            
            if ($count_row['count'] >= 50) {
                $enroll_msg = "<div class='alert alert-error'>❌ This group is full (max 50 students). Please choose another group.</div>";
            } else {
                $insert_sql = "INSERT INTO ENROLLMENT (student_id, class_id, lecturer_id) VALUES (?, ?, ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "iii", $student_id, $class_to_enroll, $lecturer_to_enroll);
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    // Log history
                    $history_sql = "INSERT INTO student_group_history (student_id, class_id, new_lecturer_id, changed_by) 
                                    VALUES (?, ?, ?, ?)";
                    $history_stmt = mysqli_prepare($conn, $history_sql);
                    mysqli_stmt_bind_param($history_stmt, "iiii", $student_id, $class_to_enroll, $lecturer_to_enroll, $student_id);
                    mysqli_stmt_execute($history_stmt);
                    
                    $group_name = $lect_data['group_name'] ?? 'Unnamed Group';
                    $role_text = $lect_data['role'] == 'penyelaras' ? 'Penyelaras' : 'Pensyarah';
                    $enroll_msg = "<div class='alert alert-success'>✅ Successfully enrolled in <strong>" . htmlspecialchars($group_name) . "</strong> ($role_text)!</div>";
                } else {
                    $enroll_msg = "<div class='alert alert-error'>❌ Error enrolling in class.</div>";
                }
            }
        }
    }
}

// ==========================================
// HANDLE UNENROLL FROM CLASS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unenroll_class_id'])) {
    $class_to_unenroll = (int)$_POST['unenroll_class_id'];
    
    // Check if student has any submissions for this class
    $check_submissions_sql = "
        SELECT 
            (SELECT COUNT(*) FROM essay_submission es 
             JOIN assignment a ON es.assignment_id = a.assignment_id 
             WHERE es.student_id = ? AND a.class_id = ?) as essay_count,
            (SELECT COUNT(*) FROM code_submission cs 
             JOIN assignment a ON cs.assignment_id = a.assignment_id 
             WHERE cs.student_id = ? AND a.class_id = ?) as code_count
    ";
    $check_stmt = mysqli_prepare($conn, $check_submissions_sql);
    mysqli_stmt_bind_param($check_stmt, "iiii", $student_id, $class_to_unenroll, $student_id, $class_to_unenroll);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $submission_counts = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);
    
    if ($submission_counts['essay_count'] > 0 || $submission_counts['code_count'] > 0) {
        $unenroll_msg = "<div class='alert alert-error'>❌ Cannot unenroll: You have already submitted assignments for this class.</div>";
    } else {
        $delete_sql = "DELETE FROM ENROLLMENT WHERE student_id = ? AND class_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "ii", $student_id, $class_to_unenroll);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $unenroll_msg = "<div class='alert alert-success'>✅ Successfully unenrolled from the class.</div>";
        } else {
            $unenroll_msg = "<div class='alert alert-error'>❌ Error unenrolling from class.</div>";
        }
    }
}

// ==========================================
// FETCH ENROLLED CLASSES WITH GROUP INFO (FILTERED BY PROGRAM)
// ==========================================
$enrolled_sql = "
    SELECT c.class_id, c.class_name, c.class_code, 
           e.lecturer_id, l.full_name AS lecturer_name,
           COALESCE(cl.group_name, '') AS group_name, 
           cl.role,
           (SELECT COUNT(*) FROM assignment a 
            WHERE a.class_id = c.class_id 
            AND a.lecturer_id = e.lecturer_id 
            AND a.is_completed = 0) AS pending_assignments
    FROM CLASS c
    JOIN ENROLLMENT e ON c.class_id = e.class_id
    JOIN lecturer l ON e.lecturer_id = l.lecturer_id
    LEFT JOIN course_lecturer cl ON e.lecturer_id = cl.lecturer_id AND c.class_id = cl.class_id
    WHERE e.student_id = ?
      AND c.class_code LIKE ?  -- Filter by student's program
    ORDER BY c.class_name ASC
";
$program_like = $student_program . '%'; // BITS% or BITD%
$enrolled_stmt = mysqli_prepare($conn, $enrolled_sql);
mysqli_stmt_bind_param($enrolled_stmt, "is", $student_id, $program_like);
mysqli_stmt_execute($enrolled_stmt);
$enrolled_classes = mysqli_stmt_get_result($enrolled_stmt);

// ==========================================
// FETCH AVAILABLE CLASSES (WITH GROUPS/LECTURERS) - WITH PAGINATION
// ==========================================
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Count total available classes (filtered by program)
if (!empty($search_term)) {
    $like_term = "%{$search_term}%";
    $count_sql = "
        SELECT COUNT(DISTINCT c.class_id) as total
        FROM CLASS c
        JOIN course_lecturer cl ON c.class_id = cl.class_id
        JOIN lecturer l ON cl.lecturer_id = l.lecturer_id
        WHERE c.class_id NOT IN (SELECT class_id FROM ENROLLMENT WHERE student_id = ?)
        AND cl.role IN ('pensyarah', 'penyelaras')
        AND c.class_code LIKE ?  -- Filter by student's program
        AND (c.class_name LIKE ? OR c.class_code LIKE ? OR l.full_name LIKE ? OR cl.group_name LIKE ?)
    ";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, "issss", $student_id, $program_like, $like_term, $like_term, $like_term, $like_term);
} else {
    $count_sql = "
        SELECT COUNT(DISTINCT c.class_id) as total
        FROM CLASS c
        JOIN course_lecturer cl ON c.class_id = cl.class_id
        WHERE c.class_id NOT IN (SELECT class_id FROM ENROLLMENT WHERE student_id = ?)
        AND cl.role IN ('pensyarah', 'penyelaras')
        AND c.class_code LIKE ?  -- Filter by student's program
    ";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, "is", $student_id, $program_like);
}
mysqli_stmt_execute($count_stmt);
$total_available = mysqli_stmt_get_result($count_stmt)->fetch_assoc()['total'];
$total_pages = ceil($total_available / $limit);

// Fetch available classes with pagination (filtered by program)
if (!empty($search_term)) {
    $like_term = "%{$search_term}%";
    $available_sql = "
        SELECT 
            c.class_id, 
            c.class_name, 
            c.class_code,
            cl.lecturer_id,
            l.full_name AS lecturer_name,
            l.staff_id,
            cl.group_name,
            cl.role,
            (SELECT COUNT(*) FROM ENROLLMENT WHERE class_id = c.class_id AND lecturer_id = cl.lecturer_id) as enrolled_count,
            (SELECT COUNT(*) FROM course_lecturer WHERE class_id = c.class_id AND role = 'penyelaras') as has_penyelaras
        FROM CLASS c
        JOIN course_lecturer cl ON c.class_id = cl.class_id
        JOIN lecturer l ON cl.lecturer_id = l.lecturer_id
        WHERE c.class_id NOT IN (SELECT class_id FROM ENROLLMENT WHERE student_id = ?)
        AND cl.role IN ('pensyarah', 'penyelaras')
        AND c.class_code LIKE ?  -- Filter by student's program
        AND (c.class_name LIKE ? OR c.class_code LIKE ? OR l.full_name LIKE ? OR cl.group_name LIKE ?)
        ORDER BY c.class_name ASC, cl.group_name ASC
        LIMIT ? OFFSET ?
    ";
    $available_stmt = mysqli_prepare($conn, $available_sql);
    mysqli_stmt_bind_param($available_stmt, "isssssii", $student_id, $program_like, $like_term, $like_term, $like_term, $like_term, $limit, $offset);
} else {
    $available_sql = "
        SELECT 
            c.class_id, 
            c.class_name, 
            c.class_code,
            cl.lecturer_id,
            l.full_name AS lecturer_name,
            l.staff_id,
            cl.group_name,
            cl.role,
            (SELECT COUNT(*) FROM ENROLLMENT WHERE class_id = c.class_id AND lecturer_id = cl.lecturer_id) as enrolled_count,
            (SELECT COUNT(*) FROM course_lecturer WHERE class_id = c.class_id AND role = 'penyelaras') as has_penyelaras
        FROM CLASS c
        JOIN course_lecturer cl ON c.class_id = cl.class_id
        JOIN lecturer l ON cl.lecturer_id = l.lecturer_id
        WHERE c.class_id NOT IN (SELECT class_id FROM ENROLLMENT WHERE student_id = ?)
        AND cl.role IN ('pensyarah', 'penyelaras')
        AND c.class_code LIKE ?  -- Filter by student's program
        ORDER BY c.class_name ASC, cl.group_name ASC
        LIMIT ? OFFSET ?
    ";
    $available_stmt = mysqli_prepare($conn, $available_sql);
    mysqli_stmt_bind_param($available_stmt, "isii", $student_id, $program_like, $limit, $offset);
}
mysqli_stmt_execute($available_stmt);
$available_classes = mysqli_stmt_get_result($available_stmt);

// ==========================================
// FETCH PENDING ASSIGNMENTS - FIXED VERSION
// ==========================================
// Get the student's enrolled classes with their lecturer IDs
$enrolled_classes_sql = "
    SELECT class_id, lecturer_id 
    FROM ENROLLMENT 
    WHERE student_id = ?
";
$enrolled_stmt2 = $conn->prepare($enrolled_classes_sql);
$enrolled_stmt2->bind_param("i", $student_id);
$enrolled_stmt2->execute();
$enrolled_result = $enrolled_stmt2->get_result();
$enrolled_classes_list = [];
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_classes_list[] = $row;
}
$enrolled_stmt2->close();

// Initialize as empty array
$new_assignments_data = [];

if (!empty($enrolled_classes_list)) {
    $class_ids = array_column($enrolled_classes_list, 'class_id');
    $lecturer_ids = array_column($enrolled_classes_list, 'lecturer_id');
    
    // Create placeholders for class IDs
    $class_placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    $lecturer_placeholders = implode(',', array_fill(0, count($lecturer_ids), '?'));
    
    $sql = "
        SELECT DISTINCT a.assignment_id, a.tittle, a.type, a.due_date, a.description,
               c.class_name, c.class_code,
               l.full_name AS lecturer_name,
               cl.group_name
        FROM assignment a
        INNER JOIN class c ON a.class_id = c.class_id
        LEFT JOIN lecturer l ON a.lecturer_id = l.lecturer_id
        LEFT JOIN course_lecturer cl ON a.lecturer_id = cl.lecturer_id AND a.class_id = cl.class_id
        LEFT JOIN code_submission cs ON a.assignment_id = cs.assignment_id AND cs.student_id = ?
        LEFT JOIN essay_submission es ON a.assignment_id = es.assignment_id AND es.student_id = ?
        WHERE a.due_date >= NOW()
          AND a.is_completed = 0
          AND c.class_code LIKE ?
          AND a.class_id IN ($class_placeholders)
          AND (a.lecturer_id IN ($lecturer_placeholders) OR a.lecturer_id IS NULL OR a.lecturer_id = 0)
          AND cs.assignment_id IS NULL 
          AND es.assignment_id IS NULL
        ORDER BY a.due_date ASC
    ";
    
    // Build parameters
    $params = array_merge(
        [$student_id, $student_id, $program_like],
        $class_ids,
        $lecturer_ids
    );
    
    // Build types string
    $types = "iis";
    $types .= str_repeat('i', count($class_ids));
    $types .= str_repeat('i', count($lecturer_ids));
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all results into an array
    while ($row = $result->fetch_assoc()) {
        $new_assignments_data[] = $row;
    }
    $stmt->close();
}

// ==========================================
// FETCH ENROLLED COURSES COUNT
// ==========================================
$count_sql = "SELECT COUNT(*) as total FROM ENROLLMENT WHERE student_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $student_id);
$count_stmt->execute();
$total_enrolled = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SILS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; color: #333; }
        .header { background: #003366; color: white; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .alert-error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }

        .profile-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; border-left: 6px solid #003366; flex-wrap: wrap; gap: 15px; }
        .profile-info h2 { font-size: 22px; margin-bottom: 5px; color: #003366; }
        .profile-info p { color: #666; font-size: 14px; line-height: 1.6; }
        .logout-btn { background: #c00; color: white; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background 0.3s; }
        .logout-btn:hover { background: #a00; }
        
        .program-badge { 
            display: inline-block; 
            background: #003366; 
            color: white; 
            padding: 2px 12px; 
            border-radius: 12px; 
            font-size: 12px; 
            font-weight: 600;
            margin-left: 8px;
        }

        .section-title { font-size: 20px; color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #ddd; display: flex; align-items: center; gap: 10px; }
        .section-title .material-symbols-outlined { color: #003366; }

        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .action-card { background: white; padding: 25px; border-radius: 12px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 2px solid transparent; transition: all 0.3s ease; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .action-card.grammar { border-color: #003366; }
        .action-card.study { border-color: #8b0000; }
        .action-card.submit { border-color: #2e7d32; }
        .card-icon { font-size: 40px; margin-bottom: 15px; }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 10px; }
        .card-desc { font-size: 13px; color: #666; }

        .class-management-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 40px; }
        @media (max-width: 768px) { .class-management-grid { grid-template-columns: 1fr; } }

        .enrolled-box, .available-box { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .enrolled-box h3, .available-box h3 { font-size: 16px; color: #003366; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; display: flex; align-items: center; gap: 8px; }
        
        .class-list { display: flex; flex-direction: column; gap: 12px; max-height: 350px; overflow-y: auto; padding-right: 5px; }
        .class-list::-webkit-scrollbar { width: 6px; }
        .class-list::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .class-list::-webkit-scrollbar-thumb { background: #003366; border-radius: 3px; }
        
        .class-item { background: #f8f9fc; border: 1px solid #e1e5ee; padding: 12px 15px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; }
        .class-item:hover { background: #e8f0fe; border-color: #003366; }
        .class-info { display: flex; align-items: center; gap: 12px; flex: 1; }
        .class-icon { font-size: 28px; background: #e6f0fa; padding: 8px; border-radius: 10px; color: #003366; }
        .class-details h4 { font-size: 15px; margin-bottom: 3px; color: #003366; }
        .class-details p { font-size: 11px; color: #666; }
        .lecturer-tag { font-size: 10px; background: #e8f0fe; color: #1a56db; padding: 2px 8px; border-radius: 12px; display: inline-block; margin-top: 3px; }
        .group-tag { font-size: 10px; background: #e0f7fa; color: #006064; padding: 2px 8px; border-radius: 12px; display: inline-block; margin-top: 3px; }
        .role-tag { font-size: 10px; background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 12px; display: inline-block; margin-top: 3px; }
        .pending-badge { font-size: 10px; background: #fff3e0; color: #e65100; padding: 2px 8px; border-radius: 12px; display: inline-block; margin-top: 3px; }
        
        .unenroll-btn { background: #dc3545; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: background 0.2s; }
        .unenroll-btn:hover { background: #c82333; }

        .assignment-card { background: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 10px; border-left: 4px solid #28a745; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .assignment-card .card-title { font-weight: 600; color: #003366; }
        .assignment-card .assignment-info { flex: 1; }
        .assignment-card .assignment-info p { font-size: 13px; color: #555; margin: 2px 0; }
        .submit-btn { background: #28a745; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; font-size: 13px; transition: background 0.3s; }
        .submit-btn:hover { background: #218838; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
            background: #f9f9f9;
            border-radius: 10px;
            border: 1.5px dashed #d0d4dc;
        }
        .empty-state .material-symbols-outlined {
            color: #bbb;
        }

        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: white; border-radius: 16px; padding: 25px; max-width: 400px; width: 90%;
            text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            transform: scale(0.95); transition: transform 0.2s;
        }
        .modal-overlay.open .modal-box { transform: scale(1); }
        .modal-icon { font-size: 50px; margin-bottom: 10px; }
        .modal-title { font-size: 18px; font-weight: 700; margin-bottom: 10px; }
        .modal-body { font-size: 13px; color: #666; margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-cancel { padding: 8px 20px; background: #e0e0e0; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .modal-confirm { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }

        /* Search & Filter */
        .search-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1.5px solid #e1e5ee;
            border-radius: 9px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            min-width: 180px;
            transition: border-color 0.2s;
        }
        .search-input:focus {
            border-color: #003366;
        }
        .search-btn {
            background: #003366;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .search-btn:hover {
            background: #1a4d8c;
        }
        .clear-search {
            background: #f1f3f5;
            color: #555;
            border: none;
            padding: 10px 16px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s;
        }
        .clear-search:hover {
            background: #e2e6ea;
        }

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

        /* Accordion styles for available classes */
        .course-accordion {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .acc-card {
            border: 1.5px solid #e1e5ee;
            border-radius: 11px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .acc-card.open {
            box-shadow: 0 4px 18px rgba(0,51,102,0.09);
            border-color: #b3c6e0;
        }
        .acc-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 16px;
            cursor: pointer;
            user-select: none;
            background: #f7f9fc;
            transition: background 0.15s;
        }
        .acc-header:hover {
            background: #eef2fb;
        }
        .acc-header-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .acc-course-name {
            font-size: 14px;
            font-weight: 700;
            color: #003366;
        }
        .acc-course-meta {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }
        .acc-code {
            font-size: 11px;
            color: #888;
            font-weight: 500;
        }
        .acc-penyelaras {
            font-size: 10px;
            background: #003366;
            color: white;
            padding: 2px 10px;
            border-radius: 10px;
            font-weight: 600;
        }
        .acc-chevron {
            font-size: 16px;
            color: #888;
            transition: transform 0.25s;
            flex-shrink: 0;
        }
        .acc-card.open .acc-chevron {
            transform: rotate(180deg);
        }
        .acc-body {
            display: none;
            padding: 0 14px 14px;
        }
        .acc-card.open .acc-body {
            display: block;
        }
        .group-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .group-table thead tr {
            background: #f0f4fb;
        }
        .group-table th {
            font-size: 10px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            text-align: left;
        }
        .group-table td {
            padding: 9px 10px;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .group-table tr:last-child td {
            border-bottom: none;
        }
        .group-table tr:hover td {
            background: #f7f9fc;
        }
        .td-lecturer {
            font-weight: 600;
            color: #003366;
        }
        .td-staff {
            font-size: 11px;
            color: #888;
        }
        .td-group {
            font-size: 11px;
            background: #e0f7fa;
            color: #006064;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-block;
        }
        .td-role {
            font-size: 10px;
            background: #fef3c7;
            color: #92400e;
            padding: 2px 10px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-block;
        }
        .capacity-bar-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .capacity-bar {
            flex: 1;
            height: 5px;
            background: #e9ecef;
            border-radius: 3px;
            min-width: 60px;
        }
        .capacity-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: #28a745;
            transition: width 0.4s ease;
        }
        .capacity-bar-fill.warn {
            background: #f59e0b;
        }
        .capacity-bar-fill.full {
            background: #dc3545;
        }
        .capacity-text {
            font-size: 11px;
            color: #666;
            white-space: nowrap;
            font-weight: 500;
        }
        .enroll-btn-small {
            background: #003366;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .enroll-btn-small:hover {
            background: #1a4d8c;
            transform: translateY(-1px);
        }
        .badge-full {
            background: #ffebee;
            color: #c62828;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 EDUASSIST Student Dashboard</h1>
    </div>

    <div class="container">
        <?php echo $enroll_msg; ?>
        <?php echo $unenroll_msg; ?>

        <div class="profile-card">
            <div class="profile-info">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h2>
                <p>
                    <strong>Matric No:</strong> <?php echo htmlspecialchars($_SESSION['matric_no']); ?> 
                    | <strong>Program:</strong> <?php echo htmlspecialchars($_SESSION['program']); ?>
                    <span class="program-badge">
                        <?php echo htmlspecialchars($_SESSION['program']); ?>
                    </span>
                </p>
                <p style="font-size: 12px; color: #888;">
                    Enrolled in <?php echo $total_enrolled; ?> class(es) 
                    | Showing <strong><?php echo htmlspecialchars($_SESSION['program']); ?></strong> program subjects only
                </p>
            </div>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>

        <h3 class="section-title">
            <span class="material-symbols-outlined">smart_toy</span>
            Academic Tools
        </h3>
        <div class="action-grid">
            <a href="grammar_check.php" class="action-card grammar">
                <div class="card-icon">📝</div>
                <div class="card-title">Grammar Checker</div>
                <div class="card-desc">Real‑time grammar, spelling, and style suggestions.</div>
            </a>
            <a href="study_helper.php" class="action-card study">
                <div class="card-icon">🤖</div>
                <div class="card-title">Study Helper</div>
                <div class="card-desc">Socratic AI – ask guiding questions, learn by thinking.</div>
            </a>
            <a href="Assignment_Select.php" class="action-card submit">
                <div class="card-icon">📤</div>
                <div class="card-title">Submit Assignment</div>
                <div class="card-desc">View and submit your pending assignments.</div>
            </a>
        </div>

        <h3 class="section-title">
            <span class="material-symbols-outlined">class</span>
            Class Management
            <span style="font-size: 14px; font-weight: 400; color: #666; margin-left: 10px;">
                (<?php echo htmlspecialchars($_SESSION['program']); ?> Program)
            </span>
        </h3>
        <div class="class-management-grid">
            
            <!-- Enrolled Classes -->
            <div class="enrolled-box">
                <h3>
                    <span class="material-symbols-outlined" style="font-size: 20px;">check_circle</span>
                    My Active Classes
                </h3>
                <div class="class-list">
                    <?php if (mysqli_num_rows($enrolled_classes) > 0): ?>
                        <?php while ($class = mysqli_fetch_assoc($enrolled_classes)): ?>
                            <div class="class-item">
                                <div class="class-info">
                                    <div class="class-icon">📖</div>
                                    <div class="class-details">
    <h4><?php echo htmlspecialchars($class['class_name']); ?></h4>
    <p>Code: <?php echo htmlspecialchars($class['class_code']); ?></p>
    <span class="lecturer-tag">👨‍🏫 <?php echo htmlspecialchars($class['lecturer_name']); ?></span>
    <?php 
    // Display group name with fallback
    $display_group = $class['group_name'] ?? '';
    if (empty($display_group) && $class['role'] == 'pensyarah') {
        // Try to get group name from course_lecturer for this specific class and lecturer
        $group_fetch_sql = "SELECT group_name FROM course_lecturer WHERE class_id = ? AND lecturer_id = ? AND role = 'pensyarah'";
        $group_fetch_stmt = $conn->prepare($group_fetch_sql);
        $group_fetch_stmt->bind_param("ii", $class['class_id'], $class['lecturer_id']);
        $group_fetch_stmt->execute();
        $group_result = $group_fetch_stmt->get_result();
        if ($group_row = $group_result->fetch_assoc()) {
            $display_group = $group_row['group_name'] ?? '';
        }
        $group_fetch_stmt->close();
    }
    if (!empty($display_group)): ?>
        <span class="group-tag">📁 <?php echo htmlspecialchars($display_group); ?></span>
    <?php elseif($class['role'] == 'penyelaras'): ?>
        <span class="group-tag">📁 Coordinator Group</span>
    <?php endif; ?>
    <?php if($class['role']): ?>
        <span class="role-tag"><?php echo ucfirst($class['role']); ?></span>
    <?php endif; ?>
    <?php if($class['pending_assignments'] > 0): ?>
        <span class="pending-badge">📝 <?php echo $class['pending_assignments']; ?> pending</span>
    <?php endif; ?>
</div>
                                </div>
                                <form method="POST" onsubmit="return false;">
                                    <input type="hidden" name="unenroll_class_id" value="<?php echo $class['class_id']; ?>">
                                    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($class['class_name']); ?>">
                                    <button type="button" class="unenroll-btn" onclick="openUnenrollModal(<?php echo $class['class_id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>')">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">exit_to_app</span> Unenroll
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-symbols-outlined" style="font-size: 40px;">school</span><br>
                            You haven't enrolled in any <?php echo htmlspecialchars($_SESSION['program']); ?> classes yet.<br>
                            Browse available classes below!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Classes -->
            <div class="available-box">
                <h3>
                    <span class="material-symbols-outlined" style="font-size: 20px;">add_circle</span>
                    Available Classes
                    <span style="font-size: 12px; font-weight: 400; color: #888; margin-left: 10px;">
                        (<?php echo $total_available; ?> <?php echo htmlspecialchars($_SESSION['program']); ?> classes available)
                    </span>
                </h3>
                
                <form method="GET" action="" class="search-container">
                    <input type="text" name="search" class="search-input" placeholder="🔍 Search by course name, code, lecturer, or group..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit" class="search-btn">Search</button>
                    <?php if(!empty($search_term)): ?>
                        <a href="student_dashboard_2.php" class="clear-search">✕ Clear</a>
                    <?php endif; ?>
                </form>
                
                <div class="course-accordion">
                    <?php if (mysqli_num_rows($available_classes) > 0): ?>
                        <?php 
                        $current_class = null;
                        $first_class = true;
                        $accordion_index = 0;
                        while ($avail = mysqli_fetch_assoc($available_classes)): 
                            if ($current_class != $avail['class_id']) {
                                if (!$first_class) echo '</tbody></table></div></div>';
                                $current_class = $avail['class_id'];
                                $first_class = false;
                                $accordion_index++;
                        ?>
                                <div class="acc-card" id="acc-<?php echo $accordion_index; ?>">
                                    <div class="acc-header" onclick="toggleAccordion(<?php echo $accordion_index; ?>)">
                                        <div class="acc-header-left">
                                            <div class="acc-course-name"><?php echo htmlspecialchars($avail['class_name']); ?></div>
                                            <div class="acc-course-meta">
                                                <span class="acc-code"><?php echo htmlspecialchars($avail['class_code']); ?></span>
                                                <?php if($avail['has_penyelaras'] > 0): ?>
                                                    <span class="acc-penyelaras">📌 Penyelaras assigned</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="acc-chevron">▼</span>
                                    </div>
                                    <div class="acc-body">
                                        <table class="group-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:25%;">Group / Lecturer</th>
                                                    <th style="width:15%;">Staff ID</th>
                                                    <th style="width:15%;">Role</th>
                                                    <th style="width:25%;">Capacity</th>
                                                    <th style="width:20%; text-align:right;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                        <?php } ?>
                                            <tr>
                                                <td>
                                                    <span class="td-group">📁 <?php echo htmlspecialchars($avail['group_name'] ?: 'Unnamed'); ?></span>
                                                    <br>
                                                    <span class="td-lecturer"><?php echo htmlspecialchars($avail['lecturer_name']); ?></span>
                                                </td>
                                                <td><span class="td-staff"><?php echo htmlspecialchars($avail['staff_id']); ?></span></td>
                                                <td><span class="td-role"><?php echo ucfirst($avail['role']); ?></span></td>
                                                <td>
                                                    <div class="capacity-bar-wrap">
                                                        <div class="capacity-bar">
                                                            <?php 
                                                            $percent = ($avail['enrolled_count'] / 50) * 100;
                                                            $class = 'capacity-bar-fill';
                                                            if ($percent >= 90) $class .= ' full';
                                                            elseif ($percent >= 70) $class .= ' warn';
                                                            ?>
                                                            <div class="<?php echo $class; ?>" style="width: <?php echo min($percent, 100); ?>%;"></div>
                                                        </div>
                                                        <span class="capacity-text"><?php echo $avail['enrolled_count']; ?>/50</span>
                                                    </div>
                                                </td>
                                                <td style="text-align:right;">
                                                    <?php if ($avail['enrolled_count'] >= 50): ?>
                                                        <span class="badge-full">Full</span>
                                                    <?php else: ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="enroll_class_id" value="<?php echo $avail['class_id']; ?>">
                                                            <input type="hidden" name="enroll_lecturer_id" value="<?php echo $avail['lecturer_id']; ?>">
                                                            <button type="submit" class="enroll-btn-small">+ Enroll</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                        <?php 
                        endwhile; 
                        if (!$first_class) echo '</tbody></table></div></div>';
                        ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_term); ?>">‹ Prev</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_term); ?>">Next ›</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <?php if(!empty($search_term)): ?>
                                <span class="material-symbols-outlined" style="font-size: 40px;">search_off</span><br>
                                No <?php echo htmlspecialchars($_SESSION['program']); ?> classes found matching "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                            <?php else: ?>
                                <span class="material-symbols-outlined" style="font-size: 40px;">celebration</span><br>
                                You're enrolled in all available <?php echo htmlspecialchars($_SESSION['program']); ?> classes! 🎉
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <h3 class="section-title">
            <span class="material-symbols-outlined">assignment</span>
            New Assignments
        </h3>
        
        <?php if (!empty($new_assignments_data)): ?>
            <?php foreach($new_assignments_data as $row): ?>
                <div class="assignment-card">
                    <div class="assignment-info">
                        <div class="card-title">📚 <?php echo htmlspecialchars($row['class_name']); ?></div>
                        <p><strong>Assignment:</strong> <?php echo htmlspecialchars($row['tittle']); ?></p>
                        <p><strong>Type:</strong> <?php echo ucfirst($row['type']); ?> | 
                           <strong>Lecturer:</strong> <?php echo htmlspecialchars($row['lecturer_name'] ?? 'Penyelaras'); ?>
                           <?php if($row['group_name']): ?>
                               | <strong>Group:</strong> <?php echo htmlspecialchars($row['group_name']); ?>
                           <?php endif; ?>
                        </p>
                        <p><strong>Due:</strong> <?php echo date("d/m/Y h:i A", strtotime($row['due_date'])); ?></p>
                    </div>
                    <a class="submit-btn" href="submit_assignment.php?assignment_id=<?php echo $row['assignment_id']; ?>">📤 Submit Now</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size: 40px;">task_alt</span><br>
                No new assignments at the moment. Great job!
            </div>
        <?php endif; ?>

    </div>

    <!-- Unenroll Confirmation Modal -->
    <div id="unenrollModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">⚠️</div>
            <div class="modal-title">Confirm Unenrollment</div>
            <div class="modal-body" id="modalBodyText">Are you sure you want to unenroll from this class?</div>
            <div class="modal-actions">
                <button class="modal-cancel" onclick="closeUnenrollModal()">Cancel</button>
                <button class="modal-confirm" id="confirmUnenrollBtn">Yes, Unenroll</button>
            </div>
        </div>
    </div>

    <script>
        // Accordion toggle for available classes
        function toggleAccordion(index) {
            const card = document.getElementById('acc-' + index);
            if (card) {
                card.classList.toggle('open');
            }
        }

        // Auto-open first accordion by default
        document.addEventListener('DOMContentLoaded', function() {
            const firstCard = document.querySelector('.acc-card');
            if (firstCard) {
                firstCard.classList.add('open');
            }
        });

        let pendingClassId = null;
        let pendingClassName = '';

        function openUnenrollModal(classId, className) {
            pendingClassId = classId;
            pendingClassName = className;
            document.getElementById('modalBodyText').innerHTML = `Are you sure you want to unenroll from <strong>${className}</strong>?<br><small>You can only unenroll if you have no submitted assignments.</small>`;
            document.getElementById('unenrollModal').classList.add('open');
        }

        function closeUnenrollModal() {
            document.getElementById('unenrollModal').classList.remove('open');
            pendingClassId = null;
        }

        document.getElementById('confirmUnenrollBtn').addEventListener('click', function() {
            if (pendingClassId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'unenroll_class_id';
                input.value = pendingClassId;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });

        document.getElementById('unenrollModal').addEventListener('click', function(e) {
            if (e.target === this) closeUnenrollModal();
        });
    </script>

</body>
</html>

<?php
if(isset($enrolled_stmt)) $enrolled_stmt->close();
if(isset($available_stmt)) $available_stmt->close();
?>