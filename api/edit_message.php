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
    echo json_encode(['error' => 'Config file not found at: ' . $config_path]);
    exit;
}

include $config_path;

if (!isset($conn) || !$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$student_id = $_SESSION['user_id'];
$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : null;
$new_message = trim($_POST['new_message'] ?? '');

if (!$message_id) {
    echo json_encode(['error' => 'Message ID required']);
    exit;
}

if (empty($new_message)) {
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

// Verify message belongs to student and is a student message
$check_sql = "SELECT cm.message_id, cm.session_id, cm.sender, cm.created_at
              FROM chat_message cm
              JOIN chat_session cs ON cm.session_id = cs.session_id
              WHERE cm.message_id = ? AND cs.student_id = ? AND cm.sender = 'student'";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $message_id, $student_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(['error' => 'Message not found or cannot edit']);
    exit;
}

$message_data = mysqli_fetch_assoc($check_result);
$session_id = $message_data['session_id'];
$message_created_at = $message_data['created_at'];

// Update the message
$update_sql = "UPDATE chat_message SET message = ? WHERE message_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "si", $new_message, $message_id);

if (mysqli_stmt_execute($update_stmt)) {
    // Delete subsequent messages (AI responses and any following student messages)
    // This maintains logical conversation flow
    $delete_sql = "DELETE FROM chat_message WHERE session_id = ? AND created_at > ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "is", $session_id, $message_created_at);
    mysqli_stmt_execute($delete_stmt);
    
    echo json_encode(['success' => true, 'new_message' => $new_message]);
} else {
    echo json_encode(['error' => 'Failed to edit message: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>