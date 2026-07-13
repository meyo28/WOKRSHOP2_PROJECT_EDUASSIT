<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'student') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Fix: Correct path to config.php
$config_path = __DIR__ . '/../includes/config.php';
if (!file_exists($config_path)) {
    echo json_encode(['error' => 'Config file not found']);
    exit;
}

include $config_path;

if (!isset($conn) || !$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$student_id = $_SESSION['user_id'];
$session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;
$new_title = trim($_POST['new_title'] ?? '');

if (!$session_id) {
    echo json_encode(['error' => 'Session ID required']);
    exit;
}

if (empty($new_title)) {
    echo json_encode(['error' => 'Title cannot be empty']);
    exit;
}

// Verify session belongs to student
$check_sql = "SELECT session_id FROM chat_session WHERE session_id = ? AND student_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $session_id, $student_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(['error' => 'Session not found']);
    exit;
}

// Update session title
$update_sql = "UPDATE chat_session SET session_title = ? WHERE session_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "si", $new_title, $session_id);

if (mysqli_stmt_execute($update_stmt)) {
    echo json_encode(['success' => true, 'new_title' => $new_title]);
} else {
    echo json_encode(['error' => 'Failed to rename session: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>