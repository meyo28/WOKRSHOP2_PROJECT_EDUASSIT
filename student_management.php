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
$preview_data = [];
$show_preview = false;
$imported_count = 0;
$skipped_count = 0;
$import_errors = [];

// ==========================================
// 1. HANDLE ADD NEW STUDENT
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
    $matric_no = trim($_POST['matric_no']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $program = trim($_POST['program']);
    $phone_no = trim($_POST['phone_no']);
    $password = !empty($_POST['password']) ? md5($_POST['password']) : md5('utem123'); 

    // Validate required fields
    if (empty($matric_no) || empty($full_name) || empty($email) || empty($program)) {
        $error_msg = "❌ All fields except password are required!";
    } else {
        $check_stmt = $conn->prepare("SELECT matric_no FROM student WHERE matric_no = ? OR email = ?");
        $check_stmt->bind_param("ss", $matric_no, $email);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error_msg = "A student with this Matric No or Email already exists!";
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO student (matric_no, password, full_name, email, program, phone_no) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssss", $matric_no, $password, $full_name, $email, $program, $phone_no);
            if ($insert_stmt->execute()) {
                $success_msg = "Student registered successfully!";
            } else {
                $error_msg = "Error adding student: " . $conn->error;
            }
        }
    }
}

// ==========================================
// 2. HANDLE UPDATE STUDENT
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_student'])) {
    $student_id = $_POST['student_id'];
    $matric_no = trim($_POST['matric_no']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $program = trim($_POST['program']);
    $phone_no = trim($_POST['phone_no']);
    
    // Validate required fields
    if (empty($matric_no) || empty($full_name) || empty($email) || empty($program)) {
        $error_msg = "❌ All fields except password are required!";
    } else {
        if (!empty($_POST['password'])) {
            $password = md5($_POST['password']);
            $update_stmt = $conn->prepare("UPDATE student SET matric_no=?, full_name=?, email=?, program=?, phone_no=?, password=? WHERE student_id=?");
            $update_stmt->bind_param("ssssssi", $matric_no, $full_name, $email, $program, $phone_no, $password, $student_id);
        } else {
            $update_stmt = $conn->prepare("UPDATE student SET matric_no=?, full_name=?, email=?, program=?, phone_no=? WHERE student_id=?");
            $update_stmt->bind_param("sssssi", $matric_no, $full_name, $email, $program, $phone_no, $student_id);
        }

        if ($update_stmt->execute()) {
            $success_msg = "Student details updated successfully!";
        } else {
            $error_msg = "Error updating student: " . $conn->error;
        }
    }
}

// ==========================================
// 3. HANDLE DELETE STUDENT
// ==========================================
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $del_stmt = $conn->prepare("DELETE FROM student WHERE student_id = ?");
    $del_stmt->bind_param("i", $delete_id);
    if ($del_stmt->execute()) {
        header("Location: student_management.php?msg=deleted");
        exit();
    } else {
        $error_msg = "Cannot delete student. They may still have active enrollments or assignment submissions.";
    }
}
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') $success_msg = "Student deleted successfully!";

// ==========================================
// 4. HANDLE CSV IMPORT - IMPROVED
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_students']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $file_tmp = $_FILES['csv_file']['tmp_name'];
    $file_name = $_FILES['csv_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, ['csv'])) {
        $error_msg = "Please upload a CSV file (.csv) only.";
    } else {
        $handle = fopen($file_tmp, "r");
        $header = fgetcsv($handle); // Get header row
        
        // Validate header
        $expected_headers = ['matric_no', 'full_name', 'email', 'program', 'phone_no', 'password'];
        $header_valid = true;
        foreach ($expected_headers as $h) {
            if (!in_array($h, $header)) {
                $header_valid = false;
                break;
            }
        }
        
        if (!$header_valid) {
            $error_msg = "❌ Invalid CSV format. Required columns: matric_no, full_name, email, program, phone_no, password";
            fclose($handle);
        } else {
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $row_num = 1;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_num++;
                
                // Map data to columns
                $row_data = array_combine($header, $data);
                
                $matric_no = trim($row_data['matric_no'] ?? '');
                $full_name = trim($row_data['full_name'] ?? '');
                $email = trim($row_data['email'] ?? '');
                $program = trim($row_data['program'] ?? '');
                $phone_no = trim($row_data['phone_no'] ?? '');
                $password = !empty($row_data['password']) ? md5(trim($row_data['password'])) : md5('utem123');
                
                // Validate required fields (ALL required except password)
                if (empty($matric_no) || empty($full_name) || empty($email) || empty($program)) {
                    $skipped++;
                    $errors[] = "Row $row_num: Missing required fields (matric_no, full_name, email, program are required)";
                    continue;
                }
                
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    $errors[] = "Row $row_num: Invalid email format for $matric_no";
                    continue;
                }
                
                $check_stmt = $conn->prepare("SELECT student_id FROM student WHERE matric_no = ? OR email = ?");
                $check_stmt->bind_param("ss", $matric_no, $email);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows > 0) {
                    $skipped++;
                    $errors[] = "Row $row_num: $matric_no already exists";
                    continue;
                }
                
                $insert_stmt = $conn->prepare("INSERT INTO student (matric_no, password, full_name, email, program, phone_no) VALUES (?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("ssssss", $matric_no, $password, $full_name, $email, $program, $phone_no);
                
                if ($insert_stmt->execute()) {
                    $imported++;
                } else {
                    $skipped++;
                    $errors[] = "Row $row_num: Error importing $matric_no - " . $conn->error;
                }
            }
            fclose($handle);
            
            $imported_count = $imported;
            $skipped_count = $skipped;
            $import_errors = $errors;
            
            if ($imported > 0) {
                $success_msg = "✅ Import completed! $imported students imported, $skipped skipped.";
            } else {
                $error_msg = "❌ Import failed. No students were imported. " . ($skipped > 0 ? "$skipped rows had errors." : "");
            }
            
            if (!empty($errors) && count($errors) <= 10) {
                if ($imported > 0) {
                    $success_msg .= "<br><small>Errors: " . implode("<br>", $errors) . "</small>";
                } else {
                    $error_msg .= "<br><small>Errors: " . implode("<br>", $errors) . "</small>";
                }
            } elseif (!empty($errors)) {
                if ($imported > 0) {
                    $success_msg .= "<br><small>Errors: " . count($errors) . " rows had issues. Check your data.</small>";
                } else {
                    $error_msg .= "<br><small>Errors: " . count($errors) . " rows had issues. Check your data.</small>";
                }
            }
        }
    }
}

// ==========================================
// 5. FETCH DATA FOR EDIT MODE
// ==========================================
$edit_mode = false;
$edit_data = ['student_id'=>'', 'matric_no'=>'', 'full_name'=>'', 'email'=>'', 'program'=>'', 'phone_no'=>'', 'password'=>''];
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $edit_stmt = $conn->prepare("SELECT * FROM student WHERE student_id = ?");
    $edit_stmt->bind_param("i", $_GET['edit_id']);
    $edit_stmt->execute();
    $result = $edit_stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $edit_data = $row;
    }
}

// ==========================================
// 6. SEARCH & FETCH ALL STUDENTS WITH PAGINATION
// ==========================================
$search_term = "";
$program_filter = "";
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Get unique programs for dropdown
$program_query = "SELECT DISTINCT program FROM student WHERE program != '' AND program IS NOT NULL ORDER BY program";
$program_result = mysqli_query($conn, $program_query);
$programs = [];
while ($p = mysqli_fetch_assoc($program_result)) {
    $programs[] = $p['program'];
}

// Build WHERE clause
$where_conditions = [];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = trim($_GET['search']);
    $safe_search = mysqli_real_escape_string($conn, $search_term);
    $where_conditions[] = "(s.full_name LIKE '%$safe_search%' OR s.matric_no LIKE '%$safe_search%')";
}

if (isset($_GET['program_filter']) && !empty($_GET['program_filter'])) {
    $program_filter = mysqli_real_escape_string($conn, $_GET['program_filter']);
    $where_conditions[] = "s.program = '$program_filter'";
}

$search_query = "";
if (!empty($where_conditions)) {
    $search_query = " WHERE " . implode(" AND ", $where_conditions);
}

// Count total students
$count_query = "SELECT COUNT(DISTINCT s.student_id) as total FROM student s $search_query";
$count_result = mysqli_query($conn, $count_query);
$total_students = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_students / $limit);

// Fetch students with pagination
$query = "
    SELECT s.student_id, s.matric_no, s.full_name, s.email, s.program, s.phone_no, 
           COUNT(e.class_id) as enrolled_count,
           GROUP_CONCAT(c.class_code SEPARATOR ', ') as class_list
    FROM student s 
    LEFT JOIN enrollment e ON s.student_id = e.student_id 
    LEFT JOIN class c ON e.class_id = c.class_id
    $search_query
    GROUP BY s.student_id 
    ORDER BY s.matric_no ASC
    LIMIT $limit OFFSET $offset
";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - SILS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ... (keep existing styles) ... */
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
        .card h2 { font-size: 18px; color: #003366; margin-bottom: 20px; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 500; }
        .input-group label .required { color: #dc3545; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 14px; }
        .input-group input:required { border-color: #ffc107; }
        .submit-btn { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 10px; }
        .submit-btn:hover { background: #218838; }
        .update-btn { background: #007bff; }
        .update-btn:hover { background: #0056b3; }
        .cancel-btn { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; text-align: center; display: inline-block; margin-top: 10px; }

        /* Import Section */
        .import-section {
            background: #f8f9fc;
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        .import-section:hover {
            border-color: #003366;
            background: #f0f5ff;
        }
        .import-section .icon { font-size: 48px; color: #003366; }
        .import-section p { color: #666; font-size: 13px; margin: 10px 0; }
        
        .import-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .import-btn { 
            background: #003366; 
            color: white; 
            border: none; 
            padding: 10px 25px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            white-space: nowrap;
        }
        .import-btn:hover { background: #1a4d8c; }
        
        .file-input-wrapper {
            display: inline-block;
            position: relative;
            overflow: hidden;
        }
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-input-label {
            background: #e0e0e0;
            color: #333;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
        }
        .file-input-label:hover { background: #ccc; }

        .file-list {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: #f8f9fc;
            margin-bottom: 8px;
            border-radius: 6px;
            border-left: 3px solid #28a745;
        }
        .file-item .file-name {
            font-size: 13px;
            color: #333;
            word-break: break-all;
            flex: 1;
        }
        .file-item .file-size {
            font-size: 11px;
            color: #666;
            margin-left: 10px;
        }
        .remove-file {
            background: none;
            border: none;
            color: #c00;
            cursor: pointer;
            font-size: 18px;
            margin-left: 10px;
            padding: 0 5px;
        }
        .remove-file:hover {
            color: #900;
        }
        .file-info-text {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        .search-filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .search-filter-container .search-input {
            flex: 1;
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
        .badge { background: #e8f5e9; color: #2e7d32; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-bottom: 5px; display: inline-block; }
        .badge-program { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .class-list { font-size: 12px; color: #555; }
        
        .action-btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; margin-right: 5px; display: inline-block; }
        .btn-edit { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .btn-delete:hover { background: #ffcdd2; }
        
        .sample-link {
            color: #003366;
            cursor: pointer;
            text-decoration: underline;
            font-size: 12px;
        }
        .sample-link:hover { color: #1a4d8c; }
        
        .badge-success { background: #28a745; color: white; padding: 2px 10px; border-radius: 12px; font-size: 11px; }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #888;
            background: #f9f9f9;
            border-radius: 10px;
            border: 1.5px dashed #d0d4dc;
        }
        
        .import-summary {
            background: #f8f9fc;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
            font-size: 13px;
        }
        .import-summary .success { color: #28a745; }
        .import-summary .error { color: #dc3545; }
        
        @media (max-width: 768px) {
            .search-filter-container {
                flex-direction: column;
            }
            .search-filter-container .search-input {
                min-width: unset;
            }
            .import-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="admin_dashboard.php" class="back-btn">← Dashboard</a>
        <h1>👨‍🎓 Student Management</h1>
    </div>

    <div class="container">
        <?php if($error_msg): ?>
            <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>
        <?php if($success_msg): ?>
            <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>
                <?php echo $edit_mode ? '✏️ Edit Student Details' : '➕ Register New Student'; ?>
            </h2>
            <form action="student_management.php" method="POST">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="student_id" value="<?php echo $edit_data['student_id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="input-group">
                        <label>Matric No <span class="required">*</span></label>
                        <input type="text" name="matric_no" value="<?php echo htmlspecialchars($edit_data['matric_no']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_data['full_name']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($edit_data['email']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Program <span class="required">*</span></label>
                        <input type="text" name="program" value="<?php echo htmlspecialchars($edit_data['program']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_no" value="<?php echo htmlspecialchars($edit_data['phone_no']); ?>" placeholder="Optional">
                    </div>
                    <div class="input-group">
                        <label><?php echo $edit_mode ? 'New Password (leave blank to keep current)' : 'Temporary Password (optional)'; ?></label>
                        <input type="text" name="password" <?php echo $edit_mode ? '' : 'placeholder="Default: utem123"'; ?>>
                        <?php if(!$edit_mode): ?>
                            <small style="color:#666;">Default: utem123 if left empty</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if($edit_mode): ?>
                    <button type="submit" name="update_student" class="submit-btn update-btn">Update Student</button>
                    <a href="student_management.php" class="cancel-btn">Cancel Edit</a>
                <?php else: ?>
                    <button type="submit" name="add_student" class="submit-btn">Register Student</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Import Section with File List BELOW the card -->
        <div class="card">
            <h2>📤 Bulk Import Students</h2>
            <div class="import-section">
                <div class="icon">📊</div>
                <p><strong>Upload CSV file</strong> to import multiple students at once</p>
                <p style="font-size: 12px; color: #888;">
                    <strong>Required columns:</strong> matric_no, full_name, email, program <span style="color: #dc3545;">*</span><br>
                    Optional: phone_no, password (default: utem123 if empty)<br>
                    <span style="color: #dc3545;">Note: All fields except password are required!</span>
                </p>
                <p style="font-size: 12px; color: #888;">
                    <a href="#" onclick="downloadSampleCSV()" class="sample-link">📥 Download sample CSV template</a>
                </p>
                
                <form method="POST" enctype="multipart/form-data" style="margin-top: 15px;">
                    <div class="import-actions">
                        <div class="file-input-wrapper">
                            <span class="file-input-label" id="fileLabel">📂 Choose CSV File</span>
                            <input type="file" name="csv_file" accept=".csv" onchange="updateFileList(this)" required>
                        </div>
                        <button type="submit" name="import_students" class="import-btn">
                            ⬆️ Upload & Import
                        </button>
                    </div>
                </form>
            </div>

            <!-- FILE LIST - BELOW THE CARD, OUTSIDE import-section -->
            <div id="fileListContainer" style="margin-top: 15px;">
                <div id="fileList" class="file-list"></div>
                <div class="file-info-text" id="fileInfoText">
                    ℹ️ Select a CSV file to import. File will appear here once selected.
                </div>
            </div>
            
            <?php if(isset($imported_count) && $imported_count > 0): ?>
            <div class="import-summary">
                <span class="success">✅ <?php echo $imported_count; ?> students imported successfully</span>
                <?php if($skipped_count > 0): ?>
                    <span class="error"> | ⚠️ <?php echo $skipped_count; ?> rows skipped</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>📋 Student Directory</h2>
            
            <!-- Updated Search & Filter -->
            <form action="student_management.php" method="GET" class="search-filter-container">
                <input type="text" name="search" class="search-input" placeholder="Search by name or matric no..." value="<?php echo htmlspecialchars($search_term); ?>">
                
                <select name="program_filter">
                    <option value="">All Programs</option>
                    <?php foreach($programs as $program): ?>
                        <option value="<?php echo htmlspecialchars($program); ?>" <?php echo $program_filter == $program ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($program); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="search-btn">🔍 Search</button>
                
                <?php if(!empty($search_term) || !empty($program_filter)): ?>
                    <a href="student_management.php" class="clear-btn">✕ Clear</a>
                <?php endif; ?>
            </form>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Matric No</th>
                            <th>Full Name</th>
                            <th>Contact & Program</th>
                            <th>Enrollment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['matric_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td>
                                        <span class="badge badge-program"><?php echo htmlspecialchars($row['program']); ?></span>
                                        <div style="margin-top: 5px;">✉️ <?php echo htmlspecialchars($row['email']); ?></div>
                                        <?php if($row['phone_no']): ?>
                                            <div style="font-size:12px; color:#666;">📞 <?php echo htmlspecialchars($row['phone_no']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge"><?php echo $row['enrolled_count']; ?> Enrolled</span>
                                        <?php if($row['class_list']): ?>
                                            <div class="class-list">Classes: <strong><?php echo htmlspecialchars($row['class_list']); ?></strong></div>
                                        <?php else: ?>
                                            <div class="class-list" style="color: #999;">Not enrolled in any classes</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="student_management.php?edit_id=<?php echo $row['student_id']; ?>&search=<?php echo urlencode($search_term); ?>&program_filter=<?php echo urlencode($program_filter); ?>&page=<?php echo $page; ?>" class="action-btn btn-edit">Edit</a>
                                        <a href="student_management.php?delete_id=<?php echo $row['student_id']; ?>&search=<?php echo urlencode($search_term); ?>&program_filter=<?php echo urlencode($program_filter); ?>&page=<?php echo $page; ?>" 
                                           class="action-btn btn-delete"
                                           onclick="return confirm('WARNING: Deleting this student will also break their enrollment and assignment records. Are you sure?');">
                                           Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <?php if(!empty($search_term) || !empty($program_filter)): ?>
                                            <span class="material-symbols-outlined" style="font-size: 40px;">search_off</span><br>
                                            No students found matching your filters.
                                        <?php else: ?>
                                            <span class="material-symbols-outlined" style="font-size: 40px;">school</span><br>
                                            No students found in the system.
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
                Showing <?php echo mysqli_num_rows($result); ?> of <?php echo $total_students; ?> students
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_term); ?>&program_filter=<?php echo urlencode($program_filter); ?>">‹ Prev</a>
                <?php else: ?>
                    <span class="disabled">‹ Prev</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&program_filter=<?php echo urlencode($program_filter); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_term); ?>&program_filter=<?php echo urlencode($program_filter); ?>">Next ›</a>
                <?php else: ?>
                    <span class="disabled">Next ›</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // FILE LIST FUNCTIONALITY
        const fileListContainer = document.getElementById('fileList');
        const fileInfoText = document.getElementById('fileInfoText');
        let selectedFile = null;

        function updateFileList(input) {
            const file = input.files[0];
            if (!file) {
                fileListContainer.innerHTML = '';
                fileInfoText.textContent = 'ℹ️ Select a CSV file to import. File will appear here once selected.';
                document.getElementById('fileLabel').textContent = '📂 Choose CSV File';
                return;
            }

            selectedFile = file;
            const actualSize = file.size > 1048576 ? (file.size / 1048576).toFixed(2) + ' MB' : (file.size / 1024).toFixed(2) + ' KB';
            
            fileListContainer.innerHTML = '';
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <span class="file-name">📄 ${escapeHtml(file.name)}</span>
                <span class="file-size">${actualSize}</span>
                <button type="button" class="remove-file" onclick="removeFile()">&times;</button>
            `;
            fileListContainer.appendChild(fileItem);
            
            fileInfoText.textContent = '✅ File ready for import. Click "Upload & Import" to proceed.';
            document.getElementById('fileLabel').textContent = '📂 ' + file.name;
        }

        function removeFile() {
            const input = document.querySelector('input[name="csv_file"]');
            input.value = '';
            fileListContainer.innerHTML = '';
            fileInfoText.textContent = 'ℹ️ Select a CSV file to import. File will appear here once selected.';
            document.getElementById('fileLabel').textContent = '📂 Choose CSV File';
            selectedFile = null;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function downloadSampleCSV() {
            const csvContent = 
                'matric_no,full_name,email,program,phone_no,password\n' +
                'B032410001,John Doe,john.doe@student.utem.edu.my,BIT,012-3456789,utem123\n' +
                'B032410002,Jane Smith,jane.smith@student.utem.edu.my,BITD,012-4567890,utem123\n' +
                'D032410003,Ahmad Bin Ali,ahmad.ali@student.utem.edu.my,Diploma IT,012-5678901,utem123\n' +
                'B032410004,Muhammad Fikri,muhammad.fikri@student.utem.edu.my,BITS,012-6789012,';
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'sample_students.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }
    </script>

</body>
</html>