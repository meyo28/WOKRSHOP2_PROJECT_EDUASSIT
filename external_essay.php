<?php
session_start();
include 'includes/config.php';
include 'includes/plagiarism_functions.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'lecturer') {
    header("Location: index.php?error=login_required");
    exit();
}

// Get assignment and student
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$student_id    = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if(!$assignment_id || !$student_id) die("Assignment or Student not selected.");

// Fetch assignment info
$stmt = $conn->prepare("SELECT tittle, type FROM assignment WHERE assignment_id=?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$assignment) die("Assignment not found.");
if($assignment['type'] !== 'essay') die("External plagiarism is only for essay submissions.");

// Fetch student essay
$stmt = $conn->prepare("SELECT es.essay, es.file_name, s.full_name, s.matric_no
                        FROM essay_submission es
                        JOIN student s ON es.student_id = s.student_id
                        WHERE es.assignment_id=? AND es.student_id=?");
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
$essay_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$essay_data) die("No essay submission found.");

$essay_text = $essay_data['essay'];

// ==========================================
// SERPAPI CONFIGURATION
// ==========================================
$serpapi_key = "";

// ==========================================
// AI DETECTION KEYS
// ==========================================
$groq_key   = "";
$gemini_key = "YOUR_ACTUAL_GEMINI_API_KEY";

// ==========================================
// FUNCTION: Extract search queries from essay
// ==========================================
function extractSearchQueries($text, $numQueries = 5) {
    $sentences = preg_split('/[.!?]+/', $text);
    $sentences = array_filter($sentences, function($s) {
        return strlen(trim($s)) > 50;
    });
    $queries = array_slice($sentences, 0, $numQueries);
    foreach ($queries as &$q) {
        $q = trim(substr($q, 0, 100));
    }
    return $queries;
}

// ==========================================
// FUNCTION: Search web using SerpApi REST API
// ==========================================
function searchWebSerpApi($query, $api_key) {
    if (empty($api_key) || $api_key == "YOUR_SERPAPI_API_KEY") {
        return ['error' => 'SerpApi key not configured'];
    }
    
    $url = "https://serpapi.com/search.json?q=" . urlencode($query) . "&api_key=" . $api_key . "&num=10";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['organic_results'])) {
            return ['organic' => $data['organic_results']];
        }
        return ['organic' => []];
    }
    
    return ['error' => "HTTP $http_code"];
}

// ==========================================
// AI Detection using Groq
// ==========================================
function detectAIGroq($text, $api_key) {
    if (empty($api_key) || strlen($api_key) < 20) {
        return [
            'error' => 'Invalid API key',
            'probability' => 'N/A',
            'model' => 'Groq (Error)'
        ];
    }
    
    $prompt = "You are an expert AI text detector. Your task is to analyze the following text and determine if it was written by an AI or a human.

    Analyze these characteristics:
    1. Perplexity: Does the text have natural word choices or is it too predictable?
    2. Burstiness: Does the sentence structure vary or is it uniform?
    3. Repetition: Are there repeated patterns or phrases?
    4. Natural flow: Does the text read like natural human writing?

    Based on your analysis, provide a score from 0 to 100 where:
    - 0-20: Definitely human-written
    - 21-40: Likely human-written
    - 41-60: Uncertain / Mixed
    - 61-80: Likely AI-generated
    - 81-100: Definitely AI-generated

    Text to analyze:
    \"\"\"$text\"\"\"

    IMPORTANT: Reply with ONLY a single number between 0 and 100. No explanation, no additional text. Just the number.";

    $payload = [
        'model'       => 'llama-3.1-8b-instant',
        'temperature' => 0.3,
        'max_tokens'  => 10,
        'messages'    => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ];
    
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['choices'][0]['message']['content'])) {
            $raw = trim($data['choices'][0]['message']['content']);
            preg_match('/\d+/', $raw, $matches);
            if (!empty($matches)) {
                $score = intval($matches[0]);
                if ($score >= 0 && $score <= 100) {
                    return [
                        'probability' => $score,
                        'raw' => $raw,
                        'model' => 'Groq (Llama 3.1 8B)',
                        'success' => true
                    ];
                }
            }
        }
    }
    
    // Fallback to simple pattern detection if API fails
    return detectAISimple($text);
}

// ==========================================
// SIMPLE FALLBACK: Keyword-Based AI Detection
// ==========================================
function detectAISimple($text) {
    $ai_patterns = [
        '/in the realm of/i',
        '/it is important to note/i',
        '/furthermore/i',
        '/moreover/i',
        '/in conclusion/i',
        '/to summarize/i',
        '/as we have seen/i',
        '/it should be noted/i',
        '/in the context of/i',
        '/on the other hand/i',
        '/nevertheless/i',
        '/consequently/i',
        '/subsequently/i',
        '/additionally/i',
        '/in particular/i',
        '/specifically/i',
        '/notably/i',
        '/significantly/i',
        '/arguably/i',
        '/undoubtedly/i',
        '/there is no doubt/i',
        '/it is clear that/i',
        '/it is evident that/i',
        '/as a result/i',
        '/due to the fact/i'
    ];
    
    $score = 0;
    
    foreach ($ai_patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            $score += 4;
        }
    }
    
    // Check for excessive transition words
    $transition_words = ['however', 'therefore', 'furthermore', 'moreover', 'consequently', 'additionally', 'subsequently', 'notably', 'significantly', 'arguably', 'undoubtedly'];
    $word_count = str_word_count($text);
    $transition_count = 0;
    
    foreach ($transition_words as $word) {
        $transition_count += substr_count(strtolower($text), $word);
    }
    
    if ($word_count > 0 && ($transition_count / $word_count) > 0.05) {
        $score += 20;
    }
    
    // Check for repetitive sentence structure
    $sentences = preg_split('/[.!?]+/', $text);
    $sentence_lengths = array_map('str_word_count', $sentences);
    if (!empty($sentence_lengths)) {
        $avg = array_sum($sentence_lengths) / count($sentence_lengths);
        $variance = 0;
        foreach ($sentence_lengths as $len) {
            $variance += pow($len - $avg, 2);
        }
        $variance /= count($sentence_lengths);
        if ($variance < 10) {
            $score += 15;
        }
    }
    
    return [
        'probability' => min(100, $score),
        'model' => 'Pattern Detection (Fallback)',
        'success' => true,
        'fallback' => true
    ];
}

// ==========================================
// KEYWORD OVERLAP SIMILARITY
// ==========================================
function keywordOverlapSimilarity($essay, $snippet) {
    $stopwords = ['the','a','an','and','or','but','in','on','at','to','for','of','with',
                  'is','are','was','were','be','been','being','have','has','had','do','does',
                  'did','will','would','could','should','may','might','that','this','these',
                  'those','it','its','by','from','as','not','if','so','than','then',
                  'when','which','who','how','what','where','they','their','there','we','our',
                  'you','your','he','she','his','her','also','can','into','about','all','more'];

    $essayLower   = strtolower($essay);
    $snippetLower = strtolower($snippet);

    preg_match_all('/[a-z]+/', $snippetLower, $sWords);
    $snippetWords = array_unique(array_filter($sWords[0], function($w) use ($stopwords) {
        return strlen($w) > 3 && !in_array($w, $stopwords);
    }));

    if (empty($snippetWords)) return 0;

    $hits = 0;
    foreach ($snippetWords as $word) {
        if (strpos($essayLower, $word) !== false) $hits++;
    }

    $score = round(($hits / count($snippetWords)) * 100);
    return min(100, $score);
}

// ==========================================
// PERFORM EXTERNAL PLAGIARISM DETECTION
// ==========================================

$search_queries = extractSearchQueries($essay_text, 5);
$web_matches = [];

// Step 1: Search web for matching content using SerpApi
if (!empty($serpapi_key) && $serpapi_key != "YOUR_SERPAPI_API_KEY") {
    foreach ($search_queries as $index => $query) {
        if ($index > 0) usleep(500000);
        
        $search_results = searchWebSerpApi($query, $serpapi_key);
        
        if (!isset($search_results['error']) && isset($search_results['organic'])) {
            foreach ($search_results['organic'] as $result) {
                $title   = $result['title']   ?? '';
                $url     = $result['link']    ?? '';
                $snippet = $result['snippet'] ?? '';

                $similarity = keywordOverlapSimilarity($essay_text, $snippet . ' ' . $title);

                if ($similarity > 5) {
                    $exists = false;
                    foreach ($web_matches as $match) {
                        if ($match['url'] == $url) { $exists = true; break; }
                    }
                    if (!$exists) {
                        $web_matches[] = [
                            'title'      => $title,
                            'url'        => $url,
                            'snippet'    => $snippet,
                            'similarity' => $similarity
                        ];
                    }
                }
            }
        }
    }
    
    usort($web_matches, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });
    $web_matches = array_slice($web_matches, 0, 10);
}

// Step 2: Detect AI generation
$ai_result = detectAIGroq($essay_text, $groq_key);
$ai_probability = isset($ai_result['probability']) ? $ai_result['probability'] : 'N/A';

// Step 3: Calculate overall similarity score
$web_similarity = 0;
if (!empty($web_matches)) {
    $total_similarity = array_sum(array_column($web_matches, 'similarity'));
    $web_similarity = round($total_similarity / count($web_matches), 2);
}

// Step 4: Generate highlighted version of essay
$highlighted_essay = htmlspecialchars($essay_text);
$stopwords = ['the','a','an','and','or','but','in','on','at','to','for','of','with',
              'is','are','was','were','be','been','being','have','has','had','do','does',
              'did','will','would','could','should','may','might','that','this','these',
              'those','it','its','by','from','as','not','if','so','than','then',
              'when','which','who','how','what','where','they','their','there'];

foreach ($web_matches as $match) {
    $source_title = addslashes(htmlspecialchars($match['title']));
    $snippet      = $match['snippet'];

    $words  = preg_split('/\s+/', $snippet);
    $phrases = [];
    for ($i = 0; $i < count($words) - 2; $i++) {
        $phrase = trim($words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2]);
        $phraseWords = explode(' ', strtolower($phrase));
        $meaningful  = array_filter($phraseWords, fn($w) => strlen($w) > 3 && !in_array($w, $stopwords));
        if (count($meaningful) >= 2) $phrases[] = $phrase;
    }
    for ($i = 0; $i < count($words) - 3; $i++) {
        $phrase = trim($words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2] . ' ' . $words[$i+3]);
        $phraseWords = explode(' ', strtolower($phrase));
        $meaningful  = array_filter($phraseWords, fn($w) => strlen($w) > 3 && !in_array($w, $stopwords));
        if (count($meaningful) >= 2) $phrases[] = $phrase;
    }

    foreach ($phrases as $phrase) {
        $pattern = '/' . preg_quote($phrase, '/') . '/i';
        if (preg_match($pattern, $highlighted_essay)) {
            $highlighted_essay = preg_replace(
                $pattern,
                '<span class="highlight-web" title="Source: ' . $source_title . '">$0</span>',
                $highlighted_essay
            );
        }
    }
}

// Determine overall risk level
if ($web_similarity > 30 || ($ai_probability !== 'N/A' && (int)$ai_probability > 70)) {
    $overall_risk = "High Risk";
    $risk_color = "red";
} elseif ($web_similarity > 15 || ($ai_probability !== 'N/A' && (int)$ai_probability > 40)) {
    $overall_risk = "Attention Required";
    $risk_color = "orange";
} else {
    $overall_risk = "Low Risk / Verified";
    $risk_color = "green";
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>External Plagiarism Report - SILS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        
        .highlight-web {
            background-color: #ffe0e0 !important;
            border-bottom: 2px solid #ff9999 !important;
            color: #990000 !important;
            padding: 2px 4px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            display: inline-block !important;
        }
        
        .highlight-web:hover {
            background-color: #ffb3b3 !important;
        }
        
        .text-panel {
            max-height: 500px;
            overflow-y: auto;
            line-height: 1.7;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .text-panel::-webkit-scrollbar {
            width: 8px;
        }
        
        .text-panel::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .text-panel::-webkit-scrollbar-thumb {
            background: #003366;
            border-radius: 4px;
        }
        
        .risk-box {
            transition: transform 0.2s;
        }
        
        .risk-box:hover {
            transform: translateY(-2px);
        }
        
        .source-card {
            transition: all 0.2s;
        }
        
        .source-card:hover {
            transform: translateX(4px);
            border-left-color: #003366 !important;
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
       class="inline-flex items-center gap-2 text-blue-800 hover:text-blue-600 font-medium">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        Back to Plagiarism Page
    </a>
</div>

<!-- Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-blue-900">External Plagiarism & AI Detection Report</h1>
    <p class="text-gray-600 mt-1">Assignment: <span class="font-semibold"><?php echo htmlspecialchars($assignment['tittle']); ?></span></p>
    <p class="text-gray-500 text-sm mt-1">Student: <?php echo htmlspecialchars($essay_data['full_name']); ?> (<?php echo htmlspecialchars($essay_data['matric_no']); ?>)</p>
</div>

<!-- Toggle Buttons -->
<div class="mb-6 flex gap-3 flex-wrap">
    <a href="plagiarism_view.php?assignment_id=<?= $assignment_id ?>&student_id=<?= $student_id ?>" 
       class="toggle-btn inactive px-5 py-2 rounded-full font-semibold text-sm inline-flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">people</span>
        Internal Plagiarism
    </a>
    <a href="external_essay.php?assignment_id=<?= $assignment_id ?>&student_id=<?= $student_id ?>&external=1" 
       class="toggle-btn active px-5 py-2 rounded-full font-semibold text-sm inline-flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">public</span>
        External AI/Web Detection
    </a>
</div>

<!-- Risk Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
    <div class="risk-box bg-white rounded-xl p-5 border-l-8 border-<?php echo $risk_color; ?>-500 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 uppercase tracking-wide">Overall Risk Level</p>
                <p class="text-2xl font-bold text-<?php echo $risk_color; ?>-600 mt-1"><?php echo $overall_risk; ?></p>
            </div>
            <span class="material-symbols-outlined text-4xl text-<?php echo $risk_color; ?>-400">gavel</span>
        </div>
    </div>
    
    <div class="risk-box bg-white rounded-xl p-5 border-l-8 border-blue-500 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 uppercase tracking-wide">Web Similarity Score</p>
                <p class="text-2xl font-bold text-blue-600 mt-1"><?php echo $web_similarity; ?>%</p>
                <p class="text-xs text-gray-400 mt-1">Based on <?php echo count($web_matches); ?> matching sources</p>
            </div>
            <span class="material-symbols-outlined text-4xl text-blue-400">public</span>
        </div>
    </div>
    
    <div class="risk-box bg-white rounded-xl p-5 border-l-8 border-purple-500 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 uppercase tracking-wide">AI Probability</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">
                    <?php echo $ai_probability !== 'N/A' ? $ai_probability . '%' : 'N/A'; ?>
                </p>
                <p class="text-xs text-gray-400 mt-1">Powered by: <?php echo isset($ai_result['model']) ? htmlspecialchars($ai_result['model']) : 'N/A'; ?></p>
                <?php if (isset($ai_result['fallback']) && $ai_result['fallback'] === true): ?>
                    <p class="text-xs text-orange-500 mt-1">⚠️ Using fallback detection</p>
                <?php endif; ?>
            </div>
            <span class="material-symbols-outlined text-4xl text-purple-400">psychology</span>
        </div>
    </div>
</div>

<!-- External Sources Found -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-8">
    <div class="bg-gray-50 px-5 py-3 border-b border-gray-200">
        <h2 class="font-bold text-blue-900 flex items-center gap-2">
            <span class="material-symbols-outlined">find_in_page</span>
            External Sources with Matching Content
        </h2>
        <p class="text-xs text-gray-500 mt-1">Sources found from web search that contain similar content to the submitted essay</p>
    </div>
    
    <?php if(empty($web_matches)): ?>
        <div class="p-8 text-center text-gray-500">
            <span class="material-symbols-outlined text-5xl mb-2 text-gray-300">verified</span>
            <p>No matching external sources found.</p>
            <p class="text-sm mt-1">The essay appears to be original based on web search.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach($web_matches as $match): ?>
            <div class="p-4 hover:bg-gray-50 transition-colors source-card border-l-4 border-transparent">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <a href="<?php echo htmlspecialchars($match['url']); ?>" target="_blank" class="font-semibold text-blue-700 hover:underline flex items-center gap-1">
                            <?php echo htmlspecialchars($match['title']); ?>
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                        </a>
                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($match['snippet']); ?></p>
                        <p class="text-xs text-gray-400 mt-1">URL: <?php echo htmlspecialchars(substr($match['url'], 0, 80)); ?>...</p>
                    </div>
                    <div class="ml-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold <?php echo $match['similarity'] > 30 ? 'bg-red-100 text-red-700' : ($match['similarity'] > 15 ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'); ?>">
                            <?php echo $match['similarity']; ?>% match
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Main Essay with Highlighting -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="bg-gray-50 px-5 py-3 border-b border-gray-200">
        <h2 class="font-bold text-blue-900 flex items-center gap-2">
            <span class="material-symbols-outlined">description</span>
            Submitted Essay with Highlighted Matches
        </h2>
        <p class="text-xs text-gray-500 mt-1">Light red highlighting indicates text that matches external web sources</p>
    </div>
    <div class="text-panel p-6 bg-white">
        <?php echo $highlighted_essay; ?>
    </div>
</div>

<!-- Detection Methodology Explanation -->
<div class="mt-8 bg-gray-100 rounded-xl p-5 border border-gray-200">
    <h3 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
        <span class="material-symbols-outlined text-blue-600">science</span>
        How Detection Works
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div class="flex gap-2">
            <span class="text-blue-600 font-bold">1.</span>
            <span class="text-gray-600">Key phrases extracted from the essay</span>
        </div>
        <div class="flex gap-2">
            <span class="text-blue-600 font-bold">2.</span>
            <span class="text-gray-600">Searched against Google via <strong>SerpApi</strong></span>
        </div>
        <div class="flex gap-2">
            <span class="text-blue-600 font-bold">3.</span>
            <span class="text-gray-600">Similarity calculated using keyword overlap analysis</span>
        </div>
        <div class="flex gap-2">
            <span class="text-blue-600 font-bold">4.</span>
            <span class="text-gray-600">AI detection performed using <strong>Groq</strong></span>
        </div>
        <div class="flex gap-2">
            <span class="text-blue-600 font-bold">5.</span>
            <span class="text-gray-600">Matching text passages highlighted for easy review</span>
        </div>
        <div class="flex gap-2">
            <span class="text-blue-600 font-bold">6.</span>
            <span class="text-gray-600">Overall risk assessment based on combined scores</span>
        </div>
    </div>
</div>

<!-- API Status Footer -->
<div class="mt-6 text-center text-xs text-gray-400 border-t border-gray-200 pt-4">
    <p>Detection powered by: 
        <?php echo ($serpapi_key && $serpapi_key != "YOUR_SERPAPI_API_KEY") ? '✅ SerpApi' : '❌ SerpApi'; ?> | 
        <?php echo ($groq_key && strlen($groq_key) > 20) ? '✅ Groq' : '❌ Groq'; ?>
    </p>
    <p class="mt-1">Similarity algorithm: Keyword overlap + Phrase matching</p>
</div>

</main>
</body>
</html>