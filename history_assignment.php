<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student') {
    header("Location: index.php?error=login_required");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get filter values
$filter_class_id = isset($_GET['class_id']) && !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$filter_grade = isset($_GET['grade']) && !empty($_GET['grade']) ? $_GET['grade'] : null;
$filter_month = isset($_GET['month']) && !empty($_GET['month']) ? $_GET['month'] : null;
$filter_status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : 'all'; // all, submitted, not_submitted

// Fetch all courses the student is enrolled in for the filter dropdown
$courses_sql = "SELECT c.class_id, c.class_name, c.class_code 
                FROM class c 
                JOIN enrollment e ON c.class_id = e.class_id 
                WHERE e.student_id = ? 
                ORDER BY c.class_name";
$stmt_courses = $conn->prepare($courses_sql);
$stmt_courses->bind_param("i", $student_id);
$stmt_courses->execute();
$available_courses = $stmt_courses->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_courses->close();

// Get the student's lecturer for each class (to filter assignments)
$lecturer_sql = "SELECT class_id, lecturer_id FROM enrollment WHERE student_id = ?";
$lecturer_stmt = $conn->prepare($lecturer_sql);
$lecturer_stmt->bind_param("i", $student_id);
$lecturer_stmt->execute();
$student_lecturers = $lecturer_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$lecturer_stmt->close();

// Create a map of class_id -> lecturer_id
$class_lecturer_map = [];
foreach ($student_lecturers as $sl) {
    $class_lecturer_map[$sl['class_id']] = $sl['lecturer_id'];
}

// Pagination config
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page-1) * $limit;

// ==========================================
// BUILD THE MAIN QUERY - SHOW ONLY ASSIGNMENTS FROM STUDENT'S LECTURER
// ==========================================
// First, get all assignments from classes the student is enrolled in
// BUT only from the lecturer assigned to that student
$enrolled_classes_sql = "SELECT class_id FROM enrollment WHERE student_id = ?";
$stmt_classes = $conn->prepare($enrolled_classes_sql);
$stmt_classes->bind_param("i", $student_id);
$stmt_classes->execute();
$enrolled_classes = $stmt_classes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_classes->close();

$class_ids = array_column($enrolled_classes, 'class_id');

// Build the WHERE clause for class_id and lecturer_id
$where_clauses = [];
$params = [$student_id, $student_id]; // for submission checks
$types = "ii";

if (!empty($class_ids)) {
    $class_conditions = [];
    foreach ($class_ids as $cid) {
        $lecturer_id = $class_lecturer_map[$cid] ?? 0;
        $class_conditions[] = "(a.class_id = ? AND a.lecturer_id = ?)";
        $params[] = $cid;
        $params[] = $lecturer_id;
        $types .= "ii";
    }
    $where_clauses[] = "(" . implode(" OR ", $class_conditions) . ")";
}

// Add filters
if ($filter_class_id) {
    $where_clauses[] = "a.class_id = ?";
    $params[] = $filter_class_id;
    $types .= "i";
}

// Build the main query
$sql = "
    SELECT a.assignment_id, a.tittle, a.type, a.due_date, a.start_date, a.class_id,
           c.class_name, c.class_code,
           cs.code AS code_text, cs.file_name AS code_file, cs.submitted_at AS code_submitted_at, cs.final_grade AS code_final_grade,
           es.essay AS essay_text, es.file_name AS essay_file, es.submitted_at AS essay_submitted_at, es.final_grade AS essay_final_grade,
           CASE 
               WHEN a.type = 'code' AND cs.student_id IS NOT NULL THEN 'submitted'
               WHEN a.type = 'essay' AND es.student_id IS NOT NULL THEN 'submitted'
               ELSE 'not_submitted'
           END AS submission_status
    FROM assignment a
    JOIN class c ON a.class_id = c.class_id
    LEFT JOIN code_submission cs ON cs.assignment_id = a.assignment_id AND cs.student_id = ?
    LEFT JOIN essay_submission es ON es.assignment_id = a.assignment_id AND es.student_id = ?
    WHERE " . implode(" AND ", $where_clauses);

// Add status filter
if ($filter_status == 'submitted') {
    $sql .= " AND ((a.type = 'code' AND cs.student_id IS NOT NULL) OR (a.type = 'essay' AND es.student_id IS NOT NULL))";
} elseif ($filter_status == 'not_submitted') {
    $sql .= " AND ((a.type = 'code' AND cs.student_id IS NULL) OR (a.type = 'essay' AND es.student_id IS NULL))";
}

if ($filter_grade) {
    if ($filter_grade == 'not_graded') {
        $sql .= " AND ((a.type = 'code' AND cs.final_grade IS NULL) OR (a.type = 'essay' AND es.final_grade IS NULL))";
    } else {
        $sql .= " AND ((a.type = 'code' AND cs.final_grade = ?) OR (a.type = 'essay' AND es.final_grade = ?))";
        $params[] = $filter_grade;
        $params[] = $filter_grade;
        $types .= "ss";
    }
}

if ($filter_month) {
    $sql .= " AND (MONTH(cs.submitted_at) = ? OR MONTH(es.submitted_at) = ?)";
    $params[] = $filter_month;
    $params[] = $filter_month;
    $types .= "ii";
}

// Add ORDER BY and LIMIT
$sql .= " ORDER BY a.due_date DESC, a.assignment_id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// ==========================================
// COUNT TOTAL ASSIGNMENTS FOR PAGINATION
// ==========================================
$count_where_clauses = [];
$count_params = [$student_id, $student_id];
$count_types = "ii";

if (!empty($class_ids)) {
    $count_class_conditions = [];
    foreach ($class_ids as $cid) {
        $lecturer_id = $class_lecturer_map[$cid] ?? 0;
        $count_class_conditions[] = "(a.class_id = ? AND a.lecturer_id = ?)";
        $count_params[] = $cid;
        $count_params[] = $lecturer_id;
        $count_types .= "ii";
    }
    $count_where_clauses[] = "(" . implode(" OR ", $count_class_conditions) . ")";
}

if ($filter_class_id) {
    $count_where_clauses[] = "a.class_id = ?";
    $count_params[] = $filter_class_id;
    $count_types .= "i";
}

$count_sql = "
    SELECT COUNT(*) as total
    FROM assignment a
    LEFT JOIN code_submission cs ON cs.assignment_id = a.assignment_id AND cs.student_id = ?
    LEFT JOIN essay_submission es ON es.assignment_id = a.assignment_id AND es.student_id = ?
    WHERE " . implode(" AND ", $count_where_clauses);

if ($filter_status == 'submitted') {
    $count_sql .= " AND ((a.type = 'code' AND cs.student_id IS NOT NULL) OR (a.type = 'essay' AND es.student_id IS NOT NULL))";
} elseif ($filter_status == 'not_submitted') {
    $count_sql .= " AND ((a.type = 'code' AND cs.student_id IS NULL) OR (a.type = 'essay' AND es.student_id IS NULL))";
}

if ($filter_grade) {
    if ($filter_grade == 'not_graded') {
        $count_sql .= " AND ((a.type = 'code' AND cs.final_grade IS NULL) OR (a.type = 'essay' AND es.final_grade IS NULL))";
    } else {
        $count_sql .= " AND ((a.type = 'code' AND cs.final_grade = ?) OR (a.type = 'essay' AND es.final_grade = ?))";
        $count_params[] = $filter_grade;
        $count_params[] = $filter_grade;
        $count_types .= "ss";
    }
}

if ($filter_month) {
    $count_sql .= " AND (MONTH(cs.submitted_at) = ? OR MONTH(es.submitted_at) = ?)";
    $count_params[] = $filter_month;
    $count_params[] = $filter_month;
    $count_types .= "ii";
}

$stmt_count = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $stmt_count->bind_param($count_types, ...$count_params);
}
$stmt_count->execute();
$total_assignments = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_assignments / $limit);

// Execute main query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get counts for stats
$submitted_count_sql = "
    SELECT COUNT(*) as count
    FROM assignment a
    LEFT JOIN code_submission cs ON cs.assignment_id = a.assignment_id AND cs.student_id = ?
    LEFT JOIN essay_submission es ON es.assignment_id = a.assignment_id AND es.student_id = ?
    WHERE " . implode(" AND ", $count_where_clauses) . "
    AND ((a.type = 'code' AND cs.student_id IS NOT NULL) OR (a.type = 'essay' AND es.student_id IS NOT NULL))
";
$submitted_count_stmt = $conn->prepare($submitted_count_sql);
// Rebuild params for submitted count
$submitted_count_params = [$student_id, $student_id];
$submitted_count_types = "ii";
foreach ($class_ids as $cid) {
    $lecturer_id = $class_lecturer_map[$cid] ?? 0;
    $submitted_count_params[] = $cid;
    $submitted_count_params[] = $lecturer_id;
    $submitted_count_types .= "ii";
}
$submitted_count_stmt->bind_param($submitted_count_types, ...$submitted_count_params);
$submitted_count_stmt->execute();
$submitted_count = $submitted_count_stmt->get_result()->fetch_assoc()['count'];
$submitted_count_stmt->close();

$not_submitted_count = $total_assignments - $submitted_count;
?>

<!DOCTYPE html><html class="light" lang="en" style=""><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Assignment History - SILS</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "outline-variant": "#c3c6d1",
                    "on-tertiary-fixed": "#410000",
                    "secondary-fixed-dim": "#78dc77",
                    "on-primary": "#ffffff",
                    "secondary-fixed": "#94f990",
                    "on-background": "#1b1c1c",
                    "on-secondary-fixed": "#002204",
                    "primary": "#001e40",
                    "on-secondary": "#ffffff",
                    "surface-tint": "#3a5f94",
                    "on-primary-fixed": "#001b3c",
                    "on-secondary-container": "#00731e",
                    "surface-variant": "#e4e2e1",
                    "background": "#fbf9f8",
                    "secondary": "#006e1c",
                    "on-surface": "#1b1c1c",
                    "inverse-primary": "#a7c8ff",
                    "tertiary-fixed-dim": "#ffb4a8",
                    "on-tertiary": "#ffffff",
                    "surface": "#fbf9f8",
                    "tertiary-fixed": "#ffdad4",
                    "surface-container-low": "#f6f3f2",
                    "tertiary-container": "#6e0000",
                    "on-surface-variant": "#43474f",
                    "inverse-surface": "#303030",
                    "tertiary": "#460000",
                    "on-error-container": "#93000a",
                    "secondary-container": "#91f78e",
                    "surface-container-highest": "#e4e2e1",
                    "primary-container": "#003366",
                    "on-error": "#ffffff",
                    "inverse-on-surface": "#f3f0f0",
                    "error-container": "#ffdad6",
                    "on-tertiary-container": "#ff6d59",
                    "surface-container-high": "#eae8e7",
                    "on-primary-fixed-variant": "#1f477b",
                    "surface-dim": "#dcd9d9",
                    "surface-container-lowest": "#ffffff",
                    "primary-fixed-dim": "#a7c8ff",
                    "on-tertiary-fixed-variant": "#930000",
                    "outline": "#737780",
                    "on-primary-container": "#799dd6",
                    "error": "#ba1a1a",
                    "primary-fixed": "#d5e3ff",
                    "on-secondary-fixed-variant": "#005313",
                    "surface-container": "#f0eded",
                    "surface-bright": "#fbf9f8"
            },
            "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
            },
            "spacing": {
                    "margin-desktop": "48px",
                    "margin-mobile": "16px",
                    "container-max-width": "1140px",
                    "gutter": "24px",
                    "unit": "4px"
            },
            "fontFamily": {
                    "label-sm": ["Manrope"],
                    "label-md": ["Manrope"],
                    "headline-lg-mobile": ["Manrope"],
                    "body-md": ["Manrope"],
                    "body-lg": ["Manrope"],
                    "headline-md": ["Manrope"],
                    "headline-lg": ["Manrope"]
            },
            "fontSize": {
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
                    "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.01em", "fontWeight": "600"}],
                    "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
                    "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                    "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                    "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.02em", "fontWeight": "700"}]
            }
          }
        }
      }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .header {
    background: #003366;
    color: white;
    padding: 20px;
    text-align: center;
    font-family: 'Poppins', sans-serif;
    font-size: 24px;
    font-weight: 600;
    border-radius: 0;
    margin-bottom: 0px;
}
        .status-submitted {
            background: #dcfce7;
            color: #166534;
        }
        .status-not-submitted {
            background: #fef3c7;
            color: #92400e;
        }
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-verified {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-grade {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-grade-a { background: #16a34a; color: white; }
        .badge-grade-b { background: #22c55e; color: white; }
        .badge-grade-c { background: #eab308; color: white; }
        .badge-grade-d { background: #f59e0b; color: white; }
        .badge-grade-f { background: #dc2626; color: white; }
        .badge-grade-na { background: #e2e8f0; color: #64748b; }
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-surface text-on-surface font-body-md min-h-screen">

<div class="header">📋 Assignment History</div>

<main class="flex-1 p-gutter md:p-12 overflow-x-hidden">
<div class="max-w-container-max-width mx-auto flex flex-col h-full">

<div class="mb-8 flex justify-between items-center flex-wrap gap-4">
  <div class="flex flex-col">
    <h1 class="font-headline-lg text-headline-lg text-primary">My Assignments</h1>
    <p class="text-on-surface-variant font-body-md">View all assignments from your lecturers</p>
  </div>
  <a href="nopending_assignment.php" class="inline-flex items-center gap-2 text-primary font-semibold hover:underline text-label-md px-4 py-2 border border-primary rounded-lg hover:bg-primary hover:text-on-primary transition-all">
    <span class="material-symbols-outlined text-lg">arrow_back</span>
    Back to Assignments
  </a>
</div>

<!-- Stats Summary -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 border border-outline-variant text-center">
        <p class="text-2xl font-bold text-primary"><?php echo $total_assignments; ?></p>
        <p class="text-sm text-on-surface-variant">Total Assignments</p>
    </div>
    <div class="bg-white rounded-xl p-4 border border-outline-variant text-center">
        <p class="text-2xl font-bold text-green-600"><?php echo $submitted_count; ?></p>
        <p class="text-sm text-on-surface-variant">Submitted</p>
    </div>
    <div class="bg-white rounded-xl p-4 border border-outline-variant text-center">
        <p class="text-2xl font-bold text-orange-600"><?php echo $not_submitted_count; ?></p>
        <p class="text-sm text-on-surface-variant">Not Submitted Yet</p>
    </div>
</div>

<div class="mb-6 p-5 bg-surface-container-low rounded-xl border border-outline-variant">
  <form method="GET" action="" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="block text-label-sm font-bold text-on-surface-variant mb-2">Filter by Subject</label>
        <select name="class_id" class="w-full px-4 py-2 rounded-lg border border-outline-variant bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">All Subjects</option>
          <?php foreach($available_courses as $course): ?>
          <option value="<?php echo $course['class_id']; ?>" <?php echo ($filter_class_id == $course['class_id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($course['class_name']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-label-sm font-bold text-on-surface-variant mb-2">Submission Status</label>
        <select name="status" class="w-full px-4 py-2 rounded-lg border border-outline-variant bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>All</option>
          <option value="submitted" <?php echo ($filter_status == 'submitted') ? 'selected' : ''; ?>>✅ Submitted</option>
          <option value="not_submitted" <?php echo ($filter_status == 'not_submitted') ? 'selected' : ''; ?>>⏳ Not Submitted</option>
        </select>
      </div>

      <div>
        <label class="block text-label-sm font-bold text-on-surface-variant mb-2">Filter by Grade</label>
        <select name="grade" class="w-full px-4 py-2 rounded-lg border border-outline-variant bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">All Grades</option>
          <option value="A" <?php echo ($filter_grade == 'A') ? 'selected' : ''; ?>>A (Excellent)</option>
          <option value="B" <?php echo ($filter_grade == 'B') ? 'selected' : ''; ?>>B (Good)</option>
          <option value="C" <?php echo ($filter_grade == 'C') ? 'selected' : ''; ?>>C (Satisfactory)</option>
          <option value="D" <?php echo ($filter_grade == 'D') ? 'selected' : ''; ?>>D (Pass)</option>
          <option value="F" <?php echo ($filter_grade == 'F') ? 'selected' : ''; ?>>F (Fail)</option>
          <option value="not_graded" <?php echo ($filter_grade == 'not_graded') ? 'selected' : ''; ?>>Not Graded Yet</option>
        </select>
      </div>

      <div>
        <label class="block text-label-sm font-bold text-on-surface-variant mb-2">Submission Month</label>
        <select name="month" class="w-full px-4 py-2 rounded-lg border border-outline-variant bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">All Months</option>
          <?php for($m=1; $m<=12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo ($filter_month == $m) ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>

    <div class="flex gap-3 pt-2 flex-wrap">
      <button type="submit" class="px-6 py-2 bg-primary text-on-primary rounded-lg font-bold text-label-md hover:bg-primary-container transition-all flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">filter_alt</span>
        Apply Filters
      </button>
      <?php if($filter_class_id || $filter_grade || $filter_month || $filter_status != 'all'): ?>
      <a href="?" class="px-6 py-2 border border-outline-variant text-on-surface-variant rounded-lg font-bold text-label-md hover:bg-surface-container-highest transition-all flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">clear</span>
        Clear All
      </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if(empty($assignments)): ?>
<div class="flex-1 flex flex-col items-center justify-center -mt-16">
  <span class="material-symbols-outlined text-6xl text-on-surface-variant mb-4">inbox</span>
  <h3 class="font-headline-md text-headline-md text-on-surface mb-2">No assignments found</h3>
  <p class="text-on-surface-variant">You have no assignments from your lecturers yet.</p>
</div>
<?php else: ?>

<div class="bg-white rounded-xl border border-surface-variant shadow overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-left border-collapse submission-table">
      <thead>
        <tr class="bg-surface-container-low text-on-surface-variant font-label-md text-label-md">
          <th class="py-4 px-6 font-semibold">Assignment Title</th>
          <th class="py-4 px-6 font-semibold">Subject</th>
          <th class="py-4 px-6 font-semibold">Due Date</th>
          <th class="py-4 px-6 font-semibold text-center">Status</th>
          <th class="py-4 px-6 font-semibold text-center">Grade</th>
          <th class="py-4 px-6 font-semibold text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-surface-variant">
        <?php foreach($assignments as $sub): 
            $is_submitted = $sub['submission_status'] == 'submitted';
            $final_grade = null;
            $submitted_at = null;
            
            if($sub['type'] === 'code' && $sub['code_text']){
                $final_grade = $sub['code_final_grade'];
                $submitted_at = $sub['code_submitted_at'];
            } elseif($sub['type'] === 'essay' && $sub['essay_text']){
                $final_grade = $sub['essay_final_grade'];
                $submitted_at = $sub['essay_submitted_at'];
            }
            
            $is_overdue = strtotime($sub['due_date']) < time() && !$is_submitted;
            
            if($is_submitted && $final_grade) {
                $status_class = 'status-verified';
                $status_text = '✅ Graded';
            } elseif($is_submitted && !$final_grade) {
                $status_class = 'status-submitted';
                $status_text = '📤 Submitted';
            } elseif($is_overdue) {
                $status_class = 'status-overdue';
                $status_text = '⚠️ Overdue';
            } else {
                $status_class = 'status-not-submitted';
                $status_text = '⏳ Not Submitted';
            }
            
            $grade_class = $final_grade ? 'badge-grade-' . strtolower($final_grade) : 'badge-grade-na';
            $grade_text = $final_grade ?? '—';
            
            $submitted_date = $submitted_at ? date('d M, Y, h:i A', strtotime($submitted_at)) : 'Not submitted';
        ?>
        <tr class="hover:bg-surface-container-lowest transition-colors">
          <td class="py-4 px-6">
            <span class="font-label-md text-label-md text-primary"><?php echo htmlspecialchars($sub['tittle']); ?></span>
          </td>
          <td class="py-4 px-6 text-on-surface font-body-md">
            <span class="text-sm"><?php echo htmlspecialchars($sub['class_code']); ?></span>
          </td>
          <td class="py-4 px-6 text-on-surface font-body-md">
            <?php echo date('d M Y', strtotime($sub['due_date'])); ?>
          </td>
          <td class="py-4 px-6 text-center">
            <span class="px-3 py-1 rounded-full text-label-sm <?php echo $status_class; ?>">
                <?php echo $status_text; ?>
            </span>
            <?php if($is_submitted): ?>
            <br><span class="text-xs text-gray-400">Submitted: <?php echo $submitted_date; ?></span>
            <?php endif; ?>
          </td>
          <td class="py-4 px-6 text-center font-bold">
            <span class="badge-grade <?php echo $grade_class; ?>">
                <?php echo $grade_text; ?>
            </span>
          </td>
          <td class="py-4 px-6 text-right">
            <?php if($is_submitted): ?>
            <a href="submission_status.php?assignment_id=<?php echo $sub['assignment_id']; ?>" 
               class="inline-flex items-center gap-1 bg-primary text-on-primary hover:bg-primary-container px-4 py-2 rounded-lg text-label-sm font-bold shadow-sm transition-all active:scale-95">
                <span>View Status</span>
                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
            </a>
            <?php elseif($is_overdue): ?>
            <span class="inline-flex items-center gap-1 bg-gray-300 text-gray-500 px-4 py-2 rounded-lg text-label-sm font-bold cursor-not-allowed">
                <span class="material-symbols-outlined text-[16px]">lock</span>
                Closed
            </span>
            <?php else: ?>
            <a href="submit_assignment.php?assignment_id=<?php echo $sub['assignment_id']; ?>" 
               class="inline-flex items-center gap-1 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-label-sm font-bold shadow-sm transition-all active:scale-95">
                <span>Submit Now</span>
                <span class="material-symbols-outlined text-[16px]">upload</span>
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="px-6 py-4 border-t border-surface-variant bg-surface-container-lowest flex items-center justify-between flex-wrap gap-4">
    <span class="font-label-sm text-label-sm text-on-surface-variant">
      Showing <?php echo ($offset+1); ?> to <?php echo min($offset+$limit,$total_assignments); ?> of <?php echo $total_assignments; ?> assignments
    </span>
    <div class="flex items-center gap-2">
      <a href="?page=<?php echo max(1,$page-1); ?><?php echo $filter_class_id ? '&class_id='.$filter_class_id : ''; ?><?php echo $filter_status != 'all' ? '&status='.$filter_status : ''; ?><?php echo $filter_grade ? '&grade='.$filter_grade : ''; ?><?php echo $filter_month ? '&month='.$filter_month : ''; ?>" class="p-2 rounded-lg border border-surface-variant hover:bg-surface-container-low <?php if($page<=1) echo 'opacity-50 cursor-not-allowed'; ?>">
        <span class="material-symbols-outlined">chevron_left</span>
      </a>
      <?php for($i=1;$i<=$total_pages;$i++): ?>
      <a href="?page=<?php echo $i; ?><?php echo $filter_class_id ? '&class_id='.$filter_class_id : ''; ?><?php echo $filter_status != 'all' ? '&status='.$filter_status : ''; ?><?php echo $filter_grade ? '&grade='.$filter_grade : ''; ?><?php echo $filter_month ? '&month='.$filter_month : ''; ?>" class="px-4 py-2 rounded-lg <?php echo $i==$page ? 'bg-primary text-on-primary' : 'hover:bg-surface-container-low'; ?> font-label-md text-label-md"><?php echo $i; ?></a>
      <?php endfor; ?>
      <a href="?page=<?php echo min($total_pages,$page+1); ?><?php echo $filter_class_id ? '&class_id='.$filter_class_id : ''; ?><?php echo $filter_status != 'all' ? '&status='.$filter_status : ''; ?><?php echo $filter_grade ? '&grade='.$filter_grade : ''; ?><?php echo $filter_month ? '&month='.$filter_month : ''; ?>" class="p-2 rounded-lg border border-surface-variant hover:bg-surface-container-low <?php if($page>=$total_pages) echo 'opacity-50 cursor-not-allowed'; ?>">
        <span class="material-symbols-outlined">chevron_right</span>
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

</main>
</body>
</html>