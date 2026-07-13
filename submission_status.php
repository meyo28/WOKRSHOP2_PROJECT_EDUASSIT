<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php?error=login_required");
    exit();
}

$student_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if(!$assignment_id){
    die("Invalid assignment.");
}

// Handle Delete Request
if(isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['confirm']) && $_GET['confirm'] == 'yes'){
    // Determine assignment type
    $stmt_type = $conn->prepare("SELECT type FROM assignment WHERE assignment_id = ?");
    $stmt_type->bind_param("i", $assignment_id);
    $stmt_type->execute();
    $type_result = $stmt_type->get_result()->fetch_assoc();
    $stmt_type->close();
    
    $assignment_table = $type_result['type'] === 'code' ? 'code_submission' : 'essay_submission';
    
    // Get file path before deleting
    $get_file_sql = "SELECT file_name FROM $assignment_table WHERE assignment_id = ? AND student_id = ?";
    $get_file_stmt = $conn->prepare($get_file_sql);
    $get_file_stmt->bind_param("ii", $assignment_id, $student_id);
    $get_file_stmt->execute();
    $file_result = $get_file_stmt->get_result();
    if($file_row = $file_result->fetch_assoc()){
        $file_to_delete = $file_row['file_name'];
        // Delete physical file if exists
        if($file_to_delete && file_exists('uploads/submissions/' . $file_to_delete)){
            unlink('uploads/submissions/' . $file_to_delete);
        }
    }
    $get_file_stmt->close();
    
    // Delete from database
    $delete_sql = "DELETE FROM $assignment_table WHERE assignment_id = ? AND student_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $assignment_id, $student_id);
    
    if($delete_stmt->execute()){
        // Redirect to submit_assignment.php with success message
        header("Location: submit_assignment.php?assignment_id=$assignment_id&deleted=1");
        exit();
    } else {
        $delete_error = "Failed to delete submission. Please try again.";
    }
    $delete_stmt->close();
}

// Determine assignment type
$stmt_type = $conn->prepare("SELECT type FROM assignment WHERE assignment_id = ?");
$stmt_type->bind_param("i", $assignment_id);
$stmt_type->execute();
$type_result = $stmt_type->get_result()->fetch_assoc();
$stmt_type->close();

$assignment_table = $type_result['type'] === 'code' ? 'code_submission' : 'essay_submission';
$content_column = $type_result['type'] === 'code' ? 'code' : 'essay';

// Fetch assignment details + submitted file/text + submitted_at + final_grade
$sql = "SELECT a.assignment_id, a.tittle, a.description, a.start_date, a.due_date, a.type, c.class_name,
       sub.file_name, sub.$content_column AS submission_text, sub.submitted_at, sub.final_grade
       FROM assignment a
       JOIN class c ON a.class_id = c.class_id
       LEFT JOIN $assignment_table sub ON sub.assignment_id = a.assignment_id AND sub.student_id = ?
       WHERE a.assignment_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$assignment){
    die("Assignment not found or not submitted.");
}

// Determine submitted file/text
$submitted_file = $assignment['file_name'] ?? null;
$submitted_text = $assignment['submission_text'] ?? '';
$final_grade = $assignment['final_grade'] ?? 'Not graded';
$submitted_time = isset($assignment['submitted_at']) ? new DateTime($assignment['submitted_at']) : null;

// Setup Deadlines and Lock States
$due = new DateTime($assignment['due_date']);
$now = new DateTime(); // Current operational timestamp
$is_past_due = ($now > $due);

if($submitted_time){
    $interval = $submitted_time->diff($due);
    
    // Absolute differences for calculation
    $total_seconds = abs($due->getTimestamp() - $submitted_time->getTimestamp());
    $days = floor($total_seconds / 86400);
    $hours = floor(($total_seconds % 86400) / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);

    // Build human-readable formatted string
    $time_string = "";
    if ($days > 0) {
        $time_string .= $days . ($days == 1 ? " day " : " days ");
    }
    if ($hours > 0 || $days > 0) {
        $time_string .= $hours . ($hours == 1 ? " hour " : " hours ");
    }
    $time_string .= $minutes . ($minutes == 1 ? " min" : " mins");

    if($submitted_time <= $due){
        $time_remaining_text = "Assignment was submitted {$time_string} early";
        $time_class = "bg-green-100 text-green-800";
    } else {
        $time_remaining_text = "Assignment was submitted {$time_string} late";
        $time_class = "bg-red-100 text-red-800";
    }
    $submitted_display = $submitted_time->format('l, d F Y, h:i A');
} else {
    $time_remaining_text = "No submission time recorded";
    $time_class = "bg-gray-100 text-gray-700";
    $submitted_display = "N/A";
}

// Determine file type for icon
$file_extension = '';
$file_icon = 'description';
if($submitted_file){
    $file_extension = strtolower(pathinfo($submitted_file, PATHINFO_EXTENSION));
    if($file_extension == 'txt') $file_icon = 'description';
    elseif($file_extension == 'pdf') $file_icon = 'picture_as_pdf';
    elseif($file_extension == 'zip') $file_icon = 'folder_zip';
    elseif(in_array($file_extension, ['py', 'java', 'c', 'cpp', 'js'])) $file_icon = 'code';
    else $file_icon = 'attach_file';
}

// Build file path for download
$file_download_path = 'uploads/submissions/' . $submitted_file;
$file_exists = $submitted_file && file_exists($file_download_path);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submission Status - SILS</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
body { font-family: 'Manrope', sans-serif; background: #f0f2f5; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400; }

.header {
    background: #003366;
    color: white;
    padding: 20px;
    text-align: center;
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 30px;
}

/* File Card Hover Effect */
.file-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.file-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #003366 !important;
    background: #e8f0fe !important;
}

.file-card:hover .download-icon {
    opacity: 1;
}

.download-icon {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.submission-table tr:last-child td { border-bottom: none; }

/* Delete Modal */
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

.modal-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    animation: bounceIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes bounceIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.btn-danger {
    background: #dc2626;
    transition: all 0.3s;
}
.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-2px);
}
.btn-secondary {
    background: #e5e7eb;
    color: #374151;
    transition: all 0.3s;
}
.btn-secondary:hover {
    background: #d1d5db;
    transform: translateY(-2px);
}

/* Success Toast */
.toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #28a745;
    color: white;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    z-index: 1001;
    animation: slideIn 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>
</head>
<body class="bg-background text-on-surface antialiased">

<div class="header">📚 Assignment Submission Status</div>

<main class="flex-grow p-4 md:p-8 min-h-screen">
    <div class="w-full max-w-4xl mx-auto px-4 md:px-8">
        
        <!-- Back Button -->
        <div class="mb-6">
            <a href="Assignment_Select.php" class="inline-flex items-center gap-1 text-blue-800 hover:text-blue-600 font-medium transition-all group">
                <span class="material-symbols-outlined text-[18px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
                Back to Assignments
            </a>
        </div>

        <!-- Success Toast for Delete -->
        <?php if(isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div id="successToast" class="toast">
            <span class="material-symbols-outlined text-[18px] align-middle mr-2">check_circle</span>
            Submission deleted successfully!
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('successToast');
                if(toast) toast.remove();
            }, 3000);
        </script>
        <?php endif; ?>

        <section class="bg-white border border-gray-200 p-6 rounded-2xl mb-6 shadow-sm">
            <div class="flex flex-col md:flex-row gap-6 md:gap-16 mb-6 pb-6 border-b border-gray-200">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Opened</p>
                    <p class="text-sm text-gray-800 font-semibold"><?php echo date('l, d F Y, h:i A', strtotime($assignment['start_date'])); ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Due Date</p>
                    <p class="text-sm text-gray-800 font-semibold"><?php echo date('l, d F Y, h:i A', strtotime($assignment['due_date'])); ?></p>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-blue-700 mt-1">info</span>
                    <div class="space-y-2">
                        <p class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($assignment['tittle']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <table class="w-full text-left submission-table border-collapse">
                <tbody>
                    <tr class="border-b border-gray-200">
                        <td class="bg-gray-50 p-5 font-medium text-gray-700 w-1/3">Submission status</td>
                        <td class="p-5">
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-100 text-green-800 font-semibold text-sm">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                Submitted for grading
                            </span>
                        </td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="bg-gray-50 p-5 font-medium text-gray-700">Grading status</td>
                        <td class="p-5 text-sm text-gray-600 italic"><?php echo htmlspecialchars($final_grade); ?></td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="bg-gray-50 p-5 font-medium text-gray-700">Time remaining</td>
                        <td class="p-5">
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full <?php echo $time_class; ?> font-semibold text-sm">
                                <?php echo $time_remaining_text; ?>
                            </span>
                        </td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="bg-gray-50 p-5 font-medium text-gray-700">Last modified</td>
                        <td class="p-5 text-sm text-gray-600"><?php echo $submitted_display; ?></td>
                    </tr>
                    <tr>
                        <td class="bg-gray-50 p-5 font-medium text-gray-700">Submitted File</td>
                        <td class="p-5">
                            <?php if($submitted_file): ?>
                                <?php if($file_exists): ?>
                                <a href="<?php echo $file_download_path; ?>" download class="file-card no-underline block">
                                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl border border-gray-200 transition-all">
                                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                                            <span class="material-symbols-outlined text-3xl text-blue-700"><?php echo $file_icon; ?></span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-blue-800 font-semibold text-sm"><?php echo htmlspecialchars($submitted_file); ?></p>
                                            <p class="text-xs text-gray-500">Click to download</p>
                                        </div>
                                        <span class="material-symbols-outlined download-icon text-blue-600">download</span>
                                    </div>
                                </a>
                                <?php else: ?>
                                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl border border-gray-200">
                                        <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                                            <span class="material-symbols-outlined text-3xl text-gray-500">description</span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-gray-800 font-semibold text-sm"><?php echo htmlspecialchars($submitted_file); ?></p>
                                            <p class="text-xs text-red-500">File not found on server</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php elseif($submitted_text): ?>
                                <pre class="p-4 bg-gray-50 rounded-md border border-gray-200 overflow-x-auto whitespace-pre-wrap font-mono text-sm"><?php echo htmlspecialchars($submitted_text); ?></pre>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">No submission file found.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="flex flex-col sm:flex-row gap-4 justify-center py-4">
            <?php if (!$is_past_due): ?>
                <a href="submit_assignment.php?assignment_id=<?php echo $assignment_id; ?>" 
                   class="flex items-center justify-center gap-2 px-6 py-3 bg-blue-800 text-white rounded-xl font-semibold hover:bg-blue-700 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-lg">edit</span>
                    Edit submission
                </a>
                <button onclick="showDeleteModal()"
                   class="flex items-center justify-center gap-2 px-6 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition-all shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined text-lg">delete</span>
                    Delete submission
                </button>
            <?php else: ?>
                <div class="flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-600 rounded-xl font-semibold border border-gray-200">
                    <span class="material-symbols-outlined text-base">lock</span>
                    Submission locked: The due date for this assignment has passed.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display: none;">
    <div class="modal-card">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-3xl text-red-600">warning</span>
        </div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Delete Submission?</h3>
        <p class="text-gray-500 text-sm mb-6">
            Are you sure you want to delete this submission?<br>
            This action cannot be undone.
        </p>
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 btn-secondary py-2 rounded-xl font-semibold">Cancel</button>
            <a href="submission_status.php?assignment_id=<?php echo $assignment_id; ?>&action=delete&confirm=yes" 
               class="flex-1 btn-danger py-2 rounded-xl font-semibold text-white text-center">
                Yes, Delete
            </a>
        </div>
    </div>
</div>

<script>
    function showDeleteModal() {
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    // Auto hide toast after 3 seconds
    setTimeout(() => {
        const toast = document.getElementById('successToast');
        if(toast) toast.style.opacity = '0';
    }, 2500);
</script>

</body>
</html>