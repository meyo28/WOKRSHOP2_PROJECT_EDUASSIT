<?php
session_start();

if(isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if($_SESSION['user_type'] == 'lecturer') {
        header("Location: lecturer_dashboard.php");
    } else {
        header("Location: student_dashboard_2.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDUASSIST - Student Integrity & Learning System | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #003366 0%, #1a4d8c 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            padding: 45px 40px;
            width: 480px;
            max-width: 100%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-icon {
            font-size: 55px;
            margin-bottom: 10px;
        }

        .logo h1 {
            font-size: 32px;
            color: #003366;
            letter-spacing: 1px;
        }

        .tagline {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }

        .subtitle {
            text-align: center;
            color: #999;
            font-size: 11px;
            margin-top: 3px;
            margin-bottom: 25px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-error {
            background: #fff0f0;
            color: #c00;
            border-left: 4px solid #c00;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #003366;
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #003366;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .login-btn:hover {
            background: #1a4d8c;
            transform: translateY(-2px);
        }

        .copyright {
            text-align: center;
            margin-top: 25px;
            font-size: 11px;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">🎓</div>
            <h1>EDUASSIST</h1>
            <p class="tagline">Student Integrity & Learning System</p>
            <p class="subtitle">Automated Grading | Plagiarism Detection | Socratic AI</p>
        </div>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    if($_GET['error'] == 'invalid') echo "❌ Invalid Matric/Staff ID or Password.";
                    elseif($_GET['error'] == 'empty') echo "❌ Please enter both ID and password.";
                    elseif($_GET['error'] == 'invalid_format') echo "❌ Invalid format. Use B/D for student or S for lecturer.";
                    elseif($_GET['error'] == 'login_required') echo "❌ Please login to access this page.";
                ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['message']) && $_GET['message'] == 'logout'): ?>
            <div class="alert alert-success">✅ You have been successfully logged out.</div>
        <?php endif; ?>

        <form action="login_process.php" method="POST">
            <div class="input-group">
                <label>📌 Matric No / Staff ID</label>
                <input type="text" name="user_id" placeholder="Enter your Matric or Staff ID" required autofocus>
            </div>
            <div class="input-group">
                <label>🔒 Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="copyright">
            © 2026 | Faculty of Information and Communication Technology | UTeM
        </div>
    </div>
</body>
</html>