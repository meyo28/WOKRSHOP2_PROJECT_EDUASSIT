<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$lecturer_id = null;

// Get lecturer_id from staff_id
$query_lect = "SELECT lecturer_id FROM lecturer WHERE staff_id = ?";
$stmt_lect = $conn->prepare($query_lect);
$stmt_lect->bind_param("s", $staff_id);
$stmt_lect->execute();
$result_lect = $stmt_lect->get_result();
if ($row_lect = $result_lect->fetch_assoc()) {
    $lecturer_id = $row_lect['lecturer_id'];
}
$stmt_lect->close();

// ==========================================
// FETCH CLASSES WHERE LECTURER IS PENSYARAH (TEACHES A GROUP)
// ==========================================
$class_sql = "
    SELECT DISTINCT 
        c.class_id, 
        c.class_name, 
        c.class_code,
        cl.group_name,
        cl.role
    FROM class c
    JOIN course_lecturer cl ON c.class_id = cl.class_id
    WHERE cl.lecturer_id = ? AND cl.role = 'pensyarah'
    ORDER BY c.class_name ASC
";
$class_stmt = $conn->prepare($class_sql);
$class_stmt->bind_param("i", $lecturer_id);
$class_stmt->execute();
$pensyarah_classes = $class_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$class_stmt->close();

// ==========================================
// ALSO FETCH CLASSES WHERE LECTURER IS PENYELARAS (ALSO TEACHES THEIR GROUP)
// ==========================================
$penyelaras_sql = "
    SELECT DISTINCT 
        c.class_id, 
        c.class_name, 
        c.class_code,
        cl.group_name,
        cl.role
    FROM class c
    JOIN course_lecturer cl ON c.class_id = cl.class_id
    WHERE cl.lecturer_id = ? AND cl.role = 'penyelaras'
    ORDER BY c.class_name ASC
";
$penyelaras_stmt = $conn->prepare($penyelaras_sql);
$penyelaras_stmt->bind_param("i", $lecturer_id);
$penyelaras_stmt->execute();
$penyelaras_classes = $penyelaras_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$penyelaras_stmt->close();

// Merge both arrays (remove duplicates by class_id)
$all_classes = array_merge($pensyarah_classes, $penyelaras_classes);
$unique_classes = [];
$seen_ids = [];
foreach ($all_classes as $class) {
    if (!in_array($class['class_id'], $seen_ids)) {
        $unique_classes[] = $class;
        $seen_ids[] = $class['class_id'];
    }
}
$classes = $unique_classes;

// ==========================================
// HANDLE FORM SUBMISSION
// ==========================================
$message = '';
$error = '';
$show_success_modal = false;
$uploaded_files_list = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $class_id = (int)$_POST['class_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $type = $_POST['type'];
    
    // Verify lecturer can access this class (must be pensyarah or penyelaras)
    $check_sql = "SELECT role FROM course_lecturer WHERE class_id = ? AND lecturer_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $class_id, $lecturer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $error = "❌ You are not assigned to this class.";
    }
    $check_stmt->close();

    // Handle multiple file uploads
    $uploaded_files = [];
    $upload_errors = [];
    $uploaded_files_info = [];
    
    $upload_dir = 'uploads/references/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['reference_files']) && !empty($_FILES['reference_files']['name'][0])) {
        $total_files = count($_FILES['reference_files']['name']);
        $allowed_extensions = ['txt', 'pdf', 'zip', 'doc', 'docx', 'py', 'java', 'c', 'cpp', 'js', 'html', 'css', 'jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 10 * 1024 * 1024;
        
        for ($i = 0; $i < $total_files; $i++) {
            $original_name = $_FILES['reference_files']['name'][$i];
            $file_tmp = $_FILES['reference_files']['tmp_name'][$i];
            $file_error = $_FILES['reference_files']['error'][$i];
            $file_size = $_FILES['reference_files']['size'][$i];
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            
            if ($file_error !== UPLOAD_ERR_OK) {
                $upload_errors[] = "Error uploading '$original_name': " . getUploadErrorMessage($file_error);
                continue;
            }
            
            if (!in_array($file_ext, $allowed_extensions)) {
                $upload_errors[] = "File '$original_name' has invalid extension. Allowed: " . implode(', ', $allowed_extensions);
                continue;
            }
            
            if ($file_size > $max_file_size) {
                $upload_errors[] = "File '$original_name' exceeds 10MB limit.";
                continue;
            }
            
            $safe_original_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
            $unique_filename = uniqid() . '_' . $safe_original_name;
            $file_path = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $uploaded_files[] = [
                    'original_name' => $safe_original_name,
                    'server_path' => $file_path
                ];
                $uploaded_files_info[] = [
                    'original_name' => $safe_original_name,
                    'size' => round($file_size / 1024, 2) . ' KB',
                    'type' => $file_ext
                ];
            } else {
                $upload_errors[] = "Failed to upload '$original_name'. Please check directory permissions.";
            }
        }
    }
    
    if (empty($error)) {
        $file_data_json = !empty($uploaded_files) ? json_encode($uploaded_files) : null;
        
        // Insert assignment with lecturer_id (no group_id needed - only their own group)
        $insert_sql = "INSERT INTO assignment 
            (tittle, description, start_date, due_date, type, class_id, lecturer_id, reference_file)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "sssssiss",
            $title,
            $description,
            $start_date,
            $due_date,
            $type,
            $class_id,
            $lecturer_id,
            $file_data_json
        );

        if ($insert_stmt->execute()) {
            $show_success_modal = true;
            $message = "✅ Assignment created successfully!";
            if (!empty($uploaded_files_info)) {
                $message .= " (" . count($uploaded_files_info) . " file(s) uploaded)";
            }
        } else {
            $error = "Database error: " . $conn->error;
        }
        $insert_stmt->close();
    }
}

// Helper function for upload error messages
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE: return "File exceeds server upload limit";
        case UPLOAD_ERR_FORM_SIZE: return "File exceeds form upload limit";
        case UPLOAD_ERR_PARTIAL: return "File was only partially uploaded";
        case UPLOAD_ERR_NO_FILE: return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR: return "Missing temporary folder";
        case UPLOAD_ERR_CANT_WRITE: return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION: return "File upload stopped by extension";
        default: return "Unknown upload error";
    }
}

$currentDateTime = date('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Assignment - SILS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; min-height: 100vh; }
        .header { 
            background: linear-gradient(135deg, #003366 0%, #1a4d8c 50%, #2d6a9f 100%);
            color: white; 
            padding: 20px; 
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,51,102,0.3);
        }
        .container { max-width: 700px; margin: 40px auto; padding: 30px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); }
        .back-link { 
            display: inline-block; 
            margin-bottom: 20px; 
            color: #003366; 
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .back-link:hover { 
            transform: translateX(-4px);
            color: #1a4d8c;
        }
        .form-group { margin-bottom: 22px; }
        label { 
            font-weight: 600; 
            display: block; 
            margin-bottom: 8px; 
            color: #333; 
            font-size: 14px;
        }
        input, select, textarea { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e2e8f0; 
            border-radius: 10px; 
            font-family: inherit; 
            font-size: 14px;
            transition: all 0.3s;
            background: #fafbfc;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #003366;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(0,51,102,0.08);
        }
        textarea { height: 100px; resize: vertical; }

        .file-upload-area { 
            border: 2px dashed #d1d5db; 
            border-radius: 12px; 
            padding: 30px 20px; 
            text-align: center; 
            background: #fafafa; 
            transition: all 0.3s; 
            cursor: pointer;
        }
        .file-upload-area:hover { 
            border-color: #003366; 
            background: #f0f5ff; 
        }
        .file-upload-area.drag-over { 
            border-color: #28a745; 
            background: #e8f5e9; 
        }
        .file-upload-area .upload-icon {
            font-size: 48px;
            color: #9ca3af;
            margin-bottom: 10px;
            display: block;
        }
        .file-upload-area p {
            color: #6b7280;
            font-size: 14px;
            margin: 5px 0;
        }
        .file-upload-area .sub-text {
            font-size: 12px;
            color: #9ca3af;
        }
        .file-input { display: none; }
        .file-upload-label { 
            display: inline-block; 
            padding: 10px 25px; 
            background: #003366; 
            color: white; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: 500;
            transition: background 0.3s;
            margin-top: 10px;
        }
        .file-upload-label:hover { background: #1a4d8c; }
        .file-list { margin-top: 15px; max-height: 200px; overflow-y: auto; }
        .file-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 10px 14px; 
            background: #f8f9fc; 
            margin-bottom: 8px; 
            border-radius: 8px; 
            border-left: 3px solid #28a745;
            transition: all 0.2s;
        }
        .file-item:hover { background: #f0f2f5; }
        .file-item .file-name { font-size: 13px; color: #333; word-break: break-all; flex: 1; }
        .file-item .file-size { font-size: 11px; color: #6b7280; margin-left: 10px; }
        .remove-file { 
            background: none; 
            border: none; 
            color: #dc2626; 
            cursor: pointer; 
            font-size: 20px; 
            margin-left: 10px; 
            padding: 0 5px;
            transition: all 0.2s;
            line-height: 1;
        }
        .remove-file:hover { 
            color: #b91c1c; 
            transform: scale(1.2);
        }
        .file-info-text { font-size: 12px; color: #6b7280; margin-top: 8px; }
        
        .btn-submit { 
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white; 
            border: none; 
            padding: 14px 24px; 
            border-radius: 10px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            width: 100%; 
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        }
        .btn-submit:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.4);
        }
        
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #86efac; }
        
        .info-note { 
            background: #eff6ff; 
            color: #1e40af; 
            padding: 14px; 
            border-radius: 10px; 
            font-size: 13px; 
            margin-top: 8px; 
            display: flex; 
            align-items: flex-start; 
            gap: 10px;
            border-left: 4px solid #3b82f6;
        }
        .info-note .icon { font-size: 20px; flex-shrink: 0; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; animation: fadeIn 0.3s ease; }
        .modal.show { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes bounceIn { 0% { transform: scale(0.3); opacity: 0; } 50% { transform: scale(1.05); } 70% { transform: scale(0.95); } 100% { transform: scale(1); opacity: 1; } }
        @keyframes checkmark { 0% { stroke-dashoffset: 100; } 100% { stroke-dashoffset: 0; } }
        .modal-content { background: white; border-radius: 24px; padding: 40px; width: 450px; max-width: 90%; text-align: center; animation: bounceIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); }
        .checkmark-circle { width: 100px; height: 100px; margin: 0 auto 20px; border-radius: 50%; background: linear-gradient(135deg, #28a745, #20c997); display: flex; align-items: center; justify-content: center; }
        .checkmark-svg { width: 60px; height: 60px; }
        .checkmark-svg path { stroke: white; stroke-width: 3; fill: none; stroke-dasharray: 100; stroke-dashoffset: 100; animation: checkmark 0.6s ease-in-out 0.3s forwards; }
        .modal-content h2 { color: #28a745; font-size: 24px; margin-bottom: 10px; }
        .modal-content p { color: #6b7280; font-size: 14px; margin-bottom: 15px; }
        .uploaded-files-list { background: #f8f9fc; border-radius: 10px; padding: 12px; margin: 15px 0; text-align: left; max-height: 150px; overflow-y: auto; }
        .uploaded-files-list h4 { font-size: 12px; color: #374151; margin-bottom: 8px; }
        .uploaded-file-item { font-size: 12px; color: #4b5563; padding: 4px 0; border-bottom: 1px solid #e5e7eb; }
        .uploaded-file-item:last-child { border-bottom: none; }
        .modal-buttons { display: flex; gap: 12px; justify-content: center; margin-top: 15px; flex-wrap: wrap; }
        .btn-go-dashboard { background: #003366; color: white; border: none; padding: 10px 25px; border-radius: 30px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-go-dashboard:hover { background: #1a4d8c; transform: translateY(-2px); }
        .btn-stay { background: #e5e7eb; color: #374151; border: none; padding: 10px 25px; border-radius: 30px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-stay:hover { background: #d1d5db; transform: translateY(-2px); }
        .countdown { margin-top: 15px; font-size: 12px; color: #9ca3af; }
        
        .role-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-badge.penyelaras { background: #003366; color: white; }
        .role-badge.pensyarah { background: #dbeafe; color: #1e40af; }
        
        .required-star { color: #dc2626; margin-left: 2px; }
        
        @media (max-width: 640px) {
            .container { margin: 20px; padding: 20px; }
            .modal-content { padding: 25px; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>📝 Create New Assignment</h1>
</div>

<div class="container">

    <a href="class_dashboard.php?class_id=<?php echo isset($_GET['class_id']) ? (int)$_GET['class_id'] : ''; ?>" class="back-link">← Back to Class</a>

    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (empty($classes)): ?>
        <div class="alert alert-error">
            You are not assigned to any classes as a Pensyarah or Penyelaras with a group to teach.
            <br><small>Only lecturers who teach a group (Pensyarah) or Penyelaras can create assignments.</small>
        </div>
    <?php else: ?>

    <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <span style="font-size: 12px; color: #6b7280;">
            <?php echo count($classes); ?> class(es) available to teach
        </span>
    </div>

    <form method="POST" enctype="multipart/form-data" id="assignmentForm">
        <div class="form-group">
            <label>Select Class <span class="required-star">*</span></label>
            <select name="class_id" id="classSelect" required>
                <option value="">-- Choose a class --</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['class_id']; ?>" <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['class_name'] . ' (' . $class['class_code'] . ')'); ?>
                        <?php if($class['group_name']): ?>
                            - <?php echo htmlspecialchars($class['group_name']); ?>
                        <?php endif; ?>
                        <?php if($class['role'] == 'penyelaras'): ?>
                            [Penyelaras]
                        <?php else: ?>
                            [Pensyarah]
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 4px;">
                You can only create assignments for classes where you are a Pensyarah or Penyelaras (teaching your own group).
            </small>
        </div>

        <div class="form-group">
            <label>Assignment Title <span class="required-star">*</span></label>
            <input type="text" name="title" required placeholder="e.g., Code Assignment / Essay Assignment">
        </div>

        <div class="form-group">
            <label>Description (optional)</label>
            <textarea name="description" placeholder="Describe the task, requirements, etc."></textarea>
        </div>

        <!-- Reference Files Upload Section -->
        <div class="form-group">
            <label>📎 Upload Reference Files / Assignment Materials</label>
            <div class="file-upload-area" id="fileUploadArea">
                <span class="upload-icon">📎</span>
                <p>Drag & drop files here or click to browse</p>
                <p class="sub-text">Supported: PDF, ZIP, TXT, DOC, DOCX, Images, PY, JAVA, C, CPP, JS, HTML, CSS (Max 10MB each)</p>
                <input type="file" name="reference_files[]" id="fileInput" class="file-input" multiple accept=".txt,.pdf,.zip,.doc,.docx,.py,.java,.c,.cpp,.js,.html,.css,.jpg,.jpeg,.png,.gif">
                <label for="fileInput" class="file-upload-label">Choose Files</label>
            </div>
            <div id="fileList" class="file-list"></div>
            <div class="file-info-text">
                ℹ️ Files will be available for students to download with their original names.
            </div>
        </div>

        <div class="form-group">
            <label>Start Date & Time <span class="required-star">*</span></label>
            <input type="datetime-local" id="start_date" name="start_date" min="<?php echo $currentDateTime; ?>" required>
        </div>

        <div class="form-group">
            <label>Due Date & Time <span class="required-star">*</span></label>
            <input type="datetime-local" id="due_date" name="due_date" required>
        </div>

        <div class="form-group">
            <label>Assignment Type <span class="required-star">*</span></label>
            <select name="type" required>
                <option value="essay">📄 Essay</option>
                <option value="code">💻 Code</option>
            </select>
        </div>

        <div class="info-note" style="margin-bottom: 20px;">
            <span class="icon">ℹ️</span>
            <div>
                <strong>Visibility:</strong> 
                This assignment will be visible ONLY to students in your group.
            </div>
        </div>

        <button type="submit" class="btn-submit">➕ Create Assignment</button>
    </form>

    <?php endif; ?>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal">
    <div class="modal-content">
        <div class="checkmark-circle">
            <svg class="checkmark-svg" viewBox="0 0 52 52">
                <path d="M14 27 L22 35 L38 18" />
            </svg>
        </div>
        <h2>✓ Assignment Created!</h2>
        <p>Your assignment has been successfully created and is now available for students.</p>
        <?php if (!empty($uploaded_files_info)): ?>
        <div class="uploaded-files-list">
            <h4>📁 Uploaded Reference Files (<?php echo count($uploaded_files_info); ?>):</h4>
            <?php foreach ($uploaded_files_info as $file): ?>
                <div class="uploaded-file-item">
                    📄 <?php echo htmlspecialchars($file['original_name']); ?> 
                    (<?php echo $file['size']; ?>)
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="modal-buttons">
            <button class="btn-stay" onclick="closeModalAndStay()">📝 Create Another</button>
            <button class="btn-go-dashboard" onclick="goToDashboard()">🏠 Go to Dashboard</button>
        </div>
        <div class="countdown">
            Redirecting to dashboard in <span id="countdownTimer">5</span> seconds...
        </div>
    </div>
</div>

<script>
// ==========================================
// FILE UPLOAD HANDLER
// ==========================================
const fileInput = document.getElementById('fileInput');
const fileList = document.getElementById('fileList');
const fileUploadArea = document.getElementById('fileUploadArea');
let selectedFiles = [];

fileInput.addEventListener('change', function(e) {
    updateFileList(Array.from(e.target.files));
});

fileUploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    fileUploadArea.classList.add('drag-over');
});

fileUploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    fileUploadArea.classList.remove('drag-over');
});

fileUploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    fileUploadArea.classList.remove('drag-over');
    const files = Array.from(e.dataTransfer.files);
    updateFileList(files);
    fileInput.files = e.dataTransfer.files;
});

function updateFileList(files) {
    selectedFiles = files;
    if (files.length === 0) {
        fileList.innerHTML = '';
        return;
    }
    
    fileList.innerHTML = '';
    files.forEach((file, index) => {
        const actualSize = file.size > 1048576 ? (file.size / 1048576).toFixed(2) + ' MB' : (file.size / 1024).toFixed(2) + ' KB';
        
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <span class="file-name">📄 ${escapeHtml(file.name)}</span>
            <span class="file-size">${actualSize}</span>
            <button type="button" class="remove-file" data-index="${index}">&times;</button>
        `;
        fileList.appendChild(fileItem);
    });
    
    document.querySelectorAll('.remove-file').forEach(btn => {
        btn.addEventListener('click', function() {
            const index = parseInt(this.dataset.index);
            removeFile(index);
        });
    });
}

function removeFile(index) {
    const newFiles = Array.from(fileInput.files);
    newFiles.splice(index, 1);
    const dataTransfer = new DataTransfer();
    newFiles.forEach(file => dataTransfer.items.add(file));
    fileInput.files = dataTransfer.files;
    updateFileList(Array.from(fileInput.files));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ==========================================
// DATE INPUT HANDLERS
// ==========================================
const startDateInput = document.getElementById("start_date");
const dueDateInput = document.getElementById("due_date");
let redirectTimer = null;
let countdownInterval = null;

function getCurrentDateTimeLocal() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth()+1).padStart(2,'0');
    const day = String(now.getDate()).padStart(2,'0');
    const hours = String(now.getHours()).padStart(2,'0');
    const minutes = String(now.getMinutes()).padStart(2,'0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function updateStartMin() {
    const now = getCurrentDateTimeLocal();
    startDateInput.min = now;
    if(startDateInput.value && startDateInput.value < now) startDateInput.value = "";
}

function updateDueMin() {
    if(startDateInput.value) {
        dueDateInput.min = startDateInput.value;
        if(dueDateInput.value && dueDateInput.value <= startDateInput.value) dueDateInput.value = "";
    }
}

updateStartMin();
updateDueMin();
setInterval(updateStartMin, 60000);

startDateInput.addEventListener("change", updateDueMin);

document.querySelector("form").addEventListener("submit", function(e){
    const startDate = new Date(startDateInput.value);
    const dueDate = new Date(dueDateInput.value);
    const now = new Date();
    if(startDate < now) {
        alert("Start date and time cannot be in the past.");
        e.preventDefault();
        return;
    }
    if(dueDate <= startDate) {
        alert("Due date and time must be after start date and time.");
        e.preventDefault();
        return;
    }
});

// ==========================================
// SUCCESS MODAL FUNCTIONS
// ==========================================

<?php if ($show_success_modal): ?>
window.addEventListener('load', function() {
    showSuccessModal();
});

function showSuccessModal() {
    const modal = document.getElementById('successModal');
    modal.classList.add('show');
    startRedirectTimer();
}

function startRedirectTimer() {
    let seconds = 5;
    const timerElement = document.getElementById('countdownTimer');
    
    countdownInterval = setInterval(function() {
        seconds--;
        if (timerElement) {
            timerElement.textContent = seconds;
        }
        if (seconds <= 0) {
            clearInterval(countdownInterval);
            clearTimeout(redirectTimer);
            goToDashboard();
        }
    }, 1000);
    
    redirectTimer = setTimeout(function() {
        goToDashboard();
    }, 5500);
}

function closeModalAndStay() {
    if (countdownInterval) clearInterval(countdownInterval);
    if (redirectTimer) clearTimeout(redirectTimer);
    
    const modal = document.getElementById('successModal');
    modal.classList.remove('show');
    
    document.getElementById('assignmentForm').reset();
    fileList.innerHTML = '';
    selectedFiles = [];
    
    startDateInput.value = '';
    dueDateInput.value = '';
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToDashboard() {
    if (countdownInterval) clearInterval(countdownInterval);
    if (redirectTimer) clearTimeout(redirectTimer);
    window.location.href = 'lecturer_dashboard.php';
}
<?php endif; ?>
</script>

</body>
</html>