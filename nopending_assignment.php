<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php?error=login_required");
    exit();
}

// Check if the student has any pending assignments
$student_id = $_SESSION['user_id'];
$sql = "SELECT a.assignment_id 
        FROM assignment a
        JOIN enrollment e ON a.class_id = e.class_id
        LEFT JOIN code_submission cs ON a.assignment_id = cs.assignment_id AND cs.student_id = ?
        LEFT JOIN essay_submission es ON a.assignment_id = es.assignment_id AND es.student_id = ?
        WHERE e.student_id = ? AND cs.assignment_id IS NULL AND es.assignment_id IS NULL
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $student_id, $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$has_assignment = $result->num_rows > 0;
$stmt->close();

// If there are pending assignments, redirect to submit_assignment.php
if($has_assignment){
    header("Location: Assignment_Select.php");
    exit();
}
?>

<!DOCTYPE html><html class="light" lang="en" style=""><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>EduSubmit - My Assignments</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          "colors": {
            "outline": "#737780",
            "background": "#fbf9f8",
            "secondary": "#006e1c",
            "surface-container-highest": "#e4e2e1",
            "on-secondary-container": "#00731e",
            "on-tertiary": "#ffffff",
            "on-surface-variant": "#43474f",
            "surface-bright": "#fbf9f8",
            "on-tertiary-fixed": "#410000",
            "surface-container-lowest": "#ffffff",
            "on-primary-fixed": "#001b3c",
            "on-primary-container": "#799dd6",
            "surface-container": "#f0eded",
            "primary": "#001e40",
            "primary-fixed": "#d5e3ff",
            "on-secondary-fixed": "#002204",
            "surface-variant": "#e4e2e1",
            "secondary-fixed-dim": "#78dc77",
            "on-primary-fixed-variant": "#1f477b",
            "inverse-surface": "#303030",
            "tertiary": "#460000",
            "on-tertiary-container": "#ff6d59",
            "error-container": "#ffdad6",
            "on-error-container": "#93000a",
            "on-error": "#ffffff",
            "on-tertiary-fixed-variant": "#930000",
            "surface-dim": "#dcd9d9",
            "secondary-container": "#91f78e",
            "tertiary-fixed": "#ffdad4",
            "surface": "#fbf9f8",
            "on-secondary": "#ffffff",
            "error": "#ba1a1a",
            "tertiary-fixed-dim": "#ffb4a8",
            "tertiary-container": "#6e0000",
            "outline-variant": "#c3c6d1",
            "on-background": "#1b1c1c",
            "on-primary": "#ffffff",
            "surface-tint": "#3a5f94",
            "on-surface": "#1b1c1c",
            "primary-fixed-dim": "#a7c8ff",
            "surface-container-low": "#f6f3f2",
            "inverse-on-surface": "#f3f0f0",
            "surface-container-high": "#eae8e7",
            "primary-container": "#003366",
            "on-secondary-fixed-variant": "#005313",
            "inverse-primary": "#a7c8ff",
            "secondary-fixed": "#94f990"
          },
          "borderRadius": {
            "DEFAULT": "0.25rem",
            "lg": "0.5rem",
            "xl": "0.75rem",
            "full": "9999px"
          },
          "spacing": {
            "gutter": "24px",
            "margin-desktop": "48px",
            "unit": "4px",
            "margin-mobile": "16px",
            "container-max-width": "1140px"
          },
          "fontFamily": {
            "label-sm": ["Manrope"],
            "body-lg": ["Manrope"],
            "label-md": ["Manrope"],
            "headline-lg-mobile": ["Manrope"],
            "body-md": ["Manrope"],
            "headline-md": ["Manrope"],
            "headline-lg": ["Manrope"]
          },
          "fontSize": {
            "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
            "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
            "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.01em", "fontWeight": "600"}],
            "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
            "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
            "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
            "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}]
          }
        },
      },
    }
  </script>
<style>
body { font-family: 'Manrope', sans-serif; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="bg-background text-on-surface min-h-screen">


<main class="flex-1 p-6">
  <!-- Action Header -->
 <div class="flex justify-between items-center mb-12">
  <div class="flex flex-col">
    <h1 class="font-headline-lg text-headline-lg text-primary">Assignments</h1>
    <p class="font-body-md text-on-surface-variant">Manage and track your academic tasks</p>
  </div>
  <form action="history_assignment.php" method="get">
    <button type="submit" class="px-6 py-2.5 border border-primary text-primary font-label-md rounded-lg hover:bg-primary hover:text-on-primary flex items-center gap-2">
      <span class="material-symbols-outlined">history</span>
      History submit assignment
    </button>
  </form>
</div>

<!-- Top Left Back Arrow -->
<div class="max-w-container-max-width mx-auto px-6 mt-4 mb-6 flex justify-start">
  <a href="student_dashboard_2.php" class="inline-flex items-center gap-2 text-blue-800 font-semibold hover:underline text-lg">
    <span class="material-symbols-outlined text-lg">arrow_back</span>
    Back to Homepage
  </a>
</div>


  <!-- Empty State -->
  <div class="flex-1 flex flex-col items-center justify-center">
    <!-- Icon -->
    <div class="w-64 h-64 mb-8 relative flex items-center justify-center">
      <div class="absolute inset-0 bg-green-100 rounded-full blur-3xl"></div>
      <div class="relative bg-surface-container-low border border-outline-variant w-48 h-56 rounded-2xl shadow-sm flex flex-col p-6 items-center justify-center">
        <div class="w-20 h-20 rounded-full bg-green-200 flex items-center justify-center mb-6">
          <span class="material-symbols-outlined text-on-secondary-container !text-4xl">task_alt</span>
        </div>
      </div>
    </div>

    <!-- Text -->
    <h3 class="font-headline-md text-headline-md text-on-surface mb-2">No pending assignment at the moment</h3>
    <p class="font-body-md text-body-md text-on-surface-variant text-center max-w-sm mb-8">
      Great job! You've caught up with all your current tasks. Check your submission history while you wait for new updates.
    </p>

    <!-- Refresh Button -->
    <form method="get">
      <button type="submit" class="px-8 py-3 bg-blue-600 text-white rounded-xl font-label-md text-label-md hover:scale-[1.02] transition-transform shadow-md">
        Refresh Portal
      </button>
    </form>
  </div>

  <!-- Guidance Boxes -->
  <div class="flex flex-col md:flex-row justify-center gap-6 mt-12 pb-8">
    <form action="grammar_check.php" method="get" class="w-full md:w-auto">
      <button type="submit" class="p-6 rounded-2xl bg-surface-container-low border border-surface-variant hover:shadow-md w-full flex flex-col items-center">
        <span class="material-symbols-outlined text-primary mb-4">auto_awesome</span>
        <h4 class="font-label-md text-label-md text-on-surface mb-1">Grammar Checker</h4>
        <p class="font-body-md text-body-md text-on-surface-variant text-sm text-center">Improve your writing. Use our built-in tool to check your grammar and spelling before submitting.</p>
      </button>
    </form>

    <form action="study_helper.php" method="get" class="w-full md:w-auto">
      <button type="submit" class="p-6 rounded-2xl bg-surface-container-low border border-surface-variant hover:shadow-md w-full flex flex-col items-center">
        <span class="material-symbols-outlined text-primary mb-4">support_agent</span>
        <h4 class="font-label-md text-label-md text-on-surface mb-1">Ask our AI Tutor</h4>
        <p class="font-body-md text-body-md text-on-surface-variant text-sm text-center">Have questions about the subject? Link with our AI bot to get instant answers and study support.</p>
      </button>
    </form>
  </div>

</main>
</body>
</html>