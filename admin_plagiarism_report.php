<?php
session_start();
include 'includes/config.php';

// Check if user is logged in AND is an admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: index.php?error=login_required");
    exit();
}

// ==========================================
// GET FILTER PARAMETERS (SIMPLIFIED)
// ==========================================
$filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : 0;
$filter_program = isset($_GET['filter_program']) ? $_GET['filter_program'] : 'all';
$filter_class = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : 0;
$filter_lecturer = isset($_GET['filter_lecturer']) ? (int)$_GET['filter_lecturer'] : 0;

// ==========================================
// FETCH FILTER OPTIONS
// ==========================================
$classes_list = mysqli_query($conn, "SELECT class_id, class_name, class_code FROM class ORDER BY class_name ASC");
$lecturers_list = mysqli_query($conn, "SELECT lecturer_id, full_name, staff_id FROM lecturer ORDER BY full_name ASC");

// ==========================================
// BUILD WHERE CLAUSE
// ==========================================
$where = ["1=1"];

if ($filter_year > 0) $where[] = "YEAR(pr.created_at) = $filter_year";
if ($filter_program != 'all') $where[] = "s.program = '" . mysqli_real_escape_string($conn, $filter_program) . "'";
if ($filter_class > 0) $where[] = "c.class_id = $filter_class";
if ($filter_lecturer > 0) $where[] = "l.lecturer_id = $filter_lecturer";

$where_sql = implode(" AND ", $where);

// ==========================================
// CORE QUERY
// ==========================================
$sql_plagiarism = "
    SELECT 
        pr.report_id, pr.similarity_percentage, pr.source_type, pr.matched_source_title,
        pr.submission_type, pr.created_at,
        s.student_id, s.full_name AS student_name, s.matric_no, s.program,
        c.class_id, c.class_code, c.class_name,
        l.lecturer_id, l.full_name AS lecturer_name
    FROM plagiarism_report pr
    LEFT JOIN essay_submission es ON pr.submission_id = es.id AND pr.submission_type = 'essay'
    LEFT JOIN code_submission cs ON pr.submission_id = cs.id AND pr.submission_type = 'code'
    JOIN student s ON s.student_id = COALESCE(es.student_id, cs.student_id)
    JOIN assignment a ON a.assignment_id = COALESCE(es.assignment_id, cs.assignment_id)
    JOIN class c ON c.class_id = a.class_id
    LEFT JOIN lecturer l ON l.lecturer_id = a.lecturer_id
    WHERE $where_sql
    ORDER BY pr.similarity_percentage DESC, pr.created_at DESC
";
$plagiarism_result = mysqli_query($conn, $sql_plagiarism);

$cases = [];
while ($row = mysqli_fetch_assoc($plagiarism_result)) {
    $cases[] = $row;
}

// ==========================================
// KPI CALCULATIONS
// ==========================================
$total_cases = count($cases);
$high_risk = 0;
$medium_risk = 0;
$low_risk = 0;
$sum_sim = 0;
$source_count = ['internal' => 0, 'web' => 0, 'scholar' => 0];
$program_count = [];
$lecturer_count = [];
$class_count = [];
$monthly_trend = [];

foreach ($cases as $c) {
    $sim = (float)$c['similarity_percentage'];
    $sum_sim += $sim;

    if ($sim >= 60) $high_risk++;
    elseif ($sim >= 30) $medium_risk++;
    else $low_risk++;

    if (isset($source_count[$c['source_type']])) $source_count[$c['source_type']]++;

    $prog = $c['program'] ?: 'Unknown';
    $program_count[$prog] = ($program_count[$prog] ?? 0) + 1;

    $lect = $c['lecturer_name'] ?: 'Unassigned';
    $lecturer_count[$lect] = ($lecturer_count[$lect] ?? 0) + 1;

    $cls = $c['class_code'] ?: 'Unknown';
    $class_count[$cls] = ($class_count[$cls] ?? 0) + 1;

    $month = date('Y-m', strtotime($c['created_at']));
    $monthly_trend[$month] = ($monthly_trend[$month] ?? 0) + 1;
}
ksort($monthly_trend);

$avg_similarity = $total_cases > 0 ? round($sum_sim / $total_cases, 1) : 0;
$high_risk_pct = $total_cases > 0 ? round(($high_risk / $total_cases) * 100, 1) : 0;

arsort($lecturer_count);
$top_lecturers = array_slice($lecturer_count, 0, 5, true);
arsort($class_count);
$top_classes = array_slice($class_count, 0, 5, true);

$years_result = mysqli_query($conn, "SELECT DISTINCT YEAR(created_at) as y FROM plagiarism_report ORDER BY y DESC");
$available_years = [];
while ($row = mysqli_fetch_assoc($years_result)) $available_years[] = $row['y'];

$active_filters = 0;
foreach ([$filter_year, $filter_class, $filter_lecturer] as $f) if ($f > 0) $active_filters++;
if ($filter_program != 'all') $active_filters++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Integrity Report - FTMK - EDUASSIST Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1e293b; }

        .header {
            background: linear-gradient(135deg, #003366 0%, #1a4d8c 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-left { display: flex; align-items: center; gap: 20px; }
        .header-left h1 { font-size: 22px; font-weight: 700; }
        .header-left .sub { font-size: 13px; opacity: 0.85; font-weight: 400; }
        .header-right { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .back-btn {
            color: white; text-decoration: none; font-weight: 500;
            background: rgba(255,255,255,0.15); padding: 6px 16px; border-radius: 8px;
            transition: background 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;
        }
        .back-btn:hover { background: rgba(255,255,255,0.25); }
        .badge-role { background: rgba(255,255,255,0.15); padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; border: 1px solid rgba(255,255,255,0.1); }

        .container { max-width: 1450px; margin: 25px auto; padding: 0 20px; }

        .filter-bar { background: white; border-radius: 14px; padding: 18px 22px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 140px; }
        .filter-group label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group select { padding: 8px 12px; border: 1.5px solid #e1e5ee; border-radius: 8px; font-family: inherit; font-size: 13px; outline: none; background: white; transition: border-color 0.2s; }
        .filter-group select:focus { border-color: #003366; }
        .filter-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-filter { background: #003366; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.2s; }
        .btn-filter:hover { background: #1a4d8c; }
        .btn-reset { background: #f1f3f5; color: #555; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.2s; text-decoration: none; }
        .btn-reset:hover { background: #e2e6ea; }
        .filter-badge { background: #e8f0fe; color: #003366; padding: 2px 12px; border-radius: 16px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 4px solid #003366; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .stat-card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500; }
        .stat-card .number { font-size: 24px; font-weight: 700; color: #003366; margin-top: 4px; }
        .stat-card.ok .number { color: #16a34a; }
        .stat-card.warn .number { color: #d97706; }
        .stat-card.danger .number { color: #dc2626; }

        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .chart-container { background: white; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .chart-container h3 { font-size: 14px; font-weight: 700; color: #003366; margin-bottom: 12px; border-bottom: 2px solid #f0f2f5; padding-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .chart-container canvas { max-height: 240px; width: 100% !important; }
        .chart-full { grid-column: 1 / -1; }

        .callout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .callout-box { background: white; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .callout-box h3 { font-size: 14px; font-weight: 700; color: #003366; margin-bottom: 12px; border-bottom: 2px solid #f0f2f5; padding-bottom: 8px; }
        .callout-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13px; border-bottom: 1px dashed #eef1f5; }
        .callout-row:last-child { border-bottom: none; }
        .callout-row .count { font-weight: 700; color: #003366; background: #e8f0fe; padding: 1px 10px; border-radius: 10px; font-size: 12px; }

        .table-container { background: white; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; overflow-x: auto; }
        .table-container h3 { font-size: 14px; font-weight: 700; color: #003366; margin-bottom: 12px; border-bottom: 2px solid #f0f2f5; padding-bottom: 8px; display: flex; align-items: center; gap: 8px; justify-content: space-between; }
        .table-container h3 .count-tag { font-size: 11px; background: #f1f3f5; color: #64748b; padding: 3px 10px; border-radius: 10px; font-weight: 600; }

        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th, td { padding: 9px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; color: #1e293b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; }
        tr:hover { background: #f8fafc; }
        .text-center { text-align: center; }

        .sim-bar-wrap { display: flex; align-items: center; gap: 8px; min-width: 110px; }
        .sim-bar-bg { flex: 1; height: 8px; border-radius: 4px; background: #f1f3f5; overflow: hidden; }
        .sim-bar-fill { height: 100%; border-radius: 4px; }
        .sim-pct { font-weight: 700; font-size: 12px; min-width: 38px; }

        .risk-high { color: #dc2626; }
        .risk-medium { color: #d97706; }
        .risk-low { color: #16a34a; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-high { background: #fee2e2; color: #991b1b; }
        .badge-medium { background: #fef3c7; color: #92400e; }
        .badge-low { background: #dcfce7; color: #166534; }
        .badge-internal { background: #e0e7ff; color: #1e40af; }
        .badge-web { background: #e0f2fe; color: #0369a1; }
        .badge-scholar { background: #fae8ff; color: #a21caf; }

        .matched-title { max-width: 280px; display: block; font-size: 11.5px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
        }
        .btn-pdf {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
        }
        .btn-pdf:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
        }
        .btn-print {
            background: #003366;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0, 51, 102, 0.3);
        }
        .btn-print:hover {
            background: #1a4d8c;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 51, 102, 0.4);
        }

        .no-data { text-align: center; padding: 40px; color: #888; }
        .no-data .icon { font-size: 48px; color: #ccc; margin-bottom: 10px; display: block; }

        .footer-note { text-align: center; font-size: 11px; color: #94a3b8; padding: 15px; border-top: 1px solid #e2e8f0; margin-top: 20px; }

        /* Print Styles */
        @media print {
            .filter-bar, .action-buttons, .back-btn, .header-right { display: none !important; }
            .stat-card, .chart-container, .table-container, .callout-box { box-shadow: none !important; border: 1px solid #ddd; page-break-inside: avoid; }
            .header { background: #003366 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge, .sim-bar-fill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .badge-role { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .chart-container canvas { max-height: 180px; }
            .stats-grid { page-break-inside: avoid; }
            .callout-grid { page-break-inside: avoid; }
            .table-container { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }

        @media (max-width: 900px) {
            .chart-grid, .callout-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .header { flex-direction: column; text-align: center; }
            .header-left { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn-pdf, .action-buttons .btn-print { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="header-left">
        <a href="admin_dashboard.php" class="back-btn">
            <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
            Dashboard
        </a>
        <div>
            <h1>🛡️ Academic Integrity Report</h1>
            <div class="sub">Faculty of Information and Communication Technology • Plagiarism &amp; Similarity Monitoring</div>
        </div>
    </div>
    <div class="header-right">
        <span class="badge-role">👑 Deputy Dean</span>
        <span class="badge-role">📅 <?php echo $filter_year > 0 ? $filter_year : 'All Years'; ?></span>
    </div>
</header>

<div class="container">

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 12px; width: 100%; align-items: flex-end;">

            <div class="filter-group" style="flex: 0.5; min-width: 120px;">
                <label>📅 Year</label>
                <select name="filter_year">
                    <option value="0" <?php echo $filter_year == 0 ? 'selected' : ''; ?>>All Years</option>
                    <?php foreach ($available_years as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($filter_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group" style="flex: 0.5; min-width: 120px;">
                <label>🎓 Program</label>
                <select name="filter_program">
                    <option value="all" <?php echo ($filter_program == 'all') ? 'selected' : ''; ?>>All Programs</option>
                    <option value="BITS" <?php echo ($filter_program == 'BITS') ? 'selected' : ''; ?>>BITS</option>
                    <option value="BITD" <?php echo ($filter_program == 'BITD') ? 'selected' : ''; ?>>BITD</option>
                </select>
            </div>

            <div class="filter-group">
                <label>📚 Class</label>
                <select name="filter_class">
                    <option value="0">All Classes</option>
                    <?php mysqli_data_seek($classes_list, 0); while($row = mysqli_fetch_assoc($classes_list)): ?>
                        <option value="<?php echo $row['class_id']; ?>" <?php echo ($filter_class == $row['class_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($row['class_code'] . ' - ' . $row['class_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>👨‍🏫 Lecturer</label>
                <select name="filter_lecturer">
                    <option value="0">All Lecturers</option>
                    <?php mysqli_data_seek($lecturers_list, 0); while($row = mysqli_fetch_assoc($lecturers_list)): ?>
                        <option value="<?php echo $row['lecturer_id']; ?>" <?php echo ($filter_lecturer == $row['lecturer_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($row['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">🔍 Apply</button>
                <a href="admin_plagiarism_report.php" class="btn-reset">✕ Reset</a>
            </div>

            <?php if ($active_filters > 0): ?>
            <div style="width: 100%; margin-top: 4px;">
                <span class="filter-badge">📌 <?php echo $active_filters; ?> filter(s) applied</span>
            </div>
            <?php endif; ?>

        </form>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <button onclick="window.print()" class="btn-print">
            <span class="material-symbols-outlined">print</span>
            Print / Save as PDF
        </button>
    </div>

    <?php if ($total_cases == 0): ?>
        <div class="no-data">
            <span class="icon">📭</span>
            <h3>No Flagged Submissions Found</h3>
            <p>No plagiarism cases match the selected filters. Try widening your filter criteria.</p>
        </div>
    <?php else: ?>

    <!-- ===== KPI STATS ===== -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">🚩 Total Flagged Cases</div>
            <div class="number"><?php echo $total_cases; ?></div>
        </div>
        <div class="stat-card danger">
            <div class="label">🔴 High Risk (≥60%)</div>
            <div class="number"><?php echo $high_risk; ?> <span style="font-size:13px;">(<?php echo $high_risk_pct; ?>%)</span></div>
        </div>
        <div class="stat-card warn">
            <div class="label">🟠 Medium Risk (30-59%)</div>
            <div class="number"><?php echo $medium_risk; ?></div>
        </div>
        <div class="stat-card ok">
            <div class="label">🟢 Low Risk (&lt;30%)</div>
            <div class="number"><?php echo $low_risk; ?></div>
        </div>
        <div class="stat-card">
            <div class="label">📊 Avg. Similarity</div>
            <div class="number"><?php echo $avg_similarity; ?>%</div>
        </div>
        <div class="stat-card">
            <div class="label">🔗 Internal Matches</div>
            <div class="number"><?php echo $source_count['internal']; ?></div>
        </div>
    </div>

    <!-- ===== CHARTS ===== -->
    <div class="chart-grid">
        <div class="chart-container">
            <h3>⚠️ Risk Severity Distribution</h3>
            <canvas id="riskChart"></canvas>
        </div>
        <div class="chart-container">
            <h3>🌐 Flagged Cases by Source Type</h3>
            <canvas id="sourceChart"></canvas>
        </div>
        <div class="chart-container">
            <h3>🎓 Flagged Cases by Program</h3>
            <canvas id="programChart"></canvas>
        </div>
        <div class="chart-container">
            <h3>📈 Plagiarism Cases Trend</h3>
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- ===== CALLOUTS: TOP LECTURERS / CLASSES ===== -->
    <div class="callout-grid">
        <div class="callout-box">
            <h3>👨‍🏫 Lecturers with Most Flagged Submissions</h3>
            <?php if (empty($top_lecturers)): ?>
                <p style="color:#888; font-size:13px;">No data.</p>
            <?php else: foreach ($top_lecturers as $name => $cnt): ?>
                <div class="callout-row">
                    <span><?php echo htmlspecialchars($name); ?></span>
                    <span class="count"><?php echo $cnt; ?> case<?php echo $cnt > 1 ? 's' : ''; ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <div class="callout-box">
            <h3>📚 Classes with Most Flagged Submissions</h3>
            <?php if (empty($top_classes)): ?>
                <p style="color:#888; font-size:13px;">No data.</p>
            <?php else: foreach ($top_classes as $name => $cnt): ?>
                <div class="callout-row">
                    <span><?php echo htmlspecialchars($name); ?></span>
                    <span class="count"><?php echo $cnt; ?> case<?php echo $cnt > 1 ? 's' : ''; ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- ===== DETAILED CASE TABLE ===== -->
    <div class="table-container">
        <h3>
            <span>🔍 Flagged Submission Details</span>
            <span class="count-tag"><?php echo $total_cases; ?> result(s)</span>
        </h3>
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Matric No.</th>
                    <th>Class</th>
                    <th class="text-center">Type</th>
                    <th>Similarity</th>
                    <th class="text-center">Risk</th>
                    <th class="text-center">Source</th>
                    <th>Matched With</th>
                    <th>Date Flagged</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cases as $c):
                    $sim = (float)$c['similarity_percentage'];
                    if ($sim >= 60) { $risk_label = 'High'; $risk_class = 'badge-high'; $bar_color = '#dc2626'; }
                    elseif ($sim >= 30) { $risk_label = 'Medium'; $risk_class = 'badge-medium'; $bar_color = '#d97706'; }
                    else { $risk_label = 'Low'; $risk_class = 'badge-low'; $bar_color = '#16a34a'; }
                    $source_badge = 'badge-' . $c['source_type'];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['student_name']); ?></strong><br>
                        <span style="font-size:11px; color:#94a3b8;"><?php echo htmlspecialchars($c['program']); ?></span></td>
                    <td><?php echo htmlspecialchars($c['matric_no']); ?></td>
                    <td><?php echo htmlspecialchars($c['class_code']); ?></td>
                    <td class="text-center"><?php echo $c['submission_type'] == 'essay' ? '📝 Essay' : '💻 Code'; ?></td>
                    <td>
                        <div class="sim-bar-wrap">
                            <div class="sim-bar-bg"><div class="sim-bar-fill" style="width:<?php echo min($sim,100); ?>%; background:<?php echo $bar_color; ?>;"></div></div>
                            <span class="sim-pct" style="color:<?php echo $bar_color; ?>;"><?php echo $sim; ?>%</span>
                        </div>
                    </td>
                    <td class="text-center"><span class="badge <?php echo $risk_class; ?>"><?php echo $risk_label; ?></span></td>
                    <td class="text-center"><span class="badge <?php echo $source_badge; ?>"><?php echo ucfirst($c['source_type']); ?></span></td>
                    <td><span class="matched-title" title="<?php echo htmlspecialchars($c['matched_source_title']); ?>"><?php echo htmlspecialchars($c['matched_source_title']); ?></span></td>
                    <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <div class="footer-note">
        Report generated by EDUASSIST - Student Integrity &amp; Learning System | FTMK, UTeM<br>
        Generated on: <?php echo date('j F Y, g:i A'); ?> | For: Deputy Dean (Academic)
    </div>

</div>

<script>
// ===== Risk Severity Donut =====
new Chart(document.getElementById('riskChart'), {
    type: 'doughnut',
    data: {
        labels: ['High Risk (≥60%)', 'Medium Risk (30-59%)', 'Low Risk (<30%)'],
        datasets: [{
            data: [<?php echo $high_risk; ?>, <?php echo $medium_risk; ?>, <?php echo $low_risk; ?>],
            backgroundColor: ['#dc2626', '#f59e0b', '#16a34a'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, cutout: '55%', plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

// ===== Source Type Bar =====
new Chart(document.getElementById('sourceChart'), {
    type: 'bar',
    data: {
        labels: ['Internal', 'Web', 'AI'],
        datasets: [{
            label: 'Cases',
            data: [<?php echo $source_count['internal']; ?>, <?php echo $source_count['web']; ?>, <?php echo $source_count['scholar']; ?>],
            backgroundColor: ['#1e40af', '#0369a1', '#7c3aed'],
            borderRadius: 6
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

// ===== Program Bar =====
const programLabels = <?php echo json_encode(array_keys($program_count)); ?>;
const programValues = <?php echo json_encode(array_values($program_count)); ?>;
new Chart(document.getElementById('programChart'), {
    type: 'bar',
    data: { labels: programLabels, datasets: [{ label: 'Flagged Cases', data: programValues, backgroundColor: '#003366', borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

// ===== Monthly Trend Line =====
const trendLabels = <?php echo json_encode(array_keys($monthly_trend)); ?>;
const trendValues = <?php echo json_encode(array_values($monthly_trend)); ?>;
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Flagged Cases',
            data: trendValues,
            borderColor: '#003366',
            backgroundColor: 'rgba(0,51,102,0.1)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#003366'
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>

</body>
</html>