<?php
session_start();
include 'includes/config.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
if (!$class_id) {
    header("Location: lecturer_dashboard.php");
    exit();
}

$staff_id = $_SESSION['staff_id'];
$check_sql = "SELECT c.class_name, c.class_code FROM class c 
              JOIN lecturer l ON c.lecturer = l.lecturer_id 
              WHERE l.staff_id = ? AND c.class_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("si", $staff_id, $class_id);
$check_stmt->execute();
$class_info = $check_stmt->get_result()->fetch_assoc();
if (!$class_info) die("Unauthorized access to this class.");

$assign_query = "SELECT assignment_id, tittle, type, due_date FROM assignment WHERE class_id = ? ORDER BY due_date ASC";
$assign_stmt = $conn->prepare($assign_query);
$assign_stmt->bind_param("i", $class_id);
$assign_stmt->execute();
$assignments = $assign_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Dashboard - <?php echo htmlspecialchars($class_info['class_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
        .header { background: #003366; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1400px; margin: 30px auto; padding: 20px; background: white; border-radius: 15px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #003366; text-decoration: none; }
        .assignment-card { border: 1px solid #ddd; border-radius: 12px; margin-bottom: 25px; padding: 20px; background: #fafafa; }
        .assignment-title { font-size: 20px; font-weight: 600; color: #003366; }
        .assignment-meta { color: #666; margin: 5px 0 15px 0; }
        .btn-compare { background: #8b0000; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
        .btn-compare:disabled { background: #ccc; cursor: not-allowed; }
        .results-area { display: none; margin-top: 20px; border-top: 2px solid #ddd; padding-top: 15px; }
        .comparison-pair { background: #fff9e6; border-left: 4px solid #8b0000; margin: 15px 0; padding: 15px; border-radius: 8px; }
        .pair-header { font-weight: bold; margin-bottom: 10px; }
        .side-by-side { display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap; }
        .student-side { flex: 1; min-width: 250px; background: white; padding: 12px; border-radius: 6px; border: 1px solid #ddd; }
        .student-side h5 { color: #003366; margin-bottom: 10px; }
        .student-code { font-family: monospace; background: #f4f4f4; padding: 8px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; font-size: 13px; max-height: 300px; overflow-y: auto; }
        .grade-form { margin-top: 20px; }
        .grade-input { width: 80px; padding: 5px; margin-right: 10px; }
        .btn-submit { background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
        .error-msg { color: #c00; background: #ffe6e6; padding: 8px; border-radius: 5px; margin-top: 10px; white-space: pre-wrap; }
        @media (max-width: 768px) { .side-by-side { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>📘 <?php echo htmlspecialchars($class_info['class_name']); ?> (<?php echo htmlspecialchars($class_info['class_code']); ?>)</h1>
    </div>
    <div class="container">
        <a href="lecturer_dashboard.php" class="back-link">← Back to My Classes</a>
        <h2>📝 Assignments</h2>
        <?php if (empty($assignments)): ?>
            <p>No assignments yet. <a href="create_assignment.php?class_id=<?php echo $class_id; ?>">Create one</a>.</p>
        <?php else: ?>
            <?php foreach ($assignments as $assign): ?>
                <div class="assignment-card" id="assign-<?php echo $assign['assignment_id']; ?>">
                    <div class="assignment-title"><?php echo htmlspecialchars($assign['tittle']); ?></div>
                    <div class="assignment-meta">Type: <?php echo ucfirst($assign['type']); ?> | Due: <?php echo date('d/m/Y', strtotime($assign['due_date'])); ?></div>
                    <button class="btn-compare" data-id="<?php echo $assign['assignment_id']; ?>" data-type="<?php echo $assign['type']; ?>">🔍 Compare Plagiarism</button>
                    <div class="results-area" id="results-<?php echo $assign['assignment_id']; ?>"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script>
        document.querySelectorAll('.btn-compare').forEach(btn => {
            btn.addEventListener('click', async function() {
                const assignId = this.dataset.id, type = this.dataset.type;
                const resultsDiv = document.getElementById('results-' + assignId);
                const btnEl = this;
                btnEl.disabled = true;
                btnEl.innerText = 'Processing...';
                resultsDiv.style.display = 'block';
                resultsDiv.innerHTML = '<div class="loading">Comparing submissions...</div>';
                const compareApi = (type === 'essay') ? 'api/compare_essay.php' : 'api/compare_code.php';
                try {
                    // Step 1: Run comparison
                    let resp = await fetch(compareApi, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'assignment_id=' + assignId });
                    let text = await resp.text();
                    let data;
                    try { data = JSON.parse(text); } catch(e) { resultsDiv.innerHTML = `<div class="error-msg">Invalid JSON from comparison API:<br>${escapeHtml(text.substring(0,300))}</div>`; btnEl.disabled=false; btnEl.innerText='🔍 Compare Plagiarism'; return; }
                    if (data.error) { resultsDiv.innerHTML = `<div class="error-msg">Comparison error: ${escapeHtml(data.error)}</div>`; btnEl.disabled=false; btnEl.innerText='🔍 Compare Plagiarism'; return; }
                    // Step 2: Get similarity results
                    resultsDiv.innerHTML = '<div class="loading">Loading results...</div>';
                    resp = await fetch(`api/get_similarity.php?assignment_id=${assignId}&type=${type}`);
                    text = await resp.text();
                    let simData;
                    try { simData = JSON.parse(text); } catch(e) { resultsDiv.innerHTML = `<div class="error-msg">Invalid JSON from similarity API:<br>${escapeHtml(text.substring(0,300))}</div>`; btnEl.disabled=false; btnEl.innerText='🔍 Compare Plagiarism'; return; }
                    if (simData.error) { resultsDiv.innerHTML = `<div class="error-msg">Similarity error: ${escapeHtml(simData.error)}</div>`; }
                    else { displayResults(simData, resultsDiv, type); }
                } catch(err) { resultsDiv.innerHTML = `<div class="error-msg">Request failed: ${escapeHtml(err.message)}</div>`; }
                finally { btnEl.disabled = false; btnEl.innerText = '🔍 Compare Plagiarism'; }
            });
        });
        function displayResults(data, container, type) {
            if (!data.matches || data.matches.length === 0) { container.innerHTML = '<div>✅ No significant plagiarism detected (<20% similarity).</div>'; return; }
            let html = '<h4>⚠️ Plagiarism Matches (>=20% similarity)</h4>';
            data.matches.forEach(m => { html += `
                <div class="comparison-pair">
                    <div class="pair-header">📌 Similarity: ${m.similarity}%<br>Student A: ${escapeHtml(m.student1_name)} (${escapeHtml(m.student1_matric)})<br>Student B: ${escapeHtml(m.student2_name)} (${escapeHtml(m.student2_matric)})</div>
                    <div class="side-by-side">
                        <div class="student-side"><h5>${escapeHtml(m.student1_name)}'s ${type==='essay'?'Essay':'Code'}</h5><div class="student-code">${escapeHtml(m.content1)}</div>
                        <div class="grade-form"><input type="number" step="0.01" class="grade-input" placeholder="Grade" id="g1_${m.sub1_id}"><button class="btn-submit" onclick="submitGrade(${m.sub1_id},'${type}',this)">Submit Grade</button></div></div>
                        <div class="student-side"><h5>${escapeHtml(m.student2_name)}'s ${type==='essay'?'Essay':'Code'}</h5><div class="student-code">${escapeHtml(m.content2)}</div>
                        <div class="grade-form"><input type="number" step="0.01" class="grade-input" placeholder="Grade" id="g2_${m.sub2_id}"><button class="btn-submit" onclick="submitGrade(${m.sub2_id},'${type}',this)">Submit Grade</button></div></div>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
        }
        function submitGrade(submissionId, type, btn) {
            let grade = btn.previousElementSibling.value;
            if (!grade) { alert('Enter a grade'); return; }
            if (grade<0 || grade>100) { alert('Grade 0-100'); return; }
            btn.disabled = true; btn.innerText = 'Saving...';
            fetch('grade_submission.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `submission_id=${submissionId}&type=${type}&grade=${grade}` })
            .then(r=>r.json()).then(d=>{ if(d.success){ alert('Grade saved!'); btn.previousElementSibling.disabled=true; btn.innerText='Saved'; } else { alert('Error: '+d.error); btn.disabled=false; btn.innerText='Submit Grade'; } })
            .catch(e=>{ alert('Request failed'); btn.disabled=false; btn.innerText='Submit Grade'; });
        }
        function escapeHtml(t) { if(!t) return ''; return String(t).replace(/[&<>]/g, m => m==='&'?'&amp;':m==='<'?'&lt;':'&gt;').substring(0,2000); }
    </script>
</body>
</html>