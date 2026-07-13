<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if(!$assignment_id) die("Assignment not selected.");

// Fetch assignment info
$stmt = $conn->prepare("SELECT tittle, type, answer_file FROM assignment WHERE assignment_id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lecturer solution
$lecturer_text = '';
if (!empty($assignment['answer_file']) && file_exists($assignment['answer_file'])) {
    $lecturer_text = file_get_contents($assignment['answer_file']);
}

// Code submissions
$sql_code = "SELECT s.student_id, s.full_name AS student_name, cs.code AS submission_text, cs.file_name, cs.final_grade
             FROM student s
             JOIN code_submission cs ON s.student_id = cs.student_id
             WHERE cs.assignment_id=? AND cs.final_grade IS NOT NULL AND cs.final_grade<>''";
$stmt_code = $conn->prepare($sql_code);
$stmt_code->bind_param("i", $assignment_id);
$stmt_code->execute();
$code_submissions = $stmt_code->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_code->close();

// Essay submissions
$sql_essay = "SELECT s.student_id, s.full_name AS student_name, es.essay AS submission_text, es.file_name, es.final_grade
              FROM student s
              JOIN essay_submission es ON s.student_id = es.student_id
              WHERE es.assignment_id=? AND es.final_grade IS NOT NULL AND es.final_grade<>''";
$stmt_essay = $conn->prepare($sql_essay);
$stmt_essay->bind_param("i", $assignment_id);
$stmt_essay->execute();
$essay_submissions = $stmt_essay->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_essay->close();

// Merge all graded submissions
$graded_submissions = array_merge($code_submissions, $essay_submissions);

// Fetch all submissions for similarity calculation (both graded and ungraded)
$sql_all = "SELECT s.student_id, cs.code AS submission_text FROM student s
            JOIN code_submission cs ON s.student_id = cs.student_id
            WHERE cs.assignment_id = ? AND cs.code <> ''
            UNION ALL
            SELECT s.student_id, es.essay AS submission_text FROM student s
            JOIN essay_submission es ON s.student_id = es.student_id
            WHERE es.assignment_id = ? AND es.essay <> ''";
$stmt_all = $conn->prepare($sql_all);
$stmt_all->bind_param("ii", $assignment_id, $assignment_id);
$stmt_all->execute();
$all_submissions = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_all->close();

// Similarity calculation function
function calcSimilarity($text1, $text2, $lecturer_text='') {
    if(!empty($lecturer_text)){
        $text1 = str_replace($lecturer_text,'',$text1);
        $text2 = str_replace($lecturer_text,'',$text2);
    }

    // exact match shortcut
    if(trim($text1) === trim($text2)) return 100.00;

    $tokens1 = array_unique(array_filter(preg_split('/[\s,;(){}\[\]=+<>!&|]+/', $text1)));
    $tokens2 = array_unique(array_filter(preg_split('/[\s,;(){}\[\]=+<>!&|]+/', $text2)));
    if(empty($tokens1) || empty($tokens2)) return 0;

    $common = count(array_intersect($tokens1,$tokens2));
    $total = count($tokens1) + count($tokens2);
    return round(($common*2/$total)*100,2);
}

// Compute similarity score for display
foreach($graded_submissions as &$sub){
    $max_sim = 0;
    foreach($all_submissions as $other){
        if($sub['student_id'] === $other['student_id']) continue;
        $sim = calcSimilarity($sub['submission_text'], $other['submission_text'], $lecturer_text);
        if($sim > $max_sim) $max_sim = $sim;
    }
    $sub['score'] = round($max_sim, 2);
}
unset($sub);
?>


<!DOCTYPE html><html class="light" lang="en" style=""><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
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
                    "on-tertiary-container": "#ff6d59",
                    "surface-container": "#f0eded",
                    "tertiary": "#460000",
                    "primary-fixed": "#d5e3ff",
                    "surface-container-highest": "#e4e2e1",
                    "primary": "#001e40",
                    "on-secondary-fixed-variant": "#005313",
                    "outline": "#737780",
                    "surface-container-low": "#f6f3f2",
                    "secondary-fixed": "#94f990",
                    "primary-fixed-dim": "#a7c8ff",
                    "secondary": "#006e1c",
                    "primary-container": "#003366",
                    "inverse-surface": "#303030",
                    "tertiary-fixed": "#ffdad4",
                    "on-secondary-fixed": "#002204",
                    "surface-bright": "#fbf9f8",
                    "on-primary": "#ffffff",
                    "on-surface": "#1b1c1c",
                    "error": "#ba1a1a",
                    "tertiary-fixed-dim": "#ffb4a8",
                    "surface": "#fbf9f8",
                    "secondary-fixed-dim": "#78dc77",
                    "secondary-container": "#91f78e",
                    "on-background": "#1b1c1c",
                    "background": "#fbf9f8",
                    "surface-dim": "#dcd9d9",
                    "on-error": "#ffffff",
                    "inverse-primary": "#a7c8ff",
                    "inverse-on-surface": "#f3f0f0",
                    "on-tertiary-fixed-variant": "#930000",
                    "on-tertiary-fixed": "#410000",
                    "on-primary-container": "#799dd6",
                    "surface-container-high": "#eae8e7",
                    "on-tertiary": "#ffffff",
                    "outline-variant": "#c3c6d1",
                    "on-primary-fixed-variant": "#1f477b",
                    "error-container": "#ffdad6",
                    "surface-tint": "#3a5f94",
                    "on-secondary-container": "#00731e",
                    "on-secondary": "#ffffff",
                    "on-primary-fixed": "#001b3c",
                    "on-surface-variant": "#43474f",
                    "surface-variant": "#e4e2e1",
                    "tertiary-container": "#6e0000",
                    "surface-container-lowest": "#ffffff",
                    "on-error-container": "#93000a"
            },
            "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
            },
            "spacing": {
                    "gutter": "24px",
                    "margin-mobile": "16px",
                    "container-max-width": "1140px",
                    "unit": "4px",
                    "margin-desktop": "48px"
            },
            "fontFamily": {
                    "headline-md": ["Manrope"],
                    "label-md": ["Manrope"],
                    "headline-lg": ["Manrope"],
                    "headline-lg-mobile": ["Manrope"],
                    "body-md": ["Manrope"],
                    "label-sm": ["Manrope"],
                    "body-lg": ["Manrope"]
            },
            "fontSize": {
                    "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                    "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.01em", "fontWeight": "600"}],
                    "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
                    "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
                    "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}]
            }
          },
        },
      }
    </script>
<style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e4e2e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-background-light text-on-background min-h-screen">

<main class="max-w-container-max-width mx-auto px-6 py-8 pb-24">

<!-- Back Arrow -->
<div class="mb-4">
    <a href="plagiarism_page.php?assignment_id=<?php echo $assignment_id; ?>" class="flex items-center gap-2 text-primary hover:text-primary-container font-label-md">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        Back to Plagiarism Page
    </a>
</div>

<!-- Header -->
<div class="mb-8 flex flex-col md:flex-row justify-between items-end gap-4">
    <div>
        <h1 class="font-headline-lg text-headline-lg text-primary">Submission History</h1>
        <p class="text-body-md text-on-surface-variant mt-1">
            Assignment: <span class="font-bold"><?php echo htmlspecialchars($assignment['tittle']); ?></span>
        </p>
    </div>
</div>

<!-- Table -->
<div class="bg-surface rounded-xl border border-outline-variant shadow overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-surface-container-low border-b border-outline-variant">
<th class="px-6 py-4 text-on-surface-variant uppercase font-label-md text-label-md">Student Name</th>
<th class="px-6 py-4 text-on-surface-variant uppercase font-label-md text-label-md">File Name</th>
<th class="px-6 py-4 text-on-surface-variant uppercase font-label-md text-label-md text-center">Score</th>
<th class="px-6 py-4 text-on-surface-variant uppercase font-label-md text-label-md">Status</th>
<th class="px-6 py-4 text-on-surface-variant uppercase font-label-md text-label-md text-center">Final Grade</th>
<th class="px-6 py-4 text-on-surface-variant uppercase font-label-md text-label-md text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-outline-variant">
<?php if(empty($graded_submissions)): ?>
<tr><td colspan="6" class="text-center py-4 text-gray-500">No graded submissions yet.</td></tr>
<?php else: ?>
<?php foreach($graded_submissions as $r): ?>
<tr class="hover:bg-surface-container-low transition-colors group">
    <td class="px-6 py-4 font-bold text-on-surface"><?php echo htmlspecialchars($r['student_name']); ?></td>
    <td class="px-6 py-4 text-body-md text-on-surface-variant italic"><?php echo htmlspecialchars($r['file_name']); ?></td>
    <td class="px-6 py-4 text-center text-headline-md font-headline-md"><?php echo $r['score']; ?>%</td>
    <td class="px-6 py-4">
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-label-sm font-label-sm <?php
            echo ($r['score']>30)?'bg-error-container text-on-error-container':'bg-secondary-container text-on-secondary-container';
        ?>">
            <span class="material-symbols-outlined text-[14px] mr-1">check_circle</span>
            <?php echo ($r['score']>30)?'High Risk':'Verified'; ?>
        </span>
    </td>
    <td class="px-6 py-4 text-center font-bold text-headline-md"><?php echo $r['final_grade']; ?></td>
    <td class="px-6 py-4 text-right">
        <a href="plagiarism_view.php?assignment_id=<?php echo $assignment_id; ?>&student_id=<?php echo $r['student_id']; ?>" 
           class="bg-primary text-on-primary px-4 py-2 rounded-lg font-label-md text-label-md hover:brightness-110 whitespace-nowrap inline-flex items-center justify-center">
            View Report
        </a>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</main>
</body>
</html>