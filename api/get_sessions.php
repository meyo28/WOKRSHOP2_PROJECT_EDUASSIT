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

$sql = "SELECT session_id, session_title, created_at, updated_at 
        FROM chat_session 
        WHERE student_id = ? 
        ORDER BY updated_at DESC";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode(['error' => 'Database query failed: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$sessions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $sessions[] = [
        'session_id' => $row['session_id'],
        'title' => $row['session_title'],
        'created_at' => date('M d, Y', strtotime($row['created_at'])),
        'updated_at' => date('M d, h:i A', strtotime($row['updated_at']))
    ];
}

echo json_encode(['sessions' => $sessions]);

mysqli_close($conn);
?>