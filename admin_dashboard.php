<?php
session_start();
include 'includes/config.php';

// Check if user is logged in AND is an admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php?error=login_required");
    exit();
}

// ---------------------------------------------------------
// SAFELY FETCH SYSTEM STATISTICS
// ---------------------------------------------------------
$total_students = 0;
$total_lecturers = 0;
$total_classes = 0;

// Get Student Count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM student");
if($result && $row = mysqli_fetch_assoc($result)) {
    $total_students = $row['count'];
}

// Get Lecturer Count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM lecturer");
if($result && $row = mysqli_fetch_assoc($result)) {
    $total_lecturers = $row['count'];
}

// Get Class Count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM class");
if($result && $row = mysqli_fetch_assoc($result)) {
    $total_classes = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SILS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
        .header { background: #003366; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .welcome { font-size: 24px; margin-bottom: 20px; color: #003366; font-weight: 600; }
        
        /* Stats Bar Styling */
        .stats-bar { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-box { flex: 1; background: #f8f9fc; padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #e0e0e0; border-top: 4px solid #003366; }
        .stat-number { font-size: 28px; font-weight: 700; color: #003366; margin-top: 5px; }
        .stat-label { font-size: 14px; color: #666; font-weight: 500; }

        /* Grid Layout for Admin Controls */
        h2 { font-size: 20px; color: #333; margin-bottom: 15px; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; }
        .admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .admin-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px 20px; transition: 0.2s; text-decoration: none; color: #333; display: flex; align-items: center; gap: 15px; }
        .admin-card:hover { background: #eef2f7; transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-color: #003366; }
        .card-icon { font-size: 30px; }
        .card-info h3 { font-size: 16px; color: #003366; margin-bottom: 5px; }
        .card-info p { font-size: 12px; color: #666; }
        
        .logout-btn { background: #c00; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-top: 10px; font-weight: 500; }
        .logout-btn:hover { background: #a00; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛡️ EDUASSIST Admin Dashboard</h1>
    </div>
    <div class="container">
        <div class="welcome">Welcome, System Administrator!</div>
        
        <div class="stats-bar">
            <div class="stat-box">
                <div class="stat-label">Total Students</div>
                <div class="stat-number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Lecturers</div>
                <div class="stat-number"><?php echo $total_lecturers; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Classes Active</div>
                <div class="stat-number"><?php echo $total_classes; ?></div>
            </div>
        </div>

        <h2>⚙️ System Control Panel</h2>
        <div class="admin-grid">
            
            <a href="student_management.php" class="admin-card">
                <div class="card-icon">👥</div>
                <div class="card-info">
                    <h3>Manage Students</h3>
                    <p>Bulk upload via CSV, edit profiles, or reset student passwords.</p>
                </div>
            </a>

            <a href="lecturer_management.php" class="admin-card">
                <div class="card-icon">👨‍🏫</div>
                <div class="card-info">
                    <h3>Manage Lecturers</h3>
                    <p>Register new staff, assign lecturer IDs, and update details.</p>
                </div>
            </a>

            <a href="course_management.php" class="admin-card">
                <div class="card-icon">📚</div>
                <div class="card-info">
                    <h3>Course Management</h3>
                    <p>Create master subjects and assign them to specific lecturers.</p>
                </div>
            </a>

            <a href="admin_plagiarism_report.php" class="admin-card">
                <div class="card-icon">🚨</div>
                <div class="card-info">
                    <h3>Dean's Report</h3>
                    <p>View academic performance and program analytics for FTMK.</p>
                </div>
            </a>

        </div>
        
        <a href="logout.php"><button class="logout-btn">🚪 Logout</button></a>
    </div>
</body>
</html>