<?php
session_start();
include 'includes/config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.php");
    exit();
}

$user_id = trim($_POST['user_id']);
$password = trim($_POST['password']);

if (empty($user_id) || empty($password)) {
    header("Location: index.php?error=empty");
    exit();
}

$first_char = strtoupper(substr($user_id, 0, 1));

if ($user_id === 'A01' && $password === 'admin123') {
        $_SESSION['user_id'] = 'A01';
        $_SESSION['user_type'] = 'admin';
        header("Location: admin_dashboard.php");
        exit();
    }
// STUDENT LOGIN (B or D)
if ($first_char == 'B' || $first_char == 'D') {
    $sql = "SELECT student_id, full_name, matric_no, program, email FROM student WHERE matric_no = ? AND password = MD5(?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $user_id, $password);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['student_id'];
        $_SESSION['user_type'] = 'student';
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['matric_no'] = $user['matric_no'];
        $_SESSION['program'] = $user['program'];
        $_SESSION['email'] = $user['email'];
        header("Location: student_dashboard_2.php");
        exit();
    } else {
        header("Location: index.php?error=invalid");
        exit();
    }
}
// LECTURER LOGIN (S)
elseif ($first_char == 'S') {
    $sql = "SELECT lecturer_id, full_name, staff_id, email FROM lecturer WHERE staff_id = ? AND password = MD5(?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $user_id, $password);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['lecturer_id'];
        $_SESSION['user_type'] = 'lecturer';
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['staff_id'] = $user['staff_id'];
        $_SESSION['email'] = $user['email'];
        header("Location: lecturer_dashboard.php");
        exit();
    } else {
        header("Location: index.php?error=invalid");
        exit();
    }
}
else {
    header("Location: index.php?error=invalid_format");
    exit();
}

mysqli_close($conn);
?>