<?php
session_start();
include 'includes/config.php';
include 'includes/plagiarism_functions.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$highlight_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
// Check if the highlighted student has submitted
$student_submitted = false;
$student_name = '';
$student_matric = '';
if ($highlight_student_id > 0) {
    $check_sql = "SELECT s.full_name, s.matric_no, 
                   (SELECT COUNT(*) FROM essay_submission WHERE assignment_id = ? AND student_id = ?) as essay_submitted,
                   (SELECT COUNT(*) FROM code_submission WHERE assignment_id = ? AND student_id = ?) as code_submitted
                   FROM student s
                   WHERE s.student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iiiii", $assignment_id, $highlight_student_id, $assignment_id, $highlight_student_id, $highlight_student_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $student_name = $row['full_name'];
        $student_matric = $row['matric_no'];
        $student_submitted = ($row['essay_submitted'] > 0 || $row['code_submitted'] > 0);
    }
    $check_stmt->close();
}
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'similarity_desc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

if (!$assignment_id) die("Assignment not selected.");

$staff_id = $_SESSION['staff_id'];

// Get lecturer_id from staff_id
$stmt_lect = $conn->prepare("SELECT lecturer_id FROM lecturer WHERE staff_id = ?");
$stmt_lect->bind_param("s", $staff_id);
$stmt_lect->execute();
$result_lect = $stmt_lect->get_result();
if($row = $result_lect->fetch_assoc()){
    $lecturer_id = $row['lecturer_id'];
} else {
    die("Lecturer not found.");
}
$stmt_lect->close();

// ==========================================
// FETCH ASSIGNMENT INFO
// ==========================================
$stmt = $conn->prepare("SELECT tittle, type, reference_file, class_id FROM assignment WHERE assignment_id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$assignment) die("Assignment not found.");

$submission_type = $assignment['type'];
$class_id = $assignment['class_id'];

// ==========================================
// CHECK LECTURER ROLE & ACCESS
// ==========================================
$role_query = "SELECT role FROM course_lecturer WHERE class_id = ? AND lecturer_id = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("ii", $class_id, $lecturer_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$role_data = $role_result->fetch_assoc();
$role_stmt->close();

if(!$role_data){
    die("You are not assigned to this class.");
}

$is_penyelaras = ($role_data['role'] == 'penyelaras');

// ==========================================
// HANDLE GRADE SUBMISSION - Redirect to plagiarism_page.php
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approved_grade'], $_POST['student_id'])) {
    $student_id = (int)$_POST['student_id'];
    $approved_grade = $_POST['approved_grade'];

    $table = ($submission_type == 'essay') ? 'essay_submission' : 'code_submission';
    $grade_map = ['A' => 90, 'B' => 80, 'C' => 70, 'D' => 60, 'F' => 0];
    $score = $grade_map[$approved_grade] ?? 0;

    $stmt = $conn->prepare("UPDATE $table SET final_grade=?, total_score=? WHERE assignment_id=? AND student_id=?");
    $stmt->bind_param("sdii", $approved_grade, $score, $assignment_id, $student_id);
    $stmt->execute();
    $stmt->close();

    // Redirect back to plagiarism_page.php
    header("Location: plagiarism_page.php?assignment_id=$assignment_id&grade_submitted=1");
    exit();
}

// ==========================================
// FETCH SUBMISSIONS (FILTERED BY GROUP FOR PENSYARAH)
// ==========================================
if ($is_penyelaras) {
    // Penyelaras: see ALL submissions
    if ($submission_type == 'code') {
        $sql = "SELECT s.student_id, s.full_name, s.matric_no, cs.code AS submission_text, 
                       cs.file_name, cs.final_grade, e.lecturer_id, l.full_name AS lecturer_name,
                       cs.id AS submission_id
                FROM student s
                JOIN code_submission cs ON s.student_id = cs.student_id
                JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ?
                JOIN lecturer l ON e.lecturer_id = l.lecturer_id
                WHERE cs.assignment_id = ? AND cs.code <> ''";
    } else {
        $sql = "SELECT s.student_id, s.full_name, s.matric_no, es.essay AS submission_text, 
                       es.file_name, es.final_grade, e.lecturer_id, l.full_name AS lecturer_name,
                       es.id AS submission_id
                FROM student s
                JOIN essay_submission es ON s.student_id = es.student_id
                JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ?
                JOIN lecturer l ON e.lecturer_id = l.lecturer_id
                WHERE es.assignment_id = ? AND es.essay <> ''";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $class_id, $assignment_id);
} else {
    // Pensyarah: see ONLY their group's submissions
    if ($submission_type == 'code') {
        $sql = "SELECT s.student_id, s.full_name, s.matric_no, cs.code AS submission_text, 
                       cs.file_name, cs.final_grade, e.lecturer_id, l.full_name AS lecturer_name,
                       cs.id AS submission_id
                FROM student s
                JOIN code_submission cs ON s.student_id = cs.student_id
                JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ? AND e.lecturer_id = ?
                JOIN lecturer l ON e.lecturer_id = l.lecturer_id
                WHERE cs.assignment_id = ? AND cs.code <> ''";
    } else {
        $sql = "SELECT s.student_id, s.full_name, s.matric_no, es.essay AS submission_text, 
                       es.file_name, es.final_grade, e.lecturer_id, l.full_name AS lecturer_name,
                       es.id AS submission_id
                FROM student s
                JOIN essay_submission es ON s.student_id = es.student_id
                JOIN enrollment e ON s.student_id = e.student_id AND e.class_id = ? AND e.lecturer_id = ?
                JOIN lecturer l ON e.lecturer_id = l.lecturer_id
                WHERE es.assignment_id = ? AND es.essay <> ''";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $class_id, $lecturer_id, $assignment_id);
}
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ==========================================
// CALCULATE SIMILARITY AND GROUP MATCHES
// ==========================================
$similarity_matrix = [];
$student_ids = array_column($submissions, 'student_id');

// Calculate all pair similarities
foreach ($submissions as $i => $sub_i) {
    $similarity_matrix[$sub_i['student_id']] = [];
    foreach ($submissions as $j => $sub_j) {
        if ($sub_i['student_id'] === $sub_j['student_id']) continue;
        
        if ($submission_type == 'code') {
            $sim = calculateCodeSimilarity($sub_i['submission_text'], $sub_j['submission_text'], '');
        } else {
            $sim = calculateEssaySimilarity($sub_i['submission_text'], $sub_j['submission_text'], '');
        }
        
        $similarity_matrix[$sub_i['student_id']][$sub_j['student_id']] = $sim;
    }
}

// Build similarity results with all matches
$similarity_results = [];
foreach ($submissions as $sub) {
    $student_id = $sub['student_id'];
    $all_matches = [];
    $max_sim = 0;
    $best_match_text = '';
    $best_match_student = null;
    
    foreach ($submissions as $other) {
        if ($student_id === $other['student_id']) continue;
        $sim = $similarity_matrix[$student_id][$other['student_id']] ?? 0;
        
        if ($sim > 15) { // Only consider matches above 15%
            $all_matches[] = [
                'student_id' => $other['student_id'],
                'student_name' => $other['full_name'],
                'matric_no' => $other['matric_no'],
                'similarity' => $sim,
                'text' => $other['submission_text']
            ];
        }
        
        if ($sim > $max_sim) {
            $max_sim = $sim;
            $best_match_text = $other['submission_text'];
            $best_match_student = $other;
        }
    }
    
    // Sort matches by similarity descending
    usort($all_matches, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });
    
    $risk = getRiskLevel($max_sim, $submission_type);
    $grade = getRecommendedGrade($max_sim, $submission_type);
    $final_grade = !empty($sub['final_grade']) ? $sub['final_grade'] : $grade;

    $similarity_results[] = [
        'student_name' => $sub['full_name'],
        'student_id' => $sub['student_id'],
        'matric_no' => $sub['matric_no'],
        'submission_text' => $sub['submission_text'],
        'file_name' => $sub['file_name'],
        'lecturer_name' => $sub['lecturer_name'] ?? null,
        'status' => $risk,
        'score' => round($max_sim, 2),
        'grade' => $grade,
        'final_grade' => $final_grade,
        'all_matches' => $all_matches,
        'best_match_text' => $best_match_text,
        'best_match_student' => $best_match_student,
        'submission_id' => $sub['submission_id'] ?? 0
    ];
}

// ==========================================
// FILTER RESULTS
// ==========================================
if ($filter_status != 'all') {
    $similarity_results = array_filter($similarity_results, function($r) use ($filter_status) {
        return $r['status'] == $filter_status;
    });
}

// ==========================================
// SORT RESULTS
// ==========================================
usort($similarity_results, function($a, $b) use ($sort_by) {
    switch($sort_by) {
        case 'similarity_asc':
            return $a['score'] <=> $b['score'];
        case 'similarity_desc':
        default:
            return $b['score'] <=> $a['score'];
        case 'name_asc':
            return strcmp($a['student_name'], $b['student_name']);
        case 'name_desc':
            return strcmp($b['student_name'], $a['student_name']);
    }
});

// ==========================================
// PAGINATION
// ==========================================
$total_results = count($similarity_results);
$total_pages = ceil($total_results / $limit);
$paginated_results = array_slice($similarity_results, $offset, $limit);

// ==========================================
// FUNCTION: Highlight Matching Shingles
// ==========================================
function highlightMatchingShingles($text1, $text2, $shingleSize = 4) {
    if (empty(trim($text1))) return '';
    
    $clean1 = strtolower($text1);
    $clean2 = strtolower($text2);
    $clean1 = preg_replace('/[^\w\s]/', ' ', $clean1);
    $clean2 = preg_replace('/[^\w\s]/', ' ', $clean2);
    $clean1 = preg_replace('/\s+/', ' ', $clean1);
    $clean2 = preg_replace('/\s+/', ' ', $clean2);
    
    $words1 = explode(' ', trim($clean1));
    $words2 = explode(' ', trim($clean2));
    $originalWords = preg_split('/\s+/', $text1);
    $originalWordCount = count($originalWords);
    
    if (count($words1) < $shingleSize || count($words2) < $shingleSize) {
        return nl2br(htmlspecialchars($text1));
    }
    
    $shingles2 = [];
    for ($i = 0; $i <= count($words2) - $shingleSize; $i++) {
        $shingle = implode(' ', array_slice($words2, $i, $shingleSize));
        $shingles2[$shingle] = true;
    }
    
    $matches = [];
    for ($i = 0; $i <= count($words1) - $shingleSize; $i++) {
        $shingle = implode(' ', array_slice($words1, $i, $shingleSize));
        if (isset($shingles2[$shingle])) {
            $matches[] = ['start_word' => $i, 'end_word' => $i + $shingleSize - 1];
        }
    }
    
    // Merge overlapping matches
    $mergedMatches = [];
    foreach ($matches as $match) {
        if (empty($mergedMatches)) {
            $mergedMatches[] = $match;
        } else {
            $last = &$mergedMatches[count($mergedMatches) - 1];
            if ($match['start_word'] <= $last['end_word'] + 1) {
                $last['end_word'] = max($last['end_word'], $match['end_word']);
            } else {
                $mergedMatches[] = $match;
            }
        }
    }
    
    if (empty($mergedMatches)) {
        return nl2br(htmlspecialchars($text1));
    }
    
    $result = [];
    $lastIndex = 0;
    
    foreach ($mergedMatches as $match) {
        if ($lastIndex < $match['start_word']) {
            $beforeWords = [];
            for ($i = $lastIndex; $i < $match['start_word'] && $i < $originalWordCount; $i++) {
                $beforeWords[] = htmlspecialchars($originalWords[$i]);
            }
            if (!empty($beforeWords)) $result[] = implode(' ', $beforeWords);
        }
        
        $highlightedChunk = [];
        for ($i = $match['start_word']; $i <= $match['end_word'] && $i < $originalWordCount; $i++) {
            $highlightedChunk[] = htmlspecialchars($originalWords[$i]);
        }
        if (!empty($highlightedChunk)) {
            $result[] = '<span class="similarity-highlight" title="Matches another submission">' . implode(' ', $highlightedChunk) . '</span>';
        }
        $lastIndex = $match['end_word'] + 1;
    }
    
    if ($lastIndex < $originalWordCount) {
        $afterWords = [];
        for ($i = $lastIndex; $i < $originalWordCount; $i++) {
            $afterWords[] = htmlspecialchars($originalWords[$i]);
        }
        if (!empty($afterWords)) $result[] = implode(' ', $afterWords);
    }
    
    return nl2br(implode(' ', $result));
}

// ==========================================
// PREPARE HIGHLIGHTED VERSIONS
// ==========================================
foreach ($paginated_results as &$result) {
    $shingleSize = ($submission_type == 'code') ? 3 : 4;
    $result['highlighted_text'] = nl2br(htmlspecialchars($result['submission_text']));
    
    // Highlight with best match if similarity > 15%
    if ($result['score'] > 15 && !empty($result['best_match_text'])) {
        $result['highlighted_text'] = highlightMatchingShingles(
            $result['submission_text'],
            $result['best_match_text'],
            $shingleSize
        );
    }
}

// Sort to put highlighted student first if selected
if ($highlight_student_id) {
    usort($paginated_results, function($a, $b) use ($highlight_student_id) {
        if ($a['student_id'] == $highlight_student_id) return -1;
        if ($b['student_id'] == $highlight_student_id) return 1;
        return 0;
    });
}

// Statistics
$total_students = count($submissions);
$high_risk = count(array_filter($similarity_results, fn($r) => $r['status'] == "High Risk"));
$attention_required = count(array_filter($similarity_results, fn($r) => $r['status'] == "Attention Required"));
$clean_pass = count(array_filter($similarity_results, fn($r) => $r['status'] == "Verified"));
$average_similarity = $total_students ? round(array_sum(array_column($similarity_results, 'score')) / $total_students, 2) : 0;
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Plagiarism Report - SILS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Manrope', sans-serif; background: #f0f2f5; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        
        .similarity-highlight {
            background-color: #ffe0e0 !important;
            border-bottom: 2px solid #ff9999 !important;
            color: #990000 !important;
            padding: 2px 4px !important;
            border-radius: 4px !important;
            cursor: help !important;
            display: inline-block !important;
        }
        .similarity-highlight:hover {
            background-color: #ffb3b3 !important;
            border-bottom-color: #ff0000 !important;
        }
        
        .text-panel {
            max-height: 500px;
            overflow-y: auto;
            line-height: 1.7;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
            background: white;
            border-radius: 12px;
        }
        .text-panel::-webkit-scrollbar { width: 8px; }
        .text-panel::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .text-panel::-webkit-scrollbar-thumb { background: #003366; border-radius: 4px; }
        
        .risk-high { background-color: #fee2e2; color: #991b1b; }
        .risk-med { background-color: #fed7aa; color: #92400e; }
        .risk-low { background-color: #dcfce7; color: #166534; }
        
        .matched-badge {
            background-color: #e0e7ff;
            color: #1e40af;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
        }
        
        .plagiarism-card {
            transition: all 0.3s ease;
        }
        .plagiarism-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px -10px rgba(0,0,0,0.15);
        }
        
        .badge-role { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .badge-role.penyelaras { background: #003366; color: white; }
        .badge-role.pensyarah { background: #e3f2fd; color: #1565c0; }
        
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        
        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 14px;
            border: 1.5px solid #e1e5ee;
            border-radius: 7px;
            text-decoration: none;
            color: #003366;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .pagination a:hover {
            background: #003366;
            color: white;
            border-color: #003366;
        }
        .pagination .active {
            background: #003366;
            color: white;
            border-color: #003366;
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .filter-select {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            font-size: 12px;
            cursor: pointer;
            outline: none;
        }
        .filter-select:focus {
            border-color: #003366;
            box-shadow: 0 0 0 3px rgba(0,51,102,0.1);
        }
        
        .match-item {
            background: #f8fafc;
            border-radius: 6px;
            padding: 4px 10px;
            margin: 2px 0;
            font-size: 12px;
        }
        
        .match-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 500;
            color: #991b1b;
            margin: 2px;
        }
        .match-badge .sim {
            color: #dc2626;
            font-weight: 700;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .btn-hover {
            transition: all 0.2s ease;
        }
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,51,102,0.2);
        }
        
        .toggle-btn {
            transition: all 0.3s ease;
        }
        .toggle-btn:hover {
            transform: translateY(-2px);
        }
        .toggle-btn.active {
            background-color: #1e40af;
            color: white;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }
        .toggle-btn.inactive {
            background-color: white;
            color: #1e40af;
            border: 2px solid #1e40af;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<main class="max-w-7xl mx-auto px-6 py-8 pb-24">

<!-- Back Button -->
<div class="mb-4">
    <a href="plagiarism_page.php?assignment_id=<?php echo $assignment_id; ?>" 
       class="inline-flex items-center gap-2 text-blue-800 hover:text-blue-600 font-medium transition-all hover:translate-x-[-2px]">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        Back to Overview
    </a>
</div>

<!-- Header -->
<div class="mb-6">
    <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-2xl font-bold text-blue-900">Plagiarism Analysis Report</h1>
        <?php if($is_penyelaras): ?>
            <span class="badge-role penyelaras">📌 Penyelaras</span>
        <?php else: ?>
            <span class="badge-role pensyarah">👨‍🏫 Pensyarah</span>
        <?php endif; ?>
    </div>
    <p class="text-gray-600 text-sm mt-1">Assignment: <span class="font-semibold"><?php echo htmlspecialchars($assignment['tittle']); ?></span></p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
    <div class="stat-card bg-white rounded-xl p-3 border border-gray-200 shadow-sm animate-in" style="animation-delay: 0.05s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] text-gray-500 uppercase tracking-wide">Total Students</p>
                <p class="text-xl font-bold text-blue-900"><?php echo $total_students; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl text-blue-300">people</span>
        </div>
    </div>
    <div class="stat-card bg-red-50 rounded-xl p-3 border border-red-200 shadow-sm animate-in" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] text-red-700 uppercase tracking-wide">High Risk</p>
                <p class="text-xl font-bold text-red-700"><?php echo $high_risk; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl text-red-400">warning</span>
        </div>
    </div>
    <div class="stat-card bg-orange-50 rounded-xl p-3 border border-orange-200 shadow-sm animate-in" style="animation-delay: 0.15s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] text-orange-700 uppercase tracking-wide">Attention</p>
                <p class="text-xl font-bold text-orange-700"><?php echo $attention_required; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl text-orange-400">priority_high</span>
        </div>
    </div>
    <div class="stat-card bg-green-50 rounded-xl p-3 border border-green-200 shadow-sm animate-in" style="animation-delay: 0.2s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] text-green-700 uppercase tracking-wide">Verified</p>
                <p class="text-xl font-bold text-green-700"><?php echo $clean_pass; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl text-green-400">verified</span>
        </div>
    </div>
    <div class="stat-card bg-blue-50 rounded-xl p-3 border border-blue-200 shadow-sm animate-in" style="animation-delay: 0.25s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] text-blue-700 uppercase tracking-wide">Avg Similarity</p>
                <p class="text-xl font-bold text-blue-700"><?php echo $average_similarity; ?>%</p>
            </div>
            <span class="material-symbols-outlined text-2xl text-blue-400">analytics</span>
        </div>
    </div>
</div>

<!-- Toggle Buttons - Internal & External -->
<div class="mb-6 flex gap-3 flex-wrap">
    <a href="plagiarism_view.php?assignment_id=<?= $assignment_id ?>&student_id=<?= $highlight_student_id ?>" 
       class="toggle-btn active px-5 py-2 rounded-full font-semibold text-sm inline-flex items-center gap-2 shadow-md">
        <span class="material-symbols-outlined text-[18px]">people</span>
        Internal Plagiarism
    </a>
    <?php if ($submission_type === 'essay'): ?>
        <a href="external_essay.php?assignment_id=<?= $assignment_id ?>&student_id=<?= $highlight_student_id ?>&external=1" 
           class="toggle-btn inactive px-5 py-2 rounded-full font-semibold text-sm inline-flex items-center gap-2 hover:shadow-md transition-all">
            <span class="material-symbols-outlined text-[18px]">public</span>
            External AI/Web Detection
        </a>
    <?php endif; ?>
</div>

<!-- Filters & Sorting -->
<div class="bg-white rounded-xl p-3 border border-gray-200 mb-6 flex flex-wrap items-center gap-3">
    <span class="text-xs font-medium text-gray-700">Filter:</span>
    <select class="filter-select" onchange="window.location.href='?assignment_id=<?php echo $assignment_id; ?>&sort=<?php echo $sort_by; ?>&page=1&status='+this.value">
        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
        <option value="High Risk" <?php echo $filter_status == 'High Risk' ? 'selected' : ''; ?>>High Risk</option>
        <option value="Attention Required" <?php echo $filter_status == 'Attention Required' ? 'selected' : ''; ?>>Attention Required</option>
        <option value="Verified" <?php echo $filter_status == 'Verified' ? 'selected' : ''; ?>>Verified</option>
    </select>
    
    <span class="text-xs font-medium text-gray-700 ml-2">Sort:</span>
    <select class="filter-select" onchange="window.location.href='?assignment_id=<?php echo $assignment_id; ?>&sort='+this.value+'&page=1&status=<?php echo $filter_status; ?>'">
        <option value="similarity_desc" <?php echo $sort_by == 'similarity_desc' ? 'selected' : ''; ?>>Highest Similarity</option>
        <option value="similarity_asc" <?php echo $sort_by == 'similarity_asc' ? 'selected' : ''; ?>>Lowest Similarity</option>
        <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
        <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
    </select>
    
    <span class="text-xs text-gray-400 ml-auto"><?php echo count($paginated_results); ?> of <?php echo $total_results; ?></span>
</div>

<!-- Results Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
<?php foreach($paginated_results as $r): ?>
<div class="plagiarism-card bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all <?php echo ($r['student_id']==$highlight_student_id)?'ring-2 ring-blue-500 shadow-lg':'';?>">
    <!-- Header -->
    <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
        <div class="flex justify-between items-start flex-wrap gap-1">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-bold text-sm text-blue-900"><?php echo htmlspecialchars($r['student_name']); ?></h3>
                    <span class="text-[10px] text-gray-500 font-mono"><?php echo htmlspecialchars($r['matric_no']); ?></span>
                    <?php if($is_penyelaras && $r['lecturer_name']): ?>
                        <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                            👨‍🏫 <?php echo htmlspecialchars($r['lecturer_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if($r['file_name'] && $r['file_name'] != 'Inline submission'): ?>
                    <div class="text-[10px] text-gray-400 mt-0.5">📄 <?php echo htmlspecialchars($r['file_name']); ?></div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php 
                    echo $r['status'] == 'Verified' ? 'risk-low' : ($r['status'] == 'Attention Required' ? 'risk-med' : 'risk-high'); 
                ?>">
                    <?php echo $r['status']; ?>
                </span>
                <span class="text-base font-bold <?php 
                    echo $r['score'] > 40 ? 'text-red-600' : ($r['score'] > 25 ? 'text-orange-600' : 'text-green-600'); 
                ?>">
                    <?php echo $r['score']; ?>%
                </span>
            </div>
        </div>
        
        <!-- Similarity Matches -->
        <?php if(!empty($r['all_matches'])): ?>
        <div class="mt-1.5">
            <span class="text-[10px] font-medium text-gray-600">Matches with:</span>
            <div class="flex flex-wrap gap-1 mt-0.5">
                <?php foreach(array_slice($r['all_matches'], 0, 5) as $match): ?>
                    <span class="match-badge">
                        <span class="sim"><?php echo $match['similarity']; ?>%</span>
                        <?php echo htmlspecialchars($match['student_name']); ?>
                    </span>
                <?php endforeach; ?>
                <?php if(count($r['all_matches']) > 5): ?>
                    <span class="text-[10px] text-gray-400">+<?php echo count($r['all_matches']) - 5; ?> more</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Submission text WITH HIGHLIGHTING -->
    <div class="px-4 py-3 text-panel" style="max-height: 200px;">
        <?php echo $r['highlighted_text']; ?>
    </div>

    <!-- Grade Recommendation -->
    <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 flex justify-between items-center flex-wrap gap-2">
        <div>
            <?php if($r['final_grade']): ?>
                <span class="text-xs text-gray-600">Grade: <strong class="text-base text-blue-800"><?php echo $r['final_grade']; ?></strong></span>
            <?php else: ?>
                <span class="text-xs text-gray-600">Recommended: <strong class="text-base text-green-700"><?php echo $r['grade']; ?></strong></span>
            <?php endif; ?>
        </div>
        <form method="POST" class="flex items-center gap-2">
            <input type="hidden" name="student_id" value="<?php echo $r['student_id']; ?>">
            <select name="approved_grade" class="border border-gray-300 rounded-full px-3 py-1 text-sm font-bold text-center bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php foreach(['A','B','C','D','F'] as $g): ?>
                <option value="<?php echo $g; ?>" <?php if($r['final_grade']==$g) echo 'selected'; ?>><?php echo $g; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-blue-800 text-white px-3 py-1 rounded-full text-xs font-bold hover:bg-blue-700 transition-all btn-hover">
                <span class="material-symbols-outlined text-[14px] align-middle">check_circle</span>
                Approve
            </button>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?assignment_id=<?php echo $assignment_id; ?>&sort=<?php echo $sort_by; ?>&status=<?php echo $filter_status; ?>&page=<?php echo $page-1; ?>">‹ Prev</a>
    <?php else: ?>
        <span class="disabled">‹ Prev</span>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i == $page): ?>
            <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
            <a href="?assignment_id=<?php echo $assignment_id; ?>&sort=<?php echo $sort_by; ?>&status=<?php echo $filter_status; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $total_pages): ?>
        <a href="?assignment_id=<?php echo $assignment_id; ?>&sort=<?php echo $sort_by; ?>&status=<?php echo $filter_status; ?>&page=<?php echo $page+1; ?>">Next ›</a>
    <?php else: ?>
        <span class="disabled">Next ›</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Legend & Algorithm Info -->
<div class="mt-6 bg-white rounded-xl p-4 border border-gray-200 shadow-sm">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <h3 class="font-bold text-gray-700 text-sm mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600 text-[18px]">info</span>
                Legend
            </h3>
            <div class="space-y-1 text-xs">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold risk-high">High Risk</span>
                    <span class="text-gray-600">&gt; <?php echo ($submission_type == 'code') ? '50%' : '40%'; ?></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold risk-med">Attention Required</span>
                    <span class="text-gray-600"><?php echo ($submission_type == 'code') ? '30-50%' : '25-40%'; ?></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold risk-low">Verified</span>
                    <span class="text-gray-600">&lt; <?php echo ($submission_type == 'code') ? '30%' : '25%'; ?></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="similarity-highlight px-2 py-0.5 text-[10px]" style="background:#ffe0e0;border-bottom:2px solid #ff9999;">Highlighted text</span>
                    <span class="text-gray-600">Matching content found</span>
                </div>
            </div>
        </div>
        <div>
            <h3 class="font-bold text-gray-700 text-sm mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-600 text-[18px]">science</span>
                Detection Details
            </h3>
            <div class="space-y-0.5 text-xs text-gray-600">
                <p>🔍 Method: <?php echo ($submission_type == 'code') ? 'Code Structure + Token Analysis' : 'Shingling (4-gram) + Word Frequency'; ?></p>
                <p>📊 Similarity: <strong>|A∩B| / |A∪B| × 100</strong></p>
                <?php if($is_penyelaras): ?>
                    <p>👁️ Comparing <strong>ALL</strong> students in this class</p>
                <?php else: ?>
                    <p>🔒 Comparing <strong>YOUR GROUP</strong> only</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</main>
</body>
</html>