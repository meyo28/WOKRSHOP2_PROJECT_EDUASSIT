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

// Check if connection exists
if (!isset($conn) || !$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$student_id = $_SESSION['user_id'];
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;

if (!$session_id) {
    // Get most recent session
    $recent_sql = "SELECT session_id FROM chat_session WHERE student_id = ? ORDER BY updated_at DESC LIMIT 1";
    $recent_stmt = mysqli_prepare($conn, $recent_sql);
    if ($recent_stmt) {
        mysqli_stmt_bind_param($recent_stmt, "i", $student_id);
        mysqli_stmt_execute($recent_stmt);
        $recent_result = mysqli_stmt_get_result($recent_stmt);
        
        if (mysqli_num_rows($recent_result) > 0) {
            $recent = mysqli_fetch_assoc($recent_result);
            $session_id = $recent['session_id'];
        }
    }
}

$messages = [];

if ($session_id) {
    // Verify session belongs to student
    $check_sql = "SELECT session_id FROM chat_session WHERE session_id = ? AND student_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ii", $session_id, $student_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Get all messages for this session including message_id
            $msg_sql = "SELECT message_id, sender, message, created_at FROM chat_message WHERE session_id = ? ORDER BY created_at ASC";
            $msg_stmt = mysqli_prepare($conn, $msg_sql);
            if ($msg_stmt) {
                mysqli_stmt_bind_param($msg_stmt, "i", $session_id);
                mysqli_stmt_execute($msg_stmt);
                $msg_result = mysqli_stmt_get_result($msg_stmt);
                
                while ($row = mysqli_fetch_assoc($msg_result)) {
                    $messages[] = [
                        'message_id' => $row['message_id'],
                        'sender' => $row['sender'],
                        'message' => $row['message'],
                        'time' => date('h:i A', strtotime($row['created_at']))
                    ];
                }
            }
        }
    }
}

echo json_encode([
    'session_id' => $session_id,
    'messages' => $messages
]);

mysqli_close($conn);
?>