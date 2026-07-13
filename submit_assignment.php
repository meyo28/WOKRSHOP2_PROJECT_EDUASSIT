<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php?error=login_required");
    exit();
}

$student_id = $_SESSION['user_id'];
$message = '';
$error = '';
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$show_success = false;

// Fetch pending assignment for this student if no specific assignment_id is provided
if(!$assignment_id){
    $sql_pending = "SELECT a.assignment_id
                    FROM assignment a
                    JOIN enrollment e ON e.class_id = a.class_id
                    LEFT JOIN code_submission cs ON cs.assignment_id = a.assignment_id AND cs.student_id = ?
                    LEFT JOIN essay_submission es ON es.assignment_id = a.assignment_id AND es.student_id = ?
                    WHERE e.student_id = ? AND cs.assignment_id IS NULL AND es.assignment_id IS NULL
                    ORDER BY a.start_date ASC
                    LIMIT 1";
    $stmt_pending = $conn->prepare($sql_pending);
    $stmt_pending->bind_param("iii", $student_id, $student_id, $student_id);
    $stmt_pending->execute();
    $result_pending = $stmt_pending->get_result();
    $pending_assignment = $result_pending->fetch_assoc();
    $stmt_pending->close();

    if(!$pending_assignment){
        header("Location: nopending_assignment.php");
        exit();
    } else {
        $assignment_id = $pending_assignment['assignment_id'];
    }
}

// Fetch the assignment details + lecturer files + previous submission
$sql = "SELECT a.assignment_id, a.tittle, a.description, a.start_date, a.due_date, a.type, a.reference_file, c.class_name,
        cs.code, cs.file_name AS submission_file_name,
        es.essay, es.file_name AS essay_file_name
        FROM assignment a
        JOIN class c ON a.class_id = c.class_id
        LEFT JOIN code_submission cs ON cs.assignment_id = a.assignment_id AND cs.student_id = ?
        LEFT JOIN essay_submission es ON es.assignment_id = a.assignment_id AND es.student_id = ?
        WHERE a.assignment_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $student_id, $student_id, $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();
$stmt->close();

if(!$assignment){
    die("Assignment not found or not available for you.");
}

// ==========================================
// PARSE LECTURER'S UPLOADED FILES (JSON format with original names)
// ==========================================
$lecturer_files = [];
if (!empty($assignment['reference_file'])) {
    $decoded = json_decode($assignment['reference_file'], true);
    if (is_array($decoded)) {
        // New format: array of ['original_name' => ..., 'server_path' => ...]
        foreach ($decoded as $file) {
            if (isset($file['original_name']) && isset($file['server_path'])) {
                $lecturer_files[] = [
                    'name' => $file['original_name'],
                    'path' => $file['server_path']
                ];
            } elseif (is_string($file)) {
                // Old format: just a string path
                $lecturer_files[] = [
                    'name' => basename($file),
                    'path' => $file
                ];
            }
        }
    } elseif (is_string($decoded)) {
        // Old format: single string
        $lecturer_files[] = [
            'name' => basename($decoded),
            'path' => $decoded
        ];
    }
}

// Preload previous submission if exists
$submitted_file_name = $assignment['submission_file_name'] ?? $assignment['essay_file_name'] ?? null;
$submitted_content = $assignment['code'] ?? $assignment['essay'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    $file_name = null;
    $file_content = '';

    // Fetch assignment type
    $stmt_type = $conn->prepare("SELECT type FROM assignment WHERE assignment_id = ?");
    $stmt_type->bind_param("i", $assignment_id);
    $stmt_type->execute();
    $stmt_type->bind_result($assignment_type);
    $stmt_type->fetch();
    $stmt_type->close();

    // Handle file upload - ONLY .txt files allowed
    if(isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK){
        $file_tmp = $_FILES['submission_file']['tmp_name'];
        $original_name = $_FILES['submission_file']['name'];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $file_size = $_FILES['submission_file']['size'];
        
        // ONLY allow .txt files
        if($file_ext === 'txt'){
            if($file_size <= 10485760){ // 10MB max
                // Read file content for plagiarism analysis
                $file_content = file_get_contents($file_tmp);
                
                // Save the file with ORIGINAL name in uploads folder (for reference)
                $upload_dir = 'uploads/submissions/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
                $file_path = $upload_dir . $safe_filename;
                
                // Check if file with same name exists, append number if needed
                $counter = 1;
                $original_path = $file_path;
                while (file_exists($file_path)) {
                    $path_parts = pathinfo($original_path);
                    $file_path = $upload_dir . $path_parts['filename'] . '_' . $counter . '.' . $path_parts['extension'];
                    $counter++;
                }
                
                if(move_uploaded_file($file_tmp, $file_path)){
                    $file_name = $safe_filename; // Store original filename
                } else {
                    $error = "Failed to upload file. Please try again.";
                }
            } else {
                $error = "File too large (max 10MB).";
            }
        } else {
            $error = "Only .txt files are allowed for plagiarism analysis. Please upload a text file.";
        }
    } else {
        $error = "Please select a .txt file to upload.";
    }

    // Insert or update existing submission
    if(empty($error)){
        $table = ($assignment_type == 'code') ? 'code_submission' : 'essay_submission';
        $content_column = ($assignment_type == 'code') ? 'code' : 'essay';
        
        // Check if submission exists
        $check_sql = "SELECT * FROM $table WHERE assignment_id=? AND student_id=?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $assignment_id, $student_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if($existing){
            // UPDATE - store file content in the content column for plagiarism analysis
            $stmt = $conn->prepare("UPDATE $table SET $content_column=?, file_name=? WHERE assignment_id=? AND student_id=?");
            $stmt->bind_param("ssii", $file_content, $file_name, $assignment_id, $student_id);
        } else {
            // INSERT - store file content in the content column for plagiarism analysis
            $stmt = $conn->prepare("INSERT INTO $table (assignment_id, student_id, $content_column, file_name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $assignment_id, $student_id, $file_content, $file_name);
        }

        if($stmt->execute()){
            $show_success = true;
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment - SILS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Manrope', sans-serif; background: #f0f2f5; }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.95); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        @keyframes checkmark {
            0% { stroke-dashoffset: 100; }
            100% { stroke-dashoffset: 0; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .header {
            background: #003366;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 30px;
        }
        
        /* File list styles */
        .file-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }
        
        .file-card {
            background: #f8f9fc;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }
        
        .file-card:hover {
            background: #e8f0fe;
            border-color: #003366;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .file-icon { font-size: 32px; }
        .file-info { flex: 1; }
        .file-name { font-size: 14px; font-weight: 600; color: #003366; word-break: break-all; }
        .file-size { font-size: 11px; color: #666; margin-top: 2px; }
        .download-icon { opacity: 0; transition: opacity 0.3s; color: #003366; }
        .file-card:hover .download-icon { opacity: 1; }
        
        /* Dropzone styles */
        .dropzone {
            border: 2px dashed #ccc;
            border-radius: 16px;
            background: #fafafa;
            transition: all 0.3s;
            cursor: pointer;
        }
        .dropzone:hover, .dropzone.drag-over { border-color: #003366; background: #e8f0fe; }
        .file-selected { border-color: #28a745; background: #e8f5e9; }
        
        .submitted-file-info {
            background: #e8f5e9;
            border: 1px solid #28a745;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        /* Success Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .success-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: bounceIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .checkmark-circle {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745, #20c997);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .checkmark-svg { width: 60px; height: 60px; }
        .checkmark-svg path {
            stroke: white;
            stroke-width: 3;
            fill: none;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: checkmark 0.6s ease-in-out 0.3s forwards;
        }
        
        .btn-primary {
            background: #003366;
            transition: all 0.3s;
        }
        .btn-primary:hover { background: #1a4d8c; transform: translateY(-2px); }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
            transition: all 0.3s;
        }
        .btn-secondary:hover { background: #ccc; transform: translateY(-2px); }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>

<div class="header">📝 Submit Assignment</div>

<main class="max-w-4xl mx-auto px-6 py-4">

<!-- Back Button -->
<div class="mb-4">
    <a href="student_dashboard_2.php" class="inline-flex items-center gap-2 text-blue-800 hover:text-blue-600 font-medium">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        Back to Dashboard
    </a>
</div>

<?php if($assignment): ?>

<!-- Assignment Details Card -->
<section class="bg-white rounded-xl shadow-md mb-6 overflow-hidden">
    <div class="bg-gradient-to-r from-blue-900 to-blue-700 px-6 py-4">
        <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($assignment['tittle']); ?></h2>
        <p class="text-blue-100 text-sm mt-1"><?php echo htmlspecialchars($assignment['class_name']); ?></p>
    </div>
    
    <div class="p-6">
        <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b">
            <div>
                <span class="text-xs text-gray-500 uppercase">Opened</span>
                <p class="font-semibold text-gray-800"><?php echo date('d F Y, h:i A', strtotime($assignment['start_date'])); ?></p>
            </div>
            <div>
                <span class="text-xs text-gray-500 uppercase">Due Date</span>
                <p class="font-semibold text-gray-800"><?php echo date('d F Y, h:i A', strtotime($assignment['due_date'])); ?></p>
            </div>
        </div>
        
        <div class="mb-4">
            <span class="text-xs text-gray-500 uppercase">Description</span>
            <p class="text-gray-700 mt-1"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
        </div>
        
        <!-- Lecturer's Files Section - DISPLAYS ORIGINAL FILENAMES -->
        <?php if(!empty($lecturer_files)): ?>
        <div class="mt-4 pt-4 border-t">
            <span class="text-xs text-gray-500 uppercase flex items-center gap-1 mb-3">
                <span class="material-symbols-outlined text-[16px]">attach_file</span>
                Reference Materials / Assignment Brief
            </span>
            <div class="file-list">
                <?php foreach($lecturer_files as $file):
                    $file_name = $file['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Determine icon based on file extension
                    $icon = 'description';
                    if($file_ext == 'pdf') $icon = 'picture_as_pdf';
                    elseif($file_ext == 'zip') $icon = 'folder_zip';
                    elseif(in_array($file_ext, ['py', 'java', 'c', 'cpp', 'js', 'html', 'css'])) $icon = 'code';
                    elseif(in_array($file_ext, ['doc', 'docx'])) $icon = 'article';
                    
                    // Get file size if file exists
                    $file_size = 'Unknown size';
                    if(file_exists($file['path'])){
                        $size_bytes = filesize($file['path']);
                        if($size_bytes < 1024 * 1024){
                            $file_size = round($size_bytes / 1024, 2) . ' KB';
                        } else {
                            $file_size = round($size_bytes / (1024 * 1024), 2) . ' MB';
                        }
                    }
                ?>
                <a href="<?php echo htmlspecialchars($file['path']); ?>" download class="file-card no-underline" target="_blank">
                    <span class="material-symbols-outlined file-icon"><?php echo $icon; ?></span>
                    <div class="file-info">
                        <div class="file-name"><?php echo htmlspecialchars($file_name); ?></div>
                        <div class="file-size"><?php echo $file_size; ?></div>
                    </div>
                    <span class="material-symbols-outlined download-icon">download</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Submission Form -->
<form method="POST" enctype="multipart/form-data" id="submissionForm">
    <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
    
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-blue-700">cloud_upload</span>
            Your Submission (.txt files only)
        </h3>
        
        <?php if($submitted_file_name): ?>
        <div class="submitted-file-info mb-4">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <div class="flex-1">
                <p class="text-sm font-medium text-green-800">Previously submitted file:</p>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($submitted_file_name); ?></p>
            </div>
            <span class="text-xs text-green-600">Upload a new file to replace</span>
        </div>
        <?php endif; ?>
        
        <!-- File Upload Dropzone -->
        <div id="dropzone" class="dropzone rounded-xl p-8 text-center cursor-pointer transition-all">
            <input type="file" name="submission_file" id="fileInput" class="hidden" accept=".txt">
            <div id="dropzoneContent">
                <span class="material-symbols-outlined text-5xl text-gray-400 mb-3">upload_file</span>
                <p class="text-gray-600 mb-2">Drag & drop your .txt file here or click to browse</p>
                <p class="text-xs text-red-500">⚠️ Only .txt files are allowed (for plagiarism analysis)</p>
                <p class="text-xs text-gray-400 mt-1">Max file size: 10MB</p>
            </div>
            <div id="selectedFileInfo" class="hidden mt-4 p-3 bg-green-50 rounded-lg">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-green-600">insert_drive_file</span>
                    <div class="flex-1 text-left">
                        <p id="selectedFileName" class="text-sm font-medium text-gray-800"></p>
                        <p id="selectedFileSize" class="text-xs text-gray-500"></p>
                    </div>
                    <button type="button" id="clearFileBtn" class="text-red-500 hover:text-red-700">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Submit Button -->
        <button type="submit" class="w-full mt-6 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-xl transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined">send</span>
            Submit Assignment
        </button>
    </div>
</form>

<?php else: ?>
    <div class="text-center py-12">
        <p class="text-gray-500">Assignment not found.</p>
    </div>
<?php endif; ?>

</main>

<!-- Success Modal (shown after successful submission) -->
<?php if($show_success): ?>
<div id="successModal" class="modal-overlay">
    <div class="success-card">
        <div class="checkmark-circle">
            <svg class="checkmark-svg" viewBox="0 0 52 52">
                <path d="M14 27 L22 35 L38 18" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-green-600 mb-2">✓ Assignment Submitted!</h1>
        <p class="text-gray-600 mb-4">
            Your assignment <strong>"<?php echo htmlspecialchars($assignment['tittle']); ?>"</strong> has been successfully submitted.
        </p>
        
        <div class="bg-gray-50 rounded-xl p-4 mb-6 text-left">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-green-600">schedule</span>
                <div>
                    <p class="text-xs text-gray-500">Submitted on</p>
                    <p class="text-sm font-medium text-gray-700"><?php echo date('d F Y, h:i A'); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2">
                <span class="material-symbols-outlined text-blue-600">info</span>
                <div>
                    <p class="text-xs text-gray-500">Status</p>
                    <p class="text-sm font-medium text-green-600">Pending Review</p>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2">
                <span class="material-symbols-outlined text-orange-500">analytics</span>
                <div>
                    <p class="text-xs text-gray-500">Plagiarism Check</p>
                    <p class="text-sm font-medium text-orange-600">Will be analyzed</p>
                </div>
            </div>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="Assignment_Select.php" class="btn-primary flex-1 text-white font-semibold py-3 px-4 rounded-xl text-center">
                View All Assignments
            </a>
            <a href="student_dashboard_2.php" class="btn-secondary flex-1 font-semibold py-3 px-4 rounded-xl text-center">
                Go to Dashboard
            </a>
        </div>
        
        <p class="text-xs text-gray-400 mt-6">
            Redirecting to Assignments in <span id="countdown" class="font-bold text-blue-600">5</span> seconds
        </p>
    </div>
</div>

<script>
    let seconds = 5;
    const countdownElement = document.getElementById('countdown');
    
    const interval = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = 'Assignment_Select.php';
        }
    }, 1000);
</script>
<?php endif; ?>

<script>
// File Upload Dropzone Logic
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const dropzoneContent = document.getElementById('dropzoneContent');
const selectedFileInfo = document.getElementById('selectedFileInfo');
const selectedFileName = document.getElementById('selectedFileName');
const selectedFileSize = document.getElementById('selectedFileSize');
const clearFileBtn = document.getElementById('clearFileBtn');

let selectedFile = null;

// Click on dropzone to trigger file input
dropzone.addEventListener('click', (e) => {
    if (e.target !== clearFileBtn && !clearFileBtn.contains(e.target)) {
        fileInput.click();
    }
});

// Drag and drop events
dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('drag-over');
});

dropzone.addEventListener('dragleave', (e) => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const file = files[0];
        // Validate file extension
        if (file.name.toLowerCase().endsWith('.txt')) {
            handleFileSelect(file);
            fileInput.files = files;
        } else {
            alert('Only .txt files are allowed for plagiarism analysis!');
        }
    }
});

// File input change
fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        const file = e.target.files[0];
        if (file.name.toLowerCase().endsWith('.txt')) {
            handleFileSelect(file);
        } else {
            alert('Only .txt files are allowed for plagiarism analysis!');
            fileInput.value = '';
        }
    }
});

function handleFileSelect(file) {
    selectedFile = file;
    const fileSize = file.size;
    let sizeString = '';
    
    if (fileSize < 1024 * 1024) {
        sizeString = (fileSize / 1024).toFixed(2) + ' KB';
    } else {
        sizeString = (fileSize / (1024 * 1024)).toFixed(2) + ' MB';
    }
    
    selectedFileName.textContent = file.name;
    selectedFileSize.textContent = sizeString;
    
    dropzoneContent.classList.add('hidden');
    selectedFileInfo.classList.remove('hidden');
    dropzone.classList.add('file-selected');
}

function clearSelectedFile() {
    selectedFile = null;
    fileInput.value = '';
    dropzoneContent.classList.remove('hidden');
    selectedFileInfo.classList.add('hidden');
    dropzone.classList.remove('file-selected');
}

clearFileBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    clearSelectedFile();
});

// Form validation before submit
document.getElementById('submissionForm').addEventListener('submit', (e) => {
    if (!selectedFile && !fileInput.files.length) {
        e.preventDefault();
        alert('Please select a .txt file to submit.');
    }
});
</script>

</body>
</html>